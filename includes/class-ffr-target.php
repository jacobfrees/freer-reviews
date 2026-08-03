<?php
/**
 * Review Targets: the taxonomy that lets one site collect reviews for many
 * different things (products, services, posts, whatever) and manage them
 * separately, each with its own star scale (1..10) and auto-approve rule.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FFR_Target {

	const TAX = 'ffr_target';

	public static function register() {
		register_taxonomy( self::TAX, FFR_CPT, array(
			'labels'            => array(
				'name'          => __( 'Review Targets', 'freeflow-reviews' ),
				'singular_name' => __( 'Review Target', 'freeflow-reviews' ),
				'search_items'  => __( 'Search Targets', 'freeflow-reviews' ),
				'all_items'     => __( 'All Targets', 'freeflow-reviews' ),
				'edit_item'     => __( 'Edit Target', 'freeflow-reviews' ),
				'add_new_item'  => __( 'Add Review Target', 'freeflow-reviews' ),
				'new_item_name' => __( 'New Target Name', 'freeflow-reviews' ),
			),
			'public'            => false,
			'show_ui'           => true,
			'show_in_menu'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'hierarchical'      => false,
			'query_var'         => false,
			'rewrite'           => false,
		) );

		$meta = array(
			'ffr_max_rating'       => array( 'type' => 'integer' ),
			'ffr_auto_approve'     => array( 'type' => 'integer' ),
			'ffr_form_title'       => array( 'type' => 'string' ),
			'ffr_thankyou_message' => array( 'type' => 'string' ),
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
		$cols['ffr_stars'] = __( 'Stars', 'freeflow-reviews' );
		$cols['ffr_go']    = __( 'Posting', 'freeflow-reviews' );
		return $cols;
	}

	public static function term_column_value( $out, $col, $term_id ) {
		if ( 'ffr_stars' === $col ) {
			$m = get_term_meta( $term_id, 'ffr_max_rating', true );
			return $m ? esc_html( $m . ' / ' . $m ) : esc_html( self::global_max() . ' / ' . self::global_max() );
		}
		if ( 'ffr_go' === $col ) {
			$aa = (int) get_term_meta( $term_id, 'ffr_auto_approve', true );
			return $aa ? __( 'Auto-post', 'freeflow-reviews' ) : __( 'Moderated', 'freeflow-reviews' );
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
		$m  = ( $term && ! is_wp_error( $term ) ) ? (int) get_term_meta( $term->term_id, 'ffr_max_rating', true ) : 0;
		$aa = ( $term && ! is_wp_error( $term ) ) ? (int) get_term_meta( $term->term_id, 'ffr_auto_approve', true ) : 0;
		$ft = ( $term && ! is_wp_error( $term ) ) ? get_term_meta( $term->term_id, 'ffr_form_title', true ) : '';
		?>
		<tr class="form-field">
			<th scope="row"><label for="ffr_max_rating"><?php esc_html_e( 'Star scale (max rating)', 'freeflow-reviews' ); ?></label></th>
			<td>
				<input type="number" id="ffr_max_rating" name="ffr_max_rating" value="<?php echo $m ? (int) $m : 5; ?>" min="1" max="10" step="1" />
				<p class="description"><?php esc_html_e( 'How many stars this thing is rated out of. Up to 10.', 'freeflow-reviews' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Posting', 'freeflow-reviews' ); ?></th>
			<td>
				<label><input type="checkbox" name="ffr_auto_approve" value="1" <?php checked( $aa, 1 ); ?> /> <?php esc_html_e( 'Auto-publish reviews for this target (overrides global)', 'freeflow-reviews' ); ?></label>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="ffr_form_title"><?php esc_html_e( 'Form title', 'freeflow-reviews' ); ?></label></th>
			<td>
				<input type="text" id="ffr_form_title" name="ffr_form_title" value="<?php echo esc_attr( $ft ); ?>" />
				<p class="description"><?php esc_html_e( 'Optional headline shown above this target\'s review form.', 'freeflow-reviews' ); ?></p>
			</td>
		</tr>
		<?php
	}

	public static function term_save( $term_id ) {
		if ( isset( $_POST['ffr_max_rating'] ) ) {
			update_term_meta( $term_id, 'ffr_max_rating', max( 1, min( 10, absint( $_POST['ffr_max_rating'] ) ) ) );
		}
		update_term_meta( $term_id, 'ffr_auto_approve', ! empty( $_POST['ffr_auto_approve'] ) ? 1 : 0 );
		if ( isset( $_POST['ffr_form_title'] ) ) {
			update_term_meta( $term_id, 'ffr_form_title', sanitize_text_field( wp_unslash( $_POST['ffr_form_title'] ) ) );
		}
	}


	/** Global default star scale (1..10), clamped. */
	public static function global_max() {
		$s = get_option( 'ffr_settings', array() );
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
			$tm = get_term_meta( $term->term_id, 'ffr_max_rating', true );
			if ( $tm !== '' ) {
				$m = (int) $tm;
			}
		}
		return max( 1, min( 10, $m ) );
	}

	/** Effective auto-approve for a target (target override > global). */
	public static function auto_approve( $term = null ) {
		$s = get_option( 'ffr_settings', array() );
		$g = ! empty( $s['auto_approve'] );
		if ( $term && ! is_wp_error( $term ) ) {
			$tm = get_term_meta( $term->term_id, 'ffr_auto_approve', true );
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
		return __( 'General', 'freeflow-reviews' );
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
			}
		}
		if ( ! $max ) {
			$max = self::global_max();
		}
		update_post_meta( $post_id, 'ffr_max_rating', max( 1, min( 10, $max ) ) );
	}
}
