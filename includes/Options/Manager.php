<?php

namespace MyProCache\Options;

use function absint;
use function array_merge;
use function explode;
use function get_option;
use function in_array;
use function is_array;
use function is_numeric;
use function sanitize_key;
use function sanitize_text_field;
use function trim;
use function update_option;
use function wp_parse_args;
use function wp_mkdir_p;
use const DIRECTORY_SEPARATOR;
use const WP_CONTENT_DIR;

class Manager
{
    public const OPTION_KEY = 'my_pro_cache_options';

    private array $options = array();

    public function __construct()
    {
        $this->options = $this->load();
    }

    public function all(): array
    {
        return $this->options;
    }

    public function get( string $key, $default = null )
    {
        return $this->options[ $key ] ?? $default;
    }

    public function update( array $values ): void
    {
        $this->options = array_merge( $this->options, $values );
        update_option( self::OPTION_KEY, $this->options );
    }

    public function sanitize( array $input ): array
    {
        $sanitized = $this->options;

        foreach ( $input as $key => $value ) {
            if ( ! is_string( $key ) ) {
                continue;
            }

            $sanitized[ $key ] = $this->sanitize_value( $key, $value );
        }

        $this->options = $sanitized;

        return $sanitized;
    }

    public function initialize_defaults(): void
    {
        $defaults       = Defaults::all();
        $existing       = get_option( self::OPTION_KEY, array() );
        $normalized     = is_array( $existing ) ? $existing : array();
        $merged         = wp_parse_args( $normalized, $defaults );
        $this->options  = $merged;
        update_option( self::OPTION_KEY, $merged );
    }

    public function ensure_cache_directory(): bool
    {
        $base = $this->get_cache_dir();
        $dirs = array(
            $base,
            $base . DIRECTORY_SEPARATOR . 'pages',
            $base . DIRECTORY_SEPARATOR . 'meta',
            $base . DIRECTORY_SEPARATOR . 'tags',
            $base . DIRECTORY_SEPARATOR . 'logs',
        );

        $created = true;

        foreach ( $dirs as $dir ) {
            if ( is_dir( $dir ) ) {
                continue;
            }

            if ( ! wp_mkdir_p( $dir ) ) {
                $created = false;
            }
        }

        return $created;
    }

    public function get_cache_dir(): string
    {
        return WP_CONTENT_DIR . '/cache/my-pro-cache';
    }


    public function replace( array $values ): void
    {
        $this->options = $values;
        update_option( self::OPTION_KEY, $this->options );
    }

    private function load(): array
    {
        $stored = get_option( self::OPTION_KEY, array() );
        $stored = is_array( $stored ) ? $stored : array();

        return wp_parse_args( $stored, Defaults::all() );
    }

