<?php
/**
 * Help Center view aggregates documentation, diagnostics, and support links.
 * The data is collected ahead of time in PageRenderer.
 */



use MyProCache\Options\Presets;
/**
 * @var array $page
 * @var array $tabs
 */

$presets = class_exists( Presets::class ) ? Presets::all() : array();
$preset_state = get_option( Presets::STATE_OPTION, array() );
$last_preset = $preset_state['preset'] ?? '';
$last_preset_time = isset( $preset_state['timestamp'] ) ? (int) $preset_state['timestamp'] : 0;
$last_preset_label = '';
if ( $last_preset && isset( $presets[ $last_preset ] ) ) {
    $last_preset_label = $presets[ $last_preset ]['label'];
}

$options = get_option( 'my_pro_cache_options', array() );
$cache_enabled = defined( 'WP_CACHE' ) && WP_CACHE;
$dropin_exists = file_exists( WP_CONTENT_DIR . '/advanced-cache.php' );
$cache_dir = WP_CONTENT_DIR . '/cache/my-pro-cache';
$cache_dir_ready = is_dir( $cache_dir ) && is_writable( $cache_dir );
$preload_enabled = ! empty( $options['preload_enabled'] );
$cdn_ready = ! empty( $options['cdn_enabled'] ) && ! empty( $options['cdn_host'] );
$object_cache_enabled = ! empty( $options['oc_enabled'] );
$object_cache_dropin = file_exists( WP_CONTENT_DIR . '/object-cache.php' );

$diagnostics = array(
    array(
        'label' => __( 'WP_CACHE constant enabled', 'my-pro-cache' ),
        'state' => $cache_enabled ? 'pass' : 'fail',
        'hint'  => __( 'Required for page caching. My Pro Cache can enable this from the Dashboard.', 'my-pro-cache' ),
    ),
    array(
        'label' => __( 'advanced-cache.php drop-in present', 'my-pro-cache' ),
        'state' => $dropin_exists ? 'pass' : 'fail',
        'hint'  => __( 'Needed to serve cache before WordPress loads.', 'my-pro-cache' ),
    ),
    array(
        'label' => __( 'Cache directory writable', 'my-pro-cache' ),
        'state' => $cache_dir_ready ? 'pass' : 'fail',
        'hint'  => sprintf( __( 'Path: %s', 'my-pro-cache' ), esc_html( $cache_dir ) ),
    ),
    array(
        'label' => __( 'Preload scheduler', 'my-pro-cache' ),
        'state' => $preload_enabled ? 'pass' : 'skip',
        'hint'  => $preload_enabled ? __( 'Sitemap queue will run on cron.', 'my-pro-cache' ) : __( 'Disabled. Enable a preset or turn on in Preload tab.', 'my-pro-cache' ),
    ),
    array(
        'label' => __( 'CDN rewrite configured', 'my-pro-cache' ),
        'state' => $cdn_ready ? 'pass' : 'skip',
        'hint'  => $cdn_ready ? __( 'Static assets will be rewritten to your CDN host.', 'my-pro-cache' ) : __( 'Skipped: CDN host not set.', 'my-pro-cache' ),
    ),
    array(
        'label' => __( 'Object cache active', 'my-pro-cache' ),
        'state' => ( $object_cache_enabled && $object_cache_dropin ) ? 'pass' : ( $object_cache_dropin ? 'skip' : 'skip' ),
        'hint'  => $object_cache_dropin ? __( 'Drop-in detected. Verify Redis/Memcached connectivity.', 'my-pro-cache' ) : __( 'Not required unless you enable the Object Cache module.', 'my-pro-cache' ),
    ),
);

$quick_links = array(
    array(
        'label' => __( 'Dashboard Overview', 'my-pro-cache' ),
        'description' => __( 'Live metrics, quick actions, and recent purges in one place.', 'my-pro-cache' ),
        'href' => admin_url( 'admin.php?page=my-pro-cache-dashboard' ),
    ),
    array(
        'label' => __( 'Presets', 'my-pro-cache' ),
        'description' => __( 'Apply Safe Defaults, Balanced, or Aggressive tuning in one click.', 'my-pro-cache' ),
        'href' => admin_url( 'admin.php?page=my-pro-cache-presets' ),
    ),
    array(
        'label' => __( 'System Status', 'my-pro-cache' ),
        'description' => __( 'Review diagnostics, exports, and safe mode toggles.', 'my-pro-cache' ),
        'href' => admin_url( 'admin.php?page=my-pro-cache-general' ),
    ),
);

