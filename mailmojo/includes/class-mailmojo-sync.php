<?php
/**
 * Mailmojo content sync manager.
 *
 * Manages the WP Application Password lifecycle and keeps Mailmojo in sync
 * with this site's connection credentials.
 *
 * @package Mailmojo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mailmojo_Sync {

	private const APP_PASSWORD_OPTION        = 'mailmojo_application_password';
	private const APP_PASSWORD_STATUS_OPTION = 'mailmojo_application_password_status';
	private const APP_PASSWORD_TRANSIENT     = 'mailmojo_application_password_plaintext';
	private const APP_PASSWORD_NAME          = 'Mailmojo';
	private const INTEGRATION_ID             = 'wordpress';
	private const SYNC_ENABLED_OPTION        = 'mailmojo_content_sync_enabled';

	private Mailmojo_Api $api;

	public function __construct( Mailmojo_Api $api ) {
		$this->api = $api;
	}

	public function is_content_sync_enabled(): bool {
		$enabled = get_option( self::SYNC_ENABLED_OPTION, '0' );
		return '1' === $enabled || true === $enabled;
	}

	/**
	 * Ensure a WP Application Password exists for the sync connection.
	 * Creates one if missing, or deletes and recreates when $force_regenerate is true.
	 * Calls sync_integration_status() internally after creating a fresh password.
	 */
	public function ensure_application_password( bool $force_regenerate = false ): void {
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			$this->set_application_password_status( 'not_available', __( 'Sync connection is not available on this WordPress version.', 'mailmojo' ) );
			return;
		}

		$user_id = $this->get_application_password_user_id();
		if ( 0 === $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( 0 === $user_id ) {
			$this->set_application_password_status( 'error', __( 'Unable to determine a user for the sync connection.', 'mailmojo' ) );
			return;
		}

		if ( $force_regenerate ) {
			$this->delete_existing_application_password( $user_id );
		}

		$existing_password = $this->get_existing_application_password( $user_id );
		if ( $existing_password ) {
			$this->store_application_password( $user_id, $existing_password );
			$this->set_application_password_status_if_needed();
			return;
		}

		$result = WP_Application_Passwords::create_new_application_password(
			$user_id,
			array(
				'name' => self::APP_PASSWORD_NAME,
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->set_application_password_status( 'error', __( 'Unable to create sync connection.', 'mailmojo' ) );
			return;
		}

		if ( ! is_array( $result ) || 2 !== count( $result ) ) {
			$this->set_application_password_status( 'error', __( 'Sync connection setup failed.', 'mailmojo' ) );
			return;
		}

		list( $password, $password_item ) = $result;

		if ( ! is_string( $password ) || '' === $password ) {
			$this->set_application_password_status( 'error', __( 'Sync connection setup failed.', 'mailmojo' ) );
			return;
		}

		if ( ! is_array( $password_item ) || empty( $password_item['uuid'] ) ) {
			$this->set_application_password_status( 'error', __( 'Sync connection setup failed.', 'mailmojo' ) );
			return;
		}

		$this->store_application_password( $user_id, $password_item );
		$this->store_application_password_plaintext( $password );

		$token = $this->get_access_token();
		if ( '' !== $token ) {
			$this->sync_integration_status( $token );
		}
	}

	/**
	 * Push or verify the sync connection credentials with Mailmojo.
	 * The caller is responsible for checking is_content_sync_enabled() first.
	 */
	public function sync_integration_status( string $token ): void {
		$app_password = $this->get_application_password();

		if ( empty( $app_password['uuid'] ) ) {
			$this->set_application_password_status( 'not_created', __( 'Sync connection has not been created yet.', 'mailmojo' ) );
			return;
		}

		$user_id = (int) $app_password['user_id'];
		if ( 0 === $user_id ) {
			$this->set_application_password_status( 'error', __( 'Unable to determine sync connection user.', 'mailmojo' ) );
			return;
		}

		$password = $this->get_application_password_plaintext();
		if ( '' !== $password ) {
			$this->update_integration_settings( $token, $password, $user_id );
			return;
		}

		$this->verify_integration_settings( $token, $app_password );
	}

	public function get_application_password(): array {
		$app_password = get_option( self::APP_PASSWORD_OPTION, array() );
		if ( ! is_array( $app_password ) ) {
			$app_password = array();
		}

		return wp_parse_args(
			$app_password,
			array(
				'user_id'    => 0,
				'uuid'       => '',
				'name'       => self::APP_PASSWORD_NAME,
				'username'   => '',
				'token_hash' => '',
				'created_at' => 0,
			)
		);
	}

	public function get_application_password_status(): array {
		$status = get_option( self::APP_PASSWORD_STATUS_OPTION, array() );
		if ( ! is_array( $status ) ) {
			$status = array();
		}

		return wp_parse_args(
			$status,
			array(
				'state'      => 'not_created',
				'message'    => '',
				'updated_at' => 0,
			)
		);
	}

	public function set_application_password_status( string $state, string $message ): void {
		update_option(
			self::APP_PASSWORD_STATUS_OPTION,
			array(
				'state'      => $state,
				'message'    => $message,
				'updated_at' => time(),
			),
			false
		);
	}

	/**
	 * Delete the WP Application Password and all options owned by this class.
	 */
	public function delete_all_data(): void {
		$app_password = $this->get_application_password();
		$user_id      = (int) $app_password['user_id'];
		$uuid         = (string) $app_password['uuid'];

		if ( $user_id > 0 && '' !== $uuid && class_exists( 'WP_Application_Passwords' ) ) {
			WP_Application_Passwords::delete_application_password( $user_id, $uuid );
		}

		delete_option( self::APP_PASSWORD_OPTION );
		delete_option( self::APP_PASSWORD_STATUS_OPTION );
		delete_option( self::SYNC_ENABLED_OPTION );
		delete_transient( self::APP_PASSWORD_TRANSIENT );
	}

	private function get_access_token(): string {
		$token = get_option( 'mailmojo_access_token', '' );
		return is_string( $token ) ? $token : '';
	}

	private function get_existing_application_password( int $user_id ): ?array {
		$passwords = WP_Application_Passwords::get_user_application_passwords( $user_id );
		foreach ( $passwords as $password ) {
			if ( self::APP_PASSWORD_NAME === $password['name'] ) {
				return $password;
			}
		}

		return null;
	}

	private function delete_existing_application_password( int $user_id ): void {
		$existing = $this->get_existing_application_password( $user_id );
		if ( ! $existing ) {
			return;
		}

		WP_Application_Passwords::delete_application_password( $user_id, $existing['uuid'] );
	}

	private function store_application_password( int $user_id, array $password_item ): void {
		update_option(
			self::APP_PASSWORD_OPTION,
			array(
				'user_id'    => $user_id,
				'uuid'       => $password_item['uuid'] ?? '',
				'name'       => $password_item['name'] ?? self::APP_PASSWORD_NAME,
				'created_at' => isset( $password_item['created'] ) ? (int) $password_item['created'] : time(),
			),
			false
		);
	}

	private function store_application_password_plaintext( string $password ): void {
		set_transient( self::APP_PASSWORD_TRANSIENT, $password, MINUTE_IN_SECONDS * 10 );
	}

	private function get_application_password_plaintext(): string {
		$password = get_transient( self::APP_PASSWORD_TRANSIENT );
		if ( is_string( $password ) ) {
			delete_transient( self::APP_PASSWORD_TRANSIENT );
			return $password;
		}

		return '';
	}

	private function get_application_password_user_id(): int {
		$password = $this->get_application_password();
		return isset( $password['user_id'] ) ? (int) $password['user_id'] : 0;
	}

	private function update_application_password_metadata( int $user_id, string $username, string $password ): void {
		$app_password              = $this->get_application_password();
		$app_password['user_id']   = $user_id;
		$app_password['username']  = $username;
		$app_password['token_hash'] = $this->hash_application_password( $password );

		update_option( self::APP_PASSWORD_OPTION, $app_password, false );
	}

	private function set_application_password_status_if_needed(): void {
		$status = $this->get_application_password_status();
		if ( 'sent' === $status['state'] ) {
			return;
		}

		$app_password = $this->get_application_password();
		if ( empty( $app_password['token_hash'] ) || empty( $app_password['username'] ) ) {
			$this->set_application_password_status( 'needs_refresh', __( 'Sync connection needs to be refreshed.', 'mailmojo' ) );
			return;
		}

		$this->set_application_password_status( 'pending', __( 'Sync connection is ready to update in Mailmojo.', 'mailmojo' ) );
	}

	private function update_integration_settings( string $token, string $password, int $user_id ): void {
		$username = $this->get_username_for_user_id( $user_id );
		if ( '' === $username ) {
			$this->set_application_password_status( 'error', __( 'Unable to determine sync connection user.', 'mailmojo' ) );
			return;
		}

		$settings = array(
			'site_url' => site_url(),
			'token'    => $password,
			'username' => $username,
		);

		try {
			$this->api->update_integration( $token, self::INTEGRATION_ID, (object) $settings );
			$this->update_application_password_metadata( $user_id, $username, $password );
			$this->api->clear_last_api_error();
			$this->set_application_password_status( 'sent', __( 'Mailmojo has the latest sync connection.', 'mailmojo' ) );
		} catch ( Throwable $error ) {
			$api_message = $this->api->format_error( $error );
			$this->api->store_last_api_error( $api_message );
			$this->set_application_password_status(
				'error',
				sprintf(
					/* translators: %s is an API error summary. */
					__( 'Unable to update Mailmojo sync connection. %s', 'mailmojo' ),
					$api_message
				)
			);
		}
	}

	private function verify_integration_settings( string $token, array $app_password ): void {
		if ( empty( $app_password['token_hash'] ) || empty( $app_password['username'] ) ) {
			$this->set_application_password_status( 'needs_refresh', __( 'Sync connection needs to be refreshed.', 'mailmojo' ) );
			return;
		}

		try {
			$integrations = $this->api->get_integrations( $token );
		} catch ( Throwable $error ) {
			$api_message = $this->api->format_error( $error );
			$this->api->store_last_api_error( $api_message );
			$this->set_application_password_status(
				'error',
				sprintf(
					/* translators: %s is an API error summary. */
					__( 'Unable to verify sync connection with Mailmojo. %s', 'mailmojo' ),
					$api_message
				)
			);
			return;
		}

		$settings = $this->extract_wordpress_integration_settings( $integrations );
		if ( null === $settings ) {
			$this->set_application_password_status( 'pending', __( 'Sync connection has not been stored in Mailmojo yet.', 'mailmojo' ) );
			return;
		}

		$remote_username = isset( $settings['username'] ) ? (string) $settings['username'] : '';
		$remote_token    = isset( $settings['token'] ) ? (string) $settings['token'] : '';
		$remote_site_url = isset( $settings['site_url'] ) ? (string) $settings['site_url'] : '';

		$matches = (
			$remote_username === (string) $app_password['username']
			&& '' !== $remote_token
			&& $this->hash_application_password( $remote_token ) === (string) $app_password['token_hash']
			&& $remote_site_url === site_url()
		);

		if ( $matches ) {
			$this->api->clear_last_api_error();
			$this->set_application_password_status( 'sent', __( 'Mailmojo has the latest sync connection.', 'mailmojo' ) );
			return;
		}

		$this->set_application_password_status( 'needs_refresh', __( 'Mailmojo has different sync connection settings.', 'mailmojo' ) );
	}

	private function extract_wordpress_integration_settings( array $integrations ): ?array {
		foreach ( $integrations as $integration ) {
			$integration_id = '';
			$data           = null;

			if ( is_object( $integration ) && method_exists( $integration, 'getIntegrationId' ) ) {
				$integration_id = (string) $integration->getIntegrationId();
				if ( method_exists( $integration, 'getData' ) ) {
					$data = $integration->getData();
				}
			} elseif ( is_object( $integration ) ) {
				$integration_id = isset( $integration->integration_id ) ? (string) $integration->integration_id : '';
				$data           = $integration->data ?? null;
			} elseif ( is_array( $integration ) ) {
				$integration_id = isset( $integration['integration_id'] ) ? (string) $integration['integration_id'] : '';
				$data           = $integration['data'] ?? null;
			}

			if ( self::INTEGRATION_ID !== $integration_id ) {
				continue;
			}

			if ( is_string( $data ) && '' !== $data ) {
				$decoded = json_decode( $data, true );
				if ( is_array( $decoded ) ) {
					$data = $decoded;
				}
			}

			if ( is_object( $data ) ) {
				$data = (array) $data;
			}

			if ( is_array( $data ) ) {
				if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
					return $data['settings'];
				}
				if ( isset( $data['settings'] ) && is_object( $data['settings'] ) ) {
					return (array) $data['settings'];
				}

				return $data;
			}
		}

		return null;
	}

	private function get_username_for_user_id( int $user_id ): string {
		$user = get_userdata( $user_id );
		if ( ! $user || empty( $user->user_login ) ) {
			return '';
		}

		return (string) $user->user_login;
	}

	private function hash_application_password( string $password ): string {
		return hash( 'sha256', $password );
	}
}
