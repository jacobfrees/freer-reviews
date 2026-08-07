<?php
/**
 * Custom post type + meta registration.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Freer_Reviews_Post_Type {

	const CPT = FREER_REVIEWS_CPT;

	public static function register() {
		$labels = array(
			'name'          => __( 'Submitted Reviews', 'freer-reviews' ),
			'singular_name' => __( 'Submitted Review', 'freer-reviews' ),
			'menu_name'     => __( 'Submitted Reviews', 'freer-reviews' ),
			'edit_item'     => __( 'Edit Review', 'freer-reviews' ),
			'view_item'     => __( 'View Review', 'freer-reviews' ),
			'search_items'  => __( 'Search Reviews', 'freer-reviews' ),
			'not_found'     => __( 'No reviews yet.', 'freer-reviews' ),
			'add_new'       => __( 'Add Review', 'freer-reviews' ),
			'add_new_item'  => __( 'Add New Review', 'freer-reviews' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => false, // parented under the single "Freer Reviews" menu
			'menu_icon'           => 'dashicons-star-filled',
			'menu_position'       => 25,
			'supports'            => array( 'title', 'editor' ),
			'hierarchical'        => false,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'capability_type'     => 'post',
			'capabilities'        => array(
				'create_posts' => 'manage_options',
			),
			'map_meta_cap'        => true,
			'show_in_rest'        => true,
		);

		register_post_type( self::CPT, $args );

		$meta = array(
			'freer_reviews_rating'           => array( 'type' => 'number',  'single' => true, 'sanitize_callback' => 'absint' ),
			'freer_reviews_max_rating'       => array( 'type' => 'number',  'single' => true, 'sanitize_callback' => 'absint' ),
			'freer_reviews_reviewer_name'    => array( 'type' => 'string',  'single' => true, 'sanitize_callback' => 'sanitize_text_field' ),
			'freer_reviews_reviewer_email'   => array( 'type' => 'string',  'single' => true, 'sanitize_callback' => 'sanitize_email' ),
			'freer_reviews_source'           => array( 'type' => 'string',  'single' => true, 'sanitize_callback' => 'sanitize_key' ),
			'freer_reviews_google_review_id' => array( 'type' => 'string',  'single' => true, 'sanitize_callback' => 'sanitize_text_field' ),
		);
		foreach ( $meta as $key => $args_meta ) {
			register_post_meta( self::CPT, $key, array_merge( array( 'show_in_rest' => true, 'auth_callback' => '__return_true' ), $args_meta ) );
		}
	}

	/**
	 * The star scale this review was collected on (defaults to the target's).
	 *
	 * @return int
	 */
	public static function max_of( $post_id ) {
		$m = (int) get_post_meta( $post_id, 'freer_reviews_max_rating', true );
		if ( ! $m ) {
			$m = 5;
		}
		return max( 1, min( 10, $m ) );
	}

	/**
	 * Turn an array of review data into a CPT entry.
	 *
	 * @param array $data {
	 *     name, rating(1-N), text, email(optional), source, google_review_id(optional),
	 *     target(term slug, optional), max_rating(optional), status
	 * }
	 * @return int|WP_Error Post ID or error.
	 */
	public static function create( $data ) {
		$data = wp_parse_args( $data, array(
			'name'              => '',
			'rating'            => 0,
			'max_rating'        => 0,
			'text'              => '',
			'email'             => '',
			'source'            => 'site',
			'google_review_id'  => '',
			'target'            => '',
			'status'            => 'pending',
		) );

		$post_id = wp_insert_post( array(
			'post_type'    => self::CPT,
			'post_status'  => $data['status'],
			'post_title'   => $data['name'] . ' — ' . $data['rating'] . '/' . ( $data['max_rating'] ? (int) $data['max_rating'] : Freer_Reviews_Target::global_max() ),
			'post_content' => wp_kses_post( $data['text'] ),
		), true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, 'freer_reviews_rating', absint( $data['rating'] ) );
		update_post_meta( $post_id, 'freer_reviews_reviewer_name', sanitize_text_field( $data['name'] ) );
		update_post_meta( $post_id, 'freer_reviews_reviewer_email', sanitize_email( $data['email'] ) );
		update_post_meta( $post_id, 'freer_reviews_source', sanitize_key( $data['source'] ) );
		if ( ! empty( $data['google_review_id'] ) ) {
			update_post_meta( $post_id, 'freer_reviews_google_review_id', sanitize_text_field( $data['google_review_id'] ) );
		}

		Freer_Reviews_Target::assign( $post_id, $data['target'], (int) $data['max_rating'] );

		return $post_id;
	}
}
