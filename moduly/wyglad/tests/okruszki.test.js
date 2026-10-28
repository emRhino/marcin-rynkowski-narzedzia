import { afterEach, describe, expect, it, vi } from 'vitest';

const OKRUSZKI = '<nav class="ct-breadcrumbs"><span>Strona główna</span><span>Zestawy</span><span>Zestaw śniadaniowy dla dwojga</span></nav>';

// jsdom nie liczy układu: szerokości ustawiamy ręcznie, a scrollLeft obcinamy jak przeglądarka.
function ustawWymiary( el, { scrollWidth, clientWidth } ) {
	let pozycja = 0;
	Object.defineProperty( el, 'scrollWidth', { configurable: true, get: () => scrollWidth } );
	Object.defineProperty( el, 'clientWidth', { configurable: true, get: () => clientWidth } );
	Object.defineProperty( el, 'scrollLeft', {
		configurable: true,
		get: () => pozycja,
		set: ( v ) => {
			pozycja = Math.max( 0, Math.min( v, scrollWidth - clientWidth ) );
		},
	} );
}

async function wczytaj( przygotuj ) {
	document.body.innerHTML = OKRUSZKI + OKRUSZKI;
	document.querySelectorAll( '.ct-breadcrumbs' ).forEach( przygotuj );
	vi.resetModules();
	await import( '../assets/okruszki.js' );
}

describe( 'okruszki.js', () => {
	const sluchacze = [];
	const dodaj = window.addEventListener.bind( window );
	window.addEventListener = ( typ, fn, opcje ) => {
		sluchacze.push( [ typ, fn ] );
		dodaj( typ, fn, opcje );
	};

	afterEach( () => {
		sluchacze.splice( 0 ).forEach( ( [ typ, fn ] ) => window.removeEventListener( typ, fn ) );
		document.body.innerHTML = '';
	} );

	it( 'za długi breadcrumb startuje przewinięty do ostatniego elementu', async () => {
		await wczytaj( ( el ) => ustawWymiary( el, { scrollWidth: 600, clientWidth: 320 } ) );

		document.querySelectorAll( '.ct-breadcrumbs' ).forEach( ( el ) => {
			expect( el.scrollLeft ).toBe( 280 );
		} );
	} );

	it( 'mieszczący się breadcrumb zostaje na początku', async () => {
		await wczytaj( ( el ) => ustawWymiary( el, { scrollWidth: 300, clientWidth: 320 } ) );

		expect( document.querySelector( '.ct-breadcrumbs' ).scrollLeft ).toBe( 0 );
	} );

	it( 'po obrocie telefonu znów pokazuje koniec', async () => {
		let szerokosc = 320;
		await wczytaj( ( el ) => {
			let pozycja = 0;
			Object.defineProperty( el, 'scrollWidth', { configurable: true, get: () => 600 } );
			Object.defineProperty( el, 'clientWidth', { configurable: true, get: () => szerokosc } );
			Object.defineProperty( el, 'scrollLeft', {
				configurable: true,
				get: () => pozycja,
				set: ( v ) => {
					pozycja = Math.max( 0, Math.min( v, 600 - szerokosc ) );
				},
			} );
		} );
		const el = document.querySelector( '.ct-breadcrumbs' );
		el.scrollLeft = 0;

		szerokosc = 500;
		window.dispatchEvent( new Event( 'resize' ) );

		expect( el.scrollLeft ).toBe( 100 );
	} );

	it( 'strona bez breadcrumbu nie zgłasza błędu', async () => {
		document.body.innerHTML = '<p>Bez okruszków</p>';
		vi.resetModules();

		await expect( import( '../assets/okruszki.js' ) ).resolves.toBeDefined();
	} );
} );
