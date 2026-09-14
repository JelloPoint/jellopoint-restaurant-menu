(function(){
    'use strict';
    var strings=window.jprmMenusAdmin||{};
    function ready(fn){if(document.readyState!=='loading'){fn();}else{document.addEventListener('DOMContentLoaded',fn);}}
    function replaceTerms(text){return text.replace(new RegExp(strings.categories||'Categories','gi'),strings.menus||'Menus').replace(new RegExp('\\b'+(strings.category||'Category')+'\\b','gi'),strings.menu||'Menu');}
    ready(function(){
        if(!document.body.classList.contains('taxonomy-jprm_menu')){return;}
        var addHdr=document.querySelector('.wrap .form-wrap > h2')||document.querySelector('.tag-add-form h2');
        if(addHdr){addHdr.textContent=replaceTerms(addHdr.textContent);}
        var addSubmit=document.querySelector('#addtag input#submit, #addtag button#submit, .tag-add-form input[type="submit"], .tag-add-form button[type="submit"]');
        if(addSubmit){if(addSubmit.tagName==='INPUT'){addSubmit.value=strings.addMenu;}else{addSubmit.textContent=strings.addMenu;}addSubmit.setAttribute('aria-label',strings.addMenu);}
        var searchLabel=document.querySelector('label[for="tag-search-input"], .search-form label, .search-box label');
        if(searchLabel){searchLabel.textContent=strings.searchMenus+':';}
        var searchInput=document.getElementById('tag-search-input')||document.querySelector('.search-form input[type="search"], .search-form input[type="text"]');
        if(searchInput){searchInput.placeholder=strings.searchMenus;}
        var h1=document.querySelector('.wrap > h1');
        if(h1){h1.textContent=replaceTerms(h1.textContent);}
        document.querySelectorAll('.subsubsub a').forEach(function(link){link.textContent=replaceTerms(link.textContent);});
        document.querySelectorAll('.term-parent-wrap select').forEach(function(select){select.value='0';});

        var toggle=document.querySelector('input[name="jprm_is_daily_menu"]'),type=document.getElementById('jprm_daily_menu_date_type'),start=document.getElementById('jprm_daily_menu_date'),end=document.getElementById('jprm_daily_menu_end_date');
        if(!toggle){return;}
        function refresh(){
            var range=type&&type.value==='range',noDate=type&&type.value==='none';
            document.querySelectorAll('.jprm-daily-menu-detail').forEach(function(field){field.style.display=toggle.checked?'':'none';});
            document.querySelectorAll('.jprm-date-label-single').forEach(function(label){label.style.display=range?'none':'';});
            document.querySelectorAll('.jprm-date-label-range').forEach(function(label){label.style.display=range?'':'none';});
            document.querySelectorAll('.jprm-daily-menu-end-date').forEach(function(field){field.style.display=toggle.checked&&range?'':'none';});
            if(start){start.closest('.jprm-daily-menu-detail').style.display=toggle.checked&&!noDate?'':'none';start.required=toggle.checked&&!noDate;}
            if(end){end.min=start?start.value:'';end.required=toggle.checked&&range;}
        }
        toggle.addEventListener('change',refresh);if(type){type.addEventListener('change',refresh);}if(start){start.addEventListener('change',refresh);}refresh();
    });
})();
