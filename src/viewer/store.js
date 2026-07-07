/**
 * PDF document store: loads via PDF.js and renders pages into canvases with
 * a strict memory budget — at most MAX_LIVE_CANVASES rendered canvases exist
 * at any moment, regardless of flipbook length.
 */

const MAX_LIVE_CANVASES = 6;
const DPR_CAP = 2;

export class PdfStore {
	/**
	 * @param {Object} config Viewer config (pdfUrl, workerSrc).
	 */
	constructor( config ) {
		this.config = config;
		this.doc = null;
		this.live = new Map(); // pageNumber -> canvas
		this.tasks = new Map(); // pageNumber -> render task
	}

	/**
	 * Load the document.
	 *
	 * @param {Function} onProgress Progress callback (0..1 or -1 if unknown).
	 * @return {Promise<Object>} PDF.js document proxy.
	 */
	async load( onProgress ) {
		const pdfjsLib = window.pdfjsLib;
		pdfjsLib.GlobalWorkerOptions.workerSrc = this.config.workerSrc;

		// disableAutoFetch + streaming lets PDF.js use HTTP range requests
		// when the server supports them, so page 1 shows before the whole
		// file arrives. When unsupported it falls back to full download.
		const task = pdfjsLib.getDocument( {
			url: this.config.pdfUrl,
			disableAutoFetch: false,
			isEvalSupported: false,
		} );
		task.onProgress = ( { loaded, total } ) => {
			onProgress( total ? Math.min( 1, loaded / total ) : -1 );
		};
		this.doc = await task.promise;
		return this.doc;
	}

	/**
	 * Number of pages (0 before load).
	 *
	 * @return {number} Page count.
	 */
	get pageCount() {
		return this.doc ? this.doc.numPages : 0;
	}

	/**
	 * Width/height of page 1 at scale 1, used for ratio.
	 *
	 * @return {Promise<{width: number, height: number}>} Size in PDF points.
	 */
	async baseSize() {
		const page = await this.doc.getPage( 1 );
		const viewport = page.getViewport( { scale: 1 } );
		return { width: viewport.width, height: viewport.height };
	}

	/**
	 * Render a page into a canvas sized for the given CSS box.
	 * No-op if this page is already rendered at a compatible size.
	 *
	 * @param {number}            pageNumber 1-based page number.
	 * @param {HTMLCanvasElement} canvas     Target canvas.
	 * @param {number}            cssWidth   Layout width in CSS px.
	 * @return {Promise<void>} Resolves when drawn.
	 */
	async render( pageNumber, canvas, cssWidth ) {
		if ( ! this.doc || pageNumber < 1 || pageNumber > this.pageCount ) {
			return;
		}
		const dpr = Math.min( window.devicePixelRatio || 1, DPR_CAP );
		const targetWidth = Math.max( 1, Math.round( cssWidth * dpr ) );
		if (
			canvas.width === targetWidth &&
			canvas.dataset.bindrRendered === '1'
		) {
			return;
		}
		if ( this.tasks.has( pageNumber ) ) {
			return; // Already rendering.
		}

		try {
			const page = await this.doc.getPage( pageNumber );
			const base = page.getViewport( { scale: 1 } );
			const scale = targetWidth / base.width;
			const viewport = page.getViewport( { scale } );

			canvas.width = viewport.width;
			canvas.height = viewport.height;

			const task = page.render( {
				canvasContext: canvas.getContext( '2d' ),
				viewport,
			} );
			this.tasks.set( pageNumber, task );
			await task.promise;
			canvas.dataset.bindrRendered = '1';
			this.live.set( pageNumber, canvas );
		} catch ( e ) {
			// Rendering can be cancelled mid-flight; leave the canvas blank.
		} finally {
			this.tasks.delete( pageNumber );
		}
	}

	/**
	 * Free canvases outside the keep window. Setting canvas dimensions to 0
	 * releases the backing bitmap immediately.
	 *
	 * @param {number} from First page to keep.
	 * @param {number} to   Last page to keep.
	 */
	evictOutside( from, to ) {
		this.live.forEach( ( canvas, pageNumber ) => {
			if ( pageNumber < from || pageNumber > to ) {
				canvas.width = 0;
				canvas.height = 0;
				delete canvas.dataset.bindrRendered;
				this.live.delete( pageNumber );
			}
		} );
		// Hard cap regardless of window size.
		while ( this.live.size > MAX_LIVE_CANVASES ) {
			const oldest = this.live.keys().next().value;
			const canvas = this.live.get( oldest );
			canvas.width = 0;
			canvas.height = 0;
			delete canvas.dataset.bindrRendered;
			this.live.delete( oldest );
		}
	}

	/**
	 * Cancel work and drop references.
	 */
	destroy() {
		this.tasks.forEach( ( task ) => {
			if ( task && task.cancel ) {
				task.cancel();
			}
		} );
		this.tasks.clear();
		this.live.clear();
		if ( this.doc ) {
			this.doc.destroy();
			this.doc = null;
		}
	}
}
