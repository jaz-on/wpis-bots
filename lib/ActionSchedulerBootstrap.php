<?php
/**
 * Loads bundled Action Scheduler when no other copy has started.
 *
 * @package WPIS\Bots
 */

namespace WPIS\Bots;

/**
 * WooCommerce Action Scheduler is installed under this plugin's vendor/ via Composer.
 */
final class ActionSchedulerBootstrap {

	/**
	 * @return void
	 */
	public static function load(): void {
		if ( class_exists( 'ActionScheduler_Versions', false ) ) {
			return;
		}
		$pkg = dirname( __DIR__ ) . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
		if ( is_readable( $pkg ) ) {
			require_once $pkg;
		}
	}
}
