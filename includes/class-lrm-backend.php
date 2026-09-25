<?php
/**
 * Datenmodell der Backend-Rechte.
 *
 * @package LOHEIDE_Rights_Management
 */

defined( 'ABSPATH' ) || exit;

/**
 * Verwaltet, was eine Rolle im Backend bearbeiten und sehen darf.
 *
 * Die Beschränkung greift, sobald für eine Rolle des Benutzers eine Regel
 * aktiv ist. Hat jemand mehrere beschränkte Rollen, werden die Freigaben
 * zusammengeführt. Nur das Umgehungsrecht (Standard: Administrator) hebt die
 * Beschränkung auf.
 */
class LRM_Backend {

	/**
	 * Options-Schlüssel.
	 */
	const OPTION = 'lrm_backend';

	/**
	 * Zwischenspeicher.
	 *
	 * @var array|null
	 */
	protected static $cache = null;

	/**
	 * Zwischenspeicher der zusammengeführten Konfiguration je Benutzer.
	 *
	 * @var array
	 */
	protected static $user_cache = array();

	/**
	 * Standardwerte einer Rollenregel.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'        => 0,
			'pages'          => array(),
			'categories'     => array(),
			'create_pages'   => 0,
			'create_posts'   => 1,
			'own_posts_only' => 0,
			'own_media_only' => 1,
			'allow_media'    => 1,
			'hidden_menus'   => array(),
			'granted_caps'   => array(),
		);
	}

	/**
	 * Alle Rollenregeln.
	 *
	 * @return array Rollenschlüssel => Regel.
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$clean = array();

		foreach ( $stored as $role => $config ) {
			$clean[ $role ] = wp_parse_args( (array) $config, self::defaults() );
		}

		self::$cache = $clean;

		return self::$cache;
	}

	/**
	 * Regel einer Rolle.
	 *
	 * @param string $role Rollenschlüssel.
	 * @return array
	 */
	public static function get( $role ) {
		$all = self::all();

		return isset( $all[ $role ] ) ? $all[ $role ] : self::defaults();
	}

	/**
	 * Ist für die Rolle eine Beschränkung aktiv?
	 *
	 * @param string $role Rollenschlüssel.
	 * @return bool
	 */
	public static function is_restricted( $role ) {
		$config = self::get( $role );

		return ! empty( $config['enabled'] );
	}

	/**
	 * Regel einer Rolle speichern und die nötigen Fähigkeiten angleichen.
	 *
	 * @param string $role   Rollenschlüssel.
	 * @param array  $config Regel.
	 */
	public static function save( $role, $config ) {
		$all = self::all();

		$config          = wp_parse_args( (array) $config, self::defaults() );
		$config          = self::sanitize( $config );
		$all[ $role ]    = $config;

		update_option( self::OPTION, $all );
		self::flush();

		// Fähigkeiten der Rolle an die Regel angleichen.
		self::sync_capabilities( $role );
	}

