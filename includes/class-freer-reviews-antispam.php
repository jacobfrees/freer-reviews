<?php
/**
 * Freer Reviews — bot & spam protection.
 *
 * Multi-layer, dependency-free defence used by BOTH the review form and the
 * WordPress comment form:
 *
 *   1. Honeypot fields   – hidden traps a human can't see; bots auto-fill them.
 *   2. Time trap         – a hidden "page rendered at" stamp; a submission that
 *                          lands faster than a human can read+publish is a bot.
 *   3. IP rate limit     – throttles submissions per IP so a single bot can't
 *                          flood the inbox.
 *   4. Link-in-text scan – flags payloads stuffed with URLs (typical spam).
 *
 * Nothing is user-facing (fields are visually hidden + off-tab-order); valid
 * submissions pass through with zero friction.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Freer_Reviews_Antispam {

	// Minimum seconds a real human realistically needs to submit.
	const MIN_FILL_SECONDS = 3;

	// Max submissions from one IP within the window.
	const RATE_MAX   = 5;
	const RATE_WINDOW = 600; // seconds (10 min)

	/**
	 * HTML for the hidden anti-spam fields. Unique names so any page can hold
	 * several forms (a review form + a comment form) without collisions.
	 *
	 * @param string $group Unique id for this form's fields.
	 */
	public static function fields( $group = 'f' ) {
		$hp1 = 'freer_reviews_' . $group . '_web';
		$hp2 = 'freer_reviews_' . $group . '_url';
		$tt  = 'freer_reviews_' . $group . '_t';
		$now = time();
		ob_start();
		?>
		<!-- Freer anti-spam (hidden) -->
		<div class="freer-reviews-hp" aria-hidden="true">
			<label><?php echo esc_html( __( 'Website', 'freer-reviews' ) ); ?><input type="text" name="<?php echo esc_attr( $hp1 ); ?>" tabindex="-1" autocomplete="off" /></label>
			<label><?php echo esc_html( __( 'Your homepage url', 'freer-reviews' ) ); ?><input type="text" name="<?php echo esc_attr( $hp2 ); ?>" tabindex="-1" autocomplete="off" /></label>
		</div>
		<input type="hidden" name="<?php echo esc_attr( $tt ); ?>" value="<?php echo esc_attr( $now ); ?>" />
		<?php
		return ob_get_clean();
	}

	/**
	 * Run the full spam battery for a request whose fields used $group.
	 * Returns true when the submission is SPAM (should be rejected).
	 *
	 * @param string $group Matches the group passed to fields().
	 */
	public static function is_spam( $group = 'f' ) {
		$hp1 = 'freer_reviews_' . $group . '_web';
		$hp2 = 'freer_reviews_' . $group . '_url';
		$tt  = 'freer_reviews_' . $group . '_t';

		// 1) Honeypots: any value = a bot filled a hidden field.
		if ( ! empty( $_POST[ $hp1 ] ) || ! empty( $_POST[ $hp2 ] ) ) {
			return true;
		}

		// 2) Time trap: no stamp, or filled unreasonably fast.
		$stamp = isset( $_POST[ $tt ] ) ? (int) $_POST[ $tt ] : 0;
		if ( ! $stamp || ( time() - $stamp ) < self::MIN_FILL_SECONDS ) {
			return true;
		}

		// 3) IP rate limit.
		if ( self::rate_limited() ) {
			return true;
		}

		// 4) Link-stuffed content.
		if ( self::has_spammy_links() ) {
			return true;
		}

		return false;
	}

	/** @return string */
	private static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip    = trim( $parts[0] );
		}
		$ip = preg_replace( '/[^0-9a-fA-F:.]/', '', (string) $ip );
		return $ip ? $ip : 'unknown';
	}

	/** @return bool true when this IP has hit the ceiling. */
	private static function rate_limited() {
		$key = 'freer_reviews_rl_' . md5( self::client_ip() . wp_get_environment_type() );
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_MAX ) {
			return true;
		}
		set_transient( $key, $count + 1, self::RATE_WINDOW );
		return false;
	}

	/** @return array current settings */
	private static function settings() {
		return Freer_Reviews_Email::settings();
	}

	/** @return bool whether captcha is enabled site-wide. */
	public static function captcha_enabled() {
		$s = self::settings();
		return ! empty( $s['captcha_enable'] );
	}

	/**
	 * Whether real Cloudflare Turnstile is configured (site+secret both set).
	 * @return bool
	 */
	public static function turnstile_configured() {
		$s = self::settings();
		return ! empty( $s['turnstile_site'] ) && ! empty( $s['turnstile_secret'] );
	}

	/**
	 * Render the single-click human check. Uses Cloudflare Turnstile when
	 * configured, otherwise the built-in click verification.
	 *
	 * @return string
	 */
	public static function captcha_markup( $group = 'f' ) {
		if ( ! self::captcha_enabled() ) {
			return '';
		}
		if ( self::turnstile_configured() ) {
			$s = self::settings();
			add_action( 'wp_footer', array( __CLASS__, 'turnstile_script' ), 99 );
			return self::turnstile_widget( $s['turnstile_site'], $group );
		}
		return self::click_widget( $group );
	}

	/** Cloudflare Turnstile widget + pass-through hidden field. */
	private static function turnstile_widget( $site_key, $group ) {
		$field = 'freer_reviews_' . $group . '_turnstile';
		ob_start();
		?>
		<div class="freer-reviews-captcha" style="margin:.6rem 0;">
			<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $site_key ); ?>" data-callback="<?php echo esc_attr( 'freer_reviews_turnstile_' . $group . '(this)' ); ?>"></div>
			<input type="hidden" name="<?php echo esc_attr( $field ); ?>" value="" />
		</div>
		<?php
		return ob_get_clean();
	}

	/** Loads the Turnstile script once (only when a widget was printed). */
	public static function turnstile_script() {
		if ( ! self::captcha_enabled() || ! self::turnstile_configured() ) {
			return;
		}
		?>
		<script>
		window.freer_reviews_turnstile_f = function (w) { var r=w.getResponse(); var i=w.closest('.freer-reviews-captcha').querySelector('input[type=hidden]'); if(i){i.value=r;} };
		window.freer_reviews_turnstile_c = function (w) { var r=w.getResponse(); var i=w.closest('.freer-reviews-captcha').querySelector('input[type=hidden]'); if(i){i.value=r;} };
		</script>
		<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
		<?php
	}

	/**
	 * Built-in single-click check. A tiny "I'm human" toggle; clicking calls the
	 * server (via AJAX) which issues a short-lived token for this browser/IP that
	 * the review submit must present. Because the token only exists after a real
	 * click round-trip, a plain automated POST can't pass it.
	 */
	private static function click_widget( $group ) {
		$field  = 'freer_reviews_' . $group . '_human';
		$nonce  = wp_create_nonce( 'freer_reviews_human_' . $group );
		$action = 'freer_reviews_human_verify';
		ob_start();
		?>
		<div class="freer-reviews-captcha freer-reviews-captcha--click" data-group="<?php echo esc_attr( $group ); ?>" data-action="<?php echo esc_attr( $action ); ?>" style="margin:.6rem 0;">
			<button type="button" class="freer-reviews-human-btn" aria-pressed="false">
				<span class="freer-reviews-human-box" aria-hidden="true"></span>
				<span class="freer-reviews-human-label"><?php esc_html_e( "I'm not a robot", 'freer-reviews' ); ?></span>
				<span class="freer-reviews-human-spinner" aria-hidden="true"></span>
				<span class="freer-reviews-human-check" aria-hidden="true">&#10003;</span>
			</button>
			<input type="hidden" name="<?php echo esc_attr( $field ); ?>" class="freer-reviews-human-token" value="" />
			<input type="hidden" name="freer_reviews_human_nonce" value="<?php echo esc_attr( $nonce ); ?>" />
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Validate the captcha for a submitted request.
	 * @param string $group
	 * @return bool true when human-verified.
	 */
	public static function captcha_ok( $group = 'f' ) {
		if ( ! self::captcha_enabled() ) {
			return true; // feature off — not required
		}
		if ( self::turnstile_configured() ) {
			$token = isset( $_POST[ 'freer_reviews_' . $group . '_turnstile' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'freer_reviews_' . $group . '_turnstile' ] ) ) : '';
			return self::turnstile_validate( $token );
		}
		return self::click_ok( $group );
	}

	/** Server-side Turnstile siteverify. */
	public static function turnstile_validate( $response_token ) {
		if ( ! $response_token ) {
			return false;
		}
		$s = self::settings();
		$res = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
			'body'    => array(
				'secret'   => $s['turnstile_secret'],
				'response' => $response_token,
				'remoteip' => self::client_ip(),
			),
			'timeout' => 10,
		) );
		if ( is_wp_error( $res ) ) {
			return false;
		}
		$json = json_decode( wp_remote_retrieve_body( $res ), true );
		return ! empty( $json['success'] );
	}

	/**
	 * AJAX handler for the built-in click check. Issues a one-time token
	 * (stored in a transient for ~10 min) tied to the browser's IP.
	 */
	public static function ajax_human_verify() {
		$group = isset( $_POST['freer_reviews_group'] ) ? sanitize_key( $_POST['freer_reviews_group'] ) : 'f';
		if ( ! wp_verify_nonce( isset( $_POST['freer_reviews_human_nonce'] ) ? wp_unslash( $_POST['freer_reviews_human_nonce'] ) : '', 'freer_reviews_human_' . $group ) ) {
			wp_send_json_error( array( 'message' => 'bad nonce' ), 403 );
		}
		$key        = 'freer_reviews_human_' . md5( self::client_ip() . $group );
		$token      = wp_generate_password( 24, false );
		set_transient( $key, $token, 600 );
		wp_send_json_success( array( 'token' => $token ) );
	}

	/** Validate the built-in click token presented on submit. */
	private static function click_ok( $group ) {
		$token = isset( $_POST[ 'freer_reviews_' . $group . '_human' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'freer_reviews_' . $group . '_human' ] ) ) : '';
		if ( ! $token ) {
			return false;
		}
		$key   = 'freer_reviews_human_' . md5( self::client_ip() . $group );
		$stored = get_transient( $key );
		if ( ! $stored || ! hash_equals( (string) $stored, (string) $token ) ) {
			return false;
		}
		delete_transient( $key ); // one-time use
		return true;
	}

	/** Extend the SAME protection to the WordPress comment form.
	 * Call from init (front-end only). Injects the hidden anti-spam fields
	 * into the comment form and rejects spam before a comment is inserted.
	 */
	public static function comments_init() {
		if ( is_admin() ) {
			return;
		}
		// Append our hidden fields + single-click check after the comment textarea.
		add_filter( 'comment_form_field_comment', function ( $field_html ) {
			return $field_html . "\n" . self::fields( 'c' ) . "\n" . self::captcha_markup( 'c' );
		} );
		// Check before the comment is saved.
		add_filter( 'preprocess_comment', array( __CLASS__, 'guard_comment' ) );
	}

	/** @param array $commentdata */
	public static function guard_comment( $commentdata ) {
		// Trust logged-in users / trusted roles immediately.
		if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
			return $commentdata;
		}
		if ( self::is_spam( 'c' ) ) {
			wp_die( esc_html__( 'Your comment was flagged as spam. Please try again.', 'freer-reviews' ), 403 );
		}
		if ( ! self::captcha_ok( 'c' ) ) {
			wp_die( esc_html__( 'Please complete the verification before commenting.', 'freer-reviews' ), 403 );
		}
		return $commentdata;
	}

	/** Flag payloads stuffed with URLs (a classic spam signature). Only inspects
	 * free-text fields that actually exist in the request.
	 *
	 * @return bool
	 */
	private static function has_spammy_links() {
		$fields = array( 'freer_reviews_text', 'freer_reviews_name', 'comment' );
		$total  = 0;
		foreach ( $fields as $f ) {
			if ( ! isset( $_POST[ $f ] ) ) {
				continue;
			}
			$v = (string) wp_unslash( $_POST[ $f ] );
			// Count explicit link markers.
			$total += preg_match_all( '~(?:https?://|www\.|\b[a-z0-9.-]+\.(?:com|net|org|io|xyz|info|biz|online|site|shop|store|club|top|vip)(?:/[^\s]*)?)~i', $v );
			if ( $total >= 3 ) {
				return true;
			}
		}
		return false;
	}
}
