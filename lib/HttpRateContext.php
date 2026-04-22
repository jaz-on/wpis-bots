<?php
/**
 * Parse rate-limit related HTTP headers for logging and WP_Error data.
 *
 * @package WPIS\Bots
 */

namespace WPIS\Bots;

/**
 * Helpers for Retry-After and common rate limit headers.
 */
final class HttpRateContext {

	/**
	 * Extract structured context from a wp_remote_* response.
	 *
	 * @param array|\WP_Error $response HTTP response.
	 * @return array<string, int|string>
	 */
	public static function from_response( $response ): array {
		if ( \is_wp_error( $response ) ) {
			return array();
		}
		$code = wp_remote_retrieve_response_code( $response );
		$data = array( 'status' => (int) $code );
		foreach ( wp_remote_retrieve_headers( $response ) as $name => $value ) {
			$lower = strtolower( (string) $name );
			if ( 'retry-after' === $lower ) {
				$data['retry_after'] = (int) $value;
			} elseif ( 'x-ratelimit-limit' === $lower ) {
				$data['rate_limit_limit'] = (string) $value;
			} elseif ( 'x-ratelimit-remaining' === $lower ) {
				$data['rate_limit_remaining'] = (string) $value;
			} elseif ( 'x-ratelimit-reset' === $lower ) {
				$data['rate_limit_reset'] = (string) $value;
			} elseif ( 'ratelimit-limit' === $lower ) {
				$data['ratelimit_limit'] = (string) $value;
			} elseif ( 'ratelimit-remaining' === $lower ) {
				$data['ratelimit_remaining'] = (string) $value;
			} elseif ( 'ratelimit-reset' === $lower ) {
				$data['ratelimit_reset'] = (string) $value;
			}
		}
		return $data;
	}

	/**
	 * Short human-readable suffix for error messages (admin logs).
	 *
	 * @param array<string, int|string> $ctx From from_response().
	 * @return string
	 */
	public static function format_hint( array $ctx ): string {
		$parts = array();
		if ( isset( $ctx['retry_after'] ) && (int) $ctx['retry_after'] > 0 ) {
			$parts[] = sprintf( 'retry after %ds', (int) $ctx['retry_after'] );
		}
		if ( isset( $ctx['rate_limit_remaining'] ) ) {
			$parts[] = 'Mastodon X-RateLimit-Remaining: ' . $ctx['rate_limit_remaining'];
		}
		if ( isset( $ctx['rate_limit_reset'] ) ) {
			$parts[] = 'X-RateLimit-Reset: ' . $ctx['rate_limit_reset'];
		}
		if ( isset( $ctx['ratelimit_remaining'] ) ) {
			$parts[] = 'RateLimit-Remaining: ' . $ctx['ratelimit_remaining'];
		}
		return '' !== $parts ? ' (' . implode( '; ', $parts ) . ')' : '';
	}

	/**
	 * Default User-Agent for outbound API calls (filterable).
	 *
	 * @param string $product Short product token e.g. WPIS-Bot-Mastodon/0.1.
	 * @return string
	 */
	public static function user_agent( string $product ): string {
		$home = function_exists( 'home_url' ) ? home_url( '/' ) : '';
		$ua   = $product . ' (WordPress; +' . $home . ')';
		return (string) apply_filters( 'wpis_bots_http_user_agent', $ua, $product );
	}
}
