<?php
/**
 * Logiktest der Zugriffsauswertung – ohne WordPress-Installation ausführbar:
 *
 *     php tests/test-access.php
 *
 * Geprüft wird vor allem der Vorrang der gesperrten Rollen.
 *
 * @package LOHEIDE_Rights_Management
 */

require_once __DIR__ . '/stubs.php';
require_once __DIR__ . '/../includes/class-lrm-roles.php';
require_once __DIR__ . '/../includes/class-lrm-rule.php';
require_once __DIR__ . '/../includes/class-lrm-settings.php';
require_once __DIR__ . '/../includes/class-lrm-access.php';

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
 * Zugriff für eine Rollenliste auswerten.
 *
 * @param int   $post_id    Inhalts-ID.
 * @param array $roles      Rollen.
 * @param bool  $can_bypass Umgehungsrecht.
 * @return array
 */
function lrm_check( $post_id, $roles, $can_bypass = false ) {
	LRM_Access::flush();

	$logged_in = ! in_array( LRM_Roles::GUEST, $roles, true );

	return LRM_Access::evaluate( $post_id, $roles, $can_bypass, $logged_in );
}

echo PHP_EOL . 'Zugriffslogik der LOHEIDE Rechteverwaltung' . PHP_EOL . PHP_EOL;

/* -------------------------------------------------------------------------
 * 1. Gesperrte Rolle gewinnt gegen freigegebene Rolle
 * ---------------------------------------------------------------------- */
echo '1) Gesperrte Rolle hat Vorrang' . PHP_EOL;

lrm_test_add_post( 10 );
lrm_test_set_rule(
	10,
	array(
		'_lrm_enabled'        => 1,
		'_lrm_visibility'     => 'roles',
		'_lrm_allowed_roles'  => array( 'subscriber', 'editor' ),
		'_lrm_denied_roles'   => array( 'kunde' ),
	)
);

$result = lrm_check( 10, array( 'subscriber', 'kunde' ) );
lrm_assert( false === $result['allowed'], 'Abonnent + gesperrte Rolle "kunde" erhält keinen Zugriff' );
lrm_assert( 'denied_role' === $result['reason'], 'Begründung ist die Rollensperre' );
lrm_assert( array( 'kunde' ) === $result['roles'], 'Die auslösende Rolle wird benannt' );

$result = lrm_check( 10, array( 'subscriber' ) );
lrm_assert( true === $result['allowed'], 'Abonnent ohne Sperre erhält Zugriff' );

$result = lrm_check( 10, array( 'author' ) );
lrm_assert( false === $result['allowed'], 'Nicht freigegebene Rolle erhält keinen Zugriff' );

