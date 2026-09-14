(function(){
    'use strict';
    var config=window.jprmSectionsAdmin||{};
    function ready(fn){if(document.readyState!=='loading'){fn();}else{document.addEventListener('DOMContentLoaded',fn);}}
    ready(function(){
        var owner=document.getElementById('jprm_owner_menus'),fields=document.querySelectorAll('.jprm-daily-section-option');
        if(owner&&fields.length){
            var refresh=function(){var visible=Array.prototype.some.call(owner.selectedOptions,function(option){return option.getAttribute('data-daily')==='1';});fields.forEach(function(field){field.style.display=visible?'':'none';});};
            owner.addEventListener('change',refresh);refresh();
        }

        if(document.getElementById('jprm_filter_menu')){return;}
        var form=document.getElementById('posts-filter');
        if(!form){return;}
        var top=form.querySelector('.tablenav.top .actions')||form.querySelector('.tablenav.top');
        if(!top){return;}
        var wrap=document.createElement('div'),label=document.createElement('label'),select=document.createElement('select');
        wrap.className='alignleft actions jprm-sections-filter';label.className='screen-reader-text';label.htmlFor='jprm_filter_menu';label.textContent=config.filterMenu||'';
        select.name='jprm_filter_menu';select.id='jprm_filter_menu';select.className='postform';
        var all=document.createElement('option');all.value='0';all.textContent=config.allMenus||'';select.appendChild(all);
        (config.options||[]).forEach(function(item){var option=document.createElement('option');option.value=String(item.id);option.textContent=item.name;if(Number(config.selected)===Number(item.id)){option.selected=true;}select.appendChild(option);});
        wrap.appendChild(label);wrap.appendChild(select);top.prepend(wrap);
        select.addEventListener('change',function(){var url=new URL(window.location.href);url.searchParams.set('jprm_filter_menu',this.value||'0');url.searchParams.delete('paged');if((this.value||'0')==='0'){url.searchParams.delete('orderby');url.searchParams.delete('order');}window.location.assign(url.toString());});
    });
})();
