<?php
/**
 * Plugin Name: WordPress Is… Bot (Mastodon)
 * Description: Discovers quote candidates on Mastodon and submits them to WPIS Core for moderation.
 * Version: 0.1.0
 * Author: Jasonnade
 * Author URI: https://jasonrouet.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpis-bot-mastodon
 * Requires at least: 6.9
 * Requires PHP: 8.2
 * Requires Plugins: wpis-plugin
 *
 * @package WPIS\BotMastodon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WPIS_BOTS_LIB_DIR' ) ) {
	define( 'WPIS_BOTS_LIB_DIR', dirname( __DIR__ ) . '/lib' );
}

require_once WPIS_BOTS_LIB_DIR . '/DocsLinks.php';
require_once WPIS_BOTS_LIB_DIR . '/HttpRateContext.php';
require_once WPIS_BOTS_LIB_DIR . '/TextHelper.php';
require_once WPIS_BOTS_LIB_DIR . '/ProcessedRemoteIds.php';
require_once WPIS_BOTS_LIB_DIR . '/RunLogger.php';
require_once WPIS_BOTS_LIB_DIR . '/QuoteIngest.php';

if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

add_filter( 'cron_schedules', array( \WPIS\BotMastodon\Scheduler::class, 'add_cron_schedules' ) );

register_activation_hook(
	__FILE__,
	static function () {
		if ( class_exists( '\WPIS\BotMastodon\Scheduler' ) ) {
			\WPIS\BotMastodon\Scheduler::activate();
		}
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		if ( class_exists( '\WPIS\BotMastodon\Scheduler' ) ) {
			\WPIS\BotMastodon\Scheduler::deactivate();
		}
	}
);

if ( class_exists( '\WPIS\BotMastodon\Plugin' ) ) {
	( new \WPIS\BotMastodon\Plugin() )->register();
}