	/**
	 * Regel bereinigen.
	 *
	 * @param array $config Rohdaten.
	 * @return array
	 */
	public static function sanitize( $config ) {
		$clean = self::defaults();

		foreach ( array( 'enabled', 'create_pages', 'create_posts', 'own_posts_only', 'own_media_only', 'allow_media' ) as $flag ) {
			$clean[ $flag ] = empty( $config[ $flag ] ) ? 0 : 1;
		}

		$clean['pages']      = array_values( array_unique( array_map( 'absint', (array) ( isset( $config['pages'] ) ? $config['pages'] : array() ) ) ) );
		$clean['categories'] = array_values( array_unique( array_map( 'absint', (array) ( isset( $config['categories'] ) ? $config['categories'] : array() ) ) ) );

		$clean['pages']      = array_values( array_filter( $clean['pages'] ) );
		$clean['categories'] = array_values( array_filter( $clean['categories'] ) );

		$menus = isset( $config['hidden_menus'] ) ? (array) $config['hidden_menus'] : array();
		$clean['hidden_menus'] = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $menus ) ) ) );

		$caps = isset( $config['granted_caps'] ) ? (array) $config['granted_caps'] : array();
		$clean['granted_caps'] = array_values( array_unique( array_filter( array_map( 'sanitize_key', $caps ) ) ) );

		return $clean;
	}

	/**
	 * Zwischenspeicher verwerfen.
	 */
	public static function flush() {
		self::$cache      = null;
		self::$user_cache = array();
	}

	/* ------------------------------------------------------------------ *
	 * Fähigkeiten
	 * ------------------------------------------------------------------ */

	/**
	 * Fähigkeiten, die eine Rolle für die zugewiesene Arbeit benötigt.
	 *
	 * WordPress arbeitet mit Erlauben und anschließendem Einschränken: Damit
	 * eine Rolle eine fremde Seite bearbeiten kann, braucht sie zunächst das
	 * allgemeine Recht dazu. Die Begrenzung auf die zugewiesenen Inhalte
	 * übernimmt danach LRM_Backend_Guard über map_meta_cap.
	 *
	 * @param array $config Regel.
	 * @return array Fähigkeiten.
	 */
	public static function required_capabilities( $config ) {
		$caps = array( 'read' );

		if ( ! empty( $config['pages'] ) || ! empty( $config['create_pages'] ) ) {
			$caps[] = 'edit_pages';
			$caps[] = 'edit_published_pages';
			$caps[] = 'edit_others_pages';

			if ( ! empty( $config['create_pages'] ) ) {
				$caps[] = 'publish_pages';
			}
		}

		if ( ! empty( $config['categories'] ) ) {
			$caps[] = 'edit_posts';
			$caps[] = 'edit_published_posts';

			if ( empty( $config['own_posts_only'] ) ) {
				$caps[] = 'edit_others_posts';
			}

			if ( ! empty( $config['create_posts'] ) ) {
				$caps[] = 'publish_posts';
			}
		}

		if ( ! empty( $config['allow_media'] ) ) {
			$caps[] = 'upload_files';
		}

		/**
		 * Filtert die Fähigkeiten, die eine beschränkte Rolle erhält.
		 *
		 * @param array $caps   Fähigkeiten.
		 * @param array $config Regel.
		 */
		return array_values( array_unique( apply_filters( 'lrm_backend_required_caps', $caps, $config ) ) );
	}

	/**
	 * Fähigkeiten einer Rolle an ihre Regel angleichen.
	 *
	 * Vergibt fehlende Fähigkeiten und nimmt zurück, was das Plugin selbst
	 * vergeben hat und nicht mehr gebraucht wird. Fähigkeiten, welche die
	 * Rolle schon vorher besaß, bleiben unangetastet.
	 *
	 * @param string $role Rollenschlüssel.
	 */
	public static function sync_capabilities( $role ) {
		$role_object = get_role( $role );

		if ( ! $role_object ) {
			return;
		}

		$all     = self::all();
		$config  = isset( $all[ $role ] ) ? $all[ $role ] : self::defaults();
		$granted = (array) $config['granted_caps'];
		$needed  = empty( $config['enabled'] ) ? array() : self::required_capabilities( $config );

		// Nicht mehr benötigte, selbst vergebene Fähigkeiten zurücknehmen.
		foreach ( $granted as $cap ) {
			if ( ! in_array( $cap, $needed, true ) ) {
				$role_object->remove_cap( $cap );
			}
		}

		$now_granted = array();

		foreach ( $needed as $cap ) {
			if ( $role_object->has_cap( $cap ) && ! in_array( $cap, $granted, true ) ) {
				// Die Rolle hatte die Fähigkeit bereits – nicht als eigene merken.
				continue;
			}

			$role_object->add_cap( $cap );
			$now_granted[] = $cap;
		}

		$all[ $role ]['granted_caps'] = $now_granted;

		update_option( self::OPTION, $all );
		self::flush();
	}

	/**
	 * Alle vom Plugin vergebenen Fähigkeiten zurücknehmen.
	 */
	public static function revoke_all_capabilities() {
		foreach ( self::all() as $role => $config ) {
			$role_object = get_role( $role );

			if ( ! $role_object ) {
				continue;
			}

			foreach ( (array) $config['granted_caps'] as $cap ) {
				$role_object->remove_cap( $cap );
			}
		}
	}

	/* ------------------------------------------------------------------ *
	 * Sicht des Benutzers
	 * ------------------------------------------------------------------ */

	/**
	 * Zusammengeführte Regel für einen Benutzer.
	 *
	 * @param WP_User|null $user Benutzer, Standard: aktueller Benutzer.
	 * @return array|null Null, wenn keine Beschränkung gilt.
	 */
	public static function for_user( $user = null ) {
		if ( null === $user ) {
			$user = wp_get_current_user();
		}

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return null;
		}

		if ( isset( self::$user_cache[ $user->ID ] ) ) {
			return self::$user_cache[ $user->ID ];
		}

		$result = null;

		// Das Umgehungsrecht hebt die Beschränkung auf.
		if ( ! user_can( $user, LRM_Roles::CAP_BYPASS ) ) {
			$merged = null;

			foreach ( (array) $user->roles as $role ) {
				if ( ! self::is_restricted( $role ) ) {
					continue;
				}

				$config = self::get( $role );

				if ( null === $merged ) {
					$merged          = $config;
					$merged['roles'] = array( $role );
					continue;
				}

				// Freigaben mehrerer beschränkter Rollen werden zusammengeführt.
				$merged['pages']      = array_values( array_unique( array_merge( $merged['pages'], $config['pages'] ) ) );
				$merged['categories'] = array_values( array_unique( array_merge( $merged['categories'], $config['categories'] ) ) );
				$merged['roles'][]    = $role;

				foreach ( array( 'create_pages', 'create_posts', 'allow_media' ) as $flag ) {
					$merged[ $flag ] = ( $merged[ $flag ] || $config[ $flag ] ) ? 1 : 0;
				}

				foreach ( array( 'own_posts_only', 'own_media_only' ) as $flag ) {
					// Die strengere Einstellung gewinnt.
					$merged[ $flag ] = ( $merged[ $flag ] && $config[ $flag ] ) ? 1 : 0;
				}

				$merged['hidden_menus'] = array_values( array_intersect( $merged['hidden_menus'], $config['hidden_menus'] ) );
			}

			$result = $merged;
		}

		/**
		 * Filtert die wirksame Backend-Regel eines Benutzers.
		 *
		 * @param array|null $result Regel oder null.
		 * @param WP_User    $user   Benutzer.
		 */
		$result = apply_filters( 'lrm_backend_user_config', $result, $user );

		self::$user_cache[ $user->ID ] = $result;

		return $result;
	}

	/**
	 * Gilt für den aktuellen Benutzer eine Beschränkung?
	 *
	 * @return bool
	 */
	public static function current_user_restricted() {
		return null !== self::for_user();
	}

	/**
	 * Seiten, die der Benutzer bearbeiten darf – inklusive der Unterseiten
	 * zugewiesener Seiten, sofern gewünscht.
	 *
	 * @param array $config Regel.
	 * @return array Seiten-IDs.
	 */
	public static function allowed_page_ids( $config ) {
		$ids = isset( $config['pages'] ) ? array_map( 'absint', (array) $config['pages'] ) : array();

		/**
		 * Filtert die bearbeitbaren Seiten.
		 *
		 * @param array $ids    Seiten-IDs.
		 * @param array $config Regel.
		 */
		return array_values( array_unique( array_filter( apply_filters( 'lrm_backend_allowed_pages', $ids, $config ) ) ) );
	}

	/**
	 * Darf der Benutzer diesen Inhalt bearbeiten?
	 *
	 * @param int          $post_id Inhalts-ID.
	 * @param array        $config  Regel.
	 * @param WP_User|null $user    Benutzer.
	 * @return bool
	 */
	public static function can_edit_post( $post_id, $config, $user = null ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return false;
		}

		if ( null === $user ) {
			$user = wp_get_current_user();
		}

		switch ( $post->post_type ) {
			case 'page':
				return in_array( (int) $post->ID, self::allowed_page_ids( $config ), true );

			case 'post':
				if ( empty( $config['categories'] ) ) {
					return false;
				}

				if ( ! empty( $config['own_posts_only'] ) && (int) $post->post_author !== (int) $user->ID ) {
					return false;
				}

				$terms = wp_get_post_categories( $post->ID );

				// Ein neuer Beitrag ohne Kategorie bleibt bearbeitbar, solange er dem Benutzer gehört.
				if ( empty( $terms ) ) {
					return (int) $post->post_author === (int) $user->ID;
				}

				return (bool) array_intersect( array_map( 'absint', $terms ), array_map( 'absint', $config['categories'] ) );

			case 'attachment':
				if ( ! empty( $config['own_media_only'] ) ) {
					return (int) $post->post_author === (int) $user->ID;
				}

				return ! empty( $config['allow_media'] );
		}

		/**
		 * Filtert die Entscheidung für weitere Inhaltstypen.
		 *
		 * @param bool    $allowed Bearbeitung erlaubt.
		 * @param WP_Post $post    Inhalt.
		 * @param array   $config  Regel.
		 */
		return apply_filters( 'lrm_backend_can_edit_post', false, $post, $config );
	}
}
