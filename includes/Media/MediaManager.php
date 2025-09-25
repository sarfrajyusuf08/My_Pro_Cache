<?php

namespace MyProCache\Media;

use MyProCache\Debug\Logger;
use MyProCache\Options\Manager;
use function add_action;
use function add_filter;
use function array_filter;
use function array_map;
use function array_unique;
use function esc_attr;
use function esc_html;
use function esc_url;
use function file_exists;
use function get_post_mime_type;
use function in_array;
use function is_admin;
use function is_string;
use function pathinfo;
use function preg_replace;
use function preg_replace_callback;
use function preg_split;
use function stripos;
use function strtolower;
use function trim;
use function wp_attachment_is_image;
use function unlink;
use function wp_get_attachment_metadata;
use function wp_get_image_editor;
use function wp_get_upload_dir;
use function wp_parse_url;
use function trailingslashit;
use function is_wp_error;
use function wp_upload_dir;
use function wp_unslash;
use const PATHINFO_EXTENSION;

class MediaManager
{
    private Manager $options;

    private Logger $logger;

    private bool $lazyImages = true;

    private bool $lazyIframes = true;

    private bool $lqipPlaceholders = false;

    private bool $convertWebp = false;

    private bool $convertAvif = false;

    private bool $rewriteModernFormats = false;

    private array $excludes = array();

    public function __construct( Manager $options, Logger $logger )
    {
        $this->options = $options;
        $this->logger  = $logger;
    }

    public function register(): void
    {
        if ( is_admin() ) {
            return;
        }

        $this->lazyImages        = (bool) $this->options->get( 'lazyload_images', true );
        $this->lazyIframes       = (bool) $this->options->get( 'lazyload_iframes', true );
        $this->lqipPlaceholders  = (bool) $this->options->get( 'lqip_placeholders', false );
        $this->convertWebp       = (bool) $this->options->get( 'convert_webp', false );
        $this->convertAvif       = (bool) $this->options->get( 'convert_avif', false );
        $this->rewriteModernFormats = (bool) $this->options->get( 'webp_avif_rewrite', false );
        $this->excludes          = $this->normalize_list( $this->options->get( 'media_excludes', array() ) );

        add_filter( 'wp_lazy_loading_enabled', array( $this, 'filter_lazy_loading' ), 10, 3 );
        add_filter( 'wp_get_attachment_image_attributes', array( $this, 'filter_image_attributes' ), 10, 3 );

        if ( $this->lazyIframes ) {
            add_filter( 'the_content', array( $this, 'filter_iframes_in_content' ), 20 );
            add_filter( 'embed_oembed_html', array( $this, 'filter_iframe_embed' ), 20, 3 );
        }

        if ( $this->convertWebp || $this->convertAvif ) {
            add_filter( 'wp_generate_attachment_metadata', array( $this, 'generate_modern_formats' ), 20, 2 );
            add_action( 'delete_attachment', array( $this, 'delete_generated_formats' ) );
        }

        if ( $this->rewriteModernFormats ) {
            add_filter( 'wp_get_attachment_image_src', array( $this, 'rewrite_image_src' ), 20, 4 );
            add_filter( 'wp_calculate_image_srcset', array( $this, 'rewrite_srcset' ), 20, 5 );
        }

        if ( $this->lqipPlaceholders ) {
            add_action( 'wp_head', array( $this, 'output_lqip_styles' ) );
            add_action( 'wp_footer', array( $this, 'output_lqip_script' ) );
        }
    }

    public function filter_lazy_loading( bool $default, string $tag, string $context ): bool
    {
        if ( 'img' !== $tag ) {
            return $default;
        }

        return $this->lazyImages;
    }

    public function filter_image_attributes( array $attr, object $attachment, string $size ): array
    {
        $src = $attr['src'] ?? '';

        if ( ! $this->lazyImages ) {
            $attr['loading'] = 'eager';
        } elseif ( ! $this->is_excluded( $src ) && empty( $attr['loading'] ) ) {
            $attr['loading'] = 'lazy';
        }

        if ( $this->lqipPlaceholders && ! $this->is_excluded( $src ) ) {
            $classes = isset( $attr['class'] ) ? $attr['class'] . ' ' : '';
            $attr['class'] = $classes . 'mpc-lqip';
        }

        if ( isset( $attr['class'] ) && $this->is_excluded( $attr['class'] ) ) {
            $attr['loading'] = 'eager';
        }

        return $attr;
    }

