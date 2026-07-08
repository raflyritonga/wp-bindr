/**
 * The flipbook viewer: toolbar, stage, engine selection, keyboard access,
 * fullscreen, zoom, and analytics wiring.
 */
import { PdfStore } from './store';
import { FlipEngine, SlideEngine } from './engines';
import { Analytics } from './analytics';

const MOBILE_BREAKPOINT = 768;
const MAX_PAGE_WIDTH = 900;
const STAGE_PADDING = 24; // Keep in sync with .bindr-stage padding in index.scss.

const ICONS = {
	prev: '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M15.4 7.4 14 6l-6 6 6 6 1.4-1.4L10.8 12z"/></svg>',
	next: '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M8.6 16.6 10 18l6-6-6-6-1.4 1.4 4.6 4.6z"/></svg>',
	zoomIn: '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M15.5 14h-.8l-.3-.3a6.5 6.5 0 1 0-.7.7l.3.3v.8l5 5 1.5-1.5-5-5zm-6 0a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9zm-.5-7h1v2h2v1h-2v2h-1v-2h-2v-1h2z"/></svg>',
	zoomOut:
		'<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M15.5 14h-.8l-.3-.3a6.5 6.5 0 1 0-.7.7l.3.3v.8l5 5 1.5-1.5-5-5zm-6 0a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9zM7 9h5v1H7z"/></svg>',
	fullscreen:
		'<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>',
	pages: '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M4 5h7v14H4zm9 0h7v14h-7zm-7 2v10h3V7zm9 0v10h3V7z"/></svg>',
	download:
		'<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 16l-5-5 1.4-1.4 2.6 2.6V4h2v8.2l2.6-2.6L17 11zm-7 2h14v2H5z"/></svg>',
};

/**
 * printf-lite for the translated "%1$s / %2$s" style strings.
 *
 * @param {string} template Translated template.
 * @param {...*}   values   Replacement values.
 * @return {string} Formatted string.
 */
function format( template, ...values ) {
	let i = 0;
	return template.replace( /%(\d+\$)?s/g, ( match, pos ) => {
		const index = pos ? parseInt( pos, 10 ) - 1 : i++;
		return String( values[ index ] );
	} );
}

export class Viewer {
	/**
	 * @param {HTMLElement} root   The .bindr-viewer element.
	 * @param {Object}      config Parsed data-bindr-config.
	 */
	constructor( root, config ) {
		this.root = root;
		this.config = config;
		this.strings = config.strings || {};
		this.store = new PdfStore( config );
		this.analytics = new Analytics( config );
		this.engine = null;
		this.mode = config.display === 'single' ? 'single' : 'double';
		this.zoom = 1;
		this.seenPages = new Set();
		this.destroyed = false;
		this.reducedMotion = window.matchMedia(
			'(prefers-reduced-motion: reduce)'
		).matches;
	}

	/**
	 * Load the PDF and boot the UI.
	 */
	async init() {
		this.buildSkeleton();
		this.analytics.trackOnce( 'open' );
		if ( this.config.fullpage ) {
			this.analytics.trackOnce( 'fullpage_open' );
		}

		try {
			await this.store.load( ( ratio ) => this.setProgress( ratio ) );
		} catch ( e ) {
			this.showError();
			return;
		}
		if ( this.destroyed ) {
			return;
		}

		this.root.classList.add( 'bindr-viewer--ready' );
		const placeholder = this.root.querySelector(
			'.bindr-viewer__placeholder'
		);
		if ( placeholder ) {
			placeholder.remove();
		}

		this.markSeen( 1 );
		this.mountEngine( 1 );
		this.updateToolbar();
		this.observeResize();
	}

