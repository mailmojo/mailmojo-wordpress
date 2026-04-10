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
$mailmojo_app_password = get_option( 'mailmojo_application_password', array() );
if ( is_array( $mailmojo_app_password ) && ! empty( $mailmojo_app_password['user_id'] ) && ! empty( $mailmojo_app_password['uuid'] ) ) {
	$mailmojo_user_id = (int) $mailmojo_app_password['user_id'];
	$mailmojo_uuid    = (string) $mailmojo_app_password['uuid'];
	if ( class_exists( 'WP_Application_Passwords' ) ) {
		WP_Application_Passwords::delete_application_password( $mailmojo_user_id, $mailmojo_uuid );
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