    private function sanitize_value( string $key, $value )
    {
        switch ( $key ) {
            case 'cache_backend':
                $allowed = array( 'disk', 'redis', 'memcached' );
                $value   = is_string( $value ) ? sanitize_key( $value ) : 'disk';
                return in_array( $value, $allowed, true ) ? $value : 'disk';
            case 'cache_logged_in_mode':
                $allowed = array( 'bypass', 'private' );
                $value   = is_string( $value ) ? sanitize_key( $value ) : 'bypass';
                return in_array( $value, $allowed, true ) ? $value : 'bypass';
            case 'oc_backend':
                $allowed = array( 'redis', 'memcached' );
                $value   = is_string( $value ) ? sanitize_key( $value ) : 'redis';
                return in_array( $value, $allowed, true ) ? $value : 'redis';
            case 'toolbox_server_snippet':
                $allowed = array( 'apache', 'nginx', 'litespeed' );
                $value   = is_string( $value ) ? sanitize_key( $value ) : 'apache';
                return in_array( $value, $allowed, true ) ? $value : 'apache';
        }

        if ( str_starts_with( $key, 'ttl_' ) || str_starts_with( $key, 'stale_' ) || str_starts_with( $key, 'preload_interval' ) ) {
            return max( 0, absint( $value ) );
        }

        if ( str_ends_with( $key, '_concurrency' ) || str_ends_with( $key, '_freq' ) ) {
            return max( 0, absint( $value ) );
        }

        if ( str_starts_with( $key, 'cache_' ) && str_contains( $key, 'vary' ) ) {
            return ! empty( $value );
        }

        if ( str_contains( $key, 'enabled' ) || str_starts_with( $key, 'min_' ) || str_starts_with( $key, 'combine_' ) || str_starts_with( $key, 'lazyload' ) || str_starts_with( $key, 'webp_' ) || str_contains( $key, '_rewrite' ) || str_starts_with( $key, 'js_' ) || str_starts_with( $key, 'css_' ) || str_starts_with( $key, 'database_' ) || str_starts_with( $key, 'general_module_' ) || str_starts_with( $key, 'heartbeat_control_' ) || 'bypass_cache_toggle' === $key || str_starts_with( $key, 'critical_css' ) ) {
            return ! empty( $value );
        }

        if ( is_string( $value ) && ( str_contains( $key, '_urls' ) || str_contains( $key, '_keys' ) || str_contains( $key, '_allowlist' ) || str_contains( $key, '_handles' ) || str_contains( $key, '_prefetch' ) || str_contains( $key, '_preconnect' ) || str_contains( $key, '_excludes' ) || 'cdn_file_types' === $key || 'cache_vary_cookie_allowlist' === $key || 'oc_persistent_groups' === $key ) ) {
            $lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $value ) ) );
            return array_values( $lines );
        }

        if ( is_array( $value ) && ( str_contains( $key, '_urls' ) || str_contains( $key, '_keys' ) || str_contains( $key, '_allowlist' ) || str_contains( $key, '_handles' ) || str_contains( $key, '_prefetch' ) || str_contains( $key, '_preconnect' ) || str_contains( $key, '_excludes' ) || 'cdn_file_types' === $key || 'cache_vary_cookie_allowlist' === $key || 'oc_persistent_groups' === $key ) ) {
            return array_values( array_filter( array_map( 'trim', $value ) ) );
        }

        if ( in_array( $key, array( 'cdn_host', 'cdn_image_host', 'cdn_static_host', 'cf_api_token', 'cf_zone_id', 'preload_user_agent', 'toolbox_generated_snippet' ), true ) ) {
            return is_string( $value ) ? trim( $value ) : '';
        }

        if ( 'oc_auth' === $key ) {
            return is_string( $value ) ? trim( $value ) : '';
        }

        if ( 'oc_host' === $key ) {
            return is_string( $value ) ? sanitize_text_field( $value ) : '127.0.0.1';
        }

        if ( 'oc_port' === $key ) {
            return absint( $value );
        }

        if ( 'debug_log_limit' === $key ) {
            $limit = absint( $value );
            return $limit > 0 ? $limit : 50;
        }

        if ( 'import_export_last' === $key ) {
            return is_string( $value ) ? sanitize_text_field( $value ) : '';
        }

        if ( 'cache_rest_api' === $key || 'cache_vary_device' === $key || 'cache_vary_role' === $key || 'cache_vary_lang' === $key ) {
            return ! empty( $value );
        }

        if ( 'cache_vary_cookie_allowlist' === $key || 'exclude_urls' === $key || 'exclude_cookies' === $key || 'exclude_user_agents' === $key || 'exclude_query_args' === $key ) {
            if ( is_array( $value ) ) {
                return array_values( array_filter( array_map( 'trim', $value ) ) );
            }

            if ( is_string( $value ) ) {
                $items = preg_split( '/\r\n|\r|\n/', $value );
                $items = is_array( $items ) ? $items : array();
                $items = array_map( 'trim', $items );
                $items = array_filter( $items );

                return array_values( $items );
            }

            return array();
        }

        if ( str_contains( $key, '_age' ) || str_contains( $key, '_size' ) ) {
            return is_numeric( $value ) ? absint( $value ) : 0;
        }

        return is_string( $value ) ? sanitize_text_field( $value ) : $value;
    }
}





