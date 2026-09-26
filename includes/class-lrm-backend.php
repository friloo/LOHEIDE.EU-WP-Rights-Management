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
 * Eine Beschränkung greift nur, wenn für *alle* Rollen des Benutzers eine
 * Regel aktiv ist. Hat er daneben eine Rolle ohne Regel, darf er arbeiten wie
 * gewohnt – sonst würde eine Nebenrolle wie „Abonnent" seine Arbeitsrolle
 * lahmlegen. Bei mehreren beschränkten Rollen gilt:
 *
 *   - Freigaben addieren sich. Wer über eine Rolle eine Seite bearbeiten darf,
 *     darf das auch mit einer zweiten, strengeren Rolle.
 *   - Sperren addieren sich ebenfalls. Was eine Rolle ausblendet, bleibt
 *     ausgeblendet – auch wenn eine andere Rolle es zeigen würde. Menüs
 *     freigegebener Inhaltstypen bleiben davon unberührt, sonst wären die
 *     zugewiesenen Inhalte nicht erreichbar.
 *   - Einschränkungen innerhalb einer Freigabe – „nur eigene Inhalte“, „nur
 *     eigene Dateien“ – bleiben bestehen, sobald eine Rolle sie verlangt.
 *
 * Nur das Umgehungsrecht (Standard: Administrator) hebt die Beschränkung auf.
 */
class LRM_Backend {

	/**
	 * Options-Schlüssel.
	 */
	const OPTION = 'lrm_backend';

