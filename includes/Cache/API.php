<?php

namespace MyProCache\Cache;

use MyProCache\Options\Manager;
use MyProCache\Debug\Logger;
use function apply_filters;
use function array_slice;
use function array_unshift;
use function date_i18n;
use function get_option;
use function is_array;
use function number_format_i18n;
use function update_option;

class API
{
    private static ?Manager $options = null;

    private static ?StorageInterface $storage = null;

    private static ?Logger $logger = null;

    public static function init( Manager $options, StorageInterface $storage, Logger $logger ): void
    {
        self::$options = $options;
        self::$storage = $storage;
        self::$logger  = $logger;
    }

    public static function get( string $key ): ?array
    {
        return self::$storage ? self::$storage->get( $key ) : null;
    }

    public static function set( string $key, array $payload, array $tags = array(), ?int $ttl = null ): bool
    {
        if ( ! self::$storage ) {
            return false;
        }

        $tags = apply_filters( 'my_pro_cache_entry_tags', $tags, $payload );

        return self::$storage->set( $key, $payload, $tags, $ttl );
    }

    public static function purge_url( string $url ): void
    {
        if ( ! self::$storage ) {
            return;
        }

        self::$storage->purge_uri( $url );
        self::record_purge( $url );
    }

    public static function purge_tag( string $tag ): void
    {
        if ( ! self::$storage ) {
            return;
        }

        self::$storage->purge_tags( array( $tag ) );
        self::record_purge( 'tag:' . $tag );
    }

    public static function purge_tags( array $tags ): void
    {
        if ( ! self::$storage ) {
            return;
        }

        self::$storage->purge_tags( $tags );
        foreach ( $tags as $tag ) {
            self::record_purge( 'tag:' . $tag );
        }
    }

    public static function purge_all(): void
    {
        if ( ! self::$storage ) {
            return;
        }

        self::$storage->clear();
        self::record_purge( 'all' );
    }

    public static function record_hit(): void
    {
        self::update_stats( 'hits' );
    }

    public static function record_miss(): void
    {
        self::update_stats( 'misses' );
    }

    private static function update_stats( string $key ): void
    {
        $stats = get_option( 'my_pro_cache_stats', array( 'hits' => 0, 'misses' => 0 ) );
        if ( ! is_array( $stats ) ) {
            $stats = array( 'hits' => 0, 'misses' => 0 );
        }

        if ( ! isset( $stats[ $key ] ) ) {
            $stats[ $key ] = 0;
        }

        $stats[ $key ]++;

        $ratio = '?';
        $total = $stats['hits'] + $stats['misses'];
        if ( $total > 0 ) {
            $ratio = number_format_i18n( ( $stats['hits'] / $total ) * 100, 2 ) . '%';
        }

        $stats['ratio'] = $ratio;

        update_option( 'my_pro_cache_stats', $stats );
    }

    private static function record_purge( string $what ): void
    {
        $recent = get_option( 'my_pro_cache_recent_purges', array() );
        if ( ! is_array( $recent ) ) {
            $recent = array();
        }

        array_unshift( $recent, $what . ' @ ' . date_i18n( 'Y-m-d H:i:s' ) );
        $recent = array_slice( $recent, 0, 10 );
        update_option( 'my_pro_cache_recent_purges', $recent );
    }
}

