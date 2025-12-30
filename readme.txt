=== Saddam Hossen WebP Optimizer ===
Contributors: saddamhossen
Tags: webp, performance, image-optimization, speed, compression
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

High-performance, zero-cost, native image optimization. Converts uploads to WebP and resizes for maximum speed.

== Description ==

Developed by **Saddam Hossen**, this plugin is a lightweight, high-performance utility designed to bridge the gap between heavy, subscription-based optimization plugins and manual coding. 

Unlike many other optimizers, this plugin runs **locally on your server**. No API keys are required, and your images are never sent to a third-party server, ensuring 100% privacy and no monthly costs.

**Key Features:**
* **Automatic WebP Conversion**: Automatically converts JPEG, PNG, and GIF to WebP format upon upload.
* **Smart Resizing**: Automatically scales down massive images to a custom maximum width (e.g., 1920px) to save disk space.
* **Manual Conversion Tool**: Adds a "Convert to WebP" action in the Media Library (List View) to optimize existing images one-by-one.
* **EXIF Orientation Correction**: Automatically rotates mobile-uploaded photos based on metadata.
* **Smart Fallback**: The plugin only replaces the original file if the WebP version is actually smaller in size.

== Installation ==

1. Upload the `sh-webp-optimizer` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Configure your desired quality and maximum width under **Settings > Media**.

== Screenshots ==

1. The settings panel integrated into the WordPress Media Settings.
2. The manual "Convert to WebP" link in the Media Library list view.

== Changelog ==

= 1.1.0 =
* Added: Manual conversion button for existing media library items.
* Added: Security nonces and permission checks for manual actions.
* Improved: Conversion engine now handles attachment metadata more efficiently.

= 1.0.0 =
* Initial release. Core WebP conversion and auto-resizing via PHP Imagick.

== Frequently Asked Questions ==

= Does this require a subscription? =
No. This plugin uses your server's native PHP Imagick library. There are no limits and no fees.

= What happens to my original images? =
To save space, the plugin replaces the original JPG/PNG with the WebP version if the WebP file is smaller. 

= Why don't I see the "Convert" link? =
Make sure you are viewing your Media Library in **List View** rather than the Grid View.