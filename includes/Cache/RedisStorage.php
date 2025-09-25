<?php

namespace MyProCache\Cache;

use MyProCache\Options\Manager;
use MyProCache\Debug\Logger;
use function class_exists;
use function extension_loaded;

/**
 * Storage adapter that leverages the object cache API when Redis is the backing engine.
 */
class RedisStorage extends ObjectCacheStorage
{
    /**
     * Invokes the base object-cache storage constructor with dependency instances.
     */
    public function __construct( Manager $options, Logger $logger )
    {
        parent::__construct( $options, $logger );
    }

    /**
     * Reports whether Redis classes or extensions are present for usage.
     */
    public static function is_available(): bool
    {
        return parent::is_available() && ( class_exists( '\\Redis' ) || extension_loaded( 'redis' ) );
    }
}
