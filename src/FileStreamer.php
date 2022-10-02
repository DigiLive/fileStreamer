<?php

declare(strict_types=1);

namespace DigiLive\FileStreamer;

use finfo;
use RuntimeException;
use SplFileInfo;

/**
 * FileStreamer
 *
 * A library for streaming a local file to a client.
 * Supports inline and attachment disposition, single file download and resumable, single/multiple range downloads.
 *
 * PHP version 7.4 or greater
 *
 * @package         DigiLive\FileStreamer
 * @author          Ferry Cools <info@DigiLive.nl>
 * @copyright   (c) 2022 Ferry Cools
 * @license         New BSD License http://www.opensource.org/licenses/bsd-license.php
 * @version         1.0.0
 * @link            https://github.com/DigiLive/fileStreamer
 */
class FileStreamer
{
    private const CONTENT_INLINE     = 0;
    private const CONTENT_ATTACHMENT = 1;
    private const CONTENT_PARTIAL    = 2;
    private const RANGE_INVALID      = 4;

    /**
     * @var bool True for inline disposition. False for attachment disposition.
     */
    private bool $inline = false;
    /**
     * @var SplFileInfo Details of the requested file.
     * @see SplFileInfo
     */
    private SplFileInfo $fileInfo;
    /**
     * @var int Delay in ms to slow down sending the file content to the client.
     *          Increase this value when serving the file hogs up the systems resources.
     */
    private int $delay;
    /**
     * @var array The ranges in bytes which are extracted from the http request headers.
     *            Each element contains a range indicated by a start- and an end byte.
     */
    private array $fileRanges;

    /**
     * @var array Contains the formats to create content for a multipart file download.
     *            A multipart file download is identified by having multiple byte-ranges in the http request headers.
     */
    private array $multipartFormats;
    /**
     * @var false|resource Handle to the file to serve.
     */
    private $filePointer;
    /**
     * @var string|null If not null, it defines type mimetype of the file to download.
     */
    private ?string $mimeType = null;

    /**
     * Class Constructor.
     *
     * Any trailing slash of the given path is stripped from the string parameter.
     * Optionally you can set a delay time to prevent hogging up the system resources.
     *
     * @param   string  $filePath  Path to the file to serve.
     * @param   int     $delay     Delay in micro second.
     */
    public function __construct(string $filePath, int $delay = 0)
    {
        $this->fileInfo = new SplFileInfo(trim(rtrim($filePath), '\/'));
        $this->delay    = $delay;
    }

    /**
     * Set the disposition of the file stream to inline.
     *
     * This way the library acts like a proxy server for the requested file.
     * A value of false will set the disposition to attachment.
     *
     * @param   bool  $inline  True to enable inline disposition.
     *
     * @return void
     */
    public function setInline(bool $inline = true): void
    {
        $this->inline = $inline;
    }

    /**
     * Set the mimetype of to file to serve.
     *
     * When not null, it overrides getting the mimetype automatically when the headers are sent.
     *
     * @param   string|null  $mimeType  the mimetype of the file to serve.
     *
     * @return void
     */
    public function setMimeType(?string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }

    /**
     * Start serving the file.
     *
     * @return void
     */
    public function start()
    {
        $fileName = $this->fileInfo->getFilename();

        $this->filePointer = @fopen($this->fileInfo->getPathname(), 'rb');
        if (!$this->filePointer || !flock($this->filePointer, LOCK_SH | LOCK_NB)) {
            throw new RuntimeException("File $fileName is currently not available!");
        }

        $this->disableCompression();
        $this->getRequestedRanges();

        switch (count($this->fileRanges)) {
            case 0:
                $this->sendFile();
                break;
            case 1:
                $this->sendSingleRange();
                break;
            default:
                $this->sendMultipleRanges();
        }

        // Serving the file finished successfully.
        if (@fclose($this->filePointer) === false) {
            trigger_error("An error occurred while closing file $fileName!", E_USER_WARNING);
        }

        $this->terminate('File served');
    }

