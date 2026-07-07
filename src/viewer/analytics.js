/**
 * Event batching for the local analytics endpoint.
 *
 * Events queue up and are flushed periodically, on tab hide, and on unload
 * (via sendBeacon so the last batch survives navigation). The nonce travels
 * in the JSON body because sendBeacon cannot set headers.
 */
export class Analytics {
	/**
	 * @param {Object} config           Viewer config.
	 * @param {number} config.id        Book ID.
	 * @param {Object} config.analytics { endpoint, nonce }.
	 */
	constructor( config ) {
		this.bookId = config.id;
		this.endpoint = config.analytics && config.analytics.endpoint;
		this.nonce = config.analytics && config.analytics.nonce;
		this.queue = [];
		this.sent = {};

		if ( ! this.endpoint ) {
			return;
		}

		this.interval = window.setInterval( () => this.flush( false ), 15000 );
		document.addEventListener( 'visibilitychange', () => {
			if ( 'hidden' === document.visibilityState ) {
				this.flush( true );
			}
		} );
		window.addEventListener( 'pagehide', () => this.flush( true ) );
	}

	/**
	 * Queue an event.
	 *
	 * @param {string} event Event name.
	 * @param {number} page  Page number (1-based, 0 when not applicable).
	 */
	track( event, page = 0 ) {
		if ( ! this.endpoint ) {
			return;
		}
		this.queue.push( { book: this.bookId, event, page } );
		if ( this.queue.length >= 20 ) {
			this.flush( false );
		}
	}

	/**
	 * Queue an event at most once per viewer instance.
	 *
	 * @param {string} event Event name.
	 * @param {number} page  Page number.
	 */
	trackOnce( event, page = 0 ) {
		if ( this.sent[ event ] ) {
			return;
		}
		this.sent[ event ] = true;
		this.track( event, page );
	}

	/**
	 * Send the queue.
	 *
	 * @param {boolean} useBeacon Prefer navigator.sendBeacon (unload-safe).
	 */
	flush( useBeacon ) {
		if ( ! this.queue.length ) {
			return;
		}
		const body = JSON.stringify( {
			nonce: this.nonce,
			events: this.queue.splice( 0, this.queue.length ),
		} );

		if ( useBeacon && navigator.sendBeacon ) {
			navigator.sendBeacon(
				this.endpoint,
				new Blob( [ body ], { type: 'application/json' } )
			);
			return;
		}

		window
			.fetch( this.endpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body,
				keepalive: true,
				credentials: 'same-origin',
			} )
			.catch( () => {} );
	}
}
