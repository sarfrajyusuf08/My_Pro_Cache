<?php

namespace MyProCache\Preload;

use MyProCache\Options\Manager;
use MyProCache\Debug\Logger;
use MyProCache\Cache\API;
use function add_action;
use function array_filter;
use function function_exists;
use function array_map;
use function array_merge;
use function array_slice;
use function array_unique;
use function get_option;
use function home_url;
use function is_array;
use function is_string;
use function time;
use function update_option;
use function wp_get_sitemap_providers;
use function wp_next_scheduled;
use function wp_remote_get;
use function wp_schedule_event;
use function wp_schedule_single_event;
use function wp_unschedule_hook;
use function wp_sitemaps_get_server;
use const MINUTE_IN_SECONDS;

class PreloadManager
{
    private const HOOK = 'my_pro_cache_preload_run';

    private Manager $options;

    private Logger $logger;

    public function __construct( Manager $options, Logger $logger )
    {
        $this->options = $options;
        $this->logger  = $logger;
    }

    public function register(): void
    {
        add_action( 'wp', array( $this, 'maybe_schedule' ) );
        add_action( self::HOOK, array( $this, 'process_queue' ) );
    }

    public static function queue_full_preload( Manager $options ): void
    {
        $urls = array( home_url( '/' ) );

        $sitemaps = array_filter( array_map( 'trim', (array) $options->get( 'preload_sitemaps', array() ) ) );
        if ( empty( $sitemaps ) ) {
            $providers = wp_get_sitemap_providers();
            $server    = function_exists( 'wp_sitemaps_get_server' ) ? wp_sitemaps_get_server() : null;

            foreach ( $providers as $name => $provider ) {
                if ( method_exists( $provider, 'get_sitemap_list' ) ) {
                    foreach ( (array) $provider->get_sitemap_list() as $entry ) {
                        if ( is_string( $entry ) && '' !== $entry ) {
                            $sitemaps[] = $entry;
                            continue;
                        }

                        if ( is_array( $entry ) && ! empty( $entry['loc'] ) ) {
                            $sitemaps[] = $entry['loc'];
                        }
                    }
                    continue;
                }

                if ( ! $server || ! is_object( $server ) ) {
                    continue;
                }

                if ( method_exists( $provider, 'get_object_subtypes' ) ) {
                    $subtypes = (array) $provider->get_object_subtypes();
                    if ( empty( $subtypes ) ) {
                        $sitemap_url = method_exists( $server, 'get_sitemap_url' ) ? $server->get_sitemap_url( $name ) : null;
                        if ( $sitemap_url ) {
                            $sitemaps[] = $sitemap_url;
                        }
                        continue;
                    }

                    foreach ( $subtypes as $subtype_key => $subtype ) {
                        $slug = is_string( $subtype_key ) ? $subtype_key : '';
                        if ( '' === $slug && is_array( $subtype ) ) {
                            if ( ! empty( $subtype['slug'] ) ) {
                                $slug = (string) $subtype['slug'];
                            } elseif ( ! empty( $subtype['name'] ) ) {
                                $slug = (string) $subtype['name'];
                            }
                        } elseif ( '' === $slug && is_string( $subtype ) ) {
                            $slug = $subtype;
                        }

                        $sitemap_url = method_exists( $server, 'get_sitemap_url' ) ? $server->get_sitemap_url( $name, $slug ) : null;
                        if ( $sitemap_url ) {
                            $sitemaps[] = $sitemap_url;
                        }
                    }

                    continue;
                }

                $sitemap_url = method_exists( $server, 'get_sitemap_url' ) ? $server->get_sitemap_url( $name ) : null;
                if ( $sitemap_url ) {
                    $sitemaps[] = $sitemap_url;
                }
            }
        }

        $urls = array_merge( $urls, $sitemaps );

        $existing = get_option( 'my_pro_cache_preload_queue', array() );
        if ( ! is_array( $existing ) ) {
            $existing = array();
        }

        $queue = array_unique( array_merge( $existing, array_filter( array_map( 'trim', $urls ) ) ) );
        update_option( 'my_pro_cache_preload_queue', $queue );
    }

    public function maybe_schedule(): void
    {
        if ( ! $this->options->get( 'preload_enabled', false ) ) {
            wp_unschedule_hook( self::HOOK );
            return;
        }

        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', self::HOOK );
        }
    }

    public function process_queue(): void
    {
        $queue = get_option( 'my_pro_cache_preload_queue', array() );
        if ( ! is_array( $queue ) || empty( $queue ) ) {
            return;
        }

        $concurrency = max( 1, (int) $this->options->get( 'preload_concurrency', 2 ) );
        $interval    = max( 1, (int) $this->options->get( 'preload_interval_sec', 1 ) );
        $agent       = $this->options->get( 'preload_user_agent', 'MyProCache-Preload/1.0' );

        $batch = array_slice( $queue, 0, $concurrency );
        $queue = array_slice( $queue, $concurrency );
        update_option( 'my_pro_cache_preload_queue', $queue );

        foreach ( $batch as $url ) {
            wp_remote_get( $url, array(
                'timeout'    => 10,
                'user-agent' => $agent,
                'blocking'   => false,
                'headers'    => array( 'Cache-Control' => 'no-cache' ),
            ) );
            $this->logger->log( 'preload', 'Queued preload for ' . $url );
        }

        if ( ! empty( $queue ) ) {
            wp_schedule_single_event( time() + $interval, self::HOOK );
        }
    }
}
