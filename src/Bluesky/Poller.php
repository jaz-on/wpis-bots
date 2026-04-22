<?php
/**
 * Bluesky polling job.
 *
 * @package WPIS\BotBluesky
 */

namespace WPIS\BotBluesky;

use WPIS\Bots\CoreDependency;
use WPIS\Bots\ProcessedRemoteIds;
use WPIS\Bots\QuoteIngest;
use WPIS\Bots\RunLogger;
use WPIS\Bots\TextHelper;

/**
 * Scheduled hook handler.
 */
final class Poller {

	/**
	 * @param bool $force When true, run even if the bot is disabled (manual test from admin).
	 * @param bool $dry_run When true, fetch and count only; no drafts, seen IDs or cursor updates.
	 * @return array<string, mixed>|null Summary stats for admin feedback, or null if the job did not run.
	 */
	public static function run( bool $force = false, bool $dry_run = false ): ?array {
		if ( ! $dry_run && ! CoreDependency::is_core_ready() ) {
			return null;
		}

		$settings = Settings::get();
		if ( ! $force && empty( $settings['enabled'] ) ) {
			return null;
		}

		$service = BlueskyClient::normalize_service_url( (string) $settings['service_url'] );
		$state   = Settings::get_state();
		$cursor  = $state['cursor'];

		$logger = new RunLogger( Settings::LOG_OPTION );
		$seen   = new ProcessedRemoteIds( Settings::SEEN_OPTION );
		$stats  = array(
			'candidates'       => 0,
			'created'          => 0,
			'bumped'           => 0,
			'skipped_keyword'  => 0,
			'skipped_seen'     => 0,
			'skipped_empty'    => 0,
			'skipped_too_long' => 0,
			'errors'           => array(),
		);
		if ( $dry_run ) {
			$stats['dry_run']       = true;
			$stats['would_process'] = 0;
		}

		$jwt = SessionManager::get_access_jwt( $settings );
		if ( is_wp_error( $jwt ) ) {
			$stats['errors'][] = $jwt->get_error_message();
			if ( ! $dry_run ) {
				$logger->push( array_merge( $stats, array( 'source' => 'bluesky' ) ) );
			}
			return array_merge( $stats, array( 'source' => 'bluesky' ) );
		}

		$query = trim( (string) $settings['search_query'] );
		if ( '' === $query ) {
			$query = 'WordPress';
		}

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
						$result = BlueskyClient::search_posts( $service, $jwt2, $query, $cursor, 25 );
					}
				}
			}
		}

		if ( is_wp_error( $result ) ) {
			$stats['errors'][] = $result->get_error_message();
			if ( ! $dry_run ) {
				$logger->push( array_merge( $stats, array( 'source' => 'bluesky' ) ) );
			}
			return array_merge( $stats, array( 'source' => 'bluesky' ) );
		}

		$patterns = TextHelper::patterns_from_textarea( (string) $settings['keyword_patterns'] );
		$posts    = $result['posts'];

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

			$url = (string) $row['url'];
			if ( '' === $url ) {
				$url = BlueskyClient::uri_to_web_url( $rid );
			}

			$res = QuoteIngest::process_candidate(
				array(
					'text'              => $text,
					'submission_source' => 'bot-bluesky',
					'source_platform'   => 'bluesky',
					'lang'              => 'en',
					'dedup_threshold'   => (int) $settings['dedup_threshold'],
					'source_url'        => $url,
					'polylang_slug'     => (string) $settings['polylang_slug'],
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

		if ( ! $dry_run ) {
			Settings::set_state( array( 'cursor' => (string) $result['cursor'] ) );
			$logger->push( array_merge( $stats, array( 'source' => 'bluesky' ) ) );
		}
		return array_merge( $stats, array( 'source' => 'bluesky' ) );
	}
}
