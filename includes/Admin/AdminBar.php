<?php
/**
 * Adds My Pro Cache shortcuts to the WordPress admin toolbar.
 */

namespace MyProCache\Admin;

use MyProCache\Options\Manager;
use MyProCache\Support\Capabilities;
use function add_query_arg;
use function __;
use function admin_url;
use function current_user_can;
use function esc_url_raw;
use function is_ssl;
use function wp_get_referer;
use function wp_nonce_url;
use WP_Admin_Bar;

/**
 * Builds top-bar menu entries for cache maintenance actions.
 */
class AdminBar
{
    private Manager $options;

    /**
     * Store option manager for later checks.
     *
     * @param Manager $options Options repository.
     */
    public function __construct( Manager $options )
    {
        $this->options = $options;
    }

    /**
     * Inject menu nodes into the admin toolbar.
     *
     * @param WP_Admin_Bar $wp_admin_bar Toolbar instance.
     */
    public function register( WP_Admin_Bar $wp_admin_bar ): void
    {
        if ( ! current_user_can( Capabilities::manage_capability() ) ) {
            return;
        }

        $wp_admin_bar->add_menu( array(
            'id'    => 'my-pro-cache-root',
            'title' => __( 'My Pro Cache', 'my-pro-cache' ),
            'href'  => admin_url( 'admin.php?page=my-pro-cache-dashboard' ),
        ) );

        $wp_admin_bar->add_menu( array(
            'id'     => 'my-pro-cache-purge-all',
            'parent' => 'my-pro-cache-root',
            'title'  => __( 'Purge All', 'my-pro-cache' ),
            'href'   => $this->action_url( 'purge_all' ),
        ) );

        $wp_admin_bar->add_menu( array(
            'id'     => 'my-pro-cache-purge-page',
            'parent' => 'my-pro-cache-root',
            'title'  => __( 'Purge This Page', 'my-pro-cache' ),
            'href'   => $this->action_url( 'purge_url', $this->current_url() ),
        ) );

        $wp_admin_bar->add_menu( array(
            'id'     => 'my-pro-cache-preload',
            'parent' => 'my-pro-cache-root',
            'title'  => __( 'Preload All', 'my-pro-cache' ),
            'href'   => $this->action_url( 'preload_all' ),
        ) );

        $wp_admin_bar->add_menu( array(
            'id'     => 'my-pro-cache-debug',
            'parent' => 'my-pro-cache-root',
            'title'  => $this->options->get( 'debug_enabled', false ) ? __( 'Disable Debug', 'my-pro-cache' ) : __( 'Enable Debug', 'my-pro-cache' ),
            'href'   => $this->action_url( 'toggle_debug' ),
        ) );
    }

    private function action_url( string $action, string $url = '' ): string
    {
        $args = array(
            'action'        => 'my_pro_cache_action',
            'mpc_action'    => $action,
            '_mpc_redirect' => $this->current_url(),
        );

        if ( 'purge_url' === $action && $url ) {
            $args['target_url'] = $url;
        }

        $query = add_query_arg( $args, admin_url( 'admin-post.php' ) );

        return wp_nonce_url( $query, 'my_pro_cache_admin_action', '_mpc_nonce' );
    }

    /**
     * Determine the current admin/browser URL to redirect back to.
     */
    private function current_url(): string
    {
        $referer = wp_get_referer();

        if ( $referer ) {
            return esc_url_raw( $referer );
        }

        $scheme = is_ssl() ? 'https://' : 'http://';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';

        return $scheme . $host . $uri;
    }
}

