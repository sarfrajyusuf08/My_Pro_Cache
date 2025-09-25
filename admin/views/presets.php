<?php
/**
 * Presets view shows tuning cards with risk badges and apply forms.
 * PageRenderer precomputes diffs, guard messages, and backup state.
 */

use MyProCache\Options\Presets;

/**
 * @var array $page
 * @var array $tabs
 * @var array $presets
 * @var array $preset_diffs
 * @var array $preset_guards
 * @var array $preset_state
 * @var array $preset_backup
 */

$presets        = is_array( $presets ?? null ) ? $presets : array();
$preset_diffs   = is_array( $preset_diffs ?? null ) ? $preset_diffs : array();
$preset_guards  = is_array( $preset_guards ?? null ) ? $preset_guards : array();
$preset_state   = is_array( $preset_state ?? null ) ? $preset_state : array();
$preset_backup  = is_array( $preset_backup ?? null ) ? $preset_backup : array();
$recent_preset  = $preset_state['preset'] ?? '';
$recent_checks  = is_array( $preset_state['checks'] ?? null ) ? $preset_state['checks'] : array();
$recent_warnings = is_array( $preset_state['warnings'] ?? null ) ? $preset_state['warnings'] : array();

$redirect_url   = esc_url( admin_url( 'admin.php?page=my-pro-cache-presets' ) );
$admin_post_url = esc_url( admin_url( 'admin-post.php' ) );
?>
<!-- Presets page container -->
<div class="wrap my-pro-cache-wrap mpc-dashboard-wrap">
    <header class="mpc-header">
        <div class="mpc-header__title">
            <h1><?php echo esc_html__( 'Performance Presets', 'my-pro-cache' ); ?></h1>
        </div>
    </header>

    <nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'My Pro Cache navigation', 'my-pro-cache' ); ?>">
        <?php foreach ( $tabs as $tab ) : ?>
            <a class="nav-tab <?php echo $tab['current'] ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $tab['url'] ); ?>"><?php echo esc_html( $tab['label'] ); ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ( ! empty( $page['description'] ) ) : ?>
        <p class="mpc-page-summary"><?php echo esc_html( $page['description'] ); ?></p>
    <?php endif; ?>

    <?php if ( ! empty( $preset_backup['options'] ) ) : ?>
        <div class="notice notice-info mpc-preset-backup">
            <p><?php echo esc_html( Presets::format_backup_summary( $preset_backup ) ); ?></p>
            <form method="post" action="<?php echo $admin_post_url; ?>" class="mpc-preset-backup__form">
                <?php wp_nonce_field( 'my_pro_cache_admin_action', '_mpc_nonce' ); ?>
                <input type="hidden" name="action" value="my_pro_cache_action" />
                <input type="hidden" name="mpc_action" value="preset_rollback" />
                <input type="hidden" name="_mpc_redirect" value="<?php echo $redirect_url; ?>" />
                <button type="submit" class="button button-secondary"><?php esc_html_e( 'Restore Previous Settings', 'my-pro-cache' ); ?></button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Card grid listing each available preset -->
    <section class="mpc-preset-grid" aria-label="<?php esc_attr_e( 'Available presets', 'my-pro-cache' ); ?>">
        <?php foreach ( $presets as $preset ) :
            $id          = $preset['id'];
            $risk        = $preset['risk'] ?? 'low';
            $risk_labels = array(
                'low'    => __( 'Low risk', 'my-pro-cache' ),
                'medium' => __( 'Medium risk', 'my-pro-cache' ),
                'high'   => __( 'High risk', 'my-pro-cache' ),
            );
            $risk_text   = $risk_labels[ $risk ] ?? __( 'Risk level unknown', 'my-pro-cache' );
            $diff        = $preset_diffs[ $id ] ?? array();
            $guard_msgs  = $preset_guards[ $id ] ?? array();
            $was_applied = ( $recent_preset === $id );
            $checks      = $was_applied ? $recent_checks : array();
            $warnings    = $was_applied ? $recent_warnings : array();
        ?>
            <article class="mpc-preset-card" role="group" aria-labelledby="mpc-preset-<?php echo esc_attr( $id ); ?>">
                <header class="mpc-preset-card__header">
                    <h2 id="mpc-preset-<?php echo esc_attr( $id ); ?>" class="mpc-preset-card__title"><?php echo esc_html( $preset['label'] ); ?></h2>
                    <span class="mpc-risk-badge mpc-risk--<?php echo esc_attr( $risk ); ?>"><?php echo esc_html( $risk_text ); ?></span>
                </header>
                <p class="mpc-preset-card__description"><?php echo esc_html( $preset['description'] ); ?></p>

                <?php if ( ! empty( $preset['highlights'] ) ) : ?>
                    <ul class="mpc-preset-highlights">
                        <?php foreach ( $preset['highlights'] as $highlight ) : ?>
                            <li><?php echo esc_html( $highlight ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <details class="mpc-preset-details">
                    <summary><?php esc_html_e( 'View option changes', 'my-pro-cache' ); ?></summary>
                    <?php if ( ! empty( $diff ) ) : ?>
                        <ul class="mpc-diff-list">
                            <?php foreach ( array_slice( $diff, 0, 12 ) as $key => $change ) : ?>
                                <li>
                                    <strong><?php echo esc_html( $key ); ?></strong>
                                    <span aria-hidden="true">&rarr;</span>
                                    <span><?php echo esc_html( $change['from'] ); ?></span>
                                    <span class="mpc-diff-sep"><?php esc_html_e( 'to', 'my-pro-cache' ); ?></span>
                                    <span><?php echo esc_html( $change['to'] ); ?></span>
                                </li>
                            <?php endforeach; ?>
                            <?php if ( count( $diff ) > 12 ) : ?>
                                <li class="mpc-diff-more"><?php echo esc_html( sprintf( __( '+ %d more changes', 'my-pro-cache' ), count( $diff ) - 12 ) ); ?></li>
                            <?php endif; ?>
                        </ul>
                    <?php else : ?>
                        <p class="mpc-empty"><?php esc_html_e( 'Already aligned with current settings.', 'my-pro-cache' ); ?></p>
                    <?php endif; ?>
                </details>

                <?php if ( ! empty( $guard_msgs ) ) : ?>
                    <div class="mpc-preset-guards">
                        <?php foreach ( $guard_msgs as $message ) : ?>
                            <p class="notice notice-warning"><span><?php echo esc_html( $message ); ?></span></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ( $was_applied ) : ?>
                    <details class="mpc-preset-verification" open>
                        <summary><?php esc_html_e( 'Post-apply checks', 'my-pro-cache' ); ?></summary>
                        <ul class="mpc-checklist">
                            <?php foreach ( $checks as $check ) :
                                $status = $check['status'] ?? 'skip';
                            ?>
                                <li class="mpc-checklist__item mpc-checklist__item--<?php echo esc_attr( $status ); ?>">
                                    <?php echo esc_html( $check['label'] ?? '' ); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ( ! empty( $warnings ) ) : ?>
                            <ul class="mpc-warning-list">
                                <?php foreach ( $warnings as $warning ) : ?>
                                    <li><?php echo esc_html( $warning ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </details>
                <?php endif; ?>

                <form method="post" action="<?php echo $admin_post_url; ?>" class="mpc-preset-card__form">
                    <?php wp_nonce_field( 'my_pro_cache_admin_action', '_mpc_nonce' ); ?>
                    <input type="hidden" name="action" value="my_pro_cache_action" />
                    <input type="hidden" name="mpc_action" value="apply_preset" />
                    <input type="hidden" name="preset_id" value="<?php echo esc_attr( $id ); ?>" />
                    <input type="hidden" name="_mpc_redirect" value="<?php echo $redirect_url; ?>" />
                    <button type="submit" class="button button-primary" <?php echo ! empty( $guard_msgs ) ? 'disabled' : ''; ?>>
                        <?php esc_html_e( 'Apply Preset', 'my-pro-cache' ); ?>
                    </button>
                </form>
            </article>
        <?php endforeach; ?>
    </section>
</div>
