<?php
/**
 * Manages installation/removal of object cache drop-in and configuration.
 */

namespace MyProCache\ObjectCache;

use MyProCache\Debug\Logger;
use MyProCache\Options\Manager;
use function add_action;
use function addslashes;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function realpath;
use function is_dir;
use function unlink;
use function wp_mkdir_p;
use const WP_CONTENT_DIR;

/**
 * Keeps the object cache drop-in in sync with plugin settings.
 */
class ObjectCacheManager
{
    private const DROPIN_PATH = WP_CONTENT_DIR . '/object-cache.php';

    private const CONFIG_PATH = WP_CONTENT_DIR . '/cache/my-pro-cache/object-cache-config.php';

    private const HEADER = 'My Pro Cache Object Cache Drop-in';

    private Manager $options;

    private Logger $logger;

    /**
     * Accept dependencies needed for logging and reading options.
     */
    public function __construct( Manager $options, Logger $logger )
    {
        $this->options = $options;
        $this->logger  = $logger;
    }

    /**
     * Hook synchronisation into admin lifecycle.
     */
    public function register(): void
    {
        $this->sync();
        add_action( 'update_option_' . Manager::OPTION_KEY, array( $this, 'on_options_updated' ), 10, 2 );
    }

    public function on_options_updated( $old_value, $new_value ): void
    {
        $watch = array( 'oc_enabled', 'oc_backend', 'oc_host', 'oc_port', 'oc_auth', 'oc_persistent_groups', 'oc_compression' );
        foreach ( $watch as $key ) {
            if ( ( $old_value[ $key ] ?? null ) !== ( $new_value[ $key ] ?? null ) ) {
                $this->sync();
                break;
            }
        }
    }

    /**
     * Create or remove drop-in based on option state.
     */
    public function sync(): void
    {
        if ( $this->options->get( 'oc_enabled', false ) ) {
            $this->write_config();
            $this->ensure_dropin();
        } else {
            $this->remove_dropin();
        }
    }

    /**
     * Clean up drop-in when plugin deactivates.
     */
    public function deactivate(): void
    {
        $this->remove_dropin();
    }


    /**
     * Write object-cache.php drop-in if compatible.
     */
    private function ensure_dropin(): void
    {
        $directory = WP_CONTENT_DIR . '/cache/my-pro-cache';
        if ( ! is_dir( $directory ) ) {
            wp_mkdir_p( $directory );
        }

        $dropin_file = MY_PRO_CACHE_PLUGIN_DIR . 'includes/ObjectCache/DropIn.php';
        $dropin_real = realpath( $dropin_file );
        if ( $dropin_real ) {
            $dropin_file = $dropin_real;
        }

        $dropin_file = str_replace( '\\', '\\\\', $dropin_file );
        $dropin_file = str_replace( "'", "\'", $dropin_file );

        $header = self::HEADER;

        $dropin_code = sprintf(
            <<<'PHP'
<?php
/**
 * %1$s
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once ABSPATH . WPINC . '/class-wp-object-cache.php';

$config = array();
$config_file = __DIR__ . '/cache/my-pro-cache/object-cache-config.php';
if ( file_exists( $config_file ) ) {
    $maybe_config = include $config_file;
    if ( is_array( $maybe_config ) ) {
        $config = $maybe_config;
    }
}

$mpc_dropin = %2$s;
if ( ! file_exists( $mpc_dropin ) ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( '[My Pro Cache] Object cache drop-in missing. Fallback to core object cache.' );
    }
    require_once ABSPATH . WPINC . '/object-cache.php';
    return;
}

require_once $mpc_dropin;
PHP,
            $header,
            var_export( $dropin_file, true )
        );

        file_put_contents( self::DROPIN_PATH, $dropin_code );
    }

    /**
     * Remove drop-in if it was installed by the plugin.
     */
    private function remove_dropin(): void
    {
        $dropin = self::DROPIN_PATH;
        if ( ! file_exists( $dropin ) ) {
            return;
        }

        $contents = file_get_contents( $dropin );
        if ( false !== $contents && strpos( $contents, self::HEADER ) !== false ) {
            unlink( $dropin );
        }
    }

    /**
     * Persist backend connection settings to disk.
     */
    private function write_config(): void
    {
        $directory = WP_CONTENT_DIR . '/cache/my-pro-cache';
        if ( ! is_dir( $directory ) ) {
            wp_mkdir_p( $directory );
        }

        $config = array(
            'backend'           => $this->options->get( 'oc_backend', 'redis' ),
            'host'              => $this->options->get( 'oc_host', '127.0.0.1' ),
            'port'              => (int) $this->options->get( 'oc_port', 'redis' === $this->options->get( 'oc_backend', 'redis' ) ? 6379 : 11211 ),
            'auth'              => (string) $this->options->get( 'oc_auth', '' ),
            'compression'       => (bool) $this->options->get( 'oc_compression', false ),
            'persistent_groups' => array_map( 'strval', (array) $this->options->get( 'oc_persistent_groups', array( 'options', 'site-options' ) ) ),
        );

        $code = "<?php\nreturn " . var_export( $config, true ) . ";\n";
        file_put_contents( self::CONFIG_PATH, $code );
    }
}
