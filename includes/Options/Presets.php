<?php

namespace MyProCache\Options;

use MyProCache\Cache\API as CacheAPI;
use MyProCache\Preload\PreloadManager;
use function __;
use function array_key_exists;
use function array_map;
use function array_slice;
use function array_unique;
use function current_time;
use function file_exists;
use function file_get_contents;
use function get_option;
use function get_site_option;
use function implode;
use function in_array;
use function is_array;
use function is_dir;
use function is_writable;
use function strpos;
use function sanitize_key;
use function time;
use function wp_date;
use const WP_CONTENT_DIR;
use const MY_PRO_CACHE_PLUGIN_DIR;

class Presets
{
    public const BACKUP_OPTION = 'my_pro_cache_preset_backup';

    public const STATE_OPTION = 'my_pro_cache_preset_state';

    public static function all(): array
    {
        return array(
            'safe_defaults' => array(
                'id'          => 'safe_defaults',
                'label'       => __( 'Safe Defaults', 'my-pro-cache' ),
                'description' => __( 'Maximum compatibility for shared hosting and mixed plugin stacks.', 'my-pro-cache' ),
                'risk'        => 'low',
                'highlights'  => array(
                    __( 'Caching enabled with conservative rules', 'my-pro-cache' ),
                    __( 'HTML minification only', 'my-pro-cache' ),
                    __( 'Extensive URL and request exclusions', 'my-pro-cache' ),
                ),
                'overrides'   => array(
                    'general_module_cache'         => true,
                    'general_module_object_cache'  => false,
                    'cache_vary_device'            => false,
                    'cache_vary_lang'              => false,
                    'ttl_default'                  => 28800,
                    'ttl_front_page'               => 21600,
                    'ttl_feed'                     => 28800,
                    'purge_on_update'              => true,
                    'purge_on_comment'             => true,
                    'purge_ccu_cdn'                => false,
                    'purge_schedule_enabled'       => false,
                    'exclude_urls'                 => array( '/wp-admin', '/wp-login.php', '/preview', '/\?s=', '/feed', '/wp-json', '/xmlrpc.php', '/admin-ajax.php' ),
                    'exclude_query_args'           => array( 'preview', 'wc-ajax', 'et_fb', 'elementor-preview' ),
                    'min_html'                     => true,
                    'min_css'                      => false,
                    'min_js'                       => false,
                    'combine_css'                  => false,
                    'combine_js'                   => false,
                    'js_defer'                     => false,
                    'js_delay_until_interaction'   => false,
                    'lazyload_images'              => true,
                    'lazyload_iframes'             => true,
                    'preload_enabled'              => false,
                    'oc_enabled'                   => false,
                    'cdn_enabled'                  => false,
                    'debug_enabled'                => false,
                ),
                'guards'      => array(),
                'post_actions' => array( 'flush_cache' ),
                'verification' => array( 'wp_cache', 'dropin', 'cache_dir', 'cdn_host', 'redis' ),
            ),
            'balanced' => array(
                'id'          => 'balanced',
                'label'       => __( 'Balanced', 'my-pro-cache' ),
                'description' => __( 'Recommended for most production sites balancing speed and safety.', 'my-pro-cache' ),
                'risk'        => 'medium',
                'highlights'  => array(
                    __( 'Device-aware caching and moderate TTLs', 'my-pro-cache' ),
                    __( 'HTML/CSS/JS minify with smart deferral', 'my-pro-cache' ),
                    __( 'Sitemap-based preload and WebP rewrites', 'my-pro-cache' ),
                ),
                'overrides'   => array(
                    'general_module_cache'         => true,
                    'general_module_object_cache'  => true,
                    'cache_vary_device'            => true,
                    'cache_vary_lang'              => false,
                    'ttl_default'                  => 21600,
                    'ttl_front_page'               => 14400,
                    'ttl_feed'                     => 21600,
                    'purge_on_update'              => true,
                    'purge_on_comment'             => true,
                    'purge_ccu_cdn'                => true,
                    'min_html'                     => true,
                    'min_css'                      => true,
                    'min_js'                       => true,
                    'combine_css'                  => false,
                    'combine_js'                   => false,
                    'js_defer'                     => true,
                    'js_delay_until_interaction'   => true,
                    'js_delay_allowlist'           => array( 'google-analytics', 'gtag', 'adsbygoogle', 'facebook-pixel' ),
                    'lazyload_images'              => true,
                    'lazyload_iframes'             => true,
                    'webp_avif_rewrite'            => true,
                    'preload_enabled'              => true,
                    'preload_concurrency'          => 3,
                    'preload_interval_sec'         => 2,
                    'oc_enabled'                   => true,
                    'oc_backend'                   => 'redis',
                    'cdn_enabled'                  => true,
                    'debug_enabled'                => false,
                ),
                'guards'      => array(),
                'post_actions' => array( 'flush_cache', 'queue_preload' ),
                'verification' => array( 'wp_cache', 'dropin', 'cache_dir', 'cdn_host', 'redis' ),
            ),
            'aggressive' => array(
                'id'          => 'aggressive',
                'label'       => __( 'Aggressive', 'my-pro-cache' ),
                'description' => __( 'Maximum performance for static and marketing sites with minimal dynamic content.', 'my-pro-cache' ),
                'risk'        => 'high',
                'highlights'  => array(
                    __( 'Extended TTLs with full-site preload', 'my-pro-cache' ),
                    __( 'Combine and delay CSS/JS aggressively', 'my-pro-cache' ),
                    __( 'Enable CDN rewrites and image optimisation', 'my-pro-cache' ),
                ),
                'overrides'   => array(
                    'general_module_cache'         => true,
                    'general_module_object_cache'  => false,
                    'cache_vary_device'            => true,
                    'ttl_default'                  => 172800,
                    'ttl_front_page'               => 86400,
                    'ttl_feed'                     => 172800,
                    'purge_on_update'              => true,
                    'purge_on_comment'             => false,
                    'min_html'                     => true,
                    'min_css'                      => true,
                    'min_js'                       => true,
                    'combine_css'                  => true,
                    'combine_js'                   => true,
                    'critical_css_auto'            => true,
                    'js_defer'                     => true,
                    'js_delay_until_interaction'   => true,
                    'lazyload_images'              => true,
                    'lazyload_iframes'             => true,
                    'convert_webp'                 => true,
                    'webp_avif_rewrite'            => true,
                    'preload_enabled'              => true,
                    'preload_concurrency'          => 5,
                    'preload_interval_sec'         => 1,
                    'preconnect'                   => array( 'https://fonts.gstatic.com', 'https://fonts.googleapis.com' ),
                    'cdn_enabled'                  => true,
                    'oc_enabled'                   => false,
                    'debug_enabled'                => false,
                ),
                'guards'      => array(
                    array(
                        'id'      => 'woocommerce',
                        'message' => __( 'Aggressive preset is not recommended while WooCommerce is active.', 'my-pro-cache' ),
                    ),
                ),
                'post_actions' => array( 'flush_cache', 'queue_preload' ),
                'verification' => array( 'wp_cache', 'dropin', 'cache_dir', 'cdn_host', 'redis' ),
            ),
        );
    }

