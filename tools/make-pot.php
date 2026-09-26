<?php
/**
 * Erzeugt die Vorlage für Übersetzungen (languages/*.pot).
 *
 *     php tools/make-pot.php
 *
 * Liest die Texte aus den Aufrufen von __(), _e(), _n(), _x() und ihren
 * maskierenden Varianten. Kommentare mit „translators:“ übernimmt es als
 * Hinweis. Bewusst ohne WP-CLI oder gettext: Das Plugin soll sich überall
 * bauen lassen, wo PHP läuft.
 *
 * @package LOHEIDE_Rights_Management
 */
$root   = dirname( __DIR__ );
$domain = 'loheide-rights-management';
$files  = array();

foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) ) as $f ) {
	$path = $f->getPathname();
	if ( substr( $path, -4 ) !== '.php' ) { continue; }
	if ( strpos( $path, '/.git/' ) !== false || strpos( $path, '/tests/' ) !== false
	  || strpos( $path, '/docs/' ) !== false || strpos( $path, '/tools/' ) !== false ) { continue; }
	$files[] = $path;
}
sort( $files );

$entries = array();   // msgid|msgctxt => ['plural'=>, 'refs'=>[], 'comments'=>[]]

foreach ( $files as $path ) {
	$src    = file_get_contents( $path );
	$rel    = ltrim( str_replace( $root, '', $path ), '/' );
	$tokens = token_get_all( $src );
	$count  = count( $tokens );

	for ( $i = 0; $i < $count; $i++ ) {
		if ( ! is_array( $tokens[ $i ] ) || $tokens[ $i ][0] !== T_STRING ) { continue; }
		$fn = $tokens[ $i ][1];
		if ( ! in_array( $fn, array( '__', '_e', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e', '_n', '_x', 'esc_html_x', '_nx' ), true ) ) { continue; }

		// Argumente einsammeln (nur Literale).
		$args = array(); $depth = 0; $j = $i + 1;
		while ( $j < $count ) {
			$t = $tokens[ $j ];
			if ( $t === '(' ) { $depth++; $j++; continue; }
			if ( $t === ')' ) { $depth--; if ( $depth === 0 ) { break; } $j++; continue; }
			if ( is_array( $t ) && $t[0] === T_CONSTANT_ENCAPSED_STRING && $depth === 1 ) {
				$args[] = stripcslashes( substr( $t[1], 1, -1 ) );
			}
			$j++;
		}
		if ( empty( $args ) ) { continue; }

		$line = $tokens[ $i ][2];
		// Kommentar für Übersetzer oberhalb?
		$note = '';
		for ( $k = $i - 1; $k > max( 0, $i - 12 ); $k-- ) {
			if ( is_array( $tokens[ $k ] ) && $tokens[ $k ][0] === T_COMMENT
			  && stripos( $tokens[ $k ][1], 'translators:' ) !== false ) {
				$note = trim( str_replace( array( '/*', '*/', '//' ), '', $tokens[ $k ][1] ) );
				break;
			}
		}

		$msgid  = $args[0];
		$plural = '';
		$ctxt   = '';
		if ( $fn === '_n' || $fn === '_nx' ) { $plural = isset( $args[1] ) ? $args[1] : ''; }
		if ( $fn === '_x' || $fn === 'esc_html_x' ) { $ctxt = isset( $args[1] ) ? $args[1] : ''; }

		$key = $ctxt . "\x04" . $msgid;
		if ( ! isset( $entries[ $key ] ) ) {
			$entries[ $key ] = array( 'msgid' => $msgid, 'plural' => $plural, 'ctxt' => $ctxt, 'refs' => array(), 'notes' => array() );
		}
		$entries[ $key ]['refs'][] = $rel . ':' . $line;
		if ( $note !== '' ) { $entries[ $key ]['notes'][ $note ] = true; }
		if ( $plural !== '' ) { $entries[ $key ]['plural'] = $plural; }
	}
}

ksort( $entries );

$esc = function ( $s ) { return str_replace( array( '\\', '"', "\n", "\t" ), array( '\\\\', '\"', '\n"' . "\n" . '"', '\t' ), $s ); };

$out  = '# Copyright (C) ' . gmdate( 'Y' ) . " LOHEIDE.EU\n";
$out .= "# This file is distributed under the GPL-2.0-or-later license.\n";
$out .= 'msgid ""' . "\n" . 'msgstr ""' . "\n";
$out .= '"Project-Id-Version: LOHEIDE.EU WP Rights Management\n"' . "\n";
$out .= '"Report-Msgid-Bugs-To: https://loheide.eu\n"' . "\n";
$out .= '"POT-Creation-Date: ' . gmdate( 'Y-m-d H:i' ) . '+0000\n"' . "\n";
$out .= '"MIME-Version: 1.0\n"' . "\n";
$out .= '"Content-Type: text/plain; charset=UTF-8\n"' . "\n";
$out .= '"Content-Transfer-Encoding: 8bit\n"' . "\n";
$out .= '"Plural-Forms: nplurals=2; plural=(n != 1);\n"' . "\n";
$out .= '"X-Domain: ' . $domain . '\n"' . "\n\n";

foreach ( $entries as $e ) {
	foreach ( array_keys( $e['notes'] ) as $note ) { $out .= '#. ' . $note . "\n"; }
	$out .= '#: ' . implode( ' ', array_unique( $e['refs'] ) ) . "\n";
	if ( $e['ctxt'] !== '' ) { $out .= 'msgctxt "' . $esc( $e['ctxt'] ) . '"' . "\n"; }
	$out .= 'msgid "' . $esc( $e['msgid'] ) . '"' . "\n";
	if ( $e['plural'] !== '' ) {
		$out .= 'msgid_plural "' . $esc( $e['plural'] ) . '"' . "\n";
		$out .= 'msgstr[0] ""' . "\n" . 'msgstr[1] ""' . "\n\n";
	} else {
		$out .= 'msgstr ""' . "\n\n";
	}
}

file_put_contents( $root . '/languages/' . $domain . '.pot', $out );
echo count( $entries ), " Texte in languages/$domain.pot\n";
