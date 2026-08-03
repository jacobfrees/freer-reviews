<?php
/**
 * Front-end display: review list, aggregate rating, widget. Supports an
 * optional target filter so each "thing" can have its own reviews.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FFR_Display {

	public static function init() {
		add_shortcode( 'freeflow_reviews', array( __CLASS__, 'shortcode_reviews' ) );
		add_shortcode( 'freeflow_rating', array( __CLASS__, 'shortcode_rating' ) );
	}

	/** @return int[] */
	public static function get_approved( $target = '' ) {
		$args = array(
			'post_type'      => FFR_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		);
		$term = FFR_Target::by_slug( $target );
		if ( $term ) {
			$args['tax_query'] = array( array(
				'taxonomy' => FFR_Target::TAX,
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
			'title'  => __( 'What People Say', 'freeflow-reviews' ),
		), $atts, 'freeflow_reviews' );

		$posts = self::get_approved( $atts['target'] );
		if ( empty( $posts ) ) {
			return '';
		}
		if ( $atts['limit'] ) {
			$posts = array_slice( $posts, 0, (int) $atts['limit'] );
		}

		wp_enqueue_style( 'ffr-front' );
		$term_label = FFR_Target::label( FFR_Target::by_slug( $atts['target'] ) );

		ob_start();
		$list_title = $atts['title'] ? $atts['title'] : sprintf( __( 'Reviews of %s', 'freeflow-reviews' ), $term_label );
		echo '<h3 class="ffr-list__title">' . esc_html( $list_title ) . '</h3>';
		echo '<div class="ffr-list">';
		foreach ( $posts as $pid ) {
			$rating = (int) get_post_meta( $pid, 'ffr_rating', true );
			$max    = FFR_Post_Type::max_of( $pid );
			$name   = get_post_meta( $pid, 'ffr_reviewer_name', true ) ?: __( 'Anonymous', 'freeflow-reviews' );
			$source = get_post_meta( $pid, 'ffr_source', true );
			$date   = get_the_date( get_option( 'date_format' ), $pid );
			?>
			<div class="ffr-item">
				<div class="ffr-item__top">
					<span class="ffr-item__name"><?php echo esc_html( $name ); ?></span>
					<?php self::stars( $rating, $max ); ?>
					<span class="ffr-item__value"><?php echo esc_html( $rating . '/' . $max ); ?></span>
					<span class="ffr-item__meta"><?php echo esc_html( $date ); ?></span>
					<?php if ( $source && 'site' !== $source ) : ?>
						<span class="ffr-item__badge ffr-item__badge--google"><?php esc_html_e( 'Google', 'freeflow-reviews' ); ?></span>
					<?php endif; ?>
				</div>
				<div class="ffr-item__text"><?php echo wp_kses_post( wpautop( get_post_field( 'post_content', $pid ) ) ); ?></div>
			</div>
			<?php
		}
		echo '</div>';
		return ob_get_clean();
	}

	public static function shortcode_rating( $atts ) {
		$atts = shortcode_atts( array(
			'target' => '',
			'title'  => __( 'Average Rating', 'freeflow-reviews' ),
		), $atts, 'freeflow_rating' );

		$posts = self::get_approved( $atts['target'] );
		if ( empty( $posts ) ) {
			return '';
		}

		$max   = FFR_Target::max_for( FFR_Target::by_slug( $atts['target'] ) );
		$sum   = 0;
		foreach ( $posts as $pid ) {
			$sum += (int) get_post_meta( $pid, 'ffr_rating', true );
		}
		$avg   = round( $sum / count( $posts ), 1 );

		wp_enqueue_style( 'ffr-front' );

		ob_start();
		?>
		<div class="ffr-rating">
			<div class="ffr-rating__value"><?php echo esc_html( number_format_i18n( $avg, 1 ) ); ?> / <?php echo esc_html( $max ); ?></div>
			<?php self::stars( (int) round( $avg ), $max ); ?>
			<div class="ffr-rating__count"><?php echo esc_html( sprintf( _n( '%s review', '%s reviews', count( $posts ), 'freeflow-reviews' ), number_format_i18n( count( $posts ) ) ) ); ?></div>
		</div>
		<?php
		FFR_Schema::echo_aggregate( $avg, count( $posts ), $max );
		return ob_get_clean();
	}

	public static function stars( $rating, $max = 5 ) {
		$max    = max( 1, min( 10, (int) $max ) );
		$rating = max( 0, min( $max, (int) round( $rating ) ) );
		echo '<span class="ffr-stars ffr-stars--readonly" style="--ffr-stars:' . esc_attr( (string) $max ) . '" aria-label="' . esc_attr( sprintf( __( '%d out of %d stars', 'freeflow-reviews' ), $rating, $max ) ) . '">';
		for ( $i = 1; $i <= $max; $i++ ) {
			echo '<span class="ffr-star' . ( $i <= $rating ? ' ffr-star--filled' : '' ) . '">&#9733;</span>';
		}
		echo '</span>';
	}
}

// Widget registration.
add_action( 'widgets_init', function () {
	register_widget( 'FFR_Reviews_Widget' );
} );

class FFR_Reviews_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct( 'ffr_reviews', __( 'FreeFlow Reviews', 'freeflow-reviews' ) );
	}
	public function widget( $args, $instance ) {
		echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . esc_html( $instance['title'] ) . $args['after_title'];
		}
		echo do_shortcode( '[freeflow_reviews limit="3" title=""]' );
		echo $args['after_widget'];
	}
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : __( 'Recent Reviews', 'freeflow-reviews' );
		echo '<p><label>' . esc_html__( 'Title', 'freeflow-reviews' ) . ': <input class="widefat" name="' . esc_attr( $this->get_field_name( 'title' ) ) . '" value="' . esc_attr( $title ) . '" /></label></p>';
	}
}
