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

	public const OPTION = 'wpis_bot_mastodon_options';

	public const STATE_OPTION = 'wpis_bot_mastodon_state';

	public const SEEN_OPTION = 'wpis_bot_mastodon_seen_ids';

	public const LOG_OPTION = 'wpis_bot_mastodon_run_log';

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'enabled'               => 0,
			'instance_url'          => 'https://mastodon.social',
			'access_token'          => '',
			'hashtag'               => 'wordpress',
			'poll_interval_minutes' => 15,
			'dedup_threshold'       => 70,
			'keyword_patterns'      => "WordPress is\nWordpress is",
			'polylang_slug'         => '',
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
		return array_merge( self::defaults(), $v );
	}

	/**
	 * @param array<string, mixed> $settings Sanitized settings.
	 * @return void
	 */
	public static function update( array $settings ): void {
		update_option( self::OPTION, array_merge( self::defaults(), $settings ), false );
	}

	/**
	 * @return array{since_id: string}
	 */
	public static function get_state(): array {
		$s = get_option( self::STATE_OPTION, array() );
		if ( ! is_array( $s ) ) {
			$s = array();
		}
		$since = isset( $s['since_id'] ) ? (string) $s['since_id'] : '';
		return array( 'since_id' => $since );
	}

	/**
	 * @param array{since_id?: string} $state State.
	 * @return void
	 */
	public static function set_state( array $state ): void {
		$cur = self::get_state();
		if ( isset( $state['since_id'] ) ) {
			$cur['since_id'] = (string) $state['since_id'];
		}
		update_option( self::STATE_OPTION, $cur, false );
	}
}
