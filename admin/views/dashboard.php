<?php
/**
 * Dashboard view renders quick actions, cache metrics, and the debug log.
 * Context arrays are supplied by PageRenderer.
 */

/**
 * @var array                        $page
 * @var array                        $tabs
 * @var MyProCache\Debug\Logger     $logger
 * @var string                       $cache_status
 * @var float                        $hit_miss_ratio
 * @var string                       $cache_size_human
 * @var int                          $warm_queue_count
 * @var string                       $object_cache_engine
 * @var array<int, array<string,mixed>> $recent_purges
 * @var bool                         $debug_enabled
 * @var array<int,string>            $recent_debug_log
 */

$cache_status       = $cache_status ?? 'disabled';
$hit_miss_ratio     = isset( $hit_miss_ratio ) ? (float) $hit_miss_ratio : 0.0;
$cache_size_human   = $cache_size_human ?? __( 'Empty', 'my-pro-cache' );
$warm_queue_count   = isset( $warm_queue_count ) ? (int) $warm_queue_count : 0;
$object_cache_engine = $object_cache_engine ?? __( 'Internal', 'my-pro-cache' );
$recent_purges      = is_array( $recent_purges ?? null ) ? $recent_purges : array();
$debug_enabled      = (bool) ( $debug_enabled ?? false );
$recent_debug_log   = is_array( $recent_debug_log ?? null ) ? $recent_debug_log : array();

$status_map = array(
    'enabled'   => array(
        'label' => __( 'Enabled', 'my-pro-cache' ),
        'class' => 'mpc-chip--enabled',
    ),
    'disabled'  => array(
        'label' => __( 'Disabled', 'my-pro-cache' ),
        'class' => 'mpc-chip--disabled',
    ),
    'attention' => array(
        'label' => __( 'Attention Required', 'my-pro-cache' ),
        'class' => 'mpc-chip--attention',
    ),
);

$status_config = $status_map[ $cache_status ] ?? $status_map['attention'];
$status_text   = $status_config['label'];
$status_class  = 'mpc-chip ' . $status_config['class'];

$ratio_clamped = max( 0, min( 100, $hit_miss_ratio ) );

$log_count          = count( $recent_debug_log );
$last_entry_summary = __( 'No debug logs yet. Toggle Debug to start collecting.', 'my-pro-cache' );
$last_entry_time    = '';

if ( $log_count > 0 ) {
    $last_line = $recent_debug_log[ $log_count - 1 ];
    $timestamp = 0;

    if ( preg_match( '/\[(\d{4}-\d{2}-\d{2}T[^\]]+)\]/', $last_line, $matches ) ) {
        $maybe_time = strtotime( $matches[1] );
        if ( $maybe_time ) {
            $timestamp = $maybe_time;
        }
    }

    if ( $timestamp ) {
        $human = human_time_diff( $timestamp, current_time( 'timestamp' ) );
        $last_entry_time = sprintf( __( '%s ago', 'my-pro-cache' ), $human );
    }

    $last_entry_summary = sprintf(
        _n( 'Last entry %1$s · %2$d log line', 'Last entry %1$s · %2$d log lines', $log_count, 'my-pro-cache' ),
        $last_entry_time ? esc_html( $last_entry_time ) : esc_html__( 'time unknown', 'my-pro-cache' ),
        $log_count
    );
}

$toolbar_redirect = esc_url( admin_url( 'admin.php?page=my-pro-cache-dashboard' ) );
$admin_post_url   = esc_url( admin_url( 'admin-post.php' ) );