    /**
     * Disable output compression of the servers.
     *
     * Note:
     * Browser compression can only be disabled when php runs as an apache module. For other php modes, you'll
     * need to disable the compression in your server configuration or .htaccess file.
     *
     * @return void
     */
    private function disableCompression(): void
    {
        if (PHP_SAPI == 'apache2handler' && @apache_setenv('no-gzip', '1') === false) {
            trigger_error('An error occurred while disabling output compression of the webserver!', E_USER_WARNING);
        }
        if (@ini_set('zlib.output_compression', 'Off') === false) {
            // PHP Unit test will always trigger this error. Suppress with @ while testing.
            trigger_error('An error occurred while disabling output compression of the php server!', E_USER_WARNING);
        }
    }

    /**
     * Get the requested ranges from the headers of the request.
     *
     * One or multiple ranges are extracted and sanitized from the request header.
     * - A range start is always lte 0 and end.
     * - A range end is always lt the filesize.
     *
     * If these conditions are not met after sanitation, the script sends a http 416 error and stops execution.
     */
    private function getRequestedRanges(): void
    {
        /*
         * Valid ranges are:
         * bytes=0-500                  // The first 500 bytes.
         * bytes=-500                   // The last 500 bytes, not 0-500!
         * bytes=500-                   // From byte 500 tot the end.
         * bytes=0-500,1000-1499,-200   // The first 500 bytes, From byte 1000 to 1499 and the last 200 bytes.
         */

        if (!isset($_SERVER['HTTP_RANGE'])) {
            $this->fileRanges = [];

            return;
        }

        $fileRanges = [];
        $fileEnd    = $this->fileInfo->getSize() - 1;
        [$rangeUnit, $requestedRanges] = explode('=', $_SERVER['HTTP_RANGE'], 2);

        if ($rangeUnit != 'bytes') {
            $this->sendHeaders(self::RANGE_INVALID);
        }

        // Sanitize requested ranges.
        $requestedRanges = explode(',', $requestedRanges);
        foreach ($requestedRanges as $range) {
            [$start, $end] = explode('-', $range);

            if ($start == '') {
                // bytes=-500 The last 500 bytes, not 0-500!
                $start = $fileEnd - $end + 1;
                $end   = $fileEnd;
            }

            if ($end == '') {
                // bytes=500- From byte 500 to the end.
                $end = $fileEnd;
            }

            $start = (int)max($start, 0);
            $end   = (int)min($end, $fileEnd);

            if ($start > $end) {
                $this->sendHeaders(self::RANGE_INVALID);
            }

            $fileRanges[] = [
                'start' => $start,
                'end'   => $end,
            ];
        }

        $this->fileRanges = $fileRanges;
    }

    /**
     * Send appropriate raw HTTP headers.
     *
     * Remember that headers must be sent before any actual output is sent, either by normal HTML tags, blank lines in
     * a file, or from PHP.
     *
     * @param   int  $type  Type of headers to send.
     *
     * @return void
     */
    private function sendHeaders(int $type): void
    {
        if ($type == self::RANGE_INVALID) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            $this->terminate();
        }

        // Get file mimetype.
        $filePath = $this->fileInfo->getPathname();
        $fileSize = $this->fileInfo->getSize();
        $mimeType = $this->mimeType;
        if (null === $mimeType) {
            $fileInfo = new finfo();
            $mimeType = @$fileInfo->file($filePath, FILEINFO_MIME_TYPE);
        }

        // Caching headers as IE6 workaround.
        header('Pragma: public');
        header('Expires: -1');
        header('Cache-Control: public, must-revalidate, post-check=0, pre-check=0');

        // Range header.
        header('Accept-Ranges: bytes');

        // Content headers.
        $contentType = 'Content-Type: ' . $mimeType ?: 'application/octet-stream';
        header($contentType);
        header('Content-Transfer-Encoding: binary');
        header("Content-Disposition: attachment; filename=\"{$this->fileInfo->getFilename()}\"");
        header("Content-Length: $fileSize");

