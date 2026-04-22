<?php
/**
 * Ring buffer of remote post IDs to avoid double-processing replays.
 *
 * @package WPIS\Bots
 */

namespace WPIS\Bots;

/**
 * Stores up to N remote identifiers in a WordPress option.
 */
final class ProcessedRemoteIds {

	private const DEFAULT_MAX = 500;

	/**
	 * @param string $option_name Option key (per bot).
	 * @param int    $max_ids     Max ids to retain.
	 */
	public function __construct(
		private string $option_name,
		private int $max_ids = self::DEFAULT_MAX
	) {
	}

	/**
	 * @param string $remote_id Remote platform id.
	 * @return bool
	 */
	public function has_seen( string $remote_id ): bool {
		$remote_id = trim( $remote_id );
		if ( '' === $remote_id ) {
			return false;
		}
		$set = $this->load_set();
		return isset( $set[ $remote_id ] );
	}

	/**
	 * @param string $remote_id Remote platform id.
	 * @return void
	 */
	public function remember( string $remote_id ): void {
		$remote_id = trim( $remote_id );
		if ( '' === $remote_id ) {
			return;
		}
		$set = $this->load_set();
		unset( $set[ $remote_id ] );
		$set  = array( $remote_id => 1 ) + $set;
		$keys = array_keys( $set );
		if ( count( $keys ) > $this->max_ids ) {
			$keys = array_slice( $keys, 0, $this->max_ids );
			$new  = array();
			foreach ( $keys as $k ) {
				$new[ $k ] = 1;
			}
			$set = $new;
		}
		update_option( $this->option_name, array_keys( $set ), false );
	}

	/**
	 * @return array<string, int>
	 */
	private function load_set(): array {
		$raw = get_option( $this->option_name, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$set = array();
		foreach ( $raw as $id ) {
			$id = (string) $id;
			if ( '' !== $id ) {
				$set[ $id ] = 1;
			}
		}
		return $set;
	}
}
