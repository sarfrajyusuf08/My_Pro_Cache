<?php
/**
 * Registers settings sections and renders the plugin configuration forms.
 */

namespace MyProCache\Admin;

use MyProCache\Options\Defaults;
use MyProCache\Options\Manager;
use MyProCache\Options\Schema;
use MyProCache\Support\Capabilities;
use function add_settings_field;
use function add_settings_section;
use function esc_attr;
use function esc_html;
use function esc_textarea;
use function register_setting;
use function current_user_can;
use function is_array;
use function implode;
use function wp_kses_post;
use function wp_strip_all_tags;

/**
 * Coordinates WordPress settings API registration for My Pro Cache tabs.
 */
class SettingsController
{
    private Manager $options;

    /**
     * Store the options manager for later use.
     *
     * @param Manager $options Options repository.
     */
    public function __construct( Manager $options )
    {
        $this->options = $options;
    }

    /**
     * Register settings sections and fields on admin_init.
     */
    public function register(): void
    {
        register_setting(
            'my_pro_cache_options_group',
            Manager::OPTION_KEY,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize' ),
                'default'           => Defaults::all(),
            )
        );

        foreach ( Schema::pages() as $slug => $page ) {
            if ( empty( $page['sections'] ) ) {
                continue;
            }

            foreach ( $page['sections'] as $section_id => $section ) {
                add_settings_section(
                    'my_pro_cache_' . $slug . '_' . $section_id,
                    $section['title'] ?? '',
                    function() use ( $section ) {
                        if ( ! empty( $section['description'] ) ) {
                            echo '<p class="description">' . wp_kses_post( $section['description'] ) . '</p>';
                        }
                    },
                    'my-pro-cache-' . $slug
                );

                if ( empty( $section['fields'] ) ) {
                    continue;
                }

                foreach ( $section['fields'] as $field_key => $field ) {
                    add_settings_field(
                        $field_key,
                        $field['label'] ?? esc_html( $field_key ),
                        array( $this, 'render_field' ),
                        'my-pro-cache-' . $slug,
                        'my_pro_cache_' . $slug . '_' . $section_id,
                        array(
                            'key'   => $field_key,
                            'field' => $field,
                        )
                    );
                }
            }
        }
    }

    /**
     * Sanitize option payload coming from the settings form submission.
     *
     * @param mixed $input Raw values from $_POST.
     * @return array Sanitized options ready for storage.
     */
    public function sanitize( $input )
    {
        if ( ! current_user_can( Capabilities::manage_capability() ) ) {
            return $this->options->all();
        }

        if ( ! is_array( $input ) ) {
            $input = array();
        }

        return $this->options->sanitize( $input );
    }

    /**
     * Render individual field controls for the settings UI.
     *
     * @param array $args Field configuration passed from Settings API.
     */
    public function render_field( array $args ): void
    {
        $key        = $args['key'];
        $field      = $args['field'];
        $value      = $this->options->get( $key, Defaults::all()[ $key ] ?? '' );
        $type       = $field['type'] ?? 'text';
        $tooltip    = $field['tooltip'] ?? '';
        $readonly   = ! empty( $field['readonly'] );
        $input_name = Manager::OPTION_KEY . '[' . esc_attr( $key ) . ']';
        $description = $field['description'] ?? '';

        echo '<div class="mpc-field mpc-field-type-' . esc_attr( $type ) . '">';

        if ( 'checkbox' === $type ) {
            if ( ! $readonly ) {
                echo '<input type="hidden" name="' . $input_name . '" value="0" />';
            }
            $checked  = ! empty( $value ) ? 'checked="checked"' : '';
            $disabled = $readonly ? 'disabled' : '';
            echo '<label><input type="checkbox" name="' . $input_name . '" value="1" ' . $checked . ' ' . $disabled . ' /> ' . esc_html( $field['label'] ?? '' ) . '</label>';
        } elseif ( 'select' === $type ) {
            echo '<select name="' . $input_name . '" ' . ( $readonly ? 'disabled' : '' ) . '>';
            foreach ( (array) ( $field['options'] ?? array() ) as $option_key => $option_label ) {
                $selected = (string) $option_key === (string) $value ? 'selected="selected"' : '';
                echo '<option value="' . esc_attr( $option_key ) . '" ' . $selected . '>' . esc_html( $option_label ) . '</option>';
            }
            echo '</select>';
        } elseif ( 'textarea' === $type ) {
            if ( is_array( $value ) ) {
                $value = implode( "\n", $value );
            }
            echo '<textarea name="' . $input_name . '" rows="5" cols="60" ' . ( $readonly ? 'readonly' : '' ) . '>' . esc_textarea( (string) $value ) . '</textarea>';
        } elseif ( 'number' === $type ) {
            $min  = isset( $field['min'] ) ? ' min="' . esc_attr( $field['min'] ) . '"' : '';
            $step = isset( $field['step'] ) ? ' step="' . esc_attr( $field['step'] ) . '"' : '';
            echo '<input type="number" name="' . $input_name . '" value="' . esc_attr( (string) $value ) . '"' . $min . $step . ( $readonly ? ' readonly' : '' ) . ' />';
        } elseif ( 'password' === $type ) {
            echo '<input type="password" name="' . $input_name . '" value="' . esc_attr( (string) $value ) . '" autocomplete="off" ' . ( $readonly ? 'readonly' : '' ) . ' />';
        } else {
            echo '<input type="text" name="' . $input_name . '" value="' . esc_attr( (string) $value ) . '" ' . ( $readonly ? 'readonly' : '' ) . ' placeholder="' . esc_attr( $field['placeholder'] ?? '' ) . '" />';
        }

        if ( $tooltip ) {
            echo '<p class="description mpc-field-help">' . esc_html( $tooltip ) . '</p>';
        }

        if ( $description ) {
            echo '<p class="description">' . wp_kses_post( $description ) . '</p>';
        }

        echo '</div>';
    }
}


