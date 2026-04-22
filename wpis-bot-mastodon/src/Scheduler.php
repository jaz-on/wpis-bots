<?php
/**
 * Recurring poll scheduling (Action Scheduler preferred, WP-Cron fallback).
 *
 * @package WPIS\BotMastodon
 */

namespace WPIS\BotMastodon;

/**
 * Registers and clears scheduled hooks.
 */
final class Scheduler {

	public const HOOK = 'wpis_bot_mastodon_poll';

	public const GROUP = 'wpis-bots';

	/**
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( self::HOOK, array( Poller::class, 'run' ) );
		add_action( 'update_option_' . Settings::OPTION, array( self::class, 'on_settings_update' ), 10, 3 );
	}

	/**
	 * @param mixed  $old_value Old option value.
	 * @param mixed  $value     New value.
	 * @param string $option    Option name.
	 * @return void
	 */
	public static function on_settings_update( $old_value, $value, string $option ): void {
		unset( $old_value, $option );
		if ( is_array( $value ) ) {
			self::reschedule();
		}
	}

	/**
	 * @param array<string, array{interval: int, display: string}> $schedules Schedules.
	 * @return array<string, array{interval: int, display: string}>
	 */
	public static function add_cron_schedules( array $schedules ): array {
		$mins              = (int) Settings::get()['poll_interval_minutes'];
		$mins              = max( Settings::MIN_POLL_INTERVAL_MINUTES, min( 120, $mins ) );
		$key               = 'wpis_bot_mastodon_every_' . $mins . '_min';
		$schedules[ $key ] = array(
			'interval' => $mins * MINUTE_IN_SECONDS,
			/* translators: %d: minutes */
			'display'  => sprintf( __( 'Every %d minutes (WPIS Mastodon)', 'wpis-bot-mastodon' ), $mins ),
		);
		return $schedules;
	}

	/**
	 * @return void
	 */
	public static function reschedule(): void {
		self::clear_all();
		$s = Settings::get();
		if ( empty( $s['enabled'] ) ) {
			return;
		}

		$mins      = max( Settings::MIN_POLL_INTERVAL_MINUTES, min( 120, (int) $s['poll_interval_minutes'] ) );
		$interval  = $mins * MINUTE_IN_SECONDS;
		$cron_name = 'wpis_bot_mastodon_every_' . $mins . '_min';

		if ( function_exists( 'as_schedule_recurring_action' ) ) {
			as_schedule_recurring_action( time(), $interval, self::HOOK, array(), self::GROUP );
			return;
		}

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 60, $cron_name, self::HOOK );
		}
	}

	/**
	 * @return void
	 */
	public static function clear_all(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, array(), self::GROUP );
		}
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * @return void
	 */
	public static function activate(): void {
		self::reschedule();
	}

	/**
	 * @return void
	 */
	public static function deactivate(): void {
		self::clear_all();
	}
}
