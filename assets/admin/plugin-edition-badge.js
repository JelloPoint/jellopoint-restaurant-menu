( function() {
	'use strict';

	if ( typeof jprmPluginEditionBadge !== 'object' || ! jprmPluginEditionBadge.basename ) {
		return;
	}

	var rows = document.querySelectorAll( '.wp-list-table.plugins tr[data-plugin]' );
	for ( var i = 0; i < rows.length; i++ ) {
		if ( jprmPluginEditionBadge.basename !== rows[ i ].getAttribute( 'data-plugin' ) ) {
			continue;
		}

		var title = rows[ i ].querySelector( '.plugin-title > strong:first-child' );
		if ( title && ! title.querySelector( '.jprm-plugin-edition-badge' ) ) {
			var badge = document.createElement( 'span' );
			badge.className = 'jprm-plugin-edition-badge';
			badge.textContent = 'PRO';
			title.appendChild( badge );
		}
	}
}() );
