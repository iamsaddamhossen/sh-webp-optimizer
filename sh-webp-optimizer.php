<?php
/**
 * Plugin Name:       Saddam Hossen WebP Optimizer
 * Plugin URI:        https://saddamhossen.dev
 * Description:       Advanced image performance tool. Converts new uploads to WebP and adds a "Convert to WebP" button for existing images.
 * Version:           1.1.0
 * Author:            Saddam Hossen
 * Author URI:        https://saddamhossen.dev
 * Text Domain:       sh-webp-optimizer
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SH_WebP_Optimizer {

    private $prefix = 'sh_webp_';

    public function __construct() {
        // Initialize Core Hooks
        add_action( 'admin_init', [ $this, 'sh_register_settings' ] );
        add_filter( 'wp_handle_upload', [ $this, 'sh_process_image_conversion' ] );
        add_action( 'admin_notices', [ $this, 'sh_check_imagick_status' ] );

        // Hooks for existing images (Media Library List View)
        add_filter( 'media_row_actions', [ $this, 'sh_add_convert_link' ], 10, 2 );
        add_action( 'admin_action_sh_convert_to_webp', [ $this, 'sh_handle_manual_conversion' ] );
    }

    /**
     * Requirement Check
     */
    public function sh_check_imagick_status() {
        if ( ! extension_loaded( 'imagick' ) && current_user_can( 'manage_options' ) ) {
            echo '<div class="notice notice-error"><p><strong>Saddam Hossen Optimizer:</strong> The Imagick PHP extension is missing.</p></div>';
        }
        
        // Show success message after manual conversion
        if ( isset( $_GET['sh_converted'] ) && $_GET['sh_converted'] == '1' ) {
            echo '<div class="notice notice-success is-dismissible"><p>Image successfully converted to WebP!</p></div>';
        }
    }

    /**
     * Settings Markup (Settings > Media)
     */
    public function sh_register_settings() {
        register_setting( 'media', $this->prefix . 'enabled', [ 'default' => 1 ] );
        register_setting( 'media', $this->prefix . 'quality', [ 'default' => 82 ] );
        register_setting( 'media', $this->prefix . 'max_w', [ 'default' => 1920 ] );

        add_settings_field(
            'sh_webp_field',
            'Saddam Hossen WebP Settings',
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
    <label><input type="checkbox" name="sh_webp_enabled" value="1" <?php checked(1, $enabled); ?>> <strong>Enable
            Auto-Conversion on Upload</strong></label><br><br>
    Quality: <input type="number" name="sh_webp_quality" value="<?php echo esc_attr($quality); ?>" min="1" max="100"
        style="width: 65px;"> % (Recommended: 80-85)<br><br>
    Max Width: <input type="number" name="sh_webp_max_w" value="<?php echo esc_attr($max_w); ?>" style="width: 90px;">
    px
    <p class="description">Note: To convert old images, go to the <a
            href="<?php echo admin_url('upload.php?mode=list'); ?>">Media Library List View</a> and click "Convert to
        WebP".</p>
</div>
<?php
    }

    /**
     * ADD LINK TO MEDIA LIBRARY ROWS
     */
    public function sh_add_convert_link( $actions, $post ) {
        if ( $post->post_mime_type !== 'image/webp' && extension_loaded('imagick') ) {
            $url = admin_url( 'admin.php?action=sh_convert_to_webp&attachment_id=' . $post->ID );
            $url = wp_nonce_url( $url, 'sh_convert_action' );
            $actions['sh_convert'] = '<a href="' . $url . '" style="color: #2271b1; font-weight: bold;">Convert to WebP</a>';
        }
        return $actions;
    }

    /**
     * HANDLE MANUAL CONVERSION REQUEST
     */
    public function sh_handle_manual_conversion() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'sh_convert_action' );

        $attachment_id = isset( $_GET['attachment_id'] ) ? intval( $_GET['attachment_id'] ) : 0;
        $file_path = get_attached_file( $attachment_id );

        if ( $file_path && file_exists( $file_path ) ) {
            $this->sh_core_convert_engine( $file_path, $attachment_id );
        }

        wp_redirect( admin_url( 'upload.php?sh_converted=1' ) );
        exit;
    }

    /**
     * CORE CONVERSION ENGINE (Used by both Uploads and Manual Button)
     */
    private function sh_core_convert_engine( $file_path, $attachment_id ) {
        try {
            $imagick = new Imagick( $file_path );
            
            // Fix Orientation
            $this->sh_fix_orientation( $imagick );

            // Resize
            $max_w = get_option( $this->prefix . 'max_w' );
            if ( $imagick->getImageWidth() > $max_w ) {
                $imagick->resizeImage( $max_w, 0, Imagick::FILTER_LANCZOS, 1 );
            }

            $imagick->setImageFormat( 'webp' );
            $imagick->setImageCompressionQuality( get_option( $this->prefix . 'quality' ) );

            $info = pathinfo( $file_path );
            $new_path = $info['dirname'] . '/' . $info['filename'] . '.webp';
            
            $imagick->writeImage( $new_path );

            if ( file_exists($new_path) && filesize( $new_path ) < filesize( $file_path ) ) {
                unlink( $file_path ); 
                update_attached_file( $attachment_id, $new_path );
                
                wp_update_post([
                    'ID' => $attachment_id,
                    'post_mime_type' => 'image/webp',
                    'guid' => str_replace( basename($file_path), basename($new_path), get_the_guid($attachment_id) )
                ]);
            } else {
                if(file_exists($new_path)) unlink( $new_path );
            }

            $imagick->destroy();
        } catch ( Exception $e ) {
            error_log( 'SH WebP Error: ' . $e->getMessage() );
        }
    }

    public function sh_process_image_conversion( $upload ) {
        if ( ! get_option( $this->prefix . 'enabled' ) || ! extension_loaded( 'imagick' ) ) return $upload;
        
        // We trigger the core engine after the file is moved
        add_action( 'add_attachment', function( $attachment_id ) use ( $upload ) {
            $this->sh_core_convert_engine( $upload['file'], $attachment_id );
        });

        return $upload;
    }

    private function sh_fix_orientation( $imagick ) {
        $orientation = $imagick->getImageOrientation();
        switch ( $orientation ) {
            case Imagick::ORIENTATION_BOTTOMRIGHT: $imagick->rotateImage( "#000", 180 ); break;
            case Imagick::ORIENTATION_RIGHTTOP:    $imagick->rotateImage( "#000", 90 ); break;
            case Imagick::ORIENTATION_LEFTBOTTOM:  $imagick->rotateImage( "#000", -90 ); break;
        }
        $imagick->setImageOrientation( Imagick::ORIENTATION_TOPLEFT );
    }
}

new SH_WebP_Optimizer();