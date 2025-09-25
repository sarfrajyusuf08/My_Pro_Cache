<?php
/**
 * Orchestrates admin-side functionality: menu, assets, forms, and maintenance actions.
 */

namespace MyProCache\Admin;

use MyProCache\Cache\API as CacheAPI;
use MyProCache\Debug\Logger;
use MyProCache\Options\Defaults;
use MyProCache\Options\Manager;
use MyProCache\Options\Presets;
use MyProCache\Preload\PreloadManager;
use MyProCache\DB\DatabaseManager;
use MyProCache\Support\Capabilities;
use function add_action;
use function add_filter;
use function add_query_arg;
use function __;
use function admin_url;
use function check_admin_referer;
use function copy;
use function current_user_can;
use function esc_html__;
use function esc_url_raw;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function home_url;
use function implode;
use function md5_file;
use function array_filter;
use function delete_option;
use function get_option;
use function update_option;
use function time;
use function in_array;
use function sprintf;
use function str_contains;
use function is_admin;
use function is_array;
use function is_dir;
use function is_writable;
use function preg_match;
use function preg_replace;
use function rawurlencode;
use function sanitize_key;
use function strpos;
use function wp_die;
use function wp_mkdir_p;
use function wp_safe_redirect;
use function wp_unslash;

/**
 * Central service wiring together admin pages and action handlers.
 */
class AdminService
{
    private Manager $options;

    private Logger $logger;

    /**
     * Store shared dependencies for later use.
     */
    public function __construct( Manager $options, Logger $logger )
    {
        $this->options = $options;
        $this->logger  = $logger;
    }

    /**
     * Attach menus, assets, meta boxes, and action handlers.
     */
    public function register(): void
    {
        if ( is_admin() ) {
            $settings  = new SettingsController( $this->options );
            $renderer  = new PageRenderer( $this->options, $this->logger );
            $menu      = new MenuBuilder( $renderer );
            $assets    = new Assets();
            $post_meta = new PostMeta( $this->options );

            add_action( 'admin_init', array( $settings, 'register' ) );
            add_action( 'admin_menu', array( $menu, 'register' ) );
            add_action( 'admin_enqueue_scripts', array( $assets, 'enqueue' ) );
            add_action( 'add_meta_boxes', array( $post_meta, 'register' ) );
            add_action( 'save_post', array( $post_meta, 'save' ), 10, 2 );
        }

        $admin_bar = new AdminBar( $this->options );
        add_action( 'admin_bar_menu', array( $admin_bar, 'register' ), 81 );

        add_action( 'admin_post_my_pro_cache_action', array( $this, 'handle_admin_post' ) );
        add_action( 'admin_post_nopriv_my_pro_cache_action', array( $this, 'handle_admin_post' ) );
        add_action( 'update_option_' . Manager::OPTION_KEY, array( $this, 'on_options_updated' ), 10, 2 );
    }

    /**
     * Route admin-post requests for quick actions and presets.
     */
    public function handle_admin_post(): void
    {
        if ( ! current_user_can( Capabilities::manage_capability() ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'my-pro-cache' ) );
        }

        $request = is_array( $_REQUEST ) ? $_REQUEST : array();
        $action  = isset( $request['mpc_action'] ) ? sanitize_key( wp_unslash( $request['mpc_action'] ) ) : '';

        check_admin_referer( 'my_pro_cache_admin_action', '_mpc_nonce' );

        switch ( $action ) {
            case 'purge_all':
                CacheAPI::purge_all();
                $this->add_notice( __( 'All cache has been purged.', 'my-pro-cache' ) );
                break;
            case 'purge_front':
                CacheAPI::purge_url( home_url( '/' ) );
                $this->add_notice( __( 'Front page cache purged.', 'my-pro-cache' ) );
                break;
            case 'purge_url':
                $target = isset( $request['target_url'] ) ? esc_url_raw( wp_unslash( $request['target_url'] ) ) : '';
                if ( $target ) {
                    CacheAPI::purge_url( $target );
                }
                $this->add_notice( __( 'Target URL cache purged.', 'my-pro-cache' ) );
                break;
            case 'preload_all':
                PreloadManager::queue_full_preload( $this->options );
                if ( $this->options->get( 'preload_enabled', false ) ) {
                    $this->add_notice( __( 'Preload started.', 'my-pro-cache' ) );
                } else {
                    $runner = new PreloadManager( $this->options, $this->logger );
                    $runner->process_queue();
                    $this->add_notice( __( 'Preload queue warmed once. Enable the preload module for automatic runs.', 'my-pro-cache' ), 'warning' );
                }
                break;
            case 'toggle_debug':
                $current = $this->options->get( 'debug_enabled', false );
                $this->options->update( array( 'debug_enabled' => ! $current ) );
                $this->add_notice( __( 'Debug mode toggled.', 'my-pro-cache' ) );
                break;
            case 'reset_defaults':
                $this->options->replace( Defaults::all() );
                $this->write_dropin_config();
                $this->add_notice( __( 'Settings reset to safe defaults.', 'my-pro-cache' ) );
                break;
            case 'apply_preset':
                $this->handle_apply_preset( $request );
                break;
            case 'preset_rollback':
                $this->handle_preset_rollback();
                break;
            case 'enable_cache':
                $this->enable_cache();
                break;
        }

