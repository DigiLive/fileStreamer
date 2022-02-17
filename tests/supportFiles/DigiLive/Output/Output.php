<?php

declare(strict_types=1);

namespace DigiLive\Output;

/**
 * Mimic the output buffer.
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
abstract class Output
{
    /**
     * @var array Captured Headers which would have been sent to the output buffer.
     */
    public static array $headers = [];
    /**
     * @var string|null Captured body which would have been sent to the output buffer.
     */
    public static ?string $body;

    /**
     * Clear the captured output headers and body.
     *
     * @return void
     */
    public static function reset()
    {
        self::$headers = [];
        self::$body    = null;
        @ob_clean();
    }

    /**
     * Get the captured body.
     *
     * @return string|null
     */
    public static function getBody(): ?string
    {
        Output::$body .= ob_get_contents();
        ob_clean();

        return self::$body;
    }

    /**
     * Wrapper function to include function overrides.
     *
     * To override functions, the overrides need to be declared somewhere.
     * To keep this more versatile and tidy, overrides and their namespace should be declared in a separate php file.
     *
     * @param   string  $functionsFile  Path of php file which contains the function overrides.
     *
     * @return void
     */
    public static function getOverrides(string $functionsFile): void
    {
        require $functionsFile;
    }
}
