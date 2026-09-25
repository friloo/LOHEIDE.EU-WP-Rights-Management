<?php
/**
 * Auswertung der Zugriffsrechte.
 *
 * @package LOHEIDE_Rights_Management
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ermittelt die wirksame Regel eines Inhalts und entscheidet über den Zugriff.
 *
 * Reihenfolge der Auswertung:
 *   1. Gesperrte Rollen  -> Zugriff verweigert (hat immer Vorrang, auch gegen Administratoren).
 *   2. Umgehungsrecht    -> Zugriff erlaubt.
 *   3. Keine Regel aktiv -> Zugriff erlaubt.
 *   4. Sichtbarkeitsmodus (öffentlich / angemeldet / ausgewählte Rollen).
 */
class LRM_Access {

	/**
	 * Zwischenspeicher für wirksame Regeln.
	 *
	 * @var array
	 */
	protected static $rules = array();

	/**
	 * Zwischenspeicher für Prüfergebnisse.
	 *
	 * @var array
	 */
	protected static $checks = array();

	/**
	 * Zwischenspeicher für gesperrte Inhalts-IDs.
	 *
	 * @var array|null
	 */
	protected static $restricted_ids = null;

	/**
	 * Schutz vor Rekursion, während gesperrte IDs ermittelt werden.
	 *
	 * @var bool
	 */
	protected static $computing = false;

	/**
	 * Rohregel eines Inhalts (ohne Vererbung).
	 *
	 * @param int $post_id Inhalts-ID.
	 * @return LRM_Rule
	 */
	public static function get_rule( $post_id ) {
		return LRM_Rule::from_post( $post_id );
	}

	/**
	 * Wirksame Regel eines Inhalts inklusive Vererbung.
	 *
	 * Die Basisregel ist die eigene Regel oder – falls keine eigene Regel
	 * aktiv ist – die des nächstgelegenen übergeordneten Inhalts. Gesperrte
	 * Rollen werden über die gesamte Kette gesammelt: ein Verbot einer
	 * übergeordneten Seite kann von einer Unterseite nicht aufgehoben werden.
	 *
	 * @param int $post_id Inhalts-ID.
	 * @return LRM_Rule
	 */
	public static function get_effective_rule( $post_id ) {
		$post_id = (int) $post_id;

		if ( isset( self::$rules[ $post_id ] ) ) {
			return self::$rules[ $post_id ];
		}

		$own  = self::get_rule( $post_id );
		$rule = clone $own;

		if ( $own->inherit ) {
			$ancestors = self::get_ancestors( $post_id );

			foreach ( $ancestors as $ancestor_id ) {
				$parent_rule = self::get_rule( $ancestor_id );

				if ( ! $parent_rule->enabled || ! $parent_rule->propagate ) {
					continue;
				}

				// Verbote summieren sich über die gesamte Kette.
				$rule->denied = array_values( array_unique( array_merge( $rule->denied, $parent_rule->denied ) ) );

				if ( ! $rule->enabled ) {
					// Erste aktive übergeordnete Regel wird als Basis übernommen.
					$rule->enabled    = true;
					$rule->visibility = $parent_rule->visibility;
					$rule->allowed    = $parent_rule->allowed;
					$rule->inherited  = true;
					$rule->source_id  = $ancestor_id;

					if ( 'inherit' === $rule->action ) {
						$rule->action       = $parent_rule->action;
						$rule->redirect_url = $parent_rule->redirect_url;
					}

					if ( '' === $rule->message ) {
						$rule->message = $parent_rule->message;
					}

					if ( 'inherit' === $rule->hide ) {
						$rule->hide = $parent_rule->hide;
					}
				}
			}
		}

		/**
		 * Filtert die wirksame Regel eines Inhalts.
		 *
		 * @param LRM_Rule $rule    Wirksame Regel.
		 * @param int      $post_id Inhalts-ID.
		 */
		$rule = apply_filters( 'lrm_effective_rule', $rule, $post_id );

		self::$rules[ $post_id ] = $rule;

		return $rule;
	}

	/**
	 * Vorfahren eines Inhalts, beginnend beim direkten Elternteil.
	 *
	 * @param int $post_id Inhalts-ID.
	 * @return array
	 */
	protected static function get_ancestors( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post || ! is_post_type_hierarchical( $post->post_type ) ) {
			return array();
		}