        switch ($type) {
            case self::CONTENT_INLINE:
                header('Content-Disposition: inline');
                break;
            case self::CONTENT_PARTIAL:
                header('HTTP/1.1 206 Partial Content');

                $rangeCount = count($this->fileRanges);

                // Single range.
                if ($rangeCount == 1) {
                    header('Content-Length: ' . ($this->fileRanges[0]['end'] - $this->fileRanges[0]['start'] + 1));
                    header(
                        sprintf(
                            'Content-Range: bytes %d-%d/%d',
                            $this->fileRanges[0]['start'],
                            $this->fileRanges[0]['end'],
                            $fileSize
                        )
                    );

                    return;
                }

                // Multiple ranges.
                $contentLength = $rangeCount * 38;                                   // boundaryStart
                $contentLength += $rangeCount * strlen($contentType . "\r\n");       // contentType
                $contentLength += 40;                                                // boundaryEnd

                // Calculate the content length of the parted download.
                foreach ($this->fileRanges as $range) {
                    $contentLength += strlen(
                        sprintf(
                            $this->multipartFormats['rangeFormat'],
                            $range['start'],
                            $range['end'],
                            $fileSize
                        )
                    );
                    $contentLength += $range['end'] - $range['start'] + 1;
                }

                header("Content-Length: $contentLength");
                header('Content-Type: multipart/byteranges; boundary=' . $this->multipartFormats['rangeBoundary']);
        }
    }

    /**
     * Terminate the current script with exit message/code.
     *
     * Terminates execution of the script. Shutdown functions and object destructors will always be executed.
     * If status is a string, this function prints the status just before exiting.
     * If status is an int, that value will be used as the exit status and not printed. Exit statuses should be in the
     * range 0 to 254, the exit status 255 is reserved by PHP and shall not be used. The status 0 is used to terminate
     * the program successfully.
     *
     * @param   $status
     *
     * @return void
     */
    public function terminate($status = null): void
    {
        exit($status);
    }

    /**
     * Stream the complete file to the client.
     *
     * @return void
     */
    private function sendFile()
    {
        $this->sendHeaders($this->inline ? self::CONTENT_INLINE : self::CONTENT_ATTACHMENT);
        $this->flush(0, $this->fileInfo->getSize());
    }

    /**
     * Flush buffered content of the file to the client.
     *
     * The start and end of the content are defined by the methods parameters.
     * The values of the parameters are treated as number of bytes from the beginning of the file to serve.
     *
     * @param   int  $start  Start of the content.
     * @param   int  $end    End of the content.
     *
     * @return void
     */
    private function flush(int $start, int $end): void
    {
        $done = false;

        fseek($this->filePointer, $start);

        while (!$done && connection_status() == CONNECTION_NORMAL) {
            ini_set('max_execution_time', '30');
            echo @fread($this->filePointer, min(1024, $end - $start + 1));
            ob_flush();
            flush();
            $done = feof($this->filePointer) || ftell($this->filePointer) >= $end;
            usleep($this->delay);
        }
    }

    /**
     * Send a single range of file content to the client.
     *
     * @return void
     */
    private function sendSingleRange(): void
    {
        $this->sendHeaders(self::CONTENT_PARTIAL);
        $this->flush($this->fileRanges[0]['start'], $this->fileRanges[0]['end']);
    }

    /**
     * Send multiple ranges of file content to the client.
     *
     * @return void
     */
    private function sendMultipleRanges(): void
    {
        $fileInfo = new finfo();
        $mimeType = @$fileInfo->file($this->fileInfo->getPathname(), FILEINFO_MIME_TYPE);
        $fileSize = $this->fileInfo->getSize();

        $this->setMultipartFormats();
        $this->sendHeaders(self::CONTENT_PARTIAL);

        foreach ($this->fileRanges as $range) {
            echo $this->multipartFormats['boundaryStart'];
            echo 'Content-Type: ' . ($mimeType ?: 'application/octet-stream') . "\r\n";
            echo sprintf($this->multipartFormats['rangeFormat'], $range['start'], $range['end'], $fileSize);
            $this->flush($range['start'], $range['end']);
        }
        echo $this->multipartFormats['boundaryEnd'];
    }

    /**
     * Create the boundary and range strings for multipart downloads.
     *
     * @return void
     */
    private function setMultipartFormats(): void
    {
        $rangeBoundary          = md5($this->fileInfo->getPathname());
        $this->multipartFormats = [
            'rangeBoundary' => "$rangeBoundary",
            'boundaryStart' => "\r\n--$rangeBoundary\r\n",
            'boundaryEnd'   => "\r\n--$rangeBoundary--\r\n",
            'rangeFormat'   => "Content-range: bytes %d-%d/%d\r\n\r\n",
        ];
    }
}
