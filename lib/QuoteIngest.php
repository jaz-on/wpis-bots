<?php
/**
 * Create pending quotes or bump counters using WPIS Core deduplication.
 *
 * @package WPIS\Bots
 */

namespace WPIS\Bots;

/**
 * Ingests a single candidate line into the quote CPT.
 */
final class QuoteIngest {

	public const RESULT_CREATED       = 'created';
	public const RESULT_BUMPED        = 'bumped';
	public const RESULT_SKIPPED_EMPTY = 'skipped_empty';
	public const RESULT_SKIPPED_LONG  = 'skipped_too_long';
	public const RESULT_ERROR_INSERT  = 'error_insert';

	/**
	 * Process one text candidate.
	 *
	 * @param array<string, mixed> $args {
	 *     Candidate fields.
	 *
	 *     @type string $text              Quote body.
	 *     @type string $submission_source bot-mastodon|bot-bluesky.
	 *     @type string $source_platform   mastodon|bluesky.
	 *     @type string $lang              Dedup language hint (default en).
	 *     @type int    $dedup_threshold   0-100 (default 70).
	 *     @type string $source_url        Optional canonical URL.
	 *     @type string $polylang_slug     Optional Polylang language slug.
	 * }
	 * @return array{result: string, post_id?: int, error?: string}
	 */
	public static function process_candidate( array $args ): array {
		if ( ! function_exists( 'wpis_find_potential_duplicates' ) ) {
			return array(
				'result' => self::RESULT_ERROR_INSERT,
				'error'  => 'wpis_find_potential_duplicates missing',
			);
		}

		$text              = isset( $args['text'] ) ? (string) $args['text'] : '';
		$submission_source = isset( $args['submission_source'] ) ? sanitize_key( (string) $args['submission_source'] ) : '';
		$source_platform   = isset( $args['source_platform'] ) ? sanitize_key( (string) $args['source_platform'] ) : '';
		$lang              = isset( $args['lang'] ) ? sanitize_key( (string) $args['lang'] ) : 'en';
		$dedup_threshold   = isset( $args['dedup_threshold'] ) ? max( 0, min( 100, (int) $args['dedup_threshold'] ) ) : 70;
		$source_url        = isset( $args['source_url'] ) ? esc_url_raw( (string) $args['source_url'] ) : '';
		$polylang_slug     = isset( $args['polylang_slug'] ) ? sanitize_key( (string) $args['polylang_slug'] ) : '';

		$text = trim( preg_replace( '/\s+/u', ' ', $text ) ?? '' );
		if ( '' === $text ) {
			return array( 'result' => self::RESULT_SKIPPED_EMPTY );
		}

		$len = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
		if ( $len > 1000 ) {
			return array( 'result' => self::RESULT_SKIPPED_LONG );
		}

		$dupes = wpis_find_potential_duplicates( $text, $lang, $dedup_threshold );
		if ( array() !== $dupes && isset( $dupes[0]['quote_id'], $dupes[0]['score'] ) && (float) $dupes[0]['score'] >= (float) $dedup_threshold ) {
			$qid     = (int) $dupes[0]['quote_id'];
			$current = (int) get_post_meta( $qid, '_wpis_counter', true );
			if ( $current < 1 ) {
				$current = 1;
			}
			update_post_meta( $qid, '_wpis_counter', $current + 1 );
			return array(
				'result'  => self::RESULT_BUMPED,
				'post_id' => $qid,
			);
		}

		$title = self::title_from_text( $text );

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'quote',
				'post_status'  => 'pending',
				'post_title'   => $title,
				'post_content' => TextHelper::truncate_body( $text, 1000 ),
				'post_author'  => 0,
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			$msg = is_wp_error( $post_id ) ? $post_id->get_error_message() : 'insert failed';
			return array(
				'result' => self::RESULT_ERROR_INSERT,
				'error'  => $msg,
			);
		}

		$post_id = (int) $post_id;

		update_post_meta( $post_id, '_wpis_counter', 1 );
		update_post_meta( $post_id, '_wpis_submission_source', $submission_source );
		update_post_meta( $post_id, '_wpis_source_platform', $source_platform );

		if ( $source_url ) {
			$domain = wp_parse_url( $source_url, PHP_URL_HOST );
			if ( is_string( $domain ) && '' !== $domain ) {
				update_post_meta( $post_id, '_wpis_source_domain', sanitize_text_field( $domain ) );
			}
		}

		if ( '' !== $polylang_slug && function_exists( 'pll_languages_list' ) && function_exists( 'pll_set_post_language' ) ) {
			$valid = pll_languages_list( array( 'fields' => 'slug' ) );
			if ( is_array( $valid ) && in_array( $polylang_slug, $valid, true ) ) {
				pll_set_post_language( $post_id, $polylang_slug );
			}
		}

		/**
		 * Fires after a bot ingested a new pending quote.
		 *
		 * @param int $post_id New quote ID.
		 */
		do_action( 'wpis_quote_submitted', $post_id );

		return array(
			'result'  => self::RESULT_CREATED,
			'post_id' => $post_id,
		);
	}

	/**
	 * @param string $text Quote text.
	 * @return string
	 */
	public static function title_from_text( string $text ): string {
		$t = wp_html_excerpt( $text, 80, '…' );
		return '' !== $t ? $t : 'Quote submission';
	}
}
