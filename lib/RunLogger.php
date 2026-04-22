<?php
/**
 * Append-only run history for bot polling (admin diagnostics).
 *
 * @package WPIS\Bots
 */

namespace WPIS\Bots;

/**
 * Stores the last N poll summaries in an option.
 */
final class RunLogger {

	/**
	 * @param string $option_key Option name.
	 * @param int    $max_rows   Max entries to keep.
	 */
	public function __construct(
		private string $option_key,
		private int $max_rows = 30
	) {
	}

	/**
	 * @param array<string, int|string|array> $row Summary row.
	 * @return void
	 */
	public function push( array $row ): void {
		$log = get_option( $this->option_key, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$row['at'] = isset( $row['at'] ) ? (int) $row['at'] : time();
		array_unshift( $log, $row );
		$log = array_slice( $log, 0, $this->max_rows );
		update_option( $this->option_key, $log, false );
	}
}
