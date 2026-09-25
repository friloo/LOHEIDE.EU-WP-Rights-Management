<?php
/**
 * Plugin-Bootstrap.
 *
 * @package LOHEIDE_Rights_Management
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bindet alle Bestandteile ein.
 */
class LRM_Plugin {

	/**
	 * Instanz.
	 *
	 * @var LRM_Plugin|null
	 */
	protected static $instance = null;

	/**
	 * Verwaltung.
	 *
	 * @var LRM_Admin
	 */
	public $admin;

	/**
	 * Editorfeld.
	 *
	 * @var LRM_Metabox
	 */
	public $metabox;

	/**
	 * Frontend.
	 *
	 * @var LRM_Frontend
	 */
	public $frontend;

	/**
	 * Shortcodes.
	 *
	 * @var LRM_Shortcodes
	 */
	public $shortcodes;

	/**
	 * Dateischutz.
	 *
	 * @var LRM_Media
	 */
	public $media;

	/**
	 * Durchsetzung der Backend-Rechte.
	 *
	 * @var LRM_Backend_Guard
	 */
	public $backend;

	/**
	 * Verwaltung der Backend-Rechte.
	 *
	 * @var LRM_Backend_Admin
	 */
	public $backend_admin;

	/**
	 * Singleton.
	 *
	 * @return LRM_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Konstruktor.
	 */
	protected function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'boot' ) );

		// Zwischenspeicher leeren, wenn Inhalte oder Einstellungen sich ändern.
		add_action( 'save_post', array( 'LRM_Access', 'flush' ) );
		add_action( 'deleted_post', array( 'LRM_Access', 'flush' ) );
		add_action( 'update_option_' . LRM_Settings::OPTION, array( 'LRM_Settings', 'flush' ) );
		add_action( 'set_current_user', array( 'LRM_Access', 'flush' ) );
	}

	/**
	 * Übersetzungen laden.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'loheide-rights-management', false, dirname( LRM_BASENAME ) . '/languages' );
	}

	/**
	 * Bestandteile starten.
	 */
	public function boot() {
		$this->metabox    = new LRM_Metabox();
		$this->frontend   = new LRM_Frontend();
		$this->shortcodes = new LRM_Shortcodes();
		$this->media      = new LRM_Media();

		$this->metabox->hooks();
		$this->frontend->hooks();
		$this->shortcodes->hooks();
		$this->media->hooks();

		$this->backend = new LRM_Backend_Guard();
		$this->backend->hooks();

		if ( is_admin() ) {
			$this->admin = new LRM_Admin();
			$this->admin->hooks();

			$this->backend_admin = new LRM_Backend_Admin();
			$this->backend_admin->hooks();
		}

		/**
		 * Das Plugin ist vollständig geladen.
		 *
		 * @param LRM_Plugin $plugin Plugin-Instanz.
		 */
		do_action( 'lrm_loaded', $this );
	}

	/**
	 * Aktivierung: Rechte und Standardeinstellungen anlegen.
	 */
	public static function activate() {
		LRM_Settings::install();
		self::add_capabilities();

		if ( LRM_Settings::get( 'protect_uploads' ) ) {
			LRM_Media::write_htaccess();
		}

		flush_rewrite_rules();
	}

	/**
	 * Deaktivierung.
	 */
	public static function deactivate() {
		// Ohne aktives Plugin würde die Regel ins Leere greifen.
		LRM_Media::remove_htaccess();

		// Wichtig: Für die Backend-Rechte erhalten Rollen Fähigkeiten wie
		// edit_others_pages, die erst das Plugin auf die zugewiesenen Inhalte
		// begrenzt. Ohne aktives Plugin dürften sie sonst alles bearbeiten.
		LRM_Backend::revoke_all_capabilities();

		flush_rewrite_rules();
	}

	/**
	 * Fähigkeiten an die Administratorrolle vergeben.
	 */
	public static function add_capabilities() {
		$role = get_role( 'administrator' );

		if ( $role ) {
			$role->add_cap( LRM_Roles::CAP_MANAGE );
			$role->add_cap( LRM_Roles::CAP_BYPASS );
		}
	}
}