		return array_map( 'intval', get_post_ancestors( $post_id ) );
	}

	/**
	 * Darf der Benutzer den Inhalt sehen?
	 *
	 * @param int          $post_id Inhalts-ID.
	 * @param WP_User|null $user    Benutzer, Standard: aktueller Benutzer.
	 * @return array {
	 *     @type bool     $allowed Zugriff erlaubt.
	 *     @type string   $reason  Begründung.
	 *     @type array    $roles   Auslösende Rollen.
	 *     @type LRM_Rule $rule    Wirksame Regel.
	 * }
	 */
	public static function check( $post_id, $user = null ) {
		$post_id = (int) $post_id;

		if ( null === $user ) {
			$user = wp_get_current_user();
		}

		$user_id  = $user instanceof WP_User ? (int) $user->ID : 0;
		$cache_key = $post_id . ':' . $user_id;

		if ( isset( self::$checks[ $cache_key ] ) ) {
			return self::$checks[ $cache_key ];
		}

		$user_roles = LRM_Roles::user_roles( $user );
		$can_bypass = ( $user_id && LRM_Settings::get( 'admin_bypass' ) && user_can( $user, LRM_Roles::CAP_BYPASS ) );

		$result = self::evaluate( $post_id, $user_roles, $can_bypass, (bool) $user_id );

		/**
		 * Filtert das Prüfergebnis.
		 *
		 * @param array        $result  Ergebnis.
		 * @param int          $post_id Inhalts-ID.
		 * @param WP_User|null $user    Benutzer.
		 */
		$result = apply_filters( 'lrm_check_access', $result, $post_id, $user );

		self::$checks[ $cache_key ] = $result;

		return $result;
	}

	/**
	 * Kurzform: Zugriff erlaubt?
	 *
	 * @param int          $post_id Inhalts-ID.
	 * @param WP_User|null $user    Benutzer.
	 * @return bool
	 */
	public static function can_view( $post_id, $user = null ) {
		$check = self::check( $post_id, $user );

		return ! empty( $check['allowed'] );
	}

	/**
	 * Kernauswertung: entscheidet anhand von Rollen über den Zugriff.
	 *
	 * Wird auch für die Rollensimulation in der Verwaltung verwendet.
	 *
	 * @param int   $post_id    Inhalts-ID.
	 * @param array $user_roles Rollen des Benutzers (inkl. Gast-Rolle).
	 * @param bool  $can_bypass Benutzer darf Beschränkungen umgehen.
	 * @param bool  $logged_in  Benutzer ist angemeldet.
	 * @return array Ergebnis-Array.
	 */
	public static function evaluate( $post_id, $user_roles, $can_bypass = false, $logged_in = null ) {
		$rule       = self::get_effective_rule( $post_id );
		$user_roles = array_values( (array) $user_roles );

		if ( null === $logged_in ) {
			$logged_in = ! in_array( LRM_Roles::GUEST, $user_roles, true );
		}

		// 1. Gesperrte Rollen haben immer Vorrang – auch gegenüber dem Umgehungsrecht.
		$blocked = array_values( array_intersect( $user_roles, $rule->denied ) );

		if ( ! empty( $blocked ) ) {
			return self::result( false, 'denied_role', $blocked, $rule );
		}

		// 2. Umgehungsrecht (z. B. Administratoren).
		if ( $can_bypass ) {
			return self::result( true, 'bypass', array(), $rule );
		}

		// 3. Keine aktive Regel.
		if ( ! $rule->enabled ) {
			return self::result( true, 'no_rule', array(), $rule );
		}

		// 4. Sichtbarkeitsmodus.
		switch ( $rule->visibility ) {
			case LRM_Rule::VISIBILITY_PUBLIC:
				return self::result( true, 'public', array(), $rule );

			case LRM_Rule::VISIBILITY_ROLES:
				$granted = array_values( array_intersect( $user_roles, $rule->allowed ) );

				return empty( $granted )
					? self::result( false, 'role_not_allowed', $user_roles, $rule )
					: self::result( true, 'allowed_role', $granted, $rule );

			case LRM_Rule::VISIBILITY_LOGGED_IN:
			default:
				return $logged_in
					? self::result( true, 'logged_in', array(), $rule )
					: self::result( false, 'login_required', array(), $rule );
		}
	}

	/**
	 * Ergebnis-Array erzeugen.
	 *
	 * @param bool     $allowed Zugriff erlaubt.
	 * @param string   $reason  Begründung.
	 * @param array    $roles   Auslösende Rollen.
	 * @param LRM_Rule $rule    Regel.
	 * @return array
	 */
	protected static function result( $allowed, $reason, $roles, $rule ) {
		return array(
			'allowed' => (bool) $allowed,
			'reason'  => $reason,
			'roles'   => array_values( (array) $roles ),
			'rule'    => $rule,
		);
	}

	/**
	 * Lesbare Begründung.
	 *
	 * @param array $check Prüfergebnis.
	 * @return string
	 */
	public static function reason_text( $check ) {
		$roles = isset( $check['roles'] ) ? implode( ', ', LRM_Roles::labels( $check['roles'] ) ) : '';

		switch ( $check['reason'] ) {
			case 'denied_role':
				/* translators: %s: Liste der Rollennamen. */
				return sprintf( __( 'Gesperrt über die Rolle: %s', 'loheide-rights-management' ), $roles );
			case 'role_not_allowed':
				return __( 'Die Rolle des Benutzers ist nicht freigegeben.', 'loheide-rights-management' );
			case 'login_required':
				return __( 'Anmeldung erforderlich.', 'loheide-rights-management' );
			case 'bypass':
				return __( 'Zugriff über das Umgehungsrecht.', 'loheide-rights-management' );
			case 'no_rule':
				return __( 'Für diesen Inhalt ist keine Einschränkung aktiv.', 'loheide-rights-management' );
			case 'public':
				return __( 'Inhalt ist öffentlich.', 'loheide-rights-management' );
			case 'logged_in':
				return __( 'Zugriff für angemeldete Benutzer.', 'loheide-rights-management' );
			case 'allowed_role':
				/* translators: %s: Liste der Rollennamen. */
				return sprintf( __( 'Zugriff über die Rolle: %s', 'loheide-rights-management' ), $roles );
		}

		return '';
	}

	/**
	 * Alle Inhalte mit aktiver eigener Regel.
	 *
	 * @param array $args Zusätzliche Abfrageargumente.
	 * @return array Inhalts-IDs.
	 */
	public static function get_ruled_post_ids( $args = array() ) {
		$post_types = LRM_Settings::protected_post_types();

		if ( empty( $post_types ) ) {
			return array();
		}

		$query_args = wp_parse_args(
			$args,
			array(
				'post_type'              => $post_types,
				'post_status'            => 'any',
				'posts_per_page'         => 500,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'     => LRM_Rule::META_ENABLED,
						'value'   => '1',
						'compare' => '=',
					),
				),
			)
		);

		$query = new WP_Query( $query_args );

		return array_map( 'intval', $query->posts );
	}

	/**
	 * IDs aller Inhalte, die der aktuelle Benutzer nicht sehen darf.
	 *
	 * Berücksichtigt auch untergeordnete Inhalte, die eine Regel erben.
	 *
	 * @return array
	 */
	public static function get_restricted_ids_for_current_user() {
		if ( null !== self::$restricted_ids ) {
			return self::$restricted_ids;
		}

		self::$computing = true;
		$candidates      = self::get_ruled_post_ids();
		$expanded   = $candidates;

		foreach ( $candidates as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post || ! is_post_type_hierarchical( $post->post_type ) ) {
				continue;
			}

			$children = get_posts(
				array(
					'post_type'        => $post->post_type,
					'post_status'      => 'any',
					'posts_per_page'   => 500,
					'fields'           => 'ids',
					'no_found_rows'    => true,
					'post_parent__in'  => array( $post_id ),
					'suppress_filters' => true,
				)
			);

			if ( $children ) {
				$expanded = array_merge( $expanded, array_map( 'intval', $children ) );
			}
		}

		$expanded  = array_values( array_unique( $expanded ) );
		$forbidden = array();

		foreach ( $expanded as $post_id ) {
			if ( ! self::can_view( $post_id ) ) {
				$forbidden[] = $post_id;
			}
		}

		self::$restricted_ids = $forbidden;
		self::$computing      = false;

		return $forbidden;
	}

	/**
	 * Läuft gerade die Ermittlung gesperrter Inhalte?
	 *
	 * Verhindert, dass eigene Abfragen erneut gefiltert werden.
	 *
	 * @return bool
	 */
	public static function is_computing() {
		return self::$computing;
	}

	/**
	 * Zwischenspeicher verwerfen.
	 *
	 * @param int|null $post_id Optional einzelner Inhalt.
	 */
	public static function flush( $post_id = null ) {
		if ( null === $post_id ) {
			self::$rules  = array();
			self::$checks = array();
		} else {
			$post_id = (int) $post_id;
			unset( self::$rules[ $post_id ] );

			foreach ( array_keys( self::$checks ) as $key ) {
				if ( 0 === strpos( $key, $post_id . ':' ) ) {
					unset( self::$checks[ $key ] );
				}
			}
		}

		self::$restricted_ids = null;
	}

	/**
	 * Gilt der Schutz für diesen Inhaltstyp?
	 *
	 * @param int|WP_Post $post Inhalt.
	 * @return bool
	 */
	public static function is_supported( $post ) {
		$post = get_post( $post );

		if ( ! $post ) {
			return false;
		}

		return in_array( $post->post_type, LRM_Settings::protected_post_types(), true );
	}
}
