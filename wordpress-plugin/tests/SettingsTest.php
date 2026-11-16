<?php

use PHPUnit\Framework\TestCase;
use PulseSync\Plugin;

final class SettingsTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['test_options'] = array();
		$GLOBALS['transients']   = array();
		$GLOBALS['wpdb']         = new FakeWpdb();
	}

	public function test_settings_sanitize_endpoint_and_token(): void {
		$plugin = new Plugin();
		$clean  = $plugin->sanitize_settings(
			array(
				'endpoint' => 'https://analytics.example/api/v1/events/',
				'api_key'  => 'site_token_123',
			)
		);

		$this->assertSame( 'https://analytics.example/api/v1/events', $clean['endpoint'] );
		$this->assertSame( 'site_token_123', $clean['api_key'] );
	}

	public function test_settings_discard_invalid_endpoint_and_non_array_values(): void {
		$plugin = new Plugin();

		$this->assertSame(
			array( 'endpoint' => '', 'api_key' => '' ),
			$plugin->sanitize_settings( 'https://invalid.example' )
		);

		$clean = $plugin->sanitize_settings( array( 'endpoint' => 'javascript:alert(1)' ) );
		$this->assertSame( '', $clean['endpoint'] );
	}

	public function test_blank_secret_keeps_the_existing_token(): void {
		$GLOBALS['test_options'][Plugin::OPTION] = array( 'api_key' => 'existing-token' );
		$plugin = new Plugin();

		$clean = $plugin->sanitize_settings( array( 'endpoint' => 'https://analytics.example/api/v1/events' ) );

		$this->assertSame( 'existing-token', $clean['api_key'] );
	}

	public function test_retry_schedule_is_five_minutes_and_deactivation_clears_it(): void {
		$plugin = new Plugin();
		$schedules = $plugin->add_cron_schedule( array() );

		$this->assertSame( 300, $schedules['every_five_minutes']['interval'] );

		Plugin::deactivate();
		$this->assertSame( Plugin::CRON_HOOK, $GLOBALS['cleared_hook'] );
	}

	public function test_activation_creates_the_durable_queue_and_schedules_retries(): void {
		Plugin::activate();

		$this->assertStringContainsString( 'wp_pulsesync_queue', $GLOBALS['created_table_sql'] );
		$this->assertSame( 'every_five_minutes', $GLOBALS['scheduled_event'] );
	}

	public function test_retry_dispatches_queued_event_to_the_api(): void {
		$GLOBALS['test_options'][Plugin::OPTION] = array(
			'endpoint' => 'https://analytics.example/api/v1/events',
			'api_key'  => 'site-token',
		);
		$GLOBALS['queued_events'] = array(
			array(
				'id'         => 7,
				'event_uuid' => '684e1fc6-35ba-4f4f-86fd-61a7aed45bbc',
				'payload'    => json_encode(
					array(
						'type'       => 'lead',
						'created_at' => '2026-01-01T00:00:00Z',
						'page_url'   => 'https://example.test/contact',
						'browser'    => 'Firefox',
						'fields'     => array( 'email' => 'visitor@example.test' ),
					)
				),
			),
		);

		( new Plugin() )->retry_queue();

		$this->assertSame( 'Bearer site-token', $GLOBALS['remote_request']['headers']['Authorization'] );
		$this->assertStringContainsString( 'status = %s', $GLOBALS['wpdb']->last_query );
	}
}
