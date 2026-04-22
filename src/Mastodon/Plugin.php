<?php
/**
 * Mastodon bot bootstrap.
 *
 * @package WPIS\BotMastodon
 */

namespace WPIS\BotMastodon;

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
	 * Ensure wpis-plugin is loaded; surface an admin notice otherwise.
	 */
	public function bootstrap(): void {
		Admin::register();
		if ( ! function_exists( 'wpis_submit_quote_candidate' ) ) {
			return;
		}
		Scheduler::register_hooks();
	}
}