	/**
	 * Build toolbar + stage inside the root container.
	 */
	buildSkeleton() {
		const s = this.strings;
		this.stage = document.createElement( 'div' );
		this.stage.className = 'bindr-stage';

		this.zoomPane = document.createElement( 'div' );
		this.zoomPane.className = 'bindr-zoom';
		this.stage.appendChild( this.zoomPane );

		this.progressEl = document.createElement( 'div' );
		this.progressEl.className = 'bindr-progress';
		this.progressEl.innerHTML =
			'<span class="bindr-progress__bar"></span><span class="bindr-progress__label"></span>';
		this.progressEl.querySelector( '.bindr-progress__label' ).textContent =
			s.loading || '';
		this.stage.appendChild( this.progressEl );

		this.toolbar = document.createElement( 'div' );
		this.toolbar.className = 'bindr-toolbar';

		this.btnPrev = this.button( 'prev', s.prev, () => this.prev() );
		this.btnNext = this.button( 'next', s.next, () => this.next() );

		this.pageBox = document.createElement( 'span' );
		this.pageBox.className = 'bindr-toolbar__pages';
		this.pageInput = document.createElement( 'input' );
		this.pageInput.type = 'number';
		this.pageInput.min = '1';
		this.pageInput.className = 'bindr-toolbar__page-input';
		this.pageInput.setAttribute( 'aria-label', s.goToPage || 'Go to page' );
		this.pageInput.addEventListener( 'change', () => {
			const n = parseInt( this.pageInput.value, 10 );
			if ( n && this.engine ) {
				this.engine.goTo( n );
			}
		} );
		this.pageTotal = document.createElement( 'span' );
		this.pageTotal.className = 'bindr-toolbar__page-total';
		this.pageBox.appendChild( this.pageInput );
		this.pageBox.appendChild( this.pageTotal );

		this.btnZoomOut = this.button( 'zoomOut', s.zoomOut, () =>
			this.setZoom( this.zoom - 0.25 )
		);
		this.btnZoomIn = this.button( 'zoomIn', s.zoomIn, () =>
			this.setZoom( this.zoom + 0.25 )
		);
		this.btnMode = this.button( 'pages', s.singleDouble, () =>
			this.toggleMode()
		);
		this.btnMode.setAttribute( 'aria-pressed', 'false' );
		this.btnFullscreen = this.button( 'fullscreen', s.fullscreen, () =>
			this.toggleFullscreen()
		);

		this.toolbar.appendChild( this.btnPrev );
		this.toolbar.appendChild( this.pageBox );
		this.toolbar.appendChild( this.btnNext );
		this.toolbar.appendChild( this.btnZoomOut );
		this.toolbar.appendChild( this.btnZoomIn );
		this.toolbar.appendChild( this.btnMode );
		this.toolbar.appendChild( this.btnFullscreen );

		if ( this.config.download ) {
			const link = document.createElement( 'a' );
			link.className = 'bindr-toolbar__btn';
			link.href = this.config.pdfUrl;
			link.setAttribute( 'download', '' );
			link.setAttribute( 'aria-label', s.download || 'Download PDF' );
			link.title = s.download || '';
			link.innerHTML = ICONS.download;
			link.addEventListener( 'click', () => {
				this.analytics.track( 'download' );
				this.analytics.flush( false );
			} );
			this.toolbar.appendChild( link );
		}

		this.root.appendChild( this.stage );
		this.root.appendChild( this.toolbar );

		this.root.tabIndex = 0;
		this.root.addEventListener( 'keydown', ( e ) => this.onKey( e ) );
	}

	/**
	 * Create a toolbar button.
	 *
	 * @param {string}   icon    Icon key.
	 * @param {string}   label   Accessible label.
	 * @param {Function} onClick Click handler.
	 * @return {HTMLButtonElement} Button.
	 */
	button( icon, label, onClick ) {
		const btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'bindr-toolbar__btn';
		btn.setAttribute( 'aria-label', label || icon );
		btn.title = label || '';
		btn.innerHTML = ICONS[ icon ];
		btn.addEventListener( 'click', onClick );
		return btn;
	}

	/**
	 * Loading progress (ratio 0..1, or -1 when length is unknown).
	 *
	 * @param {number} ratio Progress ratio.
	 */
	setProgress( ratio ) {
		if ( ! this.progressEl ) {
			return;
		}
		const bar = this.progressEl.querySelector( '.bindr-progress__bar' );
		if ( ratio >= 0 ) {
			bar.style.width = `${ Math.round( ratio * 100 ) }%`;
		} else {
			bar.classList.add( 'bindr-progress__bar--indeterminate' );
		}
	}

	/**
	 * Fatal load error: keep the page usable with a plain PDF link.
	 */
	showError() {
		const s = this.strings;
		this.stage.innerHTML = '';
		const box = document.createElement( 'div' );
		box.className = 'bindr-error';
		const msg = document.createElement( 'p' );
		msg.textContent = s.loadError || 'Error';
		const link = document.createElement( 'a' );
		link.href = this.config.pdfUrl;
		link.textContent = s.openPdf || 'Open PDF';
		box.appendChild( msg );
		box.appendChild( link );
		this.stage.appendChild( box );
	}

