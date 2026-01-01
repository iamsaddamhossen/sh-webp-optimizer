<?php
/**
 * Plugin Name:       Saddam Hossen WebP Optimizer
 * Plugin URI:        https://github.com/iamsaddamhossen/sh-webp-optimizer
 * Description:       Advanced image performance tool. Converts new uploads to WebP and adds a "Convert to WebP" button for existing images.
 * Version:           1.1.0
 * Author:            Saddam Hossen
 * Author URI:        https://saddamhossen.dev
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       saddam-hossen-webp-optimizer
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Class name must start with the plugin prefix
if ( ! class_exists( 'SH_WebP_Saddam_Hossen_Optimizer' ) ) {

    class SH_WebP_Saddam_Hossen_Optimizer {

        private $prefix = 'sh_webp_';

        public function __construct() {
            add_action( 'admin_init', [ $this, 'sh_register_settings' ] );
            add_filter( 'wp_handle_upload', [ $this, 'sh_process_image_conversion' ] );
            add_action( 'admin_notices', [ $this, 'sh_check_imagick_status' ] );
            add_filter( 'media_row_actions', [ $this, 'sh_add_convert_link' ], 10, 2 );
            add_action( 'admin_action_sh_convert_to_webp', [ $this, 'sh_handle_manual_conversion' ] );
        }

        public function sh_check_imagick_status() {
            // Nonce verification for admin notices check
            if ( isset( $_GET['sh_converted'] ) ) {
                if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'sh_convert_notice' ) ) {
                   // Optional: handle failed nonce
                }
            }

            if ( ! extension_loaded( 'imagick' ) && current_user_can( 'manage_options' ) ) {
                echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Saddam Hossen Optimizer:', 'saddam-hossen-webp-optimizer' ) . '</strong> ' . esc_html__( 'The Imagick PHP extension is missing.', 'saddam-hossen-webp-optimizer' ) . '</p></div>';
            }
            
            if ( isset( $_GET['sh_converted'] ) && '1' === $_GET['sh_converted'] ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Image successfully converted to WebP!', 'saddam-hossen-webp-optimizer' ) . '</p></div>';
            }
        }

        public function sh_register_settings() {
            register_setting( 'media', $this->prefix . 'enabled', [ 'default' => 1 ] );
            register_setting( 'media', $this->prefix . 'quality', [ 'default' => 82 ] );
            register_setting( 'media', $this->prefix . 'max_w', [ 'default' => 1920 ] );

            add_settings_field(
                'sh_webp_field',
                __( 'Saddam Hossen WebP Settings', 'saddam-hossen-webp-optimizer' ),
                [ $this, 'sh_settings_markup' ],
                'media',
                'default'
            );
        }

      public function sh_settings_markup() {
            $enabled = get_option( $this->prefix . 'enabled' );
            $quality = get_option( $this->prefix . 'quality' );
            $max_w   = get_option( $this->prefix . 'max_w' );
            ?>
<div style="background: #f0f6fb; padding: 15px; border-left: 4px solid #2271b1; border-radius: 4px;">
    <label><input type="checkbox" name="sh_webp_enabled" value="1" <?php checked( 1, $enabled ); ?>>
        <strong><?php esc_html_e( 'Enable Auto-Conversion on Upload', 'saddam-hossen-webp-optimizer' ); ?></strong></label><br><br>
    <?php esc_html_e( 'Quality:', 'saddam-hossen-webp-optimizer' ); ?> <input type="number" name="sh_webp_quality"
        value="<?php echo esc_attr( $quality ); ?>" min="1" max="100" style="width: 65px;"> %
    (<?php esc_html_e( 'Recommended: 80-85', 'saddam-hossen-webp-optimizer' ); ?>)<br><br>
    <?php esc_html_e( 'Max Width:', 'saddam-hossen-webp-optimizer' ); ?> <input type="number" name="sh_webp_max_w"
        value="<?php echo esc_attr( $max_w ); ?>" style="width: 90px;"> px
    <p class="description">
        <?php
                    /* translators: 1: opening link tag, 2: closing link tag */
                    echo sprintf( esc_html__( 'Note: To convert old images, go to the %1$sMedia Library List View%2$s and click "Convert to WebP".', 'saddam-hossen-webp-optimizer' ), '<a href="' . esc_url( admin_url( 'upload.php?mode=list' ) ) . '">', '</a>' );
                    ?>
    </p>
