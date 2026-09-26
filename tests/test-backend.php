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

/**
 * Regel eines Inhaltstyps aufbauen.
 *
 * @param array $args Abweichende Angaben.
 * @return array
 */
function lrm_type( $args = array() ) {
	return array_merge( LRM_Backend::type_defaults(), $args );
}

echo PHP_EOL . 'Backend-Rechte der LOHEIDE.EU Rechteverwaltung' . PHP_EOL . PHP_EOL;

// Inhalte: Seiten 10/11, Beiträge 20-22, Anhang 30, Verleihgegenstände 40/41.
lrm_test_add_post( 10 );
lrm_test_add_post( 11 );
lrm_test_add_post( 20, 0, 'post' );
lrm_test_add_post( 21, 0, 'post' );
lrm_test_add_post( 22, 0, 'post' );
lrm_test_add_post( 30, 0, 'attachment' );
lrm_test_add_post( 40, 0, 'verleih' );
lrm_test_add_post( 41, 0, 'verleih' );

$GLOBALS['lrm_test_post_categories'] = array(
	20 => array( 7 ),
	21 => array( 3 ),
	22 => array( 3, 7 ),
);

$GLOBALS['lrm_test_posts'][20]->post_author = 5;
$GLOBALS['lrm_test_posts'][21]->post_author = 5;
$GLOBALS['lrm_test_posts'][22]->post_author = 9;
$GLOBALS['lrm_test_posts'][30]->post_author = 9;
$GLOBALS['lrm_test_posts'][40]->post_author = 5;
$GLOBALS['lrm_test_posts'][41]->post_author = 9;

$user = new WP_User( 9, array( 'mav' ) );

$mav = array_merge(
	LRM_Backend::defaults(),
	array(
		'enabled'        => 1,
		'allow_media'    => 1,
		'own_media_only' => 1,
		'types'          => array(
			'page' => lrm_type( array( 'mode' => 'selected', 'items' => array( 10 ) ) ),
			'post' => lrm_type( array( 'mode' => 'terms', 'taxonomy' => 'category', 'terms' => array( 7 ), 'create' => 1 ) ),
		),
	)
);

/* -------------------------------------------------------------------------
 * 1. Auswahl einzelner Inhalte
 * ---------------------------------------------------------------------- */
echo '1) Einzeln zugewiesene Inhalte' . PHP_EOL;

lrm_assert( true === LRM_Backend::can_edit_post( 10, $mav, $user ), 'Die zugewiesene Seite darf bearbeitet werden' );
lrm_assert( false === LRM_Backend::can_edit_post( 11, $mav, $user ), 'Eine andere Seite nicht' );

