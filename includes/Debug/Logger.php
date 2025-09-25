<?php

namespace MyProCache\Debug;

use function date;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function filesize;
use function get_option;
use function is_array;
use function is_dir;
use function rename;
use function sprintf;
use function trim;
use function unlink;
use function wp_mkdir_p;
use function strtoupper;
use function time;
use const FILE_APPEND;
use const WP_CONTENT_DIR;

class Logger
{
    private string $logDir;

    private string $logFile;

    public function __construct()
    {
        $this->logDir  = WP_CONTENT_DIR . '/cache/my-pro-cache/logs';
        $this->logFile = $this->logDir . '/debug.log';
        $this->ensure_dir();
    }

    public function log( string $channel, string $message ): void
    {
        if ( ! $this->is_logging_enabled() ) {
            return;
        }

        $this->ensure_dir();

        $line = sprintf( '[%s] [%s] %s%s', date( 'c' ), strtoupper( $channel ), trim( $message ), PHP_EOL );
        file_put_contents( $this->logFile, $line, FILE_APPEND );
        $this->maybe_rotate();
    }

    public function get_log(): string
    {
        if ( ! file_exists( $this->logFile ) ) {
            return '';
        }

        return (string) file_get_contents( $this->logFile );
    }

    public function clear(): void
    {
        if ( file_exists( $this->logFile ) ) {
            unlink( $this->logFile );
        }
    }

    private function ensure_dir(): void
    {
        if ( ! is_dir( $this->logDir ) ) {
            wp_mkdir_p( $this->logDir );
        }
    }

    private function maybe_rotate(): void
    {
        $maxSize = 1024 * 1024 * 2; // 2MB

        if ( file_exists( $this->logFile ) && filesize( $this->logFile ) > $maxSize ) {
            rename( $this->logFile, $this->logFile . '.' . time() );
        }
    }

    private function is_logging_enabled(): bool
    {
        static $enabled = null;

        if ( null !== $enabled ) {
            return $enabled;
        }

        $enabled = defined( 'WP_DEBUG' ) && WP_DEBUG;

        if ( ! $enabled && function_exists( 'get_option' ) ) {
            $settings = get_option( 'my_pro_cache_options', array() );
            if ( is_array( $settings ) && ! empty( $settings['debug_enabled'] ) ) {
                $enabled = true;
            }
        }

        return $enabled;
    }
}

