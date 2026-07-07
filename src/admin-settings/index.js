/**
 * Settings page: color picker + full-page logo media picker.
 */

/* global jQuery */

function initLogoPicker() {
	const box = document.querySelector( '[data-bindr-logo-picker]' );
	if ( ! box ) {
		return;
	}
	const idField = box.querySelector( '[data-bindr-logo-id]' );
	const previewP = box.querySelector( '[data-bindr-logo-preview]' );
	const removeBtn = box.querySelector( '[data-bindr-logo-remove]' );

	let frame = null;
	box.querySelector( '[data-bindr-logo-select]' ).addEventListener(
		'click',
		() => {
			if ( ! frame ) {
				frame = wp.media( {
					multiple: false,
					library: { type: 'image' },
				} );
				frame.on( 'select', () => {
					const file = frame
						.state()
						.get( 'selection' )
						.first()
						.toJSON();
					idField.value = String( file.id );
					previewP.querySelector( 'img' ).src =
						( file.sizes &&
							file.sizes.medium &&
							file.sizes.medium.url ) ||
						file.url;
					previewP.hidden = false;
					removeBtn.hidden = false;
				} );
			}
			frame.open();
		}
	);

	removeBtn.addEventListener( 'click', () => {
		idField.value = '0';
		previewP.hidden = true;
		removeBtn.hidden = true;
	} );
}

function init() {
	initLogoPicker();
	if ( window.jQuery && jQuery.fn.wpColorPicker ) {
		jQuery( '.bindr-color-field' ).wpColorPicker();
	}
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
