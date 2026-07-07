/**
 * Flipbook edit screen: Media Library PDF picker, client-side page count /
 * page size probe via PDF.js (so the server never needs Imagick), live
 * preview of page 1, and the color picker for the background option.
 */
import './index.scss';

/* global jQuery, bindrAdminEdit, pdfjsLib */

const strings = window.bindrAdminEdit ? bindrAdminEdit.strings : {};

/**
 * Render page 1 of the selected PDF into the preview box and fill the
 * hidden page-count / page-size fields.
 *
 * @param {string}      url PDF URL.
 * @param {HTMLElement} box Picker root.
 */
async function probePdf( url, box ) {
	const preview = box.querySelector( '[data-bindr-preview]' );
	const countEl = box.querySelector( '[data-bindr-pagecount]' );
	const countField = box.querySelector( '[data-bindr-field="page_count"]' );
	const sizeField = box.querySelector( '[data-bindr-field="page_size"]' );

	preview.innerHTML = '';
	try {
		pdfjsLib.GlobalWorkerOptions.workerSrc = bindrAdminEdit.workerSrc;
		const doc = await pdfjsLib.getDocument( { url } ).promise;

		countField.value = String( doc.numPages );
		countEl.textContent = strings.pages.replace(
			'%s',
			String( doc.numPages )
		);

		const page = await doc.getPage( 1 );
		const base = page.getViewport( { scale: 1 } );
		sizeField.value = `${ Math.round( base.width ) }x${ Math.round(
			base.height
		) }`;

		const scale = 220 / base.width;
		const viewport = page.getViewport( { scale } );
		const canvas = document.createElement( 'canvas' );
		canvas.width = viewport.width;
		canvas.height = viewport.height;
		await page.render( {
			canvasContext: canvas.getContext( '2d' ),
			viewport,
		} ).promise;
		preview.appendChild( canvas );
		doc.destroy();
	} catch ( e ) {
		preview.textContent = strings.loadError || '';
	}
}

function initPicker() {
	const box = document.querySelector( '[data-bindr-pdf-picker]' );
	if ( ! box ) {
		return;
	}

	const idField = box.querySelector( '[data-bindr-field="pdf_id"]' );
	const current = box.querySelector( '[data-bindr-current]' );
	const filename = box.querySelector( '[data-bindr-filename]' );
	const removeBtn = box.querySelector( '[data-bindr-remove]' );
	const preview = box.querySelector( '[data-bindr-preview]' );

	// Existing PDF: render its preview on load.
	if ( preview && preview.dataset.pdfUrl ) {
		probePdf( preview.dataset.pdfUrl, box );
	}

	let frame = null;
	box.querySelector( '[data-bindr-select]' ).addEventListener(
		'click',
		() => {
			if ( ! frame ) {
				frame = wp.media( {
					title: strings.selectTitle,
					button: { text: strings.selectButton },
					multiple: false,
					library: { type: 'application/pdf' },
				} );
				frame.on( 'select', () => {
					const file = frame
						.state()
						.get( 'selection' )
						.first()
						.toJSON();
					idField.value = String( file.id );
					filename.textContent = file.filename;
					current.hidden = false;
					removeBtn.hidden = false;
					probePdf( file.url, box );
				} );
			}
			frame.open();
		}
	);

	removeBtn.addEventListener( 'click', () => {
		idField.value = '0';
		box.querySelector( '[data-bindr-field="page_count"]' ).value = '0';
		box.querySelector( '[data-bindr-field="page_size"]' ).value = '';
		current.hidden = true;
		removeBtn.hidden = true;
	} );
}

function initColorPicker() {
	if ( window.jQuery && jQuery.fn.wpColorPicker ) {
		jQuery( '.bindr-color-field' ).wpColorPicker();
	}
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', () => {
		initPicker();
		initColorPicker();
	} );
} else {
	initPicker();
	initColorPicker();
}
