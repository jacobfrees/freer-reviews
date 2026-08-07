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
	}

	/** Send a review request to a set of addresses. */
	public static function request_review( $emails ) {
		$s   = self::settings();
		$url = ! empty( $s['review_page_url'] ) ? $s['review_page_url'] : home_url( '/' );
		$sent = 0;

		foreach ( (array) $emails as $email ) {
			$email = sanitize_email( $email );
			if ( ! is_email( $email ) ) {
				continue;
			}
			$subject = self::fill( $s['request_subject'], '' );
			$message = self::fill( $s['request_message'], '' );
			$message .= '<p><a href="' . esc_url( $url ) . '" style="display:inline-block;background:#7c5cff;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;">' . esc_html__( 'Leave a review', 'freer-reviews' ) . '</a></p>';

			$data = array( 'to' => $email, 'subject' => $subject, 'message' => $message, 'review_url' => $url );
			do_action( 'freer_reviews_review_request_email', $data );

			self::send( $email, $subject, $message );
			$sent++;
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
