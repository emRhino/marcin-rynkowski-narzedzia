import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const KAFEL = `
	<li class="rynkowski-kafel rynkowski-kafel--video">
		<a class="rynkowski-kafel__wnetrze" href="#"><video autoplay muted loop></video></a>
		<button type="button" class="rynkowski-kafel__pauza" aria-pressed="false">Zatrzymaj wideo</button>
	</li>`;

/**
 * Wczytuje skrypt od nowa, jak przy nowym załadowaniu strony.
 */
async function wczytaj( { ograniczRuch = false, matchMedia = true } = {} ) {
	vi.resetModules();
	window.matchMedia = matchMedia ? vi.fn( () => ( { matches: ograniczRuch } ) ) : undefined;
	await import( '../assets/kafle.js' );
}

const nextTick = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

// Każde wczytanie skryptu zostawia obserwatora DOM; po teście go odłączamy, jak przy zmianie strony.
const obserwatorzy = [];
const PrawdziwyObserwator = window.MutationObserver;
window.MutationObserver = class extends PrawdziwyObserwator {
	constructor( callback ) {
		super( callback );
		obserwatorzy.push( this );
	}
};

describe( 'kafle.js', () => {
	beforeEach( () => {
		window.rynkowskiKafle = { zatrzymaj: 'Zatrzymaj wideo', odtworz: 'Odtwórz wideo' };
		// jsdom nie odtwarza wideo; zaślepki pilnują stanu „paused”.
		Object.defineProperty( HTMLMediaElement.prototype, 'paused', {
			configurable: true,
			get() {
				return this._zatrzymane ?? false;
			},
		} );
		HTMLMediaElement.prototype.play = vi.fn( function () {
			this._zatrzymane = false;
		} );
		HTMLMediaElement.prototype.pause = vi.fn( function () {
			this._zatrzymane = true;
		} );
	} );

	afterEach( () => {
		obserwatorzy.splice( 0 ).forEach( ( obserwator ) => obserwator.disconnect() );
		document.body.innerHTML = '';
	} );

	it( 'przycisk zatrzymuje i wznawia wideo, zmieniając opis', async () => {
		document.body.innerHTML = KAFEL;
		await wczytaj();
		const wideo = document.querySelector( 'video' );
		const przycisk = document.querySelector( 'button' );

		przycisk.click();
		expect( wideo.paused ).toBe( true );
		expect( przycisk.getAttribute( 'aria-pressed' ) ).toBe( 'true' );
		expect( przycisk.textContent ).toBe( 'Odtwórz wideo' );

		przycisk.click();
		expect( wideo.paused ).toBe( false );
		expect( przycisk.getAttribute( 'aria-pressed' ) ).toBe( 'false' );
		expect( przycisk.textContent ).toBe( 'Zatrzymaj wideo' );
	} );

	it( 'przy „ogranicz ruch” wideo startuje zatrzymane', async () => {
		document.body.innerHTML = KAFEL;
		await wczytaj( { ograniczRuch: true } );
		const wideo = document.querySelector( 'video' );

		expect( wideo.hasAttribute( 'autoplay' ) ).toBe( false );
		expect( wideo.paused ).toBe( true );
		expect( document.querySelector( 'button' ).textContent ).toBe( 'Odtwórz wideo' );
	} );

	it( 'bez autostartu pierwsze kliknięcie uruchamia wideo', async () => {
		// Tak renderuje PHP przy wyłączonym autostarcie: bez autoplay, przycisk „Odtwórz”.
		document.body.innerHTML = KAFEL.replace( ' autoplay', '' )
			.replace( 'aria-pressed="false">Zatrzymaj wideo', 'aria-pressed="true">Odtwórz wideo' );
		const wideo = document.querySelector( 'video' );
		wideo._zatrzymane = true;
		await wczytaj();
		const przycisk = document.querySelector( 'button' );

		przycisk.click();

		expect( HTMLMediaElement.prototype.play ).toHaveBeenCalledTimes( 1 );
		expect( wideo.paused ).toBe( false );
		expect( przycisk.getAttribute( 'aria-pressed' ) ).toBe( 'false' );
		expect( przycisk.textContent ).toBe( 'Zatrzymaj wideo' );
	} );

	it( 'działa w przeglądarce bez matchMedia', async () => {
		document.body.innerHTML = KAFEL;
		await wczytaj( { matchMedia: false } );

		expect( document.querySelector( 'video' ).hasAttribute( 'autoplay' ) ).toBe( true );
	} );

	it( 'pomija kafle bez wideo albo bez przycisku', async () => {
		document.body.innerHTML = `
			<li class="rynkowski-kafel rynkowski-kafel--video"><button class="rynkowski-kafel__pauza"></button></li>
			<li class="rynkowski-kafel rynkowski-kafel--video"><video></video></li>`;
		await wczytaj();

		document.querySelectorAll( '.rynkowski-kafel' ).forEach( ( kafel ) => {
			expect( kafel.dataset.rynkowskiGotowy ).toBeUndefined();
		} );
	} );

	it( 'podpina kafle doładowane później i nie podpina dwa razy', async () => {
		await wczytaj();
		document.body.insertAdjacentHTML( 'beforeend', KAFEL );
		await nextTick();
		// Kolejna zmiana DOM nie może dodać drugiego nasłuchu.
		document.body.insertAdjacentHTML( 'beforeend', '<p>inna zmiana</p>' );
		await nextTick();

		const przycisk = document.querySelector( 'button' );
		przycisk.click();

		expect( HTMLMediaElement.prototype.pause ).toHaveBeenCalledTimes( 1 );
		expect( przycisk.getAttribute( 'aria-pressed' ) ).toBe( 'true' );
	} );
} );
