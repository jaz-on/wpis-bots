<?php
/**
 * Admin page listing poll run history for Mastodon and Bluesky.
 *
 * @package WPIS\Bots
 */

namespace WPIS\Bots;

/**
 * Renders WPIS Bots → Run logs.
 */
final class RunLogsAdmin {

	/**
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'WPIS Bots — run logs', 'wpis-bots' ) . '</h1>';

		echo '<p class="description">';
		printf(
			wp_kses(
				/* translators: 1: link to Mastodon bot settings, 2: link to Bluesky bot settings */
				__( 'Configure ingestion under %1$s or %2$s.', 'wpis-bots' ),
				DocsLinks::admin_link_allowed_tags()
			),
			DocsLinks::admin_anchor( admin_url( 'admin.php?page=wpis-bot-mastodon' ), __( 'WPIS Bots → Mastodon', 'wpis-bots' ) ),
			DocsLinks::admin_anchor( admin_url( 'admin.php?page=wpis-bot-bluesky' ), __( 'WPIS Bots → Bluesky', 'wpis-bots' ) )
		);
		echo '</p>';

		if ( CoreDependency::block_if_core_missing() ) {
			echo '</div>';
			return;
		}

		self::render_section(
			__( 'Mastodon', 'wpis-bots' ),
			\WPIS\BotMastodon\Settings::LOG_OPTION
		);
		self::render_section(
			__( 'Bluesky', 'wpis-bots' ),
			\WPIS\BotBluesky\Settings::LOG_OPTION
		);

		echo '</div>';
	}

	/**
	 * @param string $title       Heading.
	 * @param string $option_key Log option name.
	 * @return void
	 */
	private static function render_section( string $title, string $option_key ): void {
		$log = get_option( $option_key, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		echo '<h2>' . esc_html( $title ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Time (UTC)', 'wpis-bots' ) . '</th>';
		echo '<th>' . esc_html__( 'Candidates', 'wpis-bots' ) . '</th>';
		echo '<th>' . esc_html__( 'Created', 'wpis-bots' ) . '</th>';
		echo '<th>' . esc_html__( 'Bumped', 'wpis-bots' ) . '</th>';
		echo '<th>' . esc_html__( 'Skipped', 'wpis-bots' ) . '</th>';
		echo '<th>' . esc_html__( 'Errors', 'wpis-bots' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $log as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$at = isset( $row['at'] ) ? gmdate( 'Y-m-d H:i:s', (int) $row['at'] ) . ' UTC' : '—';
			$sk = (int) ( $row['skipped_keyword'] ?? 0 ) + (int) ( $row['skipped_seen'] ?? 0 );
			$er = isset( $row['errors'] ) && is_array( $row['errors'] ) ? implode( '; ', array_map( 'sanitize_text_field', $row['errors'] ) ) : '';
			echo '<tr>';
			echo '<td>' . esc_html( $at ) . '</td>';
			echo '<td>' . (int) ( $row['candidates'] ?? 0 ) . '</td>';
			echo '<td>' . (int) ( $row['created'] ?? 0 ) . '</td>';
			echo '<td>' . (int) ( $row['bumped'] ?? 0 ) . '</td>';
			echo '<td>' . (int) $sk . '</td>';
			echo '<td>' . esc_html( $er ) . '</td>';
			echo '</tr>';
		}

		if ( array() === $log ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No runs recorded yet.', 'wpis-bots' ) . '</td></tr>';
		}

		echo '</tbody></table>';
	}
}
