<?php
/**
 * Adds per-post cache controls via meta boxes.
 */

namespace MyProCache\Admin;

use MyProCache\Options\Manager;
use MyProCache\Support\Capabilities;
use function add_meta_box;
use function __;
use function checked;
use function current_user_can;
use function delete_post_meta;
use function esc_attr;
use function esc_html__;
use function esc_html_e;
use function get_post_meta;
use function get_post_types;
use function update_post_meta;
use function wp_nonce_field;
use function wp_unslash;
use function wp_verify_nonce;
use const DOING_AUTOSAVE;
use WP_Post;

/**
 * Handles post edit screen meta boxes for cache rules.
 */
class PostMeta
{
    private const META_SKIP = '_my_pro_cache_skip';
    private const META_TTL  = '_my_pro_cache_custom_ttl';

    private Manager $options;

    /**
     * Store options manager for reading defaults.
     *
     * @param Manager $options Options repository.
     */
    public function __construct( Manager $options )
    {
        $this->options = $options;
    }

    public function register( $post_type = null ): void
    {
        $post_types = get_post_types( array( 'public' => true ), 'names' );

        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'my-pro-cache-meta',
                __( 'My Pro Cache', 'my-pro-cache' ),
                array( $this, 'render' ),
                $post_type,
                'side'
            );
        }
    }

    public function render( WP_Post $post ): void
    {
        $skip = (bool) get_post_meta( $post->ID, self::META_SKIP, true );
        $ttl  = (int) get_post_meta( $post->ID, self::META_TTL, true );

        wp_nonce_field( 'my_pro_cache_post_meta', '_my_pro_cache_meta_nonce' );
        ?>
        <p>
            <label>
                <input type="checkbox" name="my_pro_cache_skip" value="1" <?php checked( $skip ); ?> />
                <?php esc_html_e( 'Do not cache this content', 'my-pro-cache' ); ?>
            </label>
        </p>
        <p>
            <label for="my_pro_cache_ttl"><?php esc_html_e( 'Custom TTL (seconds)', 'my-pro-cache' ); ?></label>
            <input type="number" id="my_pro_cache_ttl" name="my_pro_cache_ttl" value="<?php echo esc_attr( $ttl ); ?>" min="0" step="60" class="widefat" />
            <span class="description"><?php esc_html_e( 'Leave empty to use global defaults.', 'my-pro-cache' ); ?></span>
        </p>
        <?php
    }

    public function save( int $post_id, WP_Post $post ): void
    {
        if ( ! isset( $_POST['_my_pro_cache_meta_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['_my_pro_cache_meta_nonce'] ), 'my_pro_cache_post_meta' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( Capabilities::manage_capability() ) ) {
            return;
        }

        $skip = ! empty( $_POST['my_pro_cache_skip'] );
        update_post_meta( $post_id, self::META_SKIP, $skip ? '1' : '' );

        $ttl_value = isset( $_POST['my_pro_cache_ttl'] ) ? (int) wp_unslash( $_POST['my_pro_cache_ttl'] ) : 0;
        if ( $ttl_value > 0 ) {
            update_post_meta( $post_id, self::META_TTL, $ttl_value );
        } else {
            delete_post_meta( $post_id, self::META_TTL );
        }
    }
}