/* -------------------------------------------------------------------------
 * 2. Sperre gilt auch für Administratoren mit Umgehungsrecht
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '2) Sperre schlägt das Umgehungsrecht' . PHP_EOL;

$result = lrm_check( 10, array( 'administrator' ), true );
lrm_assert( true === $result['allowed'], 'Administrator mit Umgehungsrecht sieht den Inhalt' );

lrm_test_set_rule(
	10,
	array(
		'_lrm_enabled'       => 1,
		'_lrm_visibility'    => 'roles',
		'_lrm_allowed_roles' => array( 'subscriber' ),
		'_lrm_denied_roles'  => array( 'kunde', 'administrator' ),
	)
);

$result = lrm_check( 10, array( 'administrator' ), true );
lrm_assert( false === $result['allowed'], 'Ausdrücklich gesperrter Administrator wird trotz Umgehungsrecht blockiert' );

/* -------------------------------------------------------------------------
 * 3. Öffentliche Seite mit Sperre
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '3) Öffentliche Seite mit gesperrter Rolle' . PHP_EOL;

lrm_test_add_post( 20 );
lrm_test_set_rule(
	20,
	array(
		'_lrm_enabled'      => 1,
		'_lrm_visibility'   => 'public',
		'_lrm_denied_roles' => array( 'subscriber' ),
	)
);

lrm_assert( true === lrm_check( 20, array( LRM_Roles::GUEST ) )['allowed'], 'Gast sieht die öffentliche Seite' );
lrm_assert( false === lrm_check( 20, array( 'subscriber' ) )['allowed'], 'Gesperrter Abonnent sieht sie nicht' );
lrm_assert( true === lrm_check( 20, array( 'editor' ) )['allowed'], 'Redakteur sieht sie' );

/* -------------------------------------------------------------------------
 * 4. Nur angemeldet
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '4) Sichtbar nur nach Anmeldung' . PHP_EOL;

lrm_test_add_post( 30 );
lrm_test_set_rule(
	30,
	array(
		'_lrm_enabled'    => 1,
		'_lrm_visibility' => 'logged_in',
	)
);

$result = lrm_check( 30, array( LRM_Roles::GUEST ) );
lrm_assert( false === $result['allowed'], 'Gast wird abgewiesen' );
lrm_assert( 'login_required' === $result['reason'], 'Begründung ist die fehlende Anmeldung' );
lrm_assert( true === lrm_check( 30, array( 'subscriber' ) )['allowed'], 'Jeder angemeldete Benutzer erhält Zugriff' );

/* -------------------------------------------------------------------------
 * 5. Gast-Rolle gezielt freigeben und sperren
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '5) Virtuelle Gast-Rolle' . PHP_EOL;

lrm_test_add_post( 40 );
lrm_test_set_rule(
	40,
	array(
		'_lrm_enabled'       => 1,
		'_lrm_visibility'    => 'roles',
		'_lrm_allowed_roles' => array( LRM_Roles::GUEST, 'editor' ),
	)
);

lrm_assert( true === lrm_check( 40, array( LRM_Roles::GUEST ) )['allowed'], 'Gast kann ausdrücklich freigegeben werden' );

lrm_test_set_rule(
	40,
	array(
		'_lrm_enabled'      => 1,
		'_lrm_visibility'   => 'public',
		'_lrm_denied_roles' => array( LRM_Roles::GUEST ),
	)
);

lrm_assert( false === lrm_check( 40, array( LRM_Roles::GUEST ) )['allowed'], 'Gesperrter Gast erhält keinen Zugriff' );
lrm_assert( true === lrm_check( 40, array( 'subscriber' ) )['allowed'], 'Angemeldeter Benutzer schon' );

/* -------------------------------------------------------------------------
 * 6. Vererbung an Unterseiten
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '6) Vererbung' . PHP_EOL;

lrm_test_add_post( 100 );
lrm_test_add_post( 101, 100 );
lrm_test_add_post( 102, 101 );

lrm_test_set_rule(
	100,
	array(
		'_lrm_enabled'       => 1,
		'_lrm_visibility'    => 'roles',
		'_lrm_allowed_roles' => array( 'editor' ),
		'_lrm_denied_roles'  => array( 'kunde' ),
	)
);

$rule = LRM_Access::get_effective_rule( 101 );
lrm_assert( $rule->enabled && $rule->inherited, 'Unterseite übernimmt die Regel der übergeordneten Seite' );
lrm_assert( 100 === $rule->source_id, 'Die Herkunft der Regel wird festgehalten' );
lrm_assert( false === lrm_check( 101, array( 'subscriber' ) )['allowed'], 'Nicht freigegebene Rolle wird auf der Unterseite abgewiesen' );
lrm_assert( true === lrm_check( 101, array( 'editor' ) )['allowed'], 'Freigegebene Rolle erhält Zugriff auf die Unterseite' );

LRM_Access::flush();
$rule = LRM_Access::get_effective_rule( 102 );
lrm_assert( $rule->enabled && 100 === $rule->source_id, 'Vererbung wirkt über mehrere Ebenen' );

/* -------------------------------------------------------------------------
 * 7. Sperren der Elternseite bleiben bei eigener Regel bestehen
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '7) Sperren summieren sich über die Seitenkette' . PHP_EOL;

lrm_test_set_rule(
	101,
	array(
		'_lrm_enabled'    => 1,
		'_lrm_visibility' => 'public',
	)
);

LRM_Access::flush();
$rule = LRM_Access::get_effective_rule( 101 );
lrm_assert( in_array( 'kunde', $rule->denied, true ), 'Sperre der übergeordneten Seite bleibt erhalten' );
lrm_assert( false === lrm_check( 101, array( 'kunde' ) )['allowed'], 'Eine Unterseite kann die Sperre nicht aufheben' );
lrm_assert( true === lrm_check( 101, array( LRM_Roles::GUEST ) )['allowed'], 'Die eigene Grundsichtbarkeit gilt weiterhin' );

/* -------------------------------------------------------------------------
 * 8. Vererbung abschalten
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '8) Vererbung abschalten' . PHP_EOL;

lrm_test_set_rule( 101, array( '_lrm_inherit' => 0 ) );
LRM_Access::flush();
$rule = LRM_Access::get_effective_rule( 101 );
lrm_assert( ! $rule->enabled, 'Ohne Vererbung bleibt die Unterseite frei' );
lrm_assert( array() === $rule->denied, 'Es werden keine Sperren übernommen' );

lrm_test_set_rule( 101, array() );
lrm_test_set_rule(
	100,
	array(
		'_lrm_enabled'       => 1,
		'_lrm_visibility'    => 'roles',
		'_lrm_allowed_roles' => array( 'editor' ),
		'_lrm_propagate'     => 0,
	)
);
LRM_Access::flush();
$rule = LRM_Access::get_effective_rule( 101 );
lrm_assert( ! $rule->enabled, 'Eine Regel ohne Weitergabe gilt nicht für Unterseiten' );

/* -------------------------------------------------------------------------
 * 9. Inhalte ohne Regel
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '9) Inhalte ohne Regel' . PHP_EOL;

lrm_test_add_post( 200 );
$result = lrm_check( 200, array( LRM_Roles::GUEST ) );
lrm_assert( true === $result['allowed'], 'Ohne Regel bleibt der Inhalt frei zugänglich' );
lrm_assert( 'no_rule' === $result['reason'], 'Die Begründung nennt die fehlende Regel' );

/* -------------------------------------------------------------------------
 * 10. Rollenmodus ohne Freigabe
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '10) Rollenmodus ohne Freigabe' . PHP_EOL;

lrm_test_add_post( 210 );
lrm_test_set_rule(
	210,
	array(
		'_lrm_enabled'       => 1,
		'_lrm_visibility'    => 'roles',
		'_lrm_allowed_roles' => array(),
	)
);

lrm_assert( false === lrm_check( 210, array( 'editor' ) )['allowed'], 'Ohne freigegebene Rolle erhält niemand Zugriff' );
lrm_assert( true === lrm_check( 210, array( 'administrator' ), true )['allowed'], 'Das Umgehungsrecht greift weiterhin' );

/* -------------------------------------------------------------------------
 * 11. Bereinigung unbekannter Rollen
 * ---------------------------------------------------------------------- */
echo PHP_EOL . '11) Bereinigung' . PHP_EOL;

lrm_assert( array( 'editor' ) === LRM_Roles::sanitize_list( array( 'editor', 'gibt_es_nicht' ) ), 'Unbekannte Rollen werden verworfen' );
lrm_assert( array( 'editor' ) === LRM_Roles::sanitize_list( array( 'editor', 'editor' ) ), 'Doppelte Einträge werden entfernt' );
lrm_assert( 'logged_in' === LRM_Rule::sanitize_visibility( 'unsinn' ), 'Unbekannter Modus fällt auf "nur angemeldet" zurück' );

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
