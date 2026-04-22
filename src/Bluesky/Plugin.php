<?php
/**
 * Bluesky bot bootstrap.
 *
 * @package WPIS\BotBluesky
 */

namespace WPIS\BotBluesky;

use WPIS\Bots\BotsAdminMenu;

/**
 * Registers hooks once WPIS Core is available.
 */
final class Plugin {

	/**
	 * Wire WordPress hooks.
	 */
	public function register(): void {
		add_action( 'plugins_loaded', array( $this, 'bootstrap' ), 100 );
	}

	/**
	 * Ensure wpis-plugin is loaded.
	 */
	public function bootstrap(): void {
		if ( ! function_exists( 'wpis_submit_quote_candidate' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>';
					esc_html_e(
						'WPIS Bots requires the WPIS Core plugin. Install and activate wpis-plugin.',
						'wpis-bots'
					);
					echo '</p></div>';
				}
			);
			return;
		}

		BotsAdminMenu::register();
		Scheduler::register_hooks();
		Admin::register();
	}
}
