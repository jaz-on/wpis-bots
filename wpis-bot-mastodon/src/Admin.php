<?php
/**
 * Settings UI and run log.
 *
 * @package WPIS\BotMastodon
 */

namespace WPIS\BotMastodon;

use WPIS\Bots\DocsLinks;

/**
 * Options page under Settings.
 */
final class Admin {

	private const SLUG = 'wpis-bot-mastodon';

	/**
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'admin_notices', array( self::class, 'notice_action_scheduler' ) );
	}

	/**
	 * @return void
	 */
	public static function notice_action_scheduler(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'settings_page_' . self::SLUG !== $screen->id ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__(
			'Action Scheduler is not loaded. Install the Action Scheduler plugin for reliable bot scheduling, or the site will fall back to WP-Cron.',
			'wpis-bot-mastodon'
		);
		echo '</p></div>';
	}

	/**
	 * @return void
	 */
	public static function menu(): void {
		add_options_page(
			__( 'WPIS Mastodon Bot', 'wpis-bot-mastodon' ),
			__( 'WPIS Mastodon Bot', 'wpis-bot-mastodon' ),
			'manage_options',
			self::SLUG,
			array( self::class, 'render_page' )
		);
	}

	/**
	 * @return void
	 */
	public static function register_settings(): void {
		register_setting(
			'wpis_bot_mastodon',
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize' ),
			)
		);
	}

	/**
	 * @param mixed $input Raw settings.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ): array {
		$defaults = Settings::defaults();
		if ( ! is_array( $input ) ) {
			return $defaults;
		}
		$out = $defaults;

		$out['enabled'] = ! empty( $input['enabled'] ) ? 1 : 0;

		$out['instance_url'] = MastodonClient::normalize_instance_url(
			isset( $input['instance_url'] ) ? (string) $input['instance_url'] : ''
		);

		$out['access_token'] = isset( $input['access_token'] )
			? sanitize_text_field( (string) $input['access_token'] ) : '';

		$tag            = isset( $input['hashtag'] ) ? (string) $input['hashtag'] : '';
		$tag            = ltrim( trim( $tag ), '#' );
		$tag            = strtolower( preg_replace( '/[^a-zA-Z0-9_]/', '', $tag ) );
		$out['hashtag'] = '' !== $tag ? $tag : 'WordPress';

		$out['poll_interval_minutes'] = isset( $input['poll_interval_minutes'] )
			? max( Settings::MIN_POLL_INTERVAL_MINUTES, min( 120, (int) $input['poll_interval_minutes'] ) ) : 15;

		$out['dedup_threshold'] = isset( $input['dedup_threshold'] )
			? max( 0, min( 100, (int) $input['dedup_threshold'] ) ) : 70;

		$out['keyword_patterns'] = isset( $input['keyword_patterns'] )
			? sanitize_textarea_field( (string) $input['keyword_patterns'] ) : $defaults['keyword_patterns'];

		$out['polylang_slug'] = isset( $input['polylang_slug'] )
			? sanitize_key( (string) $input['polylang_slug'] ) : '';

		return $out;
	}

	/**
	 * @return void
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$log = get_option( Settings::LOG_OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'WPIS Mastodon Bot', 'wpis-bot-mastodon' ) . '</h1>';
		DocsLinks::render_mastodon_intro();
		echo '<form method="post" action="options.php">';
		settings_fields( 'wpis_bot_mastodon' );
		$s = Settings::get();
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable bot', 'wpis-bot-mastodon' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( Settings::OPTION ); ?>[enabled]" value="1" <?php checked( $s['enabled'], 1 ); ?> /> <?php esc_html_e( 'Poll Mastodon on a schedule', 'wpis-bot-mastodon' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><label for="wpis_m_instance"><?php esc_html_e( 'Instance URL', 'wpis-bot-mastodon' ); ?></label></th>
				<td><input name="<?php echo esc_attr( Settings::OPTION ); ?>[instance_url]" id="wpis_m_instance" type="url" class="regular-text" value="<?php echo esc_attr( (string) $s['instance_url'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wpis_m_token"><?php esc_html_e( 'Access token (optional)', 'wpis-bot-mastodon' ); ?></label></th>
				<td><input name="<?php echo esc_attr( Settings::OPTION ); ?>[access_token]" id="wpis_m_token" type="password" class="regular-text" autocomplete="off" value="<?php echo esc_attr( (string) $s['access_token'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wpis_m_tag"><?php esc_html_e( 'Hashtag (no #)', 'wpis-bot-mastodon' ); ?></label></th>
				<td><input name="<?php echo esc_attr( Settings::OPTION ); ?>[hashtag]" id="wpis_m_tag" type="text" class="regular-text" value="<?php echo esc_attr( (string) $s['hashtag'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wpis_m_interval"><?php esc_html_e( 'Poll interval (minutes)', 'wpis-bot-mastodon' ); ?></label></th>
				<td><input name="<?php echo esc_attr( Settings::OPTION ); ?>[poll_interval_minutes]" id="wpis_m_interval" type="number" min="<?php echo (int) Settings::MIN_POLL_INTERVAL_MINUTES; ?>" max="120" value="<?php echo (int) $s['poll_interval_minutes']; ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wpis_m_dedup"><?php esc_html_e( 'Dedup threshold (0–100)', 'wpis-bot-mastodon' ); ?></label></th>
				<td><input name="<?php echo esc_attr( Settings::OPTION ); ?>[dedup_threshold]" id="wpis_m_dedup" type="number" min="0" max="100" value="<?php echo (int) $s['dedup_threshold']; ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wpis_m_kw"><?php esc_html_e( 'Keyword patterns (one per line, substring match)', 'wpis-bot-mastodon' ); ?></label></th>
				<td><textarea name="<?php echo esc_attr( Settings::OPTION ); ?>[keyword_patterns]" id="wpis_m_kw" class="large-text" rows="6"><?php echo esc_textarea( (string) $s['keyword_patterns'] ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="wpis_m_pll"><?php esc_html_e( 'Polylang language slug (optional)', 'wpis-bot-mastodon' ); ?></label></th>
				<td><input name="<?php echo esc_attr( Settings::OPTION ); ?>[polylang_slug]" id="wpis_m_pll" type="text" class="regular-text" value="<?php echo esc_attr( (string) $s['polylang_slug'] ); ?>" /></td>
			</tr>
		</table>
		<?php
		submit_button();
		echo '</form>';

		echo '<h2>' . esc_html__( 'Recent runs', 'wpis-bot-mastodon' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Time', 'wpis-bot-mastodon' ) . '</th>';
		echo '<th>' . esc_html__( 'Candidates', 'wpis-bot-mastodon' ) . '</th>';
		echo '<th>' . esc_html__( 'Created', 'wpis-bot-mastodon' ) . '</th>';
		echo '<th>' . esc_html__( 'Bumped', 'wpis-bot-mastodon' ) . '</th>';
		echo '<th>' . esc_html__( 'Skipped (keyword / seen)', 'wpis-bot-mastodon' ) . '</th>';
		echo '<th>' . esc_html__( 'Errors', 'wpis-bot-mastodon' ) . '</th>';
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
			echo '<tr><td colspan="6">' . esc_html__( 'No runs yet.', 'wpis-bot-mastodon' ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
