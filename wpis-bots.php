<?php
/**
 * Plugin Name: WordPress Is… Bots
 * Description: Mastodon and Bluesky ingestion bots for WPIS Core — quote candidates for moderation.
 * Version: 0.1.0
 * Author: Jasonnade
 * Author URI: https://jasonrouet.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpis-bots
 * Requires at least: 6.9
 * Requires PHP: 8.2
 * Requires Plugins: wpis-plugin, action-scheduler
 * GitHub Plugin URI: https://github.com/jaz-on/wpis-bots
 * Primary Branch: main
 *
 * @package WPIS\Bots
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WPIS_BOTS_PLUGIN_FILE' ) ) {
	define( 'WPIS_BOTS_PLUGIN_FILE', __FILE__ );
}

add_filter(
	'plugin_action_links_' . plugin_basename( WPIS_BOTS_PLUGIN_FILE ),
	static function ( array $links ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $links;
		}
		$url = admin_url( 'admin.php?page=wpis-bot-mastodon' );
		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $url ),
				esc_html__( 'Settings', 'wpis-bots' )
			)
		);
		return $links;
	}
);

if ( ! defined( 'WPIS_BOTS_DIR' ) ) {
	define( 'WPIS_BOTS_DIR', __DIR__ );
}

if ( ! defined( 'WPIS_BOTS_LIB_DIR' ) ) {
	define( 'WPIS_BOTS_LIB_DIR', WPIS_BOTS_DIR . '/lib' );
}

require_once WPIS_BOTS_LIB_DIR . '/autoload-runtime.php';
wpis_bots_load_autoloader();

require_once WPIS_BOTS_LIB_DIR . '/BotsAdminMenu.php';
require_once WPIS_BOTS_LIB_DIR . '/CoreDependency.php';
require_once WPIS_BOTS_LIB_DIR . '/RunLogsAdmin.php';
require_once WPIS_BOTS_LIB_DIR . '/DocsLinks.php';
require_once WPIS_BOTS_LIB_DIR . '/HttpRateContext.php';
require_once WPIS_BOTS_LIB_DIR . '/TextHelper.php';
require_once WPIS_BOTS_LIB_DIR . '/ProcessedRemoteIds.php';
require_once WPIS_BOTS_LIB_DIR . '/RunLogger.php';
require_once WPIS_BOTS_LIB_DIR . '/QuoteIngest.php';

add_filter( 'cron_schedules', array( \WPIS\BotMastodon\Scheduler::class, 'add_cron_schedules' ) );
add_filter( 'cron_schedules', array( \WPIS\BotBluesky\Scheduler::class, 'add_cron_schedules' ) );

register_activation_hook(
	__FILE__,
	static function () {
		if ( class_exists( '\WPIS\BotMastodon\Scheduler' ) ) {
			\WPIS\BotMastodon\Scheduler::activate();
		}
		if ( class_exists( '\WPIS\BotBluesky\Scheduler' ) ) {
			\WPIS\BotBluesky\Scheduler::activate();
		}
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		if ( class_exists( '\WPIS\BotMastodon\Scheduler' ) ) {
			\WPIS\BotMastodon\Scheduler::deactivate();
		}
		if ( class_exists( '\WPIS\BotBluesky\Scheduler' ) ) {
			\WPIS\BotBluesky\Scheduler::deactivate();
		}
	}
);

// Sidebar menu must register even if WPIS Core loads later — see Plugin::bootstrap().
add_action(
	'plugins_loaded',
	static function () {
		\WPIS\Bots\BotsAdminMenu::register();
	},
	1
);
( new \WPIS\BotMastodon\Plugin() )->register();
( new \WPIS\BotBluesky\Plugin() )->register();
