<?php

namespace App\Helpers;

class Logger
{
    /**
     * Write an informational message.
     */
    public static function info(
        string $message,
        array $context = [],
        string $module = 'general'
    ): void {
        self::write('INFO', $message, $context, $module);
    }

    /**
     * Write a warning message.
     */
    public static function warning(
        string $message,
        array $context = [],
        string $module = 'general'
    ): void {
        self::write('WARNING', $message, $context, $module);
    }

    /**
     * Write an error message.
     */
    public static function error(
        string $message,
        array $context = [],
        string $module = 'general'
    ): void {
        self::write('ERROR', $message, $context, $module);
    }

    /**
     * Write a log entry to the module's daily log file.
     */
    private static function write(
        string $level,
        string $message,
        array $context,
        string $module
    ): void {
        // Only allow predictable module names in filenames.
        $module = strtolower($module);

        if (!preg_match('/^[a-z0-9_-]+$/', $module)) {
            $module = 'general';
        }

        // Project root: barber-backend/
        $directory = dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . 'storage'
            . DIRECTORY_SEPARATOR
            . 'logs';

        if (
            !is_dir($directory)
            && !@mkdir($directory, 0750, true)
            && !is_dir($directory)
        ) {
            error_log(
                "[$level] Unable to create HairConnect log directory. "
                . $message
            );
            return;
        }

        if (!is_writable($directory)) {
            error_log(
                "[$level] HairConnect log directory is not writable. "
                . $message
            );
            return;
        }

        $timestamp = date(DATE_ATOM);

        $record = [
            'timestamp' => $timestamp,
            'level' => $level,
            'module' => $module,
            'message' => $message,
            'context' => $context,
        ];

//        $json = json_encode(
//            $record,
//            JSON_UNESCAPED_SLASHES
//            | JSON_INVALID_UTF8_SUBSTITUTE
//        );

        $json = json_encode(
            $record,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($json === false) {
            error_log(
                "[$level] Unable to encode HairConnect log entry."
            );
            return;
        }

        $filename = $directory
            . DIRECTORY_SEPARATOR
            . $module
            . '-'
            . date('Y-m-d')
            . '.log';

        $written = @file_put_contents(
            $filename,
            $json . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );

        if ($written === false) {
            error_log(
                "[$level] Unable to write HairConnect log entry."
            );
        }
    }
}