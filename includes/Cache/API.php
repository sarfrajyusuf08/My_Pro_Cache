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

/**
 * Static facade exposing cache storage helpers and purge statistics.
 * All components call through here to avoid hard dependencies on a specific backend.
 */
class API
{
    private static ?Manager $options = null;

    private static ?StorageInterface $storage = null;

    private static ?Logger $logger = null;

    /**
     * Bootstraps the API with the option manager, storage backend, and logger instances.
     * Call once during plugin bootstrap before using cache helpers.
     */
    public static function init( Manager $options, StorageInterface $storage, Logger $logger ): void
    {
        self::$options = $options;
        self::$storage = $storage;
        self::$logger  = $logger;
    }

    /**
     * Fetches a cached payload by deterministic key, returning metadata when present.
     */
    public static function get( string $key ): ?array
    {
        return self::$storage ? self::$storage->get( $key ) : null;
    }

    /**
     * Persists a payload into the active backend while applying tag filters and TTL.
     */
    public static function set( string $key, array $payload, array $tags = array(), ?int $ttl = null ): bool
    {
        if ( ! self::$storage ) {
            return false;
        }

        $tags = apply_filters( 'my_pro_cache_entry_tags', $tags, $payload );

        return self::$storage->set( $key, $payload, $tags, $ttl );
    }

    /**
     * Clears cache for a specific URL and logs the purge for the dashboard list.
     */
    public static function purge_url( string $url ): void
    {
        if ( ! self::$storage ) {
            return;
        }

        self::$storage->purge_uri( $url );
        self::record_purge( $url );
    }

    /**
     * Clears entries associated to a single tag and records the purge event.
     */
    public static function purge_tag( string $tag ): void
    {
        if ( ! self::$storage ) {
            return;
        }

        self::$storage->purge_tags( array( $tag ) );
        self::record_purge( 'tag:' . $tag );
    }

    /**
     * Clears entries matching multiple tags and records each tag purge.
     */
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

    /**
     * Flushes all cached entries from the backend and records the bulk purge.
     */
    public static function purge_all(): void
    {
        if ( ! self::$storage ) {
            return;
        }

        self::$storage->clear();
        self::record_purge( 'all' );
    }

    /**
     * Increments the hit counter used for the hit/miss ratio shown in the UI.
     */
    public static function record_hit(): void
    {
        self::update_stats( 'hits' );
    }

    /**
     * Increments the miss counter used for the hit/miss ratio shown in the UI.
     */
    public static function record_miss(): void
    {
        self::update_stats( 'misses' );
    }

    /**
     * Updates the persistent hit/miss counters and recalculates the ratio statistic.
     */
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

    /**
     * Stores a recent purge entry so administrators can review recent actions.
     */
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

