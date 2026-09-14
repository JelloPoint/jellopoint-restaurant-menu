(function($){
    'use strict';
    var config=window.jprmMenuItemList||{};
    function buildSelect(){
        var $select=$('<select>',{name:'jprm_target_section',id:'jprm_target_section'}).css('margin-left','6px');
        $select.append($('<option>',{value:0,text:config.chooseSection||''}));
        (config.groups||[]).forEach(function(group){var $group=$('<optgroup>',{label:group.name});(group.items||[]).forEach(function(item){$group.append($('<option>',{value:item.id,text:item.name}));});$select.append($group);});
        return $select;
    }
    function ensureSelector(selector){var $bulk=$(selector);if($bulk.length&&!$bulk.find('#jprm_target_section').length){$bulk.append(buildSelect());}}
    $(document).on('change','select[name="action"], select[name="action2"]',function(){if($(this).val()==='jprm_assign_section'){ensureSelector(this.name==='action'?'#bulk-action-selector-top':'#bulk-action-selector-bottom');}else{$('#jprm_target_section').remove();}});
    $(document).on('click','.jprm-multi-toggle',function(event){event.preventDefault();var target=$(this).attr('data-target');if(target){$('#'+target).toggle();}});
})(jQuery);
