<?php

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

function __( string $text, string $domain = 'default' ): string { return $text; }
function add_filter( ...$args ): void {}
function add_action( ...$args ): void {}
function register_activation_hook( ...$args ): void {}
function register_deactivation_hook( ...$args ): void {}
function wp_clear_scheduled_hook( string $hook ): void { $GLOBALS['cleared_hook'] = $hook; }
function wp_next_scheduled( string $hook ): bool { return ! empty( $GLOBALS['already_scheduled'] ); }
function wp_schedule_event( int $time, string $recurrence, string $hook ): void { $GLOBALS['scheduled_event'] = $recurrence; }
function get_option( string $option, $default = false ) { return $GLOBALS['test_options'][$option] ?? $default; }
function esc_url_raw( string $url, array $protocols = array() ): string { return in_array( parse_url( $url, PHP_URL_SCHEME ), $protocols, true ) ? $url : ''; }
function sanitize_text_field( string $value ): string { return trim( strip_tags( $value ) ); }
function sanitize_key( string $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function wp_http_validate_url( string $url ): bool { return (bool) filter_var( $url, FILTER_VALIDATE_URL ); }
function untrailingslashit( string $value ): string { return rtrim( $value, '/' ); }
function add_settings_error( ...$args ): void {}
function wp_remote_post( string $url, array $args ): array { $GLOBALS['remote_request'] = $args; return array( 'response' => array( 'code' => 202 ) ); }
function is_wp_error( $value ): bool { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code( array $response ): int { return $response['response']['code'] ?? 0; }
function current_time( string $type, bool $gmt = false ): string { return '2026-01-01 00:00:00'; }
function get_transient( string $key ) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient( string $key, $value, int $expiration ): bool { $GLOBALS['transients'][$key] = $value; return true; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_generate_uuid4(): string { return '684e1fc6-35ba-4f4f-86fd-61a7aed45bbc'; }
function is_email( string $email ): bool { return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL ); }

class FakeWpdb {
	public string $prefix = 'wp_';
	public int $insert_id = 1;
	public string $last_query = '';

	public function get_charset_collate(): string { return 'DEFAULT CHARACTER SET utf8mb4'; }
	public function prepare( string $query, ...$args ): string { $this->last_query = $query; return $query; }
	public function query( string $query ): int { $this->last_query = $query; return 1; }
	public function get_results( string $query, string $output ): array { return $GLOBALS['queued_events'] ?? array(); }
}

require_once dirname( __DIR__ ) . '/src/class-plugin.php';
