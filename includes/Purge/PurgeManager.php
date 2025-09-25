<?php

namespace MyProCache\Purge;

use MyProCache\Cache\API;
use MyProCache\Cache\Tagger;
use MyProCache\Options\Manager;
use MyProCache\Debug\Logger;
use function add_action;
use function do_action;
use function get_comment;
use function get_post;
use function implode;
use WP_Post;

class PurgeManager
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
        add_action( 'transition_post_status', array( $this, 'handle_post_status' ), 10, 3 );
        add_action( 'clean_post_cache', array( $this, 'handle_post_clean' ), 10, 1 );
        add_action( 'deleted_post', array( $this, 'handle_post_delete' ), 10, 1 );
        add_action( 'comment_post', array( $this, 'handle_comment' ), 10, 2 );
        add_action( 'edit_comment', array( $this, 'handle_comment' ), 10, 1 );
        add_action( 'transition_comment_status', array( $this, 'handle_comment_status' ), 10, 3 );
        add_action( 'wp_update_nav_menu', array( $this, 'handle_global_purge' ) );
        add_action( 'switch_theme', array( $this, 'handle_global_purge' ) );
    }

    public function handle_post_status( string $new_status, string $old_status, WP_Post $post ): void
    {
        if ( ! $this->options->get( 'purge_on_update', true ) ) {
            return;
        }

        if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
            return;
        }

        $this->purge_post( $post );
    }

    public function handle_post_clean( int $post_id ): void
    {
        if ( ! $this->options->get( 'purge_on_update', true ) ) {
            return;
        }

        $post = get_post( $post_id );
        if ( $post instanceof WP_Post ) {
            $this->purge_post( $post );
        }
    }

    public function handle_post_delete( int $post_id ): void
    {
        if ( ! $this->options->get( 'purge_on_update', true ) ) {
            return;
        }

        $post = get_post( $post_id );
        if ( $post instanceof WP_Post ) {
            $this->purge_post( $post );
        }
    }

    public function handle_comment( int $comment_id ): void
    {
        if ( ! $this->options->get( 'purge_on_comment', true ) ) {
            return;
        }

        $comment = get_comment( $comment_id );
        if ( $comment && $comment->comment_post_ID ) {
            $post = get_post( (int) $comment->comment_post_ID );
            if ( $post instanceof WP_Post ) {
                $this->purge_post( $post );
            }
        }
    }

    public function handle_comment_status( $new_status, $old_status, $comment ): void
    {
        if ( ! $this->options->get( 'purge_on_comment', true ) ) {
            return;
        }

        if ( $comment && $comment->comment_post_ID ) {
            $post = get_post( (int) $comment->comment_post_ID );
            if ( $post instanceof WP_Post ) {
                $this->purge_post( $post );
            }
        }
    }

    public function handle_global_purge(): void
    {
        API::purge_all();
        $this->logger->log( 'purge', 'Global purge triggered.' );
    }

    private function purge_post( WP_Post $post ): void
    {
        $tags = Tagger::tags_for_post( $post );
        API::purge_tags( $tags );
        $this->logger->log( 'purge', 'Purged tags: ' . implode( ',', $tags ) );

        if ( $this->options->get( 'purge_ccu_cdn', true ) ) {
            do_action( 'my_pro_cache_purge_cdn', $tags, $post );
        }
    }
}


