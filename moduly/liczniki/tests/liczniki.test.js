import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const TERAZ = new Date( '2026-11-28T12:00:00Z' );
const sekundy = ( data ) => Math.floor( data.getTime() / 1000 );

function licznik( koniec, { wszystkiePola = true } = {} ) {
	const pola = ( wszystkiePola ? [ 'dni', 'godz', 'min', 'sek' ] : [ 'dni', 'sek' ] )
		.map( ( j ) => `<div class="rynkowski-licznik__pole"><span class="rynkowski-licznik__liczba" data-jednostka="${ j }">00</span></div>` )
		.join( '' );
	return `<div class="rynkowski-licznik" data-koniec="${ koniec }"><div class="rynkowski-licznik__pola">${ pola }</div></div>`;
}

const wartosc = ( el, jednostka ) => el.querySelector( `[data-jednostka="${ jednostka }"]` ).textContent;

async function wczytaj( html ) {
	document.body.innerHTML = html;
	vi.resetModules();
	await import( '../assets/liczniki.js' );
}

describe( 'liczniki.js', () => {
	beforeEach( () => {
		vi.useFakeTimers();
		vi.setSystemTime( TERAZ );
	} );

	afterEach( () => {
		vi.clearAllTimers();
		vi.useRealTimers();
		document.body.innerHTML = '';
	} );

	it( 'od razu pokazuje pozostały czas z zerami wiodącymi', async () => {
		// 1 dzień, 2 godz., 3 min., 4 s.
		await wczytaj( licznik( sekundy( TERAZ ) + 86400 + 7200 + 180 + 4 ) );
		const el = document.querySelector( '.rynkowski-licznik' );

		expect( [ 'dni', 'godz', 'min', 'sek' ].map( ( j ) => wartosc( el, j ) ) ).toEqual( [ '01', '02', '03', '04' ] );
		expect( el.hidden ).toBe( false );
	} );

	it( 'odlicza co sekundę i pokazuje liczby większe niż 99 dni', async () => {
		await wczytaj( licznik( sekundy( TERAZ ) + 120 * 86400 + 10 ) );
		const el = document.querySelector( '.rynkowski-licznik' );

		vi.advanceTimersByTime( 3000 );

		expect( wartosc( el, 'sek' ) ).toBe( '07' );
		expect( wartosc( el, 'dni' ) ).toBe( '120' );
	} );

	it( 'chowa licznik w chwili końca i przestaje tykać', async () => {
		await wczytaj( licznik( sekundy( TERAZ ) + 2 ) );
		const el = document.querySelector( '.rynkowski-licznik' );

		vi.advanceTimersByTime( 1000 );
		expect( el.hidden ).toBe( false );

		vi.advanceTimersByTime( 1000 );
		expect( el.hidden ).toBe( true );
		expect( vi.getTimerCount() ).toBe( 0 );
	} );

	it( 'licznik po czasie (np. ze strony w cache) znika od razu', async () => {
		await wczytaj( licznik( sekundy( TERAZ ) - 60 ) );

		expect( document.querySelector( '.rynkowski-licznik' ).hidden ).toBe( true );
		expect( vi.getTimerCount() ).toBe( 0 );
	} );

	it( 'zły znacznik końca chowa licznik zamiast pokazywać NaN', async () => {
		await wczytaj( '<div class="rynkowski-licznik" data-koniec="abc"><span data-jednostka="sek">00</span></div>' );
		const el = document.querySelector( '.rynkowski-licznik' );

		expect( el.hidden ).toBe( true );
		expect( wartosc( el, 'sek' ) ).toBe( '00' );
	} );

	it( 'kilka liczników działa niezależnie', async () => {
		await wczytaj( licznik( sekundy( TERAZ ) + 1 ) + licznik( sekundy( TERAZ ) + 3600 ) );
		const [ krotki, dlugi ] = document.querySelectorAll( '.rynkowski-licznik' );

		vi.advanceTimersByTime( 2000 );

		expect( krotki.hidden ).toBe( true );
		expect( dlugi.hidden ).toBe( false );
		expect( wartosc( dlugi, 'min' ) ).toBe( '59' );
		expect( vi.getTimerCount() ).toBe( 1 );
	} );

	it( 'pomija brakujące pola bez błędu', async () => {
		await wczytaj( licznik( sekundy( TERAZ ) + 65, { wszystkiePola: false } ) );
		const el = document.querySelector( '.rynkowski-licznik' );

		expect( wartosc( el, 'sek' ) ).toBe( '05' );
		expect( el.querySelector( '[data-jednostka="min"]' ) ).toBeNull();
	} );

	it( 'bez liczników na stronie nie zostawia zegara', async () => {
		await wczytaj( '<p>Brak licznika</p>' );

		expect( vi.getTimerCount() ).toBe( 0 );
	} );
} );
