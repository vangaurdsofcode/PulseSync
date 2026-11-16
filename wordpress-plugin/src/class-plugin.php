<?php
/**
 * Main plugin lifecycle, settings, REST endpoint, and event queue.
 *
 * @package PulseSync
 */

namespace PulseSync;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Connects WordPress lead capture to the PulseSync API.
 */
class Plugin {
	/**
	 * Option key for plugin settings.
	 *
	 * @var string
	 */
	public const OPTION = 'pulsesync_settings';

	/**
	 * Cron hook for queue retries.
	 *
	 * @var string
	 */
	public const CRON_HOOK = 'pulsesync_retry_queue';

	/**
	 * Register lifecycle-independent hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- The connector contract retries every five minutes.
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedule' ) );
		add_action( self::CRON_HOOK, array( $this, 'retry_queue' ) );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_widget' ) );
		add_action( 'wp_footer', array( $this, 'render_widget' ) );
	}

	/**
	 * Create the durable event queue and schedule retries.
	 *
	 * @return void
	 */
	public static function activate(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'pulsesync_queue';
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_uuid char(36) NOT NULL,
			payload longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'queued',
			attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			synced_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_uuid (event_uuid),
			KEY status_created (status, created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 300, 'every_five_minutes', self::CRON_HOOK );
		}
	}

	/**
	 * Remove scheduled queue retries.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Register the five-minute retry interval.
	 *
	 * @param array<string, array<string, int|string>> $schedules Existing schedules.
	 * @return array<string, array<string, int|string>>
	 */
	public function add_cron_schedule( array $schedules ): array {
		$schedules['every_five_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every five minutes', 'pulsesync-connector' ),
		);

		return $schedules;
	}

	/**
	 * Add the plugin settings screen beneath Settings.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {
		add_options_page(
			__( 'PulseSync Settings', 'pulsesync-connector' ),
			__( 'PulseSync', 'pulsesync-connector' ),
			'manage_options',
			'pulsesync',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings fields with WordPress.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'pulsesync_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(
					'endpoint' => '',
					'api_key'  => '',
				),
			)
		);

		add_settings_section(
			'pulsesync_connection',
			__( 'API connection', 'pulsesync-connector' ),
			static function (): void {
				echo '<p>' . esc_html__( 'Connect this site to your PulseSync workspace.', 'pulsesync-connector' ) . '</p>';
			},
			'pulsesync'
		);

		add_settings_field(
			'pulsesync_endpoint',
			__( 'API endpoint', 'pulsesync-connector' ),
			array( $this, 'render_endpoint_field' ),
			'pulsesync',
			'pulsesync_connection'
		);

		add_settings_field(
			'pulsesync_api_key',
			__( 'Site API token', 'pulsesync-connector' ),
			array( $this, 'render_api_key_field' ),
			'pulsesync',
			'pulsesync_connection'
		);
	}

	/**
	 * Sanitize connection settings.
	 *
	 * @param mixed $settings Submitted settings.
	 * @return array{endpoint: string, api_key: string}
	 */
	public function sanitize_settings( $settings ): array {
		$settings = is_array( $settings ) ? $settings : array();
		$endpoint = isset( $settings['endpoint'] ) ? esc_url_raw( trim( (string) $settings['endpoint'] ), array( 'https', 'http' ) ) : '';
		$api_key  = isset( $settings['api_key'] ) ? sanitize_text_field( (string) $settings['api_key'] ) : '';
		$saved    = get_option( self::OPTION, array() );

		if ( '' === $api_key && is_array( $saved ) && ! empty( $saved['api_key'] ) ) {
			$api_key = $saved['api_key'];
		}

		if ( '' !== $endpoint && ! wp_http_validate_url( $endpoint ) ) {
			add_settings_error( self::OPTION, 'invalid_endpoint', __( 'Enter a valid API endpoint URL.', 'pulsesync-connector' ) );
			$endpoint = '';
		}

		return array(
			'endpoint' => untrailingslashit( $endpoint ),
			'api_key'  => $api_key,
		);
	}

	/**
	 * Render the API endpoint field.
	 *
	 * @return void
	 */
	public function render_endpoint_field(): void {
		$settings = get_option( self::OPTION, array() );
		$value    = isset( $settings['endpoint'] ) ? $settings['endpoint'] : '';
		printf(
			'<input class="regular-text" type="url" name="%1$s[endpoint]" value="%2$s" placeholder="https://api.example.com/api/v1/events" required>',
			esc_attr( self::OPTION ),
			esc_attr( $value )
		);
	}

	/**
	 * Render the secret token field without echoing the saved token.
	 *
	 * @return void
	 */
	public function render_api_key_field(): void {
		$settings = get_option( self::OPTION, array() );
		printf(
			'<input class="regular-text" type="password" name="%1$s[api_key]" value="" autocomplete="new-password" placeholder="%2$s">',
			esc_attr( self::OPTION ),
			empty( $settings['api_key'] ) ? esc_attr__( 'Paste your site token', 'pulsesync-connector' ) : esc_attr__( 'Token saved — leave blank to keep it', 'pulsesync-connector' )
		);
	}

	/**
	 * Render the settings form.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'PulseSync', 'pulsesync-connector' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'pulsesync_settings_group' );
				do_settings_sections( 'pulsesync' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register the public lead-capture route.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'pulsesync/v1',
			'/events',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'capture_event' ),
				'permission_callback' => array( $this, 'verify_public_nonce' ),
			)
		);
	}

	/**
	 * Require a same-site REST nonce for the public form.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return bool|WP_Error
	 */
	public function verify_public_nonce( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'pulsesync_invalid_nonce', __( 'Please refresh the page and try again.', 'pulsesync-connector' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Accept a lead, persist it, and attempt immediate delivery.
	 *
	 * @param WP_REST_Request $request Incoming form values.
	 * @return WP_REST_Response|WP_Error
	 */
	public function capture_event( WP_REST_Request $request ) {
		$ip_address  = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$event_type  = sanitize_key( (string) $request->get_param( 'type' ) );
		$event_type  = in_array( $event_type, array( 'lead', 'page_view' ), true ) ? $event_type : 'lead';
		$rate_key    = 'pulsesync_rate_' . $event_type . '_' . md5( $ip_address );
		$submissions = (int) get_transient( $rate_key );
		$limit       = 'page_view' === $event_type ? 120 : 10;

		if ( $submissions >= $limit ) {
			return new WP_Error( 'pulsesync_rate_limited', __( 'Please wait before sending another message.', 'pulsesync-connector' ), array( 'status' => 429 ) );
		}

		set_transient( $rate_key, $submissions + 1, MINUTE_IN_SECONDS );
		$fields  = $request->get_param( 'fields' );
		$fields  = is_array( $fields ) ? $fields : array();
		$payload = array(
			'type'       => 'lead',
			'page_url'   => esc_url_raw( (string) $request->get_param( 'page_url' ) ),
			'browser'    => sanitize_text_field( (string) $request->get_param( 'browser' ) ),
			'fields'     => array(
				'name'    => sanitize_text_field( (string) ( $fields['name'] ?? '' ) ),
				'email'   => sanitize_email( (string) ( $fields['email'] ?? '' ) ),
				'message' => sanitize_textarea_field( (string) ( $fields['message'] ?? '' ) ),
			),
			'created_at' => current_time( 'c', true ),
		);

		if ( empty( $payload['page_url'] ) || ( 'lead' === $event_type && ( empty( $payload['fields']['email'] ) || ! is_email( $payload['fields']['email'] ) ) ) ) {
			return new WP_Error( 'pulsesync_invalid_lead', __( 'Add a valid email address and try again.', 'pulsesync-connector' ), array( 'status' => 400 ) );
		}

		$payload['type'] = $event_type;
		$event_uuid      = wp_generate_uuid4();
		$encoded         = wp_json_encode( $payload );

		if ( false === $encoded ) {
			return new WP_Error( 'pulsesync_encode_failed', __( 'Your message could not be saved.', 'pulsesync-connector' ), array( 'status' => 500 ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'pulsesync_queue';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Queue events require durable writes to a custom table.
		$inserted = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (event_uuid, payload, status, created_at) VALUES (%s, %s, %s, %s)',
				$table_name,
				$event_uuid,
				$encoded,
				'queued',
				current_time( 'mysql', true )
			)
		);

		if ( false === $inserted ) {
			return new WP_Error( 'pulsesync_queue_failed', __( 'Your message could not be saved. Please try again.', 'pulsesync-connector' ), array( 'status' => 500 ) );
		}

		$this->dispatch_event( (int) $wpdb->insert_id, $event_uuid, $payload, false );

		return new WP_REST_Response( array( 'data' => array( 'accepted' => true ) ), 202 );
	}

	/**
	 * Retry unsynced events in batches.
	 *
	 * @return void
	 */
	public function retry_queue(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'pulsesync_queue';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Queue events require durable reads from a custom table.
		$events = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, event_uuid, payload FROM %i WHERE status = %s ORDER BY id ASC LIMIT %d',
				$table_name,
				'queued',
				20
			),
			ARRAY_A
		);

		if ( null === $events ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Surface queue persistence failures to the site operator.
			error_log( 'PulseSync could not read the local event queue.' );
			return;
		}

		foreach ( $events as $event ) {
			$payload = json_decode( $event['payload'], true );
			if ( ! is_array( $payload ) ) {
				$this->record_failure( (int) $event['id'] );
				continue;
			}

			$this->dispatch_event( (int) $event['id'], $event['event_uuid'], $payload );
		}
	}

	/**
	 * Send one queued event to the configured API.
	 *
	 * @param int                  $queue_id Local queue row.
	 * @param string               $event_uuid Stable idempotency key.
	 * @param array<string, mixed> $payload Captured form data.
	 * @param bool                 $blocking Whether to wait for the remote response.
	 * @return void
	 */
	private function dispatch_event( int $queue_id, string $event_uuid, array $payload, bool $blocking = true ): void {
		$settings = get_option( self::OPTION, array() );
		if ( empty( $settings['endpoint'] ) || empty( $settings['api_key'] ) ) {
			return;
		}

		$response = wp_remote_post(
			$settings['endpoint'],
			array(
				'timeout'     => $blocking ? 8 : 0.01,
				'blocking'    => $blocking,
				'redirection' => 0,
				'headers'     => array(
					'Authorization' => 'Bearer ' . $settings['api_key'],
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'        => wp_json_encode(
					array(
						'external_id' => $event_uuid,
						'type'        => $payload['type'],
						'occurred_at' => $payload['created_at'],
						'payload'     => array(
							'page_url' => $payload['page_url'],
							'browser'  => $payload['browser'],
							'fields'   => $payload['fields'],
						),
						'metadata'    => array( 'source' => 'wordpress-widget' ),
					)
				),
			)
		);

		if ( ! $blocking ) {
			if ( is_wp_error( $response ) ) {
				$this->record_failure( $queue_id );
			}
			return;
		}

		if ( ! is_wp_error( $response ) && in_array( wp_remote_retrieve_response_code( $response ), array( 200, 202 ), true ) ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Update the durable state in the custom queue table.
			$wpdb->query(
				$wpdb->prepare(
					'UPDATE ' . $wpdb->prefix . 'pulsesync_queue SET status = %s, synced_at = %s, attempts = attempts + 1 WHERE id = %d',
					'sent',
					current_time( 'mysql', true ),
					$queue_id
				)
			);
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Surface delivery failures without logging submitted lead data.
		error_log( 'PulseSync delivery failed for queue row ' . $queue_id . '.' );
		$this->record_failure( $queue_id );
	}

	/**
	 * Increment retry attempts after a failed delivery.
	 *
	 * @param int $queue_id Queue row identifier.
	 * @return void
	 */
	private function record_failure( int $queue_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Update the durable retry count in the custom queue table.
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . $wpdb->prefix . 'pulsesync_queue SET attempts = attempts + 1 WHERE id = %d',
				$queue_id
			)
		);
	}

	/**
	 * Enqueue assets only when a configured widget is available.
	 *
	 * @return void
	 */
	public function enqueue_widget(): void {
		$settings = get_option( self::OPTION, array() );
		if ( empty( $settings['endpoint'] ) || empty( $settings['api_key'] ) ) {
			return;
		}

		wp_enqueue_style( 'pulsesync-widget', PULSESYNC_PLUGIN_URL . 'assets/widget.css', array(), PULSESYNC_VERSION );
		wp_enqueue_script( 'pulsesync-widget', PULSESYNC_PLUGIN_URL . 'assets/widget.js', array(), PULSESYNC_VERSION, true );
		wp_localize_script(
			'pulsesync-widget',
			'pulseSyncWidget',
			array(
				'endpoint' => esc_url_raw( rest_url( 'pulsesync/v1/events' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'labels'   => array(
					'open'    => __( 'Share feedback', 'pulsesync-connector' ),
					'sending' => __( 'Sending…', 'pulsesync-connector' ),
					'success' => __( 'Thanks — your message is on its way.', 'pulsesync-connector' ),
					'error'   => __( 'Something went wrong. Please try again.', 'pulsesync-connector' ),
				),
			)
		);
	}

	/**
	 * Render the non-intrusive feedback widget.
	 *
	 * @return void
	 */
	public function render_widget(): void {
		$settings = get_option( self::OPTION, array() );
		if ( empty( $settings['endpoint'] ) || empty( $settings['api_key'] ) ) {
			return;
		}
		?>
		<div class="pulsesync-widget" data-pulsesync-widget>
			<button class="pulsesync-toggle" type="button" aria-expanded="false" aria-controls="pulsesync-panel">
				<span aria-hidden="true">✦</span> <?php echo esc_html__( 'Share feedback', 'pulsesync-connector' ); ?>
			</button>
			<form class="pulsesync-panel" id="pulsesync-panel" hidden>
				<div class="pulsesync-panel-heading">
					<div><span><?php echo esc_html__( 'WE’RE LISTENING', 'pulsesync-connector' ); ?></span><h2><?php echo esc_html__( 'How can we help?', 'pulsesync-connector' ); ?></h2></div>
					<button class="pulsesync-close" type="button" aria-label="<?php echo esc_attr__( 'Close', 'pulsesync-connector' ); ?>">×</button>
				</div>
				<label><?php echo esc_html__( 'Your name', 'pulsesync-connector' ); ?><input name="name" maxlength="120" autocomplete="name"></label>
				<label><?php echo esc_html__( 'Email address', 'pulsesync-connector' ); ?><input name="email" type="email" maxlength="254" autocomplete="email" required></label>
				<label><?php echo esc_html__( 'Message', 'pulsesync-connector' ); ?><textarea name="message" rows="3" maxlength="1000"></textarea></label>
				<button class="pulsesync-submit" type="submit"><?php echo esc_html__( 'Send message', 'pulsesync-connector' ); ?> <span aria-hidden="true">→</span></button>
				<p class="pulsesync-status" aria-live="polite"></p>
			</form>
		</div>
		<?php
	}
}
