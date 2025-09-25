/**
 * Admin: Menu Item Meta behaviors
 */
(function($){
  if (!window.JPRM_META) return;
  var LABELS = Array.isArray(JPRM_META.labels) ? JPRM_META.labels : [];

  function buildLabelOptions(){
    var $sel = $('<select/>');
    $sel.append($('<option/>',{value:'', text:JPRM_META.i18n.select}));
    LABELS.forEach(function(L){
      $sel.append($('<option/>',{value:String(L.id||''), text:L.label||''}));
    });
    return $sel.html();
  }

  function syncMode(){
    var mode = $('input[name="jprm_price_mode"]:checked').val() || 'single';
    if (mode === 'single'){
      $('.jprm-block-single').show();
      $('.jprm-block-multi').hide();
    } else {
      $('.jprm-block-single').hide();
      $('.jprm-block-multi').show();
    }
  }

  function initSingle(){
    var $ref = $('#jprm_price_label_ref');
    if ($ref.length){
      var current = $ref.data('current') || '';
      $ref.empty().append( buildLabelOptions() );
      if (current){ $ref.val(String(current)); }
    }
    function sync(){
      var mode = $('#jprm_price_label_mode').val();
      if (mode === 'custom'){ $('#jprm_price_label_custom').show(); $('#jprm_price_label_ref').hide(); }
      else { $('#jprm_price_label_custom').hide(); $('#jprm_price_label_ref').show(); }
    }
    $('#jprm_price_label_mode').off('change.jprm').on('change.jprm', sync);
    sync();
  }

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
  function collect(){
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
    $sel.empty().append( buildLabelOptions() );
    if (current){ $sel.val(String(current)); }
  }
  function initRows(){
    var $tb = $('#jprm-prices-table tbody');
    $tb.find('select.label-ref').each(function(){
      var current = $(this).data('current') || '';
      buildRowSelect($(this), current);
    });
    $tb.find('tr').each(function(){ syncRowUI($(this)); });
    $tb.on('change', 'input,select', collect);
    $tb.on('click', '.jprm-row-remove', function(e){ e.preventDefault(); $(this).