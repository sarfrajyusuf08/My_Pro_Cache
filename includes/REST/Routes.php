<?php

namespace MyProCache\REST;

use MyProCache\Options\Manager;
use MyProCache\Debug\Logger;
use MyProCache\Cache\API;
use MyProCache\Preload\PreloadManager;
use MyProCache\Support\Capabilities;
use function add_action;
use function array_unique;
use function get_option;
use function is_array;
use function register_rest_route;
use function array_merge;
use function current_user_can;
use function update_option;
use function wp_unslash;
use WP_REST_Request;
use WP_REST_Response;

class Routes
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
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes(): void
    {
        register_rest_route(
            'my-pro-cache/v1',
            '/purge',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_purge' ),
                'permission_callback' => array( $this, 'check_permissions' ),
            )
        );

        register_rest_route(
            'my-pro-cache/v1',
            '/preload',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_preload' ),
                'permission_callback' => array( $this, 'check_permissions' ),
            )
        );

        register_rest_route(
            'my-pro-cache/v1',
            '/status',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'handle_status' ),
                'permission_callback' => array( $this, 'check_permissions' ),
            )
        );
    }

    public function handle_purge( WP_REST_Request $request ): WP_REST_Response
    {
        $params = $request->get_json_params();
        $urls   = isset( $params['urls'] ) && is_array( $params['urls'] ) ? $params['urls'] : array();
        $tags   = isset( $params['tags'] ) && is_array( $params['tags'] ) ? $params['tags'] : array();

        foreach ( $urls as $url ) {
            API::purge_url( $url );
        }

        if ( ! empty( $tags ) ) {
            API::purge_tags( $tags );
        }

        return new WP_REST_Response( array( 'success' => true ) );
    }

    public function handle_preload( WP_REST_Request $request ): WP_REST_Response
    {
        $params   = $request->get_json_params();
        $sitemaps = isset( $params['sitemaps'] ) && is_array( $params['sitemaps'] ) ? array_map( 'trim', $params['sitemaps'] ) : array();
        $urls     = isset( $params['urls'] ) && is_array( $params['urls'] ) ? array_map( 'trim', $params['urls'] ) : array();

        if ( ! empty( $sitemaps ) ) {
            $this->options->update( array( 'preload_sitemaps' => $sitemaps ) );
        }

        if ( ! empty( $urls ) ) {
            $queue = get_option( 'my_pro_cache_preload_queue', array() );
            if ( ! is_array( $queue ) ) {
                $queue = array();
            }
            $queue = array_unique( array_merge( $queue, $urls ) );
            update_option( 'my_pro_cache_preload_queue', $queue );
        }

        PreloadManager::queue_full_preload( $this->options );

        return new WP_REST_Response( array( 'success' => true ) );
    }

    public function handle_status(): WP_REST_Response
    {
        $stats = get_option( 'my_pro_cache_stats', array( 'hits' => 0, 'misses' => 0, 'ratio' => '?' ) );
        if ( ! is_array( $stats ) ) {
            $stats = array( 'hits' => 0, 'misses' => 0, 'ratio' => '?' );
        }

        return new WP_REST_Response( array(
            'stats'   => $stats,
            'options' => $this->options->all(),
        ) );
    }

    public function check_permissions(): bool
    {
        return current_user_can( Capabilities::manage_capability() );
    }
}


