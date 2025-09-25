<?php
/**
 * Registers My Pro Cache menu entries inside the WordPress admin menu.
 */

namespace MyProCache\Admin;

use MyProCache\Options\Schema;
use MyProCache\Support\Capabilities;
use function add_menu_page;
use function __;
use function add_submenu_page;
use function is_callable;

/**
 * Creates main and submenu pages for the plugin screens.
 */
class MenuBuilder
{
    private PageRenderer $renderer;

    /**
     * Accept renderer dependency for callbacks.
     *
     * @param PageRenderer $renderer Responsible for outputting views.
     */
    public function __construct( PageRenderer $renderer )
    {
        $this->renderer = $renderer;
    }

    public function register( $context = '' ): void
    {
        $pages     = Schema::pages();
        $cap       = Capabilities::manage_capability();
        $menu_slug = 'my-pro-cache';
        $icon      = 'dashicons-performance';
        $position  = 55;

        // top level page (dashboard)
        add_menu_page(
            $pages['dashboard']['page_title'] ?? 'My Pro Cache',
            __( 'My Pro Cache', 'my-pro-cache' ),
            $cap,
            $menu_slug,
            function() {
                $this->renderer->render( 'dashboard' );
            },
            $icon,
            $position
        );

        foreach ( $pages as $slug => $page ) {
            $submenu_slug = 'my-pro-cache-' . $slug;
            $menu_title   = $page['menu_title'] ?? $page['page_title'] ?? ucfirst( $slug );

            add_submenu_page(
                $menu_slug,
                $page['page_title'] ?? $menu_title,
                $menu_title,
                $cap,
                $submenu_slug,
                function() use ( $slug ) {
                    $this->renderer->render( $slug );
                }
            );
        }
    }
}

