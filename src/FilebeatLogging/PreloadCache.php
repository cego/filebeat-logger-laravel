<?php

namespace Cego\FilebeatLogging;

use DeviceDetector\DeviceDetector;
use DeviceDetector\Cache\CacheInterface;

class PreloadCache implements CacheInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public readonly string $path;

    /**
     * @var bool
     */
    private bool $isWarming;

    /**
     * @param bool $isWarming
     */
    public function __construct(bool $isWarming = false)
    {
        $this->path = app()->bootstrapPath('cache/device-detector.php');
        $this->isWarming = $isWarming;

        if (! $isWarming && file_exists($this->path)) {
            $this->data = require $this->path;
        }
    }

    /**
     * @param string $id
     *
     * @return mixed
     */
    public function fetch(string $id): mixed
    {
        return $this->data[$id] ?? null;
    }

    /**
     * @param string $id
     *
     * @return bool
     */
    public function contains(string $id): bool
    {
        return array_key_exists($id, $this->data);
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
        if ($this->isWarming) {
            $this->data[$id] = $data;

            return true;
        }

        return false;
    }

    /**
     * @param string $id
     *
     * @return bool
     */
    public function delete(string $id): bool
    {
        unset($this->data[$id]);

        return true;
    }

    /**
     * @return bool
     */
    public function flushAll(): bool
    {
        $this->data = [];

        return true;
    }

    /**
     * Persists the cache to disk
     *
     * @return void
     */
    public function persist(): void
    {
        if ($this->isWarming) {
            file_put_contents($this->path, '<?php return ' . var_export($this->data, true) . ';');
        }
    }

    /**
     * @return void
     */
    public static function warm(): void
    {
        $cache = new self(true);
        $deviceDetector = new DeviceDetector('WARMING_CACHE_' . uniqid());
        $deviceDetector->setCache($cache);
        $deviceDetector->parse();
        $cache->persist();
    }
}