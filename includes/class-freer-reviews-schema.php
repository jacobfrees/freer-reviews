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
			'@type'         => 'LocalBusiness',
			'name'          => get_bloginfo( 'name' ),
			'url'           => home_url( '/' ),
			'aggregateRating' => array(
				'@type'       => 'AggregateRating',
				'ratingValue' => (string) round( $rating, 1 ),
				'bestRating'  => (string) $max,
				'ratingCount' => (string) $count,
			),
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES ) . '</script>';
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
			'itemReviewed' => array( '@type' => 'Organization', 'name' => get_bloginfo( 'name' ) ),
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
