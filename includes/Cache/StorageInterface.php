<?php

namespace MyProCache\Cache;

interface StorageInterface
{
    public function get( string $key ): ?array;

    public function set( string $key, array $payload, array $tags = array(), ?int $ttl = null ): bool;

    public function delete( string $key ): void;

    public function clear(): void;

    public function purge_tags( array $tags ): void;

    public function purge_uri( string $uri ): void;
}