	/**
	 * Option mit den zuletzt gesehenen Dashboard-Bereichen.
	 */
	const WIDGETS_OPTION = 'lrm_dashboard_widgets';

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
			'enabled'         => 0,
			'block_admin'     => 0,
			'types'           => array(),
			'allow_media'     => 1,
			'own_media_only'  => 1,
			'hidden_menus'    => array(),
			'known_menus'     => array(),
			'hide_new_menus'  => 0,
			'hidden_widgets'  => array(),
			'granted_caps'    => array(),
		);
	}

	/**
	 * Standardwerte eines Inhaltstyps innerhalb einer Regel.
	 *
	 * @return array
	 */
	public static function type_defaults() {
		return array(
			// none|all|selected|terms
			'mode'       => 'none',
			'items'      => array(),
			'terms'      => array(),
			'taxonomy'   => '',
			'create'     => 0,
			'delete'     => 0,
			'own_only'   => 0,
			'force_term' => 1,
		);
	}

	/**
	 * Inhaltstypen, die sich zuweisen lassen.
	 *
	 * @return array Slug => Anzeigename.
	 */
	public static function managed_post_types() {
		$types  = get_post_types( array( 'show_ui' => true ), 'objects' );
		$result = array();

		foreach ( $types as $type ) {
			if ( in_array( $type->name, array( 'attachment', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_global_styles', 'wp_font_family', 'wp_font_face' ), true ) ) {
				continue;
			}

			$result[ $type->name ] = $type->labels->name;
		}

		/**
		 * Filtert die zuweisbaren Inhaltstypen.
		 *
		 * @param array $result Slug => Anzeigename.
		 */
		return apply_filters( 'lrm_backend_post_types', $result );
	}

	/**
	 * Taxonomien eines Inhaltstyps, über die sich Inhalte eingrenzen lassen.
	 *
	 * @param string $post_type Inhaltstyp.
	 * @return array Slug => Anzeigename.
	 */
	public static function taxonomies_for( $post_type ) {
		$result = array();

		foreach ( get_object_taxonomies( $post_type, 'objects' ) as $taxonomy ) {
			if ( ! $taxonomy->show_ui || ! $taxonomy->hierarchical && 'post_tag' === $taxonomy->name ) {
				continue;
			}

			$result[ $taxonomy->name ] = $taxonomy->labels->name;
		}

		return $result;
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
			$clean[ $role ] = self::prepare( (array) $config );
		}

		self::$cache = $clean;

		return self::$cache;
	}

	/**
	 * Regel vervollständigen und ältere Fassungen überführen.
	 *
	 * Frühere Versionen kannten nur "pages" und "categories". Diese Angaben
	 * werden in die allgemeine Form je Inhaltstyp übernommen.
	 *
	 * @param array $config Gespeicherte Regel.
	 * @return array
	 */
	protected static function prepare( $config ) {
		$config = wp_parse_args( $config, self::defaults() );

		if ( empty( $config['types'] ) ) {
			$config['types'] = array();

			if ( ! empty( $config['pages'] ) || ! empty( $config['create_pages'] ) ) {
				$config['types']['page'] = wp_parse_args(
					array(
						'mode'   => 'selected',
						'items'  => array_map( 'absint', (array) $config['pages'] ),
						'create' => empty( $config['create_pages'] ) ? 0 : 1,
					),
					self::type_defaults()
				);
			}

			if ( ! empty( $config['categories'] ) ) {
				$config['types']['post'] = wp_parse_args(
					array(
						'mode'     => 'terms',
						'taxonomy' => 'category',
						'terms'    => array_map( 'absint', (array) $config['categories'] ),
						'create'   => empty( $config['create_posts'] ) ? 0 : 1,
						'own_only' => empty( $config['own_posts_only'] ) ? 0 : 1,
					),
					self::type_defaults()
				);
			}
		}

		unset( $config['pages'], $config['categories'], $config['create_pages'], $config['create_posts'], $config['own_posts_only'] );

		foreach ( $config['types'] as $slug => $type ) {
			$config['types'][ $slug ] = wp_parse_args( (array) $type, self::type_defaults() );
		}

		return $config;
	}

	/**
	 * Regel einer Rolle.
	 *
	 * @param string $role Rollenschlüssel.
	 * @return array
	 */
	public static function get( $role ) {
		$all = self::all();

		return isset( $all[ $role ] ) ? $all[ $role ] : self::prepare( array() );
	}

	/**
	 * Regel eines Inhaltstyps innerhalb einer Rollenregel.
	 *
	 * @param array  $config    Regel.
	 * @param string $post_type Inhaltstyp.
	 * @return array
	 */
	public static function type_config( $config, $post_type ) {
		if ( isset( $config['types'][ $post_type ] ) ) {
			return wp_parse_args( (array) $config['types'][ $post_type ], self::type_defaults() );
		}

		return self::type_defaults();
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
		$all    = self::all();
		$config = wp_parse_args( (array) $config, self::defaults() );

		// Die Buchführung über vergebene Fähigkeiten gehört dem Plugin, nicht
		// dem Aufrufer. Ginge sie beim Speichern verloren, ließen sich einmal
		// vergebene Fähigkeiten später nicht mehr zurücknehmen.
		$config['granted_caps'] = isset( $all[ $role ]['granted_caps'] )
			? (array) $all[ $role ]['granted_caps']
			: array();

		$all[ $role ] = self::sanitize( $config );

		update_option( self::OPTION, $all );
		self::flush();

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

		foreach ( array( 'enabled', 'block_admin', 'allow_media', 'own_media_only', 'hide_new_menus' ) as $flag ) {
			$clean[ $flag ] = empty( $config[ $flag ] ) ? 0 : 1;
		}

		$types = isset( $config['types'] ) ? (array) $config['types'] : array();

		foreach ( $types as $slug => $type ) {
			$slug = sanitize_key( $slug );
			$type = wp_parse_args( (array) $type, self::type_defaults() );

			$mode = sanitize_key( $type['mode'] );
			$mode = in_array( $mode, array( 'none', 'all', 'selected', 'terms' ), true ) ? $mode : 'none';

			if ( 'none' === $mode ) {
				continue;
			}

			$clean['types'][ $slug ] = array(
				'mode'       => $mode,
				'items'      => array_values( array_unique( array_filter( array_map( 'absint', (array) $type['items'] ) ) ) ),
				'terms'      => array_values( array_unique( array_filter( array_map( 'absint', (array) $type['terms'] ) ) ) ),
				'taxonomy'   => sanitize_key( $type['taxonomy'] ),
				'create'     => empty( $type['create'] ) ? 0 : 1,
				'delete'     => empty( $type['delete'] ) ? 0 : 1,
				'own_only'   => empty( $type['own_only'] ) ? 0 : 1,
				'force_term' => empty( $type['force_term'] ) ? 0 : 1,
			);
		}

		foreach ( array( 'hidden_menus', 'known_menus', 'hidden_widgets' ) as $list ) {
			$values          = isset( $config[ $list ] ) ? (array) $config[ $list ] : array();
			$clean[ $list ]  = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $values ) ) ) );
		}

		$caps                  = isset( $config['granted_caps'] ) ? (array) $config['granted_caps'] : array();
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
	 * eine Rolle einen fremden Inhalt bearbeiten kann, braucht sie zunächst das
	 * allgemeine Recht dazu. Die Begrenzung auf die zugewiesenen Inhalte
	 * übernimmt danach LRM_Backend_Guard über map_meta_cap.
	 *
	 * @param array $config Regel.
	 * @return array Fähigkeiten.
	 */
	public static function required_capabilities( $config ) {
		$caps = array( 'read' );

		foreach ( (array) $config['types'] as $slug => $type ) {
			$type = wp_parse_args( (array) $type, self::type_defaults() );

			if ( 'none' === $type['mode'] ) {
				continue;
			}

			$object = get_post_type_object( $slug );

			if ( ! $object || ! isset( $object->cap ) ) {
				continue;
			}

			$caps[] = $object->cap->edit_posts;
			$caps[] = $object->cap->edit_published_posts;

			if ( empty( $type['own_only'] ) ) {
				$caps[] = $object->cap->edit_others_posts;
			}

			if ( ! empty( $type['create'] ) ) {
				$caps[] = $object->cap->publish_posts;
			}

			if ( ! empty( $type['delete'] ) ) {
				$caps[] = $object->cap->delete_posts;
				$caps[] = $object->cap->delete_published_posts;

				if ( empty( $type['own_only'] ) ) {
					$caps[] = $object->cap->delete_others_posts;
				}
			}

			if ( ! empty( $object->cap->read_private_posts ) ) {
				$caps[] = $object->cap->read_private_posts;
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
		$caps = apply_filters( 'lrm_backend_required_caps', $caps, $config );

		// Verwaltungsrechte werden nie vergeben.
		$forbidden = array( 'manage_options', 'edit_users', 'promote_users', 'delete_users', 'activate_plugins', 'edit_plugins', 'edit_themes', 'switch_themes', 'install_plugins', 'install_themes', 'update_core', 'edit_files', 'unfiltered_html', LRM_Roles::CAP_BYPASS, LRM_Roles::CAP_MANAGE );

		return array_values( array_diff( array_unique( array_filter( $caps ) ), $forbidden ) );
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

		foreach ( $granted as $cap ) {
			if ( ! in_array( $cap, $needed, true ) ) {
				$role_object->remove_cap( $cap );
			}
		}

		$now_granted = array();

		foreach ( $needed as $cap ) {
			if ( $role_object->has_cap( $cap ) && ! in_array( $cap, $granted, true ) ) {
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
	/**
	 * Die Fähigkeiten aller gespeicherten Regeln neu vergeben.
	 *
	 * Gegenstück zu revoke_all_capabilities(): Nach einer Deaktivierung stehen
	 * die Regeln noch, die dazugehörigen Fähigkeiten aber nicht mehr. Ohne
	 * diesen Schritt bliebe eine eingerichtete Rolle nach dem Aktivieren ohne
	 * Rechte – die Einstellungen sähen aus, als wären sie verloren.
	 */
	public static function restore_capabilities() {
		self::flush();

		foreach ( array_keys( self::all() ) as $role ) {
			self::sync_capabilities( $role );
		}
	}

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

		if ( ! user_can( $user, LRM_Roles::CAP_BYPASS ) ) {
			$merged      = null;
			$unrestricted = false;

			foreach ( (array) $user->roles as $role ) {
				if ( ! self::is_restricted( $role ) ) {
					// Eine Rolle ohne eigene Beschränkung ist die weitestgehende
					// Freigabe: Sie soll normal arbeiten dürfen. Dann bleibt der
					// Benutzer insgesamt unbeschränkt – sonst würde eine
					// Nebenrolle wie „Abonnent" die Arbeitsrolle lahmlegen.
					$unrestricted = true;
					break;
				}

				$config = self::locked_out( self::get( $role ) );

				if ( null === $merged ) {
					$merged          = $config;
					$merged['roles'] = array( $role );
					continue;
				}

				$merged['types']   = self::merge_types( $merged['types'], $config['types'] );
				$merged['roles'][] = $role;

				// Was jemand bearbeiten darf, addiert sich über seine Rollen:
				// Die weiter gefasste Freigabe gewinnt.
				//
				// Einschränkungen innerhalb einer Freigabe gehen den umgekehrten
				// Weg: „nur eigene Dateien“ bleibt bestehen, sobald eine Rolle es
				// verlangt. Rollen ohne Zugriff auf die Mediathek reden dabei
				// nicht mit.
				if ( ! empty( $config['allow_media'] ) ) {
					$merged['own_media_only'] = empty( $merged['allow_media'] )
						? $config['own_media_only']
						: ( ( $merged['own_media_only'] || $config['own_media_only'] ) ? 1 : 0 );
				}

				$merged['allow_media'] = ( $merged['allow_media'] || $config['allow_media'] ) ? 1 : 0;

				// Zugang ist eine Freigabe: Lässt eine Rolle ins Backend, gilt das.
				$merged['block_admin'] = ( $merged['block_admin'] && $config['block_admin'] ) ? 1 : 0;

				// Was ausgeblendet ist, bleibt ausgeblendet, sobald es eine Rolle
				// ausblendet – wie im Frontend gewinnt die Sperre. Menüs
				// freigegebener Inhaltstypen nimmt LRM_Backend_Guard davon aus.
				// Nur Rollen, die neue Menüpunkte verbergen, bringen eine Schranke
				// mit: Was einer solchen Rolle unbekannt war, gilt als neu und
				// bleibt verborgen. Deshalb die Schnittmenge – eine Union wäre
				// eine Freigabe und würde die Sperre der strengeren Rolle
				// aufheben. Rollen, die neue Menüs zulassen, reden nicht mit.
				if ( ! empty( $config['hide_new_menus'] ) ) {
					$merged['known_menus'] = empty( $merged['hide_new_menus'] )
						? $config['known_menus']
						: array_values( array_intersect( $merged['known_menus'], $config['known_menus'] ) );
				}

				$merged['hide_new_menus'] = ( $merged['hide_new_menus'] || $config['hide_new_menus'] ) ? 1 : 0;

				$merged['hidden_menus']   = array_values( array_unique( array_merge( $merged['hidden_menus'], $config['hidden_menus'] ) ) );
				$merged['hidden_widgets'] = array_values( array_unique( array_merge( $merged['hidden_widgets'], $config['hidden_widgets'] ) ) );
			}

			$result = $unrestricted ? null : $merged;
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
	 * Inhaltstyp-Regeln zweier Rollen zusammenführen.
	 *
	 * @param array $a Erste Regel.
	 * @param array $b Zweite Regel.
	 * @return array
	 */
	/**
	 * Eine Rolle ohne Zugang zum Verwaltungsbereich gibt nichts frei.
	 *
	 * „Kein Zugang" heißt: Diese Rolle soll im Backend nichts zu sehen bekommen.
	 * Öffnet eine zweite Rolle den Zugang, darf von der gesperrten Rolle deshalb
	 * kein Menüpunkt und kein Dashboard-Bereich übrig bleiben – sonst wäre eine
	 * vollständige Sperre großzügiger als eine teilweise. Sichtbar bleibt nur,
	 * was LRM_Backend_Guard schützt: das Dashboard und die Menüs der
	 * Inhaltstypen, die eine andere Rolle freigibt.
	 *
	 * @param array $config Regel einer Rolle.
	 * @return array
	 */
	protected static function locked_out( $config ) {
		if ( empty( $config['block_admin'] ) ) {
			return $config;
		}

		$config['hide_new_menus'] = 1;
		$config['known_menus']    = array();
		$config['hidden_widgets'] = array_values(
			array_unique( array_merge( (array) $config['hidden_widgets'], array_keys( self::known_widgets() ) ) )
		);

		return $config;
	}

	protected static function merge_types( $a, $b ) {
		foreach ( $b as $slug => $type ) {
			if ( ! isset( $a[ $slug ] ) ) {
				$a[ $slug ] = $type;
				continue;
			}

			$first  = $a[ $slug ];
			$second = $type;

			// Die weiter gefasste Freigabe gewinnt.
			if ( 'all' === $first['mode'] || 'all' === $second['mode'] ) {
				$first['mode'] = 'all';
			} elseif ( $first['mode'] !== $second['mode'] ) {
				// Auswahl und Begriffe nebeneinander: beides behalten.
				$first['mode'] = 'selected' === $first['mode'] ? 'selected' : $second['mode'];
			}

			$first['items'] = array_values( array_unique( array_merge( $first['items'], $second['items'] ) ) );
			$first['terms'] = array_values( array_unique( array_merge( $first['terms'], $second['terms'] ) ) );

			foreach ( array( 'create', 'delete' ) as $flag ) {
				$first[ $flag ] = ( $first[ $flag ] || $second[ $flag ] ) ? 1 : 0;
			}

			// „Nur selbst verfasste Inhalte“ ist eine Einschränkung: Verlangt sie
			// eine der Rollen, bleibt sie bestehen.
			$first['own_only'] = ( $first['own_only'] || $second['own_only'] ) ? 1 : 0;

			$a[ $slug ] = $first;
		}

		return $a;
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
	 * Inhalts-IDs, die bei einer Auswahl freigegeben sind.
	 *
	 * @param array  $config    Regel.
	 * @param string $post_type Inhaltstyp.
	 * @return array
	 */
	public static function allowed_ids( $config, $post_type ) {
		$type = self::type_config( $config, $post_type );

		/**
		 * Filtert die bearbeitbaren Inhalte eines Typs.
		 *
		 * @param array  $ids       Inhalts-IDs.
		 * @param string $post_type Inhaltstyp.
		 * @param array  $config    Regel.
		 */
		return array_values( array_unique( array_filter( apply_filters( 'lrm_backend_allowed_items', array_map( 'absint', $type['items'] ), $post_type, $config ) ) ) );
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

		if ( 'attachment' === $post->post_type ) {
			if ( empty( $config['allow_media'] ) ) {
				return false;
			}

			if ( ! empty( $config['own_media_only'] ) ) {
				return (int) $post->post_author === (int) $user->ID;
			}

			return true;
		}

		$type = self::type_config( $config, $post->post_type );

		if ( 'none' === $type['mode'] ) {
			/**
			 * Filtert die Entscheidung für nicht zugewiesene Inhaltstypen.
			 *
			 * @param bool    $allowed Bearbeitung erlaubt.
			 * @param WP_Post $post    Inhalt.
			 * @param array   $config  Regel.
			 */
			return apply_filters( 'lrm_backend_can_edit_post', false, $post, $config );
		}

		if ( ! empty( $type['own_only'] ) && (int) $post->post_author !== (int) $user->ID ) {
			return false;
		}

		switch ( $type['mode'] ) {
			case 'all':
				return true;

			case 'selected':
				return in_array( (int) $post->ID, self::allowed_ids( $config, $post->post_type ), true );

			case 'terms':
				if ( empty( $type['terms'] ) ) {
					return false;
				}

				$taxonomy = $type['taxonomy'] ? $type['taxonomy'] : 'category';
				$assigned = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );

				if ( is_wp_error( $assigned ) ) {
					return false;
				}

				// Ein neuer Inhalt ohne Zuordnung bleibt bearbeitbar, solange er dem Benutzer gehört.
				if ( empty( $assigned ) ) {
					return (int) $post->post_author === (int) $user->ID;
				}

				return (bool) array_intersect( array_map( 'absint', $assigned ), array_map( 'absint', $type['terms'] ) );
		}

		return false;
	}

	/**
	 * Darf der Benutzer diesen Inhalt löschen?
	 *
	 * @param int          $post_id Inhalts-ID.
	 * @param array        $config  Regel.
	 * @param WP_User|null $user    Benutzer.
	 * @return bool
	 */
	public static function can_delete_post( $post_id, $config, $user = null ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return false;
		}

		if ( 'attachment' === $post->post_type ) {
			return self::can_edit_post( $post_id, $config, $user );
		}

		$type = self::type_config( $config, $post->post_type );

		if ( empty( $type['delete'] ) ) {
			return false;
		}

		return self::can_edit_post( $post_id, $config, $user );
	}

	/* ------------------------------------------------------------------ *
	 * Dashboard-Bereiche
	 * ------------------------------------------------------------------ */

	/**
	 * Bekannte Dashboard-Bereiche.
	 *
	 * @return array Schlüssel => Beschriftung.
	 */
	public static function known_widgets() {
		$stored = get_option( self::WIDGETS_OPTION, array() );

		if ( ! is_array( $stored ) || empty( $stored ) ) {
			// Rückfall auf die Bereiche, die WordPress selbst mitbringt.
			return array(
				'dashboard_right_now'    => __( 'Auf einen Blick', 'loheide-rights-management' ),
				'dashboard_activity'     => __( 'Aktivität', 'loheide-rights-management' ),
				'dashboard_quick_press'  => __( 'Schnellentwurf', 'loheide-rights-management' ),
				'dashboard_primary'      => __( 'WordPress-Neuigkeiten', 'loheide-rights-management' ),
				'dashboard_site_health'  => __( 'Website-Zustand', 'loheide-rights-management' ),
			);
		}

		return $stored;
	}

	/**
	 * Gesehene Dashboard-Bereiche merken.
	 *
	 * @param array $widgets Schlüssel => Beschriftung.
	 */
	public static function remember_widgets( $widgets ) {
		if ( empty( $widgets ) ) {
			return;
		}

		$known = self::known_widgets();
		$merged = array_merge( $known, $widgets );

		if ( $merged !== $known ) {
			update_option( self::WIDGETS_OPTION, $merged );
		}
	}
}
