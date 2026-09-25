<?php
/**
 * Aufräumen bei der Deinstallation.
 *
 * @package LOHEIDE_Rights_Management
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Serverregel im Uploads-Ordner entfernen. Bliebe sie stehen, wären nach dem
// Löschen des Plugins sämtliche Dateien nicht mehr erreichbar.
$lrm_uploads = wp_get_upload_dir();
$lrm_file    = trailingslashit( $lrm_uploads['basedir'] ) . '.htaccess';

if ( file_exists( $lrm_file ) && is_writable( $lrm_file ) ) {
	if ( ! function_exists( 'insert_with_markers' ) ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}

	insert_with_markers( $lrm_file, 'LOHEIDE.EU WP Rights Management', array() );
}

// Zwischenspeicher der Selbstprüfung.
delete_transient( 'lrm_self_test' );

// Einstellungen entfernen.
delete_option( 'lrm_settings' );

// Regeln an den Inhalten entfernen.
$meta_keys = array(
	'_lrm_enabled',
	'_lrm_visibility',
	'_lrm_allowed_roles',
	'_lrm_denied_roles',
	'_lrm_inherit',
	'_lrm_propagate',
	'_lrm_action',
	'_lrm_redirect_url',
	'_lrm_message',
	'_lrm_hide',
);

foreach ( $meta_keys as $meta_key ) {
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $meta_key ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
}

// Vergebene Fähigkeiten zurücknehmen.
if ( function_exists( 'wp_roles' ) ) {
	foreach ( wp_roles()->role_objects as $role ) {
		$role->remove_cap( 'lrm_manage_permissions' );
		$role->remove_cap( 'lrm_bypass_restrictions' );
	}
}
