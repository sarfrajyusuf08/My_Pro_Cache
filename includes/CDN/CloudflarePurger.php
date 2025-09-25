<?php

namespace MyProCache\CDN;

use MyProCache\Debug\Logger;
use MyProCache\Options\Manager;
use function add_action;
use function esc_url;
use function is_wp_error;
use function wp_remote_post;

class CloudflarePurger
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
        if ( ! $this->options->get( 'cf_enabled', false ) ) {
            return;
        }

        add_action( 'my_pro_cache_purge_cdn', array( $this, 'purge' ), 10, 2 );
    }

    public function purge( array $tags = array(), $context = null ): void
    {
        $token = trim( (string) $this->options->get( 'cf_api_token', '' ) );
        $zone  = trim( (string) $this->options->get( 'cf_zone_id', '' ) );

        if ( '' === $token || '' === $zone ) {
            $this->logger->log( 'cdn', 'Cloudflare purge skipped: missing API token or zone ID.' );
            return;
        }

        $endpoint = sprintf( 'https://api.cloudflare.com/client/v4/zones/%s/purge_cache', urlencode( $zone ) );

        $body = array( 'purge_everything' => true );
        $response = wp_remote_post(
            $endpoint,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( $body ),
                'timeout' => 15,
            )
        );

        if ( is_wp_error( $response ) ) {
            $this->logger->log( 'cdn', 'Cloudflare purge failed: ' . $response->get_error_message() );
            return;
        }

        $code = (int) ( $response['response']['code'] ?? 0 );
        if ( 200 === $code ) {
            $this->logger->log( 'cdn', 'Cloudflare cache purged successfully.' );
        } else {
            $this->logger->log( 'cdn', 'Cloudflare purge returned HTTP ' . $code );
        }
    }
}