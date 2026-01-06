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
		foreach (['jprm-menu-item-meta','jprm_admin_menuitem','jprm-admin','jprm-metabox'] as $h){
			if (wp_script_is($h,'enqueued'))   wp_dequeue_script($h);
			if (wp_script_is($h,'registered')) wp_deregister_script($h);
		}
	}

	public static function register_metaboxes(){
		// remove any older box
		remove_meta_box('jprm_menu_item_settings','jprm_menu_item','normal');

		add_meta_box('jprm_item_desc',  __('Description','jellopoint-restaurant-menu'), [__CLASS__,'render_desc'],     'jprm_menu_item', 'normal', 'high');
		add_meta_box('jprm_price_meta', __('Pricing','jellopoint-restaurant-menu'),     [__CLASS__,'render_pricing'],  'jprm_menu_item', 'normal', 'default');
		add_meta_box('jprm_item_vis',   __('Visibility','jellopoint-restaurant-menu'), [__CLASS__,'render_visibility'],'jprm_menu_item', 'normal', 'low');
	}

	/* ------------------------------- sections ------------------------------ */
	public static function render_desc($post){
		wp_nonce_field('jprm_meta','jprm_meta_nonce');

		$desc   = get_post_meta($post->ID,'jprm_desc',true);
		$format = (string) get_post_meta($post->ID,'jprm_desc_format',true);

		// Backwards compatible default: formatted/html
		if ( $format !== 'plain' && $format !== 'formatted' ) {
			$format = 'formatted';
		}

		echo '<table class="form-table"><tbody>';

		// Format toggle
		echo '<tr><th style="width:140px;"><label>'.esc_html__('Description Mode','jellopoint-restaurant-menu').'</label></th><td>';
			echo '<fieldset>';
				echo '<label style="margin-right:14px;">';
					echo '<input type="radio" name="jprm_desc_format" value="formatted" '.checked($format,'formatted',false).' /> ';
					echo esc_html__('Formatted (HTML)','jellopoint-restaurant-menu');
				echo '</label>';
				echo '<label>';
					echo '<input type="radio" name="jprm_desc_format" value="plain" '.checked($format,'plain',false).' /> ';
					echo esc_html__('Plain text','jellopoint-restaurant-menu');
				echo '</label>';
				echo '<p class="description" style="margin-top:6px;">';
					echo esc_html__('Formatted allows basic HTML like <br>, <strong>, <em>, lists and links. Plain text will display exactly as typed (HTML will not render).','jellopoint-restaurant-menu');
				echo '</p>';
			echo '</fieldset>';
		echo '</td></tr>';

		// Description textarea
		echo '<tr><th style="width:140px;"><label for="jprm_desc">'.esc_html__('Short Description','jellopoint-restaurant-menu').'</label></th><td>';
		printf('<textarea id="jprm_desc" name="jprm_desc" rows="3" style="width:100%%;">%s</textarea>', esc_textarea($desc));
		echo '</td></tr></tbody></table>';
	}

	public static function render_pricing($post){
		/* --- single price fields --- */
		$mode   = get_post_meta($post->ID,'jprm_price_mode',true) ?: 'single';
		$amount = get_post_meta($post->ID,'jprm_price_amount',true);

		$lm   = get_post_meta($post->ID,'jprm_price_label_mode',true) ?: 'ref';
		$lref = (string)get_post_meta($post->ID,'jprm_price_label_ref',true);
		$lcus = get_post_meta($post->ID,'jprm_price_label_custom',true);
		$icon = (int)get_post_meta($post->ID,'jprm_price_label_icon_id',true);

		/* --- multi price rows --- */
		$rows = get_post_meta($post->ID,'jprm_prices',true);
		if (is_string($rows) && $rows!=='') $rows = json_decode($rows,true);
		if (!is_array($rows)) $rows = [];

		/* --- labels store (predefined) --- */
		$labels = class_exists('JPRM_Labels_Store') ? JPRM_Labels_Store::all() : [];
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
		$single_opts = '<option value="">'.esc_html__('Select…','jellopoint-restaurant-menu').'</option>';
		$predef_url = '';
		foreach ($label_map as $id=>$info){
			$text=$info['text']?:$id; $iurl=$info['icon_id']?$icon_url($info['icon_id']):''; $sel=selected($lref,$id,false);
			$single_opts.='<option value="'.esc_attr($id).'" '.$sel.' data-icon="'.esc_attr($iurl).'">'.esc_html($text).'</option>';
			if ($lm==='ref' && $lref===$id) $predef_url=$iurl;
		}
		$custom_url = $icon?$icon_url($icon):'';
		$initial_url = ($lm==='ref') ? $predef_url : ($custom_url?:'');

		/* --- styles --- */
		echo '<style>
		/* Give the table more room: shrink left labels column */
		#jprm_price_meta .form-table th { width: 120px; }

		.jprm-inline{display:inline-flex;gap:8px;align-items:center;flex-wrap:nowrap}
		.jprm-inline select{max-width:220px}.jprm-inline input[type=text]{max-width:160px}
		.jprm-icon-ph{width:32px;height:32px;border:1px dashed #ccd0d4;border-radius:3px;display:inline-flex;align-items:center;justify-content:center;color:#777;background:#fff}
		.jprm-mode-switch{display:inline-flex;border:1px solid #ccd0d4;border-radius:4px;overflow:hidden}
		.jprm-pill{padding:2px 8px;cursor:pointer;background:#f6f7f7;border-right:1px solid #ccd0d4;user-select:none}
		.jprm-pill:last-child{border-right:none}.jprm-pill.active{background:#2271b1;color:#fff}

		/* TABLE: responsive (auto) with stable columns via <colgroup> */
		#jprm2-prices-wrap{overflow-x:auto}
		#jprm2-prices-table{table-layout:auto;width:100%;border-collapse:collapse}
		#jprm2-prices-table th,#jprm2-prices-table td{vertical-align:middle;box-sizing:border-box}

		/* Label cell must not spill into Amount */
		#jprm2-prices-table td.label-td{overflow:hidden;position:relative;min-width:0}
		.label-td .label-row{display:flex;align-items:center;gap:8px;flex-wrap:nowrap;min-width:0}
		.label-td .jprm-mode-switch{flex:0 0 auto}
		.label-td .inline-field{display:inline-flex;gap:8px;align-items:center;flex:1 1 auto;min-width:0;overflow:hidden}
		.label-td .inline-field select.label-ref{max-width:160px;flex:0 0 auto}
		.label-td .inline-field input.label-custom{width:120px;max-width:140px;flex:0 0 auto}

		/* Amount input steady */
		#jprm2-prices-table input.amount{width:8em!important}

		/* Icon inline */
		#jprm2-prices-table td.jprm-icon-cell{vertical-align:middle}
		.jprm-icon-inline{display:inline-flex;align-items:center;gap:8px}
		#jprm2-prices-table .jprm-row-icon-clear{margin:0}

		/* Mode visibility */
		.label-td[data-mode="ref"]  input.label-custom{display:none!important}
		.label-td[data-mode="custom"] select.label-ref{display:none!important}
		</style>';

		/* --- markup --- */
		echo '<table class="form-table"><tbody>';

		// Mode
		echo '<tr><th><label>'.esc_html__('Price Mode','jellopoint-restaurant-menu').'</label></th><td>';
		echo '<label><input type="radio" name="jprm_price_mode" value="single" '.checked($mode,'single',false).' /> '.esc_html__('Single Price','jellopoint-restaurant-menu').'</label> &nbsp; ';
		echo '<label><input type="radio" name="jprm_price_mode" value="multi" '.checked($mode,'multi',false).' /> '.esc_html__('Multiple Prices','jellopoint-restaurant-menu').'</label>';
		echo '</td></tr>';

		// Single - amount
		echo '<tr class="jprm-block-single"><th><label>'.esc_html__('Price','jellopoint-restaurant-menu').'</label></th><td>';
		printf('<input type="text" name="jprm_price_amount" value="%s" class="regular-text" style="width:110px" placeholder="%s" />',
			esc_attr($amount), esc_attr('€ 7,50'));
		echo '</td></tr>';

		// Single - label + icon
		echo '<tr class="jprm-block-single"><th><label>'.esc_html__('Price Label','jellopoint-restaurant-menu').'</label></th><td>';
		echo '<div class="jprm-inline">';
			echo '<select id="jprm_price_label_mode" name="jprm_price_label_mode" style="display:none;"><option value="ref" '.selected($lm,'ref',false).'>ref</option><option value="custom" '.selected($lm,'custom',false).'>custom</option></select>';
			echo '<div class="jprm-mode-switch" id="jprm_single_mode_switch"><span class="jprm-pill '.($lm==='ref'?'active':'').'" data-mode="ref">'.esc_html__('Preset','jellopoint-restaurant-menu').'</span><span class="jprm-pill '.($lm==='custom'?'active':'').'" data-mode="custom">'.esc_html__('Custom','jellopoint-restaurant-menu').'</span></div>';
			echo '<select id="jprm_price_label_ref" name="jprm_price_label_ref">'.$single_opts.'</select> ';
			printf('<input type="text" id="jprm_price_label_custom" name="jprm_price_label_custom" value="%s" class="regular-text" placeholder="%s" %s />',
				esc_attr($lcus), esc_attr__('Custom label','jellopoint-restaurant-menu'), $lm==='custom'?'':'style="display:none;"');
			echo '<div id="jprm_single_icon_preview" title="'.esc_attr__('Change icon','jellopoint-restaurant-menu').'" style="cursor:pointer;">';
				if ($initial_url) echo '<img src="'.esc_url($initial_url).'" width="32" />'; else echo '<span class="jprm-icon-ph"><span class="dashicons dashicons-format-image"></span></span>';
			echo '</div>';
			printf('<input type="hidden" id="jprm_price_label_icon_id" name="jprm_price_label_icon_id" value="%d" data-url="%s" />', $icon, esc_attr($custom_url));
		echo '</div>';
		echo '</td></tr>';

		// Multi table
		echo '<tr class="jprm-block-multi"><th>'.esc_html__('Multiple Prices','jellopoint-restaurant-menu').'</th><td>';
		echo '<div id="jprm2-prices-wrap">';
		?>
		<table class="widefat fixed striped" id="jprm2-prices-table">
			<colgroup>
				<col style="width:32px"><!-- enable -->
				<col><!-- label flexes -->
				<col style="width:8.5em"><!-- amount -->
				<col style="width:80px"><!-- icon -->
				<col style="width:56px"><!-- hide -->
				<col style="width:56px"><!-- actions -->
			</colgroup>
			<thead>
			<tr>
				<th></th>
				<th class="label-cell"><?php echo esc_html__('Label','jellopoint-restaurant-menu'); ?></th>
				<th><?php echo esc_html__('Amount','jellopoint-restaurant-menu'); ?></th>
				<th><?php echo esc_html__('Icon','jellopoint-restaurant-menu'); ?></th>
				<th><?php echo esc_html__('Hide','jellopoint-restaurant-menu'); ?></th>
				<th></th>
			</tr>
			</thead>
			<tbody>
			<?php
			foreach ($rows as $r){
				$en   = array_key_exists('enabled',$r) ? !empty($r['enabled']) : true;
				$lmd  = (($r['label_mode'] ?? 'ref') === 'custom') ? 'custom' : 'ref';
				$lrf  = (string)($r['label_ref'] ?? '');
				$lct  = (string)($r['label_custom'] ?? '');
				$amt  = (string)($r['amount'] ?? '');
				$hide = !empty($r['hide_icon']);
				$rid  = isset($r['icon_id']) ? (int)$r['icon_id'] : 0;

				$row_opts = '<option value="">'.esc_html__('Select…','jellopoint-restaurant-menu').'</option>';
				$pred_url = '';
				foreach ($label_map as $id=>$info){
					$text=$info['text']?:$id; $iurl=$info['icon_id']?$icon_url($info['icon_id']):''; $sel=selected($lrf,$id,false);
					$row_opts.='<option value="'.esc_attr($id).'" '.$sel.' data-icon="'.esc_attr($iurl).'">'.esc_html($text).'</option>';
					if ($lmd==='ref' && $lrf===$id) $pred_url=$iurl;
				}
				$cust_url = $rid ? $icon_url($rid) : '';
				?>
				<tr>
					<td><input type="checkbox" class="enable" <?php checked($en,true); ?> /></td>
					<td class="label-td" data-mode="<?php echo esc_attr($lmd); ?>">
						<div class="label-row">
							<select class="label-mode" style="display:none;">
								<option value="ref"    <?php selected($lmd,'ref'); ?>>ref</option>
								<option value="custom" <?php selected($lmd,'custom'); ?>>custom</option>
							</select>
							<div class="jprm-mode-switch">
								<span class="jprm-pill <?php echo $lmd==='ref'?'active':''; ?>" data-mode="ref"><?php echo esc_html__('Preset','jellopoint-restaurant-menu'); ?></span>
								<span class="jprm-pill <?php echo $lmd==='custom'?'active':''; ?>" data-mode="custom"><?php echo esc_html__('Custom','jellopoint-restaurant-menu'); ?></span>
							</div>
							<span class="inline-field">
								<select class="label-ref"><?php echo $row_opts; ?></select>
								<input type="text" class="label-custom" value="<?php echo esc_attr($lct); ?>" placeholder="<?php echo esc_attr__('Custom label','jellopoint-restaurant-menu'); ?>" />
							</span>
						</div>
					</td>
					<td><input type="text" class="amount" value="<?php echo esc_attr($amt); ?>" placeholder="€ 7,50" /></td>
					<td class="jprm-icon-cell">
						<div class="jprm-icon-inline">
							<div class="jprm-row-icon-preview" title="<?php echo esc_attr__('Change icon','jellopoint-restaurant-menu'); ?>" style="cursor:pointer;">
								<?php if ($lmd==='ref' && $pred_url): ?>
									<img src="<?php echo esc_url($pred_url); ?>" width="24" />
								<?php elseif ($lmd==='custom' && $cust_url): ?>
									<img src="<?php echo esc_url($cust_url); ?>" width="24" />
								<?php else: ?>
									<span class="jprm-icon-ph-sm"><span class="dashicons dashicons-format-image"></span></span>
								<?php endif; ?>
							</div>
							<button type="button" class="button-link jprm-row-icon-clear" aria-label="<?php echo esc_attr__('Remove Icon','jellopoint-restaurant-menu'); ?>" <?php echo ($lmd==='custom' && $rid) ? '' : 'style="display:none;"'; ?>><span class="dashicons dashicons-no-alt"></span></button>
						</div>
						<input type="hidden" class="icon-id" value="<?php echo esc_attr($rid); ?>" data-url="<?php echo esc_attr($cust_url); ?>" />
					</td>
					<td style="text-align:center;"><input type="checkbox" class="hide-icon" <?php checked($hide,true); ?> /></td>
					<td style="text-align:center;"><button type="button" class="button-link jprm-row-remove" aria-label="<?php echo esc_html__('Remove row','jellopoint-restaurant-menu'); ?>"><span class="dashicons dashicons-trash"></span></button></td>
				</tr>
				<?php
			} ?>
			</tbody>
		</table>
		<?php
		echo '</div>';
		echo '<p><a href="#" class="button" id="jprm2-row-add">'.esc_html__('Add another price','jellopoint-restaurant-menu').'</a></p>';
		echo '<input type="hidden" id="jprm2_prices" name="jprm_prices" value="'.esc_attr(json_encode($rows)).'" />';
		echo '</td></tr></tbody></table>';

		$js_data = [
			'singleOptionsHTML' => $single_opts,
			'i18n' => [
				'preset'     => __('Preset','jellopoint-restaurant-menu'),
				'custom'     => __('Custom','jellopoint-restaurant-menu'),
				'custom_lbl' => __('Custom label','jellopoint-restaurant-menu'),
				'selectIcon' => __('Select Icon','jellopoint-restaurant-menu'),
				'removeIcon' => __('Remove Icon','jellopoint-restaurant-menu'),
				'pricePh'    => '€ 7,50',
			],
		];
		echo '<script id="jprm-meta-data" type="application/json">'.wp_json_encode($js_data).'</script>'; ?>
		<script>
		(function($){
		  if (window.__JPRM2_BOUND__) return; window.__JPRM2_BOUND__=true;
		  var NS='.jprm2', CFG={}; try{CFG=JSON.parse($('#jprm-meta-data').text()||'{}')}catch(e){CFG={};}

		  function setMode(){
			var m=$('input[name="jprm_price_mode"]:checked').val()||'single';
			if(m==='multi'){$('.jprm-block-single').hide();$('.jprm-block-multi').show();}
			else{$('.jprm-block-single').show();$('.jprm-block-multi').hide();}
		  }
		  $('input[name="jprm_price_mode"]').off('change'+NS).on('change'+NS,setMode); setMode();

		  function setSingleMode(mode){
			$('#jprm_price_label_mode').val(mode);
			$('#jprm_single_mode_switch .jprm-pill').removeClass('active');
			$('#jprm_single_mode_switch .jprm-pill[data-mode="'+mode+'"]').addClass('active');
			if(mode==='custom'){ $('#jprm_price_label_custom').show(); $('#jprm_price_label_ref').hide(); }
			else{ $('#jprm_price_label_custom').hide(); $('#jprm_price_label_ref').show(); }
			refreshSingleIcon();
		  }
		  function refreshSingleIcon(){
			var mode=$('#jprm_price_label_mode').val();
			if(mode==='custom'){
			  var id=$('#jprm_price_label_icon_id').val(), url=$('#jprm_price_label_icon_id').data('url')||'';
			  if(id&&id!=='0'&&url) $('#jprm_single_icon_preview').html('<img src="'+url+'" width="32" />');
			  else $('#jprm_single_icon_preview').html('<span class="jprm-icon-ph"><span class="dashicons dashicons-format-image"></span></span>');
			}else{
			  var $o=$('#jprm_price_label_ref').find('option:selected'), url=$o.data('icon')||'';
			  if(url) $('#jprm_single_icon_preview').html('<img src="'+url+'" width="32" />'); else $('#jprm_single_icon_preview').html('');
			}
		  }
		  $('#jprm_single_mode_switch .jprm-pill').off('click'+NS).on('click'+NS,function(){setSingleMode($(this).data('mode'));});
		  $('#jprm_price_label_ref').off('change'+NS).on('change'+NS,refreshSingleIcon);
		  setSingleMode($('#jprm_price_label_mode').val());

		  // MULTI rows
		  function norm(v){return(v==null?'':String(v)).trim();}
		  function rowObj($tr){
			return {
			  enabled:$tr.find('input.enable').is(':checked'),
			  label_mode:($tr.find('select.label-mode').val()==='custom')?'custom':'ref',
			  label_ref:norm($tr.find('select.label-ref').val()),
			  label_custom:norm($tr.find('input.label-custom').val()),
			  icon_id:parseInt(($tr.find('input.icon-id').val()||'0'),10)||0,
			  amount:norm($tr.find('input.amount').val()),
			  hide_icon:$tr.find('input.hide-icon').is(':checked')
			};
		  }
		  function collect(){
			var out=[]; $('#jprm2-prices-table tbody tr').each(function(){out.push(rowObj($(this)));});
			$('#jprm2_prices').val(JSON.stringify(out));
		  }

		  function setRowMode($tr,mode){
			$tr.find('td.label-td').attr('data-mode', mode);
			$tr.find('select.label-mode').val(mode);
			$tr.find('.jprm-pill').removeClass('active');
			$tr.find('.jprm-pill[data-mode="'+mode+'"]').addClass('active');

			if(mode==='custom'){
			  $tr.find('input.label-custom').show();
			  $tr.find('select.label-ref').hide();
			  var id=$tr.find('input.icon-id').val(), url=$tr.find('input.icon-id').data('url')||'';
			  if(id&&id!=='0'&&url){
				$tr.find('.jprm-row-icon-preview').html('<img src="'+url+'" width="24" />');
				$tr.find('.jprm-row-icon-clear').show();
			  } else {
				$tr.find('.jprm-row-icon-preview').html('<span class="jprm-icon-ph-sm"><span class="dashicons dashicons-format-image"></span></span>');
				$tr.find('.jprm-row-icon-clear').hide();
			  }
			} else {
			  $tr.find('input.label-custom').hide();
			  $tr.find('select.label-ref').show();
			  var $o=$tr.find('select.label-ref option:selected'), url=$o.data('icon')||'';
			  if(url) $tr.find('.jprm-row-icon-preview').html('<img src="'+url+'" width="24" />');
			  else $tr.find('.jprm-row-icon-preview').html('');
			  $tr.find('.jprm-row-icon-clear').hide();
			}
			collect();
		  }
		  function attachRowHandlers($tr){
			$tr.off('click'+NS,'.jprm-pill').on('click'+NS,'.jprm-pill',function(){setRowMode($tr,$(this).data('mode'));});
			$tr.off('change'+NS,'select.label-ref').on('change'+NS,'select.label-ref',function(){
			  var url=$(this).find('option:selected').data('icon')||'';
			  if(url) $tr.find('.jprm-row-icon-preview').html('<img src="'+url+'" width="24" />'); else $tr.find('.jprm-row-icon-preview').html('');
			  collect();
			});
			$tr.off('change keyup'+NS,'input,select').on('change keyup'+NS,'input,select',collect);
		  }

		  var $tb=$('#jprm2-prices-table tbody');
		  $tb.find('tr').each(function(){var $tr=$(this); attachRowHandlers($tr); setRowMode($tr,$tr.find('select.label-mode').val());});

		  // media frame for row icon
		  var rowFrame=null, activeRow=null;
		  function ensureRowFrame(){
			if(rowFrame) return rowFrame;
			rowFrame=wp.media({title:(CFG.i18n&&CFG.i18n.selectIcon)||'Select Icon',multiple:false,library:{type:'image'},button:{text:(CFG.i18n&&CFG.i18n.selectIcon)||'Select Icon'}});
			rowFrame.on('select',function(){
			  if(!activeRow) return;
			  var f=rowFrame.state().get('selection').first(); if(!f) return;
			  var id=f.get('id'), sizes=f.get('sizes')||{}, url=(sizes.thumbnail&&sizes.thumbnail.url)||f.get('url');
			  activeRow.find('input.icon-id').val(String(id)).attr('data-url',url||'');
			  activeRow.find('.jprm-row-icon-preview').html('<img src="'+(url||'')+'" width="24" />');
			  activeRow.find('.jprm-row-icon-clear').show();
			  collect(); activeRow=null;
			});
			return rowFrame;
		  }
		  $tb.off('click'+NS,'.jprm-row-icon-preview').on('click'+NS,'.jprm-row-icon-preview',function(e){
			var $tr=$(this).closest('tr'); if($tr.find('select.label-mode').val()!=='custom') return;
			e.preventDefault(); activeRow=$tr; ensureRowFrame().open();
		  });
		  $(document).off('click'+NS,'.jprm-row-icon-clear').on('click'+NS,'.jprm-row-icon-clear',function(e){
			e.preventDefault(); var $tr=$(this).closest('tr');
			$tr.find('input.icon-id').val('0').attr('data-url','');
			$tr.find('.jprm-row-icon-preview').html('<span class="jprm-icon-ph-sm"><span class="dashicons dashicons-format-image"></span></span>');
			$(this).hide(); collect();
		  });

		  // add row
		  $(document).off('click','#jprm2-row-add');
		  $('#jprm2-row-add').off('click').on('click'+NS,function(e){
			e.preventDefault();
			var $tr=$('<tr/>');

			$('<td/>').append($('<input/>',{type:'checkbox','class':'enable',checked:true})).appendTo($tr);

			var $tdL=$('<td/>',{'class':'label-td','data-mode':'ref'}).appendTo($tr);
			var $row=$('<div/>',{'class':'label-row'});
			var $modeSel=$('<select/>',{'class':'label-mode'}).css('display','none')
				.append($('<option/>',{value:'ref',text:'ref'}))
				.append($('<option/>',{value:'custom',text:'custom'}));
			var $pills=$('<div/>',{'class':'jprm-mode-switch'})
				.append($('<span/>',{'class':'jprm-pill active','data-mode':'ref',text:(CFG.i18n&&CFG.i18n.preset)||'Preset'}))
				.append($('<span/>',{'class':'jprm-pill','data-mode':'custom',text:(CFG.i18n&&CFG.i18n.custom)||'Custom'}));
			var $inline=$('<span/>',{'class':'inline-field'});
			var $ref=$('<select/>',{'class':'label-ref'}).html(CFG.singleOptionsHTML||'');
			var $cus=$('<input/>',{type:'text','class':'label-custom',placeholder:(CFG.i18n&&CFG.i18n.custom_lbl)||'Custom label'});
			$inline.append($ref).append($cus);
			$row.append($modeSel).append($pills).append($inline);
			$tdL.append($row);

			$('<td/>').append($('<input/>',{type:'text','class':'amount',placeholder:(CFG.i18n&&CFG.i18n.pricePh)||'€ 7,50'})).appendTo($tr);

			var $tdI=$('<td/>',{'class':'jprm-icon-cell'});
			var $wrap=$('<div/>',{'class':'jprm-icon-inline'});
			var $prev=$('<div/>',{'class':'jprm-row-icon-preview',title:(CFG.i18n&&CFG.i18n.selectIcon)||'Select Icon'})
						.append($('<span/>',{'class':'jprm-icon-ph-sm'}).append($('<span/>',{'class':'dashicons dashicons-format-image'})));
			var $clr =$('<button/>',{type:'button','class':'button-link jprm-row-icon-clear','aria-label':(CFG.i18n&&CFG.i18n.removeIcon)||'Remove Icon'})
						.css('display','none').append($('<span/>',{'class':'dashicons dashicons-no-alt'}));
			var $hid =$('<input/>',{type:'hidden','class':'icon-id',value:'0','data-url':''});
			$wrap.append($prev).append($clr); $tdI.append($wrap).append($hid).appendTo($tr);

			$('<td/>',{'style':'text-align:center;'}).append($('<input/>',{type:'checkbox','class':'hide-icon'})).appendTo($tr);
			$('<td/>',{'style':'text-align:center;'}).append($('<button/>',{type:'button','class':'button-link jprm-row-remove','aria-label':'Remove row'}).append($('<span/>',{'class':'dashicons dashicons-trash'}))).appendTo($tr);

			$('#jprm2-prices-table tbody').append($tr);
			collect();
		  });

		  // remove row
		  $tb.off('click'+NS,'.jprm-row-remove').on('click'+NS,'.jprm-row-remove',function(e){
			e.preventDefault(); $(this).closest('tr').remove(); collect();
		  });

		  collect();
		  $('#post').off('submit'+NS).on('submit'+NS,collect);
		})(jQuery);
		</script>
		<?php
	}

	public static function render_visibility($post){
		$badge = get_post_meta($post->ID,'jprm_badge',true);
		$vis   = (get_post_meta($post->ID,'jprm_visible',true)==='yes');

		echo '<table class="form-table"><tbody>';
		echo '<tr><th style="width:140px;"><label>'.esc_html__('Visible','jellopoint-restaurant-menu').'</label></th><td>';
		printf('<label><input type="checkbox" name="jprm_visible" value="yes" %s> %s</label>',
			checked($vis,true,false), esc_html__('Show this item on the site','jellopoint-restaurant-menu'));
		echo '</td></tr>';

		// echo '<tr><th><label>'.esc_html__('Badge Text','jellopoint-restaurant-menu').'</label></th><td>';
		// printf('<input type="text" name="jprm_badge" value="%s" class="regular-text" placeholder="%s" />',
		//	esc_attr($badge), esc_attr__('e.g. Chef’s choice','jellopoint-restaurant-menu'));
		 echo '</td></tr></tbody></table>';
	}

	/* --------------------------------- save -------------------------------- */
	public static function save($post_id,$post){
		if ( ! isset($_POST['jprm_meta_nonce']) || ! wp_verify_nonce($_POST['jprm_meta_nonce'],'jprm_meta') ) return;
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
		if ( ! current_user_can('edit_post',$post_id) ) return;

		// --- Description + mode ---
		$format = isset($_POST['jprm_desc_format']) ? sanitize_key($_POST['jprm_desc_format']) : 'formatted';
		if ( $format !== 'plain' && $format !== 'formatted' ) $format = 'formatted';
		update_post_meta($post_id,'jprm_desc_format', $format);

		$raw_desc = wp_unslash($_POST['jprm_desc'] ?? '');
		if ( $format === 'plain' ) {
			// Plain text: keep as text only; no HTML rendering on frontend.
			$clean = sanitize_textarea_field( $raw_desc );
		} else {
			// Formatted: allow safe HTML.
			$clean = wp_kses_post( $raw_desc );
		}
		update_post_meta($post_id,'jprm_desc', $clean);

		update_post_meta($post_id,'jprm_badge', sanitize_text_field($_POST['jprm_badge'] ?? ''));
		update_post_meta($post_id,'jprm_visible', (isset($_POST['jprm_visible']) && $_POST['jprm_visible']==='yes')?'yes':'no');

		$mode = (($_POST['jprm_price_mode'] ?? 'single')==='multi') ? 'multi' : 'single';
		update_post_meta($post_id,'jprm_price_mode',$mode);

		if ($mode==='single'){
			update_post_meta($post_id,'jprm_price_amount', sanitize_text_field($_POST['jprm_price_amount'] ?? ''));
			$lm=(($_POST['jprm_price_label_mode'] ?? 'ref')==='custom')?'custom':'ref';
			update_post_meta($post_id,'jprm_price_label_mode',$lm);
			if ($lm==='ref'){
				update_post_meta($post_id,'jprm_price_label_ref', sanitize_text_field($_POST['jprm_price_label_ref'] ?? ''));
				delete_post_meta($post_id,'jprm_price_label_custom');
				delete_post_meta($post_id,'jprm_price_label_icon_id');
			}else{
				update_post_meta($post_id,'jprm_price_label_custom', sanitize_text_field($_POST['jprm_price_label_custom'] ?? ''));
				update_post_meta($post_id,'jprm_price_label_icon_id', intval($_POST['jprm_price_label_icon_id'] ?? 0));
				delete_post_meta($post_id,'jprm_price_label_ref');
			}
			delete_post_meta($post_id,'jprm_prices');

		}else{
			$json = $_POST['jprm_prices'] ?? '[]';
			$rows = json_decode(wp_unslash($json),true);
			$out  = [];
			if (is_array($rows)){
				foreach ($rows as $r){
					if (!is_array($r)) continue;
					$out[] = [
						'enabled'      => !empty($r['enabled']),
						'label_mode'   => (($r['label_mode'] ?? 'ref')==='custom')?'custom':'ref',
						'label_ref'    => sanitize_text_field($r['label_ref'] ?? ''),
						'label_custom' => sanitize_text_field($r['label_custom'] ?? ''),
						'icon_id'      => intval($r['icon_id'] ?? 0),
						'amount'       => sanitize_text_field($r['amount'] ?? ''),
						'hide_icon'    => !empty($r['hide_icon']),
					];
				}
			}
			update_post_meta($post_id,'jprm_prices', wp_json_encode($out));

			// clear single fields
			delete_post_meta($post_id,'jprm_price_amount');
			delete_post_meta($post_id,'jprm_price_label_mode');
			delete_post_meta($post_id,'jprm_price_label_ref');
			delete_post_meta($post_id,'jprm_price_label_custom');
			delete_post_meta($post_id,'jprm_price_label_icon_id');
		}
	}
}}
JPRM_Admin_MenuItem_Meta::init();
