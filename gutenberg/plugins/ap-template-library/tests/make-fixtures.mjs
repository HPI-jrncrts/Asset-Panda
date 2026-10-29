// TEST ONLY — writes the fixture images the reference pages use, and the PNG the e2e test
// uploads through the media modal.
//
//   node make-fixtures.mjs <fixtures dir> <out dir>
import { createRequire } from 'node:module';
import fs from 'node:fs';
import path from 'node:path';

const require = createRequire( 'C:/Sample Code/package.json' );
const { PNG } = require( 'pngjs' );
const [ , , FIX, OUT ] = process.argv;
fs.mkdirSync( FIX, { recursive: true } );
fs.mkdirSync( OUT, { recursive: true } );

function draw( file, w, h, paint ) {
	const png = new PNG( { width: w, height: h } );
	for ( let y = 0; y < h; y++ ) {
		for ( let x = 0; x < w; x++ ) {
			const [ r, g, b, a = 255 ] = paint( x, y, w, h );
			const i = ( w * y + x ) << 2;
			png.data[ i ] = r;
			png.data[ i + 1 ] = g;
			png.data[ i + 2 ] = b;
			png.data[ i + 3 ] = a;
		}
	}
	fs.writeFileSync( file, PNG.sync.write( png ) );
}
const mix = ( a, b, t ) => a.map( ( v, i ) => Math.round( v + ( b[ i ] - v ) * t ) );
const INDIGO = [ 80, 68, 225 ];
const GREEN = [ 0, 169, 145 ];
const AMBER = [ 222, 141, 20 ];
const WHITE = [ 255, 255, 255 ];
const disc = ( c ) => ( x, y, w, h ) => {
	const d = Math.hypot( x - w / 2, y - h / 2 ) / ( Math.min( w, h ) / 2 );
	return d <= 1 ? [ ...mix( c, WHITE, d * 0.35 ), 255 ] : [ 0, 0, 0, 0 ];
};
const grad = ( a, b ) => ( x, y, w, h ) => mix( a, b, ( x / w + y / h ) / 2 );
const panel = ( x, y, w, h ) => {
	const row = Math.floor( y / ( h / 6 ) );
	if ( y < h * 0.12 ) {
		return INDIGO;
	}
	return ( x > w * 0.06 && x < w * 0.94 && y % ( h / 6 ) > 12 && row > 0 ) ? mix( WHITE, GREEN, ( row % 3 ) * 0.12 ) : [ 240, 241, 245 ];
};

draw( path.join( FIX, 'badge-a.png' ), 96, 96, disc( INDIGO ) );
draw( path.join( FIX, 'badge-b.png' ), 96, 96, disc( GREEN ) );
draw( path.join( FIX, 'icon-a.png' ), 64, 64, disc( AMBER ) );
draw( path.join( FIX, 'icon-b.png' ), 64, 64, disc( [ 56, 160, 220 ] ) );
draw( path.join( FIX, 'icon-c.png' ), 64, 64, disc( INDIGO ) );
draw( path.join( FIX, 'dashboard.png' ), 800, 500, panel );
draw( path.join( FIX, 'person.png' ), 300, 520, ( x, y, w, h ) => ( Math.hypot( x - w / 2, y - h * 0.22 ) < w * 0.22 || ( y > h * 0.42 && Math.abs( x - w / 2 ) < w * ( 0.18 + ( y - h * 0.42 ) / h * 0.5 ) ) ) ? [ 60, 64, 80, 255 ] : [ 0, 0, 0, 0 ] );
draw( path.join( FIX, 'logo-a.png' ), 240, 80, grad( [ 120, 120, 130 ], [ 180, 180, 190 ] ) );
draw( path.join( FIX, 'logo-b.png' ), 240, 80, grad( [ 90, 100, 120 ], [ 160, 170, 190 ] ) );
draw( path.join( FIX, 'logo-c.png' ), 240, 80, grad( [ 110, 90, 90 ], [ 190, 160, 160 ] ) );
draw( path.join( FIX, 'card-a.png' ), 600, 400, grad( INDIGO, GREEN ) );
draw( path.join( FIX, 'card-b.png' ), 600, 400, grad( GREEN, AMBER ) );
// The image the e2e test uploads: bright orange with diagonal stripes, easy to spot in screenshots.
draw( path.join( OUT, 'ap-tl-upload-test.png' ), 640, 400, ( x, y ) => ( ( x + y ) % 80 < 40 ? [ 255, 112, 0 ] : [ 255, 196, 0 ] ) );
console.log( 'fixtures written' );
