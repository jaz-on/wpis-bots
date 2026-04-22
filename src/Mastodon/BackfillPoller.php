<?php
/**
 * Slow hashtag backfill (max_id pagination per instance).
 *
 * @package WPIS\BotMastodon
 */

namespace WPIS\BotMastodon;

use WPIS\Bots\CoreDependency;
use WPIS\Bots\ProcessedRemoteIds;
use WPIS\Bots\RunLogger;

/**
 * Scheduled hook: older posts than the live poll.
 */
final class BackfillPoller {

	/**
	 * @return array<string, mixed>|null
	 */
	public static function run(): ?array {
		if ( ! CoreDependency::is_core_ready() ) {
			return null;
		}

		$settings = Settings::get();
		if ( empty( $settings['enabled'] ) || empty( $settings['backfill_enabled'] ) ) {
			return null;
		}

		$bases     = Settings::get_instance_bases();
		$state     = Settings::get_state();
		$bf_state  = $state['backfill_by_instance'];
		$token     = (string) $settings['access_token'];
		$hashtag   = (string) $settings['hashtag'];
		$max_req   = (int) $settings['backfill_max_requests_per_instance'];

		$logger    = new RunLogger( Settings::LOG_OPTION );
		$seen      = new ProcessedRemoteIds( Settings::SEEN_OPTION );
		$stats     = Poller::base_stats( false );
		$bf_patch  = array();

		foreach ( $bases as $base ) {
			$cur = $bf_state[ $base ] ?? array(
				'max_id' => '',
				'done'   => false,
			);
			if ( ! empty( $cur['done'] ) ) {
				continue;
			}

			$pass_max = isset( $cur['max_id'] ) ? (string) $cur['max_id'] : '';

			for ( $i = 0; $i < $max_req; $i++ ) {
				$items = MastodonClient::fetch_tag_timeline( $base, $hashtag, '', $token, 40, $pass_max );
				if ( is_wp_error( $items ) ) {
					$stats['errors'][] = $base . ' (backfill): ' . $items->get_error_message();
					break;
				}

				if ( array() === $items ) {
					$bf_patch[ $base ] = array(
						'max_id' => $pass_max,
						'done'   => true,
					);
					break;
				}

				$batch  = Poller::ingest_batch( $items, $settings, $seen, $base, false, $stats );
				$stats  = $batch['stats'];
				$lowest = $batch['lowest_id'];

				if ( '' === $lowest ) {
					$bf_patch[ $base ] = array(
						'max_id' => $pass_max,
						'done'   => true,
					);
					break;
				}

				$pass_max          = $lowest;
				$bf_patch[ $base ] = array(
					'max_id' => $pass_max,
					'done'   => false,
				);
			}
		}

		if ( array() !== $bf_patch ) {
			Settings::set_state( array( 'backfill_by_instance' => $bf_patch ) );
		}

		$logger->push( array_merge( $stats, array( 'source' => 'mastodon-backfill' ) ) );
		return array_merge( $stats, array( 'source' => 'mastodon-backfill' ) );
	}
}