    public static function get( string $id ): ?array
    {
        $presets = self::all();
        $id      = sanitize_key( $id );

        return $presets[ $id ] ?? null;
    }

    public static function evaluate_guards( array $preset ): array
    {
        $errors = array();

        foreach ( $preset['guards'] ?? array() as $guard ) {
            $guard_id = $guard['id'] ?? '';
            if ( 'woocommerce' === $guard_id && self::is_woocommerce_active() ) {
                $errors[] = $guard['message'] ?? __( 'Environment guard failed.', 'my-pro-cache' );
            }
        }

        return $errors;
    }

    public static function normalize_overrides( array $preset, Manager $options ): array
    {
        $overrides = $preset['overrides'];
        $warnings  = array();

        $object_cache_supported = self::is_object_cache_compatible();

        if ( isset( $overrides['oc_enabled'] ) && $overrides['oc_enabled'] ) {
            if ( ! $object_cache_supported ) {
                $overrides['oc_enabled']                  = false;
                $overrides['general_module_object_cache'] = false;
                $warnings[]                               = __( 'Object cache drop-in is not compatible with this WordPress environment. Object cache left disabled.', 'my-pro-cache' );
            } elseif ( isset( $overrides['oc_backend'] ) && 'redis' === $overrides['oc_backend'] && ! self::is_redis_available() ) {
                $overrides['oc_backend'] = 'internal';
                $warnings[]              = __( 'Redis not detected. Object cache set to internal mode instead.', 'my-pro-cache' );
            }
        } else {
            $overrides['oc_enabled']                  = false;
            $overrides['general_module_object_cache'] = false;
        }

        if ( ! $object_cache_supported ) {
            $overrides['oc_enabled']                  = false;
            $overrides['general_module_object_cache'] = false;
        }

        if ( ! empty( $overrides['cdn_enabled'] ) ) {
            $current_options = $options->all();
            $host            = (string) ( $current_options['cdn_host'] ?? '' );
            if ( '' === $host ) {
                $overrides['cdn_enabled'] = false;
                $warnings[]               = __( 'CDN host is not configured. CDN rewrites skipped.', 'my-pro-cache' );
            }
        }

        if ( isset( $overrides['preload_enabled'] ) && $overrides['preload_enabled'] ) {
            $current_options = $options->all();
            if ( empty( $current_options['preload_sitemaps'] ) ) {
                $warnings[] = __( 'Preload uses default sitemap discovery. Ensure sitemaps are available.', 'my-pro-cache' );
            }
        }

        return array( $overrides, $warnings );
    }

