<?php
/**
 * Bluesky bot settings.
 *
 * @package WPIS\BotBluesky
 */

namespace WPIS\BotBluesky;

/**
 * Defaults and option helpers.
 */
final class Settings {

	public const MIN_POLL_INTERVAL_MINUTES = 10;

	public const MIN_BACKFILL_INTERVAL_MINUTES = 30;

	public const OPTION = 'wpis_bot_bluesky_options';

	public const STATE_OPTION = 'wpis_bot_bluesky_state';

	public const SEEN_OPTION = 'wpis_bot_bluesky_seen_ids';

	public const LOG_OPTION = 'wpis_bot_bluesky_run_log';

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'enabled'                      => 0,
			'service_url'                 => 'https://bsky.social',
			'identifier'                 => '',
			'app_password'               => '',
			'search_query'              => 'WordPress is',
			'poll_interval_minutes'     => 15,
			'backfill_enabled'          => 0,
			'backfill_interval_minutes' => 360,
			'backfill_max_pages'         => 5,
			'dedup_threshold'           => 70,
			'keyword_patterns'         => "WordPress is\nWordpress is",
			'polylang_slug'            => '',
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
		$out['backfill_interval_minutes']         = max( self::MIN_BACKFILL_INTERVAL_MINUTES, min( 24 * 60, (int) $out['backfill_interval_minutes'] ) );
		$out['backfill_max_pages'] = max( 1, min( 25, (int) $out['backfill_max_pages'] ) );
		return $out;
	}

	/**
	 * @return array{cursor: string}
	 */
	public static function get_state(): array {
		$s = get_option( self::STATE_OPTION, array() );
		if ( ! is_array( $s ) ) {
			$s = array();
		}
		$c = isset( $s['cursor'] ) ? (string) $s['cursor'] : '';
		return array( 'cursor' => $c );
	}

	/**
	 * @param array{cursor?: string} $state State.
	 * @return void
	 */
	public static function set_state( array $state ): void {
		$cur = self::get_state();
		if ( array_key_exists( 'cursor', $state ) ) {
			$cur['cursor'] = (string) $state['cursor'];
		}
		update_option( self::STATE_OPTION, $cur, false );
	}
}
