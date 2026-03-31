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

	private Mailmojo_Api  $api;
	private Mailmojo_Sync $sync;

	private function __construct() {
		$this->api  = new Mailmojo_Api();
		$this->sync = new Mailmojo_Sync( $this->api );
	}

	public static function init(): void {
		$instance = new self();
		add_action( 'admin_menu', array( $instance, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $instance, 'enqueue_admin_assets' ) );
		add_action( 'rest_api_init', array( $instance, 'register_rest_routes' ) );
		add_action( 'admin_post_mailmojo_save_token', array( $instance, 'handle_save_token' ) );
		add_action( 'admin_post_mailmojo_save_sync', array( $instance, 'handle_save_sync' ) );
		add_action( 'admin_post_mailmojo_test_connection', array( $instance, 'handle_test_connection' ) );
		add_action( 'admin_post_mailmojo_regenerate_app_password', array( $instance, 'handle_regenerate_app_password' ) );
		add_action( 'admin_post_mailmojo_reset', array( $instance, 'handle_reset' ) );
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

	public function enqueue_admin_assets( string $hook ): void {
		if ( 'toplevel_page_mailmojo' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'mailmojo-admin',
			plugins_url( 'assets/admin.css', __DIR__ ),
			array(),
			'1.0.0'
		);
	}

	public function register_rest_routes(): void {
		register_rest_route(
			'mailmojo/v1',
			'/popups',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'callback'            => array( $this, 'rest_get_popups' ),
			)
		);
	}

	public function rest_get_popups( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$token = $this->get_access_token();
		if ( '' === $token ) {
			return new WP_Error(
				'mailmojo_no_token',
				__( 'Mailmojo access token is not configured.', 'mailmojo' ),
				array( 'status' => 400 )
			);
		}

		try {
			return rest_ensure_response(
				array(
					'popups' => $this->api->get_published_popups( $token ),
				)
			);
		} catch ( Throwable $error ) {
			$this->api->store_last_api_error( $this->api->format_error( $error ) );
			return new WP_Error(
				'mailmojo_popup_fetch_failed',
				__( 'There was a problem retrieving popup forms from Mailmojo.', 'mailmojo' ),
				array( 'status' => 500 )
			);
		}
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'mailmojo' ) );
		}

		$token = $this->get_access_token();

		$has_sdk_snippet = $this->api->has_sdk_snippet();
		if ( '' !== $token && ! $has_sdk_snippet ) {
			$has_sdk_snippet = $this->api->fetch_sdk_snippet( $token ) || $this->api->has_sdk_snippet();
		}

		if ( $has_sdk_snippet ) {
			$this->api->clear_last_api_error();
		}

		$status          = $this->get_connection_status();
		$sync_enabled    = $this->sync->is_content_sync_enabled();
		$app_status      = $this->sync->get_application_password_status();
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
		$api_error_notice   = $this->api->get_last_api_error_notice();
		$replace_url        = wp_nonce_url( admin_url( 'admin.php?page=mailmojo&mailmojo_replace_token=1' ), 'mailmojo_replace_token' );
		$cancel_replace_url = admin_url( 'admin.php?page=mailmojo' );
		$show_token_form    = ( ! $token ) || $replace_token;
		$show_token_features = $token && ! $show_token_form;
		$regenerate_url     = wp_nonce_url( admin_url( 'admin-post.php?action=mailmojo_regenerate_app_password' ), 'mailmojo_regenerate_app_password' );

		?>
		<div class="wrap">
			<h1 style="line-height: 1; margin-bottom: 4px;">
				<img
					src="<?php echo esc_url( plugins_url( 'assets/mailmojo-logo.svg', __DIR__ ) ); ?>"
					alt="Mailmojo"
					width="160"
					style="display: block;"
				/>
			</h1>
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

			<p style="max-width: 600px; font-size: 15px; line-height: 1.6;"><?php esc_html_e( 'The easiest way to add Mailmojo subscribe forms to your WordPress site — and when you\'re ready, sync your posts directly into your newsletter editor so building campaigns takes minutes, not hours.', 'mailmojo' ); ?></p>

			<div class="mailmojo-features">
				<div class="mailmojo-feature">
					<span class="dashicons dashicons-groups" aria-hidden="true"></span>
					<div>
						<strong><?php esc_html_e( 'Collect subscribers', 'mailmojo' ); ?></strong>
						<p><?php esc_html_e( 'Embed signup forms and popups anywhere on your site.', 'mailmojo' ); ?></p>
					</div>
				</div>
				<div class="mailmojo-feature">
					<span class="dashicons dashicons-update" aria-hidden="true"></span>
					<div>
						<strong><?php esc_html_e( 'Sync content automatically', 'mailmojo' ); ?></strong>
						<p><?php esc_html_e( 'Posts and pages are sent to Mailmojo as you publish.', 'mailmojo' ); ?></p>
					</div>
				</div>
				<div class="mailmojo-feature">
					<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
					<div>
						<strong><?php esc_html_e( 'Send better campaigns', 'mailmojo' ); ?></strong>
						<p><?php esc_html_e( 'Reuse your WordPress content in email newsletters effortlessly.', 'mailmojo' ); ?></p>
					</div>
				</div>
			</div>

			<h2 class="mailmojo-section"><?php esc_html_e( 'Connect your Mailmojo account', 'mailmojo' ); ?></h2>
			<p class="description">
				<?php
				printf(
					/* translators: %s is a link to the Mailmojo WordPress integration page */
					esc_html__( 'Find your API token on the %s page in your Mailmojo account.', 'mailmojo' ),
					'<a href="https://v3.mailmojo.no/integrations/wordpress/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'WordPress integration', 'mailmojo' ) . '</a>'
				);
				?>
			</p>
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
				<h2 class="mailmojo-section"><?php esc_html_e( 'Sync content to your newsletters', 'mailmojo' ); ?></h2>
				<p class="description" style="max-width: 600px; margin-bottom: 12px;">
					<?php esc_html_e( 'When enabled, your WordPress posts and pages become available as drag-and-drop content blocks inside the Mailmojo newsletter editor — so you can build a campaign in minutes instead of copy-pasting from your site.', 'mailmojo' ); ?>
				</p>
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
									<?php esc_html_e( 'Enable content sync', 'mailmojo' ); ?>
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

			<?php if ( $token ) : ?>
				<h2 class="mailmojo-section"><?php esc_html_e( 'Reset', 'mailmojo' ); ?></h2>
				<p><?php esc_html_e( 'Remove the saved token and all plugin data. This lets you start the onboarding over from scratch.', 'mailmojo' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'mailmojo_reset' ); ?>
					<input type="hidden" name="action" value="mailmojo_reset" />
					<?php submit_button( __( 'Reset plugin data', 'mailmojo' ), 'delete' ); ?>
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
			return;
		}

		update_option( self::ACCESS_TOKEN_OPTION, $token, false );

		try {
			$this->api->test_connection( $token );
			$this->set_connection_status( 'connected', __( 'Connection successful.', 'mailmojo' ) );
			$this->api->fetch_sdk_snippet( $token );
			if ( $this->sync->is_content_sync_enabled() ) {
				$this->sync->sync_integration_status( $token );
			}
			$this->redirect_with_notice( 'connection_success' );
		} catch ( Throwable $error ) {
			$this->handle_connection_error( $error );
		}
	}

	public function handle_save_sync(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'mailmojo' ) );
		}

		check_admin_referer( 'mailmojo_save_sync' );

		$enabled = isset( $_POST['mailmojo_sync_enabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['mailmojo_sync_enabled'] ) );
		update_option( 'mailmojo_content_sync_enabled', $enabled ? '1' : '0', false );

		if ( $enabled ) {
			$token = $this->get_access_token();
			$this->sync->ensure_application_password();
			$app_status = $this->sync->get_application_password_status();
			if ( 'sent' !== $app_status['state'] ) {
				// Plaintext unavailable (existing password found) — regenerate so we can push credentials to Mailmojo.
				$this->sync->ensure_application_password( true );
				$app_status = $this->sync->get_application_password_status();
			}
			if ( 'sent' !== $app_status['state'] ) {
				$this->redirect_with_notice( 'sync_needs_attention' );
				return;
			}
		} else {
			$this->sync->set_application_password_status( 'not_created', __( 'Content sync is disabled.', 'mailmojo' ) );
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
			if ( $this->sync->is_content_sync_enabled() ) {
				$this->sync->set_application_password_status( 'error', __( 'Mailmojo token is missing.', 'mailmojo' ) );
			}
			$this->redirect_with_notice( 'token_missing' );
			return;
		}

		try {
			$this->api->test_connection( $token );
			$this->set_connection_status( 'connected', __( 'Connection successful.', 'mailmojo' ) );
			if ( $this->sync->is_content_sync_enabled() ) {
				$this->sync->sync_integration_status( $token );
			}
			$this->redirect_with_notice( 'connection_success' );
		} catch ( Throwable $error ) {
			if ( $this->sync->is_content_sync_enabled() ) {
				$this->sync->set_application_password_status( 'error', __( 'Mailmojo token could not be verified.', 'mailmojo' ) );
			}
			$this->handle_connection_error( $error );
		}
	}

	public function handle_regenerate_app_password(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'mailmojo' ) );
		}

		check_admin_referer( 'mailmojo_regenerate_app_password' );

		$this->sync->ensure_application_password( true );
		$app_status = $this->sync->get_application_password_status();
		if ( 'error' === $app_status['state'] ) {
			$this->redirect_with_notice( 'app_password_failed' );
			return;
		}

		$this->redirect_with_notice( 'app_password_updated' );
	}

	public function handle_reset(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'mailmojo' ) );
		}

		check_admin_referer( 'mailmojo_reset' );

		$this->delete_all_plugin_data();
		$this->redirect_with_notice( 'reset_done' );
	}

	private function delete_all_plugin_data(): void {
		$this->sync->delete_all_data();
		$this->api->delete_all_data();
		delete_option( self::ACCESS_TOKEN_OPTION );
		delete_option( self::CONNECTION_STATUS_OPTION );
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
			'token_missing'        => array(
				'type'    => 'error',
				'message' => __( 'Please enter a Mailmojo access token.', 'mailmojo' ),
			),
			'connection_success'   => array(
				'type'    => 'success',
				'message' => __( 'Connection successful.', 'mailmojo' ),
			),
			'connection_failed'    => array(
				'type'    => 'error',
				'message' => __( 'Connection failed. Review the status message for details.', 'mailmojo' ),
			),
			'app_password_updated' => array(
				'type'    => 'success',
				'message' => __( 'Connection refreshed.', 'mailmojo' ),
			),
			'app_password_failed'  => array(
				'type'    => 'error',
				'message' => __( 'Connection refresh failed. Please verify your Mailmojo token and try again.', 'mailmojo' ),
			),
			'sync_saved'           => array(
				'type'    => 'success',
				'message' => __( 'Content sync settings saved.', 'mailmojo' ),
			),
			'sync_needs_attention' => array(
				'type'    => 'warning',
				'message' => __( 'Content sync settings saved, but the connection needs attention.', 'mailmojo' ),
			),
			'reset_done'           => array(
				'type'    => 'success',
				'message' => __( 'Plugin data has been reset.', 'mailmojo' ),
			),
		);

		return $messages[ $notice ] ?? null;
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
			case 'not_available':
			case 'error':
				return 'mailmojo-status mailmojo-status--error';
			default:
				return 'mailmojo-status mailmojo-status--idle';
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
}
