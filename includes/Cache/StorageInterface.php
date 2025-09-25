<?php

namespace MyProCache\Cache;

/**
 * Contract for cache storage engines used by My Pro Cache.
 */
interface StorageInterface
{
    /**
     * Retrieves a cached payload when it exists for the provided key.
     */
    public function get( string $key ): ?array;

    /**
     * Stores a payload with optional tags and TTL metadata.
     */
    public function set( string $key, array $payload, array $tags = array(), ?int $ttl = null ): bool;

    /**
     * Removes a cached entry by its logical key.
     */
    public function delete( string $key ): void;

    /**
     * Flushes all cached entries managed by the storage backend.
     */
    public function clear(): void;

    /**
     * Invalidates cached entries associated with the supplied tags.
     */
    public function purge_tags( array $tags ): void;

    /**
     * Invalidates cached entries associated with the specified URI.
     */
    public function purge_uri( string $uri ): void;
}
