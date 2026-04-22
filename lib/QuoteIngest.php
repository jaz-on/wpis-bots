<?php
/**
 * Bridge to WPIS Core quote candidate API.
 *
 * @package WPIS\Bots
 */

namespace WPIS\Bots;

/**
 * Delegates to wpis_submit_quote_candidate() from wpis-core.
 */
final class QuoteIngest {

	public const RESULT_CREATED          = 'created';
	public const RESULT_BUMPED           = 'bumped';
	public const RESULT_SKIPPED_EMPTY    = 'skipped_empty';
	public const RESULT_SKIPPED_LONG     = 'skipped_too_long';
	public const RESULT_ERROR_INSERT     = 'error_insert';
	public const RESULT_ERROR_VALIDATION = 'error_validation';

	/**
	 * Process one text candidate.
	 *
	 * @param array<string, mixed> $args Passed to wpis_submit_quote_candidate().
	 * @return array{result: string, post_id?: int, error?: string}
	 */
	public static function process_candidate( array $args ): array {
		if ( ! function_exists( 'wpis_submit_quote_candidate' ) ) {
			return array(
				'result' => self::RESULT_ERROR_INSERT,
				'error'  => 'wpis_submit_quote_candidate missing; update WPIS Core (wpis-core).',
			);
		}

		return wpis_submit_quote_candidate( $args );
	}
}
