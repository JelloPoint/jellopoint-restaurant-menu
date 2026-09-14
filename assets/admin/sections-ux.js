(function(){
    'use strict';
    var strings=window.jprmSectionsUx||{};
    function ready(fn){if(document.readyState!=='loading'){fn();}else{document.addEventListener('DOMContentLoaded',fn);}}
    function replaceCategory(text){return text.replace(new RegExp(strings.category||'Category','gi'),strings.section||'Section');}
    ready(function(){
        if(!document.body.classList.contains('taxonomy-jprm_section')){return;}
        var addSubmit=document.querySelector('#addtag input#submit, #addtag button#submit, .tag-add-form input[type="submit"], .tag-add-form button[type="submit"]');
        if(addSubmit){if(addSubmit.tagName==='INPUT'){addSubmit.value=strings.addSection;}else{addSubmit.textContent=strings.addSection;}addSubmit.setAttribute('aria-label',strings.addSection);}
        var h1=document.querySelector('.wrap > h1');
        if(h1){h1.textContent=replaceCategory(h1.textContent);}
        document.querySelectorAll('.edit-tag-form .form-field.term-parent-wrap th label, .edit-tag-form .form-field.term-parent-wrap label, .term-parent-wrap label').forEach(function(el){el.textContent=strings.parentSection;});
        var parentSelect=document.querySelector('.term-parent-wrap select');
        if(parentSelect){parentSelect.setAttribute('aria-label',strings.parentSection);parentSelect.setAttribute('title',strings.parentSection);}
    });
})();
