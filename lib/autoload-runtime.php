<?php
/**
 * PSR-4 autoloader for bot namespaces when Composer’s vendor/ is not present.
 *
 * @package WPIS\Bots
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register spl_autoload for WPIS\BotMastodon\ and WPIS\BotBluesky\ (same as composer.json).
 *
 * @return void
 */
function wpis_bots_register_psr4_autoload(): void {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;

	$map = array(
		'WPIS\\BotMastodon\\' => dirname( __DIR__ ) . '/src/Mastodon/',
		'WPIS\\BotBluesky\\'  => dirname( __DIR__ ) . '/src/Bluesky/',
	);

	spl_autoload_register(
		static function ( string $class_name ) use ( $map ): void {
			foreach ( $map as $prefix => $base_dir ) {
				$len = strlen( $prefix );
				if ( strncmp( $prefix, $class_name, $len ) !== 0 ) {
					continue;
				}
				$relative = str_replace( '\\', '/', substr( $class_name, $len ) );
				$file     = $base_dir . $relative . '.php';
				if ( is_readable( $file ) ) {
					require $file;
				}
				return;
			}
		}
	);
}

/**
 * Load Composer autoload if present, otherwise the runtime PSR-4 loader.
 *
 * @return void
 */
function wpis_bots_load_autoloader(): void {
	if ( is_readable( WPIS_BOTS_DIR . '/vendor/autoload.php' ) ) {
		require_once WPIS_BOTS_DIR . '/vendor/autoload.php';
		return;
	}
	wpis_bots_register_psr4_autoload();
}
