/**
 * Erzeugt aus der DWD-Warncell-Liste:
 * - api/src/data/regions.json  mitgelieferte Regionsliste der API (Rückfall bis zum ersten DWD-Abruf)
 * - plugin/data/ccc-map.php    Zuordnung alter wettwarn.de Feed-IDs (CCC) für die Migration aus 2.x
 *
 * Quelle: https://www.dwd.de/DE/leistungen/opendata/help/warnungen/cap_warncellids_csv.csv
 * Aufruf:  npm run build:regions            (nutzt tools/data/cap_warncellids.csv)
 *          npm run build:regions -- --fetch (lädt die Liste vorher neu)
 */
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

import { CSV_URL, parseCsv } from '../../api/src/regions.js';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const csvPath = join( root, 'tools/data/cap_warncellids.csv' );

if ( process.argv.includes( '--fetch' ) ) {
	const res = await fetch( CSV_URL );
	if ( ! res.ok ) {
		throw new Error( `Download fehlgeschlagen: HTTP ${ res.status }` );
	}
	writeFileSync( csvPath, Buffer.from( await res.arrayBuffer() ) );
	console.log( 'Warncell-Liste aktualisiert.' );
}

const csv = readFileSync( csvPath, 'utf8' );

// 1) Regionsliste für die API.
const regions = parseCsv( csv );
const apiData = join( root, '../api/src/data' );
mkdirSync( apiData, { recursive: true } );
writeFileSync( join( apiData, 'regions.json' ), JSON.stringify( regions ) );

// 2) CCC-Codes (alte Feed-IDs) für Landkreise und Kreisteile.
const lines = csv.replace( /^﻿/, '' ).split( /\r?\n/ ).filter( Boolean );
const header = lines.shift().split( ';' );
const [ iId, iCcc ] = [ header.indexOf( 'WARNCELLID' ), header.indexOf( 'CCC' ) ];
const ccc = {};
for ( const line of lines ) {
	const f = line.split( ';' );
	const id = f[ iId ];
	const code = ( f[ iCcc ] || '' ).trim().toUpperCase();
	if ( code && /^[19]\d{8}$/.test( id ) && ( ! ccc[ code ] || id[ 0 ] === '1' ) ) {
		ccc[ code ] = Number( id );
	}
}

const php = ( v ) => `'${ String( v ).replace( /\\/g, '\\\\' ).replace( /'/g, "\\'" ) }'`;
writeFileSync(
	join( root, 'data/ccc-map.php' ),
	`<?php\n/**\n * Automatisch erzeugt von tools/build-regions.mjs – nicht manuell bearbeiten.\n * Alte wettwarn.de Feed-IDs (DWD-Kreiskennung CCC) => Warncell-ID.\n */\n\ndefined( 'ABSPATH' ) || exit;\n\nreturn array(\n` +
		Object.keys( ccc )
			.sort()
			.map( ( k ) => `\t${ php( k ) } => ${ ccc[ k ] },` )
			.join( '\n' ) +
		'\n);\n'
);

console.log( `${ regions.length } Regionen für die API, ${ Object.keys( ccc ).length } CCC-Codes für das Plugin.` );
