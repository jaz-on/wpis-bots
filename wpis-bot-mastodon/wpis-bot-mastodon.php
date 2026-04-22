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

if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

if ( class_exists( '\WPIS\BotMastodon\Plugin' ) ) {
	( new \WPIS\BotMastodon\Plugin() )->register();
}
