<?php

namespace Cego\FilebeatLogging;

use Cego\FilebeatLoggerFactory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Debug\ExceptionHandler;

class FilebeatLoggingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        Config::set('logging.channels.filebeat', [
            'driver'  => 'custom',
            'channel' => sprintf('%s - %s', env('APP_NAME'), env('APP_ENV')),
            'via'     => FilebeatLoggerFactory::class
        ]);

        $this->app->bind(ExceptionHandler::class, LoggerExceptionHandler::class);
    }
}