        $redirect = isset( $request['_mpc_redirect'] ) ? esc_url_raw( wp_unslash( $request['_mpc_redirect'] ) ) : admin_url();
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Keep configuration files in sync after option changes.
     */
    public function on_options_updated( $old_value, $new_value ): void
    {
        $this->write_dropin_config();
    }

    /**
     * Apply a preset configuration after validating guards and warnings.
     */
    private function handle_apply_preset( array $request ): void
    {
        $preset_id = isset( $request['preset_id'] ) ? sanitize_key( wp_unslash( $request['preset_id'] ) ) : '';
        $preset    = Presets::get( $preset_id );

        if ( ! $preset ) {
            $this->add_notice( __( 'Preset not found.', 'my-pro-cache' ), 'error' );
            return;
        }

        $guard_errors = Presets::evaluate_guards( $preset );
        if ( ! empty( $guard_errors ) ) {
            $this->add_notice( implode( ' ', $guard_errors ), 'warning' );
            return;
        }

        $current_options = $this->options->all();
        update_option( Presets::BACKUP_OPTION, array(
            'options'   => $current_options,
            'preset'    => $preset_id,
            'timestamp' => time(),
        ) );

        list( $overrides, $warnings ) = Presets::normalize_overrides( $preset, $this->options );
        $this->options->update( $overrides );

        Presets::run_post_actions( $preset, $this->options );

        $checks = Presets::run_verification_checks( $preset, $this->options );

        update_option( Presets::STATE_OPTION, array(
            'preset'    => $preset_id,
            'timestamp' => time(),
            'warnings'  => $warnings,
            'checks'    => $checks,
        ) );

        $message = sprintf( __( '%s preset applied.', 'my-pro-cache' ), $preset['label'] );
        if ( ! empty( $warnings ) ) {
            $message .= ' ' . __( 'Review the warnings below.', 'my-pro-cache' );
        }

        $this->add_notice( $message, empty( $warnings ) ? 'success' : 'warning' );
    }

    /**
     * Restore the previous options snapshot saved before a preset run.
     */
    private function handle_preset_rollback(): void
    {
        $backup = get_option( Presets::BACKUP_OPTION, array() );
        if ( empty( $backup['options'] ) || ! is_array( $backup['options'] ) ) {
            $this->add_notice( __( 'No preset backup available to restore.', 'my-pro-cache' ), 'warning' );
            return;
        }

        $this->options->replace( $backup['options'] );
        $this->write_dropin_config();

        delete_option( Presets::BACKUP_OPTION );
        delete_option( Presets::STATE_OPTION );

        $this->add_notice( __( 'Previous settings restored.', 'my-pro-cache' ) );
    }

    /**
     * Attempt to enable file-based caching and report errors.
     */
    private function enable_cache(): void
    {
        $errors = array();

        $wp_cache_result = $this->ensure_wp_cache_constant();
        if ( ! $wp_cache_result['success'] ) {
            $errors[] = $wp_cache_result['message'];
        }

        $dropin_result = $this->copy_dropin();
        if ( ! $dropin_result['success'] ) {
            $errors[] = $dropin_result['message'];
        }

        if ( ! $this->write_dropin_config() ) {
            $errors[] = __( 'Failed to write cache configuration file.', 'my-pro-cache' );
        }

        if ( ! $this->options->ensure_cache_directory() ) {
            $errors[] = __( 'Failed to create cache directories.', 'my-pro-cache' );
        }

        $errors = array_filter( $errors );

        if ( empty( $errors ) ) {
            $this->add_notice( __( 'Page cache enabled.', 'my-pro-cache' ) );
            return;
        }

        $this->add_notice(
            sprintf(
                __( 'Page cache could not be fully enabled: %s', 'my-pro-cache' ),
                implode( ' ', $errors )
            ),
            'error'
        );
    }