$module_cards = array(
    array(
        'title' => __( 'Cache & TTL', 'my-pro-cache' ),
        'points' => array(
            __( 'Choose disk, Redis, or Memcached storage.', 'my-pro-cache' ),
            __( 'Set separate TTLs for default, front page, and feeds.', 'my-pro-cache' ),
            __( 'Control vary rules for device, language, and cookies.', 'my-pro-cache' ),
        ),
    ),
    array(
        'title' => __( 'Purge & Excludes', 'my-pro-cache' ),
        'points' => array(
            __( 'Auto-purge on publish, edit, comments, menus, or theme switches.', 'my-pro-cache' ),
            __( 'Manual tools for URL, tag, or full cache purges.', 'my-pro-cache' ),
            __( 'Wildcard and regex exclusions for URLs, cookies, user agents, and query args.', 'my-pro-cache' ),
        ),
    ),
    array(
        'title' => __( 'Optimize & Media', 'my-pro-cache' ),
        'points' => array(
            __( 'Minify HTML, CSS, and JavaScript with optional combining.', 'my-pro-cache' ),
            __( 'Defer or delay scripts, manage critical CSS and resource hints.', 'my-pro-cache' ),
            __( 'Lazy-load images/iframes, serve WebP/AVIF, and preconnect fonts.', 'my-pro-cache' ),
        ),
    ),
    array(
        'title' => __( 'Preload & Automation', 'my-pro-cache' ),
        'points' => array(
            __( 'Crawler warms sitemap URLs on a schedule with adjustable concurrency.', 'my-pro-cache' ),
            __( 'WP-CLI commands and REST endpoints mirror toolbar actions.', 'my-pro-cache' ),
            __( 'Heartbeat controls and Safe Mode live under Toolbox.', 'my-pro-cache' ),
        ),
    ),
    array(
        'title' => __( 'Object Cache', 'my-pro-cache' ),
        'points' => array(
            __( 'Connect to Redis or Memcached, manage persistent groups.', 'my-pro-cache' ),
            __( 'Fallback to internal cache when external services are unavailable.', 'my-pro-cache' ),
            __( 'Presets leave this disabled unless compatibility is detected.', 'my-pro-cache' ),
        ),
    ),
    array(
        'title' => __( 'CDN & Integrations', 'my-pro-cache' ),
        'points' => array(
            __( 'Rewrite assets to a custom CDN host or multiple hostnames.', 'my-pro-cache' ),
            __( 'Cloudflare integration purges cache on publish and bulk actions.', 'my-pro-cache' ),
            __( 'System snippets generator covers Apache, Nginx, and LiteSpeed.', 'my-pro-cache' ),
        ),
    ),
);

$automation = array(
    array(
        'label' => __( 'Admin Toolbar', 'my-pro-cache' ),
        'details' => __( 'Purge current page, purge all, trigger preload, or toggle debug from any admin page.', 'my-pro-cache' ),
    ),
    array(
        'label' => __( 'WP-CLI', 'my-pro-cache' ),
        'details' => __( 'Use `wp my-pro-cache purge`, `preload`, `status`, and `diagnostics` for scripting deploy pipelines.', 'my-pro-cache' ),
    ),
    array(
        'label' => __( 'REST API', 'my-pro-cache' ),
        'details' => __( 'Authenticated endpoints: POST /my-pro-cache/v1/purge, POST /my-pro-cache/v1/preload, GET /my-pro-cache/v1/status.', 'my-pro-cache' ),
    ),
);

$resources = array(
    array(
        'title' => __( 'Documentation', 'my-pro-cache' ),
        'items' => array(
            array(
                'label' => __( 'Quick Start Guide', 'my-pro-cache' ),
                'url'   => 'https://example.com/docs/quick-start',
            ),
            array(
                'label' => __( 'Module Reference', 'my-pro-cache' ),
                'url'   => 'https://example.com/docs/modules',
            ),
            array(
                'label' => __( 'Deployment Checklist', 'my-pro-cache' ),
                'url'   => 'https://example.com/docs/deployment',
            ),
        ),
    ),
    array(
        'title' => __( 'Getting Help', 'my-pro-cache' ),
        'items' => array(
            array(
                'label' => __( 'Open Support Portal', 'my-pro-cache' ),
                'url'   => 'https://example.com/support',
            ),
            array(
                'label' => __( 'Status / Incident Updates', 'my-pro-cache' ),
                'url'   => 'https://status.example.com',
            ),
        ),
    ),
);

