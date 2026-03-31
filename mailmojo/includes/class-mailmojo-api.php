<?php
/**
 * Mailmojo API client.
 *
 * Handles all HTTP communication with the Mailmojo API, including the PHP SDK
 * wrapper calls and direct Guzzle requests for endpoints not yet in the SDK.
 *
 * @package Mailmojo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use GuzzleHttp\Client;

class Mailmojo_Api {

	private const SDK_SNIPPET_OPTION    = 'mailmojo_sdk_snippet';
	private const LAST_API_ERROR_OPTION = 'mailmojo_last_api_error';

	/**
	 * Test the connection by fetching the account's mailing lists.
	 *
	 * @throws RuntimeException If the SDK classes are unavailable.
	 * @throws \MailMojo\ApiException On API error.
	 */
	public function test_connection( string $token ): void {
		if ( ! class_exists( 'MailMojo\\Configuration' ) || ! class_exists( 'MailMojo\\Api\\ListApi' ) ) {
			throw new RuntimeException( __( 'Mailmojo SDK is not available.', 'mailmojo' ) );
		}

		$lists_api = new MailMojo\Api\ListApi( $this->get_http_client(), $this->get_sdk_config( $token ) );
		$lists_api->getLists();
	}

	/**
	 * Fetch and store the JS SDK snippet for the account.
	 *
	 * Returns true when a snippet was retrieved and saved. On failure, stores a
	 * non-sensitive notice that can be surfaced in the admin UI.
	 */
	public function fetch_sdk_snippet( string $token ): bool {
		try {
			$response = $this->get_http_client()->request(
				'GET',
				rtrim( $this->get_api_host(), '/' ) . '/v1/accounts/sdk/',
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $token,
						'Accept'        => 'application/json',
					),
				)
			);

			$body    = json_decode( (string) $response->getBody(), true );
			$snippet = is_array( $body ) && isset( $body['sdk_snippet'] ) ? (string) $body['sdk_snippet'] : '';

			if ( '' !== $snippet ) {
				update_option( self::SDK_SNIPPET_OPTION, $snippet, false );
				$this->clear_last_api_error();
				return true;
			}

			$this->store_last_api_error( __( 'Mailmojo did not return an SDK snippet for this account.', 'mailmojo' ) );
		} catch ( Throwable $e ) {
			// Non-fatal — the rest of the save flow continues even if this fails.
			$this->store_last_api_error( __( 'Mailmojo SDK snippet could not be loaded. Please verify the connection and try again.', 'mailmojo' ) );
		}

		return false;
	}

	/**
	 * Update an integration's settings in Mailmojo.
	 *
	 * @throws RuntimeException If the SDK classes are unavailable.
	 * @throws \MailMojo\ApiException On API error.
	 */
	public function update_integration( string $token, string $integration_id, object $settings ): void {
		$this->get_account_api( $token )->updateAccountIntegration( $integration_id, $settings );
	}

	/**
	 * Retrieve all integrations for the account as a normalised array.
	 *
	 * @throws RuntimeException If the SDK classes are unavailable.
	 * @throws \MailMojo\ApiException On API error.
	 */
	public function get_integrations( string $token ): array {
		return $this->normalize_integration_list(
			$this->get_account_api( $token )->getAccountIntegrations()
		);
	}

	/**
	 * Retrieve all published popup forms for the account.
	 *
	 * @throws RuntimeException If the request cannot be completed.
	 */
	public function get_published_popups( string $token ): array {
		$response = $this->get_http_client()->request(
			'GET',
			rtrim( $this->get_api_host(), '/' ) . '/v1/forms/',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/json',
				),
				'query'   => array(
					'is_published' => 'true',
				),
			)
		);

		$body  = json_decode( (string) $response->getBody(), true );
		$forms = $this->normalize_form_list( $body );
		$popups = array();

		foreach ( $forms as $form ) {
			if ( ! is_array( $form ) ) {
				continue;
			}

			$type = isset( $form['type'] ) ? (string) $form['type'] : '';
			if ( 'subscribe_popup' !== $type ) {
				continue;
			}

			$id         = isset( $form['id'] ) ? (int) $form['id'] : 0;
			$name       = isset( $form['name'] ) ? trim( (string) $form['name'] ) : '';
			$public_url = isset( $form['public_url'] ) ? trim( (string) $form['public_url'] ) : '';

			if ( $id <= 0 || '' === $name || '' === $public_url ) {
				continue;
			}

			$popups[] = array(
				'id'          => $id,
				'name'        => $name,
				'public_url'  => $public_url,
				'published_at' => isset( $form['published_at'] ) ? (string) $form['published_at'] : '',
			);
		}

		return $popups;
	}

	/**
	 * Format a Throwable into a human-readable API error message string.
	 */
	public function format_error( Throwable $error ): string {
		$code    = (int) $error->getCode();
		$message = trim( wp_strip_all_tags( $error->getMessage() ) );
		$message = preg_replace( '/\s+/', ' ', $message );
		$details = '';

		if ( class_exists( 'MailMojo\\ApiException' ) && $error instanceof MailMojo\ApiException ) {
			$response_body = $error->getResponseBody();
			if ( is_string( $response_body ) && '' !== $response_body ) {
				$details = $this->extract_api_error_details( $response_body );
			} elseif ( is_object( $response_body ) || is_array( $response_body ) ) {
				$details = $this->extract_api_error_details( wp_json_encode( $response_body ) );
			}
		}

		if ( '' !== $details ) {
			$message = trim( $message . ' ' . $details );
		}

		if ( '' === $message ) {
			return sprintf( __( 'API error (%d).', 'mailmojo' ), $code );
		}

		if ( 0 !== $code ) {
			return sprintf( __( 'API error (%d): %s', 'mailmojo' ), $code, $message );
		}

		return sprintf( __( 'API error: %s', 'mailmojo' ), $message );
	}

	public function store_last_api_error( string $message ): void {
		update_option(
			self::LAST_API_ERROR_OPTION,
			array(
				'message'    => $message,
				'updated_at' => time(),
			),
			false
		);
	}

	public function clear_last_api_error(): void {
		delete_option( self::LAST_API_ERROR_OPTION );
	}

	public function get_last_api_error_notice(): ?string {
		$last_error = get_option( self::LAST_API_ERROR_OPTION, array() );
		if ( ! is_array( $last_error ) ) {
			return null;
		}

		$message    = isset( $last_error['message'] ) ? (string) $last_error['message'] : '';
		$updated_at = isset( $last_error['updated_at'] ) ? (int) $last_error['updated_at'] : 0;

		if ( '' === $message || 0 === $updated_at ) {
			return null;
		}

		if ( time() - $updated_at > 15 * MINUTE_IN_SECONDS ) {
			return null;
		}

		return $message;
	}

	/**
	 * Check if the SDK snippet needs to be fetched (i.e. not yet stored).
	 */
	public function has_sdk_snippet(): bool {
		$snippet = get_option( self::SDK_SNIPPET_OPTION, '' );
		return is_string( $snippet ) && '' !== $snippet;
	}

	/**
	 * Delete all options owned by this class.
	 */
	public function delete_all_data(): void {
		delete_option( self::SDK_SNIPPET_OPTION );
		delete_option( self::LAST_API_ERROR_OPTION );
	}

	/**
	 * Return the configured Mailmojo API host URL.
	 */
	public function get_api_host(): string {
		$api_host = 'https://api.mailmojo.no/';

		if ( defined( 'MAILMOJO_API_BASE_URL' ) && MAILMOJO_API_BASE_URL ) {
			$api_host = MAILMOJO_API_BASE_URL;
		}

		/**
		 * Filters the Mailmojo API host used by the plugin.
		 *
		 * @param string $api_host API host including version prefix (e.g. https://api.mailmojo.no/v1).
		 */
		return (string) apply_filters( 'mailmojo_api_host', $api_host );
	}

	private function get_http_client(): Client {
		$api_host = $this->get_api_host();

		if ( defined( 'MAILMOJO_DEV_CA_FILE' ) && MAILMOJO_DEV_CA_FILE && str_contains( $api_host, 'api.mailmojo.local' ) ) {
			return new Client( array( 'verify' => MAILMOJO_DEV_CA_FILE ) );
		}

		return new Client();
	}

	private function get_sdk_config( string $token ): MailMojo\Configuration {
		$config = MailMojo\Configuration::getDefaultConfiguration();
		$config->setHost( $this->get_api_host() );
		$config->setAccessToken( $token );

		return $config;
	}

	private function get_account_api( string $token ): MailMojo\Api\AccountApi {
		if ( ! class_exists( 'MailMojo\\Configuration' ) || ! class_exists( 'MailMojo\\Api\\AccountApi' ) ) {
			throw new RuntimeException( __( 'Mailmojo SDK is incomplete.', 'mailmojo' ) );
		}

		return new MailMojo\Api\AccountApi( $this->get_http_client(), $this->get_sdk_config( $token ) );
	}

	private function normalize_integration_list( $result ): array {
		if ( is_array( $result ) ) {
			if ( isset( $result['integrations'] ) && is_array( $result['integrations'] ) ) {
				return $result['integrations'];
			}
			if ( isset( $result['items'] ) && is_array( $result['items'] ) ) {
				return $result['items'];
			}

			return $result;
		}

		if ( is_object( $result ) ) {
			if ( method_exists( $result, 'getIntegrationId' ) ) {
				return array( $result );
			}

			$data = null;
			if ( method_exists( $result, 'getData' ) ) {
				$data = $result->getData();
			} elseif ( isset( $result->data ) ) {
				$data = $result->data;
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
				if ( isset( $data['integrations'] ) && is_array( $data['integrations'] ) ) {
					return $data['integrations'];
				}
				if ( isset( $data['items'] ) && is_array( $data['items'] ) ) {
					return $data['items'];
				}
			}

			return array( $result );
		}

		return array();
	}

	private function normalize_form_list( $result ): array {
		if ( is_array( $result ) ) {
			if ( isset( $result['forms'] ) && is_array( $result['forms'] ) ) {
				return $result['forms'];
			}
			if ( isset( $result['items'] ) && is_array( $result['items'] ) ) {
				return $result['items'];
			}

			return $result;
		}

		if ( is_object( $result ) ) {
			if ( method_exists( $result, 'getData' ) ) {
				$data = $result->getData();
				if ( is_string( $data ) && '' !== $data ) {
					$decoded = json_decode( $data, true );
					if ( is_array( $decoded ) ) {
						return $this->normalize_form_list( $decoded );
					}
				}
			}

			return array( $result );
		}

		return array();
	}

	private function extract_api_error_details( string $response_body ): string {
		$decoded = json_decode( $response_body, true );
		if ( is_array( $decoded ) ) {
			$parts = array();
			if ( isset( $decoded['detail'] ) ) {
				$parts[] = $decoded['detail'];
			}
			if ( isset( $decoded['title'] ) ) {
				$parts[] = $decoded['title'];
			}
			if ( isset( $decoded['message'] ) ) {
				$parts[] = $decoded['message'];
			}
			if ( isset( $decoded['error'] ) ) {
				$parts[] = $decoded['error'];
			}
			if ( isset( $decoded['errors'] ) && is_array( $decoded['errors'] ) ) {
				$parts[] = wp_json_encode( $decoded['errors'] );
			}

			$parts = array_filter( array_map( 'strval', $parts ) );
			if ( ! empty( $parts ) ) {
				return $this->redact_sensitive_fields( implode( ' ', $parts ) );
			}
		}

		return $this->redact_sensitive_fields( $response_body );
	}

	private function redact_sensitive_fields( string $message ): string {
		$message = wp_strip_all_tags( $message );
		$message = preg_replace( '/\s+/', ' ', $message );
		$message = preg_replace( '/\"token\"\s*:\s*\"[^\"]+\"/i', '\"token\":\"***\"', $message );
		$message = preg_replace( '/\"username\"\s*:\s*\"[^\"]+\"/i', '\"username\":\"***\"', $message );
		$message = preg_replace( '/\"site_url\"\s*:\s*\"[^\"]+\"/i', '\"site_url\":\"***\"', $message );
		return trim( $message );
	}
}
