<?php
/**
 * Shortcodes für Teilinhalte.
 *
 * @package LOHEIDE_Rights_Management
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stellt Shortcodes bereit, mit denen einzelne Abschnitte geschützt werden.
 */
class LRM_Shortcodes {

	/**
	 * Hooks registrieren.
	 */
	public function hooks() {
		add_shortcode( 'lrm_restrict', array( $this, 'restrict' ) );
		add_shortcode( 'lrm_guest', array( $this, 'guest' ) );
	}

	/**
	 * Inhalt nur für berechtigte Rollen ausgeben.
	 *
	 * Attribute:
	 *   roles     – Liste freigegebener Rollen (Komma getrennt).
	 *   deny      – Liste gesperrter Rollen. Hat immer Vorrang.
	 *   logged_in – "yes" beschränkt auf angemeldete Benutzer.
	 *   message   – Ersatztext, wenn kein Zugriff besteht.
	 *
	 * @param array  $atts    Attribute.
	 * @param string $content Inhalt.
	 * @return string
	 */
	public function restrict( $atts, $content = '' ) {
		$atts = shortcode_atts(
			array(
				'roles'     => '',
				'deny'      => '',
				'logged_in' => '',
				'message'   => '',
			),
			$atts,
			'lrm_restrict'
		);

		$allowed    = $this->parse_roles( $atts['roles'] );
		$denied     = $this->parse_roles( $atts['deny'] );
		$user_roles = LRM_Roles::user_roles();

		// 1. Sperren haben Vorrang.
		if ( $denied && array_intersect( $user_roles, $denied ) ) {
			return $this->fallback( $atts['message'] );
		}

		// 2. Anmeldung verlangt?
		if ( 'yes' === strtolower( (string) $atts['logged_in'] ) && ! is_user_logged_in() ) {
			return $this->fallback( $atts['message'] );
		}

		// 3. Rollenfreigabe.
		if ( $allowed && ! array_intersect( $user_roles, $allowed ) ) {
			return $this->fallback( $atts['message'] );
		}

		return do_shortcode( (string) $content );
	}

	/**
	 * Inhalt nur für nicht angemeldete Besucher ausgeben.
	 *
	 * @param array  $atts    Attribute.
	 * @param string $content Inhalt.
	 * @return string
	 */
	public function guest( $atts, $content = '' ) {
		unset( $atts );

		if ( is_user_logged_in() ) {
			return '';
		}

		return do_shortcode( (string) $content );
	}

	/**
	 * Rollenliste aus einem Attribut lesen.
	 *
	 * @param string $value Attributwert.
	 * @return array
	 */
	protected function parse_roles( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return array();
		}

		return LRM_Roles::sanitize_list( array_map( 'trim', explode( ',', $value ) ) );
	}

	/**
	 * Ersatzausgabe.
	 *
	 * @param string $message Nachricht.
	 * @return string
	 */
	protected function fallback( $message ) {
		$message = trim( (string) $message );

		if ( '' === $message ) {
			return '';
		}

		return '<p class="lrm-inline-notice">' . esc_html( $message ) . '</p>';
	}
}
