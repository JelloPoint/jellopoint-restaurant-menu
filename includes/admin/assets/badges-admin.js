(function($){
	$(document).on('click', '.jprm-badge-media', function(e){
		e.preventDefault();
		var $btn   = $(this);
		var $row   = $btn.closest('.jprm-badge-row');
		var $input = $row.find('input.badge-icon-url');
		var frame  = wp.media({
			title:  $btn.data('title')  || 'Select Icon',
			button: { text: $btn.data('button') || 'Use this icon' },
			multiple: false
		});
		frame.on('select', function(){
			var attachment = frame.state().get('selection').first().toJSON();
			if ($input.length) {
				$input.val(attachment.url).trigger('change');
				$row.find('.jprm-badge-icon-preview').attr('src', attachment.url);
			}
		});
		frame.open();
	});
})(jQuery);