$debug_button_text  = $debug_enabled ? __( 'Debug On', 'my-pro-cache' ) : __( 'Debug Off', 'my-pro-cache' );
$debug_button_label = $debug_enabled ? __( 'Toggle debug mode off', 'my-pro-cache' ) : __( 'Toggle debug mode on', 'my-pro-cache' );
?>
<!-- Dashboard container holds header, actions, and metric cards -->
<div class="wrap my-pro-cache-wrap mpc-dashboard-wrap">
    <!-- Page masthead with status chips -->
    <header class="mpc-header">
        <div class="mpc-header__title">
            <h1><?php echo esc_html__( 'My Pro Cache — Dashboard', 'my-pro-cache' ); ?></h1>
            <span class="<?php echo esc_attr( $status_class ); ?>" aria-live="polite"><?php echo esc_html( $status_text ); ?></span>
            <?php if ( $debug_enabled ) : ?>
                <span class="mpc-chip mpc-chip--muted"><?php esc_html_e( 'Debug On', 'my-pro-cache' ); ?></span>
            <?php endif; ?>
        </div>
    </header>

    <?php if ( ! empty( $_GET['my_pro_cache_notice'] ) ) :
        $notice_type    = isset( $_GET['my_pro_cache_notice_type'] ) ? sanitize_key( wp_unslash( $_GET['my_pro_cache_notice_type'] ) ) : 'success';
        $allowed_types  = array( 'success', 'error', 'warning', 'info' );
        $notice_type    = in_array( $notice_type, $allowed_types, true ) ? $notice_type : 'success';
        $notice_classes = 'notice notice-' . $notice_type . ' is-dismissible';
        $notice_message = esc_html( wp_unslash( $_GET['my_pro_cache_notice'] ) );
    ?>
        <div class="<?php echo esc_attr( $notice_classes ); ?>"><p><?php echo $notice_message; ?></p></div>
    <?php endif; ?>

    <?php if ( $cache_status !== 'enabled' ) : ?>
        <div class="notice notice-warning">
            <p><?php esc_html_e( 'Caching is currently disabled. Enable the cache below to serve pages from disk.', 'my-pro-cache' ); ?></p>
        </div>
    <?php endif; ?>

    <nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'My Pro Cache navigation', 'my-pro-cache' ); ?>">
        <?php foreach ( $tabs as $tab ) : ?>
            <a class="nav-tab <?php echo $tab['current'] ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $tab['url'] ); ?>"><?php echo esc_html( $tab['label'] ); ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ( ! empty( $page['description'] ) ) : ?>
        <p class="mpc-page-summary"><?php echo esc_html( $page['description'] ); ?></p>
    <?php endif; ?>

    <div class="mpc-toolbar" role="toolbar" aria-label="<?php esc_attr_e( 'Cache maintenance actions', 'my-pro-cache' ); ?>">
        <form method="post" action="<?php echo $admin_post_url; ?>">
            <?php wp_nonce_field( 'my_pro_cache_admin_action', '_mpc_nonce' ); ?>
            <input type="hidden" name="action" value="my_pro_cache_action" />
            <input type="hidden" name="mpc_action" value="purge_all" />
            <input type="hidden" name="_mpc_redirect" value="<?php echo $toolbar_redirect; ?>" />
            <button type="submit" class="button button-primary" aria-label="<?php esc_attr_e( 'Purge the entire cache', 'my-pro-cache' ); ?>"><?php esc_html_e( 'Purge All', 'my-pro-cache' ); ?></button>
        </form>
        <form method="post" action="<?php echo $admin_post_url; ?>">
            <?php wp_nonce_field( 'my_pro_cache_admin_action', '_mpc_nonce' ); ?>
            <input type="hidden" name="action" value="my_pro_cache_action" />
            <input type="hidden" name="mpc_action" value="purge_front" />
            <input type="hidden" name="_mpc_redirect" value="<?php echo $toolbar_redirect; ?>" />
            <button type="submit" class="button" aria-label="<?php esc_attr_e( 'Purge the front page cache', 'my-pro-cache' ); ?>"><?php esc_html_e( 'Purge Front Page', 'my-pro-cache' ); ?></button>
        </form>
        <form method="post" action="<?php echo $admin_post_url; ?>">
            <?php wp_nonce_field( 'my_pro_cache_admin_action', '_mpc_nonce' ); ?>
            <input type="hidden" name="action" value="my_pro_cache_action" />
            <input type="hidden" name="mpc_action" value="preload_all" />
            <input type="hidden" name="_mpc_redirect" value="<?php echo $toolbar_redirect; ?>" />
            <button type="submit" class="button" aria-label="<?php esc_attr_e( 'Preload all cached content', 'my-pro-cache' ); ?>"><?php esc_html_e( 'Preload All', 'my-pro-cache' ); ?></button>
        </form>
        <form method="post" action="<?php echo $admin_post_url; ?>">
            <?php wp_nonce_field( 'my_pro_cache_admin_action', '_mpc_nonce' ); ?>
            <input type="hidden" name="action" value="my_pro_cache_action" />
            <input type="hidden" name="mpc_action" value="toggle_debug" />
            <input type="hidden" name="_mpc_redirect" value="<?php echo $toolbar_redirect; ?>" />
            <button type="submit" class="button" aria-label="<?php echo esc_attr( $debug_button_label ); ?>" aria-pressed="<?php echo $debug_enabled ? 'true' : 'false'; ?>"><?php echo esc_html( $debug_button_text ); ?></button>
        </form>
    </div>

    <section class="mpc-grid" aria-label="<?php esc_attr_e( 'Cache status overview', 'my-pro-cache' ); ?>">
        <article class="mpc-card" role="group" aria-labelledby="mpc-card-status">
            <div class="mpc-card__header">
                <h2 id="mpc-card-status" class="mpc-card__title"><?php esc_html_e( 'Cache Status', 'my-pro-cache' ); ?></h2>
            </div>
            <p class="mpc-card__value"><?php echo esc_html( $status_text ); ?></p>
            <p class="mpc-card__meta"><?php esc_html_e( 'WP_CACHE constant and advanced-cache.php drop-in', 'my-pro-cache' ); ?></p>
            <?php if ( 'enabled' !== $cache_status ) : ?>
                <form method="post" action="<?php echo $admin_post_url; ?>" class="mpc-card__cta">
                    <?php wp_nonce_field( 'my_pro_cache_admin_action', '_mpc_nonce' ); ?>
                    <input type="hidden" name="action" value="my_pro_cache_action" />
                    <input type="hidden" name="mpc_action" value="enable_cache" />
                    <input type="hidden" name="_mpc_redirect" value="<?php echo $toolbar_redirect; ?>" />
                    <button type="submit" class="button button-secondary" aria-label="<?php esc_attr_e( 'Enable file-based page cache', 'my-pro-cache' ); ?>"><?php esc_html_e( 'Enable Cache', 'my-pro-cache' ); ?></button>
                </form>
            <?php endif; ?>
        </article>

        <article class="mpc-card" role="group" aria-labelledby="mpc-card-ratio">
            <div class="mpc-card__header">
                <h2 id="mpc-card-ratio" class="mpc-card__title"><?php esc_html_e( 'Hit/Miss Ratio', 'my-pro-cache' ); ?></h2>
                <span class="mpc-card__meta"><?php echo esc_html( number_format_i18n( $ratio_clamped, 2 ) ); ?>%</span>
            </div>
            <div class="mpc-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( number_format_i18n( $ratio_clamped, 2 ) ); ?>" aria-label="<?php esc_attr_e( 'Cache hit rate', 'my-pro-cache' ); ?>">
                <span class="mpc-progress__bar" style="width: <?php echo esc_attr( $ratio_clamped ); ?>%"></span>
            </div>
            <p class="mpc-card__meta"><?php esc_html_e( 'Percentage of requests served from cache.', 'my-pro-cache' ); ?></p>
        </article>

        <article class="mpc-card" role="group" aria-labelledby="mpc-card-size">
            <div class="mpc-card__header">
                <h2 id="mpc-card-size" class="mpc-card__title"><?php esc_html_e( 'Cache Size', 'my-pro-cache' ); ?></h2>
            </div>
            <p class="mpc-card__value"><?php echo esc_html( $cache_size_human ); ?></p>
            <p class="mpc-card__meta"><?php esc_html_e( 'Stored HTML under wp-content/cache/my-pro-cache.', 'my-pro-cache' ); ?></p>
        </article>

        <article class="mpc-card" role="group" aria-labelledby="mpc-card-warm">
            <div class="mpc-card__header">
                <h2 id="mpc-card-warm" class="mpc-card__title"><?php esc_html_e( 'Warm Queue', 'my-pro-cache' ); ?></h2>
            </div>
            <p class="mpc-card__value"><?php echo esc_html( number_format_i18n( $warm_queue_count ) ); ?></p>
            <p class="mpc-card__meta"><?php esc_html_e( 'URLs waiting to be preloaded.', 'my-pro-cache' ); ?></p>
        </article>

        <article class="mpc-card" role="group" aria-labelledby="mpc-card-object">
            <div class="mpc-card__header">
                <h2 id="mpc-card-object" class="mpc-card__title"><?php esc_html_e( 'Object Cache', 'my-pro-cache' ); ?></h2>
            </div>
            <p class="mpc-card__value"><?php echo esc_html( $object_cache_engine ); ?></p>
            <p class="mpc-card__meta"><?php esc_html_e( 'Active object cache backend.', 'my-pro-cache' ); ?></p>
        </article>

        <article class="mpc-card mpc-card--purges" role="group" aria-labelledby="mpc-card-purges">
            <div class="mpc-card__header">
                <h2 id="mpc-card-purges" class="mpc-card__title"><?php esc_html_e( 'Recent Purges', 'my-pro-cache' ); ?></h2>
            </div>
            <div class="mpc-card__body">
                <?php if ( ! empty( $recent_purges ) ) : ?>
                    <ul class="mpc-purge-list" aria-live="polite">
                        <?php foreach ( $recent_purges as $purge ) :
                            $purge_url  = esc_html( $purge['url'] );
                            $purge_time = isset( $purge['time'] ) && $purge['time'] ? (int) $purge['time'] : 0;
                            $purge_age  = ! empty( $purge['age'] ) ? esc_html( $purge['age'] ) : '';
                            $purge_date = ! empty( $purge['date'] ) ? esc_html( $purge['date'] ) : '';
                        ?>
                            <li class="mpc-purge-item">
                                <span class="mpc-purge-item__url"><?php echo $purge_url; ?></span>
                                <span class="mpc-purge-item__time">
                                    <?php if ( $purge_time ) : ?>
                                        <time datetime="<?php echo esc_attr( gmdate( 'c', $purge_time ) ); ?>">
                                            <?php echo $purge_age ? esc_html( sprintf( __( '%s ago', 'my-pro-cache' ), $purge_age ) ) : $purge_date; ?>
                                        </time>
                                    <?php else : ?>
                                        <?php echo $purge_date ? $purge_date : esc_html__( 'Time unknown', 'my-pro-cache' ); ?>
                                    <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p class="mpc-empty"><?php esc_html_e( 'No recent purges. Try Purge All to clear the cache.', 'my-pro-cache' ); ?></p>
                <?php endif; ?>
            </div>
        </article>
    </section>

    <details id="mpc-debug-panel" class="mpc-debug-panel" data-default-open="<?php echo $debug_enabled ? 'true' : 'false'; ?>">
        <summary>
            <span><?php esc_html_e( 'Recent Debug Log', 'my-pro-cache' ); ?></span>
            <span class="mpc-debug-panel__summary-meta"><?php echo wp_kses_post( $last_entry_summary ); ?></span>
        </summary>
        <div class="mpc-debug-panel__toolbar">
            <span><?php esc_html_e( 'Latest 50 entries.', 'my-pro-cache' ); ?></span>
            <div>
                <button type="button" class="button" data-mpc-copy-log="#mpc-debug-log" data-copy-label="<?php esc_attr_e( 'Copied log to clipboard.', 'my-pro-cache' ); ?>" data-copy-error="<?php esc_attr_e( 'Unable to copy log.', 'my-pro-cache' ); ?>" aria-label="<?php esc_attr_e( 'Copy debug log to clipboard', 'my-pro-cache' ); ?>"><?php esc_html_e( 'Copy Log', 'my-pro-cache' ); ?></button>
                <span class="mpc-copy-feedback" aria-live="polite"></span>
            </div>
        </div>
        <div id="mpc-debug-log" class="mpc-debug-panel__body" tabindex="0">
            <?php if ( $log_count > 0 ) : ?>
                <pre><?php echo esc_html( implode( "\n", $recent_debug_log ) ); ?></pre>
            <?php else : ?>
                <p class="mpc-empty"><?php esc_html_e( 'No debug logs yet. Toggle Debug to start collecting.', 'my-pro-cache' ); ?></p>
            <?php endif; ?>
        </div>
    </details>
</div>
