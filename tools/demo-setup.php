<?php
/**
 * Richtet eine Demo-Umgebung zum Ausprobieren und Prüfen ein.
 *
 * Aufruf aus dem Plugin-Verzeichnis heraus:
 *
 *     php tools/demo-setup.php /pfad/zur/wordpress-installation
 *
 * Angelegt werden Rollen, Kategorien, Seiten, Beiträge, Benutzer sowie die
 * dazu passenden Regeln – alles mit dem Präfix "Demo" erkennbar. Vorhandene
 * Einträge werden wiederverwendet, das Skript lässt sich mehrfach ausführen.
 *
 * Alle Demo-Benutzer haben das Passwort "demo1234".
 *
 * @package LOHEIDE_Rights_Management
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 'Dieses Skript läuft nur auf der Kommandozeile.' . PHP_EOL );
}

$wp_path = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '';

if ( ! $wp_path || ! file_exists( $wp_path . '/wp-load.php' ) ) {
	echo 'Bitte den Pfad zur WordPress-Installation angeben:' . PHP_EOL;
	echo '  php tools/demo-setup.php /pfad/zur/wordpress-installation' . PHP_EOL;
	exit( 1 );
}

require_once $wp_path . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/taxonomy.php';

if ( ! class_exists( 'LRM_Backend' ) ) {
	$result = activate_plugin( 'loheide-rights-management/loheide-rights-management.php' );

	if ( is_wp_error( $result ) ) {
		echo 'Das Plugin konnte nicht aktiviert werden: ' . $result->get_error_message() . PHP_EOL;
		exit( 1 );
	}

	echo 'Plugin aktiviert.' . PHP_EOL;
}

/**
 * Ausgabe einer Zeile.
 *
 * @param string $text Text.
 */
function lrm_demo_say( $text ) {
	echo '  ' . $text . PHP_EOL;
}

/**
 * Seite anlegen oder wiederverwenden.
 *
 * @param string $title   Titel.
 * @param int    $parent  Übergeordnete Seite.
 * @param array  $meta    Regel-Metadaten.
 * @param string $content Inhalt.
 * @return int
 */
function lrm_demo_page( $title, $parent = 0, $meta = array(), $content = '' ) {
	$slug     = sanitize_title( $title );
	$existing = get_page_by_path( $slug );

	if ( $existing ) {
		$id = $existing->ID;
	} else {
		$id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_parent'  => $parent,
				'post_content' => $content ? $content : '<!-- wp:paragraph --><p>Demo-Inhalt.</p><!-- /wp:paragraph -->',
			)
		);
	}

	foreach ( $meta as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}

	return (int) $id;
}

echo PHP_EOL . 'Demo-Umgebung wird eingerichtet …' . PHP_EOL . PHP_EOL;

/* --------------------------------------------------------------- Rollen. */

$roles = array(
	'kunde'      => 'Kunde',
	'partner'    => 'Vertriebspartner',
	'gesperrt'   => 'Gesperrtes Konto',
	'mav'        => 'Mitarbeitervertretung',
	'kunstteam'  => 'Künstlerteam',
);

foreach ( $roles as $key => $label ) {
	if ( ! get_role( $key ) ) {
		add_role( $key, $label, array( 'read' => true ) );
	}
}

lrm_demo_say( 'Rollen: ' . implode( ', ', $roles ) );

/* ----------------------------------------------------------- Kategorien. */

$cat_mav    = get_cat_ID( 'Mitarbeitervertretung' ) ? get_cat_ID( 'Mitarbeitervertretung' ) : wp_create_category( 'Mitarbeitervertretung' );
$cat_intern = get_cat_ID( 'Interna' ) ? get_cat_ID( 'Interna' ) : wp_create_category( 'Interna' );

lrm_demo_say( sprintf( 'Kategorien: Mitarbeitervertretung (%d), Interna (%d)', $cat_mav, $cat_intern ) );

/* --------------------------------------------------- Seiten mit Regeln. */

$intranet = lrm_demo_page(
	'Intranet',
	0,
	array(
		'_lrm_enabled'      => 1,
		'_lrm_visibility'   => 'logged_in',
		'_lrm_denied_roles' => array( 'gesperrt' ),
		'_lrm_propagate'    => 1,
	)
);

lrm_demo_page(
	'Lohnabrechnungen',
	$intranet,
	array(
		'_lrm_enabled'       => 1,
		'_lrm_visibility'    => 'roles',
		'_lrm_allowed_roles' => array( 'administrator', 'editor' ),
		'_lrm_denied_roles'  => array( 'kunde' ),
		'_lrm_action'        => '404',
	)
);

