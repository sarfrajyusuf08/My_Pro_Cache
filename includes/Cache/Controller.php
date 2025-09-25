<?php

namespace MyProCache\Cache;

use MyProCache\Cache\StorageFactory;
use MyProCache\Cache\StorageInterface;
use MyProCache\Cache\Tagger;
use MyProCache\Options\Manager;
use MyProCache\Debug\Logger;
use function add_action;
use function do_action;
use function apply_filters;
use function headers_list;
use function headers_sent;
use function header;
use function http_response_code;
use function in_array;
use function array_keys;
use function strtolower;
use function trim;
use function str_replace;
use function is_admin;
use function is_customize_preview;
use function is_embed;
use function is_feed;
use function is_front_page;
use function is_preview;
use function is_search;
use function is_trackback;
use function is_user_logged_in;
use function is_404;
use function is_ssl;
use function ob_start;
use function preg_match;
use function preg_quote;
use function strlen;
use function substr;
use function sha1;
use function time;
use function wp_doing_ajax;
use function wp_doing_cron;
use function wp_is_json_request;
use const REST_REQUEST;
use const DONOTCACHEPAGE;

/**
 * Coordinates cache lookup, storage, and request filtering for front-end traffic.
 * Hooks into WordPress early enough to serve hits before templates execute.
 */
class Controller
{
    private Manager $options;

    private Logger $logger;

    private StorageInterface $storage;

    private string $state = 'BYPASS';

    private string $currentKey = '';

    private string $currentUri = '';

    /**
     * Instantiates the controller with dependencies and prepares the storage backend.
     */
    public function __construct( Manager $options, Logger $logger )
    {
        $this->options = $options;
        $this->logger  = $logger;
        $this->storage = StorageFactory::create( $options, $logger );
        API::init( $options, $this->storage, $logger );
    }

    /**
     * Registers WordPress hooks that attempt to serve cached pages and capture output.
     */
    public function register(): void
    {
        add_action( 'init', array( $this, 'maybe_serve_cache' ), 1 );
        add_action( 'template_redirect', array( $this, 'start_buffer' ), 0 );
        add_action( 'send_headers', array( $this, 'send_headers' ) );
    }

    /**
     * Attempts to short-circuit the request with a cached entry if available and fresh.
     */
    public function maybe_serve_cache(): void
    {
        if ( ! $this->is_cacheable_request( true ) ) {
            $this->state = 'BYPASS';
            API::record_miss();
            return;
        }

        $this->currentKey = Key::build_from_globals( $this->options );
        $this->currentUri = $this->current_url();
        $entry            = API::get( $this->currentKey );

        if ( ! $entry ) {
            $this->state = 'MISS';
            API::record_miss();
            return;
        }

        if ( ! $this->is_entry_fresh( $entry ) ) {
            $this->state = 'STALE';
            $this->storage->purge_uri( $entry['uri'] ?? $this->currentUri );
            API::record_miss();
            return;
        }

        $this->state = 'HIT';
        API::record_hit();

        do_action( 'my_pro_cache_before_serve', $entry );

        $age = time() - (int) $entry['created'];
        header( 'X-My-Pro-Cache: HIT', true );
        header( 'X-My-Pro-Cache-Key: ' . sha1( $this->currentKey ), true );
        header( 'X-My-Pro-Cache-Age: ' . $age, true );

        echo $entry['content'];

        do_action( 'my_pro_cache_after_serve', $entry );
        exit;
    }

    /**
     * Starts output buffering so a cacheable response can be stored after rendering.
     */
    public function start_buffer(): void
    {
        if ( ! $this->is_cacheable_request() ) {
            $this->state = 'BYPASS';
            return;
        }

        $this->currentKey = Key::build_from_globals( $this->options );
        $this->currentUri = $this->current_url();
        $this->state      = 'MISS';
        API::record_miss();

        ob_start( array( $this, 'capture_buffer' ) );
    }

    /**
     * Sends diagnostic headers describing the cache state for the current request.
     */
    public function send_headers(): void
    {
        if ( headers_sent() ) {
            return;
        }

        header( 'X-My-Pro-Cache: ' . $this->state );
        if ( $this->currentKey ) {
            header( 'X-My-Pro-Cache-Key: ' . sha1( $this->currentKey ) );
        }
    }

