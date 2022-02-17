<?php
/**
 * Functions overrides for the DigiLive\Output package.
 *
 * Headers and output which would normally be sent to php's output buffer, are caught in this class.
 *
 * PHP version 7.4 or greater
 *
 * @package         DigiLive\Output
 * @author          Ferry Cools <info@DigiLive.nl>
 * @copyright   (c) 2022 Ferry Cools
 * @version         1.0.0
 * @license         New BSD License http://www.opensource.org/licenses/bsd-license.php
 * @todo            Get this package from composer.
 */
declare(strict_types=1);

namespace DigiLive\FileStreamer;

// Above namespace must be set the same as the namespace which calls the defined functions below.

use DigiLive\Output\Output;

/**
 * headers_sent will return false if no HTTP headers have already been sent or true otherwise.
 *
 * @return false
 * @see \headers_sent()
 */
function headers_sent(): bool
{
    return false;
}

/**
 * Capture a raw HTTP header.
 *
 * Note:
 * Unlike the default header function, a captured similar header will not be replaced.
 * A new header is captured instead.
 *
 * @param   string  $value  The header string.
 *
 * @return void
 * @see \header()
 */
function header(string $value)
{
    Output::$headers[] = $value;
}

/**
 * Capture a formatted string.
 *
 * @param   string  $format
 * @param   mixed   ...$values
 *
 * @return void
 * @see \printf()
 */
function printf(string $format, ...$values)
{
    Output::$body .= sprintf($format, ...$values);
}

/**
 * Capture the body of the output buffer.
 *
 * @return void
 * @see \ob_get_contents();
 */
function ob_flush(): void
{
    Output::$body .= ob_get_contents();
    ob_clean();
}

/**
 * Flush system output buffer.
 *
 * Since the output buffer is captured, try to flush this buffer should do noting.
 *
 * @return void
 * @see \flush()
 */
function flush(): void
{
}
