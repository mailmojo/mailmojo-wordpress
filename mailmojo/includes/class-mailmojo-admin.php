<?php
/**
 * Admin setup page for Mailmojo connection.
 *
 * @package Mailmojo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use GuzzleHttp\Client;

class Mailmojo_Admin {
	private const ACCESS_TOKEN_OPTION      = 'mailmojo_access_token';
	private const CONNECTION_STATUS_OPTION = 'mailmojo_connection_status';
	private const SYNC_ENABLED_OPTION      = 'mailmojo_content_sync_enabled';
	private const APP_PASSWORD_OPTION      = 'mailmojo_application_password';
	private const APP_PASSWORD_STATUS      = 'mailmojo_application_password_status';
	private const APP_PASSWORD_TRANSIENT   = 'mailmojo_application_password_plaintext';
	private const APP_PASSWORD_NAME        = 'Mailmojo';
	private const INTEGRATION_ID           = 'wordpress';
	private const LAST_API_ERROR_OPTION    = 'mailmojo_last_api_error';

	public static function init(): void {
		$instance = new self();
		add_action( 'admin_menu', array( $instance, 'register_menu' ) );
		add_action( 'admin_post_mailmojo_save_token', array( $instance, 'handle_save_token' ) );
		add_action( 'admin_post_mailmojo_save_sync', array( $instance, 'handle_save_sync' ) );
		add_action( 'admin_post_mailmojo_test_connection', array( $instance, 'handle_test_connection' ) );
		add_action( 'admin_post_mailmojo_regenerate_app_password', array( $instance, 'handle_regenerate_app_password' ) );
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
		$sync_enabled    = $this->is_content_sync_enabled();
		$app_status      = $this->get_application_password_status();
		$replace_token   = $this->should_replace_token();
		$notice          = $this->get_notice();
		$masked_token    = $token ? str_repeat( '•', 8 ) : '';
		$test_timestamp  = $status['tested_at'] ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $status['tested_at'] ) : '';
		$status_label    = $this->get_status_label( $status['state'] );
		$status_css      = $this->get_status_css_class( $status['state'] );
		$status_icon     = $this->get_connection_status_icon_class( $status['state'] );
		$app_status_css  = $this->get_app_status_css_class( $app_status['state'] );
		$app_status_text = $this->get_app_status_label( $app_status['state'] );
		$app_status_icon = $this->get_sync_status_icon_class( $app_status['state'] );
		$sync_checked_at = $app_status['updated_at'] ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $app_status['updated_at'] ) : '';
		$api_error_notice = $this->get_last_api_error_notice();
		$replace_url     = wp_nonce_url( admin_url( 'admin.php?page=mailmojo&mailmojo_replace_token=1' ), 'mailmojo_replace_token' );
		$cancel_replace_url = admin_url( 'admin.php?page=mailmojo' );
		$show_token_form = ( ! $token ) || $replace_token;
		$show_token_features = $token && ! $show_token_form;
		$regenerate_url  = wp_nonce_url( admin_url( 'admin-post.php?action=mailmojo_regenerate_app_password' ), 'mailmojo_regenerate_app_password' );

		?>
		<div class="wrap">
			<style>
				.mailmojo-table th,
				.mailmojo-table td {
					padding-top: 8px;
					padding-bottom: 8px;
				}
				.mailmojo-section {
					margin-top: 28px;
				}
				.mailmojo-section:first-of-type {
					margin-top: 0;
				}
				.mailmojo-actions .button + .button {
					margin-left: 6px;
				}
			</style>
			<h1><?php esc_html_e( 'Mailmojo', 'mailmojo' ); ?></h1>
			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
					<p><?php echo esc_html( $notice['message'] ); ?></p>
				</div>
			<?php endif; ?>
			<?php if ( $show_token_features && $sync_enabled && 'error' === $app_status['state'] && $app_status['message'] ) : ?>
				<div class="notice notice-error is-dismissible">
					<p><?php echo esc_html( $app_status['message'] ); ?></p>
				</div>
			<?php endif; ?>
			<?php if ( $api_error_notice ) : ?>
				<div class="notice notice-error is-dismissible">
					<p><?php echo esc_html( $api_error_notice ); ?></p>
				</div>
			<?php endif; ?>

			<p><?php esc_html_e( 'Mailmojo helps you collect subscribers, show signup forms, and sync WordPress content to your Mailmojo account.', 'mailmojo' ); ?></p>
			<p><?php esc_html_e( 'To connect this site, provide a Mailmojo API access token from your account settings.', 'mailmojo' ); ?></p>

			<h2 class="mailmojo-section"><?php esc_html_e( 'Mailmojo API token', 'mailmojo' ); ?></h2>
			<?php if ( $token && ! $show_token_form ) : ?>
				<table class="form-table mailmojo-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Saved token', 'mailmojo' ); ?></th>
						<td><?php echo esc_html( $masked_token ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'mailmojo' ); ?></th>
						<td>
							<strong class="<?php echo esc_attr( $status_css ); ?>">
								<span class="dashicons <?php echo esc_attr( $status_icon ); ?>" aria-hidden="true"></span>
								<?php echo esc_html( $status_label ); ?>
							</strong>
							<?php if ( $status['message'] ) : ?>
								<span>— <?php echo esc_html( $status['message'] ); ?></span>
							<?php endif; ?>
							<?php if ( $test_timestamp ) : ?>
								<span><?php echo esc_html( sprintf( __( 'Last tested: %s', 'mailmojo' ), $test_timestamp ) ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<p class="mailmojo-actions">
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
					<p class="submit">
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Save token', 'mailmojo' ); ?>
						</button>
						<?php if ( $token ) : ?>
							<a class="button button-secondary" href="<?php echo esc_url( $cancel_replace_url ); ?>">
								<?php esc_html_e( 'Keep existing token', 'mailmojo' ); ?>
							</a>
						<?php endif; ?>
					</p>
				</form>
			<?php endif; ?>

			<?php if ( $show_token_features ) : ?>
				<h2 class="mailmojo-section"><?php esc_html_e( 'Synchronize content to Mailmojo', 'mailmojo' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'mailmojo_save_sync' ); ?>
					<input type="hidden" name="action" value="mailmojo_save_sync" />
					<table class="form-table mailmojo-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Content sync', 'mailmojo' ); ?></th>
							<td>
								<label>
									<input
										type="checkbox"
										name="mailmojo_sync_enabled"
										id="mailmojo-sync-enabled"
										value="1"
										<?php checked( $sync_enabled ); ?>
									/>
									<?php esc_html_e( 'Enable content sync to Mailmojo.', 'mailmojo' ); ?>
								</label>
							</td>
						</tr>
						<?php if ( $sync_enabled ) : ?>
							<tr>
								<th scope="row"><?php esc_html_e( 'Status', 'mailmojo' ); ?></th>
								<td>
									<p>
										<strong class="<?php echo esc_attr( $app_status_css ); ?>">
											<span class="dashicons <?php echo esc_attr( $app_status_icon ); ?>" aria-hidden="true"></span>
											<?php echo esc_html( $app_status_text ); ?>
										</strong>
										<?php if ( $app_status['message'] ) : ?>
											<span>— <?php echo esc_html( $app_status['message'] ); ?></span>
										<?php endif; ?>
										<?php if ( $sync_checked_at ) : ?>
											<span><?php echo esc_html( sprintf( __( 'Last checked: %s', 'mailmojo' ), $sync_checked_at ) ); ?></span>
										<?php endif; ?>
									</p>
								</td>
							</tr>
						<?php endif; ?>
					</table>
					<p class="submit mailmojo-actions">
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Save sync settings', 'mailmojo' ); ?>
						</button>
						<?php if ( $sync_enabled ) : ?>
							<a class="button button-secondary" href="<?php echo esc_url( $regenerate_url ); ?>">
								<?php esc_html_e( 'Refresh connection', 'mailmojo' ); ?>
							</a>
						<?php endif; ?>
					</p>
				</form>
			<?php endif; ?>

			<?php if ( $show_token_features ) : ?>
				<h2 class="mailmojo-section"><?php esc_html_e( 'Test connection', 'mailmojo' ); ?></h2>
				<p><?php esc_html_e( 'Verify that the saved token can connect to Mailmojo.', 'mailmojo' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'mailmojo_test_connection' ); ?>
					<input type="hidden" name="action" value="mailmojo_test_connection" />
					<?php submit_button( __( 'Test connection', 'mailmojo' ), 'secondary' ); ?>
				</form>
			<?php endif; ?>
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

	public function handle_save_sync(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'mailmojo' ) );
		}

		check_admin_referer( 'mailmojo_save_sync' );

		$enabled = isset( $_POST['mailmojo_sync_enabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['mailmojo_sync_enabled'] ) );
		update_option( self::SYNC_ENABLED_OPTION, $enabled ? '1' : '0', false );

		if ( $enabled ) {
			$this->ensure_application_password();
			$this->sync_mailmojo_integration_status();
			$app_status = $this->get_application_password_status();
			if ( 'sent' !== $app_status['state'] ) {
				$this->redirect_with_notice( 'sync_needs_attention' );
			}
		} else {
			$this->set_application_password_status( 'not_created', __( 'Content sync is disabled.', 'mailmojo' ) );
		}

		$this->redirect_with_notice( 'sync_saved' );
	}

	public function handle_test_connection(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'mailmojo' ) );
		}

		check_admin_referer( 'mailmojo_test_connection' );

		$token = $this->get_access_token();

		if ( '' === $token ) {
			$this->set_connection_status( 'not_connected', __( 'No token saved.', 'mailmojo' ) );
			if ( $this->is_content_sync_enabled() ) {
				$this->set_application_password_status( 'error', __( 'Mailmojo token is missing.', 'mailmojo' ) );
			}
			$this->redirect_with_notice( 'token_missing' );
		}

		try {
			$this->test_connection_with_sdk( $token );
			$this->set_connection_status( 'connected', __( 'Connection successful.', 'mailmojo' ) );
			$this->sync_mailmojo_integration_status();
			$this->redirect_with_notice( 'connection_success' );
		} catch ( Throwable $error ) {
			if ( $this->is_content_sync_enabled() ) {
				$this->set_application_password_status( 'error', __( 'Mailmojo token could not be verified.', 'mailmojo' ) );
			}
			$this->handle_connection_error( $error );
		}
	}

	public function handle_regenerate_app_password(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'mailmojo' ) );
		}

		check_admin_referer( 'mailmojo_regenerate_app_password' );

		$this->ensure_application_password( true );
		$app_status = $this->get_application_password_status();
		if ( 'error' === $app_status['state'] ) {
			$this->redirect_with_notice( 'app_password_failed' );
		}

		$this->redirect_with_notice( 'app_password_updated' );
	}

	private function test_connection_with_sdk( string $token ): void {
		if ( ! class_exists( 'MailMojo\\Configuration' ) ) {
			throw new RuntimeException( __( 'Mailmojo SDK is not available.', 'mailmojo' ) );
		}

		if ( ! class_exists( 'MailMojo\\Configuration' ) || ! class_exists( 'MailMojo\\Api\\ListApi' ) ) {
			throw new RuntimeException( __( 'Mailmojo SDK is incomplete.', 'mailmojo' ) );
		}

		$api_host = $this->get_mailmojo_api_host();
		$client = $this->create_mailmojo_http_client( $api_host );
		$config = MailMojo\Configuration::getDefaultConfiguration();
		$config->setHost( $api_host );
		$config->setAccessToken( $token );

		$lists_api  = new MailMojo\Api\ListApi( $client, $config );
		$lists_api->getLists();
	}

	private function create_mailmojo_http_client( string $api_host ): Client {
		if ( defined( 'MAILMOJO_DEV_CA_FILE' ) && MAILMOJO_DEV_CA_FILE && str_contains( $api_host, 'api.mailmojo.local' ) ) {
			return new Client( array( 'verify' => MAILMOJO_DEV_CA_FILE ) );
		}

		return new Client();
	}

	private function get_account_api( string $token ): MailMojo\Api\AccountApi {
		if ( ! class_exists( 'MailMojo\\Configuration' ) || ! class_exists( 'MailMojo\\Api\\AccountApi' ) ) {
			throw new RuntimeException( __( 'Mailmojo SDK is incomplete.', 'mailmojo' ) );
		}

		$api_host = $this->get_mailmojo_api_host();
		$client = $this->create_mailmojo_http_client( $api_host );
		$config = MailMojo\Configuration::getDefaultConfiguration();
		$config->setHost( $api_host );
		$config->setAccessToken( $token );

		return new MailMojo\Api\AccountApi( $client, $config );
	}

	private function get_mailmojo_api_host(): string {
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

	private function handle_connection_error( Throwable $error ): void {
		$code    = (int) $error->getCode();
		$message = __( 'Unexpected error while testing connection.', 'mailmojo' );

		if ( class_exists( 'MailMojo\\ApiException' ) && $error instanceof MailMojo\ApiException ) {
			if ( 0 === $code ) {
				$message = __( 'Network error. Please try again.', 'mailmojo' );
			} elseif ( 401 === $code || 403 === $code ) {
				$message = __( 'Unauthorized. Please check the token.', 'mailmojo' );
			} else {
				$message = __( 'Mailmojo API returned an error.', 'mailmojo' );
			}
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

	private function ensure_application_password( bool $force_regenerate = false ): void {
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
		$this->sync_mailmojo_integration_status();
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

	private function get_application_password(): array {
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

	private function update_application_password_metadata( int $user_id, string $username, string $password ): void {
		$app_password = $this->get_application_password();
		$app_password['user_id'] = $user_id;
		$app_password['username'] = $username;
		$app_password['token_hash'] = $this->hash_application_password( $password );

		update_option( self::APP_PASSWORD_OPTION, $app_password, false );
	}

	private function get_application_password_user_id(): int {
		$password = $this->get_application_password();
		return isset( $password['user_id'] ) ? (int) $password['user_id'] : 0;
	}

	private function set_application_password_status( string $state, string $message ): void {
		update_option(
			self::APP_PASSWORD_STATUS,
			array(
				'state'      => $state,
				'message'    => $message,
				'updated_at' => time(),
			),
			false
		);
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

	private function get_application_password_status(): array {
		$status = get_option( self::APP_PASSWORD_STATUS, array() );
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

	private function get_app_status_label( string $state ): string {
		switch ( $state ) {
			case 'sent':
				return __( 'Up to date', 'mailmojo' );
			case 'pending':
				return __( 'Ready to update', 'mailmojo' );
			case 'needs_refresh':
				return __( 'Refresh needed', 'mailmojo' );
			case 'not_available':
				return __( 'Not available', 'mailmojo' );
			case 'error':
				return __( 'Action required', 'mailmojo' );
			default:
				return __( 'Not created', 'mailmojo' );
		}
	}

	private function get_app_status_css_class( string $state ): string {
		switch ( $state ) {
			case 'sent':
				return 'mailmojo-status mailmojo-status--connected';
			case 'pending':
				return 'mailmojo-status mailmojo-status--idle';
			case 'needs_refresh':
				return 'mailmojo-status mailmojo-status--error';
			case 'not_available':
			case 'error':
				return 'mailmojo-status mailmojo-status--error';
			default:
				return 'mailmojo-status mailmojo-status--idle';
		}
	}

	private function get_access_token(): string {
		$token = get_option( self::ACCESS_TOKEN_OPTION, '' );
		return is_string( $token ) ? $token : '';
	}

	private function is_content_sync_enabled(): bool {
		$enabled = get_option( self::SYNC_ENABLED_OPTION, '0' );
		return '1' === $enabled || true === $enabled;
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

	private function get_connection_status_icon_class( string $state ): string {
		switch ( $state ) {
			case 'connected':
				return 'dashicons-yes-alt';
			case 'not_connected':
				return 'dashicons-no-alt';
			default:
				return 'dashicons-minus';
		}
	}

	private function get_sync_status_icon_class( string $state ): string {
		switch ( $state ) {
			case 'sent':
				return 'dashicons-yes-alt';
			case 'needs_refresh':
			case 'not_available':
			case 'error':
				return 'dashicons-no-alt';
			default:
				return 'dashicons-minus';
		}
	}

	private function sync_mailmojo_integration_status(): void {
		if ( ! $this->is_content_sync_enabled() ) {
			return;
		}

		$token = $this->get_access_token();
		if ( '' === $token ) {
			$this->set_application_password_status( 'error', __( 'Mailmojo token is missing.', 'mailmojo' ) );
			return;
		}

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
			$this->update_mailmojo_integration_settings( $token, $password, $user_id );
			return;
		}

		$this->verify_mailmojo_integration_settings( $token, $app_password );
	}

	private function update_mailmojo_integration_settings( string $token, string $password, int $user_id ): void {
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
			$account_api = $this->get_account_api( $token );
			$account_api->updateAccountIntegration( self::INTEGRATION_ID, (object) $settings );
			$this->update_application_password_metadata( $user_id, $username, $password );
			$this->clear_last_api_error();
			$this->set_application_password_status( 'sent', __( 'Mailmojo has the latest sync connection.', 'mailmojo' ) );
		} catch ( Throwable $error ) {
			$api_message = $this->format_api_error_message( $error );
			$this->store_last_api_error( $api_message );
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

	private function verify_mailmojo_integration_settings( string $token, array $app_password ): void {
		if ( empty( $app_password['token_hash'] ) || empty( $app_password['username'] ) ) {
			$this->set_application_password_status( 'needs_refresh', __( 'Sync connection needs to be refreshed.', 'mailmojo' ) );
			return;
		}

		$settings = $this->get_mailmojo_integration_settings( $token );
		if ( null === $settings ) {
			$this->set_application_password_status( 'pending', __( 'Sync connection has not been stored in Mailmojo yet.', 'mailmojo' ) );
			return;
		}

		$remote_username = isset( $settings['username'] ) ? (string) $settings['username'] : '';
		$remote_token = isset( $settings['token'] ) ? (string) $settings['token'] : '';
		$remote_site_url = isset( $settings['site_url'] ) ? (string) $settings['site_url'] : '';

		$matches = (
			$remote_username === (string) $app_password['username']
			&& '' !== $remote_token
			&& $this->hash_application_password( $remote_token ) === (string) $app_password['token_hash']
			&& $remote_site_url === site_url()
		);

		if ( $matches ) {
			$this->clear_last_api_error();
			$this->set_application_password_status( 'sent', __( 'Mailmojo has the latest sync connection.', 'mailmojo' ) );
			return;
		}

		$this->set_application_password_status( 'needs_refresh', __( 'Mailmojo has different sync connection settings.', 'mailmojo' ) );
	}

	private function get_mailmojo_integration_settings( string $token ): ?array {
		try {
			$account_api = $this->get_account_api( $token );
			$result = $account_api->getAccountIntegrations();
		} catch ( Throwable $error ) {
			$api_message = $this->format_api_error_message( $error );
			$this->store_last_api_error( $api_message );
			$this->set_application_password_status(
				'error',
				sprintf(
					/* translators: %s is an API error summary. */
					__( 'Unable to verify sync connection with Mailmojo. %s', 'mailmojo' ),
					$api_message
				)
			);
			return null;
		}

		$integrations = $this->normalize_integration_list( $result );
		foreach ( $integrations as $integration ) {
			$integration_id = '';
			$data = null;

			if ( is_object( $integration ) && method_exists( $integration, 'getIntegrationId' ) ) {
				$integration_id = (string) $integration->getIntegrationId();
				if ( method_exists( $integration, 'getData' ) ) {
					$data = $integration->getData();
				}
			} elseif ( is_object( $integration ) ) {
				$integration_id = isset( $integration->integration_id ) ? (string) $integration->integration_id : '';
				$data = $integration->data ?? null;
			} elseif ( is_array( $integration ) ) {
				$integration_id = isset( $integration['integration_id'] ) ? (string) $integration['integration_id'] : '';
				$data = $integration['data'] ?? null;
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

	private function format_api_error_message( Throwable $error ): string {
		$code = (int) $error->getCode();
		$message = trim( wp_strip_all_tags( $error->getMessage() ) );
		$message = preg_replace( '/\\s+/', ' ', $message );
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

	private function store_last_api_error( string $message ): void {
		update_option(
			self::LAST_API_ERROR_OPTION,
			array(
				'message'    => $message,
				'updated_at' => time(),
			),
			false
		);
	}

	private function clear_last_api_error(): void {
		delete_option( self::LAST_API_ERROR_OPTION );
	}

	private function get_last_api_error_notice(): ?string {
		$last_error = get_option( self::LAST_API_ERROR_OPTION, array() );
		if ( ! is_array( $last_error ) ) {
			return null;
		}

		$message = isset( $last_error['message'] ) ? (string) $last_error['message'] : '';
		$updated_at = isset( $last_error['updated_at'] ) ? (int) $last_error['updated_at'] : 0;

		if ( '' === $message || 0 === $updated_at ) {
			return null;
		}

		if ( time() - $updated_at > 15 * MINUTE_IN_SECONDS ) {
			return null;
		}

		return $message;
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
		$message = preg_replace( '/\\s+/', ' ', $message );
		$message = preg_replace( '/\"token\"\\s*:\\s*\"[^\"]+\"/i', '\"token\":\"***\"', $message );
		$message = preg_replace( '/\"username\"\\s*:\\s*\"[^\"]+\"/i', '\"username\":\"***\"', $message );
		$message = preg_replace( '/\"site_url\"\\s*:\\s*\"[^\"]+\"/i', '\"site_url\":\"***\"', $message );
		return trim( $message );
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
			'app_password_updated' => array(
				'type'    => 'success',
				'message' => __( 'Connection refreshed.', 'mailmojo' ),
			),
			'app_password_failed' => array(
				'type'    => 'error',
				'message' => __( 'Connection refresh failed. Please verify your Mailmojo token and try again.', 'mailmojo' ),
			),
			'sync_saved'         => array(
				'type'    => 'success',
				'message' => __( 'Content sync settings saved.', 'mailmojo' ),
			),
			'sync_needs_attention' => array(
				'type'    => 'warning',
				'message' => __( 'Content sync settings saved, but the connection needs attention.', 'mailmojo' ),
			),
		);

		return $messages[ $notice ] ?? null;
	}
}
