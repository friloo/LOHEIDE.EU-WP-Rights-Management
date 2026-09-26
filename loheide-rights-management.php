<?php
/**
 * Plugin Name:       LOHEIDE.EU WP Rights Management
 * Plugin URI:        https://loheide.eu
 * Description:       Seitenbasierte Zugriffsrechte für WordPress: komplette Seiten nach Login und WordPress-Rollen freigeben oder sperren. Gesperrte Rollen haben immer Vorrang. Entwickelt von LOHEIDE.EU.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            LOHEIDE.EU
 * Author URI:        https://loheide.eu
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       loheide-rights-management
 * Domain Path:       /languages
 *
 * @package LOHEIDE_Rights_Management
 */

defined( 'ABSPATH' ) || exit;

define( 'LRM_VERSION', '1.0.0' );
define( 'LRM_FILE', __FILE__ );
define( 'LRM_DIR', plugin_dir_path( __FILE__ ) );
define( 'LRM_URL', plugin_dir_url( __FILE__ ) );
define( 'LRM_BASENAME', plugin_basename( __FILE__ ) );
define( 'LRM_NAME', 'LOHEIDE.EU WP Rights Management' );
define( 'LRM_VENDOR', 'LOHEIDE.EU' );
define( 'LRM_VENDOR_URL', 'https://loheide.eu' );

/**
 * Läuft diese Installation auf einer geeigneten Grundlage?
 *
 * Geprüft wird vor dem Laden der Klassen. Ohne diesen Schritt bräche das Plugin
 * auf einer zu alten Umgebung mit einem Fatal Error ab, und der Betreiber sähe
 * nur eine weiße Seite statt einer Erklärung.
 *
 * @return string Leer, wenn alles passt, sonst die Meldung.
 */
function lrm_environment_problem() {
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		return sprintf(
			/* translators: 1: benötigte Version, 2: vorhandene Version. */
			__( 'Dieses Plugin benötigt PHP %1$s oder neuer. Auf diesem Server läuft PHP %2$s.', 'loheide-rights-management' ),
			'7.4',
			PHP_VERSION
		);
	}

	if ( version_compare( get_bloginfo( 'version' ), '5.8', '<' ) ) {
		return sprintf(
			/* translators: 1: benötigte Version, 2: vorhandene Version. */
			__( 'Dieses Plugin benötigt WordPress %1$s oder neuer. Diese Website nutzt WordPress %2$s.', 'loheide-rights-management' ),
			'5.8',
			get_bloginfo( 'version' )
		);
	}

	return '';
}

/**
 * Die Klassen des Plugins, in der Reihenfolge ihrer Abhängigkeiten.
 *
 * @return array
 */
function lrm_class_files() {
	return array(
		'class-lrm-roles.php',
		'class-lrm-rule.php',
		'class-lrm-settings.php',
		'class-lrm-access.php',
		'class-lrm-metabox.php',
		'class-lrm-admin.php',
		'class-lrm-frontend.php',
		'class-lrm-media.php',
		'class-lrm-backend.php',
		'class-lrm-backend-guard.php',
		'class-lrm-backend-admin.php',
		'class-lrm-shortcodes.php',
		'class-lrm-plugin.php',
	);
}

/**
 * Hinweis im Verwaltungsbereich ausgeben, ohne das Plugin zu starten.
 *
 * @param string $message Meldung.
 */
function lrm_halt_with_notice( $message ) {
	add_action(
		'admin_notices',
		function () use ( $message ) {
			printf(
				'<div class="notice notice-error"><p><strong>%1$s</strong><br>%2$s</p></div>',
				esc_html( LRM_NAME ),
				esc_html( $message )
			);
		}
	);
}

$lrm_problem = lrm_environment_problem();
$lrm_missing = array();

if ( '' === $lrm_problem ) {
	foreach ( lrm_class_files() as $lrm_file ) {
		if ( ! is_readable( LRM_DIR . 'includes/' . $lrm_file ) ) {
			$lrm_missing[] = $lrm_file;
		}
	}

	if ( ! empty( $lrm_missing ) ) {
		// Der häufigste Fall in der Praxis: Beim Hochladen über FTP sind einzelne
		// Dateien liegengeblieben. Ohne diese Prüfung endete der nächste Aufruf
		// in einem Fatal Error – auch für den Administrator, der ihn beheben soll.
		$lrm_problem = sprintf(
			/* translators: %s: Liste der Dateinamen. */
			__( 'Die Installation ist unvollständig. Diese Dateien fehlen im Ordner „includes“: %s. Bitte laden Sie das Plugin vollständig hoch.', 'loheide-rights-management' ),
			implode( ', ', $lrm_missing )
		);
	}
}

if ( '' !== $lrm_problem ) {
	lrm_halt_with_notice( $lrm_problem );

	return;
}

foreach ( lrm_class_files() as $lrm_file ) {
	require_once LRM_DIR . 'includes/' . $lrm_file;
}

if ( ! function_exists( 'lrm' ) ) {
	/**
	 * Zentrale Plugin-Instanz.
	 *
	 * Der kurze Name kann mit einem anderen Plugin zusammentreffen. Die Prüfung
	 * kostet nichts und verhindert einen Fatal Error, der sonst beide Plugins
	 * lahmlegte.
	 *
	 * @return LRM_Plugin
	 */
	function lrm() {
		return LRM_Plugin::instance();
	}
}

lrm();

register_activation_hook( __FILE__, array( 'LRM_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'LRM_Plugin', 'deactivate' ) );
