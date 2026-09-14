(function($){
    'use strict';
    var strings=window.jprmPriceLabels||{};
    function esc(value){return $('<div>').text(value||'').html();}
    function uniqueId(){return 'lbl_'+Date.now().toString(36)+'_'+Math.random().toString(36).slice(2,7);}
    function renumber(){
        $('#jprm-labels-tbody tr').each(function(index){
            var $tr=$(this);
            $tr.find('input[name$="[order]"]').val(index);
            $tr.find('input, textarea, select').each(function(){
                var name=$(this).attr('name');
                if(name){$(this).attr('name',name.replace(/labels\[[^\]]+\]/,'labels['+index+']'));}
            });
        });
    }
    function iconPlaceholder(){return '<span class="dashicons dashicons-format-image" title="'+esc(strings.chooseIcon)+'"></span>';}
    function makeRow(){
        var idx=$('#jprm-labels-tbody tr').length,id=uniqueId();
        return $([
            '<tr class="jprm-row"><td class="col-drag"><span class="dashicons dashicons-menu jprm-drag" title="'+esc(strings.drag)+'"></span></td><td>',
            '<input type="text" class="regular-text" name="labels['+idx+'][label]" value="" />',
            '<input type="hidden" name="labels['+idx+'][id]" value="'+id+'" /><input type="hidden" name="labels['+idx+'][order]" value="'+idx+'" /><input type="hidden" name="labels['+idx+'][slug]" value="" /></td><td>',
            '<div class="jprm-icon-wrap"><span class="jprm-icon-preview" role="button" tabindex="0">'+iconPlaceholder()+'</span>',
            '<input type="hidden" name="labels['+idx+'][icon_id]" value="0" /><input type="hidden" name="labels['+idx+'][icon_url]" value="" />',
            '<button type="button" class="button jprm-icon-btn jprm-icon-clear" title="'+esc(strings.clearIcon)+'"><span class="dashicons dashicons-no"></span><span class="screen-reader-text">'+esc(strings.clear)+'</span></button></div></td>',
            '<td><label><input type="checkbox" name="labels['+idx+'][active]" value="1" checked /> '+esc(strings.active)+'</label></td>',
            '<td class="jprm-actions"><button type="button" class="button jprm-icon-btn jprm-row-delete" title="'+esc(strings.deleteRow)+'"><span class="dashicons dashicons-trash"></span><span class="screen-reader-text">'+esc(strings.delete)+'</span></button></td></tr>'
        ].join(''));
    }
    $(function(){
        $('#jprm-labels-tbody').sortable({handle:'.jprm-drag',placeholder:'placeholder',items:'> tr',update:renumber});
        $('#jprm-add-row').on('click',function(){$('#jprm-labels-tbody').append(makeRow());renumber();});
        var frame;
        $(document).on('click keypress','.jprm-icon-preview',function(e){
            if(e.type==='keypress'&&e.key!=='Enter'&&e.key!==' '){return;}
            var $wrap=$(this).closest('.jprm-icon-wrap'),$input=$wrap.find('input[name*="[icon_id]"]'),$urlInput=$wrap.find('input[name*="[icon_url]"]'),$preview=$wrap.find('.jprm-icon-preview');
            if(frame){frame.close();}
            frame=wp.media({title:strings.selectIcon,button:{text:strings.useIcon},library:{type:'image'},multiple:false});
            frame.on('select',function(){var attachment=frame.state().get('selection').first().toJSON(),url=(attachment.sizes&&attachment.sizes.thumbnail&&attachment.sizes.thumbnail.url)?attachment.sizes.thumbnail.url:(attachment.icon||attachment.url);$input.val(attachment.id);$urlInput.val('');$preview.empty().append($('<img>',{src:url,alt:''}));});
            frame.open();
        });
        $(document).on('click','.jprm-icon-clear',function(){var $wrap=$(this).closest('.jprm-icon-wrap');$wrap.find('input[name*="[icon_id]"]').val('0');$wrap.find('input[name*="[icon_url]"]').val('');$wrap.find('.jprm-icon-preview').html(iconPlaceholder());});
        $(document).on('click','.jprm-row-delete',function(){$(this).closest('tr').remove();renumber();});
        $('form').on('submit',function(){$('#jprm-labels-tbody tr').each(function(){var $tr=$(this),$slug=$tr.find('input[name$="[slug]"]');if(!$slug.val()){var label=($tr.find('input[name$="[label]"]').val()||'').toLowerCase().trim();$slug.val(label.normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,''));}});renumber();});
    });
})(jQuery);
