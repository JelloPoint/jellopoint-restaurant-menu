(function($){
    'use strict';
    $(function(){
        var frame,strings=window.jprmPrintDocument||{};
        $('#jprm-select-logo').on('click',function(event){
            event.preventDefault();if(frame){frame.open();return;}
            frame=wp.media({title:strings.chooseLogo,button:{text:strings.useLogo},library:{type:'image'},multiple:false});
            frame.on('select',function(){var image=frame.state().get('selection').first().toJSON(),sizes=image.sizes||{},url=(sizes.medium&&sizes.medium.url)||(sizes.thumbnail&&sizes.thumbnail.url)||image.url||'',$preview=$('#jprm-print-logo-preview').empty();$('#jprm-print-logo-id').val(image.id||0);if(url){$('<img>',{src:url,alt:''}).appendTo($preview);}else{$('<span>').text(strings.noLogo).appendTo($preview);}});
            frame.open();
        });
        $('#jprm-remove-logo').on('click',function(event){event.preventDefault();$('#jprm-print-logo-id').val('0');$('#jprm-print-logo-preview').empty().append($('<span>').text(strings.noLogo));});
    });
})(jQuery);
