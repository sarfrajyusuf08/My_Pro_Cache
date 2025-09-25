<?php

namespace MyProCache\Cache;

use MyProCache\Options\Manager;
use MyProCache\Debug\Logger;
use function class_exists;
use function extension_loaded;

/**
 * Storage adapter that proxies cache reads/writes through WordPress object cache when backed by Memcached.
 */
class MemcachedStorage extends ObjectCacheStorage
{
    /**
     * Passes configuration to the shared object-cache storage implementation.
     */
    public function __construct( Manager $options, Logger $logger )
    {
        parent::__construct( $options, $logger );
    }

    /**
     * Confirms Memcached support exists before selecting this adapter.
     */
    public static function is_available(): bool
    {
        return parent::is_available() && ( class_exists( '\\Memcached' ) || extension_loaded( 'memcached' ) );
    }
}
