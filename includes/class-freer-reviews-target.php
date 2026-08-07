<?php
/**
 * Review Targets: the taxonomy that lets one site collect reviews for many
 * different things (products, services, posts, whatever) and manage them
 * separately, each with its own star scale (1..10) and auto-approve rule.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Freer_Reviews_Target {

	const TAX       = 'freer_reviews_target';
	const GROUP_TAX = 'freer_reviews_group';

	public static function register() {
		// Parent taxonomy: Review Groups (collections). Forms belong to one group.
		register_taxonomy( self::GROUP_TAX, FREER_REVIEWS_CPT, array(
			'labels'            => array(
				'name'          => __( 'Review Groups', 'freer-reviews' ),
				'singular_name' => __( 'Review Group', 'freer-reviews' ),
				'search_items'  => __( 'Search Review Groups', 'freer-reviews' ),
				'all_items'     => __( 'All Review Groups', 'freer-reviews' ),
				'edit_item'     => __( 'Edit Review Group', 'freer-reviews' ),
				'add_new_item'  => __( 'Add New Review Group', 'freer-reviews' ),
				'new_item_name' => __( 'New Review Group Name', 'freer-reviews' ),
				'not_found'     => __( 'No review groups found.', 'freer-reviews' ),
			),
			'public'            => false,
			'show_ui'           => true,
			'show_in_menu'      => false, // submenu under "Freer Reviews"
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'hierarchical'      => true,
			'query_var'         => false,
			'rewrite'           => false,
		) );

		// Review Forms: the thing being reviewed (email + star scale + per-form fields).
		register_taxonomy( self::TAX, FREER_REVIEWS_CPT, array(
			'labels'            => array(
				'name'          => __( 'Review Forms', 'freer-reviews' ),
				'singular_name' => __( 'Review Form', 'freer-reviews' ),
				'search_items'  => __( 'Search Review Forms', 'freer-reviews' ),
				'all_items'     => __( 'All Review Forms', 'freer-reviews' ),
				'edit_item'     => __( 'Edit Review Form', 'freer-reviews' ),
				'add_new_item'  => __( 'Add New Review Form', 'freer-reviews' ),
				'new_item_name' => __( 'New Review Form Name', 'freer-reviews' ),
				'not_found'     => __( 'No review forms found.', 'freer-reviews' ),
				'back_to_items' => __( '&larr; Back to Review Forms', 'freer-reviews' ),
			),
			'public'            => false,
			'show_ui'           => true,
			'show_in_menu'      => false, // submenu under "Freer Reviews"
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'hierarchical'      => false,
			'query_var'         => false,
			'rewrite'           => false,
		) );

		$meta = array(
			'freer_reviews_max_rating'       => array( 'type' => 'integer' ),
			'freer_reviews_auto_approve'     => array( 'type' => 'integer' ),
			'freer_reviews_form_title'       => array( 'type' => 'string' ),
			'freer_reviews_thankyou_message' => array( 'type' => 'string' ),
			'freer_reviews_form_config'      => array( 'type' => 'array' ),
			'freer_reviews_group'            => array( 'type' => 'integer' ), // parent Review Group this form belongs to
		);
		foreach ( $meta as $key => $args ) {
			register_term_meta( self::TAX, $key, array_merge(
				array( 'show_in_rest' => true, 'auth_callback' => '__return_true' ),
				$args
			) );
		}

		// Per-target settings on the term admin screens.
		add_action( self::TAX . '_add_form_fields', array( __CLASS__, 'term_fields_add' ) );
		add_action( self::TAX . '_edit_form_fields', array( __CLASS__, 'term_fields_edit' ), 10, 2 );
		add_action( 'created_' . self::TAX, array( __CLASS__, 'term_save' ), 10, 2 );
		add_action( 'edited_' . self::TAX, array( __CLASS__, 'term_save' ), 10, 2 );
		add_filter( 'manage_edit-' . self::TAX . '_columns', array( __CLASS__, 'term_columns' ) );
		add_filter( 'manage_' . self::TAX . '_custom_column', array( __CLASS__, 'term_column_value' ), 10, 3 );
	}

	/** Term admin columns: stars + approval. */
	public static function term_columns( $cols ) {
		$cols['freer_reviews_stars'] = __( 'Stars', 'freer-reviews' );
		$cols['freer_reviews_go']    = __( 'Posting', 'freer-reviews' );
		return $cols;
	}

	public static function term_column_value( $out, $col, $term_id ) {
		if ( 'freer_reviews_stars' === $col ) {
			$m = get_term_meta( $term_id, 'freer_reviews_max_rating', true );
			return $m ? esc_html( $m . ' / ' . $m ) : esc_html( self::global_max() . ' / ' . self::global_max() );
		}
		if ( 'freer_reviews_go' === $col ) {
			$aa = (int) get_term_meta( $term_id, 'freer_reviews_auto_approve', true );
			return $aa ? __( 'Auto-post', 'freer-reviews' ) : __( 'Moderated', 'freer-reviews' );
		}
		return $out;
	}

	public static function term_fields_add() {
		self::term_field_row( null );
	}

	public static function term_fields_edit( $term ) {
		self::term_field_row( $term );
	}

	private static function term_field_row( $term = null ) {
		$m  = ( $term && ! is_wp_error( $term ) ) ? (int) get_term_meta( $term->term_id, 'freer_reviews_max_rating', true ) : 0;
		$aa = ( $term && ! is_wp_error( $term ) ) ? (int) get_term_meta( $term->term_id, 'freer_reviews_auto_approve', true ) : 0;
		$ft = ( $term && ! is_wp_error( $term ) ) ? get_term_meta( $term->term_id, 'freer_reviews_form_title', true ) : '';
		$cfg = ( $term && ! is_wp_error( $term ) ) ? (array) get_term_meta( $term->term_id, 'freer_reviews_form_config', true ) : array();
		$d   = Freer_Reviews_Form::defaults();
		if ( empty( $cfg ) ) {
			$cfg = $d;
		} else {
			$cfg = array_merge( $d, array_intersect_key( $cfg, $d ) );
		}
		?>
		<tr class="form-field">
			<th scope="row"><label for="freer_reviews_group"><?php esc_html_e( 'Review Group', 'freer-reviews' ); ?></label></th>
			<td>
				<?php
				$group_id = ( $term && ! is_wp_error( $term ) ) ? (int) get_term_meta( $term->term_id, 'freer_reviews_group', true ) : 0;
				$groups   = get_terms( array( 'taxonomy' => self::GROUP_TAX, 'hide_empty' => false ) );
				?>
				<select id="freer_reviews_group" name="freer_reviews_group" style="min-width:16rem;">
					<option value="0"><?php esc_html_e( '— No group —', 'freer-reviews' ); ?></option>
					<?php foreach ( $groups as $g ) : ?>
						<option value="<?php echo esc_attr( $g->term_id ); ?>" <?php selected( $group_id, $g->term_id ); ?>><?php echo esc_html( $g->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'The Review Group (collection) this form belongs to. Reviews land in this group automatically.', 'freer-reviews' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="freer_reviews_max_rating"><?php esc_html_e( 'Star scale (max rating)', 'freer-reviews' ); ?></label></th>
			<td>
				<input type="number" id="freer_reviews_max_rating" name="freer_reviews_max_rating" value="<?php echo $m ? (int) $m : 5; ?>" min="1" max="10" step="1" />
				<p class="description"><?php esc_html_e( 'How many stars this thing is rated out of. Up to 10.', 'freer-reviews' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Posting', 'freer-reviews' ); ?></th>
			<td>
				<label><input type="checkbox" name="freer_reviews_auto_approve" value="1" <?php checked( $aa, 1 ); ?> /> <?php esc_html_e( 'Auto-publish reviews for this target (overrides global)', 'freer-reviews' ); ?></label>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="freer_reviews_form_title"><?php esc_html_e( 'Form title', 'freer-reviews' ); ?></label></th>
			<td>
				<input type="text" id="freer_reviews_form_title" name="freer_reviews_form_title" value="<?php echo esc_attr( $ft ); ?>" />
				<p class="description"><?php esc_html_e( 'Optional headline shown above this target\'s review form.', 'freer-reviews' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Fields', 'freer-reviews' ); ?></th>
			<td>
				<p class="description"><?php esc_html_e( 'Which fields to show and whether each is required. Leave a field hidden to skip it entirely.', 'freer-reviews' ); ?></p>
				<?php foreach ( Freer_Reviews_Form::FIELDS as $f ) :
					$shown  = ! empty( $cfg[ 'show_' . $f ] );
					$req    = ! empty( $cfg[ 'require_' . $f ] );
					$labels = array(
						'rating' => __( 'Rating (stars)', 'freer-reviews' ),
						'name'   => __( 'Name', 'freer-reviews' ),
						'email'  => __( 'Email', 'freer-reviews' ),
						'text'   => __( 'Text', 'freer-reviews' ),
					);
				?>
					<label style="display:inline-block;margin-right:1.2rem;">
						<input type="checkbox" name="freer_reviews_config[show_<?php echo $f; ?>]" value="1" <?php checked( $shown, 1 ); ?> /> <?php echo esc_html( $labels[ $f ] ); ?>
					</label>
					<label style="display:inline-block;margin-right:1.5rem;color:#646970;">
						<input type="checkbox" name="freer_reviews_config[require_<?php echo $f; ?>]" value="1" <?php checked( $req, 1 ); ?> <?php disabled( ! $shown ); ?> /> <?php esc_html_e( 'Required', 'freer-reviews' ); ?>
					</label>
					<?php if ( 'name' === $f || 'email' === $f || 'text' === $f ) : ?>
						<input type="text" name="freer_reviews_config[label_<?php echo $f; ?>]" value="<?php echo esc_attr( $cfg[ 'label_' . $f ] ); ?>" placeholder="Label" style="width:11rem;margin-right:.5rem;" />
						<input type="text" name="freer_reviews_config[placeholder_<?php echo $f; ?>]" value="<?php echo esc_attr( $cfg[ 'placeholder_' . $f ] ); ?>" placeholder="Placeholder" style="width:12rem;" />
					<?php endif; ?>
					<br />
				<?php endforeach; ?>
				<p style="margin-top:.5rem;">
					<?php esc_html_e( 'Rating label', 'freer-reviews' ); ?>:
					<input type="text" name="freer_reviews_config[label_rating]" value="<?php echo esc_attr( $cfg['label_rating'] ); ?>" style="width:14rem;margin-right:1rem;" />
					<?php esc_html_e( 'Submit button', 'freer-reviews' ); ?>:
					<input type="text" name="freer_reviews_config[submit]" value="<?php echo esc_attr( $cfg['submit'] ); ?>" style="width:12rem;margin-right:1rem;" />
					<?php esc_html_e( 'Text min length', 'freer-reviews' ); ?>:
					<input type="number" name="freer_reviews_config[min]" value="<?php echo esc_attr( (int) $cfg['min'] ); ?>" min="0" max="1000" style="width:6rem;" />
				</p>
			</td>
		</tr>
		<?php
	}

	public static function term_save( $term_id ) {
		if ( isset( $_POST['freer_reviews_max_rating'] ) ) {
			update_term_meta( $term_id, 'freer_reviews_max_rating', max( 1, min( 10, absint( $_POST['freer_reviews_max_rating'] ) ) ) );
		}
		update_term_meta( $term_id, 'freer_reviews_auto_approve', ! empty( $_POST['freer_reviews_auto_approve'] ) ? 1 : 0 );
		if ( isset( $_POST['freer_reviews_form_title'] ) ) {
			update_term_meta( $term_id, 'freer_reviews_form_title', sanitize_text_field( wp_unslash( $_POST['freer_reviews_form_title'] ) ) );
		}
		if ( isset( $_POST['freer_reviews_group'] ) ) {
			$gid = absint( $_POST['freer_reviews_group'] );
			// Store as term-meta AND keep the form assigned inside that group on reviews.
			if ( $gid && term_exists( $gid, self::GROUP_TAX ) ) {
				update_term_meta( $term_id, 'freer_reviews_group', $gid );
			} else {
				delete_term_meta( $term_id, 'freer_reviews_group' );
			}
		}

		if ( isset( $_POST['freer_reviews_config'] ) && is_array( $_POST['freer_reviews_config'] ) ) {
			$d     = Freer_Reviews_Form::defaults();
			$cfg   = array();
			$in    = wp_unslash( $_POST['freer_reviews_config'] );

			foreach ( Freer_Reviews_Form::FIELDS as $f ) {
				$cfg[ 'show_' . $f ]     = ! empty( $in[ 'show_' . $f ] ) ? 1 : 0;
				// A field can only be required if it is shown.
				$cfg[ 'require_' . $f ]  = ( $cfg[ 'show_' . $f ] && ! empty( $in[ 'require_' . $f ] ) ) ? 1 : 0;
				$cfg[ 'label_' . $f ]    = isset( $in[ 'label_' . $f ] ) ? sanitize_text_field( $in[ 'label_' . $f ] ) : $d[ 'label_' . $f ];
				if ( isset( $in[ 'placeholder_' . $f ] ) ) {
					$cfg[ 'placeholder_' . $f ] = sanitize_text_field( $in[ 'placeholder_' . $f ] );
				} else {
					$cfg[ 'placeholder_' . $f ] = '';
				}
			}
			$cfg['submit'] = isset( $in['submit'] ) && $in['submit'] !== '' ? sanitize_text_field( $in['submit'] ) : $d['submit'];
			$cfg['min']    = isset( $in['min'] ) ? max( 0, min( 1000, absint( $in['min'] ) ) ) : $d['min'];

			if ( $cfg === $d ) {
				delete_term_meta( $term_id, 'freer_reviews_form_config' );
			} else {
				update_term_meta( $term_id, 'freer_reviews_form_config', $cfg );
			}
		}
	}


	/** Global default star scale (1..10), clamped. */
	public static function global_max() {
		$s = get_option( 'freer_reviews_settings', array() );
		$m = isset( $s['max_rating'] ) ? (int) $s['max_rating'] : 5;
		return max( 1, min( 10, $m ) );
	}

	/** @return WP_Term|null */
	public static function by_slug( $slug ) {
		if ( ! $slug ) {
			return null;
		}
		$t = get_term_by( 'slug', sanitize_title( $slug ), self::TAX );
		return ( $t && ! is_wp_error( $t ) ) ? $t : null;
	}

	/** Effective max rating for a target (target override > global). */
	public static function max_for( $term = null ) {
		$m = self::global_max();
		if ( $term && ! is_wp_error( $term ) ) {
			$tm = get_term_meta( $term->term_id, 'freer_reviews_max_rating', true );
			if ( $tm !== '' ) {
				$m = (int) $tm;
			}
		}
		return max( 1, min( 10, $m ) );
	}

	/** Effective auto-approve for a target (target override > global). */
	public static function auto_approve( $term = null ) {
		$s = get_option( 'freer_reviews_settings', array() );
		$g = ! empty( $s['auto_approve'] );
		if ( $term && ! is_wp_error( $term ) ) {
			$tm = get_term_meta( $term->term_id, 'freer_reviews_auto_approve', true );
			if ( $tm !== '' ) {
				$g = (bool) $tm;
			}
		}
		return $g;
	}

	/** Nice name for display. */
	public static function label( $term = null ) {
		if ( $term && ! is_wp_error( $term ) ) {
			return $term->name;
		}
		return __( 'General', 'freer-reviews' );
	}

	/** Assign a review to a target (by slug) + store its star scale. */
	public static function assign( $post_id, $slug = '', $max = 0 ) {
		if ( $slug ) {
			$term = self::by_slug( $slug );
			if ( $term ) {
				wp_set_object_terms( $post_id, (int) $term->term_id, self::TAX );
				if ( ! $max ) {
					$max = self::max_for( $term );
				}
				// Also tag the review with its parent Review Group so the
				// dashboard shows Groups > Forms > Reviews.
				$gid = (int) get_term_meta( $term->term_id, 'freer_reviews_group', true );
				if ( $gid && term_exists( $gid, self::GROUP_TAX ) ) {
					wp_set_object_terms( $post_id, array( $gid ), self::GROUP_TAX );
				}
			}
		}
		if ( ! $max ) {
			$max = self::global_max();
		}
		update_post_meta( $post_id, 'freer_reviews_max_rating', max( 1, min( 10, $max ) ) );
	}
}
