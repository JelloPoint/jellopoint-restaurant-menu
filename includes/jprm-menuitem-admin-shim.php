
<?php
// Safety: direct access
if ( ! defined('ABSPATH') ) { exit; }

/**
 * Admin UI shim for JelloPoint Restaurant Menu – Menu Item edit screen.
 * Scope: Enable sortable + common row buttons without affecting admin menus.
 * This file is intentionally decoupled from the main class to avoid regressions.
 */

add_action('admin_enqueue_scripts', function(){
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ( ! $screen || 'jprm_menu_item' !== $screen->post_type ) {
        return;
    }

    // Dependencies
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_media();

    $js = <<<'JS'
    (function($){
        function initSortable(context){
            var $tables = $(context).find([
                '#jprm-prices',
                '#jprm-multiprice-table',
                'table[data-jprm="prices"]',
                '.jprm-sortable'
            ].join(','));

            $tables.each(function(){
                var $t = $(this);
                var $tb = $t.is('table') ? $t.find('tbody') : $t;
                if ($tb.data('jprm-sorted')) return;
                try {
                    $tb.sortable({
                        handle: '.drag, .jprm-drag, .dashicons-move, .row-handle',
                        items: '> tr',
                        axis: 'y',
                        helper: function(e, ui){
                            ui.children().each(function(){ $(this).width($(this).width()); });
                            return ui;
                        },
                        stop: function(){
                            $tb.children('tr').each(function(i){
                                $(this).find('input.order, input[name$="[order]"]').val(i);
                            });
                            $tb.trigger('jprm:rows-reordered');
                        }
                    });
                    $tb.data('jprm-sorted', true);
                } catch(e){ /* resilient */ }
            });
        }

        function initButtons(context){
            var $ctx = $(context);
            $ctx.off('click.jprmAdd', '.jprm-add-row').on('click.jprmAdd', '.jprm-add-row', function(e){
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

            $ctx.off('click.jprmDup', '.jprm-dup-row').on('click.jprmDup', '.jprm-dup-row', function(e){
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

            $ctx.off('click.jprmDel', '.jprm-del-row').on('click.jprmDel', '.jprm-del-row', function(e){
                e.preventDefault();
                $(this).closest('tr').remove();
                $ctx.trigger('jprm:row-deleted');
            });

            $ctx.off('click.jprmMedia', '.jprm-select-media').on('click.jprmMedia', '.jprm-select-media', function(e){
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

        function boot(){
            initSortable(document);
            initButtons(document);
        }
        $(document).on('ready', boot);
        $(document).on('ajaxComplete', boot);
    })(jQuery);
    JS;

    wp_register_script( 'jprm-menuitem-admin-shim', false, [ 'jquery', 'jquery-ui-sortable', 'media-editor' ], '1.0.0', true );
    wp_enqueue_script( 'jprm-menuitem-admin-shim' );
    wp_add_inline_script( 'jprm-menuitem-admin-shim', $js );
});
