<?php
/**
 * Bluesky bot admin UI.
 *
 * @package WPIS\BotBluesky
 */

namespace WPIS\BotBluesky;

use WPIS\Bots\CoreDependency;
use WPIS\Bots\DocsLinks;
use WPIS\Bots\PolylangSettings;

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
	 * Transient key suffix for test-connection feedback (per user).
	 */
	private const TEST_NOTICE_TRANSIENT = 'wpis_bot_bluesky_test_notice';

	/**
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'admin_notices', array( self::class, 'notice_action_scheduler' ) );
		add_action( 'admin_notices', array( self::class, 'render_admin_notices' ) );
		add_action( 'admin_post_wpis_bot_bluesky_poll_now', array( self::class, 'handle_poll_now' ) );
		add_action( 'admin_post_wpis_bot_bluesky_test_connection', array( self::class, 'handle_test_connection' ) );
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
		printf(
			wp_kses(
				/* translators: 1: Action Scheduler plugin link, 2: WP-Cron handbook link */
				__( 'Action Scheduler is not available. Install and activate the %1$s plugin (or rely on %2$s, which is less reliable on low traffic sites).', 'wpis-bot-bluesky' ),
				DocsLinks::external_link_allowed_tags()
			),
			DocsLinks::external_anchor( 'https://wordpress.org/plugins/action-scheduler/', __( 'Action Scheduler', 'wpis-bot-bluesky' ) ),
			DocsLinks::external_anchor( 'https://developer.wordpress.org/plugins/cron/', __( 'WP-Cron', 'wpis-bot-bluesky' ) )
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

		$out['identifier'] = '';
		if ( isset( $input['identifier'] ) ) {
			$out['identifier'] = BlueskyClient::normalize_identifier(
				sanitize_text_field( (string) $input['identifier'] )
			);
		}

		$out['app_password'] = isset( $input['app_password'] )
			? sanitize_text_field( (string) $input['app_password'] ) : '';

		$out['search_query'] = isset( $input['search_query'] )
			? sanitize_text_field( (string) $input['search_query'] ) : 'WordPress is';

		$out['poll_interval_minutes'] = isset( $input['poll_interval_minutes'] )
			? max( Settings::MIN_POLL_INTERVAL_MINUTES, min( 120, (int) $input['poll_interval_minutes'] ) ) : 15;

		$out['backfill_enabled'] = ! empty( $input['backfill_enabled'] ) ? 1 : 0;
		$out['backfill_interval_minutes'] = isset( $input['backfill_interval_minutes'] )
			? max( Settings::MIN_BACKFILL_INTERVAL_MINUTES, min( 24 * 60, (int) $input['backfill_interval_minutes'] ) )
			: 360;
		$out['backfill_max_pages'] = isset( $input['backfill_max_pages'] )
			? max( 1, min( 25, (int) $input['backfill_max_pages'] ) )
			: 5;

		$out['dedup_threshold'] = isset( $input['dedup_threshold'] )
			? max( 0, min( 100, (int) $input['dedup_threshold'] ) ) : 70;

		$out['keyword_patterns'] = isset( $input['keyword_patterns'] )
			? sanitize_textarea_field( (string) $input['keyword_patterns'] ) : $defaults['keyword_patterns'];

		if ( PolylangSettings::is_active() ) {
			$out['polylang_slug'] = isset( $input['polylang_slug'] )
				? sanitize_key( (string) $input['polylang_slug'] ) : '';
		} else {
			$out['polylang_slug'] = '';
		}

		return $out;
	}

	/**
	 * Verifies session and one search request (no ingestion).
	 *
	 * @return void
	 */
	public static function handle_test_connection(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do this.', 'wpis-bot-bluesky' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'wpis_bot_bluesky_test_connection' );

		$redirect   = admin_url( 'admin.php?page=' . self::SLUG );
		$notice_key = self::TEST_NOTICE_TRANSIENT . '_' . get_current_user_id();

		$s       = Settings::get();
		$service = BlueskyClient::normalize_service_url( (string) $s['service_url'] );
		$jwt     = SessionManager::get_access_jwt( $s );
		if ( is_wp_error( $jwt ) ) {
			set_transient(
				$notice_key,
				array(
					'ok'      => false,
					'message' => $jwt->get_error_message(),
				),
				60
			);
			wp_safe_redirect( $redirect );
			exit;
		}

		$query = trim( (string) $s['search_query'] );
		if ( '' === $query ) {
			$query = 'WordPress';
		}

		$search = BlueskyClient::search_posts( $service, $jwt, $query, '', 1 );
		if ( is_wp_error( $search ) ) {
			set_transient(
				$notice_key,
				array(
					'ok'      => false,
					'message' => $search->get_error_message(),
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
				'count' => count( $search['posts'] ),
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
			wp_die( esc_html__( 'Sorry, you are not allowed to do this.', 'wpis-bot-bluesky' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'wpis_bot_bluesky_poll_now' );

		$redirect   = admin_url( 'admin.php?page=' . self::SLUG );
		$notice_key = self::POLL_NOTICE_TRANSIENT . '_' . get_current_user_id();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked via check_admin_referer above.
		$dry_raw = isset( $_POST['wpis_bot_bluesky_dry_run'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['wpis_bot_bluesky_dry_run'] ) ) : '';
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
					esc_html__( 'Connection test succeeded: search returned %d post(s). Nothing was saved to WordPress.', 'wpis-bot-bluesky' ),
					(int) ( $test['count'] ?? 0 )
				);
				echo '</p></div>';
			} else {
				echo '<div class="notice notice-error is-dismissible"><p>';
				echo esc_html__( 'Connection test failed:', 'wpis-bot-bluesky' );
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
						esc_html__( 'Dry run finished. Candidates: %1$d. Would ingest (keyword match, not yet seen): %2$d. No drafts, seen IDs or search cursor were updated.', 'wpis-bot-bluesky' ),
						(int) ( $st['candidates'] ?? 0 ),
						(int) ( $st['would_process'] ?? 0 )
					);
				} else {
					echo esc_html__( 'Poll finished successfully.', 'wpis-bot-bluesky' );
					echo ' ';
					printf(
						/* translators: 1: candidates count, 2: created, 3: bumped */
						esc_html__( 'Candidates: %1$d, created: %2$d and bumped: %3$d.', 'wpis-bot-bluesky' ),
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
					echo esc_html__( 'Full poll did not run: WPIS Core is incomplete or inactive. Use Test connection or Dry run to check Bluesky only.', 'wpis-bot-bluesky' );
				} else {
					echo esc_html__( 'Poll did not run (nothing to do).', 'wpis-bot-bluesky' );
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
		echo '<h2 style="margin-top: 0;">' . esc_html__( 'Try the API and run a poll', 'wpis-bot-bluesky' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'These actions use settings already saved in the database. Save the form below first if you changed any field.', 'wpis-bot-bluesky' ) . '</p>';
		echo '<p class="description">';
		printf(
			wp_kses(
				/* translators: %s: link to Bluesky developer documentation */
				__( 'Platform overview: %s.', 'wpis-bot-bluesky' ),
				DocsLinks::external_link_allowed_tags()
			),
			DocsLinks::external_anchor( 'https://docs.bsky.app/docs/get-started', __( 'Bluesky developer docs', 'wpis-bot-bluesky' ) )
		);
		echo '</p>';

		echo '<h3>' . esc_html__( 'Test connection', 'wpis-bot-bluesky' ) . '</h3>';
		echo '<p>' . esc_html__( 'Opens a session and runs one search. Does not create drafts or change bot state.', 'wpis-bot-bluesky' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom: 1.25em;">';
		wp_nonce_field( 'wpis_bot_bluesky_test_connection' );
		echo '<input type="hidden" name="action" value="wpis_bot_bluesky_test_connection" />';
		submit_button( __( 'Test connection', 'wpis-bot-bluesky' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<h3>' . esc_html__( 'Manual poll', 'wpis-bot-bluesky' ) . '</h3>';
		echo '<p>' . esc_html__( 'Runs a full fetch using saved settings, even if the bot is disabled. Optional dry run counts matches without creating drafts or updating seen IDs.', 'wpis-bot-bluesky' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wpis_bot_bluesky_poll_now' );
		echo '<input type="hidden" name="action" value="wpis_bot_bluesky_poll_now" />';
		echo '<p><label><input type="checkbox" name="wpis_bot_bluesky_dry_run" value="1" /> ';
		echo esc_html__( 'Dry run (no drafts, do not update seen IDs or search cursor)', 'wpis-bot-bluesky' );
		echo '</label></p>';
		submit_button( __( 'Run poll now', 'wpis-bot-bluesky' ), 'primary', 'submit', false );
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

		$log = get_option( Settings::LOG_OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$last = array() !== $log && isset( $log[0] ) && is_array( $log[0] ) ? $log[0] : null;

		echo '<div class="wrap"><h1>' . esc_html__( 'WPIS Bluesky Bot', 'wpis-bot-bluesky' ) . '</h1>';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag after options.php redirect.
		if ( ! empty( $_GET['settings-updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html__( 'Settings saved.', 'wpis-bot-bluesky' );
			echo ' ';
			echo esc_html__( 'Use Test connection or a manual poll below to verify your Bluesky session.', 'wpis-bot-bluesky' );
			echo '</p></div>';
		}

		CoreDependency::block_if_core_missing();

		DocsLinks::render_bluesky_intro();
		self::render_quick_actions_card();

		echo '<form method="post" action="options.php">';
		settings_fields( 'wpis_bot_bluesky' );
		$s = Settings::get();
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable bot', 'wpis-bot-bluesky' ); ?></th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( Settings::OPTION ); ?>[enabled]" value="1" <?php checked( $s['enabled'], 1 ); ?> /> <?php esc_html_e( 'Poll Bluesky on a schedule', 'wpis-bot-bluesky' ); ?></label>
					<p class="description">
						<?php
						printf(
							wp_kses(
								/* translators: %s: link to shipped admin guide on GitHub */
								__( 'When on, the site runs a recurring job to search Bluesky and may create or bump quote drafts. When off, that schedule does not run, but the Test connection and manual poll actions (below) still work for debugging. Options and limits: %s.', 'wpis-bot-bluesky' ),
								DocsLinks::external_link_allowed_tags()
							),
							DocsLinks::external_anchor( DocsLinks::shipped_doc_url( 'guide-admin.md' ), __( 'WPIS Bots admin guide (GitHub)', 'wpis-bot-bluesky' ) )
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpis_b_service"><?php esc_html_e( 'Service URL', 'wpis-bot-bluesky' ); ?></label></th>
				<td>
					<input name="<?php echo esc_attr( Settings::OPTION ); ?>[service_url]" id="wpis_b_service" type="url" class="regular-text" value="<?php echo esc_attr( (string) $s['service_url'] ); ?>" />
					<p class="description">
						<?php
						printf(
							wp_kses(
								/* translators: 1: link to bsky.social, 2: link to Bluesky API hosts documentation */
								__( 'Base URL of the PDS and AppView for your account. Use https://bsky.social for a normal bluesky.social account (%1$s). Change it only if your account is hosted on a different PDS (advanced). No path after the host. See %2$s when you point at a custom PDS.', 'wpis-bot-bluesky' ),
								DocsLinks::external_link_allowed_tags()
							),
							DocsLinks::external_anchor( 'https://bsky.social', __( 'open Bluesky in the browser', 'wpis-bot-bluesky' ) ),
							DocsLinks::external_anchor( 'https://docs.bsky.app/docs/advanced-guides/api-directory', __( 'Bluesky API hosts guide', 'wpis-bot-bluesky' ) )
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpis_b_id"><?php esc_html_e( 'Bluesky account', 'wpis-bot-bluesky' ); ?></label></th>
				<td>
					<input name="<?php echo esc_attr( Settings::OPTION ); ?>[identifier]" id="wpis_b_id" type="text" class="regular-text" autocomplete="username" value="<?php echo esc_attr( (string) $s['identifier'] ); ?>" />
					<p class="description">
						<?php
						printf(
							wp_kses(
								/* translators: %s: link to Bluesky account settings */
								__( 'Use your handle, your sign-in email or a DID. A bsky.app profile link or a line that starts with an at-sign before the handle is normalized on save. A display name or a post URL on its own is not valid here. Open %s if you need your handle.', 'wpis-bot-bluesky' ),
								DocsLinks::external_link_allowed_tags()
							),
							DocsLinks::external_anchor( 'https://bsky.app/settings', __( 'Bluesky account settings', 'wpis-bot-bluesky' ) )
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpis_b_pw"><?php esc_html_e( 'App password', 'wpis-bot-bluesky' ); ?></label></th>
				<td>
					<input name="<?php echo esc_attr( Settings::OPTION ); ?>[app_password]" id="wpis_b_pw" type="password" class="regular-text" autocomplete="new-password" value="<?php echo esc_attr( (string) $s['app_password'] ); ?>" />
					<p class="description">
						<?php
						printf(
							wp_kses(
								/* translators: %s: link to Bluesky app passwords */
								__( 'Create it under %s, not your main login password. It usually has four word-like parts separated by hyphens. If it leaks, delete it in Bluesky and add a new one here.', 'wpis-bot-bluesky' ),
								DocsLinks::external_link_allowed_tags()
							),
							DocsLinks::external_anchor( 'https://bsky.app/settings/app-passwords', __( 'Bluesky app passwords', 'wpis-bot-bluesky' ) )
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpis_b_q"><?php esc_html_e( 'Search query', 'wpis-bot-bluesky' ); ?></label></th>
				<td>
					<input name="<?php echo esc_attr( Settings::OPTION ); ?>[search_query]" id="wpis_b_q" type="text" class="regular-text" value="<?php echo esc_attr( (string) $s['search_query'] ); ?>" />
					<p class="description">
						<?php
						printf(
							wp_kses(
								/* translators: %s: link to Bluesky search */
								__( 'The same type of string as the Bluesky search field: words, phrases or other search syntax. The bot fetches recent posts for this query, then applies the keyword list below. Broader text matches more posts and can use the API more quickly. Try the same string in %s first.', 'wpis-bot-bluesky' ),
								DocsLinks::external_link_allowed_tags()
							),
							DocsLinks::external_anchor( 'https://bsky.app/search', __( 'Bluesky search', 'wpis-bot-bluesky' ) )
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpis_b_interval"><?php esc_html_e( 'Poll interval (minutes)', 'wpis-bot-bluesky' ); ?></label></th>
				<td>
					<input name="<?php echo esc_attr( Settings::OPTION ); ?>[poll_interval_minutes]" id="wpis_b_interval" type="number" min="<?php echo (int) Settings::MIN_POLL_INTERVAL_MINUTES; ?>" max="120" value="<?php echo (int) $s['poll_interval_minutes']; ?>" />
					<p class="description">
						<?php
						printf(
							wp_kses(
								/* translators: 1: minimum minutes, 2: maximum minutes, 3: WP-Cron link, 4: Action Scheduler link */
								__( 'Time between automatic runs. Must be between %1$d and %2$d. Smaller values call Bluesky more often. The schedule uses %3$s or %4$s, depending on your site.', 'wpis-bot-bluesky' ),
								DocsLinks::external_link_allowed_tags()
							),
							(int) Settings::MIN_POLL_INTERVAL_MINUTES,
							120,
							DocsLinks::external_anchor( 'https://developer.wordpress.org/plugins/cron/', __( 'WordPress cron', 'wpis-bot-bluesky' ) ),
							DocsLinks::external_anchor( 'https://wordpress.org/plugins/action-scheduler/', __( 'Action Scheduler', 'wpis-bot-bluesky' ) )
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Search backfill (deeper pages)', 'wpis-bot-bluesky' ); ?></th>
				<td>
					<p>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION ); ?>[backfill_enabled]" value="1" <?php checked( ! empty( $s['backfill_enabled'] ) ); ?> />
							<?php esc_html_e( 'Run a second slow job that walks more search result pages per run (same query and filters)', 'wpis-bot-bluesky' ); ?>
						</label>
					</p>
					<p>
						<label for="wpis_b_bf_int"><?php esc_html_e( 'Backfill interval (minutes)', 'wpis-bot-bluesky' ); ?></label>
						<input name="<?php echo esc_attr( Settings::OPTION ); ?>[backfill_interval_minutes]" id="wpis_b_bf_int" type="number" min="<?php echo (int) Settings::MIN_BACKFILL_INTERVAL_MINUTES; ?>" max="1440" value="<?php echo (int) $s['backfill_interval_minutes']; ?>" class="small-text" />
					</p>
					<p>
						<label for="wpis_b_bf_pages"><?php esc_html_e( 'Max search pages per backfill run', 'wpis-bot-bluesky' ); ?></label>
						<input name="<?php echo esc_attr( Settings::OPTION ); ?>[backfill_max_pages]" id="wpis_b_bf_pages" type="number" min="1" max="25" value="<?php echo (int) $s['backfill_max_pages']; ?>" class="small-text" />
					</p>
					<p class="description">
						<?php
						esc_html_e(
							'The fast poll above still does one page each time. Backfill chains several search pages in one background run on its own schedule so the cursor moves faster through the index when you want more history. A short lock prevents the two jobs from changing the cursor at the same time.',
							'wpis-bot-bluesky'
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpis_b_dedup"><?php esc_html_e( 'Dedup threshold (0–100)', 'wpis-bot-bluesky' ); ?></label></th>
				<td>
					<input name="<?php echo esc_attr( Settings::OPTION ); ?>[dedup_threshold]" id="wpis_b_dedup" type="number" min="0" max="100" value="<?php echo (int) $s['dedup_threshold']; ?>" />
					<p class="description">
						<?php
						printf(
							wp_kses(
								/* translators: %s: link to admin guide */
								__( 'Passed to WPIS Core when a candidate might match an existing quote. Higher means a closer match is needed before a post is treated as a duplicate (bump) instead of a new draft. 0 to 100. See %s.', 'wpis-bot-bluesky' ),
								DocsLinks::external_link_allowed_tags()
							),
							DocsLinks::external_anchor( DocsLinks::shipped_doc_url( 'guide-admin.md' ), __( 'WPIS Bots admin guide (GitHub)', 'wpis-bot-bluesky' ) )
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpis_b_kw"><?php esc_html_e( 'Keyword patterns (one per line)', 'wpis-bot-bluesky' ); ?></label></th>
				<td>
					<textarea name="<?php echo esc_attr( Settings::OPTION ); ?>[keyword_patterns]" id="wpis_b_kw" class="large-text" rows="6"><?php echo esc_textarea( (string) $s['keyword_patterns'] ); ?></textarea>
					<p class="description">
						<?php
						printf(
							wp_kses(
								/* translators: %s: link to API limits doc */
								__( 'Each line is a substring match (case-insensitive). The post must match at least one non-empty line. If every line is empty, the code treats the match as true for all posts, so keep real patterns unless you know you want that. Pacing and quotas: %s.', 'wpis-bot-bluesky' ),
								DocsLinks::external_link_allowed_tags()
							),
							DocsLinks::external_anchor( DocsLinks::shipped_doc_url( 'limites-api-et-bonnes-pratiques.md' ), __( 'API limits and good practice (GitHub)', 'wpis-bot-bluesky' ) )
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="wpis_b_pll">
						<?php esc_html_e( 'Polylang language slug (optional)', 'wpis-bot-bluesky' ); ?>
					</label>
				</th>
				<td>
					<?php
					$pll_active = PolylangSettings::is_active();
					$pll_id     = 'wpis_b_pll';
					?>
					<?php if ( $pll_active ) : ?>
					<input name="<?php echo esc_attr( Settings::OPTION ); ?>[polylang_slug]" id="<?php echo esc_attr( $pll_id ); ?>" type="text" class="regular-text" value="<?php echo esc_attr( (string) $s['polylang_slug'] ); ?>" />
					<?php else : ?>
					<input name="<?php echo esc_attr( $pll_id ); ?>_dummy" id="<?php echo esc_attr( $pll_id ); ?>" type="text" class="regular-text" value="" readonly disabled />
					<?php endif; ?>
					<p class="description">
						<?php
						if ( $pll_active ) {
							printf(
								wp_kses(
									/* translators: %s: link to Polylang language code help */
									__( 'Set the content language slug for new drafts, for example en or fr. Leave empty to use the default in WPIS Core. Find codes under %s.', 'wpis-bot-bluesky' ),
									DocsLinks::external_link_allowed_tags()
								),
								DocsLinks::external_anchor( 'https://polylang.pro/doc/how-to-find-the-language-code-in-polylang/', __( 'Polylang language codes', 'wpis-bot-bluesky' ) )
							);
						} else {
							esc_html_e( 'Install and activate Polylang to assign a language to drafts.', 'wpis-bot-bluesky' );
						}
						?>
					</p>
				</td>
			</tr>
		</table>
		<?php
		submit_button();
		echo '</form>';

		if ( $last ) {
			$at_last = isset( $last['at'] ) ? gmdate( 'Y-m-d H:i:s', (int) $last['at'] ) . ' UTC' : '—';
			echo '<p class="description">';
			printf(
				wp_kses(
					/* translators: 1: UTC datetime of last run, 2: link to WPIS Bots run logs */
					__( 'Last logged run: %1$s (%2$s).', 'wpis-bot-bluesky' ),
					DocsLinks::external_link_allowed_tags()
				),
				esc_html( $at_last ),
				DocsLinks::admin_anchor( admin_url( 'admin.php?page=wpis-bots-logs' ), __( 'full run log history', 'wpis-bot-bluesky' ) )
			);
			echo '</p>';
		}

		$logs_url = admin_url( 'admin.php?page=wpis-bots-logs' );
		echo '<p><a href="' . esc_url( $logs_url ) . '">' . esc_html__( 'View all run logs', 'wpis-bot-bluesky' ) . '</a></p>';
		echo '</div>';
	}
}