    public function filter_iframes_in_content( string $content ): string
    {
        if ( stripos( $content, '<iframe' ) === false ) {
            return $content;
        }

        return preg_replace_callback(
            '/<iframe([^>]*)>(.*?)<\/iframe>/is',
            function ( array $matches ) {
                $tag = $matches[0];
                if ( $this->is_excluded( $tag ) ) {
                    return $tag;
                }

                if ( stripos( $tag, 'loading=' ) === false ) {
                    $tag = preg_replace( '/<iframe/', '<iframe loading="lazy" ', $tag, 1 );
                }

                return $tag;
            },
            $content
        );
    }

    public function filter_iframe_embed( string $html, $url, array $attr ): string
    {
        if ( stripos( $html, '<iframe' ) === false ) {
            return $html;
        }

        return $this->filter_iframes_in_content( $html );
    }

    public function generate_modern_formats( array $metadata, int $attachment_id ): array
    {
        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            return $metadata;
    }

        $upload   = wp_upload_dir();
        $base_dir = trailingslashit( $upload['basedir'] );
        $relative = $metadata['file'] ?? '';

        if ( '' === $relative ) {
            return $metadata;
        }

        $metadata['mpc_formats'] = $metadata['mpc_formats'] ?? array();

        $targets = array();

        if ( $this->convertWebp ) {
            $targets[] = 'webp';
        }

        if ( $this->convertAvif ) {
            $targets[] = 'avif';
        }

        if ( empty( $targets ) ) {
            return $metadata;
        }

        $sizes = $metadata['sizes'] ?? array();
        $all_files = array( 'full' => $relative );

        foreach ( $sizes as $size => $info ) {
            if ( empty( $info['file'] ) ) {
                continue;
            }
            $subdir = pathinfo( $relative, PATHINFO_DIRNAME );
            if ( '' === $subdir || '.' === $subdir ) {
                $subdir = '';
            }
            $path = $subdir ? $subdir . '/'. $info['file'] : $info['file'];
            $all_files[ $size ] = $path;
        }

        foreach ( $targets as $format ) {
            foreach ( $all_files as $size_key => $rel_path ) {
                $source = $base_dir . $rel_path;
                if ( ! file_exists( $source ) ) {
                    continue;
                }
                $generated = $this->generate_variant( $source, $format );
                if ( $generated ) {
                    $metadata['mpc_formats'][ $format ][ $size_key ] = $generated;
                }
            }
        }

