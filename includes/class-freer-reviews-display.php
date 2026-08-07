<?php
/**
 * Front-end display: review list, aggregate rating, widget. Supports an
 * optional target filter so each "thing" can have its own reviews.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Freer_Reviews_Display {

	public static function init() {
		add_shortcode( 'freer_reviews', array( __CLASS__, 'shortcode_reviews' ) );
		add_shortcode( 'freer_reviews_rating', array( __CLASS__, 'shortcode_rating' ) );
	}

	/** @return int[] */
	public static function get_approved( $target = '' ) {
		$args = array(
			'post_type'      => FREER_REVIEWS_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		);
		$term = Freer_Reviews_Target::by_slug( $target );
		if ( $term ) {
			$args['tax_query'] = array( array(
				'taxonomy' => Freer_Reviews_Target::TAX,
				'field'    => 'term_id',
				'terms'    => $term->term_id,
			) );
		}
		return get_posts( $args );
	}

	public static function shortcode_reviews( $atts ) {
		$atts = shortcode_atts( array(
			'target' => '',
			'limit'  => 20,
			'title'  => __( 'What People Say', 'freer-reviews' ),
		), $atts, 'freer_reviews' );

		$posts = self::get_approved( $atts['target'] );
		if ( empty( $posts ) ) {
			return '';
		}
		if ( $atts['limit'] ) {
			$posts = array_slice( $posts, 0, (int) $atts['limit'] );
		}

		wp_enqueue_style( 'freer_reviews_front' );
		$term_label = Freer_Reviews_Target::label( Freer_Reviews_Target::by_slug( $atts['target'] ) );

		ob_start();
		$list_title = $atts['title'] ? $atts['title'] : sprintf( __( 'Reviews of %s', 'freer-reviews' ), $term_label );
		echo '<h3 class="freer-reviews-list__title">' . esc_html( $list_title ) . '</h3>';
		echo '<div class="freer-reviews-list">';
		foreach ( $posts as $pid ) {
			$rating = (int) get_post_meta( $pid, 'freer_reviews_rating', true );
			$max    = Freer_Reviews_Post_Type::max_of( $pid );
			$name   = get_post_meta( $pid, 'freer_reviews_reviewer_name', true ) ?: __( 'Anonymous', 'freer-reviews' );
			$source = get_post_meta( $pid, 'freer_reviews_source', true );
			$date   = get_the_date( get_option( 'date_format' ), $pid );
			?>
			<div class="freer-reviews-item">
				<div class="freer-reviews-item__top">
					<span class="freer-reviews-item__name"><?php echo esc_html( $name ); ?></span>
					<?php self::stars( $rating, $max ); ?>
					<span class="freer-reviews-item__value"><?php echo esc_html( $rating . '/' . $max ); ?></span>
					<span class="freer-reviews-item__meta"><?php echo esc_html( $date ); ?></span>
					<?php if ( $source && 'site' !== $source ) : ?>
						<span class="freer-reviews-item__badge freer-reviews-item__badge--google"><?php esc_html_e( 'Google', 'freer-reviews' ); ?></span>
					<?php endif; ?>
				</div>
				<div class="freer-reviews-item__text"><?php echo wp_kses_post( wpautop( get_post_field( 'post_content', $pid ) ) ); ?></div>
			</div>
			<?php
		}
		echo '</div>';
		return ob_get_clean();
	}

	public static function shortcode_rating( $atts ) {
		$atts = shortcode_atts( array(
			'target' => '',
			'title'  => __( 'Average Rating', 'freer-reviews' ),
		), $atts, 'freer_reviews_rating' );

		$posts = self::get_approved( $atts['target'] );
		if ( empty( $posts ) ) {
			return '';
		}

		$max   = Freer_Reviews_Target::max_for( Freer_Reviews_Target::by_slug( $atts['target'] ) );
		$sum   = 0;
		foreach ( $posts as $pid ) {
			$sum += (int) get_post_meta( $pid, 'freer_reviews_rating', true );
		}
		$avg   = round( $sum / count( $posts ), 1 );

		wp_enqueue_style( 'freer_reviews_front' );

		ob_start();
		?>
		<div class="freer-reviews-rating">
			<div class="freer-reviews-rating__value"><?php echo esc_html( number_format_i18n( $avg, 1 ) ); ?> / <?php echo esc_html( $max ); ?></div>
			<?php self::stars( (int) round( $avg ), $max ); ?>
			<div class="freer-reviews-rating__count"><?php echo esc_html( sprintf( _n( '%s review', '%s reviews', count( $posts ), 'freer-reviews' ), number_format_i18n( count( $posts ) ) ) ); ?></div>
		</div>
		<?php
		Freer_Reviews_Schema::echo_aggregate( $avg, count( $posts ), $max );
		return ob_get_clean();
	}

	public static function stars( $rating, $max = 5 ) {
		$max    = max( 1, min( 10, (int) $max ) );
		$rating = max( 0, min( $max, (int) round( $rating ) ) );
		echo '<span class="freer-reviews-stars freer-reviews-stars--readonly" style="--freer-reviews-stars:' . esc_attr( (string) $max ) . '" aria-label="' . esc_attr( sprintf( __( '%d out of %d stars', 'freer-reviews' ), $rating, $max ) ) . '">';
		for ( $i = 1; $i <= $max; $i++ ) {
			echo '<span class="freer-reviews-star' . ( $i <= $rating ? ' freer-reviews-star--filled' : '' ) . '">&#9733;</span>';
		}
		echo '</span>';
	}
}

// Widget registration.
add_action( 'widgets_init', function () {
	register_widget( 'Freer_Reviews_Widget' );
} );

class Freer_Reviews_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct( 'freer_reviews', __( 'Freer Reviews', 'freer-reviews' ) );
	}
	public function widget( $args, $instance ) {
		echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . esc_html( $instance['title'] ) . $args['after_title'];
		}
		echo do_shortcode( '[freer_reviews limit="3" title=""]' );
		echo $args['after_widget'];
	}
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : __( 'Recent Reviews', 'freer-reviews' );
		echo '<p><label>' . esc_html__( 'Title', 'freer-reviews' ) . ': <input class="widefat" name="' . esc_attr( $this->get_field_name( 'title' ) ) . '" value="' . esc_attr( $title ) . '" /></label></p>';
	}
}
