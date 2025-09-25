<?php

namespace MyProCache\CDN;

use MyProCache\Debug\Logger;
use MyProCache\Options\Manager;
use function add_filter;
use function esc_url;
use function esc_url_raw;
use function home_url;
use function is_admin;
use function trailingslashit;
use function wp_parse_url;

class Rewriter
{
    private Manager $options;

    private Logger $logger;

    private string $siteHost;

    private string $siteScheme;

    private array $fileExtensions;

    private string $primaryHost;

    private string $imageHost;

    private string $staticHost;

    public function __construct( Manager $options, Logger $logger )
    {
        $this->options = $options;
        $this->logger  = $logger;

        $home         = wp_parse_url( home_url( '/' ) );
        $this->siteHost   = $home['host'] ?? '';
        $this->siteScheme = $home['scheme'] ?? 'https';

        $extensions = (array) $options->get( 'cdn_file_types', array( 'css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'woff', 'woff2' ) );
        $extensions = array_map( 'strtolower', array_map( 'trim', $extensions ) );
        $this->fileExtensions = array_filter( $extensions );

        $this->primaryHost = trim( (string) $options->get( 'cdn_host', '' ) );
        $this->imageHost   = trim( (string) $options->get( 'cdn_image_host', '' ) );
        $this->staticHost  = trim( (string) $options->get( 'cdn_static_host', '' ) );
    }

    public function register(): void
    {
        if ( ! $this->options->get( 'cdn_enabled', false ) ) {
            return;
        }

        if ( empty( $this->primaryHost ) ) {
            $this->logger->log( 'cdn', 'CDN rewrite skipped: primary host not configured.' );
            return;
        }

        add_filter( 'style_loader_src', array( $this, 'rewrite_style_src' ), 20, 2 );
        add_filter( 'script_loader_src', array( $this, 'rewrite_script_src' ), 20, 2 );
        add_filter( 'wp_get_attachment_url', array( $this, 'rewrite_attachment' ) );
        add_filter( 'wp_calculate_image_srcset', array( $this, 'rewrite_srcset' ) );
        add_filter( 'wp_resource_hints', array( $this, 'resource_hints' ), 10, 2 );
    }

    public function rewrite_style_src( string $src, string $handle ): string
    {
        return $this->maybe_rewrite( $src, 'static' );
    }

    public function rewrite_script_src( string $src, string $handle ): string
    {
        return $this->maybe_rewrite( $src, 'static' );
    }

    public function rewrite_attachment( string $url ): string
    {
        return $this->maybe_rewrite( $url, 'media' );
    }

    public function rewrite_srcset( array $sources ): array
    {
        foreach ( $sources as &$source ) {
            if ( isset( $source['url'] ) ) {
                $source['url'] = $this->maybe_rewrite( $source['url'], 'media' );
            }
        }

        return $sources;
    }

    public function resource_hints( array $urls, string $relation ): array
    {
        if ( 'preconnect' !== $relation && 'dns-prefetch' !== $relation ) {
            return $urls;
        }

        $hosts = array_filter( array( $this->primaryHost, $this->imageHost, $this->staticHost ) );

        foreach ( $hosts as $host ) {
            $hint = $relation === 'preconnect' ? esc_url( 'https://' . $host ) : esc_url_raw( '//' . $host );
            if ( ! in_array( $hint, $urls, true ) ) {
                $urls[] = $hint;
            }
        }

        return $urls;
    }

    private function maybe_rewrite( string $url, string $context ): string
    {
        if ( '' === $url || is_admin() ) {
            return $url;
        }

        $parts = wp_parse_url( $url );
        if ( empty( $parts['host'] ) ) {
            return $url;
        }

        if ( $parts['host'] !== $this->siteHost ) {
            return $url;
        }

        $extension = strtolower( pathinfo( $parts['path'] ?? '', PATHINFO_EXTENSION ) );
        if ( $extension && ! in_array( $extension, $this->fileExtensions, true ) ) {
            return $url;
        }

        $target = $this->determine_host( $context, $extension );
        if ( ! $target ) {
            return $url;
        }

        $scheme = $parts['scheme'] ?? $this->siteScheme;
        $rewritten = str_replace( $parts['scheme'] . '://' . $parts['host'], $scheme . '://' . $target, $url );

        return $rewritten;
    }

    private function determine_host( string $context, string $extension ): string
    {
        if ( 'media' === $context && $this->imageHost ) {
            return $this->imageHost;
        }

        if ( 'static' === $context && $this->staticHost ) {
            return $this->staticHost;
        }

        return $this->primaryHost;
    }
}