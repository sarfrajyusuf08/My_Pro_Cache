<?php

namespace MyProCache\DB;

use MyProCache\Debug\Logger;
use MyProCache\Options\Manager;
use MyProCache\Support\Capabilities;
use function add_action;
use function admin_url;
use function array_filter;
use function array_map;
use function array_unique;
use function check_admin_referer;
use function current_user_can;
use function delete_expired_site_transients;
use function delete_expired_transients;
use function esc_url_raw;
use function get_comment;
use function get_post;
use function get_users;
use function is_array;
use function wp_delete_comment;
use function wp_delete_post;
use function wp_delete_post_revision;
use function wp_get_referer;
use function wp_safe_redirect;
use function wp_schedule_event;
use function wp_unschedule_hook;
use function wp_next_scheduled;
use function wp_json_encode;
use const DAY_IN_SECONDS;
use const HOUR_IN_SECONDS;
use const WP_CONTENT_DIR;
use WP_Post;
use WP_Comment;
use wpdb;

class DatabaseManager
{
    private const HOOK = 'my_pro_cache_database_cleanup';

    private Manager $options;

    private Logger $logger;

    public function __construct( Manager $options, Logger $logger )
    {
        $this->options = $options;
        $this->logger  = $logger;
    }

    public function register(): void
    {
        add_action( 'init', array( $this, 'maybe_schedule' ) );
        add_action( self::HOOK, array( $this, 'run_cleanup' ) );
        add_action( 'admin_post_my_pro_cache_database_cleanup', array( $this, 'handle_manual_cleanup' ) );
    }

    public function deactivate(): void
    {
        wp_unschedule_hook( self::HOOK );
    }

    public function maybe_schedule(): void
    {
        if ( ! $this->options->get( 'general_module_database', true ) ) {
            wp_unschedule_hook( self::HOOK );
            return;
        }

        if ( ! $this->has_enabled_tasks() ) {
            wp_unschedule_hook( self::HOOK );
            return;
        }

        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
        }
    }

    public function handle_manual_cleanup(): void
    {
        if ( ! current_user_can( Capabilities::manage_capability() ) ) {
            wp_safe_redirect( admin_url() );
            return;
        }

        check_admin_referer( 'my_pro_cache_admin_action', '_mpc_nonce' );

        $this->run_cleanup();

        $referer = wp_get_referer();
        wp_safe_redirect( $referer ? esc_url_raw( $referer ) : admin_url() );
        exit;
    }

    public function run_cleanup(): void
    {
        $report = array();

        if ( $this->options->get( 'database_cleanup_revisions', false ) ) {
            $report['revisions'] = $this->cleanup_revisions();
        }

        if ( $this->options->get( 'database_cleanup_autodrafts', false ) ) {
            $report['autodrafts'] = $this->cleanup_autodrafts();
        }

        if ( $this->options->get( 'database_cleanup_trash_posts', false ) ) {
            $report['trash_posts'] = $this->cleanup_trash_posts();
        }

        if ( $this->options->get( 'database_cleanup_spam', false ) ) {
            $report['spam_comments'] = $this->cleanup_spam_comments();
        }

        if ( $this->options->get( 'database_cleanup_transients', false ) ) {
            $report['transients'] = $this->cleanup_transients();
        }

        if ( $this->options->get( 'database_cleanup_sessions', false ) ) {
            $report['sessions'] = $this->cleanup_sessions();
        }

        if ( $this->options->get( 'database_optimize_tables', false ) ) {
            $report['optimized'] = $this->optimize_tables();
        }

        $this->logger->log( 'database', 'Database cleanup run: ' . wp_json_encode( $report ) );
    }

    private function has_enabled_tasks(): bool
    {
        $keys = array(
            'database_cleanup_revisions',
            'database_cleanup_autodrafts',
            'database_cleanup_trash_posts',
            'database_cleanup_spam',
            'database_cleanup_transients',
            'database_cleanup_sessions',
            'database_optimize_tables',
        );

        foreach ( $keys as $key ) {
            if ( $this->options->get( $key, false ) ) {
                return true;
            }
        }

        return false;
    }

    private function cleanup_revisions(): int
    {
        global $wpdb;

        $ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision' LIMIT 500" );
        $count = 0;

        foreach ( $ids as $id ) {
            if ( wp_delete_post_revision( (int) $id ) ) {
                $count++;
            }
        }

        return $count;
    }

    private function cleanup_autodrafts(): int
    {
        global $wpdb;

        $ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'auto-draft' LIMIT 200" );
        $count = 0;

        foreach ( $ids as $id ) {
            if ( wp_delete_post( (int) $id, true ) ) {
                $count++;
            }
        }

        return $count;
    }

    private function cleanup_trash_posts(): int
    {
        global $wpdb;

        $ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'trash' LIMIT 200" );
        $count = 0;

        foreach ( $ids as $id ) {
            if ( wp_delete_post( (int) $id, true ) ) {
                $count++;
            }
        }

        return $count;
    }

    private function cleanup_spam_comments(): int
    {
        global $wpdb;

        $ids = $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved IN ('spam','trash') LIMIT 500" );
        $count = 0;

        foreach ( $ids as $id ) {
            if ( wp_delete_comment( (int) $id, true ) ) {
                $count++;
            }
        }

        return $count;
    }

    private function cleanup_transients(): int
    {
        delete_expired_transients();
        if ( function_exists( 'delete_expired_site_transients' ) ) {
            delete_expired_site_transients();
        }

        return 1;
    }

    private function cleanup_sessions(): int
    {
        global $wpdb;

        return (int) $wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'session_tokens'" );
    }

    private function optimize_tables(): int
    {
        global $wpdb;

        $tables = $wpdb->get_col( 'SHOW TABLES' );
        $count  = 0;

        foreach ( (array) $tables as $table ) {
            $wpdb->query( "OPTIMIZE TABLE {$table}" );
            $count++;
        }

        return $count;
    }
}
