<?php

namespace MyProCache\Cache;

use MyProCache\Options\Manager;
use MyProCache\Debug\Logger;
use function class_exists;
use function extension_loaded;

class MemcachedStorage extends ObjectCacheStorage
{
    public function __construct( Manager $options, Logger $logger )
    {
        parent::__construct( $options, $logger );
    }

    public static function is_available(): bool
    {
        return parent::is_available() && ( class_exists( '\\Memcached' ) || extension_loaded( 'memcached' ) );
    }
}
