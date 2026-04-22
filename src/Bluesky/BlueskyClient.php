<?php
/**
 * Bluesky AT Protocol client (session + searchPosts).
 *
 * @package WPIS\BotBluesky
 */

namespace WPIS\BotBluesky;

use WPIS\Bots\HttpRateContext;

/**
 * XRPC calls via wp_remote_*.
 */
final class BlueskyClient {

	private const USER_AGENT_PRODUCT = 'WPIS-Bot-Bluesky/0.1';

	/**
	 * @param string $service Normalized service URL (no trailing slash).
	 * @param string $identifier Handle or email.
	 * @param string $app_password App password.
	 * @return array{accessJwt: string, refreshJwt: string}|\WP_Error
	 */
	public static function create_session( string $service, string $identifier, string $app_password ) {
		$url      = $service . '/xrpc/com.atproto.server.createSession';
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 25,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
					'User-Agent'   => HttpRateContext::user_agent( self::USER_AGENT_PRODUCT ),
				),
				'body'    => wp_json_encode(
					array(
						'identifier' => $identifier,
						'password'   => $app_password,
					)
				),
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
			$slug   = 429 === (int) $code ? 'wpis_bluesky_auth_ratelimit' : 'wpis_bluesky_auth';
			$detail = self::xrpc_error_body_detail( $body );
			$msg    = sprintf(
				/* translators: 1: HTTP status, 2: header hint */
				__( 'Bluesky auth HTTP %1$d%2$s', 'wpis-bot-bluesky' ),
				(int) $code,
				$hint
			);
			if ( '' !== $detail ) {
				$msg .= ' — ' . $detail;
			}
			return new \WP_Error( $slug, $msg, $ctx );
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || empty( $data['accessJwt'] ) ) {
			$fallback = self::xrpc_error_body_detail( $body );
			$msg      = __( 'Invalid session response (missing access token).', 'wpis-bot-bluesky' );
			if ( '' !== $fallback ) {
				$msg .= ' — ' . $fallback;
			}
			return new \WP_Error( 'wpis_bluesky_auth', $msg );
		}

		return array(
			'accessJwt'  => (string) $data['accessJwt'],
			'refreshJwt' => isset( $data['refreshJwt'] ) ? (string) $data['refreshJwt'] : '',
		);
	}

	/**
	 * @param string $service Service URL.
	 * @param string $refresh Refresh JWT.
	 * @return array{accessJwt: string, refreshJwt: string}|\WP_Error
	 */
	public static function refresh_session( string $service, string $refresh ) {
		$url      = $service . '/xrpc/com.atproto.server.refreshSession';
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 25,
				'headers' => array(
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . $refresh,
					'User-Agent'    => HttpRateContext::user_agent( self::USER_AGENT_PRODUCT ),
				),
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
			$detail = self::xrpc_error_body_detail( $body );
			$msg    = sprintf(
				/* translators: 1: HTTP status, 2: header hint */
				__( 'Bluesky refresh HTTP %1$d%2$s', 'wpis-bot-bluesky' ),
				(int) $code,
				$hint
			);
			if ( '' !== $detail ) {
				$msg .= ' — ' . $detail;
			}
			return new \WP_Error( 'wpis_bluesky_refresh', $msg, $ctx );
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || empty( $data['accessJwt'] ) ) {
			$fallback = self::xrpc_error_body_detail( $body );
			$msg      = __( 'Invalid refresh response (missing access token).', 'wpis-bot-bluesky' );
			if ( '' !== $fallback ) {
				$msg .= ' — ' . $fallback;
			}
			return new \WP_Error( 'wpis_bluesky_refresh', $msg );
		}

		return array(
			'accessJwt'  => (string) $data['accessJwt'],
			'refreshJwt' => isset( $data['refreshJwt'] ) ? (string) $data['refreshJwt'] : $refresh,
		);
	}

	/**
	 * @param string $service    Service URL.
	 * @param string $access_jwt Access JWT.
	 * @param string $query      Search q.
	 * @param string $cursor     Pagination cursor.
	 * @param int    $limit      Result limit.
	 * @return array{posts: array<int, array{uri: string, text: string}>, cursor: string}|\WP_Error
	 */
	public static function search_posts( string $service, string $access_jwt, string $query, string $cursor = '', int $limit = 25 ) {
		$url  = $service . '/xrpc/app.bsky.feed.searchPosts';
		$args = array(
			'q'     => $query,
			'limit' => max( 1, min( 100, $limit ) ),
		);
		if ( '' !== $cursor ) {
			$args['cursor'] = $cursor;
		}
		$url = add_query_arg( $args, $url );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 25,
				'headers' => array(
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . $access_jwt,
					'User-Agent'    => HttpRateContext::user_agent( self::USER_AGENT_PRODUCT ),
				),
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
			$slug   = 429 === (int) $code ? 'wpis_bluesky_search_ratelimit' : 'wpis_bluesky_search';
			$merged = array_merge( $ctx, array( 'status' => (int) $code ) );
			$detail = self::xrpc_error_body_detail( $body );
			$msg    = sprintf(
				/* translators: 1: HTTP status, 2: header hint */
				__( 'Bluesky search HTTP %1$d%2$s', 'wpis-bot-bluesky' ),
				(int) $code,
				$hint
			);
			if ( '' !== $detail ) {
				$msg .= ' — ' . $detail;
			}
			return new \WP_Error( $slug, $msg, $merged );
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			$fallback = self::xrpc_error_body_detail( $body );
			$msg      = __( 'Invalid search response (not JSON).', 'wpis-bot-bluesky' );
			if ( '' !== $fallback ) {
				$msg .= ' — ' . $fallback;
			}
			return new \WP_Error( 'wpis_bluesky_search', $msg );
		}

		$posts_raw = isset( $data['posts'] ) && is_array( $data['posts'] ) ? $data['posts'] : array();
		$out       = array();
		foreach ( $posts_raw as $post ) {
			if ( ! is_array( $post ) ) {
				continue;
			}
			$uri = isset( $post['uri'] ) ? (string) $post['uri'] : '';
			if ( '' === $uri ) {
				continue;
			}
			$record = isset( $post['record'] ) && is_array( $post['record'] ) ? $post['record'] : array();
			$text   = isset( $record['text'] ) ? (string) $record['text'] : '';
			$text   = trim( $text );
			if ( '' === $text ) {
				continue;
			}
			$out[] = array(
				'uri'  => $uri,
				'text' => $text,
				'url'  => self::uri_to_web_url( $uri ),
			);
		}

		$next_cursor = isset( $data['cursor'] ) ? (string) $data['cursor'] : '';

		return array(
			'posts'  => $out,
			'cursor' => $next_cursor,
		);
	}

	/**
	 * @param string $uri AT URI.
	 * @return string Web URL or empty.
	 */
	public static function uri_to_web_url( string $uri ): string {
		if ( preg_match( '#^at://([^/]+)/app\.bsky\.feed\.post/([^/]+)#', $uri, $m ) ) {
			return 'https://bsky.app/profile/' . rawurlencode( $m[1] ) . '/post/' . rawurlencode( $m[2] );
		}
		return '';
	}

	/**
	 * @param string $url Raw service URL.
	 * @return string Normalized origin.
	 */
	public static function normalize_service_url( string $url ): string {
		$url = trim( $url );
		$url = esc_url_raw( $url );
		if ( '' === $url ) {
			return 'https://bsky.social';
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return 'https://bsky.social';
		}
		$scheme = isset( $parts['scheme'] ) && 'http' === $parts['scheme'] ? 'http' : 'https';
		$host   = $parts['host'];
		$port   = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		return $scheme . '://' . $host . $port;
	}

	/**
	 * Extracts XRPC error fields (error + message) or a short plain-text fallback for admin messages.
	 *
	 * @param string $body Raw HTTP body.
	 * @return string One line, empty if nothing usable.
	 */
	private static function xrpc_error_body_detail( string $body ): string {
		$body = trim( $body );
		if ( '' === $body ) {
			return '';
		}
		$data = json_decode( $body, true );
		if ( is_array( $data ) ) {
			$err = isset( $data['error'] ) ? trim( (string) $data['error'] ) : '';
			$msg = isset( $data['message'] ) ? trim( (string) $data['message'] ) : '';
			$out = '';
			if ( '' !== $err && '' !== $msg && strcasecmp( $err, $msg ) !== 0 ) {
				$out = $err . ': ' . $msg;
			} elseif ( '' !== $msg ) {
				$out = $msg;
			} else {
				$out = $err;
			}
			if ( '' !== $out ) {
				return self::truncate_error_detail( $out );
			}
		}
		$plain = wp_strip_all_tags( $body );
		$plain = preg_replace( '/\s+/', ' ', $plain );
		$plain = trim( (string) $plain );
		if ( '' === $plain ) {
			return '';
		}
		return self::truncate_error_detail( $plain );
	}

	/**
	 * @param string $text Raw detail.
	 * @param int    $max  Max character length (UTF-8 safe when mbstring is available).
	 * @return string
	 */
	private static function truncate_error_detail( string $text, int $max = 400 ): string {
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			if ( mb_strlen( $text, 'UTF-8' ) <= $max ) {
				return $text;
			}
			return mb_substr( $text, 0, $max - 3, 'UTF-8' ) . '...';
		}
		if ( strlen( $text ) <= $max ) {
			return $text;
		}
		return substr( $text, 0, $max - 3 ) . '...';
	}
}