    /**
     * Evaluates the buffered response, saves it to cache when eligible, and returns it.
     */
    public function capture_buffer( string $buffer ): string
    {
        if ( 'MISS' !== $this->state || strlen( $buffer ) < 255 ) {
            return $buffer;
        }

        if ( http_response_code() >= 400 ) {
            return $buffer;
        }

        $ttl  = $this->determine_ttl();
        $tags = Tagger::current_tags();

        $payload = array(
            'content' => $buffer,
            'headers' => headers_list(),
            'status'  => http_response_code(),
            'uri'     => $this->currentUri,
        );

        API::set( $this->currentKey, $payload, $tags, $ttl );

        return $buffer;
    }

    /**
     * Checks the current request against cache eligibility rules and exclusions.
     */
    private function is_cacheable_request( bool $serving = false ): bool
    {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || wp_is_json_request() || is_preview() || is_trackback() || is_embed() || is_customize_preview() ) {
            return false;
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
            return false;
        }

        if ( defined( 'REST_REQUEST' ) && REST_REQUEST && ! $this->options->get( 'cache_rest_api', true ) ) {
            return false;
        }

        if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
            return false;
        }

        if ( is_feed() ) {
            return false;
        }

        $mode = $this->options->get( 'cache_logged_in_mode', 'bypass' );
        if ( is_user_logged_in() && 'private' !== $mode ) {
            return false;
        }

        if ( $this->matches_exclusions() ) {
            return false;
        }

        return (bool) apply_filters( 'my_pro_cache_should_cache_request', true, $serving );
    }

    /**
     * Tests request metadata against user-defined exclusion patterns and cookies.
     */
    private function matches_exclusions(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        foreach ( (array) $this->options->get( 'exclude_urls', array() ) as $pattern ) {
            if ( $this->pattern_matches( $pattern, $uri ) ) {
                return true;
            }
        }

        if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
            foreach ( (array) $this->options->get( 'exclude_user_agents', array() ) as $pattern ) {
                if ( $this->pattern_matches( $pattern, $_SERVER['HTTP_USER_AGENT'] ) ) {
                    return true;
                }
            }
        }

        foreach ( (array) $this->options->get( 'exclude_cookies', array() ) as $cookie ) {
            if ( isset( $_COOKIE[ $cookie ] ) ) {
                return true;
            }
        }

        if ( ! empty( $_GET ) ) {
            $excludes = array_map( 'strtolower', (array) $this->options->get( 'exclude_query_args', array() ) );
            foreach ( array_keys( $_GET ) as $param ) {
                if ( in_array( strtolower( $param ), $excludes, true ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Matches wildcard or regex-style patterns against the supplied subject string.
     */
    private function pattern_matches( string $pattern, string $subject ): bool
    {
        $pattern = trim( $pattern );
        if ( '' === $pattern ) {
            return false;
        }

        if ( $pattern[0] === '#' && substr( $pattern, -1 ) === '#' ) {
            return (bool) @preg_match( $pattern, $subject );
        }

        $regex = '#^' . str_replace( '\\*', '.*', preg_quote( $pattern, '#' ) ) . '#i';
        return (bool) @preg_match( $regex, $subject );
    }

    /**
     * Selects an appropriate TTL based on the current query context.
     */
    private function determine_ttl(): int
    {
        if ( is_front_page() ) {
            return (int) $this->options->get( 'ttl_front_page', 600 );
        }

        if ( is_feed() ) {
            return (int) $this->options->get( 'ttl_feed', 900 );
        }

        return (int) $this->options->get( 'ttl_default', 3600 );
    }

    /**
     * Determines whether a cached entry is still within its time-to-live window.
     */
    private function is_entry_fresh( array $entry ): bool
    {
        $ttl     = isset( $entry['ttl'] ) ? (int) $entry['ttl'] : (int) $this->options->get( 'ttl_default', 3600 );
        $created = (int) ( $entry['created'] ?? 0 );
        if ( $ttl <= 0 ) {
            return true;
        }

        return ( time() - $created ) < $ttl;
    }

    /**
     * Builds the absolute URL for the active request for tagging and logging.
     */
    private function current_url(): string
    {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';

        return $scheme . $host . $uri;
    }
}
