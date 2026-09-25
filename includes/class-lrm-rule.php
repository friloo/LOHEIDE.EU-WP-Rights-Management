<?php
/**
 * Regel-Objekt.
 *
 * @package LOHEIDE_Rights_Management
 */

defined( 'ABSPATH' ) || exit;

/**
 * Beschreibt die Berechtigungsregel eines einzelnen Inhalts.
 */
class LRM_Rule {

	/**
	 * Meta-Schlüssel.
	 */
	const META_ENABLED     = '_lrm_enabled';
	const META_VISIBILITY  = '_lrm_visibility';
	const META_ALLOWED     = '_lrm_allowed_roles';
	const META_DENIED      = '_lrm_denied_roles';
	const META_INHERIT     = '_lrm_inherit';
	const META_PROPAGATE   = '_lrm_propagate';
	const META_ACTION      = '_lrm_action';
	const META_REDIRECT    = '_lrm_redirect_url';
	const META_MESSAGE     = '_lrm_message';
	const META_HIDE        = '_lrm_hide';

	/**
	 * Sichtbarkeitsmodi.
	 */
	const VISIBILITY_PUBLIC    = 'public';
	const VISIBILITY_LOGGED_IN = 'logged_in';
	const VISIBILITY_ROLES     = 'roles';

	/**
	 * Regel ist aktiv.
	 *
	 * @var bool
	 */
	public $enabled = false;

	/**
	 * Sichtbarkeitsmodus.
	 *
	 * @var string
	 */
	public $visibility = self::VISIBILITY_PUBLIC;

	/**
	 * Erlaubte Rollen.
	 *
	 * @var array
	 */
	public $allowed = array();

	/**
	 * Gesperrte Rollen (haben immer Vorrang).
	 *
	 * @var array
	 */
	public $denied = array();

	/**
	 * Regel von übergeordneten Seiten übernehmen.
	 *
	 * @var bool
	 */
	public $inherit = true;

	/**
	 * Regel an untergeordnete Seiten weitergeben.
	 *
	 * @var bool
	 */
	public $propagate = true;

	/**
	 * Aktion bei verweigertem Zugriff: inherit|message|login|404|redirect.
	 *
	 * @var string
	 */
	public $action = 'inherit';

	/**
	 * Ziel-URL für die Weiterleitung.
	 *
	 * @var string
	 */
	public $redirect_url = '';

	/**
	 * Individuelle Hinweismeldung.
	 *
	 * @var string
	 */
	public $message = '';

	/**
	 * Inhalt in Menüs/Listen verbergen: inherit|yes|no.
	 *
	 * @var string
	 */
	public $hide = 'inherit';

	/**
	 * ID des Inhalts, zu dem die Regel gehört.
	 *
	 * @var int
	 */
	public $post_id = 0;

	/**
	 * ID des Inhalts, von dem die wirksame Regel stammt.
	 *
	 * @var int
	 */
	public $source_id = 0;

	/**
	 * Regel wurde geerbt.
	 *
	 * @var bool
	 */
	public $inherited = false;

	/**
	 * Regel aus den Meta-Daten eines Inhalts lesen.
	 *
	 * @param int $post_id Inhalts-ID.
	 * @return LRM_Rule
	 */
	public static function from_post( $post_id ) {
		$post_id = (int) $post_id;
		$rule    = new self();

		$rule->post_id      = $post_id;
		$rule->source_id    = $post_id;
		$rule->enabled      = (bool) get_post_meta( $post_id, self::META_ENABLED, true );
		$rule->allowed      = LRM_Roles::sanitize_list( get_post_meta( $post_id, self::META_ALLOWED, true ) );
		$rule->denied       = LRM_Roles::sanitize_list( get_post_meta( $post_id, self::META_DENIED, true ) );
		$rule->redirect_url = (string) get_post_meta( $post_id, self::META_REDIRECT, true );
		$rule->message      = (string) get_post_meta( $post_id, self::META_MESSAGE, true );
		$rule->visibility   = self::sanitize_visibility( get_post_meta( $post_id, self::META_VISIBILITY, true ) );
		$rule->action       = self::sanitize_action( get_post_meta( $post_id, self::META_ACTION, true ) );
		$rule->hide         = self::sanitize_tristate( get_post_meta( $post_id, self::META_HIDE, true ) );

		$inherit   = get_post_meta( $post_id, self::META_INHERIT, true );
		$propagate = get_post_meta( $post_id, self::META_PROPAGATE, true );

		// Ohne gespeicherten Wert gelten die Standardeinstellungen.
		$rule->inherit   = ( '' === $inherit ) ? (bool) LRM_Settings::get( 'inherit_default' ) : (bool) $inherit;
		$rule->propagate = ( '' === $propagate ) ? true : (bool) $propagate;

		return $rule;
	}

