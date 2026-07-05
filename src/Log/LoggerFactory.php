<?php
declare(strict_types=1);

namespace HL7v2\Log;

use Monolog\Logger;
use Monolog\Level;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class LoggerFactory
{
    public static function createOutputLogger(string $name = 'hl7'): Logger
    {
        $logger = new Logger($name);

        $handler = new StreamHandler(
            'php://output',
            Level::Debug
        );

        $handler->setFormatter(
            new LineFormatter(
                "[%datetime%] %level_name%: %message%<br>\n",
                'Y-m-d H:i:s',
                true,
                true
            )
        );

        $logger->pushHandler($handler);

        return $logger;
    }

    public static function createFileLogger(string $filename, string $name = 'hl7'): Logger
    {
        $logger = new Logger($name);

        $handler = new StreamHandler(
            $filename,
            Level::Debug
        );

        $handler->setFormatter(
            new LineFormatter(
                "[%datetime%] %level_name%: %message%\n",
                'Y-m-d H:i:s',
                true,
                true
            )
        );

        $logger->pushHandler($handler);

        return $logger;
    }
}
