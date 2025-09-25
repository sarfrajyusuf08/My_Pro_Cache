<?php
/**
 * Generic settings view renders the tab navigation and WP settings form.
 * Individual sections are registered via SettingsController.
 */

/**
 * Generic settings page renderer.
 *
 * @var array  $page
 * @var string $slug
 * @var array  $tabs
 * @var array  $values
 */
?>
<!-- Settings page container shares dashboard styling -->
<div class="wrap my-pro-cache-wrap mpc-dashboard-wrap">
    <header class="mpc-header">
        <div class="mpc-header__title">
            <h1> echo esc_html( $page['page_title'] ?? 'My Pro Cache' ); ?></h1>
        </div>
    </header>

     if ( ! empty( $_GET['my_pro_cache_notice'] ) ) :
        $notice_type    = isset( $_GET['my_pro_cache_notice_type'] ) ? sanitize_key( wp_unslash( $_GET['my_pro_cache_notice_type'] ) ) : 'success';
        $allowed_types  = array( 'success', 'error', 'warning', 'info' );
        $notice_type    = in_array( $notice_type, $allowed_types, true ) ? $notice_type : 'success';
        $notice_classes = 'notice notice-' . $notice_type . ' is-dismissible';
        $notice_message = esc_html( wp_unslash( $_GET['my_pro_cache_notice'] ) );
    ?>
        <div class=" echo esc_attr( $notice_classes ); ?>"><p> echo $notice_message; ?></p></div>
     endif; ?>

    <nav class="nav-tab-wrapper" aria-label=" esc_attr_e( 'My Pro Cache navigation', 'my-pro-cache' ); ?>">
         foreach ( $tabs as $tab ) : ?>
            <a class="nav-tab  echo $tab['current'] ? 'nav-tab-active' : ''; ?>" href=" echo esc_url( $tab['url'] ); ?>"> echo esc_html( $tab['label'] ); ?></a>
         endforeach; ?>
    </nav>

     if ( ! empty( $page['description'] ) ) : ?>
        <p class="mpc-page-summary"> echo esc_html( $page['description'] ); ?></p>
     endif; ?>

     if ( ! isset( $page['show_save'] ) || $page['show_save'] ) : ?>
        <!-- Settings form wrapper renders section fields -->
        <div class="mpc-settings-panel" role="region" aria-labelledby="mpc-settings-title">
            <h2 id="mpc-settings-title" class="screen-reader-text"> echo esc_html( $page['page_title'] ?? __( 'Settings', 'my-pro-cache' ) ); ?></h2>
            <form method="post" action=" echo esc_url( admin_url( 'options.php' ) ); ?>" class="mpc-settings-form">
                
                settings_fields( 'my_pro_cache_options_group' );
                do_settings_sections( 'my-pro-cache-' . $slug );
                submit_button( __( 'Save Changes', 'my-pro-cache' ), 'primary', 'submit', true, array( 'class' => 'mpc-save-button' ) );
                ?>
            </form>

            <!-- Secondary actions like reset live here -->
            <div class="mpc-settings-toolbar">
                <form method="post" action=" echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mpc-reset-form">
                     wp_nonce_field( 'my_pro_cache_admin_action', '_mpc_nonce' ); ?>
                    <input type="hidden" name="action" value="my_pro_cache_action" />
                    <input type="hidden" name="mpc_action" value="reset_defaults" />
                    <input type="hidden" name="_mpc_redirect" value=" echo esc_url( admin_url( 'admin.php?page=my-pro-cache-' . $slug ) ); ?>" />
                     submit_button( __( 'Reset to Safe Defaults', 'my-pro-cache' ), 'secondary', 'submit', false ); ?>
                </form>
            </div>
        </div>
     else : ?>
        <div class="mpc-settings-panel" role="region">
             do_settings_sections( 'my-pro-cache-' . $slug ); ?>
        </div>
     endif; ?>
</div>
