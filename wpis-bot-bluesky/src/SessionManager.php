<?php
/**
 * Caches Bluesky JWT in a transient between polls.
 *
 * @package WPIS\BotBluesky
 */

namespace WPIS\BotBluesky;

/**
 * Obtains a valid access JWT.
 */
final class SessionManager {

	private const TRANSIENT = 'wpis_bot_bluesky_session_v1';

	private const TTL = 3000;

	/**
	 * @param array<string, mixed> $settings Settings array.
	 * @return string|\WP_Error Access JWT.
	 */
	public static function get_access_jwt( array $settings ) {
		$cached = get_transient( self::TRANSIENT );
		if ( is_array( $cached ) && ! empty( $cached['accessJwt'] ) ) {
			return (string) $cached['accessJwt'];
		}

		$identifier   = trim( (string) ( $settings['identifier'] ?? '' ) );
		$app_password = (string) ( $settings['app_password'] ?? '' );
		if ( '' === $identifier || '' === $app_password ) {
			return new \WP_Error( 'wpis_bluesky_creds', 'Bluesky identifier and app password are required.' );
		}

		$service = BlueskyClient::normalize_service_url( (string) ( $settings['service_url'] ?? '' ) );
		$session = BlueskyClient::create_session( $service, $identifier, $app_password );
		if ( is_wp_error( $session ) ) {
			return $session;
		}

		set_transient( self::TRANSIENT, $session, self::TTL );
		return (string) $session['accessJwt'];
	}

	/**
	 * @return void
	 */
	public static function flush(): void {
		delete_transient( self::TRANSIENT );
	}

	/**
	 * @param string               $refresh Refresh JWT.
	 * @param array<string, mixed> $settings Settings (service URL).
	 * @return string|\WP_Error New access JWT.
	 */
	public static function refresh_access_jwt( string $refresh, array $settings ) {
		$service = BlueskyClient::normalize_service_url( (string) ( $settings['service_url'] ?? '' ) );
		$session = BlueskyClient::refresh_session( $service, $refresh );
		if ( is_wp_error( $session ) ) {
			delete_transient( self::TRANSIENT );
			return $session;
		}
		set_transient( self::TRANSIENT, $session, self::TTL );
		return (string) $session['accessJwt'];
	}

	/**
	 * @return array{accessJwt?: string, refreshJwt?: string}|false
	 */
	public static function get_cached_raw() {
		$c = get_transient( self::TRANSIENT );
		return is_array( $c ) ? $c : false;
	}
}