	/**
	 * Sichtbarkeitsmodus bereinigen.
	 *
	 * @param string $value Rohwert.
	 * @return string
	 */
	public static function sanitize_visibility( $value ) {
		$allowed = array( self::VISIBILITY_PUBLIC, self::VISIBILITY_LOGGED_IN, self::VISIBILITY_ROLES );
		$value   = sanitize_key( $value );

		return in_array( $value, $allowed, true ) ? $value : self::VISIBILITY_LOGGED_IN;
	}

	/**
	 * Aktion bereinigen.
	 *
	 * @param string $value Rohwert.
	 * @return string
	 */
	public static function sanitize_action( $value ) {
		$allowed = array( 'inherit', 'message', 'login', '404', 'redirect' );
		$value   = sanitize_key( $value );

		return in_array( $value, $allowed, true ) ? $value : 'inherit';
	}

	/**
	 * Dreiwertige Option bereinigen.
	 *
	 * @param string $value Rohwert.
	 * @return string
	 */
	public static function sanitize_tristate( $value ) {
		$allowed = array( 'inherit', 'yes', 'no' );
		$value   = sanitize_key( $value );

		return in_array( $value, $allowed, true ) ? $value : 'inherit';
	}

	/**
	 * Wirksame Aktion unter Berücksichtigung der globalen Einstellungen.
	 *
	 * @return string
	 */
	public function effective_action() {
		if ( 'inherit' === $this->action ) {
			return LRM_Settings::get( 'denied_action' );
		}

		return $this->action;
	}

	/**
	 * Soll der Inhalt in Menüs und Listen verborgen werden?
	 *
	 * @return bool
	 */
	public function should_hide() {
		if ( 'inherit' === $this->hide ) {
			return (bool) LRM_Settings::get( 'hide_from_menus' );
		}

		return 'yes' === $this->hide;
	}

	/**
	 * Kurzbeschreibung der Regel für die Verwaltung.
	 *
	 * @return string
	 */
	public function describe() {
		if ( ! $this->enabled ) {
			return __( 'Keine Einschränkung', 'loheide-rights-management' );
		}

		switch ( $this->visibility ) {
			case self::VISIBILITY_PUBLIC:
				$text = __( 'Öffentlich', 'loheide-rights-management' );
				break;
			case self::VISIBILITY_ROLES:
				$text = empty( $this->allowed )
					? __( 'Nur ausgewählte Rollen (keine ausgewählt)', 'loheide-rights-management' )
					: sprintf(
						/* translators: %s: Liste der Rollennamen. */
						__( 'Nur: %s', 'loheide-rights-management' ),
						implode( ', ', LRM_Roles::labels( $this->allowed ) )
					);
				break;
			case self::VISIBILITY_LOGGED_IN:
			default:
				$text = __( 'Nur angemeldete Benutzer', 'loheide-rights-management' );
				break;
		}

		if ( ! empty( $this->denied ) ) {
			$text .= ' · ' . sprintf(
				/* translators: %s: Liste der Rollennamen. */
				__( 'gesperrt: %s', 'loheide-rights-management' ),
				implode( ', ', LRM_Roles::labels( $this->denied ) )
			);
		}

		return $text;
	}

	/**
	 * Rollen, die in beiden Listen stehen. Das Verbot gewinnt.
	 *
	 * @return array
	 */
	public function conflicts() {
		return array_values( array_intersect( $this->allowed, $this->denied ) );
	}
}
