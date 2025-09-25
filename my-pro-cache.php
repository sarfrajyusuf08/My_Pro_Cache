<?php
/**
 * Plugin Name:       My Pro Cache
 * Plugin URI:        https://example.com/
 * Description:       Enterprise-grade caching, optimization, and CDN toolkit for WordPress.
 * Version:           1.2.0
 * Author:            Your Name
 * Author URI:        https://example.com/
 * Text Domain:       my-pro-cache
 * Domain Path:       /languages
 * Requires at least: 6.1
 * Requires PHP:      8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Environment guards: avoid fatal errors on unsupported setups.
// Keep this BEFORE any typed code or autoloaders are included.
if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
    if ( function_exists( 'add_action' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'My Pro Cache requires PHP 8.0 or higher. Please upgrade PHP to activate the plugin.', 'my-pro-cache' ) . '</p></div>';
        } );
    }
    return;
}

global $wp_version;
if ( isset( $wp_version ) && version_compare( $wp_version, '6.1', '<' ) ) {
    if ( function_exists( 'add_action' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'My Pro Cache requires WordPress 6.1 or higher. Please update WordPress to activate the plugin.', 'my-pro-cache' ) . '</p></div>';
        } );
    }
    return;
}

if ( ! defined( 'MY_PRO_CACHE_VERSION' ) ) {
    define( 'MY_PRO_CACHE_VERSION', '1.2.0' );
}

define( 'MY_PRO_CACHE_FILE', __FILE__ );
define( 'MY_PRO_CACHE_DIR', plugin_dir_path( __FILE__ ) );
define( 'MY_PRO_CACHE_URL', plugin_dir_url( __FILE__ ) );
define( 'MY_PRO_CACHE_OPTION_KEY', 'my_pro_cache_options' );
define( 'MY_PRO_CACHE_PLUGIN_DIR', MY_PRO_CACHE_DIR );

require_once MY_PRO_CACHE_DIR . 'includes/Autoloader.php';

$autoloader = new \MyProCache\Autoloader( 'MyProCache', MY_PRO_CACHE_DIR . 'includes' );
$autoloader->register();

\MyProCache\Plugin::instance()->boot();


