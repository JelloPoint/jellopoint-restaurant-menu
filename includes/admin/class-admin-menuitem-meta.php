<?php
/**
 * Admin meta for JPRM Menu Item
 */
if ( ! defined('ABSPATH') ) exit;

if ( ! class_exists('JPRM_Admin_MenuItem_Meta') ) {
class JPRM_Admin_MenuItem_Meta {

	/* ------------------------------ bootstrap ------------------------------ */
	public static function init(){
		add_action('add_meta_boxes',           [__CLASS__, 'register_metaboxes']);
		add_action('save_post_jprm_menu_item', [__CLASS__, 'save'], 10, 2);
		add_action('admin_enqueue_scripts',    [__CLASS__, 'enqueue'], 100);
		add_action('admin_head',               [__CLASS__, 'hide_core_editor']);
	}

	/* -------------------------------- assets ------------------------------- */
	public static function hide_core_editor(){
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if ($screen && $screen->post_type === 'jprm_menu_item'){
			echo '<style>#postdivrich,#wp-content-media-buttons{display:none!important;}</style>';
		}
	}

	public static function enqueue(){
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if ( ! $screen || $screen->post_type !== 'jprm_menu_item') return;

		wp_enqueue_script('jquery');
		if (function_exists('wp_enqueue_media')) wp_enqueue_media();

		// hard stop any legacy/conflicting enqueues
		foreach (['jprm-legacy-admin','jprm-admin','jprm-admin-js','jprm-admin-menu','jprm-admin-menuitem'] as $h) wp_dequeue_script($h);
		foreach (['jprm-legacy-admin','jprm-admin','jprm-admin-css','jprm-admin-menu','jprm-admin-menuitem'] as $h) wp_dequeue_style($h);

		$css = <<<CSS
		/* Tight form UI */
		.jprm-box .form-table th{ width: 140px; vertical-align: top; padding-top: 10px;}
		.jprm-box .form-table td{ padding-top: 8px;}
		.jprm-field-mini{ width: 90px;}
		.jprm-field-short{ width: 140px;}
		.jprm-field-amount{ width: 110px;}
		.jprm-price-rows table{ width:100%; border-collapse: collapse; }
		.jprm-price-rows th, .jprm-price-rows td{ padding:6px 8px; border-bottom:1px solid #e5e5e5; }
		.jprm-price-rows th{ text-align:left; }
		.jprm-row-actions{ white-space: nowrap; }
		.jprm-thumb{ width: 28px; height: 28px; object-fit: contain; border: 1px solid #ccd0d4; background:#fff; border-radius:3px; vertical-align: middle; }
		.jprm-inline-help{ color:#666; font-size:12px; opacity:.9; }
		.jprm-badge{ display:inline-block; background:#f0f6f9; border:1px solid #ccd0d4; padding:2px 6px; border-radius:4px; }
		.jprm-mode-switch label{ margin-right:14px; }
		.jprm-box h2.hndle span small{ font-weight: normal; color:#777; margin-left:8px; }
		.jprm-note{ background:#fffbe6; border:1px solid #ffe58f; padding:8px 10px; border-radius:4px; }
		.jprm-ghost{ opacity:0.5; }
		.jprm-nowrap{ white-space:nowrap; }
		.jprm-hidden{ display:none; }
		.jprm-col-icon{ width: 190px; }
		.jprm-col-actions{ width: 120px; text-align:right; }
		.jprm-col-amount{ width: 140px; }
		.jprm-col-hide{ width: 80px; text-align:center; }
		.jprm-col-label{ min-width: 200px; }
		CSS;
		wp_register_style('jprm-menuitem-inline', false);
		wp_enqueue_style('jprm-menuitem-inline');
		wp_add_inline_style('jprm-menuitem-inline', $css);

		$js = <<<JS
		jQuery(function($){
			function toggleMode(){
				var v = $('input[name="jprm_price_mode"]:checked').val();
				$('.jprm-section-single').toggle(v==='single');
				$('.jprm-section-multi').toggle(v==='multi');
			}
			$(document).on('change','input[name="jprm_price_mode"]', toggleMode);
			toggleMode();

			// add row
			$(document).on('click','.jprm-add-row', function(e){
				e.preventDefault();
				var $t = $('#jprm-rows-body');
				var i = $t.children('tr').length;
				var tpl = $('#jprm-row-template').html().replace(/__IDX__/g, i);
				$t.append(tpl);
			});

			// remove row
			$(document).on('click','.jprm-del-row', function(e){
				e.preventDefault();
				$(this).closest('tr').remove();
			});

			// label mode switch
			$(document).on('change','.jprm-label-mode', function(){
				var $row = $(this).closest('tr');
				var m = $(this).val();
				$row.find('.jprm-lref').toggle(m==='ref');
				$row.find('.jprm-lcustom').toggle(m==='custom');
			}).trigger('change');

			// pick icon
			$(document).on('click','.jprm-pick-icon', function(e){
				e.preventDefault();
				var $row = $(this).closest('tr');
				var $id  = $row.find('.jprm-icon-id');
				var $img = $row.find('.jprm-icon-thumb');
				var frame = wp.media({title:'Select Icon', multiple:false, library:{type:'image'}});
				frame.on('select', function(){
					var att = frame.state().get('selection').first().toJSON();
					$id.val(att.id);
					if(att.sizes && att.sizes.thumbnail){ $img.attr('src', att.sizes.thumbnail.url); }
					else { $img.attr('src', att.url); }
				});
				frame.open();
			});

			// clear icon
			$(document).on('click','.jprm-clear-icon', function(e){
				e.preventDefault();
				var $row = $(this).closest('tr');
				$row.find('.jprm-icon-id').val('0');
				$row.find('.jprm-icon-thumb').attr('src','');
			});
		});
		JS;
		wp_add_inline_script('jquery-core', $js, 'after');
	}

	/* ------------------------------- metaboxes ------------------------------ */
	public static function register_metaboxes(){
		add_meta_box('jprm_desc',    esc_html__('Description','jellopoint-restaurant-menu'), [__CLASS__,'render_desc'],    'jprm_menu_item','normal','high');
		add_meta_box('jprm_pricing', esc_html__('Prices & Labels','jellopoint-restaurant-menu'), [__CLASS__,'render_pricing'],'jprm_menu_item','normal','high');
		add_meta_box('jprm_other',   esc_html__('Visibility & Badge','jellopoint-restaurant-menu'), [__CLASS__,'render_other'],'jprm_menu_item','side','core');
	}

	/* --------------------------------- views -------------------------------- */
	public static function render_desc($post){
		wp_nonce_field('jprm_meta','jprm_meta_nonce');
		$desc = get_post_meta($post->ID,'jprm_desc',true);
		echo '<table class="form-table"><tbody>';
		echo '<tr><th style="width:140px;"><label for="jprm_desc">'.esc_html__('Description','jellopoint-restaurant-menu').'</label></th><td>';
		printf('<textarea id="jprm_desc" name="jprm_desc" rows="3" style="width:100%%;">%s</textarea>', esc_textarea($desc));
		echo '</td></tr></tbody></table>';
	}

	public static function render_pricing($post){
		/* --- single price fields --- */
		$mode   = get_post_meta($post->ID,'jprm_price_mode',true) ?: 'single';
		$amount = get_post_meta($post->ID,'jprm_price_amount',true);

		$lm   = get_post_meta($post->ID,'jprm_price_label_mode',true) ?: 'ref';
		$lref = (string)get_post_meta($post->ID,'jprm_price_label_ref',true);
		$lcc  = (string)get_post_meta($post->ID,'jprm_price_label_custom',true);
		$ico  = (int) get_post_meta($post->ID,'jprm_price_label_icon_id',true);

		/* --- multi rows --- */
		$rows_json = get_post_meta($post->ID,'jprm_prices',true);
		$rows = [];
		if (is_string($rows_json) && $rows_json !== ''){
			$tmp = json_decode($rows_json, true);
			if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) $rows = $tmp;
		}

		$labels = get_option('jprm_price_labels_v2');
		if (is_string($labels)){
			$labels = json_decode($labels, true);
			if (json_last_error() !== JSON_ERROR_NONE) $labels = [];
		}
		if (!is_array($labels)) $labels = [];

		$label_map = [];
		foreach ($labels as $L){
			$label_map[(string)($L['id']??'')] = [
				'text'    => (string)($L['label'] ?? ''),
				'icon_id' => isset($L['icon_id']) ? (int)$L['icon_id'] : (isset($L['icon']) ? (int)$L['icon'] : 0),
			];
		}
		$icon_url = function($id){
			$sizes=['thumbnail','medium','full'];
			foreach($sizes as $s){ $src=wp_get_attachment_image_src((int)$id,$s); if(is_array($src)&&!empty($src[0])) return $src[0]; }
			return wp_get_attachment_url((int)$id) ?: '';
		};

		/* --- single select options --- */
		$single_opts = '<option value="">'.esc_html__('- Select -','jellopoint-restaurant-menu').'</option>';
		foreach ($labels as $L){
			$single_opts .= sprintf(
				'<option value="%s"%s>%s</option>',
				esc_attr((string)$L['id']),
				selected($lref,(string)$L['id'],false),
				esc_html((string)$L['label'])
			);
		}

		echo '<div class="jprm-box">';
		echo '<p class="jprm-mode-switch">';
		printf('<label><input type="radio" name="jprm_price_mode" value="single" %s /> %s</label> &nbsp; ',
			checked($mode,'single', false), esc_html__('Single Price','jellopoint-restaurant-menu'));
		printf('<label><input type="radio" name="jprm_price_mode" value="multi" %s /> %s</label>',
			checked($mode,'multi', false), esc_html__('Multiple Prices','jellopoint-restaurant-menu'));
		echo '</p>';

		/* ---------------------------- single section ---------------------------- */
		echo '<div class="jprm-section-single">';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>'.esc_html__('Amount','jellopoint-restaurant-menu').'</th><td>';
		printf('<input type="text" name="jprm_price_amount" class="jprm-field-amount" value="%s" placeholder="%s" />',
			esc_attr($amount), esc_attr__('e.g. 9,95','jellopoint-restaurant-menu'));
		echo '</td></tr>';

		echo '<tr><th>'.esc_html__('Label','jellopoint-restaurant-menu').'</th><td>';
		printf('<label class="jprm-nowrap"><input type="radio" name="jprm_price_label_mode" value="ref" %s /> %s</label> &nbsp; ',
			checked($lm,'ref',false), esc_html__('Use registry label','jellopoint-restaurant-menu'));
		printf('<label class="jprm-nowrap"><input type="radio" name="jprm_price_label_mode" value="custom" %s /> %s</label>',
			checked($lm,'custom',false), esc_html__('Custom label','jellopoint-restaurant-menu'));
		echo '<div class="jprm-inline-help">'.esc_html__('Either pick a stored label or type a one-off custom label.','jellopoint-restaurant-menu').'</div>';

		echo '<div class="jprm-label-ref" style="margin-top:6px;">';
		printf('<select name="jprm_price_label_ref" class="jprm-field-short">%s</select>', $single_opts);
		echo '</div>';

		echo '<div class="jprm-label-custom" style="margin-top:6px;">';
		printf('<input type="text" name="jprm_price_label_custom" class="regular-text" value="%s" placeholder="%s" />',
			esc_attr($lcc), esc_attr__('E.g. Small Glass','jellopoint-restaurant-menu'));
		echo '</div>';

		echo '<div style="margin-top:6px;">';
		$thumb = $ico ? $icon_url($ico) : '';
		printf('<img src="%s" class="jprm-thumb jprm-sicon" alt="" /> ', esc_url($thumb));
		printf('<input type="number" name="jprm_price_label_icon_id" class="jprm-field-mini" value="%d" min="0" step="1" /> ', (int)$ico);
		echo '<a href="#" class="button button-secondary jprm-pick-icon">'.esc_html__('Pick Icon','jellopoint-restaurant-menu').'</a> ';
		echo '<a href="#" class="button jprm-clear-icon">'.esc_html__('Clear','jellopoint-restaurant-menu').'</a>';
		echo '<div class="jprm-inline-help">'.esc_html__('You can override the icon for this single price.','jellopoint-restaurant-menu').'</div>';
		echo '</div>';

		echo '</td></tr>';
		echo '</tbody></table>';
		echo '</div>';

		/* ---------------------------- multi section ----------------------------- */
		echo '<div class="jprm-section-multi">';
		echo '<div class="jprm-price-rows">';
		echo '<table class="widefat fixed striped">';
		echo '<thead>
			<tr>
				<th></th>
				<th class="label-cell">'.esc_html__('Label','jellopoint-restaurant-menu').'</th>
				<th>'.esc_html__('Amount','jellopoint-restaurant-menu').'</th>
				<th>'.esc_html__('Icon','jellopoint-restaurant-menu').'</th>
				<th>'.esc_html__('Hide','jellopoint-restaurant-menu').'</th>
				<th></th>
			</tr>
			</thead>
			<tbody>
		';

		// rows: enabled, label_mode(ref/custom), label_ref, label_custom, icon_id, amount, hide_icon
		if (empty($rows)) $rows = [
			['enabled'=>true,'label_mode'=>'ref','label_ref'=>'','label_custom'=>'','icon_id'=>0,'amount'=>'','hide_icon'=>false],
		];

		foreach ($rows as $i => $r){
			$en   = array_key_exists('enabled',$r) ? !empty($r['enabled']) : true;
			$lmd  = (($r['label_mode'] ?? 'ref') === 'custom') ? 'custom' : 'ref';
			$lrf  = (string)($r['label_ref']    ?? '');
			$lcc2 = (string)($r['label_custom'] ?? '');
			$ico2 = (int)   ($r['icon_id']      ?? 0);
			$amt  = (string)($r['amount']       ?? '');
			$hid  = ! empty($r['hide_icon']);

			$opts = '<option value="">'.esc_html__('- Select -','jellopoint-restaurant-menu').'</option>';
			foreach ($labels as $L){
				$opts .= sprintf(
					'<option value="%s"%s>%s</option>',
					esc_attr((string)$L['id']),
					selected($lrf,(string)$L['id'],false),
					esc_html((string)$L['label'])
				);
			}

			$th = $ico2 ? $icon_url($ico2) : '';

			echo '<tr>';
			echo '<td><label><input type="checkbox" name="jprm_rows['.$i.'][enabled]" value="1" '.checked($en,true,false).' /> '.esc_html__('On','jellopoint-restaurant-menu').'</label></td>';

			echo '<td class="label-cell">';
			echo '<div class="jprm-nowrap" style="margin-bottom:6px;">';
			echo '<label><input type="radio" name="jprm_rows['.$i.'][label_mode]" class="jprm-label-mode" value="ref" '.checked($lmd,'ref',false).' /> '.esc_html__('Registry','jellopoint-restaurant-menu').'</label> &nbsp; ';
			echo '<label><input type="radio" name="jprm_rows['.$i.'][label_mode]" class="jprm-label-mode" value="custom" '.checked($lmd,'custom',false).' /> '.esc_html__('Custom','jellopoint-restaurant-menu').'</label>';
			echo '</div>';

			echo '<div class="jprm-lref"><select name="jprm_rows['.$i.'][label_ref]" class="jprm-field-short">'.$opts.'</select></div>';
			echo '<div class="jprm-lcustom"><input type="text" name="jprm_rows['.$i.'][label_custom]" value="'.esc_attr($lcc2).'" placeholder="'.esc_attr__('E.g. Small','jellopoint-restaurant-menu').'" /></div>';
			echo '</td>';

			echo '<td class="jprm-col-amount"><input type="text" name="jprm_rows['.$i.'][amount]" class="jprm-field-amount" value="'.esc_attr($amt).'" placeholder="'.esc_attr__('e.g. 9,95','jellopoint-restaurant-menu').'" /></td>';

			echo '<td class="jprm-col-icon">';
			echo '<img src="'.esc_url($th ?: '').'" class="jprm-thumb jprm-icon-thumb" alt="" /> ';
			echo '<input type="number" name="jprm_rows['.$i.'][icon_id]" class="jprm-icon-id jprm-field-mini" value="'.(int)$ico2.'" min="0" step="1" /> ';
			echo '<a href="#" class="button button-secondary jprm-pick-icon">'.esc_html__('Pick','jellopoint-restaurant-menu').'</a> ';
			echo '<a href="#" class="button jprm-clear-icon">'.esc_html__('Clear','jellopoint-restaurant-menu').'</a>';
			echo '</td>';

			echo '<td class="jprm-col-hide" style="text-align:center;"><input type="checkbox" name="jprm_rows['.$i.'][hide_icon]" value="1" '.checked($hid,true,false).' /></td>';

			echo '<td class="jprm-col-actions" style="text-align:right;"><a href="#" class="button jprm-del-row">'.esc_html__('Delete','jellopoint-restaurant-menu').'</a></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<p><a href="#" class="button button-secondary jprm-add-row">'.esc_html__('Add Row','jellopoint-restaurant-menu').'</a></p>';
		echo '</div>'; // .jprm-price-rows
		echo '</div>'; // .jprm-section-multi

		// Row template
		echo '<script type="text/template" id="jprm-row-template">';
		echo '<tr>';
		echo '<td><label><input type="checkbox" name="jprm_rows[__IDX__][enabled]" value="1" checked /> '.esc_html__('On','jellopoint-restaurant-menu').'</label></td>';
		echo '<td class="label-cell">';
		echo '<div class="jprm-nowrap" style="margin-bottom:6px;">';
		echo '<label><input type="radio" name="jprm_rows[__IDX__][label_mode]" class="jprm-label-mode" value="ref" checked /> '.esc_html__('Registry','jellopoint-restaurant-menu').'</label> &nbsp; ';
		echo '<label><input type="radio" name="jprm_rows[__IDX__][label_mode]" class="jprm-label-mode" value="custom" /> '.esc_html__('Custom','jellopoint-restaurant-menu').'</label>';
		echo '</div>';
		echo '<div class="jprm-lref"><select name="jprm_rows[__IDX__][label_ref]" class="jprm-field-short"><option value="">'.esc_html__('- Select -','jellopoint-restaurant-menu').'</option>';
		foreach ($labels as $L){
			echo '<option value="'.esc_attr((string)$L['id']).'">'.esc_html((string)$L['label']).'</option>';
		}
		echo '</select></div>';
		echo '<div class="jprm-lcustom"><input type="text" name="jprm_rows[__IDX__][label_custom]" value="" placeholder="'.esc_attr__('E.g. Small','jellopoint-restaurant-menu').'" /></div>';
		echo '</td>';
		echo '<td class="jprm-col-amount"><input type="text" name="jprm_rows[__IDX__][amount]" class="jprm-field-amount" value="" placeholder="'.esc_attr__('e.g. 9,95','jellopoint-restaurant-menu').'" /></td>';
		echo '<td class="jprm-col-icon">';
		echo '<img src="" class="jprm-thumb jprm-icon-thumb" alt="" /> ';
		echo '<input type="number" name="jprm_rows[__IDX__][icon_id]" class="jprm-icon-id jprm-field-mini" value="0" min="0" step="1" /> ';
		echo '<a href="#" class="button button-secondary jprm-pick-icon">'.esc_html__('Pick','jellopoint-restaurant-menu').'</a> ';
		echo '<a href="#" class="button jprm-clear-icon">'.esc_html__('Clear','jellopoint-restaurant-menu').'</a>';
		echo '</td>';
		echo '<td class="jprm-col-hide" style="text-align:center;"><input type="checkbox" name="jprm_rows[__IDX__][hide_icon]" value="1" /></td>';
		echo '<td class="jprm-col-actions" style="text-align:right;"><a href="#" class="button jprm-del-row">'.esc_html__('Delete','jellopoint-restaurant-menu').'</a></td>';
		echo '</tr>';
		echo '</script>';

		echo '</div>'; // .jprm-box

		// toggle UI pieces for single label mode
		echo '<script>
		jQuery(function($){
			function syncSingleLabelMode(){
				var m = $("input[name=\'jprm_price_label_mode\']:checked").val();
				$(".jprm-label-ref").toggle(m==="ref");
				$(".jprm-label-custom").toggle(m==="custom");
			}
			$(document).on("change","input[name=\'jprm_price_label_mode\']", syncSingleLabelMode);
			syncSingleLabelMode();
		});
		</script>';
	}

	public static function render_other($post){
		$vis   = get_post_meta($post->ID,'jprm_visible',true) === 'yes';
		$badge = get_post_meta($post->ID,'jprm_badge',true);
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>'.esc_html__('Visible','jellopoint-restaurant-menu').'</th><td>';
		printf('<label><input type="checkbox" name="jprm_visible" value="yes" %s> %s</label>',
			checked($vis,true,false), esc_html__('Show this item on the site','jellopoint-restaurant-menu'));
		echo '</td></tr>';

		echo '<tr><th><label>'.esc_html__('Badge Text','jellopoint-restaurant-menu').'</label></th><td>';
		printf('<input type="text" name="jprm_badge" value="%s" class="regular-text" placeholder="%s" />',
			esc_attr($badge), esc_attr__('e.g. Chef’s choice','jellopoint-restaurant-menu'));
		echo '</td></tr></tbody></table>';
	}

	/* --------------------------------- save -------------------------------- */
	public static function save($post_id,$post){
		if ( ! isset($_POST['jprm_meta_nonce']) || ! wp_verify_nonce($_POST['jprm_meta_nonce'],'jprm_meta') ) return;
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
		if ( ! current_user_can('edit_post',$post_id) ) return;

		update_post_meta($post_id,'jprm_desc',  wp_kses_post($_POST['jprm_desc'] ?? ''));
		update_post_meta($post_id,'jprm_badge', sanitize_text_field($_POST['jprm_badge'] ?? ''));
		update_post_meta($post_id,'jprm_visible', (isset($_POST['jprm_visible']) && $_POST['jprm_visible']==='yes')?'yes':'no');

		$mode = (($_POST['jprm_price_mode'] ?? '') === 'multi') ? 'multi' : 'single';
		update_post_meta($post_id,'jprm_price_mode',$mode);

		if ($mode === 'single'){
			$amount = sanitize_text_field($_POST['jprm_price_amount'] ?? '');
			$lm     = (($_POST['jprm_price_label_mode'] ?? '') === 'custom') ? 'custom' : 'ref';
			$lref   = sanitize_text_field($_POST['jprm_price_label_ref'] ?? '');
			$lcc    = sanitize_text_field($_POST['jprm_price_label_custom'] ?? '');
			$ico    = isset($_POST['jprm_price_label_icon_id']) ? (int) $_POST['jprm_price_label_icon_id'] : 0;

			update_post_meta($post_id,'jprm_price_amount', $amount);
			update_post_meta($post_id,'jprm_price_label_mode', $lm);
			update_post_meta($post_id,'jprm_price_label_ref',  $lref);
			update_post_meta($post_id,'jprm_price_label_custom',$lcc);
			update_post_meta($post_id,'jprm_price_label_icon_id',$ico);

			$cfg = [
				'mode'      => 'single',
				'price'     => $amount,
				'label_ref' => ($lm === 'ref') ? $lref : $lcc,
				'hide_icon' => false,
			];
			if ($ico > 0) $cfg['icon_id'] = $ico;

			update_post_meta($post_id,'jprm_price', wp_json_encode($cfg));

			// clean multi
			delete_post_meta($post_id,'jprm_prices');

		}else{
			$rows = [];
			$in = isset($_POST['jprm_rows']) && is_array($_POST['jprm_rows']) ? $_POST['jprm_rows'] : [];
			foreach ($in as $r){
				$en  = ! empty($r['enabled']);
				$lmd = (isset($r['label_mode']) && $r['label_mode'] === 'custom') ? 'custom' : 'ref';
				$lrf = sanitize_text_field($r['label_ref'] ?? '');
				$lcc = sanitize_text_field($r['label_custom'] ?? '');
				$ico = isset($r['icon_id']) ? (int)$r['icon_id'] : 0;
				$amt = sanitize_text_field($r['amount'] ?? '');
				$hid = ! empty($r['hide_icon']);
				if ( ! $en ) continue;

				$rows[] = [
					'enabled'      => true,
					'label_mode'   => $lmd,
					'label_ref'    => $lmd === 'ref' ? $lrf : '',
					'label_custom' => $lmd === 'custom' ? $lcc : '',
					'icon_id'      => $ico,
					'amount'       => $amt,
					'hide_icon'    => $hid,
				];
			}
			update_post_meta($post_id,'jprm_prices', wp_json_encode($rows));

			// normalized v3-like
			$rows_norm = [];
			foreach ($rows as $r){
				$ref = $r['label_mode'] === 'ref' ? $r['label_ref'] : ($r['label_custom'] ?? '');
				$item = [
					'label_ref' => (string)$ref,
					'value'     => (string)($r['amount'] ?? ''),
					'hide_icon' => ! empty($r['hide_icon']),
				];
				if ( ! empty($r['icon_id']) ) $item['icon_id'] = (int)$r['icon_id'];
				$rows_norm[] = $item;
			}
			update_post_meta($post_id,'jprm_price', wp_json_encode([
				'mode' => 'multi',
				'rows' => $rows_norm,
			]));

			// clean single
			delete_post_meta($post_id,'jprm_price_amount');
			delete_post_meta($post_id,'jprm_price_label_mode');
			delete_post_meta($post_id,'jprm_price_label_ref');
			delete_post_meta($post_id,'jprm_price_label_custom');
			delete_post_meta($post_id,'jprm_price_label_icon_id');
		}
	}
}}
JPRM_Admin_MenuItem_Meta::init();