	/**
	 * Whether the double-page engine should be used right now.
	 *
	 * @return {boolean} True for the flip engine.
	 */
	wantsFlip() {
		return (
			'double' === this.mode &&
			! this.reducedMotion &&
			this.root.clientWidth >= MOBILE_BREAKPOINT &&
			this.store.pageCount > 1
		);
	}

	/**
	 * Compute page dimensions and mount the right engine.
	 *
	 * @param {number} startPage Page to open at.
	 */
	mountEngine( startPage ) {
		if ( this.engine ) {
			this.engine.destroy();
			this.engine = null;
		}

		const flip = this.wantsFlip();
		const ratio = this.config.ratio || 1.414;
		const rootW = this.root.clientWidth || 600;
		const availW = Math.max( 120, rootW - 2 * STAGE_PADDING );

		// Stage height: fill the root in fullpage/fixed/fullscreen layouts,
		// otherwise derive from width + ratio, capped to the viewport.
		let innerH;
		if (
			this.config.fullpage ||
			this.isFullscreen() ||
			this.root.classList.contains( 'bindr-viewer--fixed' )
		) {
			this.stage.style.height = '';
			innerH = Math.max(
				120,
				( this.stage.clientHeight || 400 ) - 2 * STAGE_PADDING
			);
		} else {
			innerH = Math.min(
				( flip ? availW / 2 : availW ) * ratio,
				window.innerHeight * 0.85
			);
			this.stage.style.height = `${ Math.round(
				innerH + 2 * STAGE_PADDING
			) }px`;
		}

		const pageW = Math.min(
			flip ? availW / 2 : availW,
			innerH / ratio,
			MAX_PAGE_WIDTH
		);
		const pageH = pageW * ratio;

		const onTurn = ( page ) => this.onTurn( page );

		if ( flip ) {
			this.engine = new FlipEngine( this.zoomPane, this.store, {
				pageWidth: Math.floor( pageW ),
				pageHeight: Math.floor( pageH ),
				onTurn,
			} );
		} else {
			this.engine = new SlideEngine( this.zoomPane, this.store, {
				pageWidth: Math.floor( pageW ),
				reducedMotion: this.reducedMotion,
				onTurn,
			} );
		}
		this.engine.mount( startPage );
		this.updateToolbar();
	}

	/**
	 * Page turn handler: analytics + toolbar.
	 *
	 * @param {number} page Current 1-based page.
	 */
	onTurn( page ) {
		this.markSeen( page );
		this.analytics.track( 'page_turn', page );
		this.updateToolbar();
	}

	/**
	 * Track seen pages; fire `complete` at ≥ 90% of pages seen.
	 *
	 * @param {number} page Current page (left page of the spread).
	 */
	markSeen( page ) {
		this.seenPages.add( page );
		if ( this.wantsFlip() && page + 1 <= this.store.pageCount ) {
			this.seenPages.add( page + 1 );
		}
		const total = this.store.pageCount || this.config.pageCount;
		if ( total > 0 && this.seenPages.size >= Math.ceil( total * 0.9 ) ) {
			this.analytics.trackOnce( 'complete', page );
		}
	}

	/**
	 * Refresh the toolbar page indicator.
	 */
	updateToolbar() {
		const total = this.store.pageCount;
		const current = this.engine ? this.engine.current : 1;
		this.pageInput.value = String( current );
		this.pageInput.max = String( total );
		this.pageTotal.textContent = ` / ${ total }`;
		this.pageBox.setAttribute(
			'aria-label',
			format( this.strings.pageOf || '%1$s/%2$s', current, total )
		);
		this.btnPrev.disabled = current <= 1;
		this.btnNext.disabled = current >= total;
		this.btnMode.setAttribute(
			'aria-pressed',
			'single' === this.mode ? 'true' : 'false'
		);
	}

	/**
	 * Next spread/page.
	 */
	next() {
		if ( this.engine ) {
			this.engine.next();
		}
	}

	/**
	 * Previous spread/page.
	 */
	prev() {
		if ( this.engine ) {
			this.engine.prev();
		}
	}

	/**
	 * Toggle single/double page mode.
	 */
	toggleMode() {
		this.mode = 'single' === this.mode ? 'double' : 'single';
		this.mountEngine( this.engine ? this.engine.current : 1 );
	}

