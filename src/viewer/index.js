/**
 * Front-end boot: find every .bindr-viewer container and start a viewer when
 * it approaches the viewport. Exposes a single window.Bindr namespace.
 */
import { Viewer } from './viewer';
import './index.scss';

const instances = new WeakMap();

/**
 * Boot one container.
 *
 * @param {HTMLElement} root Viewer container.
 */
function boot( root ) {
	if ( instances.has( root ) ) {
		return;
	}
	let config;
	try {
		config = JSON.parse( root.dataset.bindrConfig );
	} catch ( e ) {
		return;
	}
	const viewer = new Viewer( root, config );
	instances.set( root, viewer );
	viewer.init();
}

/**
 * Scan the document (or a subtree) for viewer containers. Public API so
 * page builders that inject content late can re-scan.
 *
 * @param {HTMLElement|Document} scope Root to scan.
 */
function scan( scope = document ) {
	const roots = scope.querySelectorAll( '.bindr-viewer[data-bindr-config]' );
	if ( ! roots.length ) {
		return;
	}

	// Defer PDF loading until the viewer is near the viewport so pages with
	// below-the-fold embeds do not pay the download cost upfront.
	if ( 'undefined' !== typeof IntersectionObserver ) {
		const io = new IntersectionObserver(
			( entries, observer ) => {
				entries.forEach( ( entry ) => {
					if ( entry.isIntersecting ) {
						observer.unobserve( entry.target );
						boot( entry.target );
					}
				} );
			},
			{ rootMargin: '400px' }
		);
		roots.forEach( ( root ) => io.observe( root ) );
	} else {
		roots.forEach( boot );
	}
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', () => scan() );
} else {
	scan();
}

window.Bindr = window.Bindr || {};
window.Bindr.scan = scan;
