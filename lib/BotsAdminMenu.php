<?php
/**
 * Top-level admin menu for both platform bots (visible sidebar entry).
 *
 * @package WPIS\Bots
 */

namespace WPIS\Bots;

/**
 * Registers one parent menu and two submenus (avoids burying under Settings).
 */
final class BotsAdminMenu {

	/**
	 * Whether admin_menu has already been wired (both bootstraps call register()).
	 *
	 * @var bool
	 */
	private static bool $hooked = false;

	/**
	 * @return void
	 */
	public static function register(): void {
		if ( self::$hooked ) {
			return;
		}
		self::$hooked = true;
		add_action( 'admin_menu', array( self::class, 'add_menus' ), 9 );
	}

	/**
	 * @return void
	 */
	public static function add_menus(): void {
		add_menu_page(
			__( 'WPIS Bots', 'wpis-bots' ),
			__( 'WPIS Bots', 'wpis-bots' ),
			'manage_options',
			'wpis-bot-mastodon',
			array( \WPIS\BotMastodon\Admin::class, 'render_page' ),
			'dashicons-share',
			56
		);
		add_submenu_page(
			'wpis-bot-mastodon',
			__( 'Mastodon', 'wpis-bots' ),
			__( 'Mastodon', 'wpis-bots' ),
			'manage_options',
			'wpis-bot-mastodon',
			array( \WPIS\BotMastodon\Admin::class, 'render_page' )
		);
		add_submenu_page(
			'wpis-bot-mastodon',
			__( 'Bluesky', 'wpis-bots' ),
			__( 'Bluesky', 'wpis-bots' ),
			'manage_options',
			'wpis-bot-bluesky',
			array( \WPIS\BotBluesky\Admin::class, 'render_page' )
		);
	}
}
