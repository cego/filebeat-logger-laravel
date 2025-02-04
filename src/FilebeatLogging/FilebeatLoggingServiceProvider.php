<?php

namespace Cego\FilebeatLogging;

use JsonException;
use Cego\FilebeatLoggerFactory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Debug\ExceptionHandler;

class FilebeatLoggingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @throws JsonException
     *
     * @return void
     */
    public function register(): void
    {
        $config = [
            'driver'               => 'custom',
            'channel'              => 'filebeat',
            'extras'               => json_decode(env('FILEBEAT_LOGGER_EXTRAS', '{}'), associative: true, flags: JSON_THROW_ON_ERROR),
            'stream'               => env('FILEBEAT_LOGGER_STREAM', 'php://stderr'),
            'rotating'             => env('FILEBEAT_LOGGER_ROTATING', false),
            'httpContextProcessor' => RequestProcessor::class,
            'via'                  => FilebeatLoggerFactory::class,
        ];

        Config::set('logging.channels.filebeat', $config);

        $this->app->bind(ExceptionHandler::class, LoggerExceptionHandler::class);
    }
}
