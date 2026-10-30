/* Ekran edycji wiersza informacji: wybór ikony z biblioteki mediów. */
( function ( $ ) {
	'use strict';

	$( function () {
		$( document ).on( 'click', '.rynkowski-media-wybierz', function ( event ) {
			event.preventDefault();

			var $pole = $( this ).closest( '.rynkowski-media' );
			var ramka = wp.media( {
				title: rynkowskiInformacje.wybierz,
				button: { text: rynkowskiInformacje.uzyj },
				library: { type: 'image' },
				multiple: false,
			} );

			ramka.on( 'select', function () {
				var plik = ramka.state().get( 'selection' ).first().toJSON();
				var url = plik.sizes && plik.sizes.thumbnail ? plik.sizes.thumbnail.url : plik.url;

				$pole.find( '.rynkowski-media-id' ).val( plik.id );
				// Podgląd budowany przez DOM, bez wstawiania surowego HTML.
				$pole.find( '.rynkowski-media-podglad' ).empty().append(
					$( '<img>' ).attr( { src: url, alt: '', width: 48, height: 48 } )
				);
			} );

			ramka.open();
		} );

		$( document ).on( 'click', '.rynkowski-media-usun', function ( event ) {
			event.preventDefault();

			var $pole = $( this ).closest( '.rynkowski-media' );
			$pole.find( '.rynkowski-media-id' ).val( '' );
			$pole.find( '.rynkowski-media-podglad' ).empty();
		} );
	} );
} )( jQuery );
