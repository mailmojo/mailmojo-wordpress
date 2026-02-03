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
		$app_password    = $this->get_application_password();
		$app_status      = $this->get_application_password_status();
		$app_password_ui = $this->get_application_password_plaintext();
		$replace_token   = $this->should_replace_token();
		$notice          = $this->get_notice();
		$masked_token    = $token ? str_repeat( '•', 8 ) : '';
		$test_timestamp  = $status['tested_at'] ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $status['tested_at'] ) : '';
		$status_label    = $this->get_status_label( $status['state'] );
		$status_css      = $this->get_status_css_class( $status['state'] );
		$app_status_css  = $this->get_app_status_css_class( $app_status['state'] );
		$app_status_text = $this->get_app_status_label( $app_status['state'] );
		$replace_url     = wp_nonce_url( admin_url( 'admin.php?page=mailmojo&mailmojo_replace_token=1' ), 'mailmojo_replace_token' );
		$cancel_replace_url = admin_url( 'admin.php?page=mailmojo' );
		$show_token_form = ( ! $token ) || $replace_token;
		$show_token_features = $token && ! $show_token_form;
		$regenerate_url  = wp_nonce_url( admin_url( 'admin-post.php?action=mailmojo_regenerate_app_password' ), 'mailmojo_regenerate_app_password' );
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

			<?php if ( $show_token_features ) : ?>
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
			<?php endif; ?>

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
				<h2><?php esc_html_e( 'Synchronize content to Mailmojo', 'mailmojo' ); ?></h2>
				<p><?php esc_html_e( 'Enable content sync to make your WordPress posts available in Mailmojo for newsletters.', 'mailmojo' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'mailmojo_save_sync' ); ?>
					<input type="hidden" name="action" value="mailmojo_save_sync" />
					<table class="form-table" role="presentation">
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
					</table>
					<?php submit_button( __( 'Save sync settings', 'mailmojo' ) ); ?>
				</form>

				<?php if ( $sync_enabled ) : ?>
					<h3><?php esc_html_e( 'Application password', 'mailmojo' ); ?></h3>
					<p><?php esc_html_e( 'Mailmojo uses an application password to read data from your WordPress site.', 'mailmojo' ); ?></p>
					<p>
						<strong class="<?php echo esc_attr( $app_status_css ); ?>">
							<?php echo esc_html( $app_status_text ); ?>
						</strong>
						<?php if ( $app_status['message'] ) : ?>
							<span>— <?php echo esc_html( $app_status['message'] ); ?></span>
						<?php endif; ?>
					</p>
					<?php if ( $app_password_ui ) : ?>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="mailmojo-app-password"><?php esc_html_e( 'Application password', 'mailmojo' ); ?></label>
								</th>
								<td>
									<input
										type="password"
										id="mailmojo-app-password"
										class="regular-text"
										value="<?php echo esc_attr( $app_password_ui ); ?>"
										readonly
									/>
									<p class="description">
										<?php esc_html_e( 'Copy this password now. For security, it is only shown once.', 'mailmojo' ); ?>
									</p>
								</td>
							</tr>
						</table>
					<?php elseif ( $app_password['uuid'] ) : ?>
						<p><?php esc_html_e( 'An application password is stored for this site. Regenerate it if you need to copy it again.', 'mailmojo' ); ?></p>
					<?php else : ?>
						<p><?php esc_html_e( 'No application password has been created yet. Enable sync to generate one.', 'mailmojo' ); ?></p>
					<?php endif; ?>
					<p>
						<a class="button" href="<?php echo esc_url( $regenerate_url ); ?>">
							<?php esc_html_e( 'Regenerate application password', 'mailmojo' ); ?>
						</a>
					</p>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( $show_token_features ) : ?>
				<h2><?php esc_html_e( 'Test connection', 'mailmojo' ); ?></h2>
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
			$this->redirect_with_notice( 'token_missing' );
		}

		try {
			$this->test_connection_with_sdk( $token );
			$this->set_connection_status( 'connected', __( 'Connection successful.', 'mailmojo' ) );
			$this->redirect_with_notice( 'connection_success' );
		} catch ( Throwable $error ) {
			var_dump( $error );
			exit;
			$this->handle_connection_error( $error );
		}
	}

	public function handle_regenerate_app_password(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'mailmojo' ) );
		}

		check_admin_referer( 'mailmojo_regenerate_app_password' );

		$this->ensure_application_password( true );
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
		$client = null;
		if ( defined('MAILMOJO_DEV_CA_FILE') && MAILMOJO_DEV_CA_FILE &&
				str_contains($api_host, 'api.mailmojo.local')) {
			// Use custom Guzzle client with dev CA for self-signed certs in local dev environment
			$verify = MAILMOJO_DEV_CA_FILE;
			$client = new Client( array( 'verify' => $verify ) );
		}

		$lists_api  = new MailMojo\Api\ListApi($client);
		$lists_api->getConfig()->setHost( $api_host );
		$lists_api->getConfig()->setAccessToken( $token );
		$lists_api->getLists();
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
			$this->set_application_password_status( 'not_available', __( 'Application passwords are not available on this WordPress version.', 'mailmojo' ) );
			return;
		}

		$user_id = $this->get_application_password_user_id();
		if ( 0 === $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( 0 === $user_id ) {
			$this->set_application_password_status( 'error', __( 'Unable to determine a user for the application password.', 'mailmojo' ) );
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
			$this->set_application_password_status( 'error', __( 'Unable to create an application password.', 'mailmojo' ) );
			return;
		}

		if ( ! is_array( $result ) || 2 !== count( $result ) ) {
			$this->set_application_password_status( 'error', __( 'Application password creation failed.', 'mailmojo' ) );
			return;
		}

		list( $password, $password_item ) = $result;

		if ( ! is_string( $password ) || '' === $password ) {
			$this->set_application_password_status( 'error', __( 'Application password creation failed.', 'mailmojo' ) );
			return;
		}

		if ( ! is_array( $password_item ) || empty( $password_item['uuid'] ) ) {
			$this->set_application_password_status( 'error', __( 'Application password creation failed.', 'mailmojo' ) );
			return;
		}

		$this->store_application_password( $user_id, $password_item );
		$this->store_application_password_plaintext( $password );
		$this->set_application_password_status( 'pending', __( 'Application password created and ready to send to Mailmojo.', 'mailmojo' ) );
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
				'created_at' => 0,
			)
		);
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

		$this->set_application_password_status( 'pending', __( 'Application password created and ready to send to Mailmojo.', 'mailmojo' ) );
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
				return __( 'Sent to Mailmojo', 'mailmojo' );
			case 'pending':
				return __( 'Ready to send', 'mailmojo' );
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
				'message' => __( 'Application password updated.', 'mailmojo' ),
			),
			'sync_saved'         => array(
				'type'    => 'success',
				'message' => __( 'Content sync settings saved.', 'mailmojo' ),
			),
		);

		return $messages[ $notice ] ?? null;
	}
}