    /**
     * Inject or update the WP_CACHE definition inside wp-config.php.
     *
     * @return array{success:bool,message:?string} Result status.
     */
    /**
     * Inject or update the WP_CACHE definition inside wp-config.php.
     *
     * @return array{success:bool,message:?string} Result status.
     */
    private function ensure_wp_cache_constant(): array
    {
        if ( defined( 'WP_CACHE' ) && WP_CACHE ) {
            return array( 'success' => true, 'message' => null );
        }

        $config = ABSPATH . 'wp-config.php';

        if ( ! file_exists( $config ) || ! is_writable( $config ) ) {
            return array(
                'success' => false,
                'message' => __( 'wp-config.php is missing or not writable.', 'my-pro-cache' ),
            );
        }

        $contents = file_get_contents( $config );
        if ( false === $contents ) {
            return array(
                'success' => false,
                'message' => __( 'Unable to read wp-config.php.', 'my-pro-cache' ),
            );
        }

        $true_pattern  = "/define\(\s*['\" ]WP_CACHE['\" ]\s*,\s*true\s*\)\s*;/i";
        $false_pattern = "/define\(\s*['\" ]WP_CACHE['\" ]\s*,\s*false\s*\)\s*;/i";

        if ( preg_match( $true_pattern, $contents ) ) {
            return array( 'success' => true, 'message' => null );
        }

        if ( preg_match( $false_pattern, $contents ) ) {
            $updated = preg_replace( $false_pattern, "define('WP_CACHE', true); // Modified by My Pro Cache;", $contents, 1 );
            if ( null === $updated ) {
                return array(
                    'success' => false,
                    'message' => __( 'Could not update WP_CACHE definition in wp-config.php.', 'my-pro-cache' ),
                );
            }
            $contents = $updated;
        } else {
            $insert = "\n\ndefine('WP_CACHE', true); // Added by My Pro Cache\n";
            $tag    = '<?php';
            $pos    = strpos( $contents, $tag );

            if ( false === $pos ) {
                $contents = $tag . $insert . $contents;
            } else {
                $pos += strlen( $tag );
                $contents = substr( $contents, 0, $pos ) . $insert . substr( $contents, $pos );
            }
        }

        if ( false === file_put_contents( $config, $contents ) ) {
            return array(
                'success' => false,
                'message' => __( 'Unable to update wp-config.php with WP_CACHE.', 'my-pro-cache' ),
            );
        }

        return array( 'success' => true, 'message' => null );
    }

    /**
     * Copy the advanced-cache drop-in if compatible or report why not.
     *
     * @return array{success:bool,message:?string} Result status.
     */
    private function copy_dropin(): array
    {
        $source = MY_PRO_CACHE_PLUGIN_DIR . 'advanced-cache.php';
        $target = WP_CONTENT_DIR . '/advanced-cache.php';

        if ( ! file_exists( $source ) ) {
            return array(
                'success' => false,
                'message' => __( 'Advanced cache template is missing from the plugin.', 'my-pro-cache' ),
            );
        }

        if ( file_exists( $target ) ) {
            if ( md5_file( $source ) === md5_file( $target ) ) {
                return array( 'success' => true, 'message' => null );
            }

            $existing = file_get_contents( $target );
            if ( false === $existing ) {
                return array(
                    'success' => false,
                    'message' => __( 'Existing advanced-cache.php could not be inspected.', 'my-pro-cache' ),
                );
            }

            if ( ! str_contains( $existing, 'My Pro Cache' ) ) {
                return array(
                    'success' => false,
                    'message' => __( 'Another plugin already provides wp-content/advanced-cache.php.', 'my-pro-cache' ),
                );
            }
        }

        if ( ! copy( $source, $target ) ) {
            return array(
                'success' => false,
                'message' => __( 'Failed to copy advanced-cache.php into wp-content.', 'my-pro-cache' ),
            );
        }

        return array( 'success' => true, 'message' => null );
    }
    /**
     * Write cached configuration for the drop-in on disk.
     */
    public function write_dropin_config(): bool
    {
        $config_file = WP_CONTENT_DIR . '/cache/my-pro-cache/config.php';
        $dir         = dirname( $config_file );

        if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
            return false;
        }

        $options = $this->options->all();
        $data    = array(
            'ttl_default'                 => (int) ( $options['ttl_default'] ?? 3600 ),
            'ttl_front_page'              => (int) ( $options['ttl_front_page'] ?? 600 ),
            'ttl_feed'                    => (int) ( $options['ttl_feed'] ?? 900 ),
            'stale_while_revalidate'      => (int) ( $options['stale_while_revalidate'] ?? 0 ),
            'stale_if_error'              => (int) ( $options['stale_if_error'] ?? 0 ),
            'exclude_urls'                => (array) ( $options['exclude_urls'] ?? array() ),
            'exclude_cookies'             => (array) ( $options['exclude_cookies'] ?? array() ),
            'exclude_user_agents'         => (array) ( $options['exclude_user_agents'] ?? array() ),
            'exclude_query_args'          => (array) ( $options['exclude_query_args'] ?? array() ),
            'cache_vary_device'           => (bool) ( $options['cache_vary_device'] ?? true ),
            'cache_vary_lang'             => (bool) ( $options['cache_vary_lang'] ?? false ),
            'cache_vary_cookie_allowlist' => (array) ( $options['cache_vary_cookie_allowlist'] ?? array() ),
        );

        $contents = "<?php\nreturn " . var_export( $data, true ) . ";\n";

        return false !== file_put_contents( $config_file, $contents );
    }

    /**
     * Queue a redirect notice with optional severity type.
     */
    private function add_notice( string $message, string $type = 'success' ): void
    {
        $allowed = array( 'success', 'error', 'warning', 'info' );
        $type    = in_array( $type, $allowed, true ) ? $type : 'success';

        add_filter(
            'redirect_post_location',
            function( $location ) use ( $message, $type ) {
                return add_query_arg(
                    array(
                        'my_pro_cache_notice'      => rawurlencode( $message ),
                        'my_pro_cache_notice_type' => $type,
                    ),
                    $location
                );
            }
        );
    }

}














