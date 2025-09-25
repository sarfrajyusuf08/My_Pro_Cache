<?php

namespace MyProCache\Cache;

use MyProCache\Options\Manager;
use MyProCache\Debug\Logger;

/**
 * Chooses an appropriate cache storage implementation based on plugin settings.
 */
class StorageFactory
{
    /**
     * Returns a storage adapter instance, falling back to disk when others are unavailable.
     */
    public static function create( Manager $options, Logger $logger ): StorageInterface
    {
        $backend = $options->get( 'cache_backend', 'disk' );

        switch ( $backend ) {
            case 'redis':
                if ( class_exists( RedisStorage::class ) && RedisStorage::is_available() ) {
                    return new RedisStorage( $options, $logger );
                }
                $logger->log( 'cache', 'Redis backend not available. Falling back to disk cache.' );
                return new DiskStorage( $options );
            case 'memcached':
                if ( class_exists( MemcachedStorage::class ) && MemcachedStorage::is_available() ) {
                    return new MemcachedStorage( $options, $logger );
                }
                $logger->log( 'cache', 'Memcached backend not available. Falling back to disk cache.' );
                return new DiskStorage( $options );
            case 'disk':
            default:
                return new DiskStorage( $options );
        }
    }
}