/* -------------------------------------------------------------------------
 * 2. Begriffe
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '2) Inhalte nach Kategorie' . PHP_EOL;

lrm_assert( true === LRM_Backend::can_edit_post( 20, $mav, $user ), 'Beitrag in der freigegebenen Kategorie ist bearbeitbar' );
lrm_assert( false === LRM_Backend::can_edit_post( 21, $mav, $user ), 'Beitrag in einer anderen Kategorie nicht' );
lrm_assert( true === LRM_Backend::can_edit_post( 22, $mav, $user ), 'Mehrere Kategorien genügen, wenn eine freigegeben ist' );

$eigene              = $mav;
$eigene['types']['post']['own_only'] = 1;
lrm_assert( false === LRM_Backend::can_edit_post( 20, $eigene, $user ), 'Mit „nur eigene“ ist ein fremder Beitrag gesperrt' );
lrm_assert( true === LRM_Backend::can_edit_post( 22, $eigene, $user ), 'Der eigene Beitrag bleibt bearbeitbar' );

/* -------------------------------------------------------------------------
 * 3. Eigene Inhaltstypen
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '3) Eigene Inhaltstypen' . PHP_EOL;

lrm_assert( false === LRM_Backend::can_edit_post( 40, $mav, $user ), 'Ein nicht zugewiesener Inhaltstyp ist gesperrt' );

$kunst = array_merge(
	LRM_Backend::defaults(),
	array(
		'enabled' => 1,
		'types'   => array( 'verleih' => lrm_type( array( 'mode' => 'all', 'create' => 1, 'delete' => 1 ) ) ),
	)
);

lrm_assert( true === LRM_Backend::can_edit_post( 40, $kunst, $user ), 'Im Modus „alle“ darf jeder Inhalt des Typs bearbeitet werden' );
lrm_assert( false === LRM_Backend::can_edit_post( 10, $kunst, $user ), 'Andere Inhaltstypen bleiben gesperrt' );

$kunst_eigene                          = $kunst;
$kunst_eigene['types']['verleih']['own_only'] = 1;
lrm_assert( false === LRM_Backend::can_edit_post( 40, $kunst_eigene, $user ), 'Mit „nur eigene“ zählt die Urheberschaft auch hier' );
lrm_assert( true === LRM_Backend::can_edit_post( 41, $kunst_eigene, $user ), 'Der eigene Gegenstand bleibt bearbeitbar' );

/* -------------------------------------------------------------------------
 * 4. Löschen
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '4) Löschen' . PHP_EOL;

lrm_assert( false === LRM_Backend::can_delete_post( 10, $mav, $user ), 'Ohne das Recht darf nichts gelöscht werden' );

$darf_loeschen                         = $mav;
$darf_loeschen['types']['page']['delete'] = 1;
lrm_assert( true === LRM_Backend::can_delete_post( 10, $darf_loeschen, $user ), 'Mit dem Recht ist die zugewiesene Seite löschbar' );
lrm_assert( false === LRM_Backend::can_delete_post( 11, $darf_loeschen, $user ), 'Eine fremde Seite bleibt auch dann gesperrt' );

/* -------------------------------------------------------------------------
 * 5. Mediathek
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '5) Mediathek' . PHP_EOL;

lrm_assert( true === LRM_Backend::can_edit_post( 30, $mav, $user ), 'Die eigene Datei ist bearbeitbar' );

$GLOBALS['lrm_test_posts'][30]->post_author = 5;
lrm_assert( false === LRM_Backend::can_edit_post( 30, $mav, $user ), 'Eine fremde Datei nicht, wenn nur eigene erlaubt sind' );

$ohne_medien                = $mav;
$ohne_medien['allow_media'] = 0;
lrm_assert( false === LRM_Backend::can_edit_post( 30, $ohne_medien, $user ), 'Ohne Zugriff auf die Mediathek ist keine Datei bearbeitbar' );

/* -------------------------------------------------------------------------
 * 6. Benötigte Fähigkeiten
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '6) Benötigte Fähigkeiten' . PHP_EOL;

$caps = LRM_Backend::required_capabilities( $mav );

lrm_assert( in_array( 'read', $caps, true ), 'Die Rolle erhält Zugang zum Backend' );
lrm_assert( in_array( 'edit_pages', $caps, true ), 'Seiten bearbeiten wird vergeben' );
lrm_assert( in_array( 'edit_others_pages', $caps, true ), 'Auch fremde Seiten – die Begrenzung übernimmt map_meta_cap' );
lrm_assert( ! in_array( 'publish_pages', $caps, true ), 'Ohne das Recht zum Anlegen keine Veröffentlichung von Seiten' );
lrm_assert( in_array( 'publish_posts', $caps, true ), 'Beiträge anlegen ist erlaubt' );
lrm_assert( ! in_array( 'delete_pages', $caps, true ), 'Ohne Löschrecht keine Löschfähigkeit' );
lrm_assert( in_array( 'upload_files', $caps, true ), 'Dateien hochladen ist erlaubt' );
lrm_assert( ! in_array( 'manage_options', $caps, true ), 'Verwaltungsrechte werden nie vergeben' );

$kunst_caps = LRM_Backend::required_capabilities( $kunst );
lrm_assert( in_array( 'edit_verleihs', $kunst_caps, true ), 'Für eigene Inhaltstypen werden deren Fähigkeiten verwendet' );
lrm_assert( in_array( 'delete_verleihs', $kunst_caps, true ), 'Mit Löschrecht auch die Löschfähigkeit' );

$nur_eigene = LRM_Backend::required_capabilities( $eigene );
lrm_assert( ! in_array( 'edit_others_posts', $nur_eigene, true ), 'Bei „nur eigene“ entfällt das Recht an fremden Beiträgen' );

/* -------------------------------------------------------------------------
 * 7. Fähigkeiten angleichen
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '7) Fähigkeiten angleichen' . PHP_EOL;

$GLOBALS['lrm_test_options']['lrm_backend'] = array();
LRM_Backend::flush();

LRM_Backend::save( 'mav', $mav );
$role = get_role( 'mav' );

lrm_assert( $role->has_cap( 'edit_pages' ), 'Nach dem Speichern besitzt die Rolle die nötigen Fähigkeiten' );
lrm_assert( $role->has_cap( 'edit_others_pages' ), 'Auch das Recht an fremden Seiten' );

$gespeichert = LRM_Backend::get( 'mav' );
lrm_assert( in_array( 'edit_pages', $gespeichert['granted_caps'], true ), 'Vergebene Fähigkeiten werden vermerkt' );

$aus            = $gespeichert;
$aus['enabled'] = 0;
LRM_Backend::save( 'mav', $aus );
$role = get_role( 'mav' );

lrm_assert( ! $role->has_cap( 'edit_others_pages' ), 'Ohne Beschränkung werden sie zurückgenommen' );
lrm_assert( ! $role->has_cap( 'edit_pages' ), 'Es bleibt nichts übrig, was die Rolle vorher nicht hatte' );

$GLOBALS['lrm_test_roles']['redaktion'] = new LRM_Test_Role( 'redaktion', array( 'edit_pages' => true ) );
LRM_Backend::save( 'redaktion', $mav );
$redaktion            = LRM_Backend::get( 'redaktion' );
$redaktion['enabled'] = 0;
LRM_Backend::save( 'redaktion', $redaktion );

lrm_assert( get_role( 'redaktion' )->has_cap( 'edit_pages' ), 'Zuvor vorhandene Fähigkeiten bleiben unangetastet' );

// Speichern ohne die interne Buchführung darf sie nicht verlieren.
$GLOBALS['lrm_test_roles']['wechsler'] = new LRM_Test_Role( 'wechsler' );
LRM_Backend::save( 'wechsler', $mav );
lrm_assert( get_role( 'wechsler' )->has_cap( 'edit_pages' ), 'Die Rolle erhält die Fähigkeiten' );

// Jetzt ein Aufruf mit frischem Array – wie aus einem Skript heraus.
LRM_Backend::save(
	'wechsler',
	array(
		'enabled' => 1,
		'types'   => array( 'post' => lrm_type( array( 'mode' => 'terms', 'taxonomy' => 'category', 'terms' => array( 7 ) ) ) ),
	)
);
lrm_assert( ! get_role( 'wechsler' )->has_cap( 'edit_pages' ), 'Nicht mehr benötigte Fähigkeiten werden auch dann zurückgenommen' );
lrm_assert( get_role( 'wechsler' )->has_cap( 'edit_posts' ), 'Die neuen Fähigkeiten sind vorhanden' );

/* -------------------------------------------------------------------------
 * 8. Übernahme älterer Regeln
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '8) Übernahme älterer Regeln' . PHP_EOL;

$GLOBALS['lrm_test_options']['lrm_backend'] = array(
	'alt' => array(
		'enabled'        => 1,
		'pages'          => array( 10, 11 ),
		'categories'     => array( 7 ),
		'create_pages'   => 1,
		'create_posts'   => 0,
		'own_posts_only' => 1,
	),
);
LRM_Backend::flush();

$alt = LRM_Backend::get( 'alt' );

lrm_assert( 'selected' === $alt['types']['page']['mode'], 'Frühere Seitenzuweisungen werden übernommen' );
lrm_assert( array( 10, 11 ) === $alt['types']['page']['items'], 'Die Seiten bleiben erhalten' );
lrm_assert( 1 === $alt['types']['page']['create'], 'Das Recht zum Anlegen wird übernommen' );
lrm_assert( 'terms' === $alt['types']['post']['mode'], 'Frühere Kategorien werden zu einer Begriffsregel' );
lrm_assert( array( 7 ) === $alt['types']['post']['terms'], 'Die Kategorien bleiben erhalten' );
lrm_assert( 1 === $alt['types']['post']['own_only'], 'Auch die Beschränkung auf eigene Beiträge' );
lrm_assert( ! isset( $alt['pages'] ), 'Die alten Felder werden entfernt' );

/* -------------------------------------------------------------------------
 * 9. Mehrere Rollen
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '9) Mehrere Rollen' . PHP_EOL;

$GLOBALS['lrm_test_options']['lrm_backend'] = array(
	'mav'       => array_merge(
		LRM_Backend::defaults(),
		array(
			'enabled' => 1,
			'types'   => array( 'page' => lrm_type( array( 'mode' => 'selected', 'items' => array( 10 ), 'own_only' => 1 ) ) ),
			'hidden_menus' => array( 'tools.php', 'plugins.php' ),
		)
	),
	'kunstteam' => array_merge(
		LRM_Backend::defaults(),
		array(
			'enabled' => 1,
			'types'   => array(
				'page'    => lrm_type( array( 'mode' => 'selected', 'items' => array( 11 ), 'own_only' => 0, 'create' => 1 ) ),
				'verleih' => lrm_type( array( 'mode' => 'all' ) ),
			),
			'hidden_menus' => array( 'tools.php' ),
		)
	),
);
LRM_Backend::flush();

lrm_test_set_user( 9, array( 'mav', 'kunstteam' ) );
$merged = LRM_Backend::for_user( wp_get_current_user() );

lrm_assert( is_array( $merged ), 'Für einen Benutzer mit zwei beschränkten Rollen gilt eine Regel' );
lrm_assert( array( 10, 11 ) === $merged['types']['page']['items'], 'Die Inhalte beider Rollen werden zusammengeführt' );
lrm_assert( 1 === $merged['types']['page']['own_only'], 'Die Einschränkung auf eigene Inhalte bleibt bestehen' );
lrm_assert( 1 === $merged['types']['page']['create'], 'Das Recht zum Anlegen wird übernommen' );
lrm_assert( isset( $merged['types']['verleih'] ), 'Inhaltstypen der zweiten Rolle kommen hinzu' );
lrm_assert( array( 'tools.php', 'plugins.php' ) === $merged['hidden_menus'], 'Verborgen bleibt alles, was mindestens eine Rolle verbirgt' );

// Der häufige Fall: Abonnent ist vollständig gesperrt, die Zweitrolle arbeitet.
$GLOBALS['lrm_test_options']['lrm_backend']['subscriber'] = array_merge(
	LRM_Backend::defaults(),
	array(
		'enabled'        => 1,
		'block_admin'    => 1,
		'types'          => array(),
		'allow_media'    => 0,
		'hidden_menus'   => array( 'index.php', 'edit.php', 'upload.php', 'profile.php', 'tools.php' ),
		'hide_new_menus' => 1,
	)
);
$GLOBALS['lrm_test_options']['lrm_backend']['mav']['allow_media']    = 1;
$GLOBALS['lrm_test_options']['lrm_backend']['mav']['own_media_only'] = 1;
$GLOBALS['lrm_test_options']['lrm_backend']['mav']['hidden_menus'] = array( 'profile.php' );
LRM_Backend::flush();

lrm_test_set_user( 11, array( 'subscriber', 'mav' ) );
$doppel = LRM_Backend::for_user( wp_get_current_user() );

lrm_assert( 'selected' === $doppel['types']['page']['mode'], 'Die Freigabe der Zweitrolle bleibt erhalten' );
lrm_assert( array( 10 ) === $doppel['types']['page']['items'], 'Die zugewiesene Seite ebenfalls' );
lrm_assert( 1 === $doppel['allow_media'], 'Erlaubt eine Rolle die Mediathek, bleibt sie erlaubt' );
lrm_assert( 1 === $doppel['own_media_only'], 'Die Einschränkung auf eigene Dateien bleibt erhalten' );
lrm_assert( in_array( 'profile.php', $doppel['hidden_menus'], true ), 'Ein Menüpunkt bleibt verborgen, den eine Rolle verbirgt' );
lrm_assert( in_array( 'tools.php', $doppel['hidden_menus'], true ), 'Auch die Sperren der strengeren Rolle gelten' );
lrm_assert( 1 === $doppel['hide_new_menus'], 'Die strengere Angabe zu neuen Menüs gewinnt' );
lrm_assert( 0 === $doppel['block_admin'], 'Lässt eine Rolle ins Backend, bleibt der Zugang offen' );

lrm_test_set_user( 12, array( 'subscriber' ) );
$nur_abo = LRM_Backend::for_user( wp_get_current_user() );
lrm_assert( 1 === $nur_abo['block_admin'], 'Für die gesperrte Rolle allein bleibt der Zugang zu' );

lrm_test_set_user( 3, array( 'editor' ) );
lrm_assert( null === LRM_Backend::for_user( wp_get_current_user() ), 'Eine Rolle ohne Regel bleibt unbeschränkt' );

// Gemeldeter Fall: Eine Arbeitsrolle ohne eigene Regel neben einem vollständig
// gesperrten Abonnenten. Die Nebenrolle darf die Arbeitsrolle nicht aussperren.
lrm_test_set_user( 13, array( 'subscriber', 'qm_editor' ) );
lrm_assert( null === LRM_Backend::for_user( wp_get_current_user() ), 'Eine Rolle ohne Regel hebt die Sperre der Nebenrolle auf' );

lrm_test_set_user( 14, array( 'qm_editor', 'subscriber' ) );
lrm_assert( null === LRM_Backend::for_user( wp_get_current_user() ), 'Die Reihenfolge der Rollen spielt dabei keine Rolle' );

lrm_test_set_user( 1, array( 'administrator', 'mav' ) );
$GLOBALS['lrm_test_caps'][1][ LRM_Roles::CAP_BYPASS ] = true;
LRM_Backend::flush();

lrm_assert( null === LRM_Backend::for_user( wp_get_current_user() ), 'Das Umgehungsrecht hebt die Beschränkung auf' );

/* -------------------------------------------------------------------------
 * 10. Bereinigung
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '10) Bereinigung' . PHP_EOL;

$schmutzig = LRM_Backend::sanitize(
	array(
		'enabled' => '1',
		'types'   => array(
			'page' => array( 'mode' => 'unsinn', 'items' => array( '10', 'abc', 0 ) ),
			'post' => array( 'mode' => 'none', 'items' => array( 5 ) ),
			'verleih' => array( 'mode' => 'all' ),
		),
		'hidden_menus' => array( 'tools.php', '', 'tools.php' ),
	)
);

lrm_assert( ! isset( $schmutzig['types']['page'] ), 'Ein unbekannter Modus verfällt' );
lrm_assert( ! isset( $schmutzig['types']['post'] ), 'Nicht freigegebene Typen werden nicht gespeichert' );
lrm_assert( isset( $schmutzig['types']['verleih'] ), 'Gültige Angaben bleiben erhalten' );
lrm_assert( array( 'tools.php' ) === $schmutzig['hidden_menus'], 'Doppelte und leere Menüeinträge werden entfernt' );

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
