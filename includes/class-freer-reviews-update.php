<?php
/**
 * Freer Reviews self-update via the Freer Plugin Hub.
 * Pings https://jacobfrees.com/?ffhub_upd=freer-reviews and, if the hub
 * has a newer version, injects the update into the WP update transient so the
 * plugin updates itself from this site (no WordPress.org required).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Freer_Reviews_Update {

	// Point at the Freer Plugin Hub on Jacob Frees' site.
	const HUB  = 'https://jacobfrees.com';
	const SLUG = 'freer-reviews';
	const FILE = 'freer-reviews/freer-reviews.php';

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check' ) );
	}

	public static function check( $transient ) {
		if ( empty( $transient->checked ) || ! isset( $transient->checked[ self::FILE ] ) ) {
			return $transient;
		}

		// Query the hub's REST update channel (cache-proof).
		$url  = trailingslashit( self::HUB ) . 'wp-json/freer_hub/v1/update/' . self::SLUG . '/';
		$resp = wp_remote_get( $url, array( 'timeout' => 8 ) );
		if ( is_wp_error( $resp ) ) {
			return $transient;
		}

		$info = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $info['version'] ) ) {
			return $transient;
		}

		if ( version_compare( $info['version'], $transient->checked[ self::FILE ], '>' ) ) {
			$o              = new stdClass();
			$o->slug        = self::SLUG;
			$o->plugin      = self::FILE;
			$o->new_version = $info['version'];
			$o->package     = isset( $info['package'] ) ? $info['package'] : '';
			$o->url         = self::HUB . '/';
			if ( isset( $info['tested'] ) )   { $o->tested   = $info['tested']; }
			if ( isset( $info['requires'] ) ) { $o->requires = $info['requires']; }
			$transient->response[ self::FILE ] = $o; // phpcs:ignore
		}

		return $transient;
	}
}