	/**
	 * Apply zoom via CSS transform; the stage scrolls when zoomed in.
	 *
	 * @param {number} level New zoom level.
	 */
	setZoom( level ) {
		this.zoom = Math.min( 3, Math.max( 1, level ) );
		this.zoomPane.style.transform =
			1 === this.zoom ? '' : `scale(${ this.zoom })`;
		this.stage.classList.toggle( 'bindr-stage--zoomed', this.zoom > 1 );
		this.btnZoomOut.disabled = this.zoom <= 1;
		this.btnZoomIn.disabled = this.zoom >= 3;
	}

	/**
	 * Whether this viewer currently fills the screen (real or fake fullscreen).
	 *
	 * @return {boolean} True when fullscreen.
	 */
	isFullscreen() {
		return (
			document.fullscreenElement === this.root ||
			this.root.classList.contains( 'bindr-viewer--fs-fake' )
		);
	}

	/**
	 * Fullscreen with an iOS-Safari CSS fallback.
	 */
	toggleFullscreen() {
		const doc = document;
		if ( this.root.requestFullscreen ) {
			if ( doc.fullscreenElement === this.root ) {
				doc.exitFullscreen();
			} else {
				this.root.requestFullscreen().catch( () => {} );
			}
			return;
		}
		this.root.classList.toggle( 'bindr-viewer--fs-fake' );
		this.relayout();
	}

	/**
	 * Keyboard navigation.
	 *
	 * @param {KeyboardEvent} e Event.
	 */
	onKey( e ) {
		if ( e.target === this.pageInput ) {
			return;
		}
		switch ( e.key ) {
			case 'ArrowLeft':
				this.prev();
				e.preventDefault();
				break;
			case 'ArrowRight':
				this.next();
				e.preventDefault();
				break;
			case '+':
			case '=':
				this.setZoom( this.zoom + 0.25 );
				e.preventDefault();
				break;
			case '-':
				this.setZoom( this.zoom - 0.25 );
				e.preventDefault();
				break;
		}
	}

	/**
	 * Watch container size (themes, tabs, accordions). Re-mount the engine
	 * when the width actually changes; ignore hidden (0-width) states.
	 */
	observeResize() {
		let lastWidth = this.root.clientWidth;
		let timer = null;

		const onResize = () => {
			const width = this.root.clientWidth;
			if ( ! width || Math.abs( width - lastWidth ) < 24 ) {
				return;
			}
			lastWidth = width;
			this.relayout();
		};

		if ( 'undefined' !== typeof ResizeObserver ) {
			this.resizeObserver = new ResizeObserver( () => {
				window.clearTimeout( timer );
				timer = window.setTimeout( onResize, 200 );
			} );
			this.resizeObserver.observe( this.root );
		} else {
			window.addEventListener( 'resize', () => {
				window.clearTimeout( timer );
				timer = window.setTimeout( onResize, 200 );
			} );
		}

		// Fullscreen can keep the same width, so the width-based observer
		// alone would skip this relayout.
		this.onFullscreenChange = () => {
			this.root.classList.toggle(
				'bindr-viewer--fs',
				document.fullscreenElement === this.root
			);
			this.relayout();
		};
		document.addEventListener(
			'fullscreenchange',
			this.onFullscreenChange
		);

		// Hidden-then-shown containers (tabs, accordions) report width 0 at
		// init time; re-measure when the viewer becomes visible.
		if ( 'undefined' !== typeof IntersectionObserver ) {
			this.intersectionObserver = new IntersectionObserver(
				( entries ) => {
					entries.forEach( ( entry ) => {
						if ( entry.isIntersecting ) {
							onResize();
						}
					} );
				}
			);
			this.intersectionObserver.observe( this.root );
		}
	}

	/**
	 * Re-mount the engine at the current page (after size/mode changes).
	 */
	relayout() {
		if ( this.engine ) {
			this.mountEngine( this.engine.current );
		}
	}

	/**
	 * Tear everything down.
	 */
	destroy() {
		this.destroyed = true;
		this.analytics.flush( true );
		if ( this.resizeObserver ) {
			this.resizeObserver.disconnect();
		}
		if ( this.intersectionObserver ) {
			this.intersectionObserver.disconnect();
		}
		if ( this.onFullscreenChange ) {
			document.removeEventListener(
				'fullscreenchange',
				this.onFullscreenChange
			);
		}
		if ( this.engine ) {
			this.engine.destroy();
		}
		this.store.destroy();
	}
}
