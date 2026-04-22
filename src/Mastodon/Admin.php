<?php
/**
 * Settings UI and run log.
 *
 * @package WPIS\BotMastodon
 */

namespace WPIS\BotMastodon;

use WPIS\Bots\CoreDependency;
use WPIS\Bots\DocsLinks;

/**
 * Options page under Settings.
 */
final class Admin {

	private const SLUG = 'wpis-bot-mastodon';

	/**
	 * Transient key suffix for poll feedback (per user).
	 */
	private const POLL_NOTICE_TRANSIENT = 'wpis_bot_mastodon_poll_notice';

	/**
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'admin_notices', array( self::class, 'notice_action_scheduler' ) );
		add_action( 'admin_notices', array( self::class, 'render_admin_notices' ) );
		add_action( 'admin_post_wpis_bot_mastodon_poll_now', array( self::class, 'handle_poll_now' ) );
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
			'Action Scheduler is not available. From the wpis-bots package run composer install (see vendor/woocommerce/action-scheduler), install the Action Scheduler plugin, or the site will fall back to WP-Cron.',
			'wpis-bot-mastodon'
		);
		echo '</p></div>';
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
	 * Runs one poll immediately (admin test; uses saved credentials even if bot is off).
	 *
	 * @return void
	 */
	public static function handle_poll_now(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do this.', 'wpis-bot-mastodon' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'wpis_bot_mastodon_poll_now' );

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
			echo esc_html__( 'Settings saved.', 'wpis-bot-mastodon' );
			echo ' ';
			echo esc_html__( 'Use "Run poll now" below to confirm your instance URL and token work.', 'wpis-bot-mastodon' );
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
				echo esc_html__( 'Poll finished successfully.', 'wpis-bot-mastodon' );
				echo ' ';
				printf(
					/* translators: 1: candidates count, 2: created, 3: bumped */
					esc_html__( 'Candidates: %1$d, created: %2$d and bumped: %3$d.', 'wpis-bot-mastodon' ),
					(int) ( $st['candidates'] ?? 0 ),
					(int) ( $st['created'] ?? 0 ),
					(int) ( $st['bumped'] ?? 0 )
				);
				echo '</p></div>';
				return;
			}
			if ( 'error' === $type && ! empty( $data['errors'] ) && is_array( $data['errors'] ) ) {
				echo '<div class="notice notice-error is-dismissible"><p>';
				echo esc_html__( 'Poll failed:', 'wpis-bot-mastodon' );
				echo ' ';
				echo esc_html( implode( '; ', array_map( 'sanitize_text_field', $data['errors'] ) ) );
				echo '</p></div>';
				return;
			}
			if ( 'skip' === $type ) {
				$reason = isset( $data['reason'] ) ? (string) $data['reason'] : '';
				echo '<div class="notice notice-warning is-dismissible"><p>';
				if ( 'core' === $reason ) {
					echo esc_html__( 'Poll did not run: WPIS Core is not available.', 'wpis-bot-mastodon' );
				} else {
					echo esc_html__( 'Poll did not run (nothing to do).', 'wpis-bot-mastodon' );
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

		echo '<div class="wrap"><h1>' . esc_html__( 'WPIS Mastodon Bot', 'wpis-bot-mastodon' ) . '</h1>';
		if ( CoreDependency::block_if_core_missing() ) {
			echo '</div>';
			return;
		}

		$log = get_option( Settings::LOG_OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$last = array() !== $log && isset( $log[0] ) && is_array( $log[0] ) ? $log[0] : null;

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
				<td>
					<input name="<?php echo esc_attr( Settings::OPTION ); ?>[access_token]" id="wpis_m_token" type="password" class="regular-text" autocomplete="off" value="<?php echo esc_attr( (string) $s['access_token'] ); ?>" />
					<p class="description">
						<?php
						esc_html_e(
							'Leave empty when the public hashtag timeline works without logging in (most instances). If you must create an app under Preferences → Development:',
							'wpis-bot-mastodon'
						);
						?>
					</p>
					<p class="description">
						<?php
						esc_html_e(
							'Redirect URI: urn:ietf:wg:oauth:2.0:oob is correct when you only copy the access token into WordPress (no browser callback).',
							'wpis-bot-mastodon'
						);
						?>
					</p>
					<p class="description">
						<?php
						esc_html_e(
							'Scopes: enable read. If the form lists fine-grained scopes, also enable read:statuses (read public posts). Profile alone is not enough.',
							'wpis-bot-mastodon'
						);
						?>
					</p>
					<p class="description">
						<?php
						esc_html_e(
							'Do not enable write or write:statuses — this plugin only downloads the tag timeline and never publishes to Mastodon.',
							'wpis-bot-mastodon'
						);
						?>
					</p>
				</td>
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

		echo '<h2>' . esc_html__( 'Manual poll', 'wpis-bot-mastodon' ) . '</h2>';
		echo '<p>' . esc_html__( 'Runs one fetch using the saved settings, even if the bot is disabled. Use this to verify credentials before enabling the schedule.', 'wpis-bot-mastodon' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wpis_bot_mastodon_poll_now' );
		echo '<input type="hidden" name="action" value="wpis_bot_mastodon_poll_now" />';
		submit_button( __( 'Run poll now', 'wpis-bot-mastodon' ), 'secondary', 'submit', false );
		echo '</form>';

		if ( $last ) {
			$at_last = isset( $last['at'] ) ? gmdate( 'Y-m-d H:i:s', (int) $last['at'] ) . ' UTC' : '—';
			echo '<p class="description">';
			printf(
				/* translators: 1: UTC datetime of last run */
				esc_html__( 'Last logged run: %1$s (see Run logs for full history).', 'wpis-bot-mastodon' ),
				esc_html( $at_last )
			);
			echo '</p>';
		}

		$logs_url = admin_url( 'admin.php?page=wpis-bots-logs' );
		echo '<p><a href="' . esc_url( $logs_url ) . '">' . esc_html__( 'View all run logs', 'wpis-bot-mastodon' ) . '</a></p>';
		echo '</div>';
	}
}
