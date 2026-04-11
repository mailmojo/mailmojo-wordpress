<?php
/**
 * Plugin Name:       Mailmojo for WordPress
 * Description:       Display Mailmojo subscribe popups on your site, add popup button blocks, and sync your WordPress posts to Mailmojo for faster newsletter creation.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      8.2
 * Author:            Mailmojo
 * Author URI:        https://mailmojo.no
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mailmojo
 *
 * @package Mailmojo
 */

// Autoload dependencies installed via Composer.
$mailmojo_autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $mailmojo_autoload ) ) {
	require_once $mailmojo_autoload;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

foreach ( array( 'class-mailmojo-api', 'class-mailmojo-sync', 'class-mailmojo-admin' ) as $mailmojo_class_file ) {
	$mailmojo_file = __DIR__ . '/includes/' . $mailmojo_class_file . '.php';
	if ( file_exists( $mailmojo_file ) ) {
		require_once $mailmojo_file;
	}
}

if ( class_exists( 'Mailmojo_Admin' ) ) {
	Mailmojo_Admin::init();
}

add_action( 'init', 'mailmojo_load_textdomain' );

function mailmojo_load_textdomain(): void {
	load_plugin_textdomain( 'mailmojo', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

/**
 * Output the Mailmojo JS SDK snippet in the public page <head>.
 */
add_action( 'wp_head', 'mailmojo_output_sdk_snippet' );

function mailmojo_output_sdk_snippet(): void {
	$snippet = get_option( 'mailmojo_sdk_snippet', '' );
	if ( is_string( $snippet ) && '' !== $snippet ) {
		// Snippet is a full <script> tag sourced from the Mailmojo API — output as-is.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "\n" . $snippet . "\n";
	}
}
/**
 * Registers the block using a `blocks-manifest.php` file, which improves the performance of block type registration.
 * Behind the scenes, it also registers all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
 */
function mailmojo_register_blocks() {
	/**
	 * Registers the block(s) metadata from the `blocks-manifest.php` and registers the block type(s)
	 * based on the registered block metadata.
	 * Added in WordPress 6.8 to simplify the block metadata registration process added in WordPress 6.7.
	 *
	 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
	 */
	if ( function_exists( 'wp_register_block_types_from_metadata_collection' ) ) {
		wp_register_block_types_from_metadata_collection( __DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php' );
		wp_set_script_translations( 'mailmojo-mailmojo-popup-button-editor-script', 'mailmojo', __DIR__ . '/languages' );
		return;
	}

	/**
	 * Registers the block(s) metadata from the `blocks-manifest.php` file.
	 * Added to WordPress 6.7 to improve the performance of block type registration.
	 *
	 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
	 */
	if ( function_exists( 'wp_register_block_metadata_collection' ) ) {
		wp_register_block_metadata_collection( __DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php' );
	}
	/**
	 * Registers the block type(s) in the `blocks-manifest.php` file.
	 *
	 * @see https://developer.wordpress.org/reference/functions/register_block_type/
	 */
	$manifest_data = require __DIR__ . '/build/blocks-manifest.php';
	foreach ( array_keys( $manifest_data ) as $block_type ) {
		register_block_type( __DIR__ . "/build/{$block_type}" );
		wp_set_script_translations( 'mailmojo-mailmojo-popup-button-editor-script', 'mailmojo', __DIR__ . '/languages' );
	}
}
add_action( 'init', 'mailmojo_register_blocks' );
