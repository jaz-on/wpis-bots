<?php
/**
 * Bluesky bot bootstrap.
 *
 * @package WPIS\BotBluesky
 */

namespace WPIS\BotBluesky;

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
	 * Ensure wpis-core is loaded.
	 */
	public function bootstrap(): void {
		Admin::register();
		if ( ! CoreDependency::is_core_ready() ) {
			return;
		}
		Scheduler::register_hooks();
	}
}