$system_report = '';
if ( class_exists( '\\MyProCache\\Support\\SystemReport' ) ) {
    $system_report = \MyProCache\Support\SystemReport::generate();
}
?>
<!-- Help center container groups knowledge base content -->
<div class="wrap my-pro-cache-wrap mpc-dashboard-wrap">
    <header class="mpc-header">
        <div class="mpc-header__title">
            <h1> echo esc_html( $page['page_title'] ?? __( 'My Pro Cache Help Center', 'my-pro-cache' ) ); ?></h1>
             if ( $last_preset_label ) : ?>
                <span class="mpc-chip mpc-chip--muted"> echo esc_html( sprintf( __( 'Last preset: %s', 'my-pro-cache' ), $last_preset_label ) ); ?></span>
             endif; ?>
        </div>
    </header>

    <nav class="nav-tab-wrapper" aria-label=" esc_attr_e( 'My Pro Cache navigation', 'my-pro-cache' ); ?>">
         foreach ( $tabs as $tab ) : ?>
            <a class="nav-tab  echo $tab['current'] ? 'nav-tab-active' : ''; ?>" href=" echo esc_url( $tab['url'] ); ?>"> echo esc_html( $tab['label'] ); ?></a>
         endforeach; ?>
    </nav>

     if ( ! empty( $page['description'] ) ) : ?>
        <p class="mpc-page-summary"> echo esc_html( $page['description'] ); ?></p>
     endif; ?>

    <!-- Quick start steps and high-level navigation links -->
    <section class="mpc-help-section" aria-labelledby="mpc-help-quick-start">
        <h2 id="mpc-help-quick-start"> esc_html_e( 'Quick Start Checklist', 'my-pro-cache' ); ?></h2>
        <ol class="mpc-help-steps">
            <li> esc_html_e( 'Activate the plugin and visit the Dashboard tab.', 'my-pro-cache' ); ?></li>
            <li> esc_html_e( 'Click “Enable Cache” to add the drop-in and define WP_CACHE.', 'my-pro-cache' ); ?></li>
            <li> esc_html_e( 'Choose a preset (Safe, Balanced, Aggressive) or configure modules manually.', 'my-pro-cache' ); ?></li>
            <li> esc_html_e( 'Review exclusions and purge behaviour to avoid caching sensitive routes.', 'my-pro-cache' ); ?></li>
            <li> esc_html_e( 'Enable preload and object cache only after verifying compatibility.', 'my-pro-cache' ); ?></li>
        </ol>

        <div class="mpc-help-quick-links">
             foreach ( $quick_links as $link ) : ?>
                <a class="mpc-help-quick-link" href=" echo esc_url( $link['href'] ); ?>">
                    <span class="mpc-help-quick-link__label"> echo esc_html( $link['label'] ); ?></span>
                    <span class="mpc-help-quick-link__description"> echo esc_html( $link['description'] ); ?></span>
                </a>
             endforeach; ?>
        </div>
    </section>

     if ( ! empty( $presets ) ) : ?>
    <section class="mpc-help-section" aria-labelledby="mpc-help-presets">
        <h2 id="mpc-help-presets"> esc_html_e( 'Preset Profiles', 'my-pro-cache' ); ?></h2>
        <div class="mpc-preset-grid">
             foreach ( $presets as $preset ) :
                $risk = $preset['risk'] ?? 'low';
                $risk_map = array(
                    'low'    => __( 'Low risk', 'my-pro-cache' ),
                    'medium' => __( 'Medium risk', 'my-pro-cache' ),
                    'high'   => __( 'High risk', 'my-pro-cache' ),
                );
                $risk_label = $risk_map[ $risk ] ?? __( 'Risk unknown', 'my-pro-cache' );
            ?>
            <article class="mpc-preset-card" role="group" aria-label=" echo esc_attr( $preset['label'] ); ?>">
                <header class="mpc-preset-card__header">
                    <h3 class="mpc-preset-card__title"> echo esc_html( $preset['label'] ); ?></h3>
                    <span class="mpc-risk-badge mpc-risk-- echo esc_attr( $risk ); ?>"> echo esc_html( $risk_label ); ?></span>
                </header>
                <p class="mpc-preset-card__description"> echo esc_html( $preset['description'] ); ?></p>
                 if ( ! empty( $preset['highlights'] ) ) : ?>
                    <ul class="mpc-preset-highlights">
                         foreach ( array_slice( $preset['highlights'], 0, 3 ) as $highlight ) : ?>
                            <li> echo esc_html( $highlight ); ?></li>
                         endforeach; ?>
                    </ul>
                 endif; ?>
            </article>
             endforeach; ?>
        </div>
    </section>
     endif; ?>

    <section class="mpc-help-section" aria-labelledby="mpc-help-modules">
        <h2 id="mpc-help-modules"> esc_html_e( 'Module Cheat Sheet', 'my-pro-cache' ); ?></h2>
        <div class="mpc-help-grid">
             foreach ( $module_cards as $card ) : ?>
                <div class="mpc-help-card">
                    <h3> echo esc_html( $card['title'] ); ?></h3>
                    <ul>
                         foreach ( $card['points'] as $point ) : ?>
                            <li> echo esc_html( $point ); ?></li>
                         endforeach; ?>
                    </ul>
                </div>
             endforeach; ?>
        </div>
    </section>

    <!-- Highlight environment checks admins should review -->
    <section class="mpc-help-section" aria-labelledby="mpc-help-diagnostics">
        <h2 id="mpc-help-diagnostics"> esc_html_e( 'Health & Diagnostics', 'my-pro-cache' ); ?></h2>
        <ul class="mpc-checklist">
             foreach ( $diagnostics as $status ) :
                $state = $status['state'];
                $state_class = 'mpc-checklist__item--' . $state;
            ?>
            <li class="mpc-checklist__item  echo esc_attr( $state_class ); ?>">
                <span class="mpc-checklist__title"> echo esc_html( $status['label'] ); ?></span>
                 if ( ! empty( $status['hint'] ) ) : ?>
                    <span class="mpc-checklist__hint"> echo esc_html( $status['hint'] ); ?></span>
                 endif; ?>
            </li>
             endforeach; ?>
        </ul>
    </section>

    <section class="mpc-help-section" aria-labelledby="mpc-help-automation">
        <h2 id="mpc-help-automation"> esc_html_e( 'Automation & APIs', 'my-pro-cache' ); ?></h2>
        <ul class="mpc-help-list">
             foreach ( $automation as $item ) : ?>
                <li>
                    <strong> echo esc_html( $item['label'] ); ?>:</strong>
                    <span> echo esc_html( $item['details'] ); ?></span>
                </li>
             endforeach; ?>
        </ul>
    </section>

    <!-- Documentation and support quick links -->
    <section class="mpc-help-section" aria-labelledby="mpc-help-resources">
        <h2 id="mpc-help-resources"> esc_html_e( 'Resources & Support', 'my-pro-cache' ); ?></h2>
        <div class="mpc-help-resources">
             foreach ( $resources as $group ) : ?>
                <div class="mpc-help-card">
                    <h3> echo esc_html( $group['title'] ); ?></h3>
                    <ul>
                         foreach ( $group['items'] as $item ) : ?>
                            <li><a href=" echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer"> echo esc_html( $item['label'] ); ?></a></li>
                         endforeach; ?>
                    </ul>
                </div>
             endforeach; ?>
            <div class="mpc-help-card">
                <h3> esc_html_e( 'System Report', 'my-pro-cache' ); ?></h3>
                <p> esc_html_e( 'Copy or download this summary when contacting support.', 'my-pro-cache' ); ?></p>
                <textarea readonly rows="10" class="mpc-help-report"> echo esc_textarea( $system_report ); ?></textarea>
                <form method="post" action=" echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mpc-help-actions">
                    <input type="hidden" name="action" value="my_pro_cache_help" />
                    <input type="hidden" name="mpc_help_action" value="download_report" />
                     wp_nonce_field( 'my_pro_cache_help_action', '_mpc_help_nonce' ); ?>
                    <button type="submit" class="button">
                         esc_html_e( 'Download Report', 'my-pro-cache' ); ?>
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- Common troubleshooting tips for site owners -->
    <section class="mpc-help-section" aria-labelledby="mpc-help-troubleshooting">
        <h2 id="mpc-help-troubleshooting"> esc_html_e( 'Troubleshooting Tips', 'my-pro-cache' ); ?></h2>
        <ul class="mpc-help-list">
            <li> esc_html_e( 'Pages not caching? Check exclusions and confirm WP_CACHE and advanced-cache.php are active.', 'my-pro-cache' ); ?></li>
            <li> esc_html_e( 'Stale content after deploy? Purge All then run Preload All to rebuild.', 'my-pro-cache' ); ?></li>
            <li> esc_html_e( 'Conflict suspected? Enable Safe Mode from Toolbox to temporarily disable Optimise, Media, and CDN modules.', 'my-pro-cache' ); ?></li>
            <li> esc_html_e( 'Debug headers/logs can be toggled on the Dashboard. Review wp-content/cache/my-pro-cache/logs/debug.log for decisions.', 'my-pro-cache' ); ?></li>
        </ul>
    </section>
</div>
