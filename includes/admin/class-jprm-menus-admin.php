<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * UI + data tweaks for the jprm_menu taxonomy:
 * - Rename Category/Categories -> Menu/Menus (list/add/edit)
 * - Hide Slug & Parent (list/add/edit)
 * - Remove Slug column (list)
 * - Ensure no invalid columns are added to wp_terms on insert/update
 *   (parent belongs to wp_term_taxonomy, never to wp_terms)
 */
class Menus_Admin {

	const TAX = 'jprm_menu';
	const META_IS_DAILY = '_jprm_is_daily_menu';
	const META_DATE = '_jprm_daily_menu_date';
	const META_DATE_TYPE = '_jprm_daily_menu_date_type';
	const META_END_DATE = '_jprm_daily_menu_end_date';
	const META_FIXED_PRICE = '_jprm_daily_menu_fixed_price';
	const NONCE_ACTION = 'jprm_save_daily_menu_fields';
	const NONCE_FIELD = '_jprm_daily_menu_nonce';

	public static function init() : void {
		// Remove "Slug" column on the Menus list screen
		add_filter( 'manage_edit-' . self::TAX . '_columns', [ __CLASS__, 'columns' ] );
		add_filter( 'manage_' . self::TAX . '_custom_column', [ __CLASS__, 'column_content' ], 10, 3 );

		add_action( self::TAX . '_add_form_fields', [ __CLASS__, 'add_daily_menu_fields' ] );
		add_action( self::TAX . '_edit_form_fields', [ __CLASS__, 'edit_daily_menu_fields' ] );
		add_action( 'created_' . self::TAX, [ __CLASS__, 'save_daily_menu_fields' ] );
		add_action( 'edited_' . self::TAX, [ __CLASS__, 'save_daily_menu_fields' ] );

		// Inject CSS/JS on BOTH taxonomy admin screens
		add_action( 'admin_head-edit-tags.php', [ __CLASS__, 'inject_css_js' ] );
		add_action( 'admin_head-term.php',      [ __CLASS__, 'inject_css_js' ] );

		/**
		 * IMPORTANT:
		 * - Never allow non-wp_terms columns (e.g. 'parent') into the $data array that inserts into wp_terms.
		 * - If parent needs normalizing, do it via wp_insert_term_args; core uses args for wp_term_taxonomy.
		 */
	add_filter( 'wp_insert_term_data', [ __CLASS__, 'sanitize_terms_table_data' ], 999, 3 );
	add_filter( 'wp_insert_term_args', [ __CLASS__, 'sanitize_parent_arg' ], 999, 2 );
	}

