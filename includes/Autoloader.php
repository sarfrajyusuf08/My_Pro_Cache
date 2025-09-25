<?php

namespace MyProCache;

/**
 * Simple PSR-4 autoloader for the plugin.
 */
class Autoloader
{
    private string $baseNamespace;

    private string $baseDir;

    public function __construct( string $baseNamespace, string $baseDir )
    {
        $this->baseNamespace = trim( $baseNamespace, '\\' ) . '\\';
        $this->baseDir       = rtrim( $baseDir, '\\/' ) . DIRECTORY_SEPARATOR;
    }

    public function register(): void
    {
        spl_autoload_register( array( $this, 'autoload' ) );
    }

    private function autoload( string $class ): void
    {
        if ( strncmp( $class, $this->baseNamespace, strlen( $this->baseNamespace ) ) !== 0 ) {
            return;
        }

        $relative = substr( $class, strlen( $this->baseNamespace ) );
        $relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );

        $file = $this->baseDir . $relative . '.php';

        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
}