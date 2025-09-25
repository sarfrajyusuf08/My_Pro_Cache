<?php

namespace MyProCache\Cache;

use MyProCache\Options\Manager;
use MyProCache\Debug\Logger;
use function array_diff;
use function array_values;
use function in_array;
use function is_array;
use function sha1;
use function wp_cache_delete;
use function wp_cache_get;
use function wp_cache_set;
use function wp_using_ext_object_cache;

/**
 * Storage backend that utilises WordPress object cache APIs for caching responses.
 * Provides shared behaviour for Redis and Memcached adapters.
 */
class ObjectCacheStorage implements StorageInterface
{
    protected Manager $options;

    protected Logger $logger;

    protected string $group = 'my_pro_cache_pages';

    protected string $tagGroup = 'my_pro_cache_tags';

    protected string $uriGroup = 'my_pro_cache_uris';

    protected string $indexKey = 'my_pro_cache_keys';

    /**
     * Stores the option manager and logger for later cache operations.
     */
    public function __construct( Manager $options, Logger $logger )
    {
        $this->options = $options;
        $this->logger  = $logger;
    }

    /**
     * Checks whether WordPress is currently using an external object cache.
     */
    public static function is_available(): bool
    {
        return function_exists( 'wp_cache_set' ) && wp_using_ext_object_cache();
    }

    /**
     * Retrieves a cached record by key hash from the object cache group.
     */
    public function get( string $key ): ?array
    {
        $hash = sha1( $key );
        $data = wp_cache_get( $hash, $this->group );

        return is_array( $data ) ? $data : null;
    }

    /**
     * Writes the response payload into the object cache and updates indices.
     */
    public function set( string $key, array $payload, array $tags = array(), ?int $ttl = null ): bool
    {
        $hash = sha1( $key );
        $record = array(
            'key'     => $key,
            'uri'     => $payload['uri'] ?? '',
            'created' => time(),
            'ttl'     => $ttl,
            'headers' => $payload['headers'] ?? array(),
            'tags'    => $tags,
            'status'  => $payload['status'] ?? 200,
            'content' => $payload['content'] ?? '',
        );

        wp_cache_set( $hash, $record, $this->group, $ttl ?? 0 );
        $this->index_key( $hash );
        $this->index_tags( $hash, $tags );
        if ( ! empty( $record['uri'] ) ) {
            $this->index_uri( $hash, $record['uri'] );
        }

        return true;
    }

    /**
     * Deletes a cached entry using its logical key.
     */
    public function delete( string $key ): void
    {
        $this->delete_by_hash( sha1( $key ) );
    }

    /**
     * Clears every stored record and resets supporting indices.
     */
    public function clear(): void
    {
        $keys = wp_cache_get( $this->indexKey, $this->group );
        if ( is_array( $keys ) ) {
            foreach ( $keys as $hash ) {
                wp_cache_delete( $hash, $this->group );
            }
        }

        wp_cache_delete( $this->indexKey, $this->group );
        wp_cache_delete( $this->indexKey, $this->tagGroup );
        wp_cache_delete( $this->indexKey, $this->uriGroup );
    }

    /**
     * Removes cached entries associated with the supplied tags.
     */
    public function purge_tags( array $tags ): void
    {
        foreach ( $tags as $tag ) {
            $tag_hash = sha1( $tag );
            $keys     = wp_cache_get( $tag_hash, $this->tagGroup );
            if ( ! is_array( $keys ) ) {
                continue;
            }
            foreach ( $keys as $hash ) {
                $this->delete_by_hash( $hash );
            }
            wp_cache_delete( $tag_hash, $this->tagGroup );
        }
    }

    /**
     * Removes cached entries cached for a specific URI.
     */
    public function purge_uri( string $uri ): void
    {
        $uri_hash = sha1( $uri );
        $keys     = wp_cache_get( $uri_hash, $this->uriGroup );
        if ( ! is_array( $keys ) ) {
            return;
        }
        foreach ( $keys as $hash ) {
            $this->delete_by_hash( $hash );
        }
        wp_cache_delete( $uri_hash, $this->uriGroup );
    }

    /**
     * Maintains an index of all cache hashes for full clear operations.
     */
    protected function index_key( string $hash ): void
    {
        $keys = wp_cache_get( $this->indexKey, $this->group );
        if ( ! is_array( $keys ) ) {
            $keys = array();
        }
        if ( ! in_array( $hash, $keys, true ) ) {
            $keys[] = $hash;
        }
        wp_cache_set( $this->indexKey, $keys, $this->group );
    }

    /**
     * Associates a cache hash with each tag for targeted purging.
     */
    protected function index_tags( string $hash, array $tags ): void
    {
        foreach ( $tags as $tag ) {
            $tag_hash = sha1( $tag );
            $keys     = wp_cache_get( $tag_hash, $this->tagGroup );
            if ( ! is_array( $keys ) ) {
                $keys = array();
            }
            if ( ! in_array( $hash, $keys, true ) ) {
                $keys[] = $hash;
            }
            wp_cache_set( $tag_hash, $keys, $this->tagGroup );
        }
    }

    /**
     * Associates a cache hash with a URI for direct invalidation.
     */
    protected function index_uri( string $hash, string $uri ): void
    {
        $uri_hash = sha1( $uri );
        $keys     = wp_cache_get( $uri_hash, $this->uriGroup );
        if ( ! is_array( $keys ) ) {
            $keys = array();
        }
        if ( ! in_array( $hash, $keys, true ) ) {
            $keys[] = $hash;
        }
        wp_cache_set( $uri_hash, $keys, $this->uriGroup );
    }

    /**
     * Removes a cached record and cleans up its tag/URI indices.
     */
    protected function delete_by_hash( string $hash ): void
    {
        $record = wp_cache_get( $hash, $this->group );
        if ( is_array( $record ) ) {
            if ( ! empty( $record['tags'] ) ) {
                foreach ( (array) $record['tags'] as $tag ) {
                    $tag_hash = sha1( $tag );
                    $keys     = wp_cache_get( $tag_hash, $this->tagGroup );
                    if ( is_array( $keys ) ) {
                        $keys = array_values( array_diff( $keys, array( $hash ) ) );
                        if ( empty( $keys ) ) {
                            wp_cache_delete( $tag_hash, $this->tagGroup );
                        } else {
                            wp_cache_set( $tag_hash, $keys, $this->tagGroup );
                        }
                    }
                }
            }
            if ( ! empty( $record['uri'] ) ) {
                $uri_hash = sha1( $record['uri'] );
                $keys     = wp_cache_get( $uri_hash, $this->uriGroup );
                if ( is_array( $keys ) ) {
                    $keys = array_values( array_diff( $keys, array( $hash ) ) );
                    if ( empty( $keys ) ) {
                        wp_cache_delete( $uri_hash, $this->uriGroup );
                    } else {
                        wp_cache_set( $uri_hash, $keys, $this->uriGroup );
                    }
                }
            }
        }

        wp_cache_delete( $hash, $this->group );
    }
}

