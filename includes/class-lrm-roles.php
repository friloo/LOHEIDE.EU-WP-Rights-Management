<?php
/**
 * Rollen-Helfer.
 *
 * @package LOHEIDE_Rights_Management
 */

defined( 'ABSPATH' ) || exit;

/**
 * Kapselt den Zugriff auf die von WordPress bereitgestellten Rollen und
 * ergänzt sie um die virtuelle Rolle "Gast" (nicht angemeldet).
 */
class LRM_Roles {

	/**
	 * Schlüssel der virtuellen Rolle für nicht angemeldete Besucher.
	 */
	const GUEST = 'lrm_guest';

	/**
	 * Fähigkeit zum Verwalten der Berechtigungen.
	 */
	const CAP_MANAGE = 'lrm_manage_permissions';

	/**
	 * Fähigkeit, Beschränkungen zu umgehen.
	 */
	const CAP_BYPASS = 'lrm_bypass_restrictions';

	/**
	 * Zwischenspeicher.
	 *
	 * @var array|null
	 */
	protected static $cache = null;

	/**
	 * Alle auswählbaren Rollen inklusive der virtuellen Gast-Rolle.
	 *
	 * @return array Schlüssel => Anzeigename.
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$roles = array(
			self::GUEST => __( 'Gast (nicht angemeldet)', 'loheide-rights-management' ),
		);

		$wp_roles = wp_roles();

		foreach ( $wp_roles->get_names() as $key => $name ) {
			$roles[ $key ] = translate_user_role( $name );
		}

		/**
		 * Filtert die Liste der auswählbaren Rollen.
		 *
		 * @param array $roles Schlüssel => Anzeigename.
		 */
		self::$cache = apply_filters( 'lrm_selectable_roles', $roles );

		return self::$cache;
	}

	/**
	 * Nur die echten WordPress-Rollen (ohne Gast).
	 *
	 * @return array
	 */
	public static function wp_only() {
		$roles = self::all();
		unset( $roles[ self::GUEST ] );

		return $roles;
	}

	/**
	 * Anzeigename einer Rolle.
	 *
	 * @param string $role Rollenschlüssel.
	 * @return string
	 */
	public static function label( $role ) {
		$roles = self::all();

		if ( isset( $roles[ $role ] ) ) {
			return $roles[ $role ];
		}

		/* translators: %s: Rollenschlüssel. */
		return sprintf( __( 'Unbekannte Rolle (%s)', 'loheide-rights-management' ), $role );
	}

	/**
	 * Mehrere Rollenschlüssel in Anzeigenamen übersetzen.
	 *
	 * @param array $roles Rollenschlüssel.
	 * @return array
	 */
	public static function labels( $roles ) {
		return array_map( array( __CLASS__, 'label' ), (array) $roles );
	}

	/**
	 * Rollen eines Benutzers. Nicht angemeldete Besucher erhalten die
	 * virtuelle Rolle "Gast", damit sie in den Regeln adressierbar sind.
	 *
	 * @param WP_User|null $user Benutzer, Standard: aktueller Benutzer.
	 * @return array Rollenschlüssel.
	 */
	public static function user_roles( $user = null ) {
		if ( null === $user ) {
			$user = wp_get_current_user();
		}

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return array( self::GUEST );
		}

		$roles = array_values( (array) $user->roles );

		if ( empty( $roles ) ) {
			// Angemeldet, aber ohne Rolle (z. B. Multisite-Benutzer ohne Zuordnung).
			$roles = array();
		}

		/**
		 * Filtert die Rollen, mit denen ein Benutzer geprüft wird.
		 *
		 * @param array   $roles Rollenschlüssel.
		 * @param WP_User $user  Benutzer.
		 */
		return apply_filters( 'lrm_user_roles', $roles, $user );
	}

	/**
	 * Übergebene Rollenschlüssel gegen die bekannten Rollen prüfen.
	 *
	 * @param mixed $roles Rohdaten.
	 * @return array Bereinigte, eindeutige Rollenschlüssel.
	 */
	public static function sanitize_list( $roles ) {
		if ( ! is_array( $roles ) ) {
			return array();
		}

		$known = self::all();
		$clean = array();

		foreach ( $roles as $role ) {
			$role = sanitize_key( $role );

			if ( isset( $known[ $role ] ) && ! in_array( $role, $clean, true ) ) {
				$clean[] = $role;
			}
		}

		return $clean;
	}

	/**
	 * Anzahl der Benutzer pro Rolle.
	 *
	 * @param string $role Rollenschlüssel.
	 * @return int|null Null, wenn nicht ermittelbar (Gast-Rolle).
	 */
	public static function user_count( $role ) {
		if ( self::GUEST === $role ) {
			return null;
		}

		$counts = count_users();

		if ( isset( $counts['avail_roles'][ $role ] ) ) {
			return (int) $counts['avail_roles'][ $role ];
		}

		return 0;
	}
}
