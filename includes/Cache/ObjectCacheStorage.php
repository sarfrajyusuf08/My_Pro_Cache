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

class ObjectCacheStorage implements StorageInterface
{
    protected Manager $options;

    protected Logger $logger;

    protected string $group = 'my_pro_cache_pages';

    protected string $tagGroup = 'my_pro_cache_tags';

    protected string $uriGroup = 'my_pro_cache_uris';

    protected string $indexKey = 'my_pro_cache_keys';

    public function __construct( Manager $options, Logger $logger )
    {
        $this->options = $options;
        $this->logger  = $logger;
    }

    public static function is_available(): bool
    {
        return function_exists( 'wp_cache_set' ) && wp_using_ext_object_cache();
    }

    public function get( string $key ): ?array
    {
        $hash = sha1( $key );
        $data = wp_cache_get( $hash, $this->group );

        return is_array( $data ) ? $data : null;
    }

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

    public function delete( string $key ): void
    {
        $this->delete_by_hash( sha1( $key ) );
    }

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

