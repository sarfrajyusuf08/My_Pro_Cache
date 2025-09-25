<?php

namespace MyProCache\Support;

use function get_bloginfo;
use function get_option;
use function home_url;
use function is_multisite;
use function phpversion;
use function site_url;
use function basename;
use function size_format;
use function wp_get_theme;
use function wp_using_ext_object_cache;
use function get_users;
use function wp_get_active_and_valid_plugins;
use function implode;
use function sprintf;
use const ABSPATH;

class SystemReport
{
    public static function generate(): string
    {
        global $wpdb;

        $theme      = wp_get_theme();
        $plugins    = wp_get_active_and_valid_plugins();
        $plugin_list = array_map( function( $plugin ) {
            return basename( dirname( $plugin ) ) . '/' . basename( $plugin );
        }, $plugins );

        $lines = array(
            'WordPress: ' . get_bloginfo( 'version' ),
            'PHP: ' . phpversion(),
            'Site URL: ' . site_url(),
            'Home URL: ' . home_url(),
            'Multisite: ' . ( is_multisite() ? 'Yes' : 'No' ),
            'DB Version: ' . ( isset( $wpdb->db_version ) ? $wpdb->db_version() : 'n/a' ),
            'Table Prefix: ' . $wpdb->prefix,
            'Theme: ' . $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ),
            'Object Cache: ' . ( wp_using_ext_object_cache() ? 'External' : 'Internal' ),
            'Active Plugins: ' . implode( ', ', $plugin_list ),
        );

        return implode( "\n", $lines );
    }
}

