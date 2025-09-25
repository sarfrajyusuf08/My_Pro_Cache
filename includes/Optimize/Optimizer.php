<?php

namespace MyProCache\Optimize;

use MyProCache\Options\Manager;
use function add_action;
use function add_filter;
use function array_filter;
use function array_map;
use function array_unique;
use function esc_attr;
use function esc_url;
use function in_array;
use function is_admin;
use function preg_replace;
use function preg_split;
use function sprintf;
use function stripos;
use function trim;
use function wp_parse_url;

class Optimizer
{
    private Manager $options;

    private bool $asyncCss = false;

    private bool $deferScripts = false;

    private bool $delayScripts = false;

    private bool $hasDelayedScripts = false;

    private array $delayAllowlist = array();

    private array $excludeCssHandles = array();

    private array $excludeJsHandles = array();

    public function __construct( Manager $options )
    {
        $this->options = $options;
    }

    public function register(): void
    {
        if ( ! $this->options->get( 'general_module_optimize', true ) ) {
            return;
        }

        if ( is_admin() ) {
            return;
        }

        $this->asyncCss      = (bool) $this->options->get( 'css_async', false );
        $this->deferScripts  = (bool) $this->options->get( 'js_defer', false );
        $this->delayScripts  = (bool) $this->options->get( 'js_delay_until_interaction', false );
        $this->delayAllowlist    = $this->normalize_handles( $this->options->get( 'js_delay_allowlist', array() ) );
        $this->excludeCssHandles = $this->normalize_handles( $this->options->get( 'exclude_css_handles', array() ) );
        $this->excludeJsHandles  = $this->normalize_handles( $this->options->get( 'exclude_js_handles', array() ) );

        add_action( 'wp_head', array( $this, 'maybe_preload_assets' ), 1 );

        if ( $this->asyncCss ) {
            add_filter( 'style_loader_tag', array( $this, 'filter_style_tag' ), 20, 4 );
        }

        if ( $this->deferScripts || $this->delayScripts ) {
            add_filter( 'script_loader_tag', array( $this, 'filter_script_tag' ), 20, 3 );
        }

        if ( $this->delayScripts ) {
            add_action( 'wp_footer', array( $this, 'output_delay_bootstrap' ), 1 );
        }
    }

    public function maybe_preload_assets(): void
    {
        $preload    = $this->normalize_list( $this->options->get( 'preload_keys', array() ) );
        $prefetch   = $this->normalize_list( $this->options->get( 'dns_prefetch', array() ) );
        $preconnect = $this->normalize_list( $this->options->get( 'preconnect', array() ) );

        if ( empty( $preload ) && empty( $prefetch ) && empty( $preconnect ) ) {
            return;
        }

        foreach ( $preload as $url ) {
            $href = esc_url( $url );
            if ( '' === $href ) {
                continue;
            }

            list( $as, $type_attr, $cross_attr ) = $this->detect_preload_meta( $url );
            printf( "<link rel='preload' href='%s' as='%s'%s%s />\n", $href, esc_attr( $as ), $type_attr, $cross_attr );
        }

        foreach ( $prefetch as $host ) {
            $href = $this->normalize_host( $host, 'dns-prefetch' );
            if ( null === $href ) {
                continue;
            }

            printf( "<link rel='dns-prefetch' href='%s' />\n", esc_url( $href ) );
        }

        foreach ( $preconnect as $host ) {
            $href = $this->normalize_host( $host, 'preconnect' );
            if ( null === $href ) {
                continue;
            }

            printf( "<link rel='preconnect' href='%s' crossorigin />\n", esc_url( $href ) );
        }
    }

    public function filter_style_tag( string $html, string $handle, string $href, string $media ): string
    {
        if ( ! $this->asyncCss || '' === $href ) {
            return $html;
        }

        if ( in_array( strtolower( $handle ), $this->excludeCssHandles, true ) ) {
            return $html;
        }

        $media_attr = '';
        if ( $media && 'all' !== $media ) {
            $media_attr = " media='" . esc_attr( $media ) . "'";
        }

        $preload = sprintf(
            "<link rel='preload' href='%s' as='style'%s onload=\"this.rel='stylesheet'\">",
            esc_url( $href ),
            $media_attr
        );

        $noscript = '<noscript>' . $html . '</noscript>';

        return $preload . "\n" . $noscript;
    }

    public function filter_script_tag( string $tag, string $handle, string $src ): string
    {
        if ( strpos( $tag, ' src=' ) === false || '' === $src ) {
            return $tag;
        }

        $handle_key = strtolower( $handle );

        if ( in_array( $handle_key, $this->excludeJsHandles, true ) ) {
            return $tag;
        }

        $skip_delay = $this->should_skip_delay( $handle_key, $src );

        if ( $this->deferScripts && ! $skip_delay ) {
            $tag = $this->apply_defer_attribute( $tag );
        }

        if ( ! $this->delayScripts || $skip_delay ) {
            return $tag;
        }

        $this->hasDelayedScripts = true;

        $without_src = preg_replace( '~\s+src=([\'\"])(.*?)\1~i', '', $tag, 1 );
        if ( null !== $without_src && '' !== $without_src ) {
            $tag = $without_src;
        }

        $tag = $this->inject_attribute( $tag, "data-mpc-delay='" . esc_url( $src ) . "'" );
        $tag = $this->inject_attribute( $tag, "data-mpc-handle='" . esc_attr( $handle ) . "'" );

        return $tag;
    }

