<?php
/**
 * Aufräumen bei der Deinstallation.
 *
 * @package LOHEIDE_Rights_Management
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

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
