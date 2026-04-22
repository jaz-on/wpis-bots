<?php
/**
 * Bluesky polling job.
 *
 * @package WPIS\BotBluesky
 */

namespace WPIS\BotBluesky;

use WPIS\Bots\ProcessedRemoteIds;
use WPIS\Bots\QuoteIngest;
use WPIS\Bots\RunLogger;
use WPIS\Bots\TextHelper;

/**
 * Scheduled hook handler.
 */
final class Poller {

	/**
	 * @return void
	 */
	public static function run(): void {
		if ( ! function_exists( 'wpis_find_potential_duplicates' ) ) {
			return;
		}

		$settings = Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			return;
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

		$jwt = SessionManager::get_access_jwt( $settings );
		if ( is_wp_error( $jwt ) ) {
			$stats['errors'][] = $jwt->get_error_message();
			$logger->push( array_merge( $stats, array( 'source' => 'bluesky' ) ) );
			return;
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
			$logger->push( array_merge( $stats, array( 'source' => 'bluesky' ) ) );
			return;
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
				$seen->remember( $rid );
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

		Settings::set_state( array( 'cursor' => (string) $result['cursor'] ) );

		$logger->push( array_merge( $stats, array( 'source' => 'bluesky' ) ) );
	}
}
