<?php
/**
 * Admin setup page for Mailmojo connection.
 *
 * @package Mailmojo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mailmojo_Admin {
	private const ACCESS_TOKEN_OPTION      = 'mailmojo_access_token';
	private const CONNECTION_STATUS_OPTION = 'mailmojo_connection_status';

	public static function init(): void {
		$instance = new self();
		add_action( 'admin_menu', array( $instance, 'register_menu' ) );
		add_action( 'admin_post_mailmojo_save_token', array( $instance, 'handle_save_token' ) );
		add_action( 'admin_post_mailmojo_test_connection', array( $instance, 'handle_test_connection' ) );
	}

	public function register_menu(): void {
		add_menu_page(
			__( 'Mailmojo', 'mailmojo' ),
			__( 'Mailmojo', 'mailmojo' ),
			'manage_options',
			'mailmojo',
			array( $this, 'render_page' ),
			'dashicons-email-alt2'
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'mailmojo' ) );
		}

		$token           = $this->get_access_token();
		$status          = $this->get_connection_status();
		$replace_token   = $this->should_replace_token();
		$notice          = $this->get_notice();
		$masked_token    = $token ? str_repeat( '•', 8 ) : '';
		$test_timestamp  = $status['tested_at'] ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $status['tested_at'] ) : '';
		$status_label    = $this->get_status_label( $status['state'] );
		$status_css      = $this->get_status_css_class( $status['state'] );
		$replace_url     = wp_nonce_url( admin_url( 'admin.php?page=mailmojo&mailmojo_replace_token=1' ), 'mailmojo_replace_token' );
		$show_token_form = ( ! $token ) || $replace_token;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Mailmojo', 'mailmojo' ); ?></h1>
			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
					<p><?php echo esc_html( $notice['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<p><?php esc_html_e( 'Mailmojo helps you collect subscribers, show signup forms, and sync WordPress content to your Mailmojo account.', 'mailmojo' ); ?></p>
			<p><?php esc_html_e( 'To connect this site, provide a Mailmojo API access token from your account settings.', 'mailmojo' ); ?></p>

			<h2><?php esc_html_e( 'Connection status', 'mailmojo' ); ?></h2>
			<p>
				<strong class="<?php echo esc_attr( $status_css ); ?>">
					<?php echo esc_html( $status_label ); ?>
				</strong>
				<?php if ( $status['message'] ) : ?>
					<span>— <?php echo esc_html( $status['message'] ); ?></span>
				<?php endif; ?>
				<?php if ( $test_timestamp ) : ?>
					<span><?php echo esc_html( sprintf( __( 'Last tested: %s', 'mailmojo' ), $test_timestamp ) ); ?></span>
				<?php endif; ?>
			</p>

			<h2><?php esc_html_e( 'Mailmojo API token', 'mailmojo' ); ?></h2>
			<?php if ( $token && ! $show_token_form ) : ?>
				<p>
					<?php echo esc_html( sprintf( __( 'Saved token: %s', 'mailmojo' ), $masked_token ) ); ?>
				</p>
				<p>
					<a class="button" href="<?php echo esc_url( $replace_url ); ?>">
						<?php esc_html_e( 'Replace token', 'mailmojo' ); ?>
					</a>
				</p>
			<?php endif; ?>

			<?php if ( $show_token_form ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'mailmojo_save_token' ); ?>
					<input type="hidden" name="action" value="mailmojo_save_token" />
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="mailmojo-access-token"><?php esc_html_e( 'Access token', 'mailmojo' ); ?></label>
							</th>
							<td>
								<input
									type="password"
									name="mailmojo_access_token"
									id="mailmojo-access-token"
									class="regular-text"
									autocomplete="new-password"
									placeholder="<?php esc_attr_e( 'Paste your Mailmojo API token', 'mailmojo' ); ?>"
									required
								/>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Save token', 'mailmojo' ) ); ?>
				</form>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Test connection', 'mailmojo' ); ?></h2>
			<p><?php esc_html_e( 'Verify that the saved token can connect to Mailmojo.', 'mailmojo' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'mailmojo_test_connection' ); ?>
				<input type="hidden" name="action" value="mailmojo_test_connection" />
				<?php submit_button( __( 'Test connection', 'mailmojo' ), 'secondary' ); ?>
			</form>
		</div>
		<?php
	}

	public function handle_save_token(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'mailmojo' ) );
		}

		check_admin_referer( 'mailmojo_save_token' );

		$token = isset( $_POST['mailmojo_access_token'] ) ? sanitize_text_field( wp_unslash( $_POST['mailmojo_access_token'] ) ) : '';

		if ( '' === $token ) {
			$this->redirect_with_notice( 'token_missing' );
		}

		update_option( self::ACCESS_TOKEN_OPTION, $token, false );
		update_option(
			self::CONNECTION_STATUS_OPTION,
			array(
				'state'     => 'not_tested',
				'message'   => __( 'Token saved. Connection has not been tested yet.', 'mailmojo' ),
				'tested_at' => 0,
			),
			false
		);

		$this->redirect_with_notice( 'token_saved' );
	}

	public function handle_test_connection(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'mailmojo' ) );
		}

		check_admin_referer( 'mailmojo_test_connection' );

		$token = $this->get_access_token();

		if ( '' === $token ) {
			$this->set_connection_status( 'not_connected', __( 'No token saved.', 'mailmojo' ) );
			$this->redirect_with_notice( 'token_missing' );
		}

		try {
			$this->test_connection_with_sdk( $token );
			$this->set_connection_status( 'connected', __( 'Connection successful.', 'mailmojo' ) );
			$this->redirect_with_notice( 'connection_success' );
		} catch ( Throwable $error ) {
			$this->handle_connection_error( $error );
		}
	}

	private function test_connection_with_sdk( string $token ): void {
		if ( ! class_exists( 'MailMojo\\Configuration' ) ) {
			throw new RuntimeException( __( 'Mailmojo SDK is not available.', 'mailmojo' ) );
		}

		if ( ! class_exists( 'GuzzleHttp\\Client' ) ) {
			throw new RuntimeException( __( 'Mailmojo SDK dependencies are missing.', 'mailmojo' ) );
		}

		$config = MailMojo\Configuration::getDefaultConfiguration();
		if ( method_exists( $config, 'setAccessToken' ) ) {
			$config->setAccessToken( $token );
		}
		if ( method_exists( $config, 'setApiKey' ) ) {
			$config->setApiKey( 'Authorization', $token );
		}
		if ( method_exists( $config, 'setApiKeyPrefix' ) ) {
			$config->setApiKeyPrefix( 'Authorization', 'Bearer' );
		}

		$client    = new GuzzleHttp\Client();
		$candidate = $this->resolve_api_candidate( $client, $config );

		if ( ! $candidate ) {
			throw new RuntimeException( __( 'Mailmojo SDK does not expose a supported connection test.', 'mailmojo' ) );
		}

		$instance = $candidate['instance'];
		$method   = $candidate['method'];
		$instance->$method();
	}

	private function resolve_api_candidate( GuzzleHttp\Client $client, MailMojo\Configuration $config ): ?array {
		$candidates = array(
			array(
				'class'   => 'MailMojo\\Api\\AccountApi',
				'methods' => array( 'getAccount', 'getAccountWithHttpInfo' ),
			),
			array(
				'class'   => 'MailMojo\\Api\\AccountsApi',
				'methods' => array( 'getAccount', 'getAccountWithHttpInfo' ),
			),
			array(
				'class'   => 'MailMojo\\Api\\UsersApi',
				'methods' => array( 'getCurrentUser', 'getMe', 'getUser' ),
			),
			array(
				'class'   => 'MailMojo\\Api\\DefaultApi',
				'methods' => array( 'ping', 'getPing' ),
			),
		);

		foreach ( $candidates as $candidate ) {
			if ( ! class_exists( $candidate['class'] ) ) {
				continue;
			}

			$instance = $this->instantiate_api( $candidate['class'], $client, $config );
			if ( ! $instance ) {
				continue;
			}

			foreach ( $candidate['methods'] as $method ) {
				if ( method_exists( $instance, $method ) ) {
					return array(
						'instance' => $instance,
						'method'   => $method,
					);
				}
			}
		}

		return null;
	}

	private function instantiate_api( string $class, GuzzleHttp\Client $client, MailMojo\Configuration $config ): ?object {
		try {
			return new $class( $client, $config );
		} catch ( Throwable $error ) {
			try {
				return new $class( $client );
			} catch ( Throwable $error ) {
				try {
					return new $class();
				} catch ( Throwable $error ) {
					return null;
				}
			}
		}
	}

	private function handle_connection_error( Throwable $error ): void {
		$code    = (int) $error->getCode();
		$message = __( 'Unexpected error while testing connection.', 'mailmojo' );

		if ( class_exists( 'MailMojo\\ApiException' ) && $error instanceof MailMojo\ApiException ) {
			if ( 401 === $code || 403 === $code ) {
				$message = __( 'Unauthorized. Please check the token.', 'mailmojo' );
			} else {
				$message = __( 'Mailmojo API returned an error.', 'mailmojo' );
			}
		} elseif ( class_exists( 'GuzzleHttp\\Exception\\ConnectException' ) && $error instanceof GuzzleHttp\Exception\ConnectException ) {
			$message = __( 'Network error. Please try again.', 'mailmojo' );
		}

		$this->set_connection_status( 'not_connected', $message );
		$this->redirect_with_notice( 'connection_failed' );
	}

	private function set_connection_status( string $state, string $message ): void {
		update_option(
			self::CONNECTION_STATUS_OPTION,
			array(
				'state'     => $state,
				'message'   => $message,
				'tested_at' => time(),
			),
			false
		);
	}

	private function get_access_token(): string {
		$token = get_option( self::ACCESS_TOKEN_OPTION, '' );
		return is_string( $token ) ? $token : '';
	}

	private function get_connection_status(): array {
		$status = get_option( self::CONNECTION_STATUS_OPTION, array() );
		if ( ! is_array( $status ) ) {
			$status = array();
		}

		return wp_parse_args(
			$status,
			array(
				'state'     => 'not_tested',
				'message'   => '',
				'tested_at' => 0,
			)
		);
	}

	private function get_status_label( string $state ): string {
		switch ( $state ) {
			case 'connected':
				return __( 'Connected', 'mailmojo' );
			case 'not_connected':
				return __( 'Not connected', 'mailmojo' );
			default:
				return __( 'Not tested', 'mailmojo' );
		}
	}

	private function get_status_css_class( string $state ): string {
		switch ( $state ) {
			case 'connected':
				return 'mailmojo-status mailmojo-status--connected';
			case 'not_connected':
				return 'mailmojo-status mailmojo-status--error';
			default:
				return 'mailmojo-status mailmojo-status--idle';
		}
	}

	private function should_replace_token(): bool {
		if ( empty( $_GET['mailmojo_replace_token'] ) ) {
			return false;
		}

		return wp_verify_nonce(
			isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '',
			'mailmojo_replace_token'
		);
	}

	private function redirect_with_notice( string $notice ): void {
		$redirect_url = add_query_arg(
			array(
				'page'            => 'mailmojo',
				'mailmojo_notice' => $notice,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	private function get_notice(): ?array {
		if ( empty( $_GET['mailmojo_notice'] ) ) {
			return null;
		}

		$notice = sanitize_key( wp_unslash( $_GET['mailmojo_notice'] ) );

		$messages = array(
			'token_saved'        => array(
				'type'    => 'success',
				'message' => __( 'Token saved successfully.', 'mailmojo' ),
			),
			'token_missing'      => array(
				'type'    => 'error',
				'message' => __( 'Please enter a Mailmojo access token.', 'mailmojo' ),
			),
			'connection_success' => array(
				'type'    => 'success',
				'message' => __( 'Connection successful.', 'mailmojo' ),
			),
			'connection_failed'  => array(
				'type'    => 'error',
				'message' => __( 'Connection failed. Review the status message for details.', 'mailmojo' ),
			),
		);

		return $messages[ $notice ] ?? null;
	}
}
