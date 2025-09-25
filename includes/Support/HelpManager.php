<?php

namespace MyProCache\Support;

use MyProCache\Debug\Logger;
use MyProCache\Options\Manager;
use function add_action;
use function current_user_can;
use function check_admin_referer;
use function admin_url;
use function esc_url_raw;
use function wp_get_referer;
use function wp_safe_redirect;
use function sanitize_key;
use function wp_unslash;
use function nocache_headers;
use function gmdate;
use function apply_filters;


class HelpManager
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
        add_action( 'admin_post_my_pro_cache_help', array( $this, 'handle_request' ) );
    }

    public function handle_request(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_safe_redirect( admin_url() );
            return;
        }

        check_admin_referer( 'my_pro_cache_help_action', '_mpc_help_nonce' );

        $request = wp_unslash( $_POST );
        $action  = isset( $request['mpc_help_action'] ) ? sanitize_key( $request['mpc_help_action'] ) : '';

        if ( 'download_report' === $action ) {
            $this->download_report();
            return;
        }

        $referer = wp_get_referer();
        wp_safe_redirect( $referer ? esc_url_raw( $referer ) : admin_url() );
        exit;
    }

    private function download_report(): void
    {
        $report = SystemReport::generate();
        $filename = 'my-pro-cache-system-report-' . gmdate( 'Ymd-His' ) . '.txt';

        nocache_headers();
        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $report ) );

        echo $report;
        exit;
    }
}







