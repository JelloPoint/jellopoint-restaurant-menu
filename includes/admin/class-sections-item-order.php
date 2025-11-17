<?php
namespace JelloPoint\RestaurantMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Sections_Item_Order {

	public static function init() : void {
		$tax = 'jprm_section';
		add_action( "{$tax}_add_form_fields",  [ __CLASS__, 'add_field' ] );
		add_action( "{$tax}_edit_form_fields", [ __CLASS__, 'edit_field' ] );
		add_action( "created_{$tax}",           [ __CLASS__, 'save' ] );
		add_action( "edited_{$tax}",            [ __CLASS__, 'save' ] );
	}

	public static function add_field() : void { ?>
		<div class="form-field term-group">
			<label for="jprm_items_orderby"><?php esc_html_e( 'Items order by', 'jprm' ); ?></label>
			<select id="jprm_items_orderby" name="jprm_items_orderby">
				<option value="menu_order"><?php esc_html_e('Menu Order','jprm'); ?></option>
				<option value="title"><?php esc_html_e('Title (A→Z)','jprm'); ?></option>
				<option value="date"><?php esc_html_e('Date (old→new)','jprm'); ?></option>
			</select>
		</div>
		<div class="form-field term-group">
			<label for="jprm_items_orderdir"><?php esc_html_e( 'Items direction', 'jprm' ); ?></label>
			<select id="jprm_items_orderdir" name="jprm_items_orderdir">
				<option value="ASC"><?php esc_html_e('ASC','jprm'); ?></option>
				<option value="DESC"><?php esc_html_e('DESC','jprm'); ?></option>
			</select>
			<p class="description"><?php esc_html_e('This controls how menu items inside this section are sorted on the frontend.', 'jprm'); ?></p>
		</div>
	<?php }

	public static function edit_field( $term ) : void {
		$orderby = get_term_meta( $term->term_id, '_jprm_items_orderby', true ) ?: 'menu_order';
		$orderdir = strtoupper( (string) get_term_meta( $term->term_id, '_jprm_items_orderdir', true ) ) ?: 'ASC'; ?>
		<tr class="form-field term-group-wrap">
			<th scope="row"><label for="jprm_items_orderby"><?php esc_html_e('Items order by','jprm'); ?></label></th>
			<td>
				<select id="jprm_items_orderby" name="jprm_items_orderby">
					<option value="menu_order" <?php selected($orderby,'menu_order'); ?>><?php esc_html_e('Menu Order','jprm'); ?></option>
					<option value="title"      <?php selected($orderby,'title'); ?>><?php esc_html_e('Title (A→Z)','jprm'); ?></option>
					<option value="date"       <?php selected($orderby,'date'); ?>><?php esc_html_e('Date (old→new)','jprm'); ?></option>
				</select>
			</td>
		</tr>
		<tr class="form-field term-group-wrap">
			<th scope="row"><label for="jprm_items_orderdir"><?php esc_html_e('Items direction','jprm'); ?></label></th>
			<td>
				<select id="jprm_items_orderdir" name="jprm_items_orderdir">
					<option value="ASC"  <?php selected(strtoupper($orderdir),'ASC'); ?>>ASC</option>
					<option value="DESC" <?php selected(strtoupper($orderdir),'DESC'); ?>>DESC</option>
				</select>
				<p class="description"><?php esc_html_e('This controls how menu items inside this section are sorted on the frontend.', 'jprm'); ?></p>
			</td>
		</tr>
	<?php }

	public static function save( $term_id ) : void {
		if ( isset($_POST['jprm_items_orderby']) ) {
			$by = (string) $_POST['jprm_items_orderby'];
			if ( ! in_array( $by, ['menu_order','title','date'], true ) ) $by = 'menu_order';
			update_term_meta( $term_id, '_jprm_items_orderby', $by );
		}
		if ( isset($_POST['jprm_items_orderdir']) ) {
			$dir = strtoupper( (string) $_POST['jprm_items_orderdir'] );
			$dir = ( $dir === 'DESC' ) ? 'DESC' : 'ASC';
			update_term_meta( $term_id, '_jprm_items_orderdir', $dir );
		}
	}
}
