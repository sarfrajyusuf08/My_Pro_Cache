<?php

namespace MyProCache\Toolbox;

use MyProCache\Debug\Logger;
use MyProCache\Options\Defaults;
use MyProCache\Options\Manager;
use function add_action;
use function add_filter;
use function esc_html__;
use function esc_url_raw;
use function admin_url;
use function current_user_can;
use function check_admin_referer;
use function wp_safe_redirect;
use function wp_get_referer;
use function wp_json_encode;
use function wp_unslash;
use function sanitize_key;
use function wp_cache_flush;
use function apply_filters;
use function wp_remote_get;
use function trailingslashit;
use const ABSPATH;

class ToolboxManager
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
        add_action( 'init', array( $this, 'maybe_enable_safe_mode' ), 1 );
        add_action( 'admin_post_my_pro_cache_toolbox_action', array( $this, 'handle_admin_post' ) );
        add_filter( 'my_pro_cache_toolbox_snippet', array( $this, 'generate_snippet' ) );
    }

    public function handle_admin_post(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_safe_redirect( admin_url() );
            return;
        }

        check_admin_referer( 'my_pro_cache_admin_action', '_mpc_nonce' );

        $request = $_POST;
        $action  = isset( $request['mpc_toolbox_action'] ) ? sanitize_key( wp_unslash( $request['mpc_toolbox_action'] ) ) : '';

        switch ( $action ) {
            case 'enable_safe_mode':
                $this->options->update( array( 'toolbox_safe_mode' => true ) );
                $this->logger->log( 'toolbox', 'Safe mode enabled by admin action.' );
                break;
            case 'disable_safe_mode':
                $this->options->update( array( 'toolbox_safe_mode' => false ) );
                $this->logger->log( 'toolbox', 'Safe mode disabled by admin action.' );
                break;
            case 'reset_defaults':
                $this->options->update( Defaults::all() );
                $this->logger->log( 'toolbox', 'Settings reset to defaults.' );
                wp_cache_flush();
                break;
            case 'generate_snippet':
                $snippet = $this->generate_snippet( '' );
                $this->options->update( array( 'toolbox_generated_snippet' => $snippet ) );
                break;
        }

        $referer = wp_get_referer();
        wp_safe_redirect( $referer ? esc_url_raw( $referer ) : admin_url() );
        exit;
    }

    public function maybe_enable_safe_mode(): void
    {
        if ( ! $this->options->get( 'toolbox_safe_mode', false ) ) {
            return;
        }

        add_filter( 'my_pro_cache_module_enabled', function ( $enabled, $module ) {
            $allowed = array( 'cache', 'general', 'debug' );
            if ( in_array( $module, $allowed, true ) ) {
                return $enabled;
            }
            return false;
        }, 10, 2 );
    }

    public function generate_snippet( $snippet )
    {
        $target = $this->options->get( 'toolbox_server_snippet', 'apache' );

        $rules = '';
        switch ( $target ) {
            case 'nginx':
                $rules = "# My Pro Cache
location ~* \.(?:html|htm)$ {
    add_header X-My-Pro-Cache HIT always;
    try_files $uri $uri/ /index.php?$args;
}
";
                break;
            case 'litespeed':
            case 'apache':
            default:
                $rules = "# My Pro Cache
<IfModule mod_headers.c>
    Header set X-My-Pro-Cache HIT
</IfModule>
";
                break;
        }

        return apply_filters( 'my_pro_cache_toolbox_snippet', $rules, $target );
    }
}

