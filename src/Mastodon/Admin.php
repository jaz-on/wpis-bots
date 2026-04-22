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
	 * Transient key suffix for test-connection feedback (per user).
	 */
	private const TEST_NOTICE_TRANSIENT = 'wpis_bot_mastodon_test_notice';

	/**
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'admin_notices', array( self::class, 'notice_action_scheduler' ) );
		add_action( 'admin_notices', array( self::class, 'render_admin_notices' ) );
		add_action( 'admin_post_wpis_bot_mastodon_poll_now', array( self::class, 'handle_poll_now' ) );
		add_action( 'admin_post_wpis_bot_mastodon_test_connection', array( self::class, 'handle_test_connection' ) );
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
	 * Fetches one timeline post to verify instance URL and token (no ingestion).
	 *
	 * @return void
	 */
	public static function handle_test_connection(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do this.', 'wpis-bot-mastodon' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'wpis_bot_mastodon_test_connection' );

		$redirect   = admin_url( 'admin.php?page=' . self::SLUG );
		$notice_key = self::TEST_NOTICE_TRANSIENT . '_' . get_current_user_id();

		$s        = Settings::get();
		$instance = MastodonClient::normalize_instance_url( (string) $s['instance_url'] );
		$items    = MastodonClient::fetch_tag_timeline(
			$instance,
			(string) $s['hashtag'],
			'',
			(string) $s['access_token'],
			1
		);

		if ( is_wp_error( $items ) ) {
			set_transient(
				$notice_key,
				array(
					'ok'      => false,
					'message' => $items->get_error_message(),
				),
				60
			);
			wp_safe_redirect( $redirect );
			exit;
		}

		set_transient(
			$notice_key,
			array(
				'ok'    => true,
				'count' => count( $items ),
			),
			60
		);
		wp_safe_redirect( $redirect );
		exit;
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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked via check_admin_referer above.
		$dry_raw = isset( $_POST['wpis_bot_mastodon_dry_run'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['wpis_bot_mastodon_dry_run'] ) ) : '';
		$dry_run = ( '1' === $dry_raw );

		if ( ! $dry_run && ! CoreDependency::is_core_ready() ) {
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

		$stats = Poller::run( true, $dry_run );
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

		$test_key = self::TEST_NOTICE_TRANSIENT . '_' . get_current_user_id();
		$test     = get_transient( $test_key );
		if ( false !== $test && is_array( $test ) ) {
			delete_transient( $test_key );
			if ( ! empty( $test['ok'] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>';
				printf(
					/* translators: %d: number of posts returned (0 or 1) */
					esc_html__( 'Connection test succeeded: timeline returned %d post(s). Nothing was saved to WordPress.', 'wpis-bot-mastodon' ),
					(int) ( $test['count'] ?? 0 )
				);
				echo '</p></div>';
			} else {
				echo '<div class="notice notice-error is-dismissible"><p>';
				echo esc_html__( 'Connection test failed:', 'wpis-bot-mastodon' );
				echo ' ';
				echo esc_html( isset( $test['message'] ) ? (string) $test['message'] : '' );
				echo '</p></div>';
			}
		}

		$notice_key = self::POLL_NOTICE_TRANSIENT . '_' . get_current_user_id();
		$data       = get_transient( $notice_key );
		if ( false !== $data && is_array( $data ) ) {
			delete_transient( $notice_key );
			$type = isset( $data['type'] ) ? (string) $data['type'] : '';
			if ( 'ok' === $type && isset( $data['stats'] ) && is_array( $data['stats'] ) ) {
				$st = $data['stats'];
				echo '<div class="notice notice-success is-dismissible"><p>';
				if ( ! empty( $st['dry_run'] ) ) {
					printf(
						/* translators: 1: candidates, 2: would_process */
						esc_html__( 'Dry run finished. Candidates: %1$d. Would ingest (keyword match, not yet seen): %2$d. No drafts, seen IDs or since_id were updated.', 'wpis-bot-mastodon' ),
						(int) ( $st['candidates'] ?? 0 ),
						(int) ( $st['would_process'] ?? 0 )
					);
				} else {
					echo esc_html__( 'Poll finished successfully.', 'wpis-bot-mastodon' );
					echo ' ';
					printf(
						/* translators: 1: candidates count, 2: created, 3: bumped */
						esc_html__( 'Candidates: %1$d, created: %2$d and bumped: %3$d.', 'wpis-bot-mastodon' ),
						(int) ( $st['candidates'] ?? 0 ),
						(int) ( $st['created'] ?? 0 ),
						(int) ( $st['bumped'] ?? 0 )
					);
				}
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
					echo esc_html__( 'Full poll did not run: WPIS Core is incomplete or inactive. Use Test connection or Dry run to check the Mastodon API only.', 'wpis-bot-mastodon' );
				} else {
					echo esc_html__( 'Poll did not run (nothing to do).', 'wpis-bot-mastodon' );
				}
				echo '</p></div>';
			}
		}
	}

	/**
	 * Test API, manual poll and dry run (uses saved options).
	 *
	 * @return void
	 */
	private static function render_quick_actions_card(): void {
		echo '<div class="card" style="max-width: 56rem; padding: 1rem 1.25rem; margin: 1em 0;">';
		echo '<h2 style="margin-top: 0;">' . esc_html__( 'Try the API and run a poll', 'wpis-bot-mastodon' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'These actions use settings already saved in the database. Save the form below first if you changed any field.', 'wpis-bot-mastodon' ) . '</p>';

		echo '<h3>' . esc_html__( 'Test connection', 'wpis-bot-mastodon' ) . '</h3>';
		echo '<p>' . esc_html__( 'Fetches one post from the hashtag timeline. Does not create drafts or change bot state.', 'wpis-bot-mastodon' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom: 1.25em;">';
		wp_nonce_field( 'wpis_bot_mastodon_test_connection' );
		echo '<input type="hidden" name="action" value="wpis_bot_mastodon_test_connection" />';
		submit_button( __( 'Test connection', 'wpis-bot-mastodon' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<h3>' . esc_html__( 'Manual poll', 'wpis-bot-mastodon' ) . '</h3>';
		echo '<p>' . esc_html__( 'Runs a full fetch using saved settings, even if the bot is disabled. Optional dry run counts matches without creating drafts or updating seen IDs.', 'wpis-bot-mastodon' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wpis_bot_mastodon_poll_now' );
		echo '<input type="hidden" name="action" value="wpis_bot_mastodon_poll_now" />';
		echo '<p><label><input type="checkbox" name="wpis_bot_mastodon_dry_run" value="1" /> ';
		echo esc_html__( 'Dry run (no drafts, do not update seen IDs or since_id)', 'wpis-bot-mastodon' );
		echo '</label></p>';
		submit_button( __( 'Run poll now', 'wpis-bot-mastodon' ), 'primary', 'submit', false );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * @return void
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'WPIS Mastodon Bot', 'wpis-bot-mastodon' ) . '</h1>';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag after options.php redirect.
		if ( ! empty( $_GET['settings-updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html__( 'Settings saved.', 'wpis-bot-mastodon' );
			echo ' ';
			echo esc_html__( 'Use Test connection or a manual poll below to verify the instance and token.', 'wpis-bot-mastodon' );
			echo '</p></div>';
		}

		CoreDependency::block_if_core_missing();

		$log = get_option( Settings::LOG_OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$last = array() !== $log && isset( $log[0] ) && is_array( $log[0] ) ? $log[0] : null;

		DocsLinks::render_mastodon_intro();
		self::render_quick_actions_card();

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
