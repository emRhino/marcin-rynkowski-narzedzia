/* Breadcrumb w linii: na starcie przewinięty do ostatniego elementu (bieżąca strona widoczna). */
( function () {
	'use strict';

	function doKonca() {
		document.querySelectorAll( '.ct-breadcrumbs' ).forEach( function ( okruszki ) {
			// Bez przepełnienia (np. komputer) scrollWidth = clientWidth i nic się nie zmienia.
			okruszki.scrollLeft = okruszki.scrollWidth;
		} );
	}

	doKonca();

	// Obrót telefonu albo zmiana szerokości zmienia, co się mieści.
	window.addEventListener( 'resize', doKonca );
} )();