    public static function calculate_diff( array $current, array $overrides ): array
    {
        $diff = array();

        foreach ( $overrides as $key => $new_value ) {
            $current_value = $current[ $key ] ?? null;
            if ( self::values_are_equal( $current_value, $new_value ) ) {
                continue;
            }

            $diff[ $key ] = array(
                'from' => self::export_value( $current_value ),
                'to'   => self::export_value( $new_value ),
            );
        }

        return $diff;
    }

    public static function run_post_actions( array $preset, Manager $options ): void
    {
        foreach ( $preset['post_actions'] ?? array() as $action ) {
            switch ( $action ) {
                case 'flush_cache':
                    CacheAPI::purge_all();
                    break;
                case 'queue_preload':
                    PreloadManager::queue_full_preload( $options );
                    break;
            }
        }
    }

    public static function run_verification_checks( array $preset, Manager $options ): array
    {
        $results = array();
        $current = $options->all();

        foreach ( $preset['verification'] ?? array() as $check_id ) {
            $results[] = self::perform_check( $check_id, $preset, $current );
        }

        return $results;
    }

    public static function format_backup_summary( array $backup ): string
    {
        if ( empty( $backup ) || empty( $backup['timestamp'] ) ) {
            return '';
        }

        $label = ucfirst( (string) ( $backup['preset'] ?? 'unknown' ) );

        return sprintf(
            __( 'Stored from preset: %1$s on %2$s', 'my-pro-cache' ),
            $label,
            wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $backup['timestamp'] )
        );
    }

    private static function perform_check( string $check_id, array $preset, array $current ): array
    {
        switch ( $check_id ) {
            case 'wp_cache':
                $pass = defined( 'WP_CACHE' ) && WP_CACHE;
                return self::make_check_result( __( 'WP_CACHE constant enabled', 'my-pro-cache' ), $pass );
            case 'dropin':
                $pass = file_exists( WP_CONTENT_DIR . '/advanced-cache.php' );
                return self::make_check_result( __( 'advanced-cache.php present', 'my-pro-cache' ), $pass );
            case 'cache_dir':
                $dir  = WP_CONTENT_DIR . '/cache/my-pro-cache';
                $pass = is_dir( $dir ) && is_writable( $dir );
                return self::make_check_result( __( 'Cache directory writable', 'my-pro-cache' ), $pass );
            case 'cdn_host':
                $enabled = ! empty( $preset['overrides']['cdn_enabled'] );
                if ( ! $enabled ) {
                    return self::make_check_result( __( 'CDN host configured (not required)', 'my-pro-cache' ), true, 'skip' );
                }
                $host = (string) ( $current['cdn_host'] ?? '' );
                $pass = '' !== $host;
                return self::make_check_result( __( 'CDN hostname configured', 'my-pro-cache' ), $pass );
            case 'redis':
                $enabled = ! empty( $preset['overrides']['oc_enabled'] ) && ( $preset['overrides']['oc_backend'] ?? '' ) === 'redis';
                if ( ! $enabled ) {
                    return self::make_check_result( __( 'Redis connectivity (not required)', 'my-pro-cache' ), true, 'skip' );
                }
                return self::make_check_result( __( 'Redis client available', 'my-pro-cache' ), self::is_redis_available() );
            default:
                return self::make_check_result( __( 'Check not recognised', 'my-pro-cache' ), false, 'skip' );
        }
    }

    private static function make_check_result( string $label, bool $status, string $mode = 'pass' ): array
    {
        $state = $status ? 'pass' : 'fail';
        if ( 'skip' === $mode ) {
            $state = $status ? 'skip' : 'fail';
        }

        return array(
            'label'  => $label,
            'status' => $state,
        );
    }

    private static function values_are_equal( $a, $b ): bool
    {
        if ( is_array( $a ) || is_array( $b ) ) {
            return $a == $b; // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
        }

        return $a === $b;
    }

    private static function export_value( $value ): string
    {
        if ( is_bool( $value ) ) {
            return $value ? __( 'Enabled', 'my-pro-cache' ) : __( 'Disabled', 'my-pro-cache' );
        }

        if ( is_array( $value ) ) {
            $flat = array_map( 'strval', $value );
            return implode( ', ', array_unique( $flat ) );
        }

        if ( null === $value || '' === $value ) {
            return __( 'Empty', 'my-pro-cache' );
        }

        return (string) $value;
    }

    private static function is_object_cache_compatible(): bool
    {
        if ( ! defined( 'MY_PRO_CACHE_PLUGIN_DIR' ) ) {
            return false;
        }

        $dropin = MY_PRO_CACHE_PLUGIN_DIR . 'includes/ObjectCache/DropIn.php';
        if ( ! file_exists( $dropin ) ) {
            return false;
        }

        $contents = file_get_contents( $dropin );
        if ( false === $contents ) {
            return false;
        }

        return false !== strpos( $contents, 'function add_non_persistent_groups' );
    }

    private static function is_woocommerce_active(): bool
    {
        $active_plugins = (array) get_option( 'active_plugins', array() );
        $site_plugins   = (array) get_site_option( 'active_sitewide_plugins', array() );

        if ( in_array( 'woocommerce/woocommerce.php', $active_plugins, true ) ) {
            return true;
        }

        return array_key_exists( 'woocommerce/woocommerce.php', $site_plugins );
    }

    private static function is_redis_available(): bool
    {
        if ( class_exists( '\\Redis' ) ) {
            return true;
        }

        if ( defined( 'WP_REDIS_HOST' ) || defined( 'WP_REDIS_CLIENT' ) ) {
            return true;
        }

        if ( defined( 'WP_CACHE_KEY_SALT' ) && defined( 'WP_CACHE' ) && WP_CACHE ) {
            return true;
        }

        return false;
    }
}
