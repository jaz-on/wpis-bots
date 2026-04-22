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
				/* translators: 1: guide URL, 2: limits URL, 3: resources URL */
				__( 'Documentation : <a href="%1$s" target="_blank" rel="noopener noreferrer">Guide administrateur</a> (français, accessible), <a href="%2$s" target="_blank" rel="noopener noreferrer">limites des API</a> et <a href="%3$s" target="_blank" rel="noopener noreferrer">ressources Mastodon / Bluesky</a>.', 'wpis-bot-mastodon' ),
				esc_url( self::base_url() . 'guide-admin.md' ),
				esc_url( self::base_url() . 'limites-api-et-bonnes-pratiques.md' ),
				esc_url( self::base_url() . 'ressources.md' )
			)
		);
	}

	/**
	 * @return void
	 */
	public static function render_bluesky_intro(): void {
		self::emit_intro(
			sprintf(
				/* translators: 1: guide URL, 2: limits URL, 3: resources URL */
				__( 'Documentation : <a href="%1$s" target="_blank" rel="noopener noreferrer">Guide administrateur</a> (français, accessible), <a href="%2$s" target="_blank" rel="noopener noreferrer">limites des API</a> et <a href="%3$s" target="_blank" rel="noopener noreferrer">ressources Mastodon / Bluesky</a>.', 'wpis-bot-bluesky' ),
				esc_url( self::base_url() . 'guide-admin.md' ),
				esc_url( self::base_url() . 'limites-api-et-bonnes-pratiques.md' ),
				esc_url( self::base_url() . 'ressources.md' )
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
	 * Full URL to a shipped markdown file under docs/ on GitHub (respects wpis_bots_docs_base_url).
	 *
	 * @param string $file e.g. guide-admin.md
	 * @return string
	 */
	public static function shipped_doc_url( string $file ): string {
		return self::base_url() . ltrim( $file, '/' );
	}

	/**
	 * @param string $url              Admin URL.
	 * @param string $translated_label Already translated anchor text.
	 * @return string Safe HTML (same window).
	 */
	public static function admin_anchor( string $url, string $translated_label ): string {
		return '<a href="' . esc_url( $url ) . '">' . esc_html( $translated_label ) . '</a>';
	}

	/**
	 * @return array<string, array<string, bool>>
	 */
	public static function admin_link_allowed_tags(): array {
		return array(
			'a' => array(
				'href' => true,
			),
		);
	}

	/**
	 * @param string $html Already translated HTML with escaped URLs.
	 * @return void
	 */
	private static function emit_intro( string $html ): void {
		$allowed = self::external_link_allowed_tags();
		echo '<div class="notice notice-info inline"><p>';
		echo wp_kses( $html, $allowed );
		echo '</p></div>';
	}

	/**
	 * @return array<string, array<string, bool>>
	 */
	public static function external_link_allowed_tags(): array {
		return array(
			'a' => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
		);
	}

	/**
	 * @param string $url              Full URL.
	 * @param string $translated_label Already translated anchor text.
	 * @return string Safe HTML (opens in a new tab).
	 */
	public static function external_anchor( string $url, string $translated_label ): string {
		return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $translated_label ) . '</a>';
	}
}
