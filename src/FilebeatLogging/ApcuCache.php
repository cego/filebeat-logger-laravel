<?php

namespace Cego\FilebeatLogging;

use DeviceDetector\Cache\CacheInterface;

class ApcuCache implements CacheInterface
{
    public function fetch(string $id)
    {
        return apcu_fetch($id);
    }

    public function contains(string $id): bool
    {
        return apcu_exists($id);
    }

    public function save(string $id, $data, int $lifeTime = 0): bool
    {
        return apcu_store($id, $data, $lifeTime);
    }

    public function delete(string $id): bool
    {
        return apcu_delete($id);
    }

    public function flushAll(): bool
    {
        return apcu_clear_cache();
    }
}
