<?php
/**
 * Admin settings page + moderation columns.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FFR_Admin {

	public static function defaults() {
		return array(
			'auto_approve'      => 0,
			'max_rating'        => 5,
			'review_page_url'   => '',
			'google_api_key'    => '',
			'google_place_id'   => '',
			'google_auto_sync'  => 0,
			'import_paste'      => '',
			'request_emails'    => '',
			// Email
			'thankyou_enable'   => 1,
			'thankyou_subject'  => __( 'Thank you, {name}!', 'freeflow-reviews' ),
			'thankyou_message'  => __( "Hi {name},\n\nThank you for taking a moment to share your words. Feedback is how we all get better, and yours helps the whole community. Appreciate you.\n\nWith gratitude,\n{site}", 'freeflow-reviews' ),
			'request_enable'    => 1,
			'request_subject'   => __( "We'd love your honest feedback", 'freeflow-reviews' ),
			'request_message'   => __( "Hi {name},\n\nIf {site} added something to your world, a few honest words means a lot. It takes a minute and helps others find their way here too.\n\nThank you,\n{site}", 'freeflow-reviews' ),
		);
	}

	public static function register_settings() {
		register_setting( 'ffr_reviews', 'ffr_settings', array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize' ),
		) );
	}

	/** Store sanitizer (runs on every settings save via options.php). */
	public static function sanitize( $in ) {
		$d    = self::defaults();
		$in   = is_array( $in ) ? $in : array();
		$out  = array();
		$out['auto_approve']    = ! empty( $in['auto_approve'] ) ? 1 : 0;
		$out['max_rating']      = isset( $in['max_rating'] ) ? max( 1, min( 10, absint( $in['max_rating'] ) ) ) : 5;
		$out['review_page_url'] = esc_url_raw( isset( $in['review_page_url'] ) ? $in['review_page_url'] : '' );
		$out['google_api_key']  = sanitize_text_field( isset( $in['google_api_key'] ) ? $in['google_api_key'] : '' );
		$out['google_place_id'] = sanitize_text_field( isset( $in['google_place_id'] ) ? $in['google_place_id'] : '' );
		$out['google_auto_sync'] = ! empty( $in['google_auto_sync'] ) ? 1 : 0;
		$out['thankyou_enable'] = ! empty( $in['thankyou_enable'] ) ? 1 : 0;
		$out['thankyou_subject'] = sanitize_text_field( isset( $in['thankyou_subject'] ) ? $in['thankyou_subject'] : $d['thankyou_subject'] );
		$out['thankyou_message'] = sanitize_textarea_field( isset( $in['thankyou_message'] ) ? $in['thankyou_message'] : $d['thankyou_message'] );
		$out['request_enable']  = ! empty( $in['request_enable'] ) ? 1 : 0;
		$out['request_subject'] = sanitize_text_field( isset( $in['request_subject'] ) ? $in['request_subject'] : $d['request_subject'] );
		$out['request_message'] = sanitize_textarea_field( isset( $in['request_message'] ) ? $in['request_message'] : $d['request_message'] );

		FFR_Google::maybe_schedule( $out['google_auto_sync'] );
		return $out;
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . FFR_CPT,
			__( 'FreeFlow Reviews Settings', 'freeflow-reviews' ),
			__( 'Settings', 'freeflow-reviews' ),
			'manage_options',
			'ffr-settings',
			array( __CLASS__, 'settings_page' )
		);
	}

	/** Handle explicit action buttons (sync / send requests / import). */
	public static function handle_actions() {
		if ( ! isset( $_POST['ffr_action'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$action = sanitize_key( $_POST['ffr_action'] );

		// Many actions come from the Import tab, which uses its own nonce.
		$ok = check_admin_referer( 'ffr_import_action' );
		if ( ! $ok ) {
			return;
		}

		if ( 'sync_google' === $action ) {
			$res = FFR_Google::sync_from_google();
			wp_safe_redirect( add_query_arg( array( 'ffr_notice' => $res['ok'] ? 'sync_ok' : 'sync_err', 'ffr_msg' => rawurlencode( $res['message'] ) ), admin_url( 'edit.php?post_type=' . FFR_CPT . '&page=ffr-settings' ) ) );
			exit;
		}

		if ( 'import_paste' === $action ) {
			$text = isset( $_POST['ffr_import'] ) ? wp_unslash( $_POST['ffr_import'] ) : '';
			$entries = FFR_Google::parse_import( $text );
			$n = 0;
			foreach ( $entries as $e ) {
				$e['source'] = 'google';
				$e['status'] = 'publish';
				$id = FFR_Post_Type::create( $e );
				if ( ! is_wp_error( $id ) ) {
					$n++;
				}
			}
			wp_safe_redirect( add_query_arg( array( 'ffr_notice' => 'import_ok', 'ffr_msg' => rawurlencode( sprintf( __( 'Imported %d reviews.', 'freeflow-reviews' ), $n ) ) ), admin_url( 'edit.php?post_type=' . FFR_CPT . '&page=ffr-settings' ) ) );
			exit;
		}

		if ( 'send_requests' === $action ) {
			$text  = isset( $_POST['ffr_emails'] ) ? wp_unslash( $_POST['ffr_emails'] ) : '';
			$emails = array_filter( array_map( 'trim', preg_split( '/[\s,;]+/', $text ) ) );
			$n = FFR_Email::request_review( $emails );
			wp_safe_redirect( add_query_arg( array( 'ffr_notice' => 'req_ok', 'ffr_msg' => rawurlencode( sprintf( __( 'Sent %d review request emails.', 'freeflow-reviews' ), $n ) ) ), admin_url( 'edit.php?post_type=' . FFR_CPT . '&page=ffr-settings' ) ) );
			exit;
		}
	}

	public static function settings_page() {
		$s = FFR_Email::settings();
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'FreeFlow Reviews', 'freeflow-reviews' ); ?></h1>
			<?php
			if ( isset( $_GET['ffr_notice'] ) ) {
				$msg = isset( $_GET['ffr_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['ffr_msg'] ) ) : '';
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
			}
			?>
			<nav class="nav-tab-wrapper">
				<a class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>" href="edit.php?post_type=<?php echo FFR_CPT; ?>&page=ffr-settings"><?php esc_html_e( 'General', 'freeflow-reviews' ); ?></a>
				<a class="nav-tab <?php echo 'google' === $tab ? 'nav-tab-active' : ''; ?>" href="edit.php?post_type=<?php echo FFR_CPT; ?>&page=ffr-settings&tab=google"><?php esc_html_e( 'Google Sync', 'freeflow-reviews' ); ?></a>
				<a class="nav-tab <?php echo 'import' === $tab ? 'nav-tab-active' : ''; ?>" href="edit.php?post_type=<?php echo FFR_CPT; ?>&page=ffr-settings&tab=import"><?php esc_html_e( 'Import', 'freeflow-reviews' ); ?></a>
				<a class="nav-tab <?php echo 'email' === $tab ? 'nav-tab-active' : ''; ?>" href="edit.php?post_type=<?php echo FFR_CPT; ?>&page=ffr-settings&tab=email"><?php esc_html_e( 'Email & Requests', 'freeflow-reviews' ); ?></a>
			</nav>

			<form method="post" action="options.php">
				<?php settings_fields( 'ffr_reviews' ); ?>

				<?php if ( 'google' === $tab ) : ?>
					<table class="form-table">
						<tr><th><?php esc_html_e( 'Google API key', 'freeflow-reviews' ); ?></th>
							<td><input type="text" class="regular-text" name="ffr_settings[google_api_key]" value="<?php echo esc_attr( $s['google_api_key'] ); ?>" /></td></tr>
						<tr><th><?php esc_html_e( 'Place ID', 'freeflow-reviews' ); ?></th>
							<td><input type="text" class="regular-text" name="ffr_settings[google_place_id]" value="<?php echo esc_attr( $s['google_place_id'] ); ?>" placeholder="ChIJ..." /></td></tr>
						<tr><th><?php esc_html_e( 'Auto-sync daily', 'freeflow-reviews' ); ?></th>
							<td><label><input type="checkbox" name="ffr_settings[google_auto_sync]" value="1" <?php checked( $s['google_auto_sync'], 1 ); ?> /> <?php esc_html_e( 'Pull new Google reviews once per day', 'freeflow-reviews' ); ?></label></td></tr>
					</table>
					<p class="description"><?php esc_html_e( 'Live pull uses the Google Places API with your own key + place_id. If you prefer, paste reviews manually on the Import tab (works with zero credentials).', 'freeflow-reviews' ); ?></p>
					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'freeflow-reviews' ); ?></button></p>
				<?php elseif ( 'import' === $tab ) : ?>
					<p><?php esc_html_e( 'Paste JSON or CSV (name,rating,text) below. One review per line for CSV.', 'freeflow-reviews' ); ?></p>
					<textarea class="large-text code" name="ffr_settings[import_paste]" rows="8" placeholder='{"name":"Sam","rating":5,"text":"So glad I found this place."}'></textarea>
					<p class="description"><?php esc_html_e( 'JSON: one object or an array of {name,rating,text}. CSV: name,rating,text[,google_review_id].', 'freeflow-reviews' ); ?></p>
					<?php wp_nonce_field( 'ffr_import_action' ); ?>
					<p><button type="submit" class="button button-primary" name="ffr_action" value="import_paste"><?php esc_html_e( 'Import Reviews', 'freeflow-reviews' ); ?></button></p>
				<?php elseif ( 'email' === $tab ) : ?>
					<h2><?php esc_html_e( 'Thank-you email (sent to reviewers)', 'freeflow-reviews' ); ?></h2>
					<table class="form-table">
						<tr><th><?php esc_html_e( 'Enabled', 'freeflow-reviews' ); ?></th><td><label><input type="checkbox" name="ffr_settings[thankyou_enable]" value="1" <?php checked( $s['thankyou_enable'], 1 ); ?> /> <?php esc_html_e( 'Send thank-you after a review', 'freeflow-reviews' ); ?></label></td></tr>
						<tr><th><?php esc_html_e( 'Subject', 'freeflow-reviews' ); ?></th><td><input type="text" class="regular-text" name="ffr_settings[thankyou_subject]" value="<?php echo esc_attr( $s['thankyou_subject'] ); ?>" /></td></tr>
						<tr><th><?php esc_html_e( 'Message', 'freeflow-reviews' ); ?></th><td><textarea class="large-text code" rows="6" name="ffr_settings[thankyou_message]"><?php echo esc_textarea( $s['thankyou_message'] ); ?></textarea><p class="description">{name} {site}</p></td></tr>
					</table>
					<h2><?php esc_html_e( 'Review request (send to past customers)', 'freeflow-reviews' ); ?></h2>
					<table class="form-table">
						<tr><th><?php esc_html_e( 'Review page URL', 'freeflow-reviews' ); ?></th><td><input type="url" class="regular-text" name="ffr_settings[review_page_url]" value="<?php echo esc_attr( $s['review_page_url'] ); ?>" /></td></tr>
						<tr><th><?php esc_html_e( 'Subject', 'freeflow-reviews' ); ?></th><td><input type="text" class="regular-text" name="ffr_settings[request_subject]" value="<?php echo esc_attr( $s['request_subject'] ); ?>" /></td></tr>
						<tr><th><?php esc_html_e( 'Message', 'freeflow-reviews' ); ?></th><td><textarea class="large-text code" rows="6" name="ffr_settings[request_message]"><?php echo esc_textarea( $s['request_message'] ); ?></textarea></td></tr>
					</table>
					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Email Settings', 'freeflow-reviews' ); ?></button></p>
					<hr />
					<h2><?php esc_html_e( 'Send requests now', 'freeflow-reviews' ); ?></h2>
					<p><?php esc_html_e( 'Paste email addresses (comma / newline separated).', 'freeflow-reviews' ); ?></p>
					<textarea class="large-text code" name="ffr_settings[request_emails]" rows="4" placeholder="you@example.com, friend@example.com"></textarea>
					<?php wp_nonce_field( 'ffr_import_action' ); ?>
					<p><button type="submit" class="button button-primary" name="ffr_action" value="send_requests"><?php esc_html_e( 'Send Review Requests', 'freeflow-reviews' ); ?></button></p>
				<?php else : ?>
					<table class="form-table">
						<tr><th><?php esc_html_e( 'Default star scale', 'freeflow-reviews' ); ?></th>
							<td><input type="number" name="ffr_settings[max_rating]" value="<?php echo esc_attr( $s['max_rating'] ); ?>" min="1" max="10" step="1" /><p class="description"><?php esc_html_e( 'How many stars reviews are rated out of by default (1 to 10). Each target can override this.', 'freeflow-reviews' ); ?></p></td></tr>
						<tr><th><?php esc_html_e( 'Auto-approve reviews', 'freeflow-reviews' ); ?></th>
							<td><label><input type="checkbox" name="ffr_settings[auto_approve]" value="1" <?php checked( $s['auto_approve'], 1 ); ?> /> <?php esc_html_e( 'Publish new reviews immediately (otherwise they wait for approval)', 'freeflow-reviews' ); ?></label></td></tr>
						<tr><th><?php esc_html_e( 'Review page URL', 'freeflow-reviews' ); ?></th>
							<td><input type="url" class="regular-text" name="ffr_settings[review_page_url]" value="<?php echo esc_attr( $s['review_page_url'] ); ?>" /><p class="description"><?php esc_html_e( 'Where the review form lives. Used in the [freeflow_request_link] CTA and request emails.', 'freeflow-reviews' ); ?></p></td></tr>
					</table>
					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'freeflow-reviews' ); ?></button></p>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}

	// ---- Moderation columns ----

	public static function columns( $cols ) {
		return array(
			'cb'       => $cols['cb'],
			'title'    => __( 'Reviewer', 'freeflow-reviews' ),
			'rating'   => __( 'Rating', 'freeflow-reviews' ),
			'source'   => __( 'Source', 'freeflow-reviews' ),
			'date'     => __( 'Date', 'freeflow-reviews' ),
		);
	}

	public static function column_content( $column, $post_id ) {
		if ( 'rating' === $column ) {
			$r = (int) get_post_meta( $post_id, 'ffr_rating', true );
			echo str_repeat( '&#9733;', $r ) . str_repeat( '&#9734;', 5 - $r );
		} elseif ( 'source' === $column ) {
			$src = get_post_meta( $post_id, 'ffr_source', true );
			echo esc_html( $src ? $src : 'site' );
		}
	}
}
