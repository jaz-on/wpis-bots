<?php
/**
 * Bluesky bot admin UI.
 *
 * @package WPIS\BotBluesky
 */

namespace WPIS\BotBluesky;

use WPIS\Bots\CoreDependency;
use WPIS\Bots\DocsLinks;

/**
 * Settings page.
 */
final class Admin {

	private const SLUG = 'wpis-bot-bluesky';

	/**
	 * Transient key suffix for poll feedback (per user).
	 */
	private const POLL_NOTICE_TRANSIENT = 'wpis_bot_bluesky_poll_notice';

	/**
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'admin_notices', array( self::class, 'notice_action_scheduler' ) );
		add_action( 'admin_notices', array( self::class, 'render_admin_notices' ) );
		add_action( 'admin_post_wpis_bot_bluesky_poll_now', array( self::class, 'handle_poll_now' ) );
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
		if ( ! \WPIS\Bots\BotsAdminMenu::is_plugin_admin_screen() ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__(
			'Action Scheduler is not available. Install and activate the Action Scheduler plugin (or rely on WP-Cron, which is less reliable on low traffic sites).',
			'wpis-bot-bluesky'
		);
		echo '</p></div>';
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
	 * Runs one poll immediately (admin test; uses saved credentials even if bot is off).
	 *
	 * @return void
	 */
	public static function handle_poll_now(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do this.', 'wpis-bot-bluesky' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'wpis_bot_bluesky_poll_now' );

		$redirect   = admin_url( 'admin.php?page=' . self::SLUG );
		$notice_key = self::POLL_NOTICE_TRANSIENT . '_' . get_current_user_id();

		if ( ! function_exists( 'wpis_find_potential_duplicates' ) ) {
			set_transient(
				$notice_key,
				array(
					'type'   => 'skip',
					'reason' => 'core',
				),
				60
			);
			wp_safe_redirect( $redirect );
			exit;
		}

		$stats = Poller::run( true );
		if ( null === $stats ) {
			set_transient(
				$notice_key,
				array(
					'type'   => 'skip',
					'reason' => 'noop',
				),
				60
			);
			wp_safe_redirect( $redirect );
			exit;
		}

		$errors = isset( $stats['errors'] ) && is_array( $stats['errors'] ) ? $stats['errors'] : array();
		$errors = array_filter( array_map( 'strval', $errors ) );

		if ( array() !== $errors ) {
			set_transient(
				$notice_key,
				array(
					'type'   => 'error',
					'errors' => $errors,
					'stats'  => $stats,
				),
				60
			);
			wp_safe_redirect( $redirect );
			exit;
		}

		set_transient(
			$notice_key,
			array(
				'type'  => 'ok',
				'stats' => $stats,
			),
			60
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Settings saved and manual poll result notices.
	 *
	 * @return void
	 */
	public static function render_admin_notices(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only query args from admin redirects.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::SLUG !== $page ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- flag appended by options.php after a successful save.
		if ( ! empty( $_GET['settings-updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html__( 'Settings saved.', 'wpis-bot-bluesky' );
			echo ' ';
			echo esc_html__( 'Use "Run poll now" below to confirm your Bluesky session works.', 'wpis-bot-bluesky' );
			echo '</p></div>';
		}

		$notice_key = self::POLL_NOTICE_TRANSIENT . '_' . get_current_user_id();
		$data       = get_transient( $notice_key );
		if ( false !== $data && is_array( $data ) ) {
			delete_transient( $notice_key );
			$type = isset( $data['type'] ) ? (string) $data['type'] : '';
			if ( 'ok' === $type && isset( $data['stats'] ) && is_array( $data['stats'] ) ) {
				$st = $data['stats'];
				echo '<div class="notice notice-success is-dismissible"><p>';
				echo esc_html__( 'Poll finished successfully.', 'wpis-bot-bluesky' );
				echo ' ';
				printf(
					/* translators: 1: candidates count, 2: created, 3: bumped */
					esc_html__( 'Candidates: %1$d, created: %2$d and bumped: %3$d.', 'wpis-bot-bluesky' ),
					(int) ( $st['candidates'] ?? 0 ),
					(int) ( $st['created'] ?? 0 ),
					(int) ( $st['bumped'] ?? 0 )
				);
				echo '</p></div>';
				return;
			}
			if ( 'error' === $type && ! empty( $data['errors'] ) && is_array( $data['errors'] ) ) {
				echo '<div class="notice notice-error is-dismissible"><p>';
				echo esc_html__( 'Poll failed:', 'wpis-bot-bluesky' );
				echo ' ';
				echo esc_html( implode( '; ', array_map( 'sanitize_text_field', $data['errors'] ) ) );
				echo '</p></div>';
				return;
			}
			if ( 'skip' === $type ) {
				$reason = isset( $data['reason'] ) ? (string) $data['reason'] : '';
				echo '<div class="notice notice-warning is-dismissible"><p>';
				if ( 'core' === $reason ) {
					echo esc_html__( 'Poll did not run: WPIS Core is not available.', 'wpis-bot-bluesky' );
				} else {
					echo esc_html__( 'Poll did not run (nothing to do).', 'wpis-bot-bluesky' );
				}
				echo '</p></div>';
			}
		}
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
		$last = array() !== $log && isset( $log[0] ) && is_array( $log[0] ) ? $log[0] : null;

		echo '<div class="wrap"><h1>' . esc_html__( 'WPIS Bluesky Bot', 'wpis-bot-bluesky' ) . '</h1>';
		if ( CoreDependency::block_if_core_missing() ) {
			echo '</div>';
			return;
		}

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

		echo '<h2>' . esc_html__( 'Manual poll', 'wpis-bot-bluesky' ) . '</h2>';
		echo '<p>' . esc_html__( 'Runs one fetch using the saved settings, even if the bot is disabled. Use this to verify credentials before enabling the schedule.', 'wpis-bot-bluesky' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wpis_bot_bluesky_poll_now' );
		echo '<input type="hidden" name="action" value="wpis_bot_bluesky_poll_now" />';
		submit_button( __( 'Run poll now', 'wpis-bot-bluesky' ), 'secondary', 'submit', false );
		echo '</form>';

		if ( $last ) {
			$at_last = isset( $last['at'] ) ? gmdate( 'Y-m-d H:i:s', (int) $last['at'] ) . ' UTC' : '—';
			echo '<p class="description">';
			printf(
				/* translators: 1: UTC datetime of last run */
				esc_html__( 'Last logged run: %1$s (see Run logs for full history).', 'wpis-bot-bluesky' ),
				esc_html( $at_last )
			);
			echo '</p>';
		}

		$logs_url = admin_url( 'admin.php?page=wpis-bots-logs' );
		echo '<p><a href="' . esc_url( $logs_url ) . '">' . esc_html__( 'View all run logs', 'wpis-bot-bluesky' ) . '</a></p>';
		echo '</div>';
	}
}
