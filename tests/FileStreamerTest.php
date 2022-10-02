<?php /** @noinspection PhpUndefinedFieldInspection */

/** @noinspection PhpUndefinedMethodInspection */

declare(strict_types=1);

namespace DigiLive\FileStreamer\Tests;

use DigiLive\FileStreamer\FileStreamer;
use DigiLive\Output\Output;
use DigiLive\Reflector\Reflector;
use Exception;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamException;
use org\bovigo\vfs\vfsStreamWrapper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit test for class DigiLive\FileStreamer.
 *
 * PHP version 7.4 or greater
 *
 * @package         DigiLive\FileStreamer\Tests
 * @author          Ferry Cools <info@DigiLive.nl>
 * @copyright   (c) 2022 Ferry Cools
 * @license         New BSD License http://www.opensource.org/licenses/bsd-license.php
 * @version         1.0.0
 * @link            https://github.com/DigiLive/fileStreamer
 */
class FileStreamerTest extends TestCase
{
    /**
     * Defines the headers which are always sent by the FileStreamer class.
     * Some could be overridden at a real download, but the Output class will append instead of override.
     */
    private const defaultHeaders = [
        'Pragma: public',
        'Expires: -1',
        'Cache-Control: public, must-revalidate, post-check=0, pre-check=0',
        'Accept-Ranges: bytes',
        'Content-Type: text/plain',                                  // Could be overridden at real download.
        'Content-Transfer-Encoding: binary',
        'Content-Disposition: attachment; filename="dummyFile.txt"', // Could be overridden at real download.
        'Content-Length: 10',                                        // Could be overridden at real download.
    ];

    /**
     * Instantiate mocks required for this test case.
     *
     * Mocks include a filesystem and an output buffer.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        vfsStream::setup('source', null, ['dummyFile.txt' => '0123456789']);
        Output::getOverrides('./supportFiles/OutputFunctions.php');
        Output::reset();
    }

    /**
     * Clear mocks after the last test of this test class is run.
     *
     * @throws vfsStreamException When unregistering the mocked filesystem fails.
     */
    public static function tearDownAfterClass(): void
    {
        vfsStreamWrapper::unregister();
        Output::reset();
    }

    /**
     * Reset the collected output and headers after each test.
     *
     * @return void
     */
    public function tearDown(): void
    {
        Output::reset();
    }

    /**
     * Test if range values get extracted from the ranges as defined in the http request header.
     *
     * @dataProvider provideValidRanges
     *
     * @param   string  $RequestedRange  A valid range.
     * @param   array   $expectedRange   Expected range extracted from the valid range.
     *
     * @return void
     */
    public function testGetRequestedRanges(string $RequestedRange, array $expectedRange)
    {
        $_SERVER['HTTP_RANGE'] = $RequestedRange;
        $fileStreamer          = new FileStreamer(vfsStream::url('source/dummyFile.txt'));
        $reflector             = Reflector::create($fileStreamer);

        $reflector->getRequestedRanges();

        $this->assertEquals($expectedRange, $reflector->fileRanges);
        $reflector->unset();
    }

    /**
     * Test if the correct headers are set when requesting an invalid range.
     *
     * @dataProvider provideInvalidRanges
     *
     * @param   string  $RequestedRange  An invalid range.
     *
     * @return void
     */
    public function testRequestedRangesInvalid(string $RequestedRange)
    {
        $_SERVER['HTTP_RANGE'] = $RequestedRange;

        // Mock the class to override the terminate method.
        /** @var FileStreamer $mock */
        $mock      = $this->mockFileStreamer();
        $reflector = Reflector::create($mock);

        try {
            $reflector->getRequestedRanges();
            $this->fail();
        } catch (Exception $e) {
            $this->assertCount(1, Output::$headers);
            $this->assertContains('HTTP/1.1 416 Requested Range Not Satisfiable', Output::$headers);
        }

        $reflector->unset();
    }

    /**
     * Mock the FileStreamer class to override the terminate method.
     *
     * The terminate method contains an exit statement which will abort the execution of php.
     * To prevent this, this method is overridden and will only raise an exception instead.
     *
     * @return MockObject The mocked FileDownload class.
     */
    private function mockFileStreamer(): MockObject
    {
        $mock = $this->getMockBuilder(FileStreamer::class)
                     ->onlyMethods(['terminate'])
                     ->setConstructorArgs([vfsStream::url('source/dummyFile.txt')])
                     ->getMock();
        $mock->method('terminate')->willThrowException(new Exception('Mocked'));

        return $mock;
    }

    /**
     * Test if the correct data is being served according to the requested range.
     *
     * @dataProvider provideValidRanges
     *
     * @param   string  $RequestedRange   A valid range.
     * @param   array   $expectedRange    Unused.
     * @param   string  $expectedOutput   Expected content which is sent to the outputbuffer.
     * @param   array   $expectedHeaders  Expected headers which are sent to the outputbuffer.
     *
     * @return void
     */
    public function testStartSingleRange(
        string $RequestedRange,
        array $expectedRange,
        string $expectedOutput,
        array $expectedHeaders
    ): void {
        // Skip multi-range dataset.
        if ($this->dataName() == 'multiRange') {
            $this->markTestSkipped('Test skipped.');
        }

        $_SERVER['HTTP_RANGE'] = $RequestedRange;
        $expectedHeaders       = array_merge(self::defaultHeaders, $expectedHeaders);

        // Get a mocked instance of the class with overridden terminate method.
        /** @var FileStreamer $mock */
        $mock = $this->mockFileStreamer();

        try {
            // Suppress warning or method will be aborted.
            @$mock->start();
            $this->fail();
        } catch (Exception $e) {
            $this->assertSame($expectedOutput, Output::getBody());
            $this->assertSame($expectedHeaders, Output::$headers);
        }
    }

