<?php

namespace MyProCache\Cache;

use MyProCache\Options\Manager;
use function apply_filters;
use function get_locale;
use function in_array;
use function implode;
use function is_user_logged_in;
use function is_ssl;
use function ksort;
use function parse_str;
use function parse_url;
use function sha1;
use function str_starts_with;
use function strtolower;
use function wp_get_current_user;
use function wp_is_mobile;
use function wp_json_encode;
use const PHP_URL_HOST;
use const PHP_URL_PATH;
use const PHP_URL_QUERY;

/**
 * Creates deterministic cache keys using request context and configured vary rules.
 */
class Key
{
    /**
     * Builds a cache key from the active global request environment.
     */
    public static function build_from_globals( Manager $options ): string
    {
        $scheme = is_ssl() ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';

        return self::build_from_uri( $scheme, $host, $uri, $options );
    }

    /**
     * Builds a cache key for an arbitrary URL using plugin options.
     */
    public static function build_from_url( string $url, Manager $options ): string
    {
        $scheme = parse_url( $url, PHP_URL_SCHEME ) ?? 'http';
        $host   = parse_url( $url, PHP_URL_HOST ) ?? 'localhost';
        $path   = parse_url( $url, PHP_URL_PATH ) ?? '/';
        $query  = parse_url( $url, PHP_URL_QUERY ) ?? '';

        return self::build_from_components( $scheme, $host, $path, $query, $options );
    }

    /**
     * Normalises a URI relative to host/scheme before delegating to component builder.
     */
    private static function build_from_uri( string $scheme, string $host, string $uri, Manager $options ): string
    {
        $parts = parse_url( $scheme . '://' . $host . $uri );
        $path  = $parts['path'] ?? '/';
        $query = $parts['query'] ?? '';

        return self::build_from_components( $scheme, $host, $path, $query, $options );
    }

    /**
     * Assembles the final cache key from scheme, host, path, filtered query, and vary data.
     */
    private static function build_from_components( string $scheme, string $host, string $path, string $query, Manager $options ): string
    {
        $args = array();
        if ( $query ) {
            parse_str( $query, $args );
        }
        $args = self::filter_query_args( $args, $options );
        ksort( $args );
        $query_hash = sha1( wp_json_encode( $args ) );

        $vary_parts = self::vary_parts( $options );
        $vary_parts = apply_filters( 'my_pro_cache_vary_parts', $vary_parts );
        $vary_hash  = sha1( wp_json_encode( $vary_parts ) );

        $key_parts = array( strtolower( $scheme ), strtolower( $host ), $path, $query_hash, $vary_hash );
        $key_parts = apply_filters( 'my_pro_cache_key_parts', $key_parts );

        return implode( '|', $key_parts );
    }

    /**
     * Removes ignored query parameters such as tracking or configured exclusions.
     */
    private static function filter_query_args( array $args, Manager $options ): array
    {
        $exclude  = array_map( 'strtolower', (array) $options->get( 'exclude_query_args', array() ) );
        $filtered = array();

        foreach ( $args as $key => $value ) {
            $lkey = strtolower( $key );
            if ( in_array( $lkey, $exclude, true ) ) {
                continue;
            }
            if ( str_starts_with( $lkey, 'utm_' ) ) {
                continue;
            }
            $filtered[ $lkey ] = $value;
        }

        return $filtered;
    }

    /**
     * Collects user/device/language/cookie markers that influence cache variation.
     */
    private static function vary_parts( Manager $options ): array
    {
        $parts = array();

        if ( $options->get( 'cache_vary_device', true ) ) {
            $parts['device'] = wp_is_mobile() ? 'mobile' : 'desktop';
        }

        if ( is_user_logged_in() && 'private' === $options->get( 'cache_logged_in_mode', 'bypass' ) ) {
            $user = wp_get_current_user();
            $parts['user'] = (int) $user->ID;
        }

        if ( $options->get( 'cache_vary_role', false ) && is_user_logged_in() ) {
            $user = wp_get_current_user();
            $parts['role'] = implode( ',', (array) $user->roles );
        }

        if ( $options->get( 'cache_vary_lang', false ) ) {
            $parts['lang'] = get_locale();
        }

        $allow = apply_filters( 'my_pro_cache_cookie_allowlist', (array) $options->get( 'cache_vary_cookie_allowlist', array() ) );
        foreach ( $allow as $cookie ) {
            if ( isset( $_COOKIE[ $cookie ] ) ) {
                $parts[ 'cookie_' . $cookie ] = $_COOKIE[ $cookie ];
            }
        }

        return $parts;
    }
}
