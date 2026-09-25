<?php
/**
 * Logiktest des Dateischutzes – ohne WordPress-Installation ausführbar:
 *
 *     php tests/test-media.php
 *
 * Schwerpunkt: Ausnahmeliste und Abwehr von Pfadmanipulationen.
 *
 * @package LOHEIDE_Rights_Management
 */

require_once __DIR__ . '/stubs.php';
require_once __DIR__ . '/../includes/class-lrm-roles.php';
require_once __DIR__ . '/../includes/class-lrm-rule.php';
require_once __DIR__ . '/../includes/class-lrm-settings.php';
require_once __DIR__ . '/../includes/class-lrm-access.php';
require_once __DIR__ . '/../includes/class-lrm-media.php';

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

// Testverzeichnis aufbauen.
$base = $GLOBALS['lrm_test_uploads'];

if ( ! is_dir( $base . '/2026/01' ) ) {
	mkdir( $base . '/2026/01', 0777, true );
}

file_put_contents( $base . '/2026/01/vertrag.pdf', 'PDF' );
file_put_contents( $base . '/2026/01/logo.png', 'PNG' );
file_put_contents( $base . '/2026/01/bild-300x200.jpg', 'JPG' );
file_put_contents( $base . '/schadcode.php', '<?php echo 1;' );
file_put_contents( dirname( $base ) . '/geheim.txt', 'ausserhalb' );

echo PHP_EOL . 'Dateischutz der LOHEIDE.EU Rechteverwaltung' . PHP_EOL . PHP_EOL;

/* -------------------------------------------------------------------------
 * 1. Mustervergleich der Ausnahmeliste
 * ---------------------------------------------------------------------- */
echo '1) Ausnahmeliste: Mustervergleich' . PHP_EOL;

lrm_assert( LRM_Media::matches( '2026/01/logo.png', '2026/01/logo.png' ), 'Vollständiger Pfad passt' );
lrm_assert( LRM_Media::matches( 'logo.png', '2026/01/logo.png' ), 'Reiner Dateiname passt auf jeden Ordner' );
lrm_assert( LRM_Media::matches( '2026/*/logo.png', '2026/01/logo.png' ), 'Platzhalter für den Ordner' );
lrm_assert( LRM_Media::matches( '*.svg', 'branding/zeichen.svg' ), 'Platzhalter für den Pfad' );
lrm_assert( LRM_Media::matches( 'logo-?.png', 'logo-2.png' ), 'Platzhalter für ein Zeichen' );
lrm_assert( ! LRM_Media::matches( 'logo.png', '2026/01/vertrag.pdf' ), 'Andere Datei passt nicht' );
lrm_assert( ! LRM_Media::matches( 'logo.png', '2026/01/logo.png.pdf' ), 'Kein Treffer bei angehängter Endung' );
lrm_assert( ! LRM_Media::matches( '', '2026/01/logo.png' ), 'Leeres Muster passt nie' );

