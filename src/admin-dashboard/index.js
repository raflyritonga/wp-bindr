/**
 * Analytics dashboard: hand-rolled SVG time-series chart. No chart library —
 * the data is a small per-day series and a bar chart is enough.
 */
import './index.scss';

const SVG_NS = 'http://www.w3.org/2000/svg';

/**
 * Build the list of days between from and to (inclusive), filling gaps
 * with zero so the x-axis is continuous.
 *
 * @param {Array}  series Rows { day, opens }.
 * @param {string} from   Y-m-d.
 * @param {string} to     Y-m-d.
 * @return {Array} Filled rows.
 */
function fillDays( series, from, to ) {
	const byDay = {};
	series.forEach( ( row ) => {
		byDay[ row.day ] = row;
	} );
	const out = [];
	const cursor = new Date( `${ from }T00:00:00Z` );
	const end = new Date( `${ to }T00:00:00Z` );
	while ( cursor <= end ) {
		const key = cursor.toISOString().slice( 0, 10 );
		out.push( byDay[ key ] || { day: key, opens: 0 } );
		cursor.setUTCDate( cursor.getUTCDate() + 1 );
	}
	return out;
}

function el( name, attrs ) {
	const node = document.createElementNS( SVG_NS, name );
	Object.keys( attrs ).forEach( ( key ) =>
		node.setAttribute( key, attrs[ key ] )
	);
	return node;
}

function renderChart( container ) {
	let series;
	try {
		series = JSON.parse( container.dataset.series );
	} catch ( e ) {
		return;
	}
	const days = fillDays(
		series,
		container.dataset.from,
		container.dataset.to
	);

	const width = 800;
	const height = 220;
	const pad = { top: 10, right: 10, bottom: 24, left: 36 };
	const chartW = width - pad.left - pad.right;
	const chartH = height - pad.top - pad.bottom;
	const max = Math.max( 1, ...days.map( ( d ) => d.opens ) );

	const svg = el( 'svg', {
		viewBox: `0 0 ${ width } ${ height }`,
		class: 'bindr-chart',
		role: 'img',
	} );

	// Y gridlines + labels.
	const steps = 4;
	for ( let i = 0; i <= steps; i++ ) {
		const value = Math.round( ( max / steps ) * i );
		const y = pad.top + chartH - ( chartH / steps ) * i;
		svg.appendChild(
			el( 'line', {
				x1: pad.left,
				x2: width - pad.right,
				y1: y,
				y2: y,
				class: 'bindr-chart__grid',
			} )
		);
		const label = el( 'text', {
			x: pad.left - 6,
			y: y + 4,
			'text-anchor': 'end',
			class: 'bindr-chart__label',
		} );
		label.textContent = String( value );
		svg.appendChild( label );
	}

	// Bars.
	const step = chartW / days.length;
	const barW = Math.max( 2, step * 0.7 );
	days.forEach( ( day, i ) => {
		const h = ( day.opens / max ) * chartH;
		const bar = el( 'rect', {
			x: pad.left + i * step + ( step - barW ) / 2,
			y: pad.top + chartH - h,
			width: barW,
			height: Math.max( day.opens > 0 ? 2 : 0, h ),
			class: 'bindr-chart__bar',
		} );
		const title = document.createElementNS( SVG_NS, 'title' );
		title.textContent = `${ day.day }: ${ day.opens }`;
		bar.appendChild( title );
		svg.appendChild( bar );
	} );

	// X labels: first, middle, last.
	[ 0, Math.floor( days.length / 2 ), days.length - 1 ].forEach( ( i ) => {
		if ( ! days[ i ] ) {
			return;
		}
		const label = el( 'text', {
			x: pad.left + i * step + step / 2,
			y: height - 6,
			'text-anchor': 'middle',
			class: 'bindr-chart__label',
		} );
		label.textContent = days[ i ].day.slice( 5 );
		svg.appendChild( label );
	} );

	container.appendChild( svg );
}

const chart = document.getElementById( 'bindr-chart' );
if ( chart ) {
	renderChart( chart );
}
