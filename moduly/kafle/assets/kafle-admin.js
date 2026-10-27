/* Ekran edycji kafla promocyjnego: pola zależne od typu, biblioteka mediów, kolory. */
( function ( $ ) {
	'use strict';

	function pokazPolaTypu() {
		var typ = $( '.rynkowski-kafel-typ:checked' ).val() || 'image';
		$( '.rynkowski-kafel-pole' ).each( function () {
			$( this ).toggle( $( this ).data( 'typ' ) === typ );
		} );
	}

	$( function () {
		$( '.rynkowski-color' ).wpColorPicker();

		$( document ).on( 'change', '.rynkowski-kafel-typ', pokazPolaTypu );
		pokazPolaTypu();

		$( document ).on( 'click', '.rynkowski-media-wybierz', function ( event ) {
			event.preventDefault();

			var $pole = $( this ).closest( '.rynkowski-media' );
			var ramka = wp.media( {
				title: rynkowskiKafle.wybierz,
				button: { text: rynkowskiKafle.uzyj },
				library: { type: $pole.data( 'library' ) },
				multiple: false,
			} );

			ramka.on( 'select', function () {
				var plik = ramka.state().get( 'selection' ).first().toJSON();
				var $podglad = $pole.find( '.rynkowski-media-podglad' ).empty();

				$pole.find( '.rynkowski-media-id' ).val( plik.id );

				// Podgląd budowany przez DOM, bez wstawiania surowego HTML.
				if ( plik.type === 'image' ) {
					var url = plik.sizes && plik.sizes.thumbnail ? plik.sizes.thumbnail.url : plik.url;
					$( '<img>' ).attr( { src: url, alt: '' } ).css( 'max-width', '150px' ).appendTo( $podglad );
				} else {
					$( '<code>' ).text( plik.filename ).appendTo( $podglad );
				}
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
