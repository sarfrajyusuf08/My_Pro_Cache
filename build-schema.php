<?php
$file = __DIR__ . '/includes/Options/Schema.php';
$content = <<'PHP'
<?php

namespace MyProCache\Options;

use function __;
use function sprintf;
use function strip_tags;

class Schema
{
    public static function pages(): array
    {
        $pages = self::definitions();

        return self::ensure_tooltips( $pages );
    }

    public static function field_definitions(): array
    {
        $fields = array();

        foreach ( self::pages() as $page ) {
            if ( empty( $page['sections'] ) ) {
                continue;
            }

            foreach ( $page['sections'] as $section ) {
                if ( empty( $section['fields'] ) ) {
                    continue;
                }

                foreach ( $section['fields'] as $key => $field ) {
                    $fields[ $key ] = $field;
                }
            }
        }

        return $fields;
    }

    private static function definitions(): array
    {
        return array(
            'dashboard' => array(
                'menu_title'  => __( 'Dashboard', 'my-pro-cache' ),
                'page_title'  => __( 'My Pro Cache — Dashboard', 'my-pro-cache' ),
                'description' => __( 'Overview of cache health, quick actions, and recent activity.', 'my-pro-cache' ),
                'icon'        => 'dashicons-dashboard',
                'show_save'   => false,
                'sections'    => array(),
            ),
            'cache' => array(
                'menu_title'  => __( 'Cache', 'my-pro-cache' ),
                'page_title'  => __( 'Full Page Cache', 'my-pro-cache' ),
                'description' => __( 'Configure the primary cache behaviour and storage backend.', 'my-pro-cache' ),
                'sections'    => array(
                    array(
                        'title'       => __( 'Storage Backend', 'my-pro-cache' ),
                        'description' => __( 'Select where cached pages are stored and how logged-in visitors are handled.', 'my-pro-cache' ),
                        'fields'      => array(
                            'cache_backend' => self::field(
                                'select',
                                __( 'Backend', 'my-pro-cache' ),
                                __( 'Choose the storage engine for cached pages. Disk is simplest; Redis or Memcached suit clustered setups.', 'my-pro-cache' ),
                                array(
                                    'options' => array(
                                        'disk'      => __( 'Disk (wp-content/cache)', 'my-pro-cache' ),
                                        'redis'     => __( 'Redis', 'my-pro-cache' ),
                                        'memcached' => __( 'Memcached', 'my-pro-cache' ),
                                    ),
                                )
                            ),
                            'cache_logged_in_mode' => self::field(
                                'select',
                                __( 'Logged-in Users', 'my-pro-cache' ),
                                __( 'Decide whether authenticated sessions should bypass caching or receive user-specific private entries.', 'my-pro-cache' ),
                                array(
                                    'options' => array(
                                        'bypass'  => __( 'Bypass cache', 'my-pro-cache' ),
                                        'private' => __( 'Serve private cache', 'my-pro-cache' ),
                                    ),
                                )
                            ),
                            'cache_rest_api' => self::field(
                                'checkbox',
                                __( 'Cache REST API Responses', 'my-pro-cache' ),
                                __( 'Store GET responses from the REST API for faster headless/front-end consumption.', 'my-pro-cache' )
                            ),
                        ),
                    ),
                    array(
                        'title'       => __( 'Cache Variation (Vary)', 'my-pro-cache' ),
                        'description' => __( 'Control how cache items vary across devices, roles, languages, and cookies.', 'my-pro-cache' ),
                        'fields'      => array(
                            'cache_vary_device' => self::field(
                                'checkbox',
                                __( 'Vary by Device', 'my-pro-cache' ),
                                __( 'Maintain discrete cache entries for mobile and desktop visitors.', 'my-pro-cache' )
                            ),
                            'cache_vary_role' => self::field(
                                'checkbox',
                                __( 'Vary by User Role', 'my-pro-cache' ),
                                __( 'Create separate cache entries based on the visitor’s WordPress role.', 'my-pro-cache' )
                            ),
                            'cache_vary_lang' => self::field(
                                'checkbox',
                                __( 'Vary by Language', 'my-pro-cache' ),
                                __( 'Split cache entries per active language on multilingual sites.', 'my-pro-cache' )
                            ),
                            'cache_vary_cookie_allowlist' => self::field(
                                'textarea',
                                __( 'Cookie Allowlist', 'my-pro-cache' ),
                                __( 'Only the listed cookies influence the vary hash. Enter one cookie name per line.', 'my-pro-cache' ),
                                array( 'placeholder' => "wp-wpml_current_language\nmy_cookie" )
                            ),
                        ),
                    ),
                ),
            ),
            'ttl' => array(
                'menu_title'  => __( 'TTL', 'my-pro-cache' ),
                'page_title'  => __( 'Cache TTL', 'my-pro-cache' ),
                'description' => __( 'Control how long cached content stays fresh before regeneration.', 'my-pro-cache' ),
                'sections'    => array(
                    array(
                        'title'       => __( 'Time To Live', 'my-pro-cache' ),
                        'description' => __( 'Define cache lifetimes for different contexts and stale policies.', 'my-pro-cache' ),
                        'fields'      => array(
                            'ttl_default' => self::field(
                                'number',
                                __( 'Default TTL (seconds)', 'my-pro-cache' ),
                                __( 'Base lifetime applied to cached pages when no more specific rule matches.', 'my-pro-cache' ),
                                array( 'min' => 60, 'step' => 60 )
                            ),
                            'ttl_front_page' => self::field(
                                'number',
                                __( 'Front Page TTL (seconds)', 'my-pro-cache' ),
                                __( 'Shorter TTL keeps the home page refreshed more often.', 'my-pro-cache' ),
                                array( 'min' => 60, 'step' => 60 )
                            ),
                            'ttl_feed' => self::field(
                                'number',
                                __( 'Feed TTL (seconds)', 'my-pro-cache' ),
                                __( 'Lifetime for RSS, Atom, and JSON feeds.', 'my-pro-cache' ),
                                array( 'min' => 60, 'step' => 60 )
                            ),
                            'stale_while_revalidate' => self::field(
                                'number',
                                __( 'Stale-While-Revalidate (seconds)', 'my-pro-cache' ),
                                __( 'Serve a slightly stale page while a fresh copy regenerates asynchronously.', 'my-pro-cache' ),
                                array( 'min' => 0 )
                            ),
                            'stale_if_error' => self::field(
                                'number',
                                __( 'Stale-If-Error (seconds)', 'my-pro-cache' ),
                                __( 'Continue serving cached content if the origin responds with errors.', 'my-pro-cache' ),
                                array( 'min' => 0 )
                            ),
                        ),
                    ),
                ),
            ),
            'purge' => array(
                'menu_title'  => __( 'Purge', 'my-pro-cache' ),
                'page_title'  => __( 'Cache Purge Rules', 'my-pro-cache' ),
                'description' => __( 'Control automatic and manual cache purges.', 'my-pro-cache' ),
                'sections'    => array(
                    array(
                        'title'       => __( 'Automatic Purge Triggers', 'my-pro-cache' ),
                        'description' => __( 'Clear related cache entries when content updates occur.', 'my-pro-cache' ),
                        'fields'      => array(
                            'purge_on_update' => self::field(
                                'checkbox',
                                __( 'Post/Page Updates', 'my-pro-cache' ),
                                __( 'Purge caches when posts, terms, menus, or attachments are updated.', 'my-pro-cache' )
                            ),
                            'purge_on_comment' => self::field(
                                'checkbox',
                                __( 'New Comments', 'my-pro-cache' ),
                                __( 'Invalidate cached pages when new comments are approved.', 'my-pro-cache' )
                            ),
                            'purge_ccu_cdn' => self::field(
                                'checkbox',
                                __( 'Purge Connected CDN', 'my-pro-cache' ),
                                __( 'Issue a CDN cache purge whenever the local cache is cleared.', 'my-pro-cache' )
                            ),
                        ),
                    ),
                    array(
                        'title'       => __( 'Scheduled Purge', 'my-pro-cache' ),
                        'description' => __( 'Define recurring purge patterns handled by WP-Cron.', 'my-pro-cache' ),
                        'fields'      => array(
                            'purge_schedule_enabled' => self::field(
                                'checkbox',
                                __( 'Enable Scheduled Purge', 'my-pro-cache' ),
                                __( 'Turn on recurring purge jobs for the patterns listed below.', 'my-pro-cache' )
                            ),
                            'purge_schedule_patterns' => self::field(
                                'textarea',
                                __( 'URL Patterns', 'my-pro-cache' ),
                                __( 'Enter one URL or wildcard per line to be purged on schedule.', 'my-pro-cache' ),
                                array( 'placeholder' => "/promo/*\n/landing-page" )
                            ),
                        ),
                    ),
                ),
            ),
            'excludes' => array(
                'menu_title'  => __( 'Excludes', 'my-pro-cache' ),
                'page_title'  => __( 'Cache Exclusions', 'my-pro-cache' ),
                'description' => __( 'Exclude specific requests from caching.', 'my-pro-cache' ),
                'sections'    => array(
                    array(
                        'title'       => __( 'Exclude Rules', 'my-pro-cache' ),
                        'description' => __( 'Provide wildcards or regular expressions to bypass caching.', 'my-pro-cache' ),
                        'fields'      => array(
                            'exclude_urls' => self::field(
                                'textarea',
                                __( 'URL Patterns', 'my-pro-cache' ),
                                __( 'Paths or regex patterns that should never be cached (one per line).', 'my-pro-cache' ),
                                array( 'placeholder' => "/checkout\n#/preview#" )
                            ),
                            'exclude_cookies' => self::field(
                                'textarea',
                                __( 'Cookie Names', 'my-pro-cache' ),
                                __( 'If a visitor carries any of these cookies, the request bypasses cache.', 'my-pro-cache' )
                            ),
                            'exclude_user_agents' => self::field(
                                'textarea',
                                __( 'User Agents', 'my-pro-cache' ),
                                __( 'List bots or browsers that should skip cache (supports regex).', 'my-pro-cache' )
                            ),
                            'exclude_query_args' => self::field(
                                'textarea',
                                __( 'Query Arguments', 'my-pro-cache' ),
                                __( 'Query-string keys that force bypass when present.', 'my-pro-cache' )
                            ),
                        ),
                    ),
                ),
            ),
            'optimize' => array(
                'menu_title'  => __( 'Optimize', 'my-pro-cache' ),
                'page_title'  => __( 'CSS/JS/HTML Optimization', 'my-pro-cache' ),
                'description' => __( 'Minify, combine, and control asset delivery.', 'my-pro-cache' ),
                'sections'    => array(
                    array(
                        'title'       => __( 'Minification', 'my-pro-cache' ),
                        'description' => __( 'Reduce asset size by removing comments and whitespace.', 'my-pro-cache' ),
                        'fields'      => array(
                            'min_html' => self::field(
                                'checkbox',
                                __( 'Minify HTML', 'my-pro-cache' ),
                                __( 'Compress the generated HTML before caching to reduce payload size.', 'my-pro-cache' )
                            ),
                            'min_css' => self::field(
                                'checkbox',
                                __( 'Minify CSS', 'my-pro-cache' ),
                                __( 'Strip comments and whitespace from enqueued stylesheets.', 'my-pro-cache' )
                            ),
                            'min_js' => self::field(
                                'checkbox',
                                __( 'Minify JavaScript', 'my-pro-cache' ),
                                __( 'Minify enqueued scripts to decrease download size.', 'my-pro-cache' )
                            ),
                        ),
                    ),
                    array(
                        'title'       => __( 'Combination & Delivery', 'my-pro-cache' ),
                        'description' => __( 'Merge assets and adjust loading strategy for better performance.', 'my-pro-cache' ),
                        'fields'      => array(
                            'combine_css' => self::field(
                                'checkbox',
                                __( 'Combine CSS', 'my-pro-cache' ),
                                __( 'Concatenate multiple CSS files into a single bundle when safe.', 'my-pro-cache' )
                            ),
                            'combine_js' => self::field(
                                'checkbox',
                                __( 'Combine JavaScript', 'my-pro-cache' ),
                                __( 'Merge compatible scripts into fewer requests.', 'my-pro-cache' )
                            ),
                            'exclude_css_handles' => self::field(
                                'textarea',
                                __( 'Exclude CSS Handles', 'my-pro-cache' ),
                                __( 'List style handles that should never be minified or combined (one per line).', 'my-pro-cache' )
                            ),
                            'exclude_js_handles' => self::field(
                                'textarea',
                                __( 'Exclude Script Handles', 'my-pro-cache' ),
                                __( 'List script handles to exclude from minify/combine operations.', 'my-pro-cache' )
                            ),
                            'critical_css_auto' => self::field(
                                'checkbox',
                                __( 'Generate Critical CSS', 'my-pro-cache' ),
                                __( 'Automatically extract above-the-fold CSS via the Pro service.', 'my-pro-cache' )
                            ),
                            'critical_css_per_post_type' => self::field(
                                'checkbox',
                                __( 'Critical CSS per Post Type', 'my-pro-cache' ),
                                __( 'Maintain separate critical CSS per post type or template.', 'my-pro-cache' )
                            ),
                            'css_async' => self::field(
                                'checkbox',
                                __( 'Load CSS Asynchronously', 'my-pro-cache' ),
                                __( 'Convert render-blocking stylesheets into async loads with preload fallback.', 'my-pro-cache' )
                            ),
                            'js_defer' => self::field(
                                'checkbox',
                                __( 'Defer JavaScript', 'my-pro-cache' ),
                                __( 'Add the defer attribute to compatible scripts so parsing waits for HTML.', 'my-pro-cache' )
                            ),
                            'js_delay_until_interaction' => self::field(
                                'checkbox',
                                __( 'Delay JS Until Interaction', 'my-pro-cache' ),
                                __( 'Hold selected scripts until the user interacts (scroll/click) to improve initial paint.', 'my-pro-cache' )
                            ),
                            'js_delay_allowlist' => self::field(
                                'textarea',
                                __( 'Delay Allowlist', 'my-pro-cache' ),
                                __( 'Handles or patterns that must run immediately even when delaying JavaScript.', 'my-pro-cache' )
                            ),
                            'preload_keys' => self::field(
                                'textarea',
                                __( 'Preload Resources', 'my-pro-cache' ),
                                __( 'Absolute URLs to preload (fonts, hero images, critical scripts). One per line.', 'my-pro-cache' )
                            ),
                            'dns_prefetch' => self::field(
                                'textarea',
                                __( 'DNS Prefetch', 'my-pro-cache' ),
                                __( 'Domains to resolve early to reduce connection latency.', 'my-pro-cache' )
                            ),
                            'preconnect' => self::field(
                                'textarea',
                                __( 'Preconnect', 'my-pro-cache' ),
                                __( 'Domains to preconnect (DNS + TCP + TLS) for priority assets.', 'my-pro-cache' )
                            ),
                        ),
                    ),
                ),
            ),
            'media' => array(
                'menu_title'  => __( 'Media', 'my-pro-cache' ),
                'page_title'  => __( 'Media Optimisation', 'my-pro-cache' ),
                'description' => __( 'Optimise images, iframes, and media delivery.', 'my-pro-cache' ),
                'sections'    => array(
                    array(
                        'title'       => __( 'Lazy Loading', 'my-pro-cache' ),
                        'description' => __( 'Defer off-screen media until the visitor is about to view it.', 'my-pro-cache' ),
                        'fields'      => array(
                            'lazyload_images' => self::field(
                                'checkbox',
                                __( 'Lazy Load Images', 'my-pro-cache' ),
                                __( 'Apply loading="lazy" plus optional placeholders to images.', 'my-pro-cache' )
                            ),
                            'lazyload_iframes' => self::field(
                                'checkbox',
                                __( 'Lazy Load Iframes', 'my-pro-cache' ),
                                __( 'Delay iframe loading (video embeds, maps) until scrolled into view.', 'my-pro-cache' )
                            ),
                            'lqip_placeholders' => self::field(
                                'checkbox',
                                __( 'LQIP Placeholders', 'my-pro-cache' ),
                                __( 'Display low-quality placeholders to soften perceived loading.', 'my-pro-cache' )
                            ),
                        ),
                    ),
                    array(
                        'title'       => __( 'Next-Gen Formats', 'my-pro-cache' ),
                        'description' => __( 'Serve lighter formats with automatic fallback.', 'my-pro-cache' ),
                        'fields'      => array(
                            'convert_webp' => self::field(
                                'checkbox',
                                __( 'Generate WebP', 'my-pro-cache' ),
                                __( 'Create WebP versions of new uploads during crunching.', 'my-pro-cache' )
                            ),
                            'convert_avif' => self::field(
                                'checkbox',
                                __( 'Generate AVIF', 'my-pro-cache' ),
                                __( 'Generate AVIF variants for even smaller images where supported.', 'my-pro-cache' )
                            ),
                            'webp_avif_rewrite' => self::field(
                                'checkbox',
                                __( 'Rewrite to WebP/AVIF', 'my-pro-cache' ),
                                __( 'Serve the modern image if it exists, falling back to original when unavailable.', 'my-pro-cache' )
                            ),
                            'media_excludes' => self::field(
                                'textarea',
                                __( 'Exclude Selectors/URLs', 'my-pro-cache' ),
                                __( 'CSS selectors or image URLs to skip from lazy loading and rewrites.', 'my-pro-cache' )
                            ),
                        ),
                    ),
                ),
            ),
            ...
PHP;
file_put_contents($file, $content);
PHP;
file_put_contents(__DIR__ . '/build-schema-generated.log', 'done');
