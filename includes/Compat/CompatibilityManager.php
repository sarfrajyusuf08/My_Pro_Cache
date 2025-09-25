<?php

namespace MyProCache\Compat;

use MyProCache\Options\Manager;
use MyProCache\Debug\Logger;
use function add_action;

class CompatibilityManager
{
    private Manager $options;

    private Logger $logger;

    public function __construct( Manager $options, Logger $logger )
    {
        $this->options = $options;
        $this->logger  = $logger;
    }

    public function register(): void
    {
        add_action( 'plugins_loaded', array( $this, 'detect_conflicts' ), 20 );
    }

    public function detect_conflicts(): void
    {
        if ( class_exists( 'WP_Super_Cache' ) ) {
            $this->logger->log( 'compat', 'WP Super Cache detected. Consider disabling overlapping cache features.' );
        }
    }
}
