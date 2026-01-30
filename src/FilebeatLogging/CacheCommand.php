<?php

namespace Cego\FilebeatLogging;

use Illuminate\Console\Command;

class CacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'filebeat-logger:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm filebeat logger laravel device detector cache';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        PreloadCache::warm();

        return 0;
    }
}
