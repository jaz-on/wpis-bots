<?php
/**
 * Mastodon bot bootstrap.
 *
 * @package WPIS\BotMastodon
 */

namespace WPIS\BotMastodon;

use WPIS\Bots\CoreDependency;

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
	 * Ensure wpis-core is loaded; surface an admin notice otherwise.
	 */
	public function bootstrap(): void {
		Admin::register();
		if ( ! CoreDependency::is_core_ready() ) {
			return;
		}
		Scheduler::register_hooks();
	}
}