lrm_demo_page(
	'Kundenbereich',
	0,
	array(
		'_lrm_enabled'       => 1,
		'_lrm_visibility'    => 'roles',
		'_lrm_allowed_roles' => array( 'kunde', 'partner', 'subscriber' ),
		'_lrm_denied_roles'  => array( 'gesperrt' ),
		'_lrm_message'       => 'Dieser Bereich steht ausschließlich unseren Kunden zur Verfügung.',
	)
);

lrm_demo_page(
	'Downloads',
	0,
	array(
		'_lrm_enabled'      => 1,
		'_lrm_visibility'   => 'public',
		'_lrm_denied_roles' => array( 'gesperrt', 'lrm_guest' ),
	)
);

$mav_page = lrm_demo_page( 'Mitarbeitervertretung', 0, array(), '<!-- wp:paragraph --><p>Seite der Mitarbeitervertretung.</p><!-- /wp:paragraph -->' );

lrm_demo_page( 'Kontakt' );

lrm_demo_say( 'Seiten angelegt, darunter Intranet, Kundenbereich und Mitarbeitervertretung.' );

/* -------------------------------------------------------------- Beiträge. */

foreach ( array( 'Bericht der MAV' => $cat_mav, 'Interne Notiz' => $cat_intern ) as $title => $category ) {
	if ( ! get_page_by_path( sanitize_title( $title ), OBJECT, 'post' ) ) {
		wp_insert_post(
			array(
				'post_title'    => $title,
				'post_name'     => sanitize_title( $title ),
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_content'  => 'Demo-Beitrag.',
				'post_category' => array( $category ),
			)
		);
	}
}

lrm_demo_say( 'Beiträge in beiden Kategorien angelegt.' );

/* -------------------------------------------------------------- Benutzer. */

$users = array(
	'demo.kunde'   => 'kunde',
	'demo.partner' => 'partner',
	'demo.gesperrt' => 'gesperrt',
	'demo.mav'     => 'mav',
	'demo.kunst'   => 'kunstteam',
);

foreach ( $users as $login => $role ) {
	$user = get_user_by( 'login', $login );

	if ( $user ) {
		wp_set_password( 'demo1234', $user->ID );
		$object = new WP_User( $user->ID );
		$object->set_role( $role );
		continue;
	}

	wp_insert_user(
		array(
			'user_login' => $login,
			'user_pass'  => 'demo1234',
			'user_email' => $login . '@example.test',
			'role'       => $role,
		)
	);
}

lrm_demo_say( 'Benutzer: ' . implode( ', ', array_keys( $users ) ) . ' (Passwort: demo1234)' );

/* -------------------------------------------------------- Backend-Rechte. */

LRM_Backend::save(
	'mav',
	array(
		'enabled'        => 1,
		'allow_media'    => 1,
		'own_media_only' => 1,
		'types'          => array(
			'page' => array( 'mode' => 'selected', 'items' => array( $mav_page ), 'create' => 0, 'delete' => 0, 'own_only' => 0 ),
			'post' => array( 'mode' => 'terms', 'taxonomy' => 'category', 'terms' => array( $cat_mav ), 'create' => 1, 'delete' => 1, 'own_only' => 0, 'force_term' => 1 ),
		),
		// Das Verleihsystem bleibt dem Künstlerteam vorbehalten.
		'hidden_menus'   => array( 'edit-comments.php', 'themes.php', 'plugins.php', 'users.php', 'tools.php', 'options-general.php', 'lrm-overview', 'verleihsystem' ),
		'hide_new_menus' => 1,
		'hidden_widgets' => array( 'dashboard_right_now', 'dashboard_activity', 'dashboard_primary' ),
	)
);

LRM_Backend::save(
	'kunstteam',
	array(
		'enabled'        => 1,
		'allow_media'    => 1,
		'own_media_only' => 0,
		'types'          => array(),
		'hidden_menus'   => array( 'edit.php', 'edit.php?post_type=page', 'edit-comments.php', 'themes.php', 'plugins.php', 'users.php', 'tools.php', 'options-general.php', 'lrm-overview' ),
		'hide_new_menus' => 0,
	)
);

lrm_demo_say( 'Backend-Rechte für Mitarbeitervertretung und Künstlerteam gesetzt.' );

/* --------------------------------------------------------- Dateischutz. */

$settings                       = get_option( 'lrm_settings', array() );
$settings['protect_uploads']    = 1;
$settings['uploads_mode']       = 'login';
$settings['whitelist_branding'] = 1;
update_option( 'lrm_settings', $settings );

LRM_Settings::flush();
LRM_Media::write_htaccess();

lrm_demo_say( 'Dateischutz eingeschaltet, Serverregel geschrieben: ' . ( LRM_Media::htaccess_active() ? 'ja' : 'nein (bei nginx normal)' ) );

echo PHP_EOL . 'Fertig. Anmeldung als Administrator, dann "Rechte" im Menü öffnen.' . PHP_EOL . PHP_EOL;
