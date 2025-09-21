\
(function($){
    'use strict';

    function initMenuItemEditor(){
        // Targets common containers for prices or repeaters
        var $roots = $(
            '#jprm-prices, #jprm-multiprice-table, table[data-jprm="prices"], .jprm-sortable'
        );

        $roots.each(function(){
            var $root = $(this);
            var $tbody = $root.is('table') ? $root.find('tbody') : $root;
            if ($tbody.data('jprm-sorted')) return;

            try {
                $tbody.sortable({
                    handle: '.drag, .jprm-drag, .dashicons-move, .row-handle',
                    items: '> tr',
                    axis: 'y',
                    helper: function(e, ui){
                        ui.children().each(function(){ $(this).width($(this).width()); });
                        return ui;
                    },
                    stop: function(){
                        $tbody.children('tr').each(function(i){
                            $(this).find('input.order, input[name$="[order]"]').val(i);
                        });
                        $tbody.trigger('jprm:rows-reordered');
                    }
                });
                $tbody.data('jprm-sorted', true);
            } catch(e){ /* keep resilient */ }
        });

        // Buttons (delegate)
        var $doc = $(document);
        $doc.off('click.jprmAdd', '.jprm-add-row').on('click.jprmAdd', '.jprm-add-row', function(e){
            e.preventDefault();
            var $btn = $(this);
            var tmplSel = $btn.data('template') || '#jprm-price-row-template';
            var targetSel = $btn.data('target') || '#jprm-prices tbody, .jprm-sortable tbody';
            var $tmpl = $(tmplSel);
            var $target = $(targetSel).first();
            if ($tmpl.length && $target.length){
                var html = $tmpl.html();
                var uid = ('pl-' + Math.random().toString(36).slice(2,9));
                html = html.replace(/__ID__/g, uid);
                var $row = $(html);
                $target.append($row);
                $target.trigger('jprm:row-added', [$row]);
            }
        });

        $doc.off('click.jprmDup', '.jprm-dup-row').on('click.jprmDup', '.jprm-dup-row', function(e){
            e.preventDefault();
            var $row = $(this).closest('tr');
            var $clone = $row.clone(true, true);
            $clone.find('input,select,textarea').each(function(){
                if (this.name){
                    this.name = this.name.replace(/\[(\d+)\]/, function(_,n){ return '[' + (parseInt(n,10)+1) + ']'; });
                }
            });
            $row.after($clone);
            $row.parent().trigger('jprm:row-duplicated', [$clone]);
        });

        $doc.off('click.jprmDel', '.jprm-del-row').on('click.jprmDel', '.jprm-del-row', function(e){
            e.preventDefault();
            $(this).closest('tr').remove();
            $doc.trigger('jprm:row-deleted');
        });

        $doc.off('click.jprmMedia', '.jprm-select-media').on('click.jprmMedia', '.jprm-select-media', function(e){
            e.preventDefault();
            var $btn = $(this);
            var $preview = $btn.closest('td, .cell, .field').find('.jprm-icon-preview');
            var frame = wp.media({ multiple: false });
            frame.on('select', function(){
                var att = frame.state().get('selection').first().toJSON();
                $btn.prev('input[type="hidden"]').val(att.id).trigger('change');
                if ($preview.length){
                    $preview.empty().append($('<img/>',{src: att.url, alt: att.alt || ''}));
                }
            });
            frame.open();
        });
    }

    function initPriceLabels(){
        // Price Labels table specifics
        var $tb = $('#jprm-labels-table tbody');
        if (!$tb.length || $tb.data('jprm-sorted')) return;

        try {
            $tb.sortable({
                handle: '.drag, .jprm-drag, .dashicons-move, .row-handle',
                items: '> tr',
                axis: 'y',
                stop: function(){
                    $tb.children('tr').each(function(i){
                        $(this).find('input.order').val(i);
                    });
                    $tb.trigger('jprm:labels-reordered');
                }
            });
            $tb.data('jprm-sorted', true);
        } catch(e){}

        var $doc = $(document);
        // Icon select/remove
        $doc.off('click.jprmLblMedia', '.jprm-label-select').on('click.jprmLblMedia', '.jprm-label-select', function(e){
            e.preventDefault();
            var $btn = $(this);
            var $row = $btn.closest('tr');
            var $hid = $row.find('input.icon_id');
            var $prev = $row.find('.jprm-icon-preview');
            var frame = wp.media({ multiple:false });
            frame.on('select', function(){
                var a = frame.state().get('selection').first().toJSON();
                $hid.val(a.id).trigger('change');
                if ($prev.length){ $prev.empty().append($('<img/>',{src:a.url, alt: a.alt||''})); }
            });
            frame.open();
        });
        $doc.off('click.jprmLblRemove', '.jprm-label-remove').on('click.jprmLblRemove', '.jprm-label-remove', function(e){
            e.preventDefault();
            var $row = $(this).closest('tr');
            $row.find('input.icon_id').val('0').trigger('change');
            $row.find('.jprm-icon-preview').empty();
        });
    }

    function boot(){
        if (window.JPRM_ADMIN_CTX){
            if (JPRM_ADMIN_CTX.is_menu_item_editor){ initMenuItemEditor(); }
            if (JPRM_ADMIN_CTX.is_price_labels){ initPriceLabels(); }
        } else {
            // Fallback: try both
            initMenuItemEditor();
            initPriceLabels();
        }
    }

    $(document).on('ready', boot);
    $(document).on('ajaxComplete', boot);
})(jQuery);
