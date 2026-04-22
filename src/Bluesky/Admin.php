<?php
/**
 * Bluesky bot admin UI.
 *
 * @package WPIS\BotBluesky
 */

namespace WPIS\BotBluesky;

use WPIS\Bots\DocsLinks;

/**
 * Settings page.
 */
final class Admin {

	private const SLUG = 'wpis-bot-bluesky';

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
		if ( class_exists( 'ActionScheduler_Versions', false ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'settings_page_' . self::SLUG !== $screen->id ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__(
			'Action Scheduler is not available. From the wpis-bots package run composer install (see vendor/woocommerce/action-scheduler), install the Action Scheduler plugin, or the site will fall back to WP-Cron.',
			'wpis-bot-bluesky'
		);
		echo '</p></div>';
	}

	/**
	 * @return void
	 */
	public static function menu(): void {
		add_options_page(
			__( 'WPIS Bluesky Bot', 'wpis-bot-bluesky' ),
			__( 'WPIS Bluesky Bot', 'wpis-bot-bluesky' ),
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
			'wpis_bot_bluesky',
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize' ),
			)
		);
	}

	/**
	 * @param mixed $input Raw.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ): array {
		$defaults = Settings::defaults();
		if ( ! is_array( $input ) ) {
			return $defaults;
		}
		$out = $defaults;

		$out['enabled'] = ! empty( $input['enabled'] ) ? 1 : 0;

		$out['service_url'] = BlueskyClient::normalize_service_url(
			isset( $input['service_url'] ) ? (string) $input['service_url'] : ''
		);

		$out['identifier'] = isset( $input['identifier'] )
			? sanitize_text_field( (string) $input['identifier'] ) : '';

		$out['app_password'] = isset( $input['app_password'] )
			? sanitize_text_field( (string) $input['app_password'] ) : '';

		$out['search_query'] = isset( $input['search_query'] )
			? sanitize_text_field( (string) $input['search_query'] ) : 'WordPress is';

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

		echo '<div class="wrap"><h1>' . esc_html__( 'WPIS Bluesky Bot', 'wpis-bot-bluesky' ) . '</h1>';
		DocsLinks::render_bluesky_intro();
		echo '<form method="post" action="options.php">';
		settings_fields( 'wpis_bot_bluesky' );
		$s = Settings::get();
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable bot', 'wpis-bot-bluesky' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( Settings::OPTION ); ?>[enabled]" value="1" <?php checked( $s['enabled'], 1 ); ?> /> <?php esc_html_e( 'Poll Bluesky on a schedule', 'wpis-bot-bluesky' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><label for="wpis_b_service"><?php esc_html_e( 'Service URL', 'wpis-bot-bluesky' ); ?></label></th>
				<td><input name="<?php echo esc_attr( Settings::OPTION ); ?>[service_url]" id="wpis_b_service" type="url" class="regular-text" value="<?php echo esc_attr( (string) $s['service_url'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wpis_b_id"><?php esc_html_e( 'Bluesky handle or email', 'wpis-bot-bluesky' ); ?></label></th>
				<td><input name="<?php echo esc_attr( Settings::OPTION ); ?>[identifier]" id="wpis_b_id" type="text" class="regular-text" autocomplete="username" value="<?php echo esc_attr( (string) $s['identifier'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wpis_b_pw"><?php esc_html_e( 'App password', 'wpis-bot-bluesky' ); ?></label></th>
				<td><input name="<?php echo esc_attr( Settings::OPTION ); ?>[app_password]" id="wpis_b_pw" type="password" class="regular-text" autocomplete="new-password" value="<?php echo esc_attr( (string) $s['app_password'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wpis_b_q"><?php esc_html_e( 'Search query', 'wpis-bot-bluesky' ); ?></label></th>
				<td><input name="<?php echo esc_attr( Settings::OPTION ); ?>[search_query]" id="wpis_b_q" type="text" class="regular-text" value="<?php echo esc_attr( (string) $s['search_query'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wpis_b_interval"><?php esc_html_e( 'Poll interval (minutes)', 'wpis-bot-bluesky' ); ?></label></th>
				<td><input name="<?php echo esc_attr( Settings::OPTION ); ?>[poll_interval_minutes]" id="wpis_b_interval" type="number" min="<?php echo (int) Settings::MIN_POLL_INTERVAL_MINUTES; ?>" max="120" value="<?php echo (int) $s['poll_interval_minutes']; ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wpis_b_dedup"><?php esc_html_e( 'Dedup threshold (0–100)', 'wpis-bot-bluesky' ); ?></label></th>
				<td><input name="<?php echo esc_attr( Settings::OPTION ); ?>[dedup_threshold]" id="wpis_b_dedup" type="number" min="0" max="100" value="<?php echo (int) $s['dedup_threshold']; ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wpis_b_kw"><?php esc_html_e( 'Keyword patterns (one per line)', 'wpis-bot-bluesky' ); ?></label></th>
				<td><textarea name="<?php echo esc_attr( Settings::OPTION ); ?>[keyword_patterns]" id="wpis_b_kw" class="large-text" rows="6"><?php echo esc_textarea( (string) $s['keyword_patterns'] ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="wpis_b_pll"><?php esc_html_e( 'Polylang language slug (optional)', 'wpis-bot-bluesky' ); ?></label></th>
				<td><input name="<?php echo esc_attr( Settings::OPTION ); ?>[polylang_slug]" id="wpis_b_pll" type="text" class="regular-text" value="<?php echo esc_attr( (string) $s['polylang_slug'] ); ?>" /></td>
			</tr>
		</table>
		<?php
		submit_button();
		echo '</form>';

		echo '<h2>' . esc_html__( 'Recent runs', 'wpis-bot-bluesky' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Time', 'wpis-bot-bluesky' ) . '</th>';
		echo '<th>' . esc_html__( 'Candidates', 'wpis-bot-bluesky' ) . '</th>';
		echo '<th>' . esc_html__( 'Created', 'wpis-bot-bluesky' ) . '</th>';
		echo '<th>' . esc_html__( 'Bumped', 'wpis-bot-bluesky' ) . '</th>';
		echo '<th>' . esc_html__( 'Skipped (keyword / seen)', 'wpis-bot-bluesky' ) . '</th>';
		echo '<th>' . esc_html__( 'Errors', 'wpis-bot-bluesky' ) . '</th>';
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
			echo '<tr><td colspan="6">' . esc_html__( 'No runs yet.', 'wpis-bot-bluesky' ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