        return $metadata;
    }

    public function delete_generated_formats( int $attachment_id ): void
    {
        $meta = wp_get_attachment_metadata( $attachment_id );
        if ( empty( $meta['mpc_formats'] ) || ! is_array( $meta['mpc_formats'] ) ) {
            return;
        }

        $upload   = wp_upload_dir();
        $base_dir = trailingslashit( $upload['basedir'] );

        foreach ( $meta['mpc_formats'] as $format => $entries ) {
            foreach ( $entries as $relative ) {
                $path = $base_dir . ltrim( $relative, '/' );
                if ( file_exists( $path ) ) {
                    unlink( $path );
                }
            }
        }
    }

    public function rewrite_image_src( $image, int $attachment_id, $size, bool $icon )
    {
        if ( ! is_array( $image ) || empty( $image[0] ) || $this->is_excluded( $image[0] ) ) {
            return $image;
        }

        $variant = $this->locate_variant( $attachment_id, $size, $image[0] );
        if ( $variant ) {
            $image[0] = $variant;
        }

        return $image;
    }

    public function rewrite_srcset( array $sources, array $size_array, string $image_src, array $image_meta, int $attachment_id ): array
    {
        foreach ( $sources as &$source ) {
            if ( empty( $source['url'] ) || $this->is_excluded( $source['url'] ) ) {
                continue;
            }
            $new = $this->locate_variant_by_url( $attachment_id, $source['url'], $image_meta );
            if ( $new ) {
                $source['url'] = $new;
            }
        }

        return $sources;
    }

    public function output_lqip_styles(): void
    {
        if ( ! $this->lqipPlaceholders ) {
            return;
        }

        echo '<style id="mpc-lqip">img.mpc-lqip{filter:blur(12px);transition:filter .3s ease,opacity .3s ease;}img.mpc-lqip[data-mpc-loaded="1"]{filter:none;opacity:1;}</style>';
    }

    public function output_lqip_script(): void
    {
        if ( ! $this->lqipPlaceholders ) {
            return;
        }

        echo '<script id="mpc-lqip-js">document.addEventListener("DOMContentLoaded",function(){var imgs=document.querySelectorAll("img.mpc-lqip");imgs.forEach(function(img){if(img.complete){img.setAttribute("data-mpc-loaded","1");}img.addEventListener("load",function(){img.setAttribute("data-mpc-loaded","1");},{once:true});});});</script>';
    }

    private function locate_variant( int $attachment_id, $size, string $url ): ?string
    {
        $meta = wp_get_attachment_metadata( $attachment_id );
        if ( empty( $meta['mpc_formats'] ) || ! is_array( $meta['mpc_formats'] ) ) {
            return null;
        }

        $format = $this->preferred_format();
        if ( ! $format || empty( $meta['mpc_formats'][ $format ] ) ) {
            return null;
        }

        $key = is_string( $size ) ? $size : 'full';

        $relative = $meta['mpc_formats'][ $format ][ $key ] ?? null;
        if ( ! $relative ) {
            return $this->locate_variant_by_url( $attachment_id, $url, $meta );
        }

        return $this->build_url_from_relative( $relative );
    }

    private function locate_variant_by_url( int $attachment_id, string $url, array $meta ): ?string
    {
        if ( empty( $meta['mpc_formats'] ) || ! is_array( $meta['mpc_formats'] ) ) {
            return null;
        }

        $format = $this->preferred_format();
        if ( ! $format || empty( $meta['mpc_formats'][ $format ] ) ) {
            return null;
        }

        $upload = wp_upload_dir();
        $baseurl = trailingslashit( $upload['baseurl'] );
        if ( 0 !== stripos( $url, $baseurl ) ) {
            return null;
        }

        $relative = ltrim( substr( $url, strlen( $baseurl ) ), '/' );

        $subdir = pathinfo( $meta['file'], PATHINFO_DIRNAME );
        if ( '' === $subdir || '.' === $subdir ) {
            $subdir = '';
        }

        foreach ( $meta['mpc_formats'][ $format ] as $entry => $path ) {
            if ( $path === $relative ) {
                return $this->build_url_from_relative( $path );
            }
            $expected = ( $subdir ? $subdir . '/' : '' ) . basename( $path );
            if ( $expected === $relative ) {
                return $this->build_url_from_relative( $path );
            }
        }

        return null;
    }

    private function preferred_format(): ?string
    {
        if ( ! $this->rewriteModernFormats ) {
            return null;
        }

        $accept = strtolower( $_SERVER['HTTP_ACCEPT'] ?? '' );

        if ( $this->convertAvif && false !== strpos( $accept, 'image/avif' ) ) {
            return 'avif';
        }

        if ( $this->convertWebp && false !== strpos( $accept, 'image/webp' ) ) {
            return 'webp';
        }

        return null;
    }

    private function generate_variant( string $source, string $format ): ?string
    {
        $editor = wp_get_image_editor( $source );
        if ( is_wp_error( $editor ) ) {
            $this->logger->log( 'media', 'Unable to load image editor for '. $source );
            return null;
        }

        $destination = preg_replace( '/\.[^.]+$/', '.'. $format, $source );
        if ( ! $destination ) {
            return null;
        }

        $saved = $editor->save( $destination, 'image/'. $format );
        if ( is_wp_error( $saved ) ) {
            $this->logger->log( 'media', 'Saving '. $format . ' failed for '. $source );
            return null;
        }

        $upload = wp_upload_dir();
        $base   = trailingslashit( $upload['basedir'] );

        return ltrim( str_replace( $base, '', $saved['path'] ?? $destination ), '/' );
    }

    private function normalize_list( $value ): array
    {
        if ( is_string( $value ) ) {
            $value = preg_split( '/

|
|
/', $value );
        }

        $value = (array) $value;

        $value = array_map(
            static function ( $item ) {
                return strtolower( trim( (string) $item ) );
            },
            $value
        );

        $value = array_filter(
            $value,
            static function ( $item ) {
                return '' !== $item;
            }
        );

        return array_values( array_unique( $value ) );
    }

    private function normalize_handles( $value ): array
    {
        return $this->normalize_list( $value );
    }

    private function build_url_from_relative( string $relative ): string
    {
        $upload = wp_upload_dir();
        return trailingslashit( $upload['baseurl'] ) . ltrim( $relative, '/' );
    }

    private function is_excluded( string $value ): bool
    {
        $value = strtolower( $value );
        foreach ( $this->excludes as $needle ) {
            if ( $needle && false !== strpos( $value, $needle ) ) {
                return true;
            }
        }

        return false;
    }
}




