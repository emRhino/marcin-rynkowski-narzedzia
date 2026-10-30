import { beforeEach, describe, expect, it, vi } from 'vitest';
import jQuery from 'jquery';

const FORMULARZ = `
	<table><tr><td class="rynkowski-media">
		<input type="hidden" class="rynkowski-media-id" value="5">
		<div class="rynkowski-media-podglad"><img src="stara.png"></div>
		<button type="button" class="rynkowski-media-wybierz">Wybierz</button>
		<button type="button" class="rynkowski-media-usun">Usuń</button>
	</td></tr></table>`;

let ramka;

async function wczytaj() {
	document.body.innerHTML = FORMULARZ;
	jQuery( document ).off();
	vi.resetModules();
	await import( '../assets/informacje-admin.js' );
	await new Promise( ( resolve ) => jQuery( resolve ) );
	await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
}

function wybierz( plik ) {
	ramka.plik = plik;
	ramka.handlers.select.forEach( ( handler ) => handler() );
}

describe( 'informacje-admin.js', () => {
	beforeEach( () => {
		window.jQuery = jQuery;
		window.rynkowskiInformacje = { wybierz: 'Wybierz ikonę', uzyj: 'Użyj tej ikony' };
		ramka = { handlers: { select: [] }, plik: null, open: vi.fn() };
		ramka.on = vi.fn( ( zdarzenie, handler ) => ramka.handlers[ zdarzenie ].push( handler ) );
		ramka.state = () => ( { get: () => ( { first: () => ( { toJSON: () => ramka.plik } ) } ) } );
		window.wp = { media: vi.fn( () => ramka ) };
	} );

	it( 'otwiera bibliotekę obrazków i wstawia miniaturę wybranej ikony', async () => {
		await wczytaj();
		jQuery( '.rynkowski-media-wybierz' ).trigger( 'click' );

		expect( window.wp.media ).toHaveBeenCalledWith( {
			title: 'Wybierz ikonę',
			button: { text: 'Użyj tej ikony' },
			library: { type: 'image' },
			multiple: false,
		} );
		expect( ramka.open ).toHaveBeenCalled();

		wybierz( { id: 42, url: 'duza.png', sizes: { thumbnail: { url: 'mini.png' } } } );

		expect( jQuery( '.rynkowski-media-id' ).val() ).toBe( '42' );
		const img = jQuery( '.rynkowski-media-podglad img' );
		expect( img ).toHaveLength( 1 );
		expect( img.attr( 'src' ) ).toBe( 'mini.png' );
		expect( img.attr( 'alt' ) ).toBe( '' );
	} );

	it( 'obrazek bez miniatury (np. SVG) używa pełnego adresu, bez wstawiania HTML', async () => {
		await wczytaj();
		jQuery( '.rynkowski-media-wybierz' ).trigger( 'click' );

		wybierz( { id: 43, url: '"><script>alert(1)</script>.svg' } );

		const podglad = document.querySelector( '.rynkowski-media-podglad' );
		expect( podglad.querySelector( 'script' ) ).toBeNull();
		expect( podglad.querySelector( 'img' ).getAttribute( 'src' ) ).toBe( '"><script>alert(1)</script>.svg' );
	} );

	it( 'usuń czyści wybór i podgląd', async () => {
		await wczytaj();
		jQuery( '.rynkowski-media-usun' ).trigger( 'click' );

		expect( jQuery( '.rynkowski-media-id' ).val() ).toBe( '' );
		expect( jQuery( '.rynkowski-media-podglad' ).children() ).toHaveLength( 0 );
	} );
} );
