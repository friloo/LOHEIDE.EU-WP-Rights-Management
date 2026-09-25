<?php
/**
 * Logiktest der Backend-Rechte – ohne WordPress-Installation ausführbar:
 *
 *     php tests/test-backend.php
 *
 * @package LOHEIDE_Rights_Management
 */

require_once __DIR__ . '/stubs.php';
require_once __DIR__ . '/../includes/class-lrm-roles.php';
require_once __DIR__ . '/../includes/class-lrm-rule.php';
require_once __DIR__ . '/../includes/class-lrm-settings.php';
require_once __DIR__ . '/../includes/class-lrm-access.php';
require_once __DIR__ . '/../includes/class-lrm-backend.php';

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

echo PHP_EOL . 'Backend-Rechte der LOHEIDE.EU Rechteverwaltung' . PHP_EOL . PHP_EOL;

// Inhalte: Seiten 10 (MAV) und 11 (andere), Beiträge 20 (MAV-Kategorie) und 21 (andere).
lrm_test_add_post( 10 );
lrm_test_add_post( 11 );
lrm_test_add_post( 20, 0, 'post' );
lrm_test_add_post( 21, 0, 'post' );
lrm_test_add_post( 22, 0, 'post' );
lrm_test_add_post( 30, 0, 'attachment' );

$GLOBALS['lrm_test_post_categories'] = array(
	20 => array( 7 ),        // Mitarbeitervertretung
	21 => array( 3 ),        // Allgemein
	22 => array( 3, 7 ),     // beides
);

// Beitrag 20 gehört einem anderen Benutzer, 22 dem Benutzer selbst.
$GLOBALS['lrm_test_posts'][20]->post_author = 5;
$GLOBALS['lrm_test_posts'][21]->post_author = 5;
$GLOBALS['lrm_test_posts'][22]->post_author = 9;
$GLOBALS['lrm_test_posts'][30]->post_author = 9;

$mav = array(
	'enabled'        => 1,
	'pages'          => array( 10 ),
	'categories'     => array( 7 ),
	'create_pages'   => 0,
	'create_posts'   => 1,
	'own_posts_only' => 0,
	'own_media_only' => 1,
	'allow_media'    => 1,
	'hidden_menus'   => array( 'tools.php' ),
	'granted_caps'   => array(),
);

$user = new WP_User( 9, array( 'mav' ) );

/* -------------------------------------------------------------------------
 * 1. Seiten
 * ---------------------------------------------------------------------- */
echo '1) Bearbeitbare Seiten' . PHP_EOL;

lrm_assert( true === LRM_Backend::can_edit_post( 10, $mav, $user ), 'Die zugewiesene Seite darf bearbeitet werden' );
lrm_assert( false === LRM_Backend::can_edit_post( 11, $mav, $user ), 'Eine andere Seite nicht' );

$leer = array_merge( $mav, array( 'pages' => array() ) );
lrm_assert( false === LRM_Backend::can_edit_post( 10, $leer, $user ), 'Ohne Zuweisung ist keine Seite bearbeitbar' );

