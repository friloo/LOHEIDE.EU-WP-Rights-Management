<?php
/**
 * Prüft die Schutzmechanismen beim Laden – ohne WordPress-Installation:
 *
 *     php tests/test-bootstrap.php
 *
 * Geprüft wird, was passiert, bevor das Plugin überhaupt startet: eine zu alte
 * Umgebung und eine unvollständig hochgeladene Installation. Beides endete
 * ohne diese Prüfungen in einem Fatal Error.
 *
 * @package LOHEIDE_Rights_Management
 */

require_once __DIR__ . '/stubs.php';

$GLOBALS['lrm_failures'] = 0;
$GLOBALS['lrm_checks']   = 0;

/**
 * Einzelne Zusicherung prüfen.
 *
 * @param bool   $condition Bedingung.
 * @param string $label     Beschreibung.
 */
function lrm_assert( $condition, $label ) {
	$GLOBALS['lrm_checks']++;

	if ( $condition ) {
		echo "  \033[32mOK\033[0m   " . $label . PHP_EOL;

		return;
	}

	$GLOBALS['lrm_failures']++;
	echo "  \033[31mFEHL\033[0m " . $label . PHP_EOL;
}

// Die Bootstrap-Datei lädt sich nicht ohne WordPress; geprüft wird ihr Inhalt.
$bootstrap = file_get_contents( dirname( __DIR__ ) . '/loheide-rights-management.php' );

echo PHP_EOL . '1) Kopfdaten des Plugins' . PHP_EOL;

foreach ( array( 'Plugin Name', 'Description', 'Version', 'Requires at least', 'Requires PHP', 'License', 'Text Domain', 'Domain Path' ) as $feld ) {
	lrm_assert( false !== strpos( $bootstrap, $feld . ':' ), 'Kopfzeile vorhanden: ' . $feld );
}

echo PHP_EOL . '2) Schutz beim Laden' . PHP_EOL;

lrm_assert( false !== strpos( $bootstrap, "defined( 'ABSPATH' )" ), 'Direkter Aufruf der Datei wird abgewiesen' );
lrm_assert( false !== strpos( $bootstrap, "function_exists( 'lrm' )" ), 'Die globale Funktion prüft auf Namensgleichheit' );
lrm_assert( false !== strpos( $bootstrap, 'is_readable' ), 'Fehlende Klassendateien werden erkannt' );
lrm_assert( false !== strpos( $bootstrap, 'lrm_environment_problem' ), 'PHP- und WordPress-Version werden geprüft' );

echo PHP_EOL . '3) Die geprüften Dateien gibt es auch' . PHP_EOL;

preg_match( '/function lrm_class_files\(\) \{.*?return array\((.*?)\);/s', $bootstrap, $treffer );
preg_match_all( "/'([a-z0-9\-\.]+\.php)'/", isset( $treffer[1] ) ? $treffer[1] : '', $dateien );

lrm_assert( ! empty( $dateien[1] ), 'Die Liste der Klassendateien ist lesbar' );

$fehlend = array();

foreach ( $dateien[1] as $datei ) {
	if ( ! is_readable( dirname( __DIR__ ) . '/includes/' . $datei ) ) {
		$fehlend[] = $datei;
	}
}

lrm_assert( empty( $fehlend ), 'Jede aufgeführte Datei liegt im Ordner „includes“' . ( $fehlend ? ' – fehlt: ' . implode( ', ', $fehlend ) : '' ) );

$vorhanden = glob( dirname( __DIR__ ) . '/includes/class-lrm-*.php' );
$vorhanden = array_map( 'basename', (array) $vorhanden );
$ungelistet = array_diff( $vorhanden, $dateien[1] );

lrm_assert( empty( $ungelistet ), 'Keine Klassendatei fehlt in der Liste' . ( $ungelistet ? ' – nicht gelistet: ' . implode( ', ', $ungelistet ) : '' ) );

echo PHP_EOL . '4) Deinstallation' . PHP_EOL;

$uninstall = file_get_contents( dirname( __DIR__ ) . '/uninstall.php' );

lrm_assert( false !== strpos( $uninstall, "defined( 'WP_UNINSTALL_PLUGIN' )" ), 'Die Deinstallation läuft nur über WordPress' );
lrm_assert( false !== strpos( $uninstall, 'insert_with_markers' ), 'Die Serverregel im Uploads-Ordner wird entfernt' );

echo PHP_EOL;

if ( $GLOBALS['lrm_failures'] > 0 ) {
	echo "\033[31m" . $GLOBALS['lrm_failures'] . ' von ' . $GLOBALS['lrm_checks'] . " Prüfungen fehlgeschlagen.\033[0m" . PHP_EOL;
	exit( 1 );
}

echo "\033[32mAlle " . $GLOBALS['lrm_checks'] . " Prüfungen erfolgreich.\033[0m" . PHP_EOL;