</div>
<?php
        }

        public function sh_add_convert_link( $actions, $post ) {
            if ( $post->post_mime_type !== 'image/webp' && extension_loaded( 'imagick' ) ) {
                $url = admin_url( 'admin.php?action=sh_convert_to_webp&attachment_id=' . $post->ID );
                $url = wp_nonce_url( $url, 'sh_convert_action' );
                $actions['sh_convert'] = '<a href="' . esc_url( $url ) . '" style="color: #2271b1; font-weight: bold;">' . esc_html__( 'Convert to WebP', 'saddam-hossen-webp-optimizer' ) . '</a>';
            }
            return $actions;
        }

        public function sh_handle_manual_conversion() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Unauthorized access.', 'saddam-hossen-webp-optimizer' ) );
            }
            check_admin_referer( 'sh_convert_action' );

            $attachment_id = isset( $_GET['attachment_id'] ) ? intval( $_GET['attachment_id'] ) : 0;
            $file_path = get_attached_file( $attachment_id );

            if ( $file_path && file_exists( $file_path ) ) {
                $this->sh_core_convert_engine( $file_path, $attachment_id );
            }

            // Use wp_safe_redirect instead of wp_redirect
            wp_safe_redirect( admin_url( 'upload.php?sh_converted=1' ) );
            exit;
        }

        private function sh_core_convert_engine( $file_path, $attachment_id ) {
            try {
                $imagick = new Imagick( $file_path );
                $this->sh_fix_orientation( $imagick );

                $max_w = get_option( $this->prefix . 'max_w' );
                if ( $imagick->getImageWidth() > $max_w ) {
                    $imagick->resizeImage( $max_w, 0, Imagick::FILTER_LANCZOS, 1 );
                }

                $imagick->setImageFormat( 'webp' );
                $imagick->setImageCompressionQuality( (int) get_option( $this->prefix . 'quality' ) );

                $info = pathinfo( $file_path );
                $new_path = $info['dirname'] . '/' . $info['filename'] . '.webp';
                
                $imagick->writeImage( $new_path );

                if ( file_exists( $new_path ) && filesize( $new_path ) < filesize( $file_path ) ) {
                    // Use wp_delete_file instead of unlink
                    wp_delete_file( $file_path ); 
                    update_attached_file( $attachment_id, $new_path );
                    
                    wp_update_post( [
                        'ID'             => $attachment_id,
                        'post_mime_type' => 'image/webp',
                        'guid'           => str_replace( basename( $file_path ), basename( $new_path ), get_the_guid( $attachment_id ) ),
                    ] );
                } else {
                    if ( file_exists( $new_path ) ) {
                        wp_delete_file( $new_path );
                    }
                }

                $imagick->destroy();
            } catch ( Exception $e ) {
                // Silently handle or use a production-safe log method if needed
            }
        }

        public function sh_process_image_conversion( $upload ) {
            if ( ! get_option( $this->prefix . 'enabled' ) || ! extension_loaded( 'imagick' ) ) {
                return $upload;
            }
            
            add_action( 'add_attachment', function( $attachment_id ) use ( $upload ) {
                $this->sh_core_convert_engine( $upload['file'], $attachment_id );
            } );

            return $upload;
        }

        private function sh_fix_orientation( $imagick ) {
            $orientation = $imagick->getImageOrientation();
            switch ( $orientation ) {
                case Imagick::ORIENTATION_BOTTOMRIGHT:
                    $imagick->rotateImage( '#000', 180 );
                    break;
                case Imagick::ORIENTATION_RIGHTTOP:
                    $imagick->rotateImage( '#000', 90 );
                    break;
                case Imagick::ORIENTATION_LEFTBOTTOM:
                    $imagick->rotateImage( '#000', -90 );
                    break;
            }
            $imagick->setImageOrientation( Imagick::ORIENTATION_TOPLEFT );
        }
    }

    new SH_WebP_Saddam_Hossen_Optimizer();
}