/* -------------------------------------------------------------------------
 * 2. Beiträge und Kategorien
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '2) Beiträge nach Kategorie' . PHP_EOL;

lrm_assert( true === LRM_Backend::can_edit_post( 20, $mav, $user ), 'Beitrag in der freigegebenen Kategorie ist bearbeitbar' );
lrm_assert( false === LRM_Backend::can_edit_post( 21, $mav, $user ), 'Beitrag in einer anderen Kategorie nicht' );
lrm_assert( true === LRM_Backend::can_edit_post( 22, $mav, $user ), 'Beitrag in mehreren Kategorien genügt, wenn eine freigegeben ist' );

$eigene = array_merge( $mav, array( 'own_posts_only' => 1 ) );
lrm_assert( false === LRM_Backend::can_edit_post( 20, $eigene, $user ), 'Mit „nur eigene“ ist ein fremder Beitrag gesperrt' );
lrm_assert( true === LRM_Backend::can_edit_post( 22, $eigene, $user ), 'Der eigene Beitrag bleibt bearbeitbar' );

$ohne_kat = array_merge( $mav, array( 'categories' => array() ) );
lrm_assert( false === LRM_Backend::can_edit_post( 20, $ohne_kat, $user ), 'Ohne freigegebene Kategorie ist kein Beitrag bearbeitbar' );

/* -------------------------------------------------------------------------
 * 3. Mediathek
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '3) Mediathek' . PHP_EOL;

lrm_assert( true === LRM_Backend::can_edit_post( 30, $mav, $user ), 'Die eigene Datei ist bearbeitbar' );

$GLOBALS['lrm_test_posts'][30]->post_author = 5;
lrm_assert( false === LRM_Backend::can_edit_post( 30, $mav, $user ), 'Eine fremde Datei nicht, wenn nur eigene erlaubt sind' );

$alle_medien = array_merge( $mav, array( 'own_media_only' => 0 ) );
lrm_assert( true === LRM_Backend::can_edit_post( 30, $alle_medien, $user ), 'Ohne die Einschränkung schon' );

/* -------------------------------------------------------------------------
 * 4. Benötigte Fähigkeiten
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '4) Benötigte Fähigkeiten' . PHP_EOL;

$caps = LRM_Backend::required_capabilities( $mav );

lrm_assert( in_array( 'read', $caps, true ), 'Die Rolle erhält Zugang zum Backend' );
lrm_assert( in_array( 'edit_pages', $caps, true ), 'Seiten bearbeiten wird vergeben' );
lrm_assert( in_array( 'edit_others_pages', $caps, true ), 'Auch fremde Seiten – die Begrenzung übernimmt map_meta_cap' );
lrm_assert( ! in_array( 'publish_pages', $caps, true ), 'Ohne das Recht zum Anlegen keine Veröffentlichung von Seiten' );
lrm_assert( in_array( 'publish_posts', $caps, true ), 'Beiträge anlegen ist erlaubt' );
lrm_assert( in_array( 'upload_files', $caps, true ), 'Dateien hochladen ist erlaubt' );
lrm_assert( ! in_array( 'manage_options', $caps, true ), 'Verwaltungsrechte werden nie vergeben' );

$nur_eigene = LRM_Backend::required_capabilities( $eigene );
lrm_assert( ! in_array( 'edit_others_posts', $nur_eigene, true ), 'Bei „nur eigene“ entfällt das Recht an fremden Beiträgen' );

/* -------------------------------------------------------------------------
 * 5. Speichern und Fähigkeiten angleichen
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '5) Fähigkeiten angleichen' . PHP_EOL;

$GLOBALS['lrm_test_options']['lrm_backend'] = array();
LRM_Backend::flush();

LRM_Backend::save( 'mav', $mav );
$role = get_role( 'mav' );

lrm_assert( $role->has_cap( 'edit_pages' ), 'Nach dem Speichern besitzt die Rolle die nötigen Fähigkeiten' );
lrm_assert( $role->has_cap( 'edit_others_pages' ), 'Auch das Recht an fremden Seiten' );

$gespeichert = LRM_Backend::get( 'mav' );
lrm_assert( in_array( 'edit_pages', $gespeichert['granted_caps'], true ), 'Vergebene Fähigkeiten werden vermerkt' );

// Beschränkung abschalten: die selbst vergebenen Fähigkeiten müssen weichen.
LRM_Backend::save( 'mav', array_merge( $mav, array( 'enabled' => 0, 'granted_caps' => $gespeichert['granted_caps'] ) ) );
$role = get_role( 'mav' );

lrm_assert( ! $role->has_cap( 'edit_others_pages' ), 'Ohne Beschränkung werden sie zurückgenommen' );
lrm_assert( ! $role->has_cap( 'edit_pages' ), 'Es bleibt nichts übrig, was die Rolle vorher nicht hatte' );

// Eine Fähigkeit, welche die Rolle schon vorher besaß, bleibt erhalten.
$GLOBALS['lrm_test_roles']['redaktion'] = new LRM_Test_Role( 'redaktion', array( 'edit_pages' => true ) );
LRM_Backend::save( 'redaktion', $mav );
LRM_Backend::save( 'redaktion', array_merge( $mav, array( 'enabled' => 0 ) ) );

lrm_assert( get_role( 'redaktion' )->has_cap( 'edit_pages' ), 'Zuvor vorhandene Fähigkeiten bleiben unangetastet' );

/* -------------------------------------------------------------------------
 * 6. Zusammenführung mehrerer Rollen
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '6) Mehrere Rollen' . PHP_EOL;

$GLOBALS['lrm_test_options']['lrm_backend'] = array(
	'mav'         => array_merge( LRM_Backend::defaults(), array( 'enabled' => 1, 'pages' => array( 10 ), 'categories' => array( 7 ), 'own_posts_only' => 1 ) ),
	'kunstteam'   => array_merge( LRM_Backend::defaults(), array( 'enabled' => 1, 'pages' => array( 11 ), 'categories' => array( 9 ), 'own_posts_only' => 0 ) ),
);
LRM_Backend::flush();

lrm_test_set_user( 9, array( 'mav', 'kunstteam' ) );
$merged = LRM_Backend::for_user( wp_get_current_user() );

lrm_assert( is_array( $merged ), 'Für einen Benutzer mit zwei beschränkten Rollen gilt eine Regel' );
lrm_assert( array( 10, 11 ) === $merged['pages'], 'Die Seiten beider Rollen werden zusammengeführt' );
lrm_assert( array( 7, 9 ) === $merged['categories'], 'Ebenso die Kategorien' );
lrm_assert( 0 === $merged['own_posts_only'], 'Bei abweichenden Angaben gilt die weitere Freigabe' );

lrm_test_set_user( 3, array( 'editor' ) );
lrm_assert( null === LRM_Backend::for_user( wp_get_current_user() ), 'Eine Rolle ohne Regel bleibt unbeschränkt' );

// Umgehungsrecht hebt die Beschränkung auf.
lrm_test_set_user( 1, array( 'administrator', 'mav' ) );
$GLOBALS['lrm_test_caps'][1][ LRM_Roles::CAP_BYPASS ] = true;
LRM_Backend::flush();

lrm_assert( null === LRM_Backend::for_user( wp_get_current_user() ), 'Das Umgehungsrecht hebt die Beschränkung auf' );

/* -------------------------------------------------------------------------
 * Ergebnis
 * ---------------------------------------------------------------------- */
echo PHP_EOL;

if ( $GLOBALS['lrm_failures'] ) {
	echo sprintf( "\033[31m%d von %d Prüfungen fehlgeschlagen.\033[0m", $GLOBALS['lrm_failures'], $GLOBALS['lrm_checks'] ) . PHP_EOL . PHP_EOL;
	exit( 1 );
}

echo sprintf( "\033[32mAlle %d Prüfungen erfolgreich.\033[0m", $GLOBALS['lrm_checks'] ) . PHP_EOL . PHP_EOL;
exit( 0 );
