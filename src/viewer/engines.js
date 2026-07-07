/**
 * Page display engines.
 *
 * - FlipEngine: double-page book spread with the StPageFlip animation.
 * - SlideEngine: single page with slide (or instant) transitions — used on
 *   small screens, in single-page mode, and when the reader prefers
 *   reduced motion (flip animation cannot be disabled reliably, so we
 *   switch to instant page changes instead).
 *
 * Both engines share the PdfStore, which enforces the canvas memory budget.
 */

const PRELOAD = 2; // Pages rendered ahead/behind the visible ones.

/**
 * Create one page element with its canvas.
 *
 * @param {number} pageNumber 1-based page number.
 * @return {HTMLElement} Page element.
 */
function createPageEl( pageNumber ) {
	const el = document.createElement( 'div' );
	el.className = 'bindr-page';
	el.dataset.page = String( pageNumber );
	const canvas = document.createElement( 'canvas' );
	canvas.className = 'bindr-page__canvas';
	el.appendChild( canvas );
	return el;
}

/**
 * Book-spread engine backed by StPageFlip.
 */
export class FlipEngine {
	/**
	 * @param {HTMLElement} stage Stage element.
	 * @param {object}      store PDF store.
	 * @param {Object}      opts  { pageWidth, pageHeight, onTurn }.
	 */
	constructor( stage, store, opts ) {
		this.stage = stage;
		this.store = store;
		this.opts = opts;
		this.current = 1;
		this.pageEls = [];
		this.flip = null;
	}

	/**
	 * Build DOM and start StPageFlip.
	 *
	 * @param {number} startPage 1-based page to open at.
	 */
	mount( startPage ) {
		this.current = startPage;
		const book = document.createElement( 'div' );
		book.className = 'bindr-book';
		this.book = book;
		this.stage.appendChild( book );

		this.pageEls = [];
		for ( let i = 1; i <= this.store.pageCount; i++ ) {
			this.pageEls.push( createPageEl( i ) );
		}

		const { PageFlip } = window.St;
		this.flip = new PageFlip( book, {
			width: this.opts.pageWidth,
			height: this.opts.pageHeight,
			size: 'fixed',
			showCover: true,
			maxShadowOpacity: 0.4,
			flippingTime: 700,
			mobileScrollSupport: true,
			usePortrait: false,
		} );
		this.flip.loadFromHTML( this.pageEls );

		this.flip.on( 'flip', ( e ) => {
			this.current = e.data + 1;
			this.renderWindow();
			this.updateOffset();
			this.opts.onTurn( this.current );
		} );

		if ( startPage > 1 ) {
			this.flip.turnToPage( startPage - 1 );
		}
		this.renderWindow();
		this.updateOffset();
	}

	/**
	 * Center a lone cover: StPageFlip parks it on the right half of the
	 * spread (back cover on the left), so shift the book by half a page.
	 */
	updateOffset() {
		const total = this.store.pageCount;
		let shift = 0;
		if ( 1 === this.current ) {
			shift = -25; // 25% of the two-page-wide book = half a page.
		} else if ( this.current === total && 0 === total % 2 ) {
			shift = 25;
		}
		this.book.style.transform = shift ? `translateX(${ shift }%)` : '';
	}

	/**
	 * Render visible pages plus preload window; evict the rest.
	 */
	renderWindow() {
		const from = Math.max( 1, this.current - PRELOAD );
		const to = Math.min( this.store.pageCount, this.current + 1 + PRELOAD );
		this.store.evictOutside( from, to );
		// Visible pages first so preload never delays what the reader sees.
		const order = [];
		for ( let i = this.current; i <= to; i++ ) {
			order.push( i );
		}
		for ( let i = this.current - 1; i >= from; i-- ) {
			order.push( i );
		}
		order.forEach( ( pageNumber ) => {
			const el = this.pageEls[ pageNumber - 1 ];
			if ( ! el ) {
				return;
			}
			this.store.render(
				pageNumber,
				el.querySelector( 'canvas' ),
				el.clientWidth || this.opts.pageWidth
			);
		} );
	}

	/**
	 * Go forward one spread.
	 */
	next() {
		this.flip.flipNext();
	}

	/**
	 * Go back one spread.
	 */
	prev() {
		this.flip.flipPrev();
	}

