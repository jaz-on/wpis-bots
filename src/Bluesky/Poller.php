<?php
/**
 * Bluesky polling job.
 *
 * @package WPIS\BotBluesky
 */

namespace WPIS\BotBluesky;

use WPIS\Bots\BotLanguage;
use WPIS\Bots\CoreDependency;
use WPIS\Bots\ProcessedRemoteIds;
use WPIS\Bots\QuoteIngest;
use WPIS\Bots\RunLogger;
use WPIS\Bots\TextHelper;

/**
 * Scheduled hook handler.
 */
final class Poller {

	private const LOCK_KEY = 'wpis_bot_bluesky_poll_lock';

	/**
	 * @param bool $force When true, run even if the bot is disabled (manual test from admin).
	 * @param bool $dry_run When true, fetch and count only; no drafts, seen IDs or cursor updates.
	 * @param int  $max_pages Search result pages to walk in one run (1 for the fast poll, more for backfill).
	 * @return array<string, mixed>|null Summary stats for admin feedback, or null if the job did not run.
	 */
	public static function run( bool $force = false, bool $dry_run = false, int $max_pages = 1 ): ?array {
		if ( ! $dry_run && ! CoreDependency::is_core_ready() ) {
			return null;
		}

		$settings = Settings::get();
		if ( ! $force && empty( $settings['enabled'] ) ) {
			return null;
		}

		$max_pages = max( 1, min( 25, $max_pages ) );
		$locked    = false;
		if ( ! $dry_run ) {
			if ( get_transient( self::LOCK_KEY ) ) {
				return array(
					'candidates'         => 0,
					'created'            => 0,
					'bumped'             => 0,
					'skipped_keyword'    => 0,
					'skipped_seen'       => 0,
					'skipped_empty'      => 0,
					'skipped_too_long'   => 0,
					'errors'             => array( __( 'Another Bluesky poller is already running. Try again in a few seconds.', 'wpis-bot-bluesky' ) ),
					'source'            => 1 < $max_pages ? 'bluesky-backfill' : 'bluesky',
				);
			}
			set_transient( self::LOCK_KEY, 1, 60 );
			$locked = true;
		}

		$log_source  = 1 < $max_pages ? 'bluesky-backfill' : 'bluesky';
		$service     = BlueskyClient::normalize_service_url( (string) $settings['service_url'] );
		$cursor      = Settings::get_state()['cursor'];
		$logger      = new RunLogger( Settings::LOG_OPTION );
		$seen        = new ProcessedRemoteIds( Settings::SEEN_OPTION );
		$stats       = self::base_stats( $dry_run );
		$query       = trim( (string) $settings['search_query'] );
		if ( '' === $query ) {
			$query = 'WordPress';
		}
		$patterns = TextHelper::patterns_from_textarea( (string) $settings['keyword_patterns'] );

		try {
			$jwt = SessionManager::get_access_jwt( $settings );
			if ( is_wp_error( $jwt ) ) {
				$stats['errors'][] = $jwt->get_error_message();
				if ( ! $dry_run ) {
					$logger->push( array_merge( $stats, array( 'source' => $log_source ) ) );
				}
				return array_merge( $stats, array( 'source' => $log_source ) );
			}

			for ( $p = 0; $p < $max_pages; $p++ ) {
				$result = BlueskyClient::search_posts( $service, $jwt, $query, $cursor, 25 );
				if ( is_wp_error( $result ) ) {
					$data  = $result->get_error_data();
					$stat  = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;
					$retry = ( 401 === $stat || 403 === $stat );
					if ( $retry ) {
						$cached = SessionManager::get_cached_raw();
						if ( is_array( $cached ) && ! empty( $cached['refreshJwt'] ) ) {
							$jwt2 = SessionManager::refresh_access_jwt( (string) $cached['refreshJwt'], $settings );
							if ( ! is_wp_error( $jwt2 ) ) {
								$jwt     = $jwt2;
								$result = BlueskyClient::search_posts( $service, $jwt, $query, $cursor, 25 );
							}
						}
					}
				}

				if ( is_wp_error( $result ) ) {
					$stats['errors'][] = $result->get_error_message();
					if ( ! $dry_run ) {
						$logger->push( array_merge( $stats, array( 'source' => $log_source ) ) );
					}
					return array_merge( $stats, array( 'source' => $log_source ) );
				}

				$posts  = $result['posts'];
				$cursor = (string) $result['cursor'];

				$batch_stats = self::ingest_post_rows( $posts, $settings, $seen, $patterns, $dry_run, $stats );
				$stats       = $batch_stats;

				if ( $dry_run ) {
					break;
				}
				Settings::set_state( array( 'cursor' => $cursor ) );
				if ( '' === $cursor ) {
					break;
				}
			}
		} finally {
			if ( $locked ) {
				delete_transient( self::LOCK_KEY );
			}
		}

		if ( ! $dry_run ) {
			$logger->push( array_merge( $stats, array( 'source' => $log_source ) ) );
		}
		return array_merge( $stats, array( 'source' => $log_source ) );
	}