	/** Drop the "slug" column in the list table */
	public static function columns( $cols ) {
		if ( isset( $cols['slug'] ) ) unset( $cols['slug'] );
		$new = [];
		foreach ( $cols as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'name' === $key ) {
				$new['jprm_menu_type'] = __( 'Type', 'jellopoint-restaurant-menu' );
				$new['jprm_daily_date'] = __( 'Date', 'jellopoint-restaurant-menu' );
				$new['jprm_daily_price'] = __( 'Fixed Price', 'jellopoint-restaurant-menu' );
			}
		}
		return $new;
	}

	/** Render the custom columns on the Menus list screen. */
	public static function column_content( $content, $column_name, $term_id ) {
		if ( 'jprm_menu_type' === $column_name ) {
			return self::is_daily_menu( (int) $term_id )
				? esc_html__( 'Daily Menu', 'jellopoint-restaurant-menu' )
				: esc_html__( 'Menu', 'jellopoint-restaurant-menu' );
		}
		if ( 'jprm_daily_date' === $column_name ) {
			if ( ! self::is_daily_menu( (int) $term_id ) ) { return ''; }
			$start = (string) get_term_meta( (int) $term_id, self::META_DATE, true );
			$end = (string) get_term_meta( (int) $term_id, self::META_END_DATE, true );
			return esc_html( $end !== '' ? $start . ' – ' . $end : $start );
		}
		if ( 'jprm_daily_price' === $column_name ) {
			return self::is_daily_menu( (int) $term_id )
				? esc_html( (string) get_term_meta( (int) $term_id, self::META_FIXED_PRICE, true ) )
				: '';
		}
		return $content;
	}

	/** Fields shown below the standard New Menu fields. */
	public static function add_daily_menu_fields() : void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<div class="form-field jprm-daily-menu-toggle-wrap">
			<label><input type="checkbox" name="jprm_is_daily_menu" value="1" /> <?php esc_html_e( 'Daily Menu', 'jellopoint-restaurant-menu' ); ?></label>
			<p><?php esc_html_e( 'Mark this regular Menu as a date-specific Daily Menu.', 'jellopoint-restaurant-menu' ); ?></p>
		</div>
		<div class="form-field jprm-daily-menu-detail">
			<label for="jprm_daily_menu_date_type"><?php esc_html_e( 'Date Type', 'jellopoint-restaurant-menu' ); ?></label>
			<select id="jprm_daily_menu_date_type" name="jprm_daily_menu_date_type"><option value="single"><?php esc_html_e( 'Single Date', 'jellopoint-restaurant-menu' ); ?></option><option value="range"><?php esc_html_e( 'Date Range', 'jellopoint-restaurant-menu' ); ?></option></select>
		</div>
		<div class="form-field jprm-daily-menu-detail">
			<label for="jprm_daily_menu_date"><span class="jprm-date-label-single"><?php esc_html_e( 'Date', 'jellopoint-restaurant-menu' ); ?></span><span class="jprm-date-label-range"><?php esc_html_e( 'Start Date', 'jellopoint-restaurant-menu' ); ?></span></label>
			<input type="date" id="jprm_daily_menu_date" name="jprm_daily_menu_date" value="" />
		</div>
		<div class="form-field jprm-daily-menu-detail jprm-daily-menu-end-date">
			<label for="jprm_daily_menu_end_date"><?php esc_html_e( 'End Date', 'jellopoint-restaurant-menu' ); ?></label>
			<input type="date" id="jprm_daily_menu_end_date" name="jprm_daily_menu_end_date" value="" />
		</div>
		<div class="form-field jprm-daily-menu-detail">
			<label for="jprm_daily_menu_fixed_price"><?php esc_html_e( 'Fixed Menu Price', 'jellopoint-restaurant-menu' ); ?></label>
			<input type="text" inputmode="decimal" id="jprm_daily_menu_fixed_price" name="jprm_daily_menu_fixed_price" value="" placeholder="39.50" />
			<p><?php esc_html_e( 'Enter the amount without a currency symbol.', 'jellopoint-restaurant-menu' ); ?></p>
		</div>
		<?php self::daily_menu_toggle_script(); ?>
		<?php
	}

	/** Fields shown on the Edit Menu screen. */
	public static function edit_daily_menu_fields( $term ) : void {
		$term_id = isset( $term->term_id ) ? (int) $term->term_id : 0;
		$enabled = self::is_daily_menu( $term_id );
		$date = (string) get_term_meta( $term_id, self::META_DATE, true );
		$date_type = (string) get_term_meta( $term_id, self::META_DATE_TYPE, true );
		$date_type = 'range' === $date_type ? 'range' : 'single';
		$end_date = (string) get_term_meta( $term_id, self::META_END_DATE, true );
		$price = (string) get_term_meta( $term_id, self::META_FIXED_PRICE, true );
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<tr class="form-field jprm-daily-menu-toggle-wrap">
			<th scope="row"><?php esc_html_e( 'Daily Menu', 'jellopoint-restaurant-menu' ); ?></th>
			<td><label><input type="checkbox" name="jprm_is_daily_menu" value="1" <?php checked( $enabled ); ?> /> <?php esc_html_e( 'This is a Daily Menu', 'jellopoint-restaurant-menu' ); ?></label></td>
		</tr>
		<tr class="form-field jprm-daily-menu-detail">
			<th scope="row"><label for="jprm_daily_menu_date_type"><?php esc_html_e( 'Date Type', 'jellopoint-restaurant-menu' ); ?></label></th>
			<td><select id="jprm_daily_menu_date_type" name="jprm_daily_menu_date_type"><option value="single" <?php selected( $date_type, 'single' ); ?>><?php esc_html_e( 'Single Date', 'jellopoint-restaurant-menu' ); ?></option><option value="range" <?php selected( $date_type, 'range' ); ?>><?php esc_html_e( 'Date Range', 'jellopoint-restaurant-menu' ); ?></option></select></td>
		</tr>
		<tr class="form-field jprm-daily-menu-detail">
			<th scope="row"><label for="jprm_daily_menu_date"><span class="jprm-date-label-single"><?php esc_html_e( 'Date', 'jellopoint-restaurant-menu' ); ?></span><span class="jprm-date-label-range"><?php esc_html_e( 'Start Date', 'jellopoint-restaurant-menu' ); ?></span></label></th>
			<td><input type="date" id="jprm_daily_menu_date" name="jprm_daily_menu_date" value="<?php echo esc_attr( $date ); ?>" /></td>
		</tr>
		<tr class="form-field jprm-daily-menu-detail jprm-daily-menu-end-date">
			<th scope="row"><label for="jprm_daily_menu_end_date"><?php esc_html_e( 'End Date', 'jellopoint-restaurant-menu' ); ?></label></th>
			<td><input type="date" id="jprm_daily_menu_end_date" name="jprm_daily_menu_end_date" value="<?php echo esc_attr( $end_date ); ?>" /></td>
		</tr>
		<tr class="form-field jprm-daily-menu-detail">
			<th scope="row"><label for="jprm_daily_menu_fixed_price"><?php esc_html_e( 'Fixed Menu Price', 'jellopoint-restaurant-menu' ); ?></label></th>
			<td><input type="text" inputmode="decimal" id="jprm_daily_menu_fixed_price" name="jprm_daily_menu_fixed_price" value="<?php echo esc_attr( $price ); ?>" placeholder="39.50" /><p class="description"><?php esc_html_e( 'Enter the amount without a currency symbol.', 'jellopoint-restaurant-menu' ); ?></p></td>
		</tr>
		<?php self::daily_menu_toggle_script(); ?>
		<?php
	}

	/** Persist validated Daily Menu term metadata. */
	public static function save_daily_menu_fields( $term_id ) : void {
		if ( empty( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_categories' ) ) { return; }

		$term_id = (int) $term_id;
		$enabled = ! empty( $_POST['jprm_is_daily_menu'] );
		$date = self::sanitize_date( isset( $_POST['jprm_daily_menu_date'] ) ? wp_unslash( $_POST['jprm_daily_menu_date'] ) : '' );
		$date_type = isset( $_POST['jprm_daily_menu_date_type'] ) && 'range' === sanitize_key( wp_unslash( $_POST['jprm_daily_menu_date_type'] ) ) ? 'range' : 'single';
		$end_date = self::sanitize_end_date( $date, isset( $_POST['jprm_daily_menu_end_date'] ) ? wp_unslash( $_POST['jprm_daily_menu_end_date'] ) : '', $date_type );
		$price = self::sanitize_price( isset( $_POST['jprm_daily_menu_fixed_price'] ) ? wp_unslash( $_POST['jprm_daily_menu_fixed_price'] ) : '' );

		update_term_meta( $term_id, self::META_IS_DAILY, $enabled ? '1' : '0' );
		self::update_or_delete_meta( $term_id, self::META_DATE, $date );
		update_term_meta( $term_id, self::META_DATE_TYPE, $date_type );
		self::update_or_delete_meta( $term_id, self::META_END_DATE, $end_date );
		self::update_or_delete_meta( $term_id, self::META_FIXED_PRICE, $price );
	}

	public static function is_daily_menu( int $term_id ) : bool {
		return '1' === (string) get_term_meta( $term_id, self::META_IS_DAILY, true );
	}

	/** Accept only a real calendar date in YYYY-MM-DD format. */
	public static function sanitize_date( $value ) : string {
		$value = sanitize_text_field( (string) $value );
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		return $date && $date->format( 'Y-m-d' ) === $value ? $value : '';
	}

	/** Store a positive, currency-independent decimal using a dot separator. */
	public static function sanitize_price( $value ) : string {
		$value = trim( str_replace( ',', '.', sanitize_text_field( (string) $value ) ) );
		if ( ! preg_match( '/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/', $value ) ) { return ''; }
		return $value;
	}

	/** Return a valid inclusive range end date, or blank for a single/invalid range. */
	public static function sanitize_end_date( string $start_date, $end_date, string $date_type ) : string {
		$end_date = self::sanitize_date( $end_date );
		if ( 'range' !== $date_type || '' === $start_date || '' === $end_date || $end_date < $start_date ) { return ''; }
		return $end_date;
	}

	private static function update_or_delete_meta( int $term_id, string $key, string $value ) : void {
		if ( '' === $value ) { delete_term_meta( $term_id, $key ); }
		else { update_term_meta( $term_id, $key, $value ); }
	}

	private static function daily_menu_toggle_script() : void {
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function(){
			var toggle = document.querySelector('input[name="jprm_is_daily_menu"]');
			var type = document.getElementById('jprm_daily_menu_date_type');
			var start = document.getElementById('jprm_daily_menu_date');
			var end = document.getElementById('jprm_daily_menu_end_date');
			if (!toggle) return;
			function refresh(){
				document.querySelectorAll('.jprm-daily-menu-detail').forEach(function(field){ field.style.display = toggle.checked ? '' : 'none'; });
				var range = type && type.value === 'range';
				document.querySelectorAll('.jprm-date-label-single').forEach(function(label){ label.style.display = range ? 'none' : ''; });
				document.querySelectorAll('.jprm-date-label-range').forEach(function(label){ label.style.display = range ? '' : 'none'; });
				document.querySelectorAll('.jprm-daily-menu-end-date').forEach(function(field){ field.style.display = toggle.checked && range ? '' : 'none'; });
				if (end) { end.min = start ? start.value : ''; end.required = toggle.checked && range; }
				if (start) { start.required = toggle.checked; }
			}
			toggle.addEventListener('change', refresh);
			if (type) type.addEventListener('change', refresh);
			if (start) start.addEventListener('change', refresh);
			refresh();
		});
		</script>
		<?php
	}

	/**
	 * Keep only valid wp_terms columns: name, slug, term_group.
	 * DO NOT pass 'parent' here — that belongs in wp_term_taxonomy and is handled from $args.
	 *
	 * @param array  $data     Data for wp_terms insert/update.
	 * @param string $taxonomy Current taxonomy.
	 * @param array  $args     Arguments including parent for wp_term_taxonomy.
	 * @return array
	 */
	public static function sanitize_terms_table_data( $data, $taxonomy, $args ) {
		// Only affect our taxonomy (extend array if you want the same behavior for others)
		if ( $taxonomy !== self::TAX ) {
			return $data;
		}

		$allowed = [ 'name', 'slug', 'term_group' ];
		$clean   = array_intersect_key( (array) $data, array_flip( $allowed ) );

		// Ensure required keys exist (WordPress will set defaults but we keep it tidy)
		if ( ! isset( $clean['term_group'] ) ) {
			$clean['term_group'] = 0;
		}

		return $clean;
	}

	/**
	 * Ensure 'parent' (used for wp_term_taxonomy) is numeric and sane.
	 * Core reads parent from $args, not from $data.
	 *
	 * @param array  $args     Insert/update args for wp_term_taxonomy.
	 * @param string $taxonomy Current taxonomy.
	 * @return array
	 */
	public static function sanitize_parent_arg( $args, $taxonomy ) {
		if ( $taxonomy !== self::TAX ) {
			return $args;
		}
		// If parent is missing or invalid, normalize to 0 (menus are effectively non-hierarchical in your UI)
		if ( ! isset( $args['parent'] ) || ! is_numeric( $args['parent'] ) ) {
			$args['parent'] = 0;
		} else {
			$args['parent'] = (int) $args['parent'];
			if ( $args['parent'] < 0 ) {
				$args['parent'] = 0;
			}
		}
		return $args;
	}

	/** CSS/JS polish – runs on edit-tags.php and term.php; no GET reliance */
	public static function inject_css_js() : void {
		?>
		<style>
			/* Hide Slug + Parent on add + edit for jprm_menu */
			body.taxonomy-<?php echo esc_attr( self::TAX ); ?> .form-field.term-slug-wrap,
			body.taxonomy-<?php echo esc_attr( self::TAX ); ?> .term-slug-wrap,
			body.taxonomy-<?php echo esc_attr( self::TAX ); ?> .form-field.term-parent-wrap,
			body.taxonomy-<?php echo esc_attr( self::TAX ); ?> .term-parent-wrap {
				display: none !important;
			}
		</style>
		<script>
		(function(){
			function onReady(fn){ if(document.readyState!=='loading'){fn();}else{document.addEventListener('DOMContentLoaded',fn);} }

			function renameOnListOrAdd(){
				if (!document.body.classList.contains('taxonomy-<?php echo esc_js(self::TAX); ?>')) return;

				// Left add box title: "Add Category" -> "Add Menu"
				var addHdr = document.querySelector('.wrap .form-wrap > h2') || document.querySelector('.tag-add-form h2');
				if (addHdr && /Add\s+Category/i.test(addHdr.textContent)) addHdr.textContent = 'Add Menu';

				// Left add box submit button text (covers input/button variants)
				var addSubmit = document.querySelector('#addtag input#submit, #addtag button#submit, .tag-add-form input[type="submit"], .tag-add-form button[type="submit"]');
				if (addSubmit) {
					if (addSubmit.tagName === 'INPUT') addSubmit.value = 'Add Menu';
					else addSubmit.textContent = 'Add Menu';
					addSubmit.setAttribute('aria-label', 'Add Menu');
				}

				// Search label + placeholder (top right)
				var searchLbl = document.querySelector('label[for="tag-search-input"]') ||
				                document.querySelector('.search-form label') ||
				                document.querySelector('.search-box label');
				if (searchLbl) searchLbl.textContent = 'Search Menus:';

				var searchInp = document.getElementById('tag-search-input') ||
				                document.querySelector('.search-form input[type="search"], .search-form input[type="text"]');
				if (searchInp) searchInp.placeholder = 'Search Menus';

				// Page title & subnav text
				var h1 = document.querySelector('.wrap > h1');
				if (h1) h1.textContent = h1.textContent.replace(/Categories/gi, 'Menus').replace(/\bCategory\b/gi, 'Menu');

				document.querySelectorAll('.subsubsub a').forEach(function(a){
					a.textContent = a.textContent.replace(/Categories/gi, 'Menus').replace(/\bCategory\b/gi, 'Menu');
				});

				// Ensure hidden parent select doesn't accidentally submit non-zero
				var parentAdd = document.querySelector('#addtag .form-field.term-parent-wrap select');
				if (parentAdd) parentAdd.value = '0';
			}

			function renameOnSingleEdit(){
				if (!document.body.classList.contains('taxonomy-<?php echo esc_js(self::TAX); ?>')) return;

				// Page title
				var h1 = document.querySelector('.wrap > h1');
				if (h1) h1.textContent = h1.textContent.replace(/Categories/gi, 'Menus').replace(/\bCategory\b/gi, 'Menu');

				// Ensure hidden parent on edit stays zero
				var parentEdit = document.querySelector('.edit-tag-form .form-field.term-parent-wrap select');
				if (parentEdit) parentEdit.value = '0';
			}

			onReady(function(){
				renameOnListOrAdd();
				renameOnSingleEdit();
			});
		})();
		</script>
		<?php
	}
}
