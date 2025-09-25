<?php
/**
 * Handles enqueuing admin styles and scripts for My Pro Cache pages.
 */

namespace MyProCache\Admin;

use function plugins_url;
use function str_contains;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;
use function wp_create_nonce;
use const MY_PRO_CACHE_FILE;
use const MY_PRO_CACHE_VERSION;

/**
 * Registers admin asset bundles for the plugin screens.
 */
class Assets
{
    /**
     * Enqueue global and screen-specific assets when viewing plugin pages.
     *
     * @param string $hook Current admin page hook suffix.
     */
    public function enqueue( string $hook ): void
    {
        if ( ! str_contains( $hook, 'my-pro-cache' ) ) {
            return;
        }

        $base = plugins_url( '', MY_PRO_CACHE_FILE );

        wp_enqueue_style(
            'my-pro-cache-admin',
            $base . '/assets/css/admin.css',
            array(),
            MY_PRO_CACHE_VERSION
        );

        wp_enqueue_style(
            'my-pro-cache-dashboard',
            $base . '/assets/admin/dashboard.css',
            array(),
            MY_PRO_CACHE_VERSION
        );

        wp_enqueue_script(
            'my-pro-cache-dashboard',
            $base . '/assets/admin/dashboard.js',
            array(),
            MY_PRO_CACHE_VERSION,
            true
        );

        wp_enqueue_script(
            'my-pro-cache-admin',
            $base . '/assets/js/admin.js',
            array( 'jquery' ),
            MY_PRO_CACHE_VERSION,
            true
        );

        wp_localize_script(
            'my-pro-cache-admin',
            'MyProCacheAdmin',
            array(
                'nonce' => wp_create_nonce( 'my_pro_cache_admin_action' ),
            )
        );
    }
}
