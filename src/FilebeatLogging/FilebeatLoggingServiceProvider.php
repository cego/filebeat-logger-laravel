<?php

namespace Cego\FilebeatLogging;

use JsonException;
use Cego\FilebeatLoggerFactory;
use Monolog\Handler\ProcessHandler;
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
            'driver'   => 'custom',
            'channel'  => 'filebeat',
            'extras'   => json_decode(env('FILEBEAT_LOGGER_EXTRAS', '{}'), $assoc = true, $depth = 512, JSON_THROW_ON_ERROR),
            'stream'   => env('FILEBEAT_LOGGER_STREAM', 'php://stdout'),
            'rotating' => env('FILEBEAT_LOGGER_ROTATING', false),
            'via'      => FilebeatLoggerFactory::class,
        ];

        if($this->isOctane()) {
            $config['handler'] = new ProcessHandler('cat >> /proc/1/fd/1');
        }

        Config::set('logging.channels.filebeat', $config);

        /* @phpstan-ignore-next-line */
        $this->app->bind(ExceptionHandler::class, LoggerExceptionHandler::class);
    }

    /** Detect if application is running octane */
    protected function isOctane(): bool
    {
        return isset($_SERVER['LARAVEL_OCTANE']) && ((int)$_SERVER['LARAVEL_OCTANE'] === 1);
    }
}
