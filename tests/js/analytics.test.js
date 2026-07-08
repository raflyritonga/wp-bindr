import { Analytics } from '../../src/viewer/analytics';

const ENDPOINT = 'https://example.test/wp-json/bindr/v1/event?_wpnonce=rest';

const makeConfig = ( endpoint = ENDPOINT ) => ( {
	id: 7,
	analytics: { endpoint, nonce: 'event-nonce' },
} );

const lastFetchBody = () =>
	JSON.parse( window.fetch.mock.calls.at( -1 )[ 1 ].body );

describe( 'Analytics', () => {
	beforeEach( () => {
		jest.useFakeTimers();
		window.fetch = jest.fn( () => Promise.resolve() );
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	test( 'flush posts queued events with flipbook id and nonce', () => {
		const analytics = new Analytics( makeConfig() );

		analytics.track( 'open' );
		analytics.track( 'page_turn', 3 );
		analytics.flush( false );

		expect( window.fetch ).toHaveBeenCalledTimes( 1 );
		expect( window.fetch.mock.calls[ 0 ][ 0 ] ).toBe( ENDPOINT );

		const body = lastFetchBody();
		expect( body.nonce ).toBe( 'event-nonce' );
		expect( body.events ).toEqual( [
			{ flipbook: 7, event: 'open', page: 0 },
			{ flipbook: 7, event: 'page_turn', page: 3 },
		] );
	} );

	test( 'missing endpoint disables tracking', () => {
		const analytics = new Analytics( { id: 7 } );

		analytics.track( 'open' );
		analytics.flush( false );

		expect( window.fetch ).not.toHaveBeenCalled();
	} );

	test( 'trackOnce sends an event only once per instance', () => {
		const analytics = new Analytics( makeConfig() );

		analytics.trackOnce( 'complete', 9 );
		analytics.trackOnce( 'complete', 10 );
		analytics.flush( false );

		expect( lastFetchBody().events ).toHaveLength( 1 );
	} );

	test( 'queue auto-flushes when 20 events accumulate', () => {
		const analytics = new Analytics( makeConfig() );

		for ( let i = 1; i <= 20; i++ ) {
			analytics.track( 'page_turn', i );
		}

		expect( window.fetch ).toHaveBeenCalledTimes( 1 );
		expect( lastFetchBody().events ).toHaveLength( 20 );

		// Queue must be empty afterwards.
		analytics.flush( false );
		expect( window.fetch ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'interval flushes pending events', () => {
		const analytics = new Analytics( makeConfig() );

		analytics.track( 'open' );
		jest.advanceTimersByTime( 15000 );

		expect( window.fetch ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'flush prefers sendBeacon on unload', () => {
		const sendBeacon = jest.fn( () => true );
		Object.defineProperty( window.navigator, 'sendBeacon', {
			value: sendBeacon,
			configurable: true,
		} );

		const analytics = new Analytics( makeConfig() );
		analytics.track( 'open' );
		analytics.flush( true );

		expect( sendBeacon ).toHaveBeenCalledTimes( 1 );
		expect( sendBeacon.mock.calls[ 0 ][ 0 ] ).toBe( ENDPOINT );
		expect( window.fetch ).not.toHaveBeenCalled();

		delete window.navigator.sendBeacon;
	} );

	test( 'empty queue does not trigger a request', () => {
		const analytics = new Analytics( makeConfig() );

		analytics.flush( false );

		expect( window.fetch ).not.toHaveBeenCalled();
	} );
} );
