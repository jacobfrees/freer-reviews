<?php
/**
 * Review submission form: shortcode, star UI, AJAX handler.
 * Supports a per-form target (the "thing" being reviewed) and a configurable
 * star scale (1..10), inherited from the target unless overridden.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FFR_Form {

	public static function init() {
		add_shortcode( 'freeflow_review_form', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_ffr_submit_review', array( __CLASS__, 'ajax_submit' ) );
		add_action( 'wp_ajax_nopriv_ffr_submit_review', array( __CLASS__, 'ajax_submit' ) );
	}

	public static function assets() {
		wp_register_style( 'ffr-front', FFR_URL . 'assets/css/ffr-front.css', array(), FFR_VERSION );
		wp_register_script( 'ffr-front', FFR_URL . 'assets/js/ffr-front.js', array( 'jquery' ), FFR_VERSION, true );
		wp_localize_script( 'ffr-front', 'FFR', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'ffr_submit' ),
			'msg'      => array(
				'required' => __( 'Please pick a rating and write a few words.', 'freeflow-reviews' ),
				'error'    => __( 'Something went wrong. Please try again.', 'freeflow-reviews' ),
			),
		) );
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'target' => '',
			'stars'  => '',
			'title'  => __( 'Leave a Review', 'freeflow-reviews' ),
		), $atts, 'freeflow_review_form' );

		$term = FFR_Target::by_slug( $atts['target'] );
		$max  = $atts['stars'] !== '' ? (int) $atts['stars'] : FFR_Target::max_for( $term );
		$max  = max( 1, min( 10, $max ) );

		wp_enqueue_style( 'ffr-front' );
		wp_enqueue_script( 'ffr-front' );

		ob_start();
		?>
		<div class="ffr-form" id="ffr-form">
			<?php if ( $atts['title'] ) : ?>
				<h3 class="ffr-form__title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>
			<form class="ffr-form__inner" method="post">
				<input type="hidden" name="ffr_nonce" value="<?php echo esc_attr( wp_create_nonce( 'ffr_submit' ) ); ?>" />
				<input type="hidden" name="ffr_target" value="<?php echo esc_attr( $atts['target'] ); ?>" />
				<input type="hidden" name="ffr_max" value="<?php echo esc_attr( $max ); ?>" />

				<div class="ffr-field">
					<span class="ffr-label"><?php esc_html_e( 'Your rating', 'freeflow-reviews' ); ?></span>
					<div class="ffr-stars ffr-stars--input" data-input="ffr_rating" data-max="<?php echo esc_attr( $max ); ?>">
						<?php for ( $i = 1; $i <= $max; $i++ ) : ?>
							<button type="button" class="ffr-star" data-value="<?php echo $i; ?>" aria-label="<?php printf( esc_attr__( '%d out of %d stars', 'freeflow-reviews' ), $i, $max ); ?>">&#9733;</button>
						<?php endfor; ?>
						<input type="hidden" name="ffr_rating" id="ffr_rating" value="0" />
					</div>
				</div>

				<div class="ffr-field">
					<label class="ffr-label" for="ffr_name"><?php esc_html_e( 'Your name', 'freeflow-reviews' ); ?></label>
					<input type="text" name="ffr_name" id="ffr_name" class="ffr-input" required />
				</div>

				<div class="ffr-field">
					<label class="ffr-label" for="ffr_email"><?php esc_html_e( 'Email (optional, for your thank-you)', 'freeflow-reviews' ); ?></label>
					<input type="email" name="ffr_email" id="ffr_email" class="ffr-input" />
				</div>

				<div class="ffr-field">
					<label class="ffr-label" for="ffr_text"><?php esc_html_e( 'Your words', 'freeflow-reviews' ); ?></label>
					<textarea name="ffr_text" id="ffr_text" class="ffr-input" rows="4" required minlength="10"></textarea>
				</div>

				<!-- Honeypot -->
				<div class="ffr-hp" aria-hidden="true">
					<label>Website<input type="text" name="ffr_website" tabindex="-1" autocomplete="off" /></label>
				</div>

				<p class="ffr-message" role="status"></p>

				<button type="submit" class="ffr-submit"><?php esc_html_e( 'Send My Review', 'freeflow-reviews' ); ?></button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function ajax_submit() {
		check_ajax_referer( 'ffr_submit', 'ffr_nonce' );

		if ( ! empty( $_POST['ffr_website'] ) ) {
			wp_send_json_success( array( 'message' => __( 'Thanks!', 'freeflow-reviews' ) ) );
		}

		$name    = isset( $_POST['ffr_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ffr_name'] ) ) : '';
		$email   = isset( $_POST['ffr_email'] ) ? sanitize_email( wp_unslash( $_POST['ffr_email'] ) ) : '';
		$text    = isset( $_POST['ffr_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ffr_text'] ) ) : '';
		$target  = isset( $_POST['ffr_target'] ) ? sanitize_title( wp_unslash( $_POST['ffr_target'] ) ) : '';
		$max     = isset( $_POST['ffr_max'] ) ? absint( $_POST['ffr_max'] ) : FFR_Target::global_max();
		$rating  = isset( $_POST['ffr_rating'] ) ? absint( $_POST['ffr_rating'] ) : 0;
		$max     = max( 1, min( 10, $max ) );
		$rating  = max( 1, min( $max, $rating ) );

		if ( $name === '' || $text === '' || $rating < 1 ) {
			wp_send_json_error( array( 'message' => __( 'Please pick a rating and write a few words.', 'freeflow-reviews' ) ) );
		}

		$term  = FFR_Target::by_slug( $target );
		$status = FFR_Target::auto_approve( $term ) ? 'publish' : 'pending';

		$post_id = FFR_Post_Type::create( array(
			'name'       => $name,
			'email'      => $email,
			'rating'     => $rating,
			'max_rating' => $max,
			'text'       => $text,
			'source'     => 'site',
			'target'     => $target,
			'status'     => $status,
		) );

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Something went wrong. Please try again.', 'freeflow-reviews' ) ) );
		}

		if ( $email ) {
			FFR_Email::thank_you( $post_id );
		}

		wp_send_json_success( array(
			'message' => __( 'Thank you. Your review helps everyone.', 'freeflow-reviews' ),
		) );
	}
}
