<?php
/**
 * Mastodon polling job: fetch, filter, ingest.
 *
 * @package WPIS\BotMastodon
 */

namespace WPIS\BotMastodon;

use WPIS\Bots\ProcessedRemoteIds;
use WPIS\Bots\QuoteIngest;
use WPIS\Bots\RunLogger;
use WPIS\Bots\TextHelper;

/**
 * Hook target for Action Scheduler and WP-Cron.
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

		$instance = MastodonClient::normalize_instance_url( (string) $settings['instance_url'] );
		$state    = Settings::get_state();
		$since    = $state['since_id'];

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

		$items = MastodonClient::fetch_tag_timeline(
			$instance,
			(string) $settings['hashtag'],
			$since,
			(string) $settings['access_token'],
			40
		);

		if ( is_wp_error( $items ) ) {
			$stats['errors'][] = $items->get_error_message();
			$logger->push( array_merge( $stats, array( 'source' => 'mastodon' ) ) );
			return;
		}

		$patterns = TextHelper::patterns_from_textarea( (string) $settings['keyword_patterns'] );

		usort(
			$items,
			static function ( $a, $b ) {
				return strcmp( (string) $a['id'], (string) $b['id'] );
			}
		);

		$max_id = $since;

		foreach ( $items as $row ) {
			++$stats['candidates'];
			$rid = (string) $row['id'];
			if ( $seen->has_seen( $rid ) ) {
				++$stats['skipped_seen'];
				if ( '' === $max_id || strcmp( $rid, $max_id ) > 0 ) {
					$max_id = $rid;
				}
				continue;
			}

			$text = (string) $row['text'];
			if ( ! TextHelper::matches_any_pattern( $text, $patterns ) ) {
				++$stats['skipped_keyword'];
				$seen->remember( $rid );
				if ( '' === $max_id || strcmp( $rid, $max_id ) > 0 ) {
					$max_id = $rid;
				}
				continue;
			}

			$res = QuoteIngest::process_candidate(
				array(
					'text'              => $text,
					'submission_source' => 'bot-mastodon',
					'source_platform'   => 'mastodon',
					'lang'              => 'en',
					'dedup_threshold'   => (int) $settings['dedup_threshold'],
					'source_url'        => (string) $row['url'],
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

			if ( '' === $max_id || strcmp( $rid, $max_id ) > 0 ) {
				$max_id = $rid;
			}
		}

		if ( '' !== $max_id && ( '' === $since || strcmp( $max_id, $since ) > 0 ) ) {
			Settings::set_state( array( 'since_id' => $max_id ) );
		}

		$logger->push( array_merge( $stats, array( 'source' => 'mastodon' ) ) );
	}
}
