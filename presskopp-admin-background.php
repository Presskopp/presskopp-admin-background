<?php
/**
 * Plugin Name: Presskopp Admin Background
 * Description: Adds a customizable background image to the WordPress admin with live preview, overlay, color and blur.
 * Version: 1.0.1
 * Author: Presskopp
 * Author URI: https://presskopp.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: presskopp-admin-background
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class ABI_Admin_Background {

    /**
     * Option key used to store plugin settings
     *
     * @var string
     */
    private $option_name = 'abi_admin_bg_settings';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'apply_background' ) );
        add_action( 'wp_ajax_abi_save_settings', array( $this, 'ajax_save_settings' ) );
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

        register_deactivation_hook( __FILE__, array( $this, 'on_deactivate' ) );
    }

    public function load_textdomain() {
        load_plugin_textdomain(
            'presskopp-admin-background',
            false,
            dirname( plugin_basename( __FILE__ ) ) . '/languages'
        );
    }
    /**
     * Register settings page under "Settings"
     */
    public function add_settings_page() {
        add_theme_page(
            __( 'Admin Background', 'presskopp-admin-background' ),
            __( 'Admin Background', 'presskopp-admin-background' ),
            'manage_options',
            'abi-admin-background',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Enqueue scripts and styles for admin
     */
    public function enqueue_assets( $hook ) {

        $settings  = get_option( $this->option_name, array() );
        $image_id  = isset( $settings['image_id'] ) ? absint( $settings['image_id'] ) : 0;
        $image_url = $image_id ? wp_get_attachment_url( $image_id ) : '';

        // Only load JS on plugin settings page
        if ( 'appearance_page_abi-admin-background' === $hook ) {

            wp_enqueue_media();

            wp_enqueue_script(
                'abi-admin-js',
                plugin_dir_url( __FILE__ ) . 'assets/admin.js',
                array(),
                '4.2',
                true
            );

            // Pass data to JS
            wp_localize_script(
                'abi-admin-js',
                'abiData',
                array(
                    'imageUrl' => $image_url,
                    'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'abi_save' ),
                )
            );
        }

        // Register empty handle for inline styles
        wp_register_style( 'abi-admin-style', false, array(), '1.0.1' );
        wp_enqueue_style( 'abi-admin-style' );
    }

    /**
     * Render plugin settings UI
     */
    public function render_settings_page() {

        $settings = get_option( $this->option_name, array() );

        $image_id = isset( $settings['image_id'] ) ? absint( $settings['image_id'] ) : 0;
        $overlay  = isset( $settings['overlay'] ) ? floatval( $settings['overlay'] ) : 0;
        $blur     = isset( $settings['blur'] ) ? intval( $settings['blur'] ) : 0;
        $color    = isset( $settings['color'] ) ? $settings['color'] : '#000000';
        ?>

        <div class="wrap">
            <h1><?php echo esc_html__( 'Admin Background Image', 'presskopp-admin-background' ); ?></h1>

            <table class="form-table">

                <!-- Image selection -->
                <tr>
                    <th><?php echo esc_html__( 'Background Image', 'presskopp-admin-background' ); ?></th>
                    <td>
                        <input type="hidden" id="abi_image" value="<?php echo esc_attr( $image_id ); ?>">

                        <button type="button" class="button" id="abi_upload_button">
                            <?php echo esc_html__( 'Select Image', 'presskopp-admin-background' ); ?>
                        </button>

                        <button
                            type="button"
                            class="button"
                            id="abi_remove_button"
                            style="<?php echo $image_id ? '' : 'display:none;'; ?>"
                        >
                            <?php echo esc_html__( 'Remove', 'presskopp-admin-background' ); ?>
                        </button>
                    </td>
                </tr>

                <!-- Overlay + Color -->
                <tr class="abi-dependent">
                    <th><?php echo esc_html__( 'Overlay', 'presskopp-admin-background' ); ?></th>
                    <td>
                        <div class="abi-range-group">
                            <input type="range" min="0" max="0.8" step="0.05" id="abi_overlay" value="<?php echo esc_attr( $overlay ); ?>">
                            <output><?php echo esc_html( $overlay ); ?></output>
                        </div>

                        <div style="margin-top:10px;">
                            <input type="color" id="abi_color" value="<?php echo esc_attr( $color ); ?>">
                        </div>
                    </td>
                </tr>

                <!-- Blur -->
                <tr class="abi-dependent">
                    <th><?php echo esc_html__( 'Blur', 'presskopp-admin-background' ); ?></th>
                    <td>
                        <div class="abi-range-group">
                            <input type="range" min="0" max="10" step="1" id="abi_blur" value="<?php echo esc_attr( $blur ); ?>">
                            <output><?php echo esc_html( $blur ); ?></output>
                        </div>
                    </td>
                </tr>

            </table>
        </div>

        <?php
    }

    /**
     * Handle AJAX save request
     */
    public function ajax_save_settings() {

        check_ajax_referer( 'abi_save', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
        }

        $image_id = isset( $_POST['image_id'] ) ? absint( wp_unslash( $_POST['image_id'] ) ) : 0;
        $overlay  = isset( $_POST['overlay'] )  ? floatval( wp_unslash( $_POST['overlay'] ) ) : 0;
        $blur     = isset( $_POST['blur'] )     ? intval( wp_unslash( $_POST['blur'] ) ) : 0;
        $color = isset( $_POST['color'] )
            ? sanitize_hex_color( wp_unslash( $_POST['color'] ) )
            : '';

        if ( ! $color ) {
            $color = '#000000';
        }

        update_option(
            $this->option_name,
            array(
                'image_id' => $image_id,
                'overlay'  => $overlay,
                'blur'     => $blur,
                'color'    => $color,
            )
        );

        wp_send_json_success( array(
            'message' => 'Settings saved',
        ) );
    }

    /**
     * Apply background styles globally in admin
     */
    public function apply_background( $hook ) {

        // Do not apply PHP background on settings page (JS handles it)
        if ( 'appearance_page_abi-admin-background' === $hook ) {
            return;
        }

        $settings = $this->get_settings();

        $id  = absint( $settings['image_id'] );
        $url = $id ? wp_get_attachment_url( $id ) : false;

        if ( ! $url ) {
            return;
        }

        $overlay = max( 0, min( 0.8, floatval( $settings['overlay'] ) ) );
        $blur    = max( 0, min( 10, intval( $settings['blur'] ) ) );
        $color   = $settings['color'];
        $rgba    = $this->hex_to_rgba( $color, $overlay );

        wp_add_inline_style(
            'abi-admin-style',
            "
            body.wp-admin {
                background: url('" . esc_url( $url ) . "') no-repeat center center fixed;
                background-size: cover;
            }

            body.wp-admin::before {
                content: '';
                position: fixed;
                inset: 0;
                background: {$rgba};
                backdrop-filter: blur({$blur}px);
                pointer-events: none;
            }
            "
        );
    }

    /**
     * Retrieve plugin settings with defaults
     */
    private function get_settings() {
        $defaults = array(
            'image_id' => 0,
            'overlay'  => 0,
            'blur'     => 0,
            'color'    => '#000000',
        );

        return wp_parse_args(
            get_option( $this->option_name, array() ),
            $defaults
        );
    }

    /**
     * Convert HEX color to RGBA
     */
    private function hex_to_rgba( $hex, $alpha ) {

        $hex = str_replace( '#', '', $hex );

        // if invalid hex, return transparent
        if ( ! preg_match( '/^[0-9a-fA-F]{3}$|^[0-9a-fA-F]{6}$/', $hex ) ) {
            return 'rgba(0,0,0,0)';
        }

        if ( strlen( $hex ) === 3 ) {
            $r = hexdec( str_repeat( substr( $hex, 0, 1 ), 2 ) );
            $g = hexdec( str_repeat( substr( $hex, 1, 1 ), 2 ) );
            $b = hexdec( str_repeat( substr( $hex, 2, 1 ), 2 ) );
        } else {
            $r = hexdec( substr( $hex, 0, 2 ) );
            $g = hexdec( substr( $hex, 2, 2 ) );
            $b = hexdec( substr( $hex, 4, 2 ) );
        }

        return "rgba($r,$g,$b,$alpha)";
    }

    /**
     * Cleanup on plugin deactivation
     */
    public function on_deactivate() {
        delete_option( $this->option_name );
    }
}

new ABI_Admin_Background();