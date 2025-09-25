<?php

if ( ! defined( 'WPINC' ) ) {
    die;
}

if ( ! in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true ) ) {
    return;
}

if ( ! empty( $_POST ) ) {
    return;
}

foreach ( $_COOKIE ?? array() as $name => $value ) {
    if ( str_starts_with( $name, 'wordpress_logged_in_' ) ) {
        return;
    }
}

$config_defaults = array(
    'ttl_default'                 => 3600,
    'ttl_front_page'              => 600,
    'ttl_feed'                    => 900,
    'stale_while_revalidate'      => 0,
    'stale_if_error'              => 0,
    'exclude_urls'                => array(),
    'exclude_cookies'             => array(),
    'exclude_user_agents'         => array(),
    'exclude_query_args'          => array(),
    'cache_vary_device'           => true,
    'cache_vary_lang'             => false,
    'cache_vary_cookie_allowlist' => array(),
);

$config_file = WP_CONTENT_DIR . '/cache/my-pro-cache/config.php';
$config      = $config_defaults;

if ( file_exists( $config_file ) ) {
    $loaded = include $config_file;
    if ( is_array( $loaded ) ) {
        $config = array_merge( $config_defaults, $loaded );
    }
}

$request_uri = $_SERVER['REQUEST_URI'] ?? '/';

if ( mpc_my_pro_cache_pattern_match( $config['exclude_urls'], $request_uri ) ) {
    return;
}

if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) && mpc_my_pro_cache_pattern_match( $config['exclude_user_agents'], $_SERVER['HTTP_USER_AGENT'] ) ) {
    return;
}

foreach ( (array) $config['exclude_cookies'] as $cookie ) {
    if ( isset( $_COOKIE[ $cookie ] ) ) {
        return;
    }
}

if ( ! empty( $_GET ) ) {
    $excludes = array_map( 'strtolower', (array) $config['exclude_query_args'] );
    foreach ( array_keys( $_GET ) as $param ) {
        if ( in_array( strtolower( $param ), $excludes, true ) ) {
            return;
        }
    }
}

$key       = mpc_my_pro_cache_build_key( $config );
$hash      = sha1( $key );
$cache_dir = WP_CONTENT_DIR . '/cache/my-pro-cache';
$content   = $cache_dir . '/pages/' . $hash . '.html';
$meta_file = $cache_dir . '/meta/' . $hash . '.json';

if ( ! file_exists( $content ) || ! file_exists( $meta_file ) ) {
    header( 'X-My-Pro-Cache: MISS' );
    return;
}

$meta = json_decode( (string) file_get_contents( $meta_file ), true );
if ( ! is_array( $meta ) ) {
    header( 'X-My-Pro-Cache: MISS' );
    return;
}

$ttl     = isset( $meta['ttl'] ) ? (int) $meta['ttl'] : (int) $config['ttl_default'];
$created = isset( $meta['created'] ) ? (int) $meta['created'] : 0;

if ( $ttl > 0 && ( time() - $created ) > $ttl ) {
    @unlink( $meta_file );
    @unlink( $content );
    header( 'X-My-Pro-Cache: MISS' );
    return;
}

$age = time() - $created;
header( 'X-My-Pro-Cache: HIT' );
header( 'X-My-Pro-Cache-Key: ' . $hash );
header( 'X-My-Pro-Cache-Age: ' . $age );

readfile( $content );
exit;

if ( ! function_exists( 'mpc_my_pro_cache_build_key' ) ) {
    function mpc_my_pro_cache_build_key( array $config ): string {
        $scheme = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ) ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path   = parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ) ?: '/';

        $query_args = array();
        if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
            parse_str( $_SERVER['QUERY_STRING'], $query_args );
        }

        $query_args = mpc_my_pro_cache_filter_query( $query_args, $config );
        ksort( $query_args );
        $query_hash = sha1( json_encode( $query_args ) );

        $vary      = mpc_my_pro_cache_vary_parts( $config );
        $vary_hash = sha1( json_encode( $vary ) );

        return implode( '|', array( strtolower( $scheme ), strtolower( $host ), $path, $query_hash, $vary_hash ) );
    }
}

if ( ! function_exists( 'mpc_my_pro_cache_filter_query' ) ) {
    function mpc_my_pro_cache_filter_query( array $args, array $config ): array {
        $exclude  = array_map( 'strtolower', (array) $config['exclude_query_args'] );
        $filtered = array();

        foreach ( $args as $key => $value ) {
            $lower = strtolower( $key );
            if ( in_array( $lower, $exclude, true ) ) {
                continue;
            }
            if ( str_starts_with( $lower, 'utm_' ) ) {
                continue;
            }
            $filtered[ $lower ] = $value;
        }

        return $filtered;
    }
}

if ( ! function_exists( 'mpc_my_pro_cache_vary_parts' ) ) {
    function mpc_my_pro_cache_vary_parts( array $config ): array {
        $parts = array();

        if ( ! empty( $config['cache_vary_device'] ) ) {
            $ua              = strtolower( $_SERVER['HTTP_USER_AGENT'] ?? '' );
            $parts['device'] = ( $ua && preg_match( '/(mobile|iphone|android|windows phone)/', $ua ) ) ? 'mobile' : 'desktop';
        }

        if ( ! empty( $config['cache_vary_lang'] ) && isset( $_COOKIE['wp-wpml_current_language'] ) ) {
            $parts['lang'] = $_COOKIE['wp-wpml_current_language'];
        }

        foreach ( (array) $config['cache_vary_cookie_allowlist'] as $cookie ) {
            if ( isset( $_COOKIE[ $cookie ] ) ) {
                $parts[ 'cookie_' . $cookie ] = $_COOKIE[ $cookie ];
            }
        }

        return $parts;
    }
}

if ( ! function_exists( 'mpc_my_pro_cache_pattern_match' ) ) {
    function mpc_my_pro_cache_pattern_match( array $patterns, string $subject ): bool {
        foreach ( $patterns as $pattern ) {
            $pattern = trim( $pattern );
            if ( '' === $pattern ) {
                continue;
            }
            if ( $pattern[0] === '#' && substr( $pattern, -1 ) === '#' ) {
                if ( @preg_match( $pattern, $subject ) ) {
                    return true;
                }
                continue;
            }
            $regex = '#^' . str_replace( '\\*', '.*', preg_quote( $pattern, '#' ) ) . '#i';
            if ( @preg_match( $regex, $subject ) ) {
                return true;
            }
        }

        return false;
    }
}
