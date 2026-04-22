<?php
/**
 * Mastodon bot settings (single option array).
 *
 * @package WPIS\BotMastodon
 */

namespace WPIS\BotMastodon;

/**
 * Defaults and option helpers.
 */
final class Settings {

	public const MIN_POLL_INTERVAL_MINUTES = 10;

	public const MIN_BACKFILL_INTERVAL_MINUTES = 30;

	public const OPTION = 'wpis_bot_mastodon_options';

	public const STATE_OPTION = 'wpis_bot_mastodon_state';

	public const SEEN_OPTION = 'wpis_bot_mastodon_seen_ids';

	public const LOG_OPTION = 'wpis_bot_mastodon_run_log';

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'enabled'                            => 0,
			'instance_url'                       => 'https://mastodon.social',
			'instance_urls'                      => array( 'https://mastodon.social' ),
			'access_token'                       => '',
			'hashtag'                            => 'wordpress',
			'poll_interval_minutes'              => 15,
			'backfill_enabled'                   => 0,
			'backfill_interval_minutes'          => 360,
			'backfill_max_requests_per_instance' => 1,
			'dedup_threshold'                    => 70,
			'keyword_patterns'                   => "WordPress is\nWordpress is",
			'polylang_slug'                      => '',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$v = get_option( self::OPTION, array() );
		if ( ! is_array( $v ) ) {
			$v = array();
		}
		$out = array_merge( self::defaults(), $v );

		if ( ! isset( $out['instance_urls'] ) || ! is_array( $out['instance_urls'] ) || array() === $out['instance_urls'] ) {
			$out['instance_urls'] = array( MastodonClient::normalize_instance_url( (string) $out['instance_url'] ) );
		} else {
			$clean = array();
			foreach ( $out['instance_urls'] as $u ) {
				$n = MastodonClient::normalize_instance_url( (string) $u );
				if ( '' !== $n && ! in_array( $n, $clean, true ) ) {
					$clean[] = $n;
				}
			}
			$out['instance_urls'] = array() !== $clean ? $clean : array( MastodonClient::normalize_instance_url( (string) $out['instance_url'] ) );
		}

		$out['backfill_interval_minutes'] = max( self::MIN_BACKFILL_INTERVAL_MINUTES, min( 24 * 60, (int) $out['backfill_interval_minutes'] ) );
		$out['backfill_max_requests_per_instance'] = max( 1, min( 10, (int) $out['backfill_max_requests_per_instance'] ) );

		return $out;
	}

	/**
	 * Normalized instance bases (ordered, unique).
	 *
	 * @return list<string>
	 */
	public static function get_instance_bases(): array {
		$s = self::get();
		$u = isset( $s['instance_urls'] ) && is_array( $s['instance_urls'] ) ? $s['instance_urls'] : array();
		$out = array();
		foreach ( $u as $url ) {
			$n = MastodonClient::normalize_instance_url( (string) $url );
			if ( '' !== $n && ! in_array( $n, $out, true ) ) {
				$out[] = $n;
			}
		}
		if ( array() === $out ) {
			$out[] = MastodonClient::normalize_instance_url( (string) $s['instance_url'] );
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $settings Sanitized settings.
	 * @return void
	 */
	public static function update( array $settings ): void {
		update_option( self::OPTION, array_merge( self::defaults(), $settings ), false );
	}

	/**
	 * @return array{
	 *   since_by_instance: array<string, string>,
	 *   backfill_by_instance: array<string, array{max_id: string, done: bool}>
	 * }
	 */
	public static function get_state(): array {
		$s = get_option( self::STATE_OPTION, array() );
		if ( ! is_array( $s ) ) {
			$s = array();
		}

		$since = array();
		if ( isset( $s['since_by_instance'] ) && is_array( $s['since_by_instance'] ) ) {
			foreach ( $s['since_by_instance'] as $k => $v ) {
				$key = MastodonClient::normalize_instance_url( (string) $k );
				if ( '' !== $key ) {
					$since[ $key ] = (string) $v;
				}
			}
		}

		$backfill = array();
		if ( isset( $s['backfill_by_instance'] ) && is_array( $s['backfill_by_instance'] ) ) {
			foreach ( $s['backfill_by_instance'] as $k => $row ) {
				$key = MastodonClient::normalize_instance_url( (string) $k );
				if ( '' === $key || ! is_array( $row ) ) {
					continue;
				}
				$backfill[ $key ] = array(
					'max_id' => isset( $row['max_id'] ) ? (string) $row['max_id'] : '',
					'done'   => ! empty( $row['done'] ),
				);
			}
		}

		$bases  = self::get_instance_bases();
		$legacy = isset( $s['since_id'] ) ? (string) $s['since_id'] : '';
		if ( '' !== $legacy && array() === $since && array() !== $bases ) {
			$since[ $bases[0] ] = $legacy;
		}

		return array(
			'since_by_instance'     => $since,
			'backfill_by_instance'  => $backfill,
		);
	}

	/**
	 * @param array{
	 *   since_by_instance?: array<string, string>,
	 *   backfill_by_instance?: array<string, array{max_id?: string, done?: bool}>
	 * } $state State patch.
	 * @return void
	 */
	public static function set_state( array $state ): void {
		$cur = get_option( self::STATE_OPTION, array() );
		if ( ! is_array( $cur ) ) {
			$cur = array();
		}

		if ( isset( $state['since_by_instance'] ) && is_array( $state['since_by_instance'] ) ) {
			$cur['since_by_instance'] = array();
			foreach ( $state['since_by_instance'] as $k => $v ) {
				$key = MastodonClient::normalize_instance_url( (string) $k );
				if ( '' !== $key ) {
					$cur['since_by_instance'][ $key ] = (string) $v;
				}
			}
		}

		if ( isset( $state['backfill_by_instance'] ) && is_array( $state['backfill_by_instance'] ) ) {
			if ( ! isset( $cur['backfill_by_instance'] ) || ! is_array( $cur['backfill_by_instance'] ) ) {
				$cur['backfill_by_instance'] = array();
			}
			foreach ( $state['backfill_by_instance'] as $k => $row ) {
				$key = MastodonClient::normalize_instance_url( (string) $k );
				if ( '' === $key || ! is_array( $row ) ) {
					continue;
				}
				$cur['backfill_by_instance'][ $key ] = array(
					'max_id' => isset( $row['max_id'] ) ? (string) $row['max_id'] : '',
					'done'   => ! empty( $row['done'] ),
				);
			}
		}

		unset( $cur['since_id'] );
		update_option( self::STATE_OPTION, $cur, false );
	}
}
