<?php
/**
 * Bluesky bot bootstrap.
 *
 * @package WPIS\BotBluesky
 */

namespace WPIS\BotBluesky;

/**
 * Registers hooks once WPIS Core is available.
 */
final class Plugin {

	/**
	 * Wire WordPress hooks.
	 */
	public function register(): void {
		add_action( 'plugins_loaded', array( $this, 'bootstrap' ), 20 );
	}

	/**
	 * Ensure wpis-plugin is loaded.
	 */
	public function bootstrap(): void {
		if ( ! function_exists( 'wpis_find_potential_duplicates' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>';
					esc_html_e(
						'WPIS Bot (Bluesky) requires the WPIS Core plugin. Install and activate wpis-plugin.',
						'wpis-bot-bluesky'
					);
					echo '</p></div>';
				}
			);
			return;
		}

		Scheduler::register_hooks();
		Admin::register();
	}
}