    /**
     * Test if the correct data is being served according to the requested ranges.
     *
     * @return void
     */
    public function testStartMultiRange(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=2-3,5-6,-1';
        $expectedHeaders       = [
            'HTTP/1.1 206 Partial Content',
            'Content-Length: 330',
            'Content-Type: multipart/byteranges; boundary=cafd507eba83d389029d38c0cbe92dc5',
        ];
        $expectedHeaders       = array_merge(self::defaultHeaders, $expectedHeaders);
        $expectedOutput        = <<<'TXT'

--cafd507eba83d389029d38c0cbe92dc5
Content-Type: text/plain
Content-range: bytes 2-3/10

23
--cafd507eba83d389029d38c0cbe92dc5
Content-Type: text/plain
Content-range: bytes 5-6/10

56
--cafd507eba83d389029d38c0cbe92dc5
Content-Type: text/plain
Content-range: bytes 9-9/10

9
--cafd507eba83d389029d38c0cbe92dc5--

TXT;
        // Ensure the line separators match \r\n.
        $expectedOutput = preg_replace('~\R~u', "\r\n", $expectedOutput);

        // Get a mocked instance of the class with overridden terminate method.
        /** @var FileStreamer $mock */
        $mock = $this->mockFileStreamer();

        try {
            // Suppress warning or method will be aborted.
            @$mock->start();
            $this->fail();
        } catch (Exception $e) {
            $this->assertSame($expectedOutput, Output::getBody());
            $this->assertSame($expectedHeaders, Output::$headers);
        }
    }

    /**
     * Test if the correct data is being served when no range is requested.
     * Also, it tests setting a custom Content-Type.
     *
     * @return void
     */
    public function testStartNoRange(): void
    {
        unset($_SERVER['HTTP_RANGE']);
        // Get a mocked instance of the class with overridden terminate method.
        /** @var FileStreamer $mock */
        $mock = $this->mockFileStreamer();

        // Attachment disposition Test.
        try {
            // Suppress warning or method will be aborted.
            @$mock->start();
        } catch (Exception $e) {
            // Test headers.
            $this->assertSame(self::defaultHeaders, Output::$headers);
        }

        // Inline disposition Test.
        Output::reset();
        $mock->setInline(true);
        $mock->setMimeType('digilive/test');

        $expectedHeaders = array_merge(self::defaultHeaders, ['Content-Disposition: inline']);
        $expectedHeaders[4] = 'Content-Type: digilive/test';

        try {
            // Suppress warning or method will be aborted.
            @$mock->start();
        } catch (Exception $e) {
            // Test headers.
            $this->assertSame($expectedHeaders, Output::$headers);
            $this->assertSame('0123456789', Output::getBody());
        }
    }

    /**
     * Valid ranges are:
     * bytes=0-500                  // The first 500 bytes.
     * bytes=-500                   // The last 500 bytes, not 0-500!
     * bytes=500-                   // From byte 500 tot the end.
     * bytes=0-500,1000-1499,-200   // The first 500 bytes, From byte 1000 to 1499 and the last 200 bytes.
     *
     * @return array[] Valid ranges and the expected start/end bytes extracted from these ranges.
     */
    public function provideValidRanges(): array
    {
        return [
            'singleRange' => [
                'bytes=3-7',
                [['start' => 3, 'end' => 7]],
                '34567',
                [
                    'HTTP/1.1 206 Partial Content',
                    'Content-Length: 5',
                    'Content-Range: bytes 3-7/10',
                ],
            ],
            'endRange'    => [
                'bytes=-3',
                [['start' => 7, 'end' => 9]],
                '789',
                [
                    'HTTP/1.1 206 Partial Content',
                    'Content-Length: 3',
                    'Content-Range: bytes 7-9/10',
                ],
            ],
            'startRange'  => [
                'bytes=3-',
                [['start' => 3, 'end' => 9]],
                '3456789',
                [
                    'HTTP/1.1 206 Partial Content',
                    'Content-Length: 7',
                    'Content-Range: bytes 3-9/10',
                ],
            ],
            'multiRange'  => [
                'bytes=2-4,5-7,-2',
                [
                    ['start' => 2, 'end' => 4],
                    ['start' => 5, 'end' => 7],
                    ['start' => 8, 'end' => 9],
                ],
                '23456789',
                [],
            ],
        ];
    }

    /**
     * Invalid ranges are:
     * - Unit isn't bytes.
     * - Start is greater than end.
     *
     * @return string[][] Invalid ranges.
     */
    public function provideInvalidRanges(): array
    {
        return [
            'invalidUnit'        => [
                'invalid=3-7',
            ],
            'invalidStartSingle' => [
                'bytes=9-7',
            ],
            'invalidStartMulti'  => [
                'bytes=2-4,7-5,-2',
            ],
        ];
    }
}
