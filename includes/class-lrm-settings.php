<?php
/**
 * Globale Einstellungen.
 *
 * @package LOHEIDE_Rights_Management
 */

defined( 'ABSPATH' ) || exit;

/**
 * Verwaltet die Plugin-Optionen.
 */
class LRM_Settings {

	/**
	 * Options-Schlüssel.
	 */
	const OPTION = 'lrm_settings';

	/**
	 * Einstellungsgruppe.
	 */
	const GROUP = 'lrm_settings_group';

	/**
	 * Zwischenspeicher.
	 *
	 * @var array|null
	 */
	protected static $cache = null;

	/**
	 * Standardwerte.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'post_types'        => array( 'page' ),
			'denied_action'     => 'message',
			'denied_title'      => __( 'Kein Zugriff', 'loheide-rights-management' ),
			'denied_message'    => __( 'Dieser Bereich ist geschützt. Bitte melden Sie sich mit einem berechtigten Konto an.', 'loheide-rights-management' ),
			'redirect_url'      => '',
			'login_url'         => '',
			'hide_from_menus'   => 1,
			'hide_from_queries' => 0,
			'protect_rest'      => 1,
			'protect_feeds'     => 1,
			'close_comments'    => 1,
			'admin_bypass'      => 1,
			'inherit_default'   => 1,
			'show_toolbar'      => 1,
			'show_login_form'   => 1,
			'show_credit'       => 1,
			'metabox_context'   => 'side',
			'protect_uploads'   => 0,
			'uploads_mode'      => 'login',
			'uploads_whitelist' => '',
			'whitelist_branding' => 1,
			'uploads_denied_action' => 'block',
		);
	}

	/**
	 * Alle Einstellungen.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		self::$cache = wp_parse_args( $stored, self::defaults() );

		return self::$cache;
	}

	/**
	 * Einzelne Einstellung.
	 *
	 * @param string $key     Schlüssel.
	 * @param mixed  $default Rückfallwert.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();

		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}

		return $default;
	}

	/**
	 * Zwischenspeicher verwerfen.
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Standardwerte bei der Aktivierung speichern.
	 */
	public static function install() {
		$stored = get_option( self::OPTION, null );

		if ( null === $stored ) {
			add_option( self::OPTION, self::defaults() );
		}
	}

	/**
	 * Option registrieren.
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Eingaben bereinigen.
	 *
	 * @param mixed $input Rohdaten.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$clean    = $defaults;

		if ( ! is_array( $input ) ) {
			return $clean;
		}

		// Inhaltstypen.
		$types           = isset( $input['post_types'] ) ? (array) $input['post_types'] : array();
		$available       = array_keys( self::available_post_types() );
		$clean_types     = array();
		foreach ( $types as $type ) {
			$type = sanitize_key( $type );
			if ( in_array( $type, $available, true ) ) {
				$clean_types[] = $type;
			}
		}
		$clean['post_types'] = $clean_types;

		// Aktion.
		$action                 = isset( $input['denied_action'] ) ? sanitize_key( $input['denied_action'] ) : 'message';
		$clean['denied_action'] = in_array( $action, array( 'message', 'login', '404', 'redirect' ), true ) ? $action : 'message';

		// Dateischutz.
		$uploads_mode          = isset( $input['uploads_mode'] ) ? sanitize_key( $input['uploads_mode'] ) : 'login';
		$clean['uploads_mode'] = in_array( $uploads_mode, array( 'login', 'rules' ), true ) ? $uploads_mode : 'login';

		$uploads_action                 = isset( $input['uploads_denied_action'] ) ? sanitize_key( $input['uploads_denied_action'] ) : 'block';
		$clean['uploads_denied_action'] = in_array( $uploads_action, array( 'block', 'login' ), true ) ? $uploads_action : 'block';

		$clean['uploads_whitelist'] = isset( $input['uploads_whitelist'] )
			? implode( "\n", LRM_Media::parse_patterns( $input['uploads_whitelist'] ) )
			: '';

		// Position der Metabox im Editor.
		$context                  = isset( $input['metabox_context'] ) ? sanitize_key( $input['metabox_context'] ) : 'side';
		$clean['metabox_context'] = in_array( $context, array( 'side', 'normal' ), true ) ? $context : 'side';

		$clean['denied_title']   = isset( $input['denied_title'] ) ? sanitize_text_field( $input['denied_title'] ) : $defaults['denied_title'];
		$clean['denied_message'] = isset( $input['denied_message'] ) ? wp_kses_post( $input['denied_message'] ) : $defaults['denied_message'];
		$clean['redirect_url']   = isset( $input['redirect_url'] ) ? esc_url_raw( trim( $input['redirect_url'] ) ) : '';
		$clean['login_url']      = isset( $input['login_url'] ) ? esc_url_raw( trim( $input['login_url'] ) ) : '';

		foreach ( array( 'hide_from_menus', 'hide_from_queries', 'protect_rest', 'protect_feeds', 'close_comments', 'admin_bypass', 'inherit_default', 'show_toolbar', 'show_login_form', 'show_credit', 'protect_uploads', 'whitelist_branding' ) as $flag ) {
			$clean[ $flag ] = empty( $input[ $flag ] ) ? 0 : 1;
		}

		self::flush();

		return $clean;
	}

	/**
	 * Inhaltstypen, die geschützt werden können.
	 *
	 * @return array Slug => Anzeigename.
	 */
	public static function available_post_types() {
		$types  = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);
		$result = array();

		foreach ( $types as $type ) {
			if ( 'attachment' === $type->name ) {
				continue;
			}

			$result[ $type->name ] = $type->labels->name;
		}

		return $result;
	}

	/**
	 * Inhaltstypen, für die der Schutz aktiv ist.
	 *
	 * @return array
	 */
	public static function protected_post_types() {
		$types = (array) self::get( 'post_types' );

		/**
		 * Filtert die geschützten Inhaltstypen.
		 *
		 * @param array $types Slugs.
		 */
		return apply_filters( 'lrm_protected_post_types', array_values( array_filter( $types ) ) );
	}

	/**
	 * Login-URL inklusive Rücksprungziel.
	 *
	 * @param string $redirect_to Zieladresse nach dem Login.
	 * @return string
	 */
	public static function login_url( $redirect_to = '' ) {
		$custom = self::get( 'login_url' );

		if ( $custom ) {
			if ( $redirect_to ) {
				return add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $custom );
			}

			return $custom;
		}

		return wp_login_url( $redirect_to );
	}
}
