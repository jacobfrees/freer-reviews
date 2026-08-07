<?php
/**
 * Google Business reviews sync + manual import.
 *
 * Two paths:
 *  1) Automatic pull via the Google Business Profile / Places API using the site
 *     owner's own API key + place_id (configured in settings). Live pull needs
 *     the owner's Google credentials; the code fails safe and reports clearly.
 *  2) Manual import (JSON or CSV paste) so reviews always land even without API keys.
 *
 * Reviews are stored as freer_reviews_review entries tagged source='google' and deduped by
 * the Google review id.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Freer_Reviews_Google {

	const OPT = 'freer_reviews_google_state';

	public static function init() {
		// Optional daily scheduled sync.
		add_action( 'freer_reviews_daily_google_sync', array( __CLASS__, 'run_scheduled' ) );
	}

	public static function run_scheduled() {
		$s = Freer_Reviews_Email::settings();
		if ( ! empty( $s['google_auto_sync'] ) ) {
			self::sync_from_google();
		}
	}

	/** Schedule daily cron if enabled. Call from settings save. */
	public static function maybe_schedule( $enable ) {
		$hook = 'freer_reviews_daily_google_sync';
		if ( $enable && ! wp_next_scheduled( $hook ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', $hook );
		} elseif ( ! $enable ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	/**
	 * Pull reviews from Google Places API (legacy Place Details endpoint).
	 * Returns array( 'ok' => bool, 'message' => string, 'imported' => int ).
	 */
	public static function sync_from_google() {
		$s = Freer_Reviews_Email::settings();
		$key = isset( $s['google_api_key'] ) ? trim( $s['google_api_key'] ) : '';
		$pid = isset( $s['google_place_id'] ) ? trim( $s['google_place_id'] ) : '';

		if ( ! $key || ! $pid ) {
			return array( 'ok' => false, 'message' => __( 'Add a Google API key and place_id in Settings > Google Sync first.', 'freer-reviews' ) );
		}

		$url = add_query_arg( array(
			'place_id' => $pid,
			'fields'   => 'reviews,rating,user_ratings_total',
			'key'      => $key,
			'language' => 'en',
		), 'https://maps.googleapis.com/maps/api/place/details/json' );

		$resp = wp_remote_get( $url, array( 'timeout' => 25 ) );
		if ( is_wp_error( $resp ) ) {
			return array( 'ok' => false, 'message' => $resp->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $resp );
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );

		if ( 200 !== $code || empty( $body['result'] ) ) {
			$msg = isset( $body['error_message'] ) ? $body['error_message'] : sprintf( __( 'Google returned HTTP %d.', 'freer-reviews' ), $code );
			return array( 'ok' => false, 'message' => $msg );
		}

		$reviews = isset( $body['result']['reviews'] ) ? $body['result']['reviews'] : array();
		$imported = self::import_raw( $reviews, 'via_google' );

		return array(
			'ok' => true,
			'message' => sprintf( __( 'Fetched %d Google reviews, imported %d new.', 'freer-reviews' ), count( $reviews ), $imported ),
		);
	}

	/**
	 * Turn an array of raw Google review objects (with author_name, rating,
	 * text, time) into CPT entries, deduping on time+author as a fallback id.
	 */
	public static function import_raw( $raw, $source_label = 'google' ) {
		$imported = 0;
		foreach ( (array) $raw as $r ) {
			$name   = isset( $r['author_name'] ) ? $r['author_name'] : '';
			$rating = isset( $r['rating'] ) ? (int) $r['rating'] : 0;
			$text   = isset( $r['text'] ) ? $r['text'] : '';
			if ( ! $name || ! $rating || ! $text ) {
				continue;
			}
			$gid = isset( $r['review_id'] ) ? $r['review_id'] : ( $name . '|' . ( isset( $r['time'] ) ? $r['time'] : '' ) );

			// Dedupe.
			$existing = get_posts( array(
				'post_type'  => FREER_REVIEWS_CPT,
				'post_status' => 'any',
				'meta_key'   => 'freer_reviews_google_review_id',
				'meta_value' => $gid,
				'numberposts' => 1,
				'fields'     => 'ids',
			) );
			if ( $existing ) {
				continue;
			}

			$id = Freer_Reviews_Post_Type::create( array(
				'name'             => $name,
				'rating'           => $rating,
				'text'             => $text,
				'source'           => 'google',
				'google_review_id' => $gid,
				'status'           => 'publish',
			) );
			if ( ! is_wp_error( $id ) ) {
				$imported++;
			}
		}
		return $imported;
	}

	/**
	 * Parse pasted text into review entries. Accepts either JSON (one object or
	 * array of {name,rating,text[,google_review_id]}) or CSV rows: name,rating,text.
	 */
	public static function parse_import( $text ) {
		$text = trim( $text );
		if ( '' === $text ) {
			return array();
		}

		// JSON?
		$json = json_decode( $text, true );
		if ( is_array( $json ) ) {
			$arr = isset( $json[0] ) ? $json : array( $json );
			$entries = array();
			foreach ( $arr as $row ) {
				if ( isset( $row['name'], $row['rating'], $row['text'] ) ) {
					$entries[] = array(
						'name'             => $row['name'],
						'rating'           => (int) $row['rating'],
						'text'             => $row['text'],
						'google_review_id' => isset( $row['google_review_id'] ) ? $row['google_review_id'] : '',
					);
				}
			}
			return $entries;
		}

		// CSV fallback (name,rating,text).
		$entries = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$parts = str_getcsv( $line );
			if ( count( $parts ) >= 3 ) {
				$entries[] = array(
					'name'             => $parts[0],
					'rating'           => (int) $parts[1],
					'text'             => $parts[2],
					'google_review_id' => isset( $parts[3] ) ? $parts[3] : '',
				);
			}
		}
		return $entries;
	}
}
