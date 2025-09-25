<?php

namespace MyProCache\CLI;

use MyProCache\Cache\API;
use MyProCache\Options\Manager;
use MyProCache\Debug\Logger;
use MyProCache\Preload\PreloadManager;
use function get_option;
use function is_array;
use function update_option;
use function array_unique;
use function wp_json_encode;
use const JSON_PRETTY_PRINT;
use WP_CLI;
use WP_CLI_Command;

class Commands extends WP_CLI_Command
{
    private Manager $options;

    private Logger $logger;

    public function __construct( Manager $options, Logger $logger )
    {
        $this->options = $options;
        $this->logger  = $logger;
    }

    public function register(): void
    {
        if ( class_exists( '\\WP_CLI' ) ) {
            WP_CLI::add_command( 'my-pro-cache', $this );
        }
    }

    /**
     * Purge caches.
     *
     * ## OPTIONS
     *
     * [--url=<url>]
     * [--tag=<tag>]
     * [--all]
     */
    public function purge( array $args, array $assoc_args ): void
    {
        if ( isset( $assoc_args['all'] ) ) {
            API::purge_all();
            WP_CLI::success( 'Full cache purged.' );
            return;
        }

        if ( isset( $assoc_args['url'] ) ) {
            API::purge_url( $assoc_args['url'] );
            WP_CLI::success( 'URL cache purged.' );
        }

        if ( isset( $assoc_args['tag'] ) ) {
            API::purge_tag( $assoc_args['tag'] );
            WP_CLI::success( 'Tag cache purged.' );
        }
    }

    /**
     * Queue preload jobs.
     *
     * [--sitemap=<url>]
     * [--url=<url>]
     */
    public function preload( array $args, array $assoc_args ): void
    {
        $queue = get_option( 'my_pro_cache_preload_queue', array() );
        if ( ! is_array( $queue ) ) {
            $queue = array();
        }

        if ( isset( $assoc_args['sitemap'] ) ) {
            $this->options->update( array( 'preload_sitemaps' => array( $assoc_args['sitemap'] ) ) );
        }

        if ( isset( $assoc_args['url'] ) ) {
            $queue[] = $assoc_args['url'];
            $queue   = array_unique( $queue );
            update_option( 'my_pro_cache_preload_queue', $queue );
        }

        PreloadManager::queue_full_preload( $this->options );
        WP_CLI::success( 'Preload queued.' );
    }

    /**
     * Show cache stats.
     */
    public function status(): void
    {
        $stats = get_option( 'my_pro_cache_stats', array( 'hits' => 0, 'misses' => 0, 'ratio' => '?' ) );
        WP_CLI::log( wp_json_encode( $stats, JSON_PRETTY_PRINT ) );
    }
}
