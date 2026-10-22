<?php
/**
 * schema.org structured data (Review + AggregateRating) for SEO/AEO.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Freer_Reviews_Schema {

	public static function init() {
		// no-op; methods called from display
	}

	/**
	 * Print AggregateRating JSON-LD. $item can be the site home or a specific entity.
	 * Business @type is configurable (MedicalBusiness / EmergencyService recommended
	 * for trauma + biohazard cleanup sites — generic LocalBusiness undersells the niche).
	 */
	public static function echo_aggregate( $rating, $count, $max = 5, $item = null ) {
		if ( $count < 1 ) {
			return;
		}
		if ( ! $item ) {
			$item = home_url( '/' );
		}
		$max = max( 1, min( 10, (int) $max ) );
		$schema = array(
			'@context'      => 'https://schema.org',
			'@type'         => self::business_type(),
			'name'          => get_bloginfo( 'name' ),
			'url'           => home_url( '/' ),
		);
		$service_type = self::service_type();
		if ( $service_type ) {
			$schema['serviceType'] = $service_type;
		}
		$area = self::area_served();
		if ( ! empty( $area ) ) {
			$schema['areaServed'] = $area;
		}
		$schema['aggregateRating'] = array(
			'@type'       => 'AggregateRating',
			'ratingValue' => (string) round( $rating, 1 ),
			'bestRating'  => (string) $max,
			'ratingCount' => (string) $count,
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES ) . '</script>';
	}

	/** Configured schema @type, defaulting to MedicalBusiness for the cleanup vertical. */
	public static function business_type() {
		$s = Freer_Reviews_Email::settings();
		$t = isset( $s['schema_business_type'] ) ? trim( $s['schema_business_type'] ) : '';
		return $t ? $t : 'MedicalBusiness';
	}

	/** Comma-separated serviceType string from settings, or ''. */
	public static function service_type() {
		$s = Freer_Reviews_Email::settings();
		return isset( $s['schema_service_type'] ) ? trim( $s['schema_service_type'] ) : '';
	}

	/** areaServed array of Place entries (one per line in settings), or array(). */
	public static function area_served() {
		$s   = Freer_Reviews_Email::settings();
		$raw = isset( $s['schema_area_served'] ) ? $s['schema_area_served'] : '';
		$out = array();
		foreach ( preg_split( '/[\r\n]+/', (string) $raw ) as $line ) {
			$line = trim( wp_strip_all_tags( $line ) );
			if ( '' !== $line ) {
				$out[] = array( '@type' => 'AdministrativeArea', 'name' => $line );
			}
		}
		return $out;
	}

	/**
	 * Print a single Review JSON-LD.
	 */
	public static function echo_review( $post_id, $item = null ) {
		$rating = (int) get_post_meta( $post_id, 'freer_reviews_rating', true );
		$name   = get_post_meta( $post_id, 'freer_reviews_reviewer_name', true );
		if ( ! $item ) {
			$item = home_url( '/' );
		}
		$schema = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'Review',
			'itemReviewed' => array(
				'@type' => self::business_type(),
				'name'  => get_bloginfo( 'name' ),
				'url'   => home_url( '/' ),
			),
			'reviewRating' => array(
				'@type'       => 'Rating',
				'ratingValue' => (string) $rating,
				'bestRating'  => '5',
			),
			'author'     => array( '@type' => 'Person', 'name' => $name ?: 'Anonymous' ),
			'reviewBody' => wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ),
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES ) . '</script>';
	}
}
