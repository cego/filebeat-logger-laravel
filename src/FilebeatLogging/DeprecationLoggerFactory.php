<?php

namespace Cego\FilebeatLogging;

use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Cego\FilebeatLoggerFactory;

class DeprecationLoggerFactory extends FilebeatLoggerFactory
{
    /**
     * @param array<string, mixed> $config
     */
    public function __invoke(array $config): Logger
    {
        $logger = parent::__invoke($config);

        // Add a processor that converts all log levels to INFO as we dont want deprecation logs to flood our logs
        $logger->pushProcessor(function (LogRecord $record): LogRecord {
            return $record->with(level: Level::Info);
        });

        return $logger;
    }
}