    public function output_delay_bootstrap(): void
    {
        if ( ! $this->hasDelayedScripts ) {
            return;
        }
        ?>
<script id="my-pro-cache-delay-js">(function(){var fired=false;var load=function(){if(fired){return;}fired=true;var scripts=document.querySelectorAll('script[data-mpc-delay]');if(!scripts.length){return;}var each=function(list,cb){for(var i=0;i<list.length;i++){cb(list[i],i);}};each(scripts,function(holder){if(holder.dataset.mpcLoaded){return;}holder.dataset.mpcLoaded='1';var src=holder.getAttribute('data-mpc-delay');if(!src){return;}var clone=document.createElement('script');each(holder.attributes,function(attr){if(attr.name==='data-mpc-delay'||attr.name==='data-mpc-handle'||attr.name==='src'){return;}clone.setAttribute(attr.name,attr.value);});clone.src=src;holder.parentNode.insertBefore(clone,holder);});};var trigger=function(){load();events.forEach(function(evt){window.removeEventListener(evt,trigger,{passive:true});});};var events=['keydown','mousemove','touchstart','scroll'];events.forEach(function(evt){window.addEventListener(evt,trigger,{passive:true,once:true});});setTimeout(load,5000);}());</script>
        <?php
    }

    private function normalize_list( $value ): array
    {
        if ( is_string( $value ) ) {
            $value = preg_split( '/\r\n|\r|\n/', $value );
        }

        $value = (array) $value;

        $value = array_map(
            static function ( $item ) {
                return trim( (string) $item );
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
        $list = $this->normalize_list( $value );

        return array_map(
            static function ( $item ) {
                return strtolower( $item );
            },
            $list
        );
    }

    private function should_skip_delay( string $handle, string $src ): bool
    {
        if ( in_array( $handle, $this->delayAllowlist, true ) ) {
            return true;
        }

        foreach ( $this->delayAllowlist as $needle ) {
            if ( '' === $needle ) {
                continue;
            }

            if ( stripos( $src, $needle ) !== false ) {
                return true;
            }
        }

        return false;
    }

    private function detect_preload_meta( string $url ): array
    {
        $path = wp_parse_url( $url, PHP_URL_PATH );
        $extension = strtolower( pathinfo( $path ?? '', PATHINFO_EXTENSION ) );

        $as         = 'fetch';
        $type_attr  = '';
        $cross_attr = '';

        switch ( $extension ) {
            case 'css':
                $as = 'style';
                break;
            case 'js':
                $as = 'script';
                break;
            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif':
            case 'webp':
            case 'avif':
            case 'svg':
                $as = 'image';
                if ( 'svg' === $extension ) {
                    $type_attr = " type='image/svg+xml'";
                }
                break;
            case 'woff':
            case 'woff2':
            case 'ttf':
            case 'otf':
                $as         = 'font';
                $cross_attr = ' crossorigin';
                $mime       = 'font/' . ( 'ttf' === $extension ? 'ttf' : ( 'otf' === $extension ? 'otf' : $extension ) );
                $type_attr  = " type='" . esc_attr( $mime ) . "'";
                break;
        }

        return array( $as, $type_attr, $cross_attr );
    }

    private function normalize_host( string $value, string $type ): ?string
    {
        $value = trim( $value );
        if ( '' === $value ) {
            return null;
        }

        $parsed = wp_parse_url( $value );

        if ( false === $parsed || empty( $parsed['host'] ) ) {
            $parsed = wp_parse_url( 'https://' . ltrim( $value, '/' ) );
        }

        if ( false === $parsed || empty( $parsed['host'] ) ) {
            return null;
        }

        $host = $parsed['host'];
        $port = isset( $parsed['port'] ) ? ':' . $parsed['port'] : '';

        if ( 'dns-prefetch' === $type ) {
            return '//' . $host . $port;
        }

        $scheme = $parsed['scheme'] ?? 'https';

        return $scheme . '://' . $host . $port . '/';
    }

    private function apply_defer_attribute( string $tag ): string
    {
        if ( ! $this->deferScripts ) {
            return $tag;
        }

        if ( strpos( $tag, ' defer' ) !== false ) {
            return $tag;
        }

        if ( strpos( $tag, " type='module'" ) !== false || strpos( $tag, ' type="module"' ) !== false ) {
            return $tag;
        }

        return $this->inject_attribute( $tag, 'defer' );
    }

    private function inject_attribute( string $tag, string $attribute ): string
    {
        $attribute = trim( $attribute );

        if ( '' === $attribute ) {
            return $tag;
        }

        if ( strpos( $tag, '<script' ) === false ) {
            return $tag;
        }

        if ( strpos( $tag, $attribute ) !== false ) {
            return $tag;
        }

        $updated = preg_replace( '/<script\s+/i', '<script ' . $attribute . ' ', $tag, 1 );

        return $updated ?? $tag;
    }
}
