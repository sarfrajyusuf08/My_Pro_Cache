<?php

namespace MyProCache;

use MyProCache\Admin\AdminService;
use MyProCache\Options\Manager;
use MyProCache\Cache\Controller as CacheController;
use MyProCache\Purge\PurgeManager;
use MyProCache\Preload\PreloadManager;
use MyProCache\REST\Routes as RestRoutes;
use MyProCache\CLI\Commands as CliCommands;
use MyProCache\Debug\Logger;
use MyProCache\CDN\Rewriter as CdnRewriter;
use MyProCache\CDN\CloudflarePurger;
use MyProCache\Media\MediaManager;
use MyProCache\Toolbox\ToolboxManager;
use MyProCache\DB\DatabaseManager;
use MyProCache\ObjectCache\ObjectCacheManager;
use MyProCache\Compat\CompatibilityManager;
use function load_plugin_textdomain;
use function register_activation_hook;
use function register_deactivation_hook;
use function do_action;
use function wp_unschedule_hook;

final class Plugin
{
    private static ?self $instance = null;

    private Manager $options;

    private Logger $logger;

    private ?ObjectCacheManager $objectCache = null;

    private ?DatabaseManager $database = null;

    private function __construct()
    {
        $this->options = new Manager();
        $this->logger  = new Logger();
    }

    public static function instance(): self
    {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void
    {
        $this->load_textdomain();

        $admin = new AdminService( $this->options, $this->logger );
        $admin->register();

        $cache = new CacheController( $this->options, $this->logger );
        $cache->register();

        $purge = new PurgeManager( $this->options, $this->logger );
        $purge->register();

        $preload = new PreloadManager( $this->options, $this->logger );
        $preload->register();

        $media = new MediaManager( $this->options, $this->logger );
        $media->register();

        $this->objectCache = new ObjectCacheManager( $this->options, $this->logger );
        $this->objectCache->register();

        $cdn = new CdnRewriter( $this->options, $this->logger );
        $cdn->register();

        $cloudflare = new CloudflarePurger( $this->options, $this->logger );
        $cloudflare->register();

        $this->database = new DatabaseManager( $this->options, $this->logger );
        $this->database->register();

        $compat = new CompatibilityManager( $this->options, $this->logger );
        $compat->register();

        $rest = new RestRoutes( $this->options, $this->logger );
        $rest->register();

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            $cli = new CliCommands( $this->options, $this->logger );
            $cli->register();
        }

        register_activation_hook( MY_PRO_CACHE_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( MY_PRO_CACHE_FILE, array( $this, 'deactivate' ) );
    }

    private function load_textdomain(): void
    {
        load_plugin_textdomain( 'my-pro-cache', false, basename( dirname( MY_PRO_CACHE_FILE ) ) . '/languages' );
    }

    public function activate(): void
    {
        $this->options->initialize_defaults();
        $this->options->ensure_cache_directory();
        $admin = new AdminService( $this->options, $this->logger );
        $admin->write_dropin_config();
        do_action( 'my_pro_cache_activated' );
    }

    public function deactivate(): void
    {
        wp_unschedule_hook( 'my_pro_cache_preload_run' );
        if ( $this->objectCache ) {
            $this->objectCache->deactivate();
        }
        if ( $this->database ) {
            $this->database->deactivate();
        }
        do_action( 'my_pro_cache_deactivated' );
    }
}










