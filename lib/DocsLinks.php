<?php
/**
 * Admin UI links to shipped documentation.
 *
 * @package WPIS\Bots
 */

namespace WPIS\Bots;

/**
 * Renders a short intro box on bot settings screens.
 */
final class DocsLinks {

	/**
	 * @return void
	 */
	public static function render_mastodon_intro(): void {
		self::emit_intro(
			sprintf(
				/* translators: 1: URL guide admin, 2: URL limites API */
				__( 'Documentation : <a href="%1$s">Guide administrateur</a> (français, accessible) et <a href="%2$s">Limites des API et bonnes pratiques</a>.', 'wpis-bot-mastodon' ),
				esc_url( self::base_url() . 'GUIDE-ADMIN.md' ),
				esc_url( self::base_url() . 'LIMITES-API-ET-BONNES-PRATIQUES.md' )
			)
		);
	}

	/**
	 * @return void
	 */
	public static function render_bluesky_intro(): void {
		self::emit_intro(
			sprintf(
				/* translators: 1: URL guide admin, 2: URL limites API */
				__( 'Documentation : <a href="%1$s">Guide administrateur</a> (français, accessible) et <a href="%2$s">Limites des API et bonnes pratiques</a>.', 'wpis-bot-bluesky' ),
				esc_url( self::base_url() . 'GUIDE-ADMIN.md' ),
				esc_url( self::base_url() . 'LIMITES-API-ET-BONNES-PRATIQUES.md' )
			)
		);
	}

	/**
	 * @return string
	 */
	private static function base_url(): string {
		return (string) apply_filters(
			'wpis_bots_docs_base_url',
			'https://github.com/jaz-on/wpis-bots/blob/main/docs/'
		);
	}

	/**
	 * @param string $html Already translated HTML with escaped URLs.
	 * @return void
	 */
	private static function emit_intro( string $html ): void {
		$allowed = array(
			'a' => array(
				'href' => array(),
			),
		);
		echo '<div class="notice notice-info inline"><p>';
		echo wp_kses( $html, $allowed );
		echo '</p></div>';
	}
}
