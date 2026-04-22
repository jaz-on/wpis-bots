<?php
/**
 * Mastodon polling job: fetch, filter, ingest.
 *
 * @package WPIS\BotMastodon
 */

namespace WPIS\BotMastodon;

use WPIS\Bots\CoreDependency;
use WPIS\Bots\ProcessedRemoteIds;
use WPIS\Bots\QuoteIngest;
use WPIS\Bots\RunLogger;
use WPIS\Bots\TextHelper;

/**
 * Hook target for Action Scheduler and WP-Cron.
 */
final class Poller {

	/**
	 * @param bool $force When true, run even if the bot is disabled (manual test from admin).
	 * @param bool $dry_run When true, fetch and count only; no drafts, seen IDs or state updates.
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

		$bases  = Settings::get_instance_bases();
		$state  = Settings::get_state();
		$since_map = $state['since_by_instance'];
		$token  = (string) $settings['access_token'];
		$hashtag = (string) $settings['hashtag'];

		$logger = new RunLogger( Settings::LOG_OPTION );
		$seen   = new ProcessedRemoteIds( Settings::SEEN_OPTION );
		$stats  = self::base_stats( $dry_run );

		$update_since = $since_map;

		foreach ( $bases as $base ) {
			$since = $since_map[ $base ] ?? '';
			$items = MastodonClient::fetch_tag_timeline( $base, $hashtag, $since, $token, 40, '' );
			if ( is_wp_error( $items ) ) {
				$stats['errors'][] = $base . ': ' . $items->get_error_message();
				continue;
			}

			$batch = self::ingest_batch( $items, $settings, $seen, $base, $dry_run, $stats );
			$stats = $batch['stats'];
			$high  = $batch['highest_id'];

			if ( ! $dry_run && '' !== $high && ( '' === $since || strcmp( $high, $since ) > 0 ) ) {
				$update_since[ $base ] = $high;
			}
		}

		if ( ! $dry_run ) {
			Settings::set_state( array( 'since_by_instance' => $update_since ) );
			$logger->push( array_merge( $stats, array( 'source' => 'mastodon' ) ) );
		}

		return array_merge( $stats, array( 'source' => 'mastodon' ) );
	}

	/**
	 * @param bool $dry_run Dry run flag.
	 * @return array<string, mixed>
	 */
	public static function base_stats( bool $dry_run ): array {
		$stats = array(
			'candidates'        => 0,
			'created'          => 0,
			'bumped'            => 0,
			'skipped_keyword'  => 0,
			'skipped_seen'     => 0,
			'skipped_empty'    => 0,
			'skipped_too_long' => 0,
			'errors'            => array(),
		);
		if ( $dry_run ) {
			$stats['dry_run']        = true;
			$stats['would_process']  = 0;
		}
		return $stats;
	}

	/**
	 * Process rows from a tag timeline. Stats are merged in place; uses composite post keys per instance.
	 *
	 * @param array<int, array{id: string, text: string, url: string}> $items
	 * @param array<string, mixed>                                   $settings
	 * @param string                                                 $instance_base
	 * @param array<string, mixed>                                   $stats
	 * @return array{stats: array<string, mixed>, highest_id: string, lowest_id: string}
	 */
	public static function ingest_batch( array $items, array $settings, ProcessedRemoteIds $seen, string $instance_base, bool $dry_run, array $stats ): array {
		$patterns = TextHelper::patterns_from_textarea( (string) $settings['keyword_patterns'] );

		usort(
			$items,
			static function ( $a, $b ) {
				return strcmp( (string) $a['id'], (string) $b['id'] );
			}
		);

		$highest = '';
		$lowest  = '';
		foreach ( $items as $row ) {
			$rid = (string) $row['id'];
			if ( '' === $highest || strcmp( $rid, $highest ) > 0 ) {
				$highest = $rid;
			}
			if ( '' === $lowest || strcmp( $rid, $lowest ) < 0 ) {
				$lowest = $rid;
			}
		}

		foreach ( $items as $row ) {
			$rid    = (string) $row['id'];
			$ridkey = MastodonClient::stable_post_key( $instance_base, $rid );
			++$stats['candidates'];
			if ( $seen->has_seen( $ridkey ) ) {
				++$stats['skipped_seen'];
				continue;
			}

			$text = (string) $row['text'];
			if ( ! TextHelper::matches_any_pattern( $text, $patterns ) ) {
				++$stats['skipped_keyword'];
				if ( ! $dry_run ) {
					$seen->remember( $ridkey );
				}
				continue;
			}

			if ( $dry_run ) {
				++$stats['would_process'];
				continue;
			}

			$res = QuoteIngest::process_candidate(
				array(
					'text'                => $text,
					'submission_source'  => 'bot-mastodon',
					'source_platform'   => 'mastodon',
					'lang'                => 'en',
					'dedup_threshold'     => (int) $settings['dedup_threshold'],
					'source_url'         => (string) $row['url'],
					'polylang_slug'      => (string) $settings['polylang_slug'],
				)
			);

			$seen->remember( $ridkey );

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

		return array(
			'stats'      => $stats,
			'highest_id' => $highest,
			'lowest_id'  => $lowest,
		);
	}
}