/* -------------------------------------------------------------------------
 * 2. Einlesen der Eingabe
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '2) Ausnahmeliste: Eingabe einlesen' . PHP_EOL;

$patterns = LRM_Media::parse_patterns( "2026/01/logo.png\n\n# Kommentar\n  branding/*  \n/fuehrend.png" );

lrm_assert( array( '2026/01/logo.png', 'branding/*', 'fuehrend.png' ) === $patterns, 'Leerzeilen, Kommentare und führende Schrägstriche werden entfernt' );

$from_url = LRM_Media::parse_patterns( 'https://example.test/wp-content/uploads/2026/01/logo.png' );
lrm_assert( array( '2026/01/logo.png' ) === $from_url, 'Vollständige Adresse wird auf den relativen Pfad gekürzt' );

/* -------------------------------------------------------------------------
 * 3. Pfadauflösung und Abwehr von Manipulationen
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '3) Pfadauflösung' . PHP_EOL;

lrm_assert( false !== LRM_Media::resolve_path( '2026/01/vertrag.pdf' ), 'Reguläre Datei wird aufgelöst' );
lrm_assert( false !== LRM_Media::resolve_path( '/2026/01/vertrag.pdf' ), 'Führender Schrägstrich ist zulässig' );
lrm_assert( false === LRM_Media::resolve_path( '../geheim.txt' ), 'Ausbruch mit ../ wird abgewehrt' );
lrm_assert( false === LRM_Media::resolve_path( '2026/../../geheim.txt' ), 'Ausbruch über Unterordner wird abgewehrt' );
lrm_assert( false === LRM_Media::resolve_path( '../../wp-config.php' ), 'Zugriff auf wp-config.php wird abgewehrt' );
lrm_assert( false === LRM_Media::resolve_path( 'schadcode.php' ), 'PHP-Dateien werden nie ausgeliefert' );
lrm_assert( false === LRM_Media::resolve_path( 'SCHADCODE.PHP' ), 'Auch mit Großbuchstaben nicht' );
lrm_assert( false === LRM_Media::resolve_path( '2026/01/vertrag.pdf' . chr( 0 ) . '.png' ), 'Nullbyte wird entfernt und der Rest geprüft' );
lrm_assert( false === LRM_Media::resolve_path( 'gibt-es-nicht.pdf' ), 'Fehlende Datei ergibt keinen Pfad' );
lrm_assert( false === LRM_Media::resolve_path( '' ), 'Leere Eingabe ergibt keinen Pfad' );
lrm_assert( false === LRM_Media::resolve_path( '2026/01' ), 'Ein Ordner ist keine Datei' );

$resolved = LRM_Media::resolve_path( '2026/01/vertrag.pdf' );
lrm_assert( '2026/01/vertrag.pdf' === LRM_Media::relative_path( $resolved ), 'Relativer Pfad wird korrekt zurückgerechnet' );

/* -------------------------------------------------------------------------
 * 4. Genereller Block mit Ausnahmen
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '4) Genereller Block' . PHP_EOL;

$GLOBALS['lrm_test_options']['lrm_settings'] = array(
	'protect_uploads'   => 1,
	'uploads_mode'      => 'login',
	'uploads_whitelist' => "2026/01/logo.png\nbranding/*",
	'whitelist_branding' => 0,
	'post_types'        => array( 'page', 'attachment' ),
);
LRM_Settings::flush();

$GLOBALS['lrm_test_logged_in'] = false;

lrm_assert( ! LRM_Media::is_allowed( '2026/01/vertrag.pdf' ), 'Gast erhält keine geschützte Datei' );
lrm_assert( LRM_Media::is_allowed( '2026/01/logo.png' ), 'Freigegebenes Logo bleibt erreichbar' );
lrm_assert( LRM_Media::is_allowed( 'branding/zeichen.svg' ), 'Freigegebener Ordner bleibt erreichbar' );

$GLOBALS['lrm_test_logged_in'] = true;
lrm_assert( LRM_Media::is_allowed( '2026/01/vertrag.pdf' ), 'Angemeldeter Benutzer erhält die Datei' );

$GLOBALS['lrm_test_options']['lrm_settings']['uploads_mode'] = 'rules';
LRM_Settings::flush();
$GLOBALS['lrm_test_logged_in'] = false;

lrm_assert( LRM_Media::is_allowed( '2026/01/vertrag.pdf' ), 'Im Modus „nur Dateien mit Regel“ bleibt eine Datei ohne Regel frei' );

/* -------------------------------------------------------------------------
 * 5. Regel eines Anhangs
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '5) Regel des Anhangs' . PHP_EOL;

lrm_test_add_post( 500 );
lrm_test_set_rule(
	500,
	array(
		'_lrm_enabled'       => 1,
		'_lrm_visibility'    => 'roles',
		'_lrm_allowed_roles' => array( 'editor' ),
		'_lrm_denied_roles'  => array( 'kunde' ),
	)
);
lrm_test_add_post( 501, 500, 'attachment' );
$GLOBALS['lrm_test_attached_files']['2026/01/vertrag.pdf'] = 501;
$GLOBALS['lrm_test_cache']                                 = array();

LRM_Access::flush();
$rule = LRM_Access::get_effective_rule( 501 );
lrm_assert( $rule->enabled && 500 === $rule->source_id, 'Ein Anhang erbt die Regel der Seite, an der er hängt' );
lrm_assert( false === LRM_Access::evaluate( 501, array( 'kunde' ), false, true )['allowed'], 'Gesperrte Rolle erhält die Datei nicht' );
lrm_assert( true === LRM_Access::evaluate( 501, array( 'editor' ), false, true )['allowed'], 'Freigegebene Rolle erhält die Datei' );
lrm_assert( false === LRM_Access::evaluate( 501, array( LRM_Roles::GUEST ), false, false )['allowed'], 'Gast erhält die Datei nicht' );

// Die Datei selbst prüfen: Modus „nur Dateien mit Regel“.
$GLOBALS['lrm_test_options']['lrm_settings']['uploads_mode'] = 'rules';
LRM_Settings::flush();
$GLOBALS['lrm_test_logged_in'] = true;

lrm_test_set_user( 7, array( 'kunde' ) );
lrm_assert( ! LRM_Media::is_allowed( '2026/01/vertrag.pdf' ), 'Gesperrte Rolle erhält die Datei nicht – auch ohne generellen Block' );

lrm_test_set_user( 8, array( 'editor' ) );
lrm_assert( LRM_Media::is_allowed( '2026/01/vertrag.pdf' ), 'Freigegebene Rolle erhält dieselbe Datei' );

lrm_test_set_user( 0 );
$GLOBALS['lrm_test_logged_in'] = false;
lrm_assert( ! LRM_Media::is_allowed( '2026/01/vertrag.pdf' ), 'Gast erhält die Datei nicht' );

/* -------------------------------------------------------------------------
 * 6. Erzeugte Bildgrößen
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '6) Bildgrößen' . PHP_EOL;

lrm_assert( LRM_Media::is_whitelisted( '2026/01/logo.png' ), 'Freigabe wirkt für die Originaldatei' );

$GLOBALS['lrm_test_options']['lrm_settings']['uploads_whitelist'] = 'logo*.png';
LRM_Settings::flush();
lrm_assert( LRM_Media::is_whitelisted( '2026/01/logo-300x200.png' ), 'Mit Muster lassen sich auch die erzeugten Größen freigeben' );

/* -------------------------------------------------------------------------
 * Ergebnis
 * ---------------------------------------------------------------------- */
echo PHP_EOL;

// Aufräumen.
@unlink( dirname( $base ) . '/geheim.txt' );

if ( $GLOBALS['lrm_failures'] ) {
	echo sprintf( "\033[31m%d von %d Prüfungen fehlgeschlagen.\033[0m", $GLOBALS['lrm_failures'], $GLOBALS['lrm_checks'] ) . PHP_EOL . PHP_EOL;
	exit( 1 );
}

echo sprintf( "\033[32mAlle %d Prüfungen erfolgreich.\033[0m", $GLOBALS['lrm_checks'] ) . PHP_EOL . PHP_EOL;
exit( 0 );