	/**
	 * Slow scheduled search depth (more than one result page). Requires enabled + backfill_enabled.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function run_backfill(): ?array {
		if ( ! CoreDependency::is_core_ready() ) {
			return null;
		}
		$settings = Settings::get();
		if ( empty( $settings['enabled'] ) || empty( $settings['backfill_enabled'] ) ) {
			return null;
		}
		$max = (int) $settings['backfill_max_pages'];
		return self::run( false, false, $max );
	}

	/**
	 * @param bool $dry_run Dry run.
	 * @return array<string, mixed>
	 */
	public static function base_stats( bool $dry_run ): array {
		$stats = array(
			'candidates'         => 0,
			'created'            => 0,
			'bumped'             => 0,
			'skipped_keyword'    => 0,
			'skipped_seen'       => 0,
			'skipped_empty'      => 0,
			'skipped_too_long'   => 0,
			'errors'             => array(),
		);
		if ( $dry_run ) {
			$stats['dry_run']       = true;
			$stats['would_process'] = 0;
		}
		return $stats;
	}

	/**
	 * @param array<int, array{uri: string, text: string, url?: string, source_langs?: list<string>}> $posts
	 * @param array<string, mixed>                                        $settings
	 * @param array<int, string>                                          $patterns Keyword substrings.
	 * @param array<string, mixed>                                        $stats
	 * @return array<string, mixed>
	 */
	public static function ingest_post_rows( array $posts, array $settings, ProcessedRemoteIds $seen, array $patterns, bool $dry_run, array $stats ): array {
		foreach ( $posts as $row ) {
			++$stats['candidates'];
			$rid = (string) $row['uri'];
			if ( $seen->has_seen( $rid ) ) {
				++$stats['skipped_seen'];
				continue;
			}

			$text = (string) $row['text'];
			if ( ! TextHelper::matches_any_pattern( $text, $patterns ) ) {
				++$stats['skipped_keyword'];
				if ( ! $dry_run ) {
					$seen->remember( $rid );
				}
				continue;
			}

			if ( $dry_run ) {
				++$stats['would_process'];
				continue;
			}

			$url = isset( $row['url'] ) ? (string) $row['url'] : '';
			if ( '' === $url ) {
				$url = BlueskyClient::uri_to_web_url( $rid );
			}

			$api_lang   = '';
			if ( ! empty( $row['source_langs'] ) && is_array( $row['source_langs'] ) ) {
				$api_lang = (string) ( $row['source_langs'][0] ?? '' );
			}
			$dedup_lang = BotLanguage::normalize_dedup_lang( $api_lang );
			$pll_slug   = BotLanguage::resolve_polylang_slug( $api_lang, (string) $settings['polylang_slug'] );

			$res = QuoteIngest::process_candidate(
				array(
					'text'              => $text,
					'submission_source' => 'bot-bluesky',
					'source_platform'   => 'bluesky',
					'lang'              => $dedup_lang,
					'dedup_threshold'   => (int) $settings['dedup_threshold'],
					'source_url'        => $url,
					'polylang_slug'     => $pll_slug,
					'source_language'   => $dedup_lang,
				)
			);

			$seen->remember( $rid );

			switch ( $res['result'] ) {
				case QuoteIngest::RESULT_CREATED:
					++$stats['created'];
					break;
				case QuoteIngest::RESULT_BUMPED:
					++$stats['bumped'];
					break;
				case QuoteIngest::RESULT_SKIPPED_EMPTY:
					++$stats['skipped_empty'];
					break;
				case QuoteIngest::RESULT_SKIPPED_LONG:
					++$stats['skipped_too_long'];
					break;
				default:
					if ( isset( $res['error'] ) ) {
						$stats['errors'][] = (string) $res['error'];
					}
			}
		}
		return $stats;
	}
}
