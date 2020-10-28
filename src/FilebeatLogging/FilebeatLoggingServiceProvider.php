<?php

namespace Cego\FilebeatLogging;

use Cego\FilebeatLoggerFactory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class FilebeatLoggingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        Config::set('logging.channels.filebeat', [
            'driver'  => 'custom',
            'channel' => sprintf('%s - %s', env('APP_NAME'), env('APP_ENV')),
            'via'     => FilebeatLoggerFactory::class
        ]);
    }
}
