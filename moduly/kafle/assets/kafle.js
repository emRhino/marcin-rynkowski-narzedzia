/* Kafle promocyjne: przycisk pauzy wideo i poszanowanie ustawienia „ogranicz ruch”. */
( function () {
	'use strict';

	var ograniczRuch = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function ustawPrzycisk( przycisk, zatrzymane ) {
		przycisk.setAttribute( 'aria-pressed', zatrzymane ? 'true' : 'false' );
		przycisk.textContent = zatrzymane ? rynkowskiKafle.odtworz : rynkowskiKafle.zatrzymaj;
	}

	function podepnij( kafel ) {
		var wideo = kafel.querySelector( 'video' );
		var przycisk = kafel.querySelector( '.rynkowski-kafel__pauza' );

		if ( ! wideo || ! przycisk || kafel.dataset.rynkowskiGotowy ) {
			return;
		}
		kafel.dataset.rynkowskiGotowy = '1';

		if ( ograniczRuch ) {
			wideo.removeAttribute( 'autoplay' );
			wideo.pause();
			ustawPrzycisk( przycisk, true );
		}

		przycisk.addEventListener( 'click', function () {
			if ( wideo.paused ) {
				wideo.play();
				ustawPrzycisk( przycisk, false );
			} else {
				wideo.pause();
				ustawPrzycisk( przycisk, true );
			}
		} );
	}

	function podepnijWszystkie() {
		document.querySelectorAll( '.rynkowski-kafel--video' ).forEach( podepnij );
	}

	podepnijWszystkie();

	// Kafle doładowane przez „Załaduj więcej” albo nieskończone przewijanie Blocksy.
	new MutationObserver( podepnijWszystkie ).observe( document.body, { childList: true, subtree: true } );
} )();
