<?php
/**
 * Review submission form: shortcode, star UI, AJAX handler.
 * Supports a per-form target (the "thing" being reviewed), a configurable
 * star scale (1..10), and per-field customization (show/hide, required/
 * optional, labels, placeholders, submit text, min length).
 *
 * Config resolution order:  defaults  ->  target term-meta  ->  shortcode atts
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Freer_Reviews_Form {

	const FIELDS = array( 'rating', 'name', 'email', 'text' );

	public static function init() {
		add_shortcode( 'freer_reviews_review_form', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_freer_reviews_submit_review', array( __CLASS__, 'ajax_submit' ) );
		add_action( 'wp_ajax_nopriv_freer_reviews_submit_review', array( __CLASS__, 'ajax_submit' ) );
		add_action( 'wp_ajax_freer_reviews_human_verify', array( 'Freer_Reviews_Antispam', 'ajax_human_verify' ) );
		add_action( 'wp_ajax_nopriv_freer_reviews_human_verify', array( 'Freer_Reviews_Antispam', 'ajax_human_verify' ) );
	}

	public static function assets() {
		wp_register_style( 'freer_reviews_front', FREER_REVIEWS_URL . 'assets/css/freer-reviews-front.css', array(), FREER_REVIEWS_VERSION );
		wp_register_script( 'freer_reviews_front', FREER_REVIEWS_URL . 'assets/js/freer-reviews-front.js', array( 'jquery' ), FREER_REVIEWS_VERSION, true );
		wp_localize_script( 'freer_reviews_front', 'FREER_REVIEWS', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'freer_reviews_submit' ),
			'msg'      => array(
				'required' => __( 'Please fill in the required fields.', 'freer-reviews' ),
				'error'    => __( 'Something went wrong. Please try again.', 'freer-reviews' ),
			),
		) );
	}

	/**
	 * The default form config. Field keys: rating, name, email, text.
	 */
	public static function defaults() {
		return array(
			// Which fields are shown.
			'show_rating' => 1,
			'show_name'   => 1,
			'show_email'  => 1,
			'show_text'   => 1,
			// Which shown fields are required.
			'require_rating' => 1,
			'require_name'   => 1,
			'require_email'  => 0,
			'require_text'   => 1,
			// Labels.
			'label_rating' => __( 'Your rating', 'freer-reviews' ),
			'label_name'   => __( 'Your name', 'freer-reviews' ),
			'label_email'  => __( 'Email (optional, for your thank-you)', 'freer-reviews' ),
			'label_text'   => __( 'Your words', 'freer-reviews' ),
			// Placeholders.
			'placeholder_name'  => '',
			'placeholder_email' => '',
			'placeholder_text'  => '',
			// Submit + validation.
			'submit' => __( 'Send My Review', 'freer-reviews' ),
			'min'    => 10, // minimum characters for the text field (0 = none)
		);
	}

	/**
	 * Resolve a form's effective config for a given target + shortcode atts.
	 *
	 * @param string $target Target slug (may be '').
	 * @param array  $atts   Shortcode attributes (wins over term meta).
	 * @return array
	 */
	public static function config( $target = '', $atts = array() ) {
		$cfg = self::defaults();

		// 1) Target term-meta overrides.
		$term = Freer_Reviews_Target::by_slug( $target );
		if ( $term ) {
			$meta = (array) get_term_meta( $term->term_id, 'freer_reviews_form_config', true );
			if ( ! empty( $meta ) && is_array( $meta ) ) {
				$cfg = array_merge( $cfg, array_intersect_key( $meta, $cfg ) );
			}
		}

		// 2) Shortcode atts (explicit per-field keys win).
		foreach ( $cfg as $key => $_ ) {
			if ( isset( $atts[ $key ] ) && $atts[ $key ] !== '' ) {
				if ( in_array( $key, array( 'show_rating', 'show_name', 'show_email', 'show_text', 'require_rating', 'require_name', 'require_email', 'require_text' ), true ) ) {
					$cfg[ $key ] = (string) $atts[ $key ] !== '0' && (string) $atts[ $key ] !== 'false' ? 1 : 0;
				} else {
					$cfg[ $key ] = $atts[ $key ];
				}
			}
		}

		return $cfg;
	}

	/**
	 * Which of the four fields are actually shown, in canonical order.
	 */
	public static function active_fields( $cfg ) {
		$active = array();
		foreach ( self::FIELDS as $f ) {
			if ( ! empty( $cfg[ 'show_' . $f ] ) ) {
				$active[] = $f;
			}
		}
		return $active;
	}

	public static function shortcode( $atts ) {
		$atts = (array) $atts;
		$raw  = $atts; // only keys the user actually typed (so term-meta can win)

		$cfg_defaults = self::defaults();
		$sc_atts = array_merge( array(
			'target' => '',
			'stars'  => '',
			'title'  => __( 'Leave a Review', 'freer-reviews' ),
			'show'   => '1', // render the approved stack + average above the form
		), $cfg_defaults );

		$atts = shortcode_atts( $sc_atts, $atts, 'freer_reviews_review_form' );

		$term = Freer_Reviews_Target::by_slug( $atts['target'] );
		$max  = $atts['stars'] !== '' ? (int) $atts['stars'] : Freer_Reviews_Target::max_for( $term );
		$max  = max( 1, min( 10, $max ) );

		// Raw user atts only — keys not typed by the user fall back to term-meta then defaults.
		$cfg     = self::config( $atts['target'], $raw );
		$active  = self::active_fields( $cfg );
		$min     = max( 0, (int) $cfg['min'] );
		$require = array();
		foreach ( $active as $f ) {
			if ( ! empty( $cfg[ 'require_' . $f ] ) ) {
				$require[] = $f;
			}
		}

		wp_enqueue_style( 'freer_reviews_front' );
		wp_enqueue_script( 'freer_reviews_front' );

		ob_start();
		?>
		<div class="freer-reviews-form" id="freer-reviews-form"
			data-fields="<?php echo esc_attr( implode( ',', $active ) ); ?>"
			data-require="<?php echo esc_attr( implode( ',', $require ) ); ?>"
			data-min="<?php echo esc_attr( $min ); ?>">
			<?php if ( $atts['title'] ) : ?>
				<h3 class="freer-reviews-form__title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>
			<form class="freer-reviews-form__inner" method="post">
				<input type="hidden" name="freer_reviews_nonce" value="<?php echo esc_attr( wp_create_nonce( 'freer_reviews_submit' ) ); ?>" />
				<input type="hidden" name="freer_reviews_target" value="<?php echo esc_attr( $atts['target'] ); ?>" />
				<input type="hidden" name="freer_reviews_max" value="<?php echo esc_attr( $max ); ?>" />

				<?php if ( in_array( 'rating', $active, true ) ) : ?>
				<div class="freer-reviews-field">
					<span class="freer-reviews-label"><?php echo esc_html( $cfg['label_rating'] ); ?>
						<?php if ( in_array( 'rating', $require, true ) ) : ?><span class="freer-reviews-opt" aria-hidden="true"> *</span><?php endif; ?>
					</span>
					<div class="freer-reviews-stars freer-reviews-stars--input" data-input="freer_reviews_rating" data-max="<?php echo esc_attr( $max ); ?>">
						<?php for ( $i = 1; $i <= $max; $i++ ) : ?>
							<button type="button" class="freer-reviews-star" data-value="<?php echo $i; ?>" aria-label="<?php printf( esc_attr__( '%d out of %d stars', 'freer-reviews' ), $i, $max ); ?>">&#9733;</button>
						<?php endfor; ?>
						<input type="hidden" name="freer_reviews_rating" id="freer_reviews_rating" value="0" />
					</div>
				</div>
				<?php endif; ?>

				<?php if ( in_array( 'name', $active, true ) ) : ?>
				<div class="freer-reviews-field">
					<label class="freer-reviews-label" for="freer_reviews_name"><?php echo esc_html( $cfg['label_name'] ); ?>
						<?php if ( in_array( 'name', $require, true ) ) : ?><span class="freer-reviews-opt" aria-hidden="true"> *</span><?php endif; ?>
					</label>
					<input type="text" name="freer_reviews_name" id="freer_reviews_name" class="freer-reviews-input"
						<?php echo in_array( 'name', $require, true ) ? 'required' : ''; ?>
						placeholder="<?php echo esc_attr( $cfg['placeholder_name'] ); ?>" />
				</div>
				<?php endif; ?>

				<?php if ( in_array( 'email', $active, true ) ) : ?>
				<div class="freer-reviews-field">
					<label class="freer-reviews-label" for="freer_reviews_email"><?php echo esc_html( $cfg['label_email'] ); ?>
						<?php if ( in_array( 'email', $require, true ) ) : ?><span class="freer-reviews-opt" aria-hidden="true"> *</span><?php endif; ?>
					</label>
					<input type="email" name="freer_reviews_email" id="freer_reviews_email" class="freer-reviews-input"
						<?php echo in_array( 'email', $require, true ) ? 'required' : ''; ?>
						placeholder="<?php echo esc_attr( $cfg['placeholder_email'] ); ?>" />
				</div>
				<?php endif; ?>

				<?php if ( in_array( 'text', $active, true ) ) : ?>
				<div class="freer-reviews-field">
					<label class="freer-reviews-label" for="freer_reviews_text"><?php echo esc_html( $cfg['label_text'] ); ?>
						<?php if ( in_array( 'text', $require, true ) ) : ?><span class="freer-reviews-opt" aria-hidden="true"> *</span><?php endif; ?>
					</label>
					<textarea name="freer_reviews_text" id="freer_reviews_text" class="freer-reviews-input" rows="4"
						<?php if ( in_array( 'text', $require, true ) ) : ?>required<?php endif; ?>
						<?php if ( $min ) : ?>minlength="<?php echo esc_attr( $min ); ?>"<?php endif; ?>
						placeholder="<?php echo esc_attr( $cfg['placeholder_text'] ); ?>"></textarea>
				</div>
				<?php endif; ?>

				<!-- Freer anti-spam (hidden honeypots + time trap) -->
				<?php echo Freer_Reviews_Antispam::fields( 'f' ); // phpcs:ignore WordPress.Security.EscapeOutput -- safe static HTML ?>

				<!-- Single-click human verification -->
				<?php echo Freer_Reviews_Antispam::captcha_markup( 'f' ); // phpcs:ignore WordPress.Security.EscapeOutput -- safe static HTML ?>

				<p class="freer-reviews-message" role="status"></p>

				<button type="submit" class="freer-reviews-submit"><?php echo esc_html( $cfg['submit'] ); ?></button>
			</form>
		</div>
		<?php
		$form_html = ob_get_clean();

		// Default feature: show the approved reviews + aggregate rating ABOVE the
		// form (average + stars + people's text first, form to leave a new one
		// below). Opt out entirely with show="0".
		$display_html = '';
		if ( '1' === (string) $atts['show'] ) {
			$posts = Freer_Reviews_Display::get_approved( $atts['target'] );
			if ( ! empty( $posts ) ) {
				$display_html .= Freer_Reviews_Display::shortcode_rating( array( 'target' => $atts['target'] ) );
				$display_html .= Freer_Reviews_Display::shortcode_reviews( array( 'target' => $atts['target'], 'limit' => 0 ) );
			}
		}

		return $display_html . $form_html;
	}

	public static function ajax_submit() {
		check_ajax_referer( 'freer_reviews_submit', 'freer_reviews_nonce' );

		// Bot / spam protection: honeypots, time trap, IP rate limit, link scan.
		if ( Freer_Reviews_Antispam::is_spam( 'f' ) ) {
			wp_send_json_success( array( 'message' => __( 'Thanks!', 'freer-reviews' ) ) );
		}

		// Single-click human verification.
		if ( ! Freer_Reviews_Antispam::captcha_ok( 'f' ) ) {
			wp_send_json_error( array( 'message' => __( 'Please complete the verification before sending.', 'freer-reviews' ) ) );
		}

		$name    = isset( $_POST['freer_reviews_name'] ) ? sanitize_text_field( wp_unslash( $_POST['freer_reviews_name'] ) ) : '';
		$email   = isset( $_POST['freer_reviews_email'] ) ? sanitize_email( wp_unslash( $_POST['freer_reviews_email'] ) ) : '';
		$text    = isset( $_POST['freer_reviews_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['freer_reviews_text'] ) ) : '';
		$target  = isset( $_POST['freer_reviews_target'] ) ? sanitize_title( wp_unslash( $_POST['freer_reviews_target'] ) ) : '';
		$max     = isset( $_POST['freer_reviews_max'] ) ? absint( $_POST['freer_reviews_max'] ) : Freer_Reviews_Target::global_max();
		$rating  = isset( $_POST['freer_reviews_rating'] ) ? absint( $_POST['freer_reviews_rating'] ) : 0;
		$max     = max( 1, min( 10, $max ) );
		$rating  = max( 0, min( $max, $rating ) );

		// Resolve config so the server honours the same show/require rules as the form.
		$cfg     = self::config( $target );
		$active  = self::active_fields( $cfg );
		$min     = max( 0, (int) $cfg['min'] );

		$errors = array();
		foreach ( $active as $f ) {
			if ( empty( $cfg[ 'require_' . $f ] ) ) {
				continue; // optional field, never blocks
			}
			$value = ${$f}; // $rating, $name, $email, $text
			if ( 'rating' === $f ) {
				if ( (int) $value < 1 ) { $errors[] = 'rating'; }
			} elseif ( 'text' === $f ) {
				if ( $min > 0 && mb_strlen( $value ) < $min ) { $errors[] = 'text'; }
				elseif ( $value === '' ) { $errors[] = 'text'; }
			} else {
				if ( $value === '' ) { $errors[] = $f; }
			}
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'message' => __( 'Please fill in the required fields.', 'freer-reviews' ) ) );
		}

		$term   = Freer_Reviews_Target::by_slug( $target );
		$status = Freer_Reviews_Target::auto_approve( $term ) ? 'publish' : 'pending';

		$post_id = Freer_Reviews_Post_Type::create( array(
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
			wp_send_json_error( array( 'message' => __( 'Something went wrong. Please try again.', 'freer-reviews' ) ) );
		}

		if ( $email ) {
			Freer_Reviews_Email::thank_you( $post_id );
		}

		wp_send_json_success( array(
			'message' => __( 'Thank you. Your review helps everyone.', 'freer-reviews' ),
		) );
	}
}
