<?php
/**
 * Email flow: thank-you + review request.
 *
 * Built on pluggable wp_mail() so it works out of the box, but every send
 * fires two hooks so any mailing plugin (FluentCRM, Mailchimp, MailPoet, ...)
 * can intercept and route through its own channels:
 *
 *   apply_filters('freer_reviews_send_review_email', $payload)  -> return array('skip'=>true) to take over
 *   do_action('freer_reviews_thankyou_email', $data)            -> fire-and-forget signal for email plugins
 *   do_action('freer_reviews_review_request_email', $data)      -> same, for request sends
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Freer_Reviews_Email {

	public static function init() {
		// Review request sender exposed as a shortcode + admin action.
		add_shortcode( 'freer_reviews_request_link', array( __CLASS__, 'link_shortcode' ) );
		// Timed review request: a kind follow-up N days after the job, once the crisis passes.
		add_action( 'freer_reviews_delayed_request', array( __CLASS__, 'run_delayed_request' ) );
		// Cancel any pending request if the review is deleted.
		add_action( 'delete_post', array( __CLASS__, 'cancel_delayed_request' ), 10, 1 );
	}

	/** Settings accessor with sensible defaults. */
	public static function settings() {
		return wp_parse_args( get_option( 'freer_reviews_settings', array() ), Freer_Reviews_Admin::defaults() );
	}

	/** Send an email, respecting the pluggable-hook layer. Returns true if wp_mail handled it. */
	public static function send( $to, $subject, $message, $extra = array() ) {
		$payload = array_merge( array(
			'to'      => $to,
			'subject' => $subject,
			'message' => $message,
			'headers' => array( 'Content-Type: text/html; charset=UTF-8' ),
		), $extra );

		/**
		 * Filter the outgoing review email.
		 * Return array('skip' => true) after dispatching through your own email
		 * plugin to bypass wp_mail entirely.
		 */
		$payload = apply_filters( 'freer_reviews_send_review_email', $payload );

		if ( ! empty( $payload['skip'] ) ) {
			return true;
		}

		return wp_mail( $payload['to'], $payload['subject'], $payload['message'], $payload['headers'] );
	}

	/** Replace {name} / {site} placeholders. */
	private static function fill( $tpl, $name ) {
		$find   = array( '{name}', '{site}', '{rating}' );
		$rep    = array( $name, get_bloginfo( 'name' ), '5' );
		return str_replace( $find, $rep, $tpl );
	}

	/** Thank-you email sent to a reviewer after submission. */
	public static function thank_you( $post_id ) {
		$s = self::settings();
		if ( empty( $s['thankyou_enable'] ) && ! has_filter( 'freer_reviews_thankyou_email' ) ) {
			return;
		}

		$email = get_post_meta( $post_id, 'freer_reviews_reviewer_email', true );
		$name  = get_post_meta( $post_id, 'freer_reviews_reviewer_name', true );
		if ( ! $email ) {
			return;
		}

		$subject = self::fill( $s['thankyou_subject'], $name );
		$message = self::fill( $s['thankyou_message'], $name );

		$data = array(
			'to'      => $email,
			'name'    => $name,
			'subject' => $subject,
			'message' => $message,
			'review_id' => $post_id,
		);

		// Signal for email plugins.
		do_action( 'freer_reviews_thankyou_email', $data );

		self::send( $email, $subject, $message );

		// Schedule the timed review request — a week out, once the crisis has passed.
		self::schedule_delayed_request( $post_id, $email, $name );
	}

	/**
	 * Schedule the single delayed review-request event for a submitted review.
	 * Honours request_enable and request_delay_days (0 disables the ask).
	 */
	public static function schedule_delayed_request( $post_id, $email, $name = '' ) {
		$s = self::settings();
		if ( empty( $s['request_enable'] ) ) {
			return;
		}
		$days = max( 0, (int) ( isset( $s['request_delay_days'] ) ? $s['request_delay_days'] : 7 ) );
		if ( $days < 1 ) {
			return;
		}
		// Clear any existing pending event for this review first.
		self::cancel_delayed_request( $post_id );
		wp_schedule_single_event( time() + $days * DAY_IN_SECONDS, 'freer_reviews_delayed_request', array(
			(int) $post_id,
			$email,
			$name,
		) );
	}

	/** Remove a pending delayed-request event for a review (also used on delete). */
	public static function cancel_delayed_request( $post_id ) {
		// wp_next_scheduled matches exact args; sweep all matching events by args signature instead.
		$cron = _get_cron_array();
		foreach ( $cron as $ts => $hooks ) {
			if ( ! isset( $hooks['freer_reviews_delayed_request'] ) ) {
				continue;
			}
			foreach ( $hooks['freer_reviews_delayed_request'] as $sig => $event ) {
				if ( isset( $event['args'][0] ) && (int) $event['args'][0] === (int) $post_id ) {
					wp_unschedule_event( $ts, 'freer_reviews_delayed_request', $event['args'] );
				}
			}
		}
	}

	/** Cron handler: send the timed review request for one submitted review. */
	public static function run_delayed_request( $post_id, $email = '', $name = '' ) {
		$s = self::settings();
		if ( empty( $s['request_enable'] ) ) {
			return;
		}
		// Re-read from the review post (authoritative) if the args are thin.
		if ( ! $post_id ) {
			return;
		}
		$email = $email ? $email : get_post_meta( $post_id, 'freer_reviews_reviewer_email', true );
		$name  = $name ? $name : get_post_meta( $post_id, 'freer_reviews_reviewer_name', true );
		if ( ! is_email( $email ) ) {
			return;
		}
		// Reviewer already left more than one review with this email — don't nag.
		$existing = new WP_Query( array(
			'post_type'      => FREER_REVIEWS_CPT,
			'post_status'    => 'any',
			'posts_per_page' => 2,
			'fields'         => 'ids',
			'meta_key'       => 'freer_reviews_reviewer_email',
			'meta_value'     => $email,
		) );
		if ( $existing->found_posts > 1 ) {
			return;
		}
		self::request_one( $email, $name );
	}

	/** Send one review request to a single address. Returns true if sent. */
	public static function request_one( $email, $name = '' ) {
		$s   = self::settings();
		$url = ! empty( $s['review_page_url'] ) ? $s['review_page_url'] : home_url( '/' );
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return false;
		}
		$subject = self::fill( $s['request_subject'], $name );
		$message = self::fill( $s['request_message'], $name );
		$message .= '<p><a href="' . esc_url( $url ) . '" style="display:inline-block;background:#7c5cff;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;">' . esc_html__( 'Leave a review', 'freer-reviews' ) . '</a></p>';

		$data = array( 'to' => $email, 'subject' => $subject, 'message' => $message, 'review_url' => $url );
		do_action( 'freer_reviews_review_request_email', $data );

		return self::send( $email, $subject, $message );
	}

	/** Send a review request to a set of addresses. */
	public static function request_review( $emails ) {
		$sent = 0;
		foreach ( (array) $emails as $email ) {
			if ( self::request_one( $email ) ) {
				$sent++;
			}
		}
		return $sent;
	}

	/** [freer_reviews_request_link] returns the configured review URL (for CTAs). */
	public static function link_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'text' => __( 'Leave a review', 'freer-reviews' ) ), $atts, 'freer_reviews_request_link' );
		$s   = self::settings();
		$url = ! empty( $s['review_page_url'] ) ? $s['review_page_url'] : home_url( '/' );
		return '<a class="freer-reviews-cta" href="' . esc_url( $url ) . '">' . esc_html( $atts['text'] ) . '</a>';
	}
}
