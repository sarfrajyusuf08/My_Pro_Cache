<?php
/**
 * Gathers view context and renders plugin admin screens.
 */

namespace MyProCache\Admin;

use MyProCache\Debug\Logger;
use MyProCache\Options\Manager;
use MyProCache\Options\Schema;
use MyProCache\Options\Presets;
use function admin_url;
use function array_filter;
use function array_map;
use function array_slice;
use function count;
use function current_time;
use function esc_html;
use function esc_html__;
use function esc_url;
use function explode;
use function file_exists;
use function get_option;
use function human_time_diff;
use function implode;
use function is_array;
use function size_format;
use function sprintf;
use function strtotime;
use function trailingslashit;
use function wp_date;
use function wp_die;
use function wp_kses_post;
use function wp_using_ext_object_cache;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use const WP_CONTENT_DIR;

/**
 * Supplies data for dashboard, presets, and settings views.
 */
class PageRenderer
{
    private Manager $options;

    private Logger $logger;

    /**
     * Inject option manager and logger dependencies.
     */
    public function __construct( Manager $options, Logger $logger )
    {
        $this->options = $options;
        $this->logger  = $logger;
    }

    /**
     * Resolve tabs, context data, and include the correct view template.
     */
    public function render( string $slug ): void
    {
        $pages = Schema::pages();

        if ( ! isset( $pages[ $slug ] ) ) {
            wp_die( esc_html( __( 'Invalid settings page.', 'my-pro-cache' ) ) );
        }

        $page   = $pages[ $slug ];
        $tabs   = $this->build_tabs( $slug );
        $values = $this->options->all();
        $logger = $this->logger;

        $presets        = array();
        $preset_diffs   = array();
        $preset_guards  = array();
        $preset_state   = array();
        $preset_backup  = array();
        $current_options = $values;

        $cache_status        = 'disabled';
        $hit_miss_ratio      = 0.0;
        $cache_size_human    = __( 'Empty', 'my-pro-cache' );
        $warm_queue_count    = 0;
        $object_cache_engine = wp_using_ext_object_cache() ? __( 'External', 'my-pro-cache' ) : __( 'Internal', 'my-pro-cache' );
        $recent_purges       = array();
        $debug_enabled       = (bool) $this->options->get( 'debug_enabled', false );
        $recent_debug_log    = array();

        if ( 'dashboard' === $slug ) {
            $context             = $this->build_dashboard_context();
            $cache_status        = $context['cache_status'];
            $hit_miss_ratio      = $context['hit_miss_ratio'];
            $cache_size_human    = $context['cache_size_human'];
            $warm_queue_count    = $context['warm_queue_count'];
            $object_cache_engine = $context['object_cache_engine'];
            $recent_purges       = $context['recent_purges'];
            $debug_enabled       = $context['debug_enabled'];
            $recent_debug_log    = $context['recent_debug_log'];
        } elseif ( 'presets' === $slug ) {
            $preset_context   = $this->build_presets_context();
            $presets          = $preset_context['presets'];
            $preset_diffs     = $preset_context['diffs'];
            $preset_guards    = $preset_context['guards'];
            $preset_state     = $preset_context['state'];
            $preset_backup    = $preset_context['backup'];
            $current_options  = $preset_context['current'];
        }

        $view = MY_PRO_CACHE_PLUGIN_DIR . 'admin/views/' . $slug . '.php';

        if ( ! file_exists( $view ) ) {
            $view = MY_PRO_CACHE_PLUGIN_DIR . 'admin/views/settings-page.php';
        }

        require $view;
    }

    /**
     * Build navigation tab metadata for the plugin pages.
     */
    private function build_tabs( string $current ): array
    {
        $tabs = array();

        foreach ( Schema::pages() as $slug => $page ) {
            $tabs[] = array(
                'slug'    => $slug,
                'label'   => $page['menu_title'] ?? $page['page_title'] ?? ucfirst( $slug ),
                'url'     => admin_url( 'admin.php?page=my-pro-cache-' . $slug ),
                'current' => $slug === $current,
            );
        }

        return $tabs;
    }

