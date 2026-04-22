<?php
/**
 * Mastodon REST client (tag timeline).
 *
 * @package WPIS\BotMastodon
 */

namespace WPIS\BotMastodon;

use WPIS\Bots\HttpRateContext;

/**
 * Fetches public hashtag timelines.
 */
final class MastodonClient {

	private const USER_AGENT_PRODUCT = 'WPIS-Bot-Mastodon/0.1';

	/**
	 * @param string $instance_base Normalized base URL (no trailing slash).
	 * @param string $hashtag       Without leading #.
	 * @param string $since_id      Optional snowflake id.
	 * @param string $access_token  Optional bearer token.
	 * @param int    $limit         Max statuses (max 40 typical).
	 * @return array<int, array{id: string, text: string, url: string}>|\WP_Error
	 */
	public static function fetch_tag_timeline(
		string $instance_base,
		string $hashtag,
		string $since_id = '',
		string $access_token = '',
		int $limit = 40
	) {
		$hashtag = ltrim( trim( $hashtag ), '#' );
		if ( '' === $hashtag ) {
			return new \WP_Error( 'wpis_mastodon_empty_tag', 'Hashtag is empty.' );
		}

		$path = '/api/v1/timelines/tag/' . rawurlencode( $hashtag );
		$url  = $instance_base . $path;
		$url  = add_query_arg(
			array(
				'limit' => max( 1, min( 40, $limit ) ),
			),
			$url
		);
		if ( '' !== $since_id ) {
			$url = add_query_arg( 'since_id', $since_id, $url );
		}

		$headers = array(
			'Accept'     => 'application/json',
			'User-Agent' => HttpRateContext::user_agent( self::USER_AGENT_PRODUCT ),
		);
		if ( '' !== $access_token ) {
			$headers['Authorization'] = 'Bearer ' . $access_token;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 25,
				'headers' => $headers,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( $code < 200 || $code >= 300 ) {
			$ctx    = HttpRateContext::from_response( $response );
			$hint   = HttpRateContext::format_hint( $ctx );
			$slug   = 429 === (int) $code ? 'wpis_mastodon_rate_limit' : 'wpis_mastodon_http';
			$detail = wp_strip_all_tags( (string) $body );
			if ( strlen( $detail ) > 200 ) {
				$detail = substr( $detail, 0, 200 ) . '…';
			}
			return new \WP_Error(
				$slug,
				sprintf(
					/* translators: 1: HTTP status code, 2: header hint, 3: body excerpt */
					__( 'Mastodon HTTP %1$d%2$s: %3$s', 'wpis-bot-mastodon' ),
					(int) $code,
					$hint,
					$detail
				),
				$ctx
			);
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'wpis_mastodon_json', 'Invalid JSON from Mastodon.' );
		}

		$out = array();
		foreach ( $data as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( ! empty( $row['reblog'] ) ) {
				continue;
			}
			$id = isset( $row['id'] ) ? (string) $row['id'] : '';
			if ( '' === $id ) {
				continue;
			}
			$content = isset( $row['content'] ) ? (string) $row['content'] : '';
			$text    = wp_strip_all_tags( html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			$url     = isset( $row['url'] ) ? (string) $row['url'] : '';
			if ( '' === trim( $text ) ) {
				continue;
			}
			$out[] = array(
				'id'   => $id,
				'text' => $text,
				'url'  => esc_url_raw( $url ),
			);
		}

		return $out;
	}

	/**
	 * @param string $url Raw instance URL from settings.
	 * @return string Normalized origin.
	 */
	public static function normalize_instance_url( string $url ): string {
		$url = trim( $url );
		$url = esc_url_raw( $url );
		if ( '' === $url ) {
			return 'https://mastodon.social';
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return 'https://mastodon.social';
		}
		$scheme = isset( $parts['scheme'] ) && 'http' === $parts['scheme'] ? 'http' : 'https';
		$host   = $parts['host'];
		$port   = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		return $scheme . '://' . $host . $port;
	}
}
