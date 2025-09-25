<?php

namespace MyProCache\Cache;

use function apply_filters;
use function get_post_type;
use function get_queried_object;
use function is_archive;
use function is_author;
use function is_front_page;
use function is_home;
use function is_post_type_archive;
use function is_search;
use function is_singular;
use function is_tax;
use function is_404;
use function wp_get_post_terms;
use WP_Post;
use WP_Term;

/**
 * Generates cache tags representing the current query or a specific post.
 */
class Tagger
{
    /**
     * Builds a tag list for the active query to support fine-grained invalidation.
     */
    public static function current_tags(): array
    {
        $tags = array( 'global' );

        if ( is_front_page() ) {
            $tags[] = 'front';
        }

        if ( is_home() ) {
            $tags[] = 'home';
        }

        if ( is_search() ) {
            $tags[] = 'search';
        }

        if ( is_404() ) {
            $tags[] = '404';
        }

        if ( is_singular() ) {
            $post = get_queried_object();
            if ( $post instanceof WP_Post ) {
                $tags = array_merge( $tags, self::tags_for_post( $post ) );
            }
        }

        if ( is_post_type_archive() ) {
            $tags[] = 'type_' . get_query_var( 'post_type' );
        }

        if ( is_author() ) {
            $author = get_queried_object();
            if ( isset( $author->ID ) ) {
                $tags[] = 'author_' . (int) $author->ID;
            }
        }

        if ( is_tax() || is_archive() ) {
            $term = get_queried_object();
            if ( $term instanceof WP_Term ) {
                $tags[] = 'term_' . (int) $term->term_id;
            }
        }

        return apply_filters( 'my_pro_cache_tags', array_unique( $tags ) );
    }

    /**
     * Returns canonical tags for a post including type, terms, and author.
     */
    public static function tags_for_post( WP_Post $post ): array
    {
        $tags = array(
            'post_' . $post->ID,
            'type_' . $post->post_type,
        );

        $terms = wp_get_post_terms( $post->ID, get_object_taxonomies( $post->post_type ) );
        if ( is_array( $terms ) ) {
            foreach ( $terms as $term ) {
                if ( $term instanceof WP_Term ) {
                    $tags[] = 'term_' . $term->term_id;
                }
            }
        }

        if ( $post->post_author ) {
            $tags[] = 'author_' . $post->post_author;
        }

        return apply_filters( 'my_pro_cache_post_tags', array_unique( $tags ), $post );
    }
}


