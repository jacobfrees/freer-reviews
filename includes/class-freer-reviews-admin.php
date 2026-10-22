<?php
/**
 * Admin settings page + moderation columns.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Freer_Reviews_Admin {

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
			// Protection
			'captcha_enable'    => 1,
			'turnstile_site'    => '',
			'turnstile_secret'  => '',
			// Email
			'thankyou_enable'   => 1,
			'thankyou_subject'  => __( 'Thank you, {name}!', 'freer-reviews' ),
			'thankyou_message'  => __( "Hi {name},\n\nThank you for taking a moment to share your words. Feedback is how we all get better, and yours helps the whole community. Appreciate you.\n\nWith gratitude,\n{site}", 'freer-reviews' ),
			'request_enable'    => 1,
			'request_delay_days' => 7,
			'request_subject'   => __( "Help other families find the right help", 'freer-reviews' ),
			'request_message'   => __( "Hi {name},\n\nIt's been about a week since {site} was there for you — we hope things have settled. If you have a moment, a few honest words would help other families find the right help when they need it most. It takes a minute, and it means a great deal.\n\nThank you,\n{site}", 'freer-reviews' ),
			// Structured data (schema.org) — biohazard/cleanup vertical defaults.
			'schema_business_type' => 'MedicalBusiness',
			'schema_service_type'  => __( 'unattended death cleanup, crime scene cleanup, decomposition cleanup, blood cleanup, fentanyl decontamination, hoarding remediation', 'freer-reviews' ),
			'schema_area_served'   => '',
		);
	}

	public static function register_settings() {
		register_setting( 'freer_reviews', 'freer_reviews_settings', array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize' ),
		) );
	}

	public static function admin_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$tid    = $screen ? $screen->id : '';
		// Taxonomy editors (Review Groups + Review Forms): existing on top, add-new below, tidy.
		if ( $tid === 'edit-' . Freer_Reviews_Target::GROUP_TAX || $tid === 'edit-' . Freer_Reviews_Target::TAX ) {
			wp_add_inline_style( 'list-tables', self::group_screen_css() );
		}
		// Reviews list screen: colour the rating stars + tidy the group/form badges.
		if ( $tid === 'edit-' . FREER_REVIEWS_CPT ) {
			wp_add_inline_style( 'list-tables', self::reviews_list_css() );
		}
	}

	private static function group_screen_css() {
		return '
		/* Existing groups on top, "Add New Review Group" moved below. */
		#col-container { display: flex; flex-direction: column; }
		#col-container > hr { display: none; }
		#col-left { order: 2; width: 100% !important; margin-top: 1.5rem; }
		#col-right { order: 1; width: 100% !important; }
		#col-left .form-wrap { max-width: 720px; }
		#col-left h2 { font-size: 1.15rem; margin-top: 1.5rem; }
		.widefat th, .widefat td { vertical-align: middle; }
		.column-freer_reviews_stars, .column-freer_reviews_go { width: 11%; }
		/* Group name links a touch more legible. */
		.row-title { font-weight: 600; }
		';
	}

	private static function reviews_list_css() {
		return '
		.column-rating { color: #7c5cff; font-size: .95rem; letter-spacing: 1px; white-space: nowrap; }
		.column-' . Freer_Reviews_Target::GROUP_TAX . ' a, .column-' . Freer_Reviews_Target::TAX . ' a { text-decoration: none; font-weight: 500; }
		.column-' . Freer_Reviews_Target::GROUP_TAX . ' a:hover, .column-' . Freer_Reviews_Target::TAX . ' a:hover { text-decoration: underline; }
		.column-' . Freer_Reviews_Target::GROUP_TAX . ', .column-' . Freer_Reviews_Target::TAX . ' { color: #3c3549; }
		';
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
		$out['request_delay_days'] = isset( $in['request_delay_days'] ) ? max( 0, min( 90, absint( $in['request_delay_days'] ) ) ) : 7;
		$out['request_subject'] = sanitize_text_field( isset( $in['request_subject'] ) ? $in['request_subject'] : $d['request_subject'] );
		$out['request_message'] = sanitize_textarea_field( isset( $in['request_message'] ) ? $in['request_message'] : $d['request_message'] );
		// Structured data.
		$out['schema_business_type'] = sanitize_text_field( isset( $in['schema_business_type'] ) ? $in['schema_business_type'] : $d['schema_business_type'] );
		$out['schema_service_type']  = sanitize_text_field( isset( $in['schema_service_type'] ) ? $in['schema_service_type'] : $d['schema_service_type'] );
		$out['schema_area_served']   = sanitize_textarea_field( isset( $in['schema_area_served'] ) ? $in['schema_area_served'] : $d['schema_area_served'] );
		// Protection (captcha on by default).
		$out['captcha_enable']   = ! empty( $in['captcha_enable'] ) ? 1 : 0;
		$out['turnstile_site']   = sanitize_text_field( isset( $in['turnstile_site'] ) ? $in['turnstile_site'] : '' );
		$out['turnstile_secret'] = sanitize_text_field( isset( $in['turnstile_secret'] ) ? $in['turnstile_secret'] : '' );

		Freer_Reviews_Google::maybe_schedule( $out['google_auto_sync'] );
		return $out;
	}

	public static function menu() {
		// One cohesive top-level menu: Freer Reviews
		//   - Submitted Reviews (list)
		//   - Review Groups
		//   - Review Forms
		//   - Settings
		$parent = 'freer-reviews';
		add_menu_page(
			__( 'Freer Reviews', 'freer-reviews' ),
			__( 'Freer Reviews', 'freer-reviews' ),
			'manage_options',
			$parent,
			null,
			'dashicons-star-filled',
			25
		);

		// First tab: the reviews list (edit.php?post_type=freer_reviews_review) lives under this parent.
		add_submenu_page(
			$parent,
			__( 'Submitted Reviews', 'freer-reviews' ),
			__( 'Submitted Reviews', 'freer-reviews' ),
			'manage_options',
			'edit.php?post_type=' . FREER_REVIEWS_CPT
		);

		add_submenu_page(
			$parent,
			__( 'Review Groups', 'freer-reviews' ),
			__( 'Review Groups', 'freer-reviews' ),
			'manage_options',
			'edit-tags.php?taxonomy=' . Freer_Reviews_Target::GROUP_TAX . '&post_type=' . FREER_REVIEWS_CPT
		);

		add_submenu_page(
			$parent,
			__( 'Review Forms', 'freer-reviews' ),
			__( 'Review Forms', 'freer-reviews' ),
			'manage_options',
			'edit-tags.php?taxonomy=' . Freer_Reviews_Target::TAX . '&post_type=' . FREER_REVIEWS_CPT
		);

		add_submenu_page(
			$parent,
			__( 'Freer Reviews Settings', 'freer-reviews' ),
			__( 'Settings', 'freer-reviews' ),
			'manage_options',
			'freer-reviews-settings',
			array( __CLASS__, 'settings_page' )
		);
	}

	/** Handle explicit action buttons (sync / send requests / import). */
	public static function handle_actions() {
		if ( ! isset( $_POST['freer_reviews_action'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$action = sanitize_key( $_POST['freer_reviews_action'] );

		// Many actions come from the Import tab, which uses its own nonce.
		$ok = check_admin_referer( 'freer_reviews_import_action' );
		if ( ! $ok ) {
			return;
		}

		if ( 'sync_google' === $action ) {
			$res = Freer_Reviews_Google::sync_from_google();
			wp_safe_redirect( add_query_arg( array( 'freer_reviews_notice' => $res['ok'] ? 'sync_ok' : 'sync_err', 'freer_reviews_msg' => rawurlencode( $res['message'] ) ), admin_url( 'admin.php?page=freer-reviews-settings' ) ) );
			exit;
		}

		if ( 'import_paste' === $action ) {
			$text = isset( $_POST['freer_reviews_import'] ) ? wp_unslash( $_POST['freer_reviews_import'] ) : '';
			$entries = Freer_Reviews_Google::parse_import( $text );
			$n = 0;
			foreach ( $entries as $e ) {
				$e['source'] = 'google';
				$e['status'] = 'publish';
				$id = Freer_Reviews_Post_Type::create( $e );
				if ( ! is_wp_error( $id ) ) {
					$n++;
				}
			}
			wp_safe_redirect( add_query_arg( array( 'freer_reviews_notice' => 'import_ok', 'freer_reviews_msg' => rawurlencode( sprintf( __( 'Imported %d reviews.', 'freer-reviews' ), $n ) ) ), admin_url( 'admin.php?page=freer-reviews-settings' ) ) );
			exit;
		}

		if ( 'send_requests' === $action ) {
			$text  = isset( $_POST['freer_reviews_emails'] ) ? wp_unslash( $_POST['freer_reviews_emails'] ) : '';
			$emails = array_filter( array_map( 'trim', preg_split( '/[\s,;]+/', $text ) ) );
			$n = Freer_Reviews_Email::request_review( $emails );
			wp_safe_redirect( add_query_arg( array( 'freer_reviews_notice' => 'req_ok', 'freer_reviews_msg' => rawurlencode( sprintf( __( 'Sent %d review request emails.', 'freer-reviews' ), $n ) ) ), admin_url( 'admin.php?page=freer-reviews-settings' ) ) );
			exit;
		}
	}

	public static function settings_page() {
		$s = Freer_Reviews_Email::settings();
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Freer Reviews', 'freer-reviews' ); ?></h1>
			<?php
			if ( isset( $_GET['freer_reviews_notice'] ) ) {
				$msg = isset( $_GET['freer_reviews_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['freer_reviews_msg'] ) ) : '';
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
			}
			?>
			<nav class="nav-tab-wrapper">
				<a class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>" href="admin.php?page=freer-reviews-settings"><?php esc_html_e( 'General', 'freer-reviews' ); ?></a>
				<a class="nav-tab <?php echo 'google' === $tab ? 'nav-tab-active' : ''; ?>" href="admin.php?page=freer-reviews-settings&tab=google"><?php esc_html_e( 'Google Sync', 'freer-reviews' ); ?></a>
				<a class="nav-tab <?php echo 'import' === $tab ? 'nav-tab-active' : ''; ?>" href="admin.php?page=freer-reviews-settings&tab=import"><?php esc_html_e( 'Import', 'freer-reviews' ); ?></a>
				<a class="nav-tab <?php echo 'email' === $tab ? 'nav-tab-active' : ''; ?>" href="admin.php?page=freer-reviews-settings&tab=email"><?php esc_html_e( 'Email & Requests', 'freer-reviews' ); ?></a>
			</nav>

			<form method="post" action="options.php">
				<?php settings_fields( 'freer_reviews' ); ?>

				<?php if ( 'google' === $tab ) : ?>
					<table class="form-table">
						<tr><th><?php esc_html_e( 'Google API key', 'freer-reviews' ); ?></th>
							<td><input type="text" class="regular-text" name="freer_reviews_settings[google_api_key]" value="<?php echo esc_attr( $s['google_api_key'] ); ?>" /></td></tr>
						<tr><th><?php esc_html_e( 'Place ID', 'freer-reviews' ); ?></th>
							<td><input type="text" class="regular-text" name="freer_reviews_settings[google_place_id]" value="<?php echo esc_attr( $s['google_place_id'] ); ?>" placeholder="ChIJ..." /></td></tr>
						<tr><th><?php esc_html_e( 'Auto-sync daily', 'freer-reviews' ); ?></th>
							<td><label><input type="checkbox" name="freer_reviews_settings[google_auto_sync]" value="1" <?php checked( $s['google_auto_sync'], 1 ); ?> /> <?php esc_html_e( 'Pull new Google reviews once per day', 'freer-reviews' ); ?></label></td></tr>
					</table>
					<p class="description"><?php esc_html_e( 'Live pull uses the Google Places API with your own key + place_id. If you prefer, paste reviews manually on the Import tab (works with zero credentials).', 'freer-reviews' ); ?></p>
					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'freer-reviews' ); ?></button></p>
				<?php elseif ( 'import' === $tab ) : ?>
					<p><?php esc_html_e( 'Paste JSON or CSV (name,rating,text) below. One review per line for CSV.', 'freer-reviews' ); ?></p>
					<textarea class="large-text code" name="freer_reviews_settings[import_paste]" rows="8" placeholder='{"name":"Sam","rating":5,"text":"So glad I found this place."}'></textarea>
					<p class="description"><?php esc_html_e( 'JSON: one object or an array of {name,rating,text}. CSV: name,rating,text[,google_review_id].', 'freer-reviews' ); ?></p>
					<?php wp_nonce_field( 'freer_reviews_import_action' ); ?>
					<p><button type="submit" class="button button-primary" name="freer_reviews_action" value="import_paste"><?php esc_html_e( 'Import Reviews', 'freer-reviews' ); ?></button></p>
				<?php elseif ( 'email' === $tab ) : ?>
					<h2><?php esc_html_e( 'Thank-you email (sent to reviewers)', 'freer-reviews' ); ?></h2>
					<table class="form-table">
						<tr><th><?php esc_html_e( 'Enabled', 'freer-reviews' ); ?></th><td><label><input type="checkbox" name="freer_reviews_settings[thankyou_enable]" value="1" <?php checked( $s['thankyou_enable'], 1 ); ?> /> <?php esc_html_e( 'Send thank-you after a review', 'freer-reviews' ); ?></label></td></tr>
						<tr><th><?php esc_html_e( 'Subject', 'freer-reviews' ); ?></th><td><input type="text" class="regular-text" name="freer_reviews_settings[thankyou_subject]" value="<?php echo esc_attr( $s['thankyou_subject'] ); ?>" /></td></tr>
						<tr><th><?php esc_html_e( 'Message', 'freer-reviews' ); ?></th><td><textarea class="large-text code" rows="6" name="freer_reviews_settings[thankyou_message]"><?php echo esc_textarea( $s['thankyou_message'] ); ?></textarea><p class="description">{name} {site}</p></td></tr>
					</table>
					<h2><?php esc_html_e( 'Review request (send to past customers)', 'freer-reviews' ); ?></h2>
					<table class="form-table">
						<tr><th><?php esc_html_e( 'Review page URL', 'freer-reviews' ); ?></th><td><input type="url" class="regular-text" name="freer_reviews_settings[review_page_url]" value="<?php echo esc_attr( $s['review_page_url'] ); ?>" /></td></tr>
						<tr><th><?php esc_html_e( 'Delay before request', 'freer-reviews' ); ?></th><td><input type="number" min="0" max="90" step="1" name="freer_reviews_settings[request_delay_days]" value="<?php echo esc_attr( $s['request_delay_days'] ); ?>" /> <?php esc_html_e( 'days after the thank-you', 'freer-reviews' ); ?><p class="description"><?php esc_html_e( 'Automatically sends the review request once the crisis has passed. 7 days is the sweet spot. Set 0 to disable the automatic ask.', 'freer-reviews' ); ?></p></td></tr>
						<tr><th><?php esc_html_e( 'Subject', 'freer-reviews' ); ?></th><td><input type="text" class="regular-text" name="freer_reviews_settings[request_subject]" value="<?php echo esc_attr( $s['request_subject'] ); ?>" /></td></tr>
						<tr><th><?php esc_html_e( 'Message', 'freer-reviews' ); ?></th><td><textarea class="large-text code" rows="6" name="freer_reviews_settings[request_message]"><?php echo esc_textarea( $s['request_message'] ); ?></textarea></td></tr>
					</table>
					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Email Settings', 'freer-reviews' ); ?></button></p>
					<hr />
					<h2><?php esc_html_e( 'Send requests now', 'freer-reviews' ); ?></h2>
					<p><?php esc_html_e( 'Paste email addresses (comma / newline separated).', 'freer-reviews' ); ?></p>
					<textarea class="large-text code" name="freer_reviews_settings[request_emails]" rows="4" placeholder="you@example.com, friend@example.com"></textarea>
					<?php wp_nonce_field( 'freer_reviews_import_action' ); ?>
					<p><button type="submit" class="button button-primary" name="freer_reviews_action" value="send_requests"><?php esc_html_e( 'Send Review Requests', 'freer-reviews' ); ?></button></p>
				<?php else : ?>
					<table class="form-table">
						<tr><th><?php esc_html_e( 'Default star scale', 'freer-reviews' ); ?></th>
							<td><input type="number" name="freer_reviews_settings[max_rating]" value="<?php echo esc_attr( $s['max_rating'] ); ?>" min="1" max="10" step="1" /><p class="description"><?php esc_html_e( 'How many stars reviews are rated out of by default (1 to 10). Each target can override this.', 'freer-reviews' ); ?></p></td></tr>
						<tr><th><?php esc_html_e( 'Auto-approve reviews', 'freer-reviews' ); ?></th>
							<td><label><input type="checkbox" name="freer_reviews_settings[auto_approve]" value="1" <?php checked( $s['auto_approve'], 1 ); ?> /> <?php esc_html_e( 'Publish new reviews immediately (otherwise they wait for approval)', 'freer-reviews' ); ?></label></td></tr>
						<tr><th><?php esc_html_e( 'Review page URL', 'freer-reviews' ); ?></th>
							<td><input type="url" class="regular-text" name="freer_reviews_settings[review_page_url]" value="<?php echo esc_attr( $s['review_page_url'] ); ?>" /><p class="description"><?php esc_html_e( 'Where the review form lives. Used in the [freer_reviews_request_link] CTA and request emails.', 'freer-reviews' ); ?></p></td></tr>
						<tr><th><?php esc_html_e( 'Human verification', 'freer-reviews' ); ?></th>
							<td>
								<label><input type="checkbox" name="freer_reviews_settings[captcha_enable]" value="1" <?php checked( $s['captcha_enable'], 1 ); ?> /> <?php esc_html_e( 'Require a single-click "I\'m human" check before submitting a review', 'freer-reviews' ); ?></label>
								<p class="description"><?php esc_html_e( 'On by default. If you add Cloudflare Turnstile keys below it uses the real Turnstile checkbox; otherwise it uses the built-in single-click verification. Either way it\'s a one-click, no-typing check for people.', 'freer-reviews' ); ?></p>
							</td></tr>
						<tr><th><?php esc_html_e( 'Cloudflare Turnstile (optional)', 'freer-reviews' ); ?></th>
							<td>
								<input type="text" class="regular-text" name="freer_reviews_settings[turnstile_site]" value="<?php echo esc_attr( $s['turnstile_site'] ); ?>" placeholder="0x4AAAAAA..." autocomplete="off" />
								<p class="description" style="margin:.2rem 0 .6rem;"><?php esc_html_e( 'Site key (public)', 'freer-reviews' ); ?></p>
								<input type="password" class="regular-text" name="freer_reviews_settings[turnstile_secret]" value="<?php echo esc_attr( $s['turnstile_secret'] ); ?>" placeholder="1x0000000..." autocomplete="off" />
								<p class="description" style="margin:.2rem 0 0;"><?php esc_html_e( 'Secret key — free from Cloudflare (dash.cloudflare.com → Turnstile). Leave blank to use the built-in click check.', 'freer-reviews' ); ?></p>
							</td></tr>
							<tr><th><?php esc_html_e( 'Structured data (schema.org)', 'freer-reviews' ); ?></th>
								<td>
									<p class="description" style="margin:0 0 .4rem;"><?php esc_html_e( 'Schema @type used for review rich results. Biohazard/trauma cleanup sites should use MedicalBusiness or EmergencyService, not generic LocalBusiness.', 'freer-reviews' ); ?></p>
									<input type="text" class="regular-text" name="freer_reviews_settings[schema_business_type]" value="<?php echo esc_attr( $s['schema_business_type'] ); ?>" placeholder="MedicalBusiness" />
									<p class="description" style="margin:.6rem 0 .4rem;"><?php esc_html_e( 'serviceType — comma-separated services that populate the schema:', 'freer-reviews' ); ?></p>
									<input type="text" class="large-text" name="freer_reviews_settings[schema_service_type]" value="<?php echo esc_attr( $s['schema_service_type'] ); ?>" />
									<p class="description" style="margin:.6rem 0 .4rem;"><?php esc_html_e( 'areaServed — one city/county per line (every suburb and service area, not just home base):', 'freer-reviews' ); ?></p>
									<textarea class="large-text code" rows="5" name="freer_reviews_settings[schema_area_served]" placeholder="Raleigh&#10;Durham&#10;Wake County"><?php echo esc_textarea( $s['schema_area_served'] ); ?></textarea>
								</td></tr>
							</table>
							<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'freer-reviews' ); ?></button></p>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}

	// ---- Moderation columns ----

	public static function columns( $cols ) {
		return array(
			'cb'       => $cols['cb'],
			'title'    => __( 'Reviewer', 'freer-reviews' ),
			'rating'   => __( 'Rating', 'freer-reviews' ),
			'source'   => __( 'Source', 'freer-reviews' ),
			'date'     => __( 'Date', 'freer-reviews' ),
		);
	}

	public static function column_content( $column, $post_id ) {
		if ( 'rating' === $column ) {
			$r = (int) get_post_meta( $post_id, 'freer_reviews_rating', true );
			echo str_repeat( '&#9733;', $r ) . str_repeat( '&#9734;', 5 - $r );
		} elseif ( 'source' === $column ) {
			$src = get_post_meta( $post_id, 'freer_reviews_source', true );
			echo esc_html( $src ? $src : 'site' );
		}
	}
}
