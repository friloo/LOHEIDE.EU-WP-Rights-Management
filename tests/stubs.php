<?php
/**
 * Minimale WordPress-Attrappen für den Logiktest.
 *
 * Genügt für LRM_Roles, LRM_Rule, LRM_Settings und LRM_Access::evaluate().
 *
 * @package LOHEIDE_Rights_Management
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['lrm_test_meta']    = array();
$GLOBALS['lrm_test_posts']   = array();
$GLOBALS['lrm_test_options'] = array();

/**
 * Inhalt für den Test anlegen.
 *
 * @param int    $id        ID.
 * @param int    $parent    Übergeordnete ID.
 * @param string $post_type Inhaltstyp.
 */
function lrm_test_add_post( $id, $parent = 0, $post_type = 'page' ) {
	$GLOBALS['lrm_test_posts'][ $id ] = (object) array(
		'ID'          => $id,
		'post_parent' => $parent,
		'post_type'   => $post_type,
		'post_status' => 'publish',
	);
}

/**
 * Regel für den Test setzen.
 *
 * @param int   $id   Inhalts-ID.
 * @param array $meta Meta-Werte.
 */
function lrm_test_set_rule( $id, $meta ) {
	$GLOBALS['lrm_test_meta'][ $id ] = $meta;
}

// -------------------------------------------------------------- Attrappen.

function get_post_meta( $post_id, $key = '', $single = false ) {
	$meta = isset( $GLOBALS['lrm_test_meta'][ $post_id ] ) ? $GLOBALS['lrm_test_meta'][ $post_id ] : array();

	if ( ! array_key_exists( $key, $meta ) ) {
		return $single ? '' : array();
	}

	return $single ? $meta[ $key ] : array( $meta[ $key ] );
}

function get_post( $post = null ) {
	if ( is_object( $post ) ) {
		return $post;
	}

	$id = (int) $post;

	return isset( $GLOBALS['lrm_test_posts'][ $id ] ) ? $GLOBALS['lrm_test_posts'][ $id ] : null;
}

function get_post_ancestors( $post_id ) {
	$ancestors = array();
	$post      = get_post( $post_id );

	while ( $post && $post->post_parent ) {
		$ancestors[] = (int) $post->post_parent;
		$post        = get_post( $post->post_parent );
	}

	return $ancestors;
}

function is_post_type_hierarchical( $post_type ) {
	return 'page' === $post_type;
}

function get_option( $name, $default = false ) {
	return isset( $GLOBALS['lrm_test_options'][ $name ] ) ? $GLOBALS['lrm_test_options'][ $name ] : $default;
}

function add_option( $name, $value ) {
	$GLOBALS['lrm_test_options'][ $name ] = $value;

	return true;
}

function apply_filters( $tag, $value ) {
	return $value;
}

function sanitize_key( $key ) {
	return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) );
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function wp_kses_post( $value ) {
	return (string) $value;
}

function esc_url_raw( $value ) {
	return (string) $value;
}

function translate_user_role( $name ) {
	return $name;
}

function __( $text, $domain = null ) {
	return $text;
}

function _x( $text, $context, $domain = null ) {
	return $text;
}

function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, (array) $args );
}

function get_post_types( $args = array(), $output = 'names' ) {
	return array(
		'page' => (object) array(
			'name'   => 'page',
			'labels' => (object) array( 'name' => 'Seiten' ),
		),
		'post' => (object) array(
			'name'   => 'post',
			'labels' => (object) array( 'name' => 'Beiträge' ),
		),
	);
}

/**
 * Rollenverzeichnis.
 */
class LRM_Test_WP_Roles {

	/**
	 * Rollennamen.
	 *
	 * @return array
	 */
	public function get_names() {
		return array(
			'administrator' => 'Administrator',
			'editor'        => 'Redakteur',
			'author'        => 'Autor',
			'contributor'   => 'Mitarbeiter',
			'subscriber'    => 'Abonnent',
			'kunde'         => 'Kunde',
		);
	}
}

function wp_roles() {
	static $roles = null;

	if ( null === $roles ) {
		$roles = new LRM_Test_WP_Roles();
	}

	return $roles;
}
