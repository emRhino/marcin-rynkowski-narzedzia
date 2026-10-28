/* Liczniki promocji: odliczanie co sekundę; po końcu licznik znika (także na stronie z cache). */
( function () {
	'use strict';

	function dwieCyfry( liczba ) {
		return liczba < 10 ? '0' + liczba : String( liczba );
	}

	// Zwraca false, gdy licznik się skończył i został ukryty.
	function odswiez( licznik ) {
		var koniec = parseInt( licznik.dataset.koniec, 10 ) * 1000;
		var zostalo = Math.floor( ( koniec - Date.now() ) / 1000 );

		if ( ! ( zostalo > 0 ) ) {
			licznik.hidden = true;
			return false;
		}

		var wartosci = {
			dni: Math.floor( zostalo / 86400 ),
			godz: Math.floor( ( zostalo % 86400 ) / 3600 ),
			min: Math.floor( ( zostalo % 3600 ) / 60 ),
			sek: zostalo % 60,
		};

		Object.keys( wartosci ).forEach( function ( jednostka ) {
			var pole = licznik.querySelector( '[data-jednostka="' + jednostka + '"]' );
			if ( pole ) {
				pole.textContent = dwieCyfry( wartosci[ jednostka ] );
			}
		} );

		return true;
	}

	var liczniki = Array.prototype.slice.call( document.querySelectorAll( '.rynkowski-licznik' ) );
	var zegar = setInterval( tyknij, 1000 );

	function tyknij() {
		liczniki = liczniki.filter( odswiez );
		if ( ! liczniki.length ) {
			clearInterval( zegar );
		}
	}

	tyknij();
} )();
