import { beforeEach, describe, expect, it, vi } from 'vitest';
import jQuery from 'jquery';

const FORMULARZ = `
	<input type="radio" class="rynkowski-kafel-typ" name="t" value="image">
	<input type="radio" class="rynkowski-kafel-typ" name="t" value="video">
	<input type="radio" class="rynkowski-kafel-typ" name="t" value="text">
	<table>
		<tr class="rynkowski-kafel-pole" data-typ="image" id="wiersz-obraz"><td class="rynkowski-media" data-library="image">
			<input type="hidden" class="rynkowski-media-id" value="5">
			<div class="rynkowski-media-podglad"><img src="stary.jpg"></div>
			<button type="button" class="rynkowski-media-wybierz">Wybierz</button>
			<button type="button" class="rynkowski-media-usun">Usuń</button>
		</td></tr>
		<tr class="rynkowski-kafel-pole" data-typ="video" id="wiersz-wideo"><td class="rynkowski-media" data-library="video/mp4">
			<input type="hidden" class="rynkowski-media-id" value="">
			<div class="rynkowski-media-podglad"></div>
			<button type="button" class="rynkowski-media-wybierz">Wybierz</button>
		</td></tr>
		<tr class="rynkowski-kafel-pole" data-typ="text" id="wiersz-tekst"><td>
			<input class="rynkowski-color">
		</td></tr>
	</table>`;

let ramka;

/**
 * Ładuje skrypt panelu na świeżym formularzu i czeka na jQuery ready.
 */
async function wczytaj( zaznaczony ) {
	document.body.innerHTML = FORMULARZ;
	if ( zaznaczony ) {
		document.querySelector( `.rynkowski-kafel-typ[value="${ zaznaczony }"]` ).checked = true;
	}
	jQuery( document ).off();
	vi.resetModules();
	await import( '../assets/kafle-admin.js' );
	await new Promise( ( resolve ) => jQuery( resolve ) );
	await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
}

/**
 * Udaje wybór pliku w bibliotece mediów.
 */
function wybierz( plik ) {
	ramka.handlers.select.forEach( ( handler ) => handler() );
	return plik;
}

const widoczny = ( id ) => document.getElementById( id ).style.display !== 'none';

describe( 'kafle-admin.js', () => {
	beforeEach( () => {
		window.jQuery = jQuery;
		window.rynkowskiKafle = { wybierz: 'Wybierz plik', uzyj: 'Użyj tego pliku' };
		jQuery.fn.wpColorPicker = vi.fn( function () {
			return this;
		} );
		ramka = { handlers: { select: [] }, plik: null, on: null, open: vi.fn() };
		ramka.on = vi.fn( ( zdarzenie, handler ) => ramka.handlers[ zdarzenie ].push( handler ) );
		ramka.state = () => ( { get: () => ( { first: () => ( { toJSON: () => ramka.plik } ) } ) } );
		window.wp = { media: vi.fn( () => ramka ) };
	} );

	it( 'włącza próbnik kolorów i pokazuje pola zaznaczonego typu', async () => {
		await wczytaj( 'text' );

		expect( jQuery.fn.wpColorPicker ).toHaveBeenCalled();
		expect( widoczny( 'wiersz-tekst' ) ).toBe( true );
		expect( widoczny( 'wiersz-obraz' ) ).toBe( false );
		expect( widoczny( 'wiersz-wideo' ) ).toBe( false );
	} );

	it( 'bez zaznaczenia pokazuje pola grafiki i reaguje na zmianę typu', async () => {
		await wczytaj();
		expect( widoczny( 'wiersz-obraz' ) ).toBe( true );

		const wideo = document.querySelector( '.rynkowski-kafel-typ[value="video"]' );
		wideo.checked = true;
		jQuery( wideo ).trigger( 'change' );

		expect( widoczny( 'wiersz-wideo' ) ).toBe( true );
		expect( widoczny( 'wiersz-obraz' ) ).toBe( false );
	} );

	it( 'otwiera bibliotekę z filtrem pola i wstawia miniaturę obrazu', async () => {
		await wczytaj( 'image' );
		jQuery( '#wiersz-obraz .rynkowski-media-wybierz' ).trigger( 'click' );

		expect( window.wp.media ).toHaveBeenCalledWith( {
			title: 'Wybierz plik',
			button: { text: 'Użyj tego pliku' },
			library: { type: 'image' },
			multiple: false,
		} );
		expect( ramka.open ).toHaveBeenCalled();

		ramka.plik = { id: 42, type: 'image', url: 'duzy.jpg', sizes: { thumbnail: { url: 'mini.jpg' } } };
		wybierz();

		expect( jQuery( '#wiersz-obraz .rynkowski-media-id' ).val() ).toBe( '42' );
		expect( jQuery( '#wiersz-obraz .rynkowski-media-podglad img' ).attr( 'src' ) ).toBe( 'mini.jpg' );
		expect( jQuery( '#wiersz-obraz .rynkowski-media-podglad img' ) ).toHaveLength( 1 );
	} );

	it( 'obraz bez miniatury używa pełnego adresu', async () => {
		await wczytaj( 'image' );
		jQuery( '#wiersz-obraz .rynkowski-media-wybierz' ).trigger( 'click' );

		ramka.plik = { id: 43, type: 'image', url: 'duzy.jpg' };
		wybierz();

		expect( jQuery( '#wiersz-obraz .rynkowski-media-podglad img' ).attr( 'src' ) ).toBe( 'duzy.jpg' );
	} );

	it( 'wideo pokazuje nazwę pliku jako tekst, nie HTML', async () => {
		await wczytaj( 'video' );
		jQuery( '#wiersz-wideo .rynkowski-media-wybierz' ).trigger( 'click' );

		expect( window.wp.media.mock.calls[ 0 ][ 0 ].library ).toEqual( { type: 'video/mp4' } );

		ramka.plik = { id: 7, type: 'video', filename: '<img src=x onerror=alert(1)>.mp4' };
		wybierz();

		const podglad = document.querySelector( '#wiersz-wideo .rynkowski-media-podglad' );
		expect( podglad.querySelector( 'img' ) ).toBeNull();
		expect( podglad.querySelector( 'code' ).textContent ).toBe( '<img src=x onerror=alert(1)>.mp4' );
	} );

	it( 'usuń czyści wybór i podgląd', async () => {
		await wczytaj( 'image' );
		jQuery( '#wiersz-obraz .rynkowski-media-usun' ).trigger( 'click' );

		expect( jQuery( '#wiersz-obraz .rynkowski-media-id' ).val() ).toBe( '' );
		expect( jQuery( '#wiersz-obraz .rynkowski-media-podglad' ).children() ).toHaveLength( 0 );
	} );
} );
