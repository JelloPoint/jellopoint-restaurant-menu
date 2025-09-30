/**
 * JelloPoint Menu Item Admin — minimal & robust
 * - Mode toggle (single/multi)
 * - Populate Predefined labels
 * - Icon preview for Single:
 *    - Predefined: show label icon
 *    - Custom: upload/remove + preview
 */
(function($){
  // Guard
  if (!window.JPRM_META) return;

  var LABELS = Array.isArray(JPRM_META.labels) ? JPRM_META.labels : [];
  var LMAP = {};
  for (var i=0;i<LABELS.length;i++){
    var L = LABELS[i] || {};
    LMAP[String(L.id || '')] = L;
  }

  function buildLabelOptionsHTML(){
    var html = '<option value="">' + ((JPRM_META.i18n && JPRM_META.i18n.select) || 'Select…') + '</option>';
    for (var i=0;i<LABELS.length;i++){
      var L = LABELS[i] || {};
      html += '<option value="'+ String(L.id||'') +'">'+ (L.label||'') +'</option>';
    }
    return html;
  }

  /* ---------- MODE TOGGLE ---------- */
  function setModeUI(){
    var mode = $('input[name="jprm_price_mode"]:checked').val() || 'single';
    if (mode === 'single'){
      $('.jprm-block-single').show();
      $('.jprm-block-multi').hide();
    } else {
      $('.jprm-block-single').hide();
      $('.jprm-block-multi').show();
    }
  }

  /* ---------- SINGLE: LABEL + ICON ---------- */
  var mediaFrame = null;
  function ensureFrame(){
    if (mediaFrame) return mediaFrame;
    mediaFrame = wp.media({
      title: (JPRM_META.i18n && JPRM_META.i18n.selectIcon) || 'Select Icon',
      multiple: false,
      library: { type: 'image' },
      button: { text: (JPRM_META.i18n && JPRM_META.i18n.selectIcon) || 'Select Icon' }
    });
    mediaFrame.on('select', function(){
      var file = mediaFrame.state().get('selection').first();
      if (!file) return;
      var id  = file.get('id');
      var url = (file.get('sizes') && file.get('sizes').thumbnail && file.get('sizes').thumbnail.url) || file.get('url');
      $('#jprm_price_label_icon_id').val(String(id));
      $('#jprm_single_icon_preview').html('<img src="'+url+'" style="max-width:32px;height:auto;" alt="" />');
      $('.jprm-single-icon-clear').show();
    });
    return mediaFrame;
  }

  function refreshSingleIcon(){
    var mode = $('#jprm_price_label_mode').val();
    if (mode === 'custom'){
      // custom = show actions; keep preview from hidden id (if any)
      $('#jprm_single_icon_actions').show();
      var has = $('#jprm_price_label_icon_id').val();
      $('.jprm-single-icon-clear').toggle(!!has && has !== '0' && has !== '');
    } else {
      // predefined = show label icon; hide actions
      $('#jprm_single_icon_actions').hide();
      var ref = $('#jprm_price_label_ref').val() || '';
      var L   = LMAP[ref];
      var url = (L && L.icon_url) ? L.icon_url : '';
      if (url){
        $('#jprm_single_icon_preview').html('<img src="'+url+'" style="max-width:32px;height:auto;" alt="" />');
      } else {
        $('#jprm_single_icon_preview').empty();
      }
    }
  }

  function toggleSingleControls(){
    var mode = $('#jprm_price_label_mode').val();
    if (mode === 'custom'){
      $('#jprm_price_label_custom').show();
      $('#jprm_price_label_ref').hide();
    } else {
      $('#jprm_price_label_custom').hide();
      $('#jprm_price_label_ref').show();
    }
    refreshSingleIcon();
  }

  function initSingle(){
    // Build the Predefined dropdown
    var $ref = $('#jprm_price_label_ref');
    if ($ref.length){
      var current = $ref.data('current') || '';
      $ref.html( buildLabelOptionsHTML() );
      if (current){ $ref.val(String(current)); }
    }

    // Handlers (unbind first to avoid duplicates)
    $('input[name="jprm_price_mode"]').off('.jprm').on('change.jprm', setModeUI);
    $('#jprm_price_label_mode').off('.jprm').on('change.jprm', toggleSingleControls);
    $('#jprm_price_label_ref').off('.jprm').on('change.jprm', refreshSingleIcon);

    $(document).off('.jprmIconSel').on('click.jprmIconSel', '.jprm-single-icon-select', function(e){
      e.preventDefault(); ensureFrame().open();
    });
    $(document).off('.jprmIconClr').on('click.jprmIconClr', '.jprm-single-icon-clear', function(e){
      e.preventDefault();
      $('#jprm_price_label_icon_id').val('0');
      $('#jprm_single_icon_preview').empty();
      $(this).hide();
    });

    // Initial state:
    setModeUI();
    toggleSingleControls();

    // If PHP pre-rendered a custom icon, ensure the clear button is visible
    if (JPRM_META.single && JPRM_META.single.custom_icon_url && $('#jprm_price_label_mode').val() === 'custom'){
      $('.jprm-single-icon-clear').show();
    }
  }

  /* ---------- MULTIPLE (unchanged wiring) ---------- */
  function rowToObj($tr){
    return {
      enabled:     $tr.find('input.enable').is(':checked'),
      label_mode:  $tr.find('select.label-mode').val() === 'custom' ? 'custom' : 'ref',
      label_ref:   $tr.find('select.label-ref').val() || '',
      label_custom:$tr.find('input.label-custom').val() || '',
      amount:      $tr.find('input.amount').val() || '',
      hide_icon:   $tr.find('input.hide-icon').is(':checked')
    };
  }
  function collectMulti(){
    var out = [];
    $('#jprm-prices-table tbody tr').each(function(){
      out.push( rowToObj($(this)) );
    });
    $('#jprm_prices').val( JSON.stringify(out) );
  }
  function syncRowUI($tr){
    var lm = $tr.find('select.label-mode').val();
    if (lm === 'custom'){ $tr.find('input.label-custom').show(); $tr.find('select.label-ref').hide(); }
    else { $tr.find('input.label-custom').hide(); $tr.find('select.label-ref').show(); }
  }
  function buildRowSelect($sel, current){
    $sel.html( buildLabelOptionsHTML() );
    if (current){ $sel.val(String(current)); }
  }
  function initMulti(){
    var $tb = $('#jprm-prices-table tbody');
    if (!$tb.length) return;

    $tb.find('select.label-ref').each(function(){
      buildRowSelect($(this), $(this).data('current') || '');
    });
    $tb.find('tr').each(function(){ syncRowUI($(this)); });

    $tb.off('.jprm').on('change.jprm', 'input,select', collectMulti);
    $tb.on('click.jprm', '.jprm-row-remove', function(e){ e.preventDefault(); $(this).closest('tr').remove(); collectMulti(); });

    $('#jprm-row-add').off('.jprm').on('click.jprm', function(e){
      e.preventDefault();
      var $tr = $('<tr/>');
      $tr.append('<td><input type="checkbox" class="enable" checked /></td>');
      $tr.append('<td class="label-td"><select class="label-mode"><option value="ref">'+((JPRM_META.i18n&&JPRM_META.i18n.predefined)||'Predefined')+'</option><option value="custom">'+((JPRM_META.i18n&&JPRM_META.i18n.custom)||'Custom')+'</option></select> <select class="label-ref"></select> <input type="text" class="label-custom regular-text" value="" placeholder="'+((JPRM_META.i18n&&JPRM_META.i18n.custom)||'Custom')+'" /></td>');
      $tr.append('<td><input type="text" class="amount regular-text" value="" placeholder="€ 7,50" /></td>');
      $tr.append('<td><input type="checkbox" class="hide-icon" /></td>');
      $tr.append('<td><a href="#" class="button button-secondary jprm-row-remove">'+((JPRM_META.i18n&&JPRM_META.i18n.remove)||'Remove')+'</a></td>');
      $('#jprm-prices-table tbody').append($tr);
      buildRowSelect($tr.find('select.label-ref'), '');
      syncRowUI($tr);
      collectMulti();
    });

    collectMulti();
  }

  /* ---------- BOOT ---------- */
  $(function(){
    try { initSingle(); } catch(e){ console && console.warn && console.warn('JPRM single init', e); }
    try { initMulti();  } catch(e){ console && console.warn && console.warn('JPRM multi init', e); }
  });

})(jQuery);