	/**
	 * Jump to a page without animation.
	 *
	 * @param {number} pageNumber 1-based page.
	 */
	goTo( pageNumber ) {
		this.flip.turnToPage( pageNumber - 1 );
	}

	/**
	 * Tear down.
	 */
	destroy() {
		if ( this.flip ) {
			try {
				this.flip.destroy();
			} catch ( e ) {
				// StPageFlip can throw when destroyed before full init.
			}
			this.flip = null;
		}
		this.stage.innerHTML = '';
		this.pageEls = [];
	}
}

/**
 * Single-page engine with slide/instant transitions and touch swipe.
 */
export class SlideEngine {
	/**
	 * @param {HTMLElement} stage Stage element.
	 * @param {object}      store PDF store.
	 * @param {Object}      opts  { pageWidth, reducedMotion, onTurn }.
	 */
	constructor( stage, store, opts ) {
		this.stage = stage;
		this.store = store;
		this.opts = opts;
		this.current = 1;
		this.pageEls = [];
	}

	/**
	 * Build DOM.
	 *
	 * @param {number} startPage 1-based page to open at.
	 */
	mount( startPage ) {
		this.current = startPage;
		this.track = document.createElement( 'div' );
		this.track.className = 'bindr-slides';
		if ( this.opts.reducedMotion ) {
			this.track.classList.add( 'bindr-slides--instant' );
		}
		this.stage.appendChild( this.track );

		this.pageEls = [];
		for ( let i = 1; i <= this.store.pageCount; i++ ) {
			const el = createPageEl( i );
			el.classList.add( 'bindr-slide' );
			this.track.appendChild( el );
			this.pageEls.push( el );
		}

		this.bindSwipe();
		this.position();
		this.renderWindow();
	}

	/**
	 * Position all slides relative to the current page.
	 */
	position() {
		this.pageEls.forEach( ( el, i ) => {
			const offset = i + 1 - this.current;
			el.style.transform = `translateX(${ offset * 100 }%)`;
			el.setAttribute( 'aria-hidden', offset === 0 ? 'false' : 'true' );
		} );
	}

	/**
	 * Render current page ± preload; evict the rest.
	 */
	renderWindow() {
		const from = Math.max( 1, this.current - PRELOAD );
		const to = Math.min( this.store.pageCount, this.current + PRELOAD );
		this.store.evictOutside( from, to );
		const order = [ this.current ];
		for ( let d = 1; d <= PRELOAD; d++ ) {
			order.push( this.current + d, this.current - d );
		}
		order.forEach( ( pageNumber ) => {
			if ( pageNumber < from || pageNumber > to ) {
				return;
			}
			const el = this.pageEls[ pageNumber - 1 ];
			this.store.render(
				pageNumber,
				el.querySelector( 'canvas' ),
				this.opts.pageWidth
			);
		} );
	}

	/**
	 * Touch/pointer swipe navigation.
	 */
	bindSwipe() {
		let startX = null;
		this.track.addEventListener(
			'pointerdown',
			( e ) => {
				startX = e.clientX;
			},
			{ passive: true }
		);
		this.track.addEventListener(
			'pointerup',
			( e ) => {
				if ( null === startX ) {
					return;
				}
				const delta = e.clientX - startX;
				startX = null;
				if ( Math.abs( delta ) < 40 ) {
					return;
				}
				if ( delta < 0 ) {
					this.next();
				} else {
					this.prev();
				}
			},
			{ passive: true }
		);
	}

	/**
	 * Go to next page.
	 */
	next() {
		this.goTo( this.current + 1 );
	}

	/**
	 * Go to previous page.
	 */
	prev() {
		this.goTo( this.current - 1 );
	}

	/**
	 * Jump to a page.
	 *
	 * @param {number} pageNumber 1-based page.
	 */
	goTo( pageNumber ) {
		const target = Math.min(
			Math.max( 1, pageNumber ),
			this.store.pageCount
		);
		if ( target === this.current ) {
			return;
		}
		this.current = target;
		this.position();
		this.renderWindow();
		this.opts.onTurn( this.current );
	}

	/**
	 * Tear down.
	 */
	destroy() {
		this.stage.innerHTML = '';
		this.pageEls = [];
	}
}
