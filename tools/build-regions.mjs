/**
 * Erzeugt data/regions.php und data/ccc-map.php aus der DWD-Warncell-Liste.
 *
 * Quelle: https://www.dwd.de/DE/leistungen/opendata/help/warnungen/cap_warncellids_csv.csv
 * Aufruf:  npm run build:regions            (nutzt tools/data/cap_warncellids.csv)
 *          npm run build:regions -- --fetch (lädt die Liste vorher neu)
 */
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const csvPath = join( root, 'tools/data/cap_warncellids.csv' );
const SOURCE =
	'https://www.dwd.de/DE/leistungen/opendata/help/warnungen/cap_warncellids_csv.csv?__blob=publicationFile';

// Erste Ziffer der Warncell-ID => Typ. Nur Typen, für die der DWD Warnungen
// mit Warncell-ID ausliefert (warnings.json bzw. WFS Warnungen_Gemeinden).
const TYPES = {
	1: 'k', // Landkreis / kreisfreie Stadt
	2: 's', // Binnensee
	5: 'c', // Küste
	8: 'g', // Gemeinde
	9: 't', // Kreisteil / Warngebiet
};

if ( process.argv.includes( '--fetch' ) ) {
	const res = await fetch( SOURCE );
	if ( ! res.ok ) {
		throw new Error( `Download fehlgeschlagen: HTTP ${ res.status }` );
	}
	writeFileSync( csvPath, Buffer.from( await res.arrayBuffer() ) );
	console.log( 'Warncell-Liste aktualisiert.' );
}

const lines = readFileSync( csvPath, 'utf8' )
	.replace( /^﻿/, '' )
	.split( /\r?\n/ )
	.filter( Boolean );

const header = lines.shift().split( ';' );
const col = ( name ) => header.indexOf( name );
const [ iId, iName, iCcc, iBl ] = [ 'WARNCELLID', 'NAME', 'CCC', 'BL' ].map( col );

const regions = [];
const ccc = {};

for ( const line of lines ) {
	const f = line.split( ';' );
	const id = f[ iId ];
	const type = TYPES[ id[ 0 ] ];
	if ( ! type || ! /^\d{9}$/.test( id ) ) {
		continue;
	}
	regions.push( [ Number( id ), f[ iName ].trim(), ( f[ iBl ] || '' ).trim(), type ] );

	// Alte wettwarn.de Feed-IDs entsprechen den DWD-Kreiskennungen (CCC).
	const code = ( f[ iCcc ] || '' ).trim().toUpperCase();
	if ( code && ( type === 'k' || type === 't' ) && ( ! ccc[ code ] || type === 'k' ) ) {
		ccc[ code ] = Number( id );
	}
}

regions.sort( ( a, b ) => a[ 1 ].localeCompare( b[ 1 ], 'de' ) );

const php = ( v ) =>
	typeof v === 'number' ? String( v ) : `'${ v.replace( /\\/g, '\\\\' ).replace( /'/g, "\\'" ) }'`;

const banner = `<?php\n/**\n * Automatisch erzeugt von tools/build-regions.mjs – nicht manuell bearbeiten.\n * Quelle: Deutscher Wetterdienst, Warncell-IDs (${ new Date().toISOString().slice( 0, 10 ) }).\n */\n\ndefined( 'ABSPATH' ) || exit;\n\n`;

mkdirSync( join( root, 'data' ), { recursive: true } );

writeFileSync(
	join( root, 'data/regions.php' ),
	banner +
		'return array(\n' +
		regions.map( ( r ) => `\tarray(${ r.map( php ).join( ',' ) }),` ).join( '\n' ) +
		'\n);\n'
);

writeFileSync(
	join( root, 'data/ccc-map.php' ),
	banner +
		'return array(\n' +
		Object.keys( ccc )
			.sort()
			.map( ( k ) => `\t${ php( k ) } => ${ ccc[ k ] },` )
			.join( '\n' ) +
		'\n);\n'
);

const count = regions.reduce( ( acc, r ) => ( ( acc[ r[ 3 ] ] = ( acc[ r[ 3 ] ] || 0 ) + 1 ), acc ), {} );
console.log( `${ regions.length } Regionen geschrieben`, count, `${ Object.keys( ccc ).length } CCC-Codes` );
