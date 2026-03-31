<?php
/**
 * Runs on plugin deletion to clean up all stored data.
 *
 * @package Mailmojo
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete WP Application Password before removing the option that stores its UUID.
$app_password = get_option( 'mailmojo_application_password', array() );
if ( is_array( $app_password ) && ! empty( $app_password['user_id'] ) && ! empty( $app_password['uuid'] ) ) {
	$user_id = (int) $app_password['user_id'];
	$uuid    = (string) $app_password['uuid'];
	if ( class_exists( 'WP_Application_Passwords' ) ) {
		WP_Application_Passwords::delete_application_password( $user_id, $uuid );
	}
}

delete_option( 'mailmojo_access_token' );
delete_option( 'mailmojo_connection_status' );
delete_option( 'mailmojo_content_sync_enabled' );
delete_option( 'mailmojo_application_password' );
delete_option( 'mailmojo_application_password_status' );
delete_option( 'mailmojo_last_api_error' );
delete_option( 'mailmojo_sdk_snippet' );
delete_transient( 'mailmojo_application_password_plaintext' );
