<?php

namespace Cego\FilebeatLogging;

use DeviceDetector\DeviceDetector;
use DeviceDetector\Cache\CacheInterface;

class PreloadCache implements CacheInterface
{
    /**
     * @var array<string, mixed>
     */
    private static array $data = [];

    /**
     * @var string
     */
    private string $path;

    public function __construct()
    {
        $this->path = app()->bootstrapPath('cache/device-detector.php');

        if (empty(self::$data) && file_exists($this->path)) {
            self::$data = require $this->path;
        }
    }

    /**
     * @param string $id
     *
     * @return mixed
     */
    public function fetch(string $id): mixed
    {
        return self::$data[$id] ?? null;
    }

    /**
     * @param string $id
     *
     * @return bool
     */
    public function contains(string $id): bool
    {
        return array_key_exists($id, self::$data);
    }

    /**
     * @param string $id
     * @param mixed $data
     * @param int $lifeTime
     *
     * @return bool
     */
    public function save(string $id, $data, int $lifeTime = 0): bool
    {
        self::$data[$id] = $data;

        return true;
    }

    /**
     * @param string $id
     *
     * @return bool
     */
    public function delete(string $id): bool
    {
        unset(self::$data[$id]);

        return true;
    }

    /**
     * @return bool
     */
    public function flushAll(): bool
    {
        self::$data = [];

        return true;
    }

    /**
     * @return void
     */
    public static function warm(): void
    {
        $cache = new self();
        $deviceDetector = new DeviceDetector('WARMING_CACHE_' . uniqid());
        $deviceDetector->setCache($cache);
        $deviceDetector->parse();
        file_put_contents($cache->path, '<?php return ' . var_export(self::$data, true) . ';');

        if (function_exists('opcache_compile_file')) {
            opcache_compile_file($cache->path);
        }
    }
}