    /**
     * Compute dashboard metrics and recent log entries.
     */
    private function build_dashboard_context(): array
    {
        $cache_dir   = trailingslashit( WP_CONTENT_DIR ) . 'cache/my-pro-cache/pages';
        $size_bytes  = $this->directory_size( $cache_dir );
        $cache_size  = $size_bytes > 0 ? size_format( $size_bytes ) : __( 'Empty', 'my-pro-cache' );

        $raw_stats   = get_option( 'my_pro_cache_stats', array( 'hits' => 0, 'misses' => 0 ) );
        $hits        = isset( $raw_stats['hits'] ) ? (int) $raw_stats['hits'] : 0;
        $misses      = isset( $raw_stats['misses'] ) ? (int) $raw_stats['misses'] : 0;
        $total       = max( 0, $hits + $misses );
        $ratio       = $total > 0 ? ( $hits / $total ) * 100 : 0;

        $warm_queue  = (array) get_option( 'my_pro_cache_preload_queue', array() );

        $object_cache_engine = (bool) $this->options->get( 'oc_enabled', false )
            ? ucfirst( (string) $this->options->get( 'oc_backend', 'internal' ) )
            : ( wp_using_ext_object_cache() ? __( 'External', 'my-pro-cache' ) : __( 'Internal', 'my-pro-cache' ) );

        $recent_purges = $this->parse_recent_purges( (array) get_option( 'my_pro_cache_recent_purges', array() ) );
        $debug_log     = $this->collect_debug_lines();

        $cache_status = 'disabled';
        $dropin_path  = trailingslashit( WP_CONTENT_DIR ) . 'advanced-cache.php';

        if ( defined( 'WP_CACHE' ) && WP_CACHE ) {
            $cache_status = file_exists( $dropin_path ) ? 'enabled' : 'attention';
        }

        return array(
            'cache_status'        => $cache_status,
            'hit_miss_ratio'      => round( $ratio, 2 ),
            'cache_size_human'    => $cache_size,
            'warm_queue_count'    => count( $warm_queue ),
            'object_cache_engine' => $object_cache_engine,
            'recent_purges'       => $recent_purges,
            'debug_enabled'       => (bool) $this->options->get( 'debug_enabled', false ),
            'recent_debug_log'    => $debug_log,
        );
    }

    /**
     * Prepare data required by the Presets view including diffs and guards.
     */
    private function build_presets_context(): array
    {
        $presets = Presets::all();
        $current = $this->options->all();

        $diffs  = array();
        $guards = array();

        foreach ( $presets as $id => $preset ) {
            $diffs[ $id ]  = Presets::calculate_diff( $current, $preset['overrides'] );
            $guards[ $id ] = Presets::evaluate_guards( $preset );
        }

        $state  = get_option( Presets::STATE_OPTION, array() );
        $backup = get_option( Presets::BACKUP_OPTION, array() );

        return array(
            'presets' => $presets,
            'diffs'   => $diffs,
            'guards'  => $guards,
            'state'   => is_array( $state ) ? $state : array(),
            'backup'  => is_array( $backup ) ? $backup : array(),
            'current' => $current,
        );
    }

    /**
     * Return the latest debug.log entries for the dashboard panel.
     */
    private function collect_debug_lines(): array
    {
        $raw_log = $this->logger->get_log();
        if ( '' === trim( $raw_log ) ) {
            return array();
        }

        $lines = array_map( 'trim', explode( "\n", $raw_log ) );
        $lines = array_filter( $lines, static function ( $line ) {
            return $line !== '';
        } );

        return array_slice( $lines, -50 );
    }

    /**
     * Normalise purge log entries and extract timestamps.
     */
    private function parse_recent_purges( array $entries ): array
    {
        $parsed = array();

        foreach ( $entries as $entry ) {
            if ( ! is_string( $entry ) || '' === trim( $entry ) ) {
                continue;
            }

            $url     = trim( $entry );
            $time    = 0;
            $parts   = explode( '@', $entry );

            if ( count( $parts ) >= 2 ) {
                $url_candidate  = trim( $parts[0] );
                $time_candidate = trim( implode( '@', array_slice( $parts, 1 ) ) );
                $timestamp      = strtotime( $time_candidate );

                if ( $url_candidate !== '' ) {
                    $url = $url_candidate;
                }

                if ( $timestamp ) {
                    $time = $timestamp;
                }
            }

            $parsed[] = array(
                'url'  => $url,
                'time' => $time,
                'age'  => $time ? human_time_diff( $time, current_time( 'timestamp' ) ) : '',
                'date' => $time ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time ) : '',
            );
        }

        return array_slice( $parsed, 0, 10 );
    }

    /**
     * Compute total disk usage for a cache directory.
     */
    private function directory_size( string $path ): int
    {
        if ( ! is_dir( $path ) ) {
            return 0;
        }

        $total = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ( $iterator as $file ) {
            if ( $file->isFile() ) {
                $total += $file->getSize();
            }
        }

        return $total;
    }
}