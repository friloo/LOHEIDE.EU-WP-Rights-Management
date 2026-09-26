<?php
/**
 * Durchsetzung der Backend-Rechte.
 *
 * @package LOHEIDE_Rights_Management
 */

defined( 'ABSPATH' ) || exit;

/**
 * Setzt die Regeln aus LRM_Backend im Verwaltungsbereich durch.
 *
 * Verborgene Menüpunkte allein sind kein Schutz: Jede Beschränkung wird
 * zusätzlich serverseitig geprüft – über map_meta_cap für einzelne Inhalte,
 * über gefilterte Listen und über eine Sperre beim direkten Aufruf.
 */
class LRM_Backend_Guard {

	/**
	 * Zwischenspeicher der Zähler.
	 *
	 * @var array
	 */
	protected $count_cache = array();

	/**
	 * Hooks registrieren.
	 */
	public function hooks() {
		// Rechte an einzelnen Inhalten – gelten in jedem Kontext.
		add_filter( 'map_meta_cap', array( $this, 'map_meta_cap' ), 9999, 4 );

		// Begriffe: Der Block-Editor lädt sie über die REST-Schnittstelle, wo
		// is_admin() nicht greift. Die Filter laufen deshalb immer und prüfen
		// den Kontext selbst.
		add_filter( 'get_terms_args', array( $this, 'filter_term_args' ), 9999, 2 );
		add_action( 'wp_after_insert_post', array( $this, 'enforce_terms' ), 99, 2 );
		add_action( 'save_post', array( $this, 'enforce_terms' ), 99, 2 );

		// Neuanlage über die REST-Schnittstelle, die das Backend-Menü umgeht.
		foreach ( array_keys( LRM_Backend::managed_post_types() ) as $slug ) {
			add_filter( "rest_pre_insert_{$slug}", array( $this, 'guard_rest_create' ), 10, 2 );
		}

		foreach ( get_taxonomies( array( 'show_in_rest' => true ) ) as $taxonomy ) {
			add_filter( "rest_{$taxonomy}_query", array( $this, 'filter_rest_terms' ), 9999, 2 );
		}

		if ( ! is_admin() ) {
			return;
		}

		// Listen im Verwaltungsbereich.
		add_action( 'pre_get_posts', array( $this, 'filter_admin_queries' ), 9999 );
		add_filter( 'ajax_query_attachments_args', array( $this, 'filter_attachment_query' ), 9999 );
		add_filter( 'wp_count_posts', array( $this, 'filter_counts' ), 10, 2 );

		// Menüpunkte, Dashboard und Direktaufrufe.
		add_action( 'admin_menu', array( $this, 'hide_menus' ), 9999 );
		add_action( 'admin_init', array( $this, 'block_admin_access' ), 0 );
		add_action( 'admin_init', array( $this, 'block_forbidden_screens' ), 1 );
		add_action( 'admin_bar_menu', array( $this, 'clean_admin_bar' ), 999 );
		add_action( 'wp_dashboard_setup', array( $this, 'handle_dashboard' ), 9999 );
	}

	/**
	 * Regel des aktuellen Benutzers.
	 *
	 * @return array|null
	 */
	protected function config() {
		return LRM_Backend::for_user();
	}

	/**
	 * Läuft die Anfrage in einem Kontext, in dem die Auswahl beschränkt wird?
	 *
	 * Das Frontend bleibt unberührt – dort gelten die Regeln zur Sichtbarkeit
	 * aus LRM_Access.
	 *
	 * @return bool
	 */
	protected function is_editing_context() {
		if ( is_admin() || wp_doing_ajax() ) {
			return true;
		}

		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/* ------------------------------------------------------------------ *
	 * Rechte an einzelnen Inhalten
	 * ------------------------------------------------------------------ */

	/**
	 * Bearbeitungsrechte auf die zugewiesenen Inhalte begrenzen.
	 *
	 * @param array  $caps    Erforderliche Fähigkeiten.
	 * @param string $cap     Geprüfte Fähigkeit.
	 * @param int    $user_id Benutzer-ID.
	 * @param array  $args    Zusätzliche Angaben, meist die Inhalts-ID.
	 * @return array
	 */
	public function map_meta_cap( $caps, $cap, $user_id, $args ) {
		$edit_caps   = array( 'edit_post', 'edit_page', 'publish_post' );
		$delete_caps = array( 'delete_post', 'delete_page' );

		if ( ! in_array( $cap, array_merge( $edit_caps, $delete_caps ), true ) || empty( $args[0] ) ) {
			return $caps;
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return $caps;
		}

		$config = LRM_Backend::for_user( $user );

		if ( null === $config ) {
			return $caps;
		}

		$post = get_post( $args[0] );

		if ( ! $post ) {
			return $caps;
		}

		if ( in_array( $cap, $delete_caps, true ) ) {
			return LRM_Backend::can_delete_post( $post->ID, $config, $user ) ? $caps : array( 'do_not_allow' );
		}

		return LRM_Backend::can_edit_post( $post->ID, $config, $user ) ? $caps : array( 'do_not_allow' );
	}

	/* ------------------------------------------------------------------ *
	 * Listen
	 * ------------------------------------------------------------------ */

	/**
	 * Listen im Verwaltungsbereich auf die zugewiesenen Inhalte begrenzen.
	 *
	 * @param WP_Query $query Abfrage.
	 */
	public function filter_admin_queries( $query ) {
		if ( ! $query instanceof WP_Query || ! $query->is_main_query() ) {
			return;
		}

		$config = $this->config();

		if ( null === $config ) {
			return;
		}

		$post_type = $query->get( 'post_type' );

		if ( is_array( $post_type ) ) {
			$post_type = reset( $post_type );
		}

		if ( ! $post_type ) {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

			if ( $screen && $screen->post_type ) {
				$post_type = $screen->post_type;
			}
		}

		if ( ! $post_type ) {
			return;
		}

		if ( 'attachment' === $post_type ) {
			if ( ! empty( $config['own_media_only'] ) ) {
				$query->set( 'author', get_current_user_id() );
			}

			return;
		}

		$type = LRM_Backend::type_config( $config, $post_type );

		switch ( $type['mode'] ) {
			case 'all':
				break;

			case 'selected':
				$allowed = LRM_Backend::allowed_ids( $config, $post_type );
				$query->set( 'post__in', $allowed ? $allowed : array( 0 ) );
				break;

			case 'terms':
				if ( empty( $type['terms'] ) ) {
					$query->set( 'post__in', array( 0 ) );
					break;
				}

				$query->set(
					'tax_query',
					array(
						array(
							'taxonomy' => $type['taxonomy'] ? $type['taxonomy'] : 'category',
							'field'    => 'term_id',
							'terms'    => array_map( 'absint', $type['terms'] ),
						),
					)
				);
				break;

			default:
				// Nicht zugewiesener Inhaltstyp: nichts anzeigen.
				$query->set( 'post__in', array( 0 ) );
				break;
		}

		if ( ! empty( $type['own_only'] ) && 'none' !== $type['mode'] ) {
			$query->set( 'author', get_current_user_id() );
		}
	}

	/**
	 * Medienfenster begrenzen.
	 *
	 * @param array $args Abfrageargumente.
	 * @return array
	 */
	public function filter_attachment_query( $args ) {
		$config = $this->config();

		if ( null === $config || empty( $config['own_media_only'] ) ) {
			return $args;
		}

		$args['author'] = get_current_user_id();

		return $args;
	}

	/**
	 * Zähler über den Listen an die sichtbaren Inhalte angleichen.
	 *
	 * Zählt direkt in der Datenbank, statt alle Inhalte zu laden.
	 *
	 * @param object $counts    Zähler.
	 * @param string $post_type Inhaltstyp.
	 * @return object
	 */
	public function filter_counts( $counts, $post_type ) {
		$config = $this->config();

		if ( null === $config || 'attachment' === $post_type ) {
			return $counts;
		}

		$type = LRM_Backend::type_config( $config, $post_type );

		if ( 'all' === $type['mode'] && empty( $type['own_only'] ) ) {
			return $counts;
		}

		$cache_key = $post_type . ':' . get_current_user_id();

		if ( isset( $this->count_cache[ $cache_key ] ) ) {
			return $this->count_cache[ $cache_key ];
		}

		global $wpdb;

		$where = $wpdb->prepare( 'p.post_type = %s', $post_type );
		$join  = '';

		switch ( $type['mode'] ) {
			case 'selected':
				$allowed = LRM_Backend::allowed_ids( $config, $post_type );

				if ( empty( $allowed ) ) {
					$where .= ' AND 1 = 0';
					break;
				}

				$where .= ' AND p.ID IN (' . implode( ',', array_map( 'absint', $allowed ) ) . ')';
				break;

			case 'terms':
				$tt_ids = $this->term_taxonomy_ids( $type );

				if ( empty( $tt_ids ) ) {
					$where .= ' AND 1 = 0';
					break;
				}

				$join   = " INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID";
				$where .= ' AND tr.term_taxonomy_id IN (' . implode( ',', array_map( 'absint', $tt_ids ) ) . ')';
				break;

			case 'all':
				break;

			default:
				$where .= ' AND 1 = 0';
				break;
		}

		if ( ! empty( $type['own_only'] ) ) {
			$where .= $wpdb->prepare( ' AND p.post_author = %d', get_current_user_id() );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Bestandteile sind oben einzeln aufbereitet.
		$rows = $wpdb->get_results(
			"SELECT p.post_status, COUNT( DISTINCT p.ID ) AS num FROM {$wpdb->posts} p {$join} WHERE {$where} GROUP BY p.post_status"
		);
		// phpcs:enable

		$fresh = array();

		foreach ( (array) $counts as $status => $value ) {
			$fresh[ $status ] = 0;
		}

		foreach ( (array) $rows as $row ) {
			$fresh[ $row->post_status ] = (int) $row->num;
		}

		$result                          = (object) $fresh;
		$this->count_cache[ $cache_key ] = $result;

		return $result;
	}

	/**
	 * term_taxonomy_ids der freigegebenen Begriffe.
	 *
	 * @param array $type Regel des Inhaltstyps.
	 * @return array
	 */
	protected function term_taxonomy_ids( $type ) {
		$taxonomy = $type['taxonomy'] ? $type['taxonomy'] : 'category';
		$ids      = array();

		foreach ( (array) $type['terms'] as $term_id ) {
			$term = get_term( (int) $term_id, $taxonomy );

			if ( $term && ! is_wp_error( $term ) ) {
				$ids[] = (int) $term->term_taxonomy_id;
			}
		}

		return $ids;
	}

	/* ------------------------------------------------------------------ *
	 * Begriffe
	 * ------------------------------------------------------------------ */

	/**
	 * Begriffsauswahl auf die freigegebenen Einträge begrenzen.
	 *
	 * @param array $args       Argumente.
	 * @param array $taxonomies Taxonomien.
	 * @return array
	 */
	public function filter_term_args( $args, $taxonomies ) {
		if ( ! $this->is_editing_context() ) {
			return $args;
		}

		$config = $this->config();

		if ( null === $config ) {
			return $args;
		}

		foreach ( (array) $taxonomies as $taxonomy ) {
			$allowed = $this->allowed_terms( $config, $taxonomy );

			if ( null === $allowed ) {
				continue;
			}

			return $this->limit_include( $args, $allowed );
		}

		return $args;
	}

	/**
	 * Begriffsabfragen der REST-Schnittstelle begrenzen.
	 *
	 * @param array           $args    Abfrageargumente.
	 * @param WP_REST_Request $request Anfrage.
	 * @return array
	 */
	public function filter_rest_terms( $args, $request = null ) {
		unset( $request );

		$config = $this->config();

		if ( null === $config ) {
			return $args;
		}

		$taxonomy = isset( $args['taxonomy'] ) ? $args['taxonomy'] : '';

		if ( is_array( $taxonomy ) ) {
			$taxonomy = reset( $taxonomy );
		}

		$allowed = $this->allowed_terms( $config, $taxonomy );

		if ( null === $allowed ) {
			return $args;
		}

		return $this->limit_include( $args, $allowed );
	}

	/**
	 * Freigegebene Begriffe einer Taxonomie.
	 *
	 * @param array  $config   Regel.
	 * @param string $taxonomy Taxonomie.
	 * @return array|null Null, wenn die Taxonomie nicht beschränkt ist.
	 */
	protected function allowed_terms( $config, $taxonomy ) {
		if ( ! $taxonomy ) {
			return null;
		}

		foreach ( (array) $config['types'] as $type ) {
			$type = wp_parse_args( (array) $type, LRM_Backend::type_defaults() );

			if ( 'terms' !== $type['mode'] || empty( $type['terms'] ) ) {
				continue;
			}

			$type_taxonomy = $type['taxonomy'] ? $type['taxonomy'] : 'category';

			if ( $type_taxonomy === $taxonomy ) {
				return array_map( 'absint', $type['terms'] );
			}
		}

		return null;
	}

	/**
	 * Auswahl auf die freigegebenen Einträge begrenzen.
	 *
	 * @param array $args    Argumente.
	 * @param array $allowed Freigegebene IDs.
	 * @return array
	 */
	protected function limit_include( $args, $allowed ) {
		if ( ! empty( $args['include'] ) ) {
			$args['include'] = array_values( array_intersect( array_map( 'absint', (array) $args['include'] ), $allowed ) );

			if ( empty( $args['include'] ) ) {
				$args['include'] = array( 0 );
			}

			return $args;
		}

		$args['include'] = $allowed;

		return $args;
	}

	/**
	 * Beim Speichern nur freigegebene Begriffe zulassen.
	 *
	 * @param int     $post_id Inhalts-ID.
	 * @param WP_Post $post    Inhalt.
	 */
	public function enforce_terms( $post_id, $post = null ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! $post instanceof WP_Post ) {
			$post = get_post( $post_id );
		}

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$config = $this->config();

		if ( null === $config ) {
			return;
		}

		$type = LRM_Backend::type_config( $config, $post->post_type );

		if ( 'terms' !== $type['mode'] || empty( $type['terms'] ) ) {
			return;
		}

		$taxonomy = $type['taxonomy'] ? $type['taxonomy'] : 'category';
		$allowed  = array_map( 'absint', $type['terms'] );
		$current  = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );

		if ( is_wp_error( $current ) ) {
			return;
		}

		$current = array_map( 'absint', $current );
		$kept    = array_values( array_intersect( $current, $allowed ) );

		// Ohne freigegebenen Begriff wird der erste zugewiesene gesetzt, sofern
		// die Zuordnung verlangt wird.
		if ( empty( $kept ) && ! empty( $type['force_term'] ) ) {
			$kept = array( (int) $allowed[0] );
		}

		if ( $kept !== $current ) {
			wp_set_object_terms( $post_id, $kept, $taxonomy );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Neuanlage
	 * ------------------------------------------------------------------ */

	/**
	 * Neue Inhalte über die REST-Schnittstelle abweisen.
	 *
	 * Im Backend fängt block_forbidden_screens den Aufruf ab; die Schnittstelle
	 * prüft dagegen nur die allgemeine Fähigkeit, die für das Bearbeiten der
	 * zugewiesenen Inhalte gebraucht wird.
	 *
	 * @param stdClass        $prepared Vorbereiteter Inhalt.
	 * @param WP_REST_Request $request  Anfrage.
	 * @return stdClass|WP_Error
	 */
	public function guard_rest_create( $prepared, $request ) {
		$config = $this->config();

		if ( null === $config ) {
			return $prepared;
		}

		// Eine vorhandene ID bedeutet Bearbeitung – dafür greift map_meta_cap.
		$id = $request instanceof WP_REST_Request ? $request->get_param( 'id' ) : 0;

		if ( $id ) {
			return $prepared;
		}

		$post_type = isset( $prepared->post_type ) ? $prepared->post_type : '';

		if ( ! $post_type && $request instanceof WP_REST_Request ) {
			$route = $request->get_route();

			foreach ( LRM_Backend::managed_post_types() as $slug => $label ) {
				$object = get_post_type_object( $slug );

				if ( $object && ! empty( $object->rest_base ) && false !== strpos( $route, '/' . $object->rest_base ) ) {
					$post_type = $slug;
					break;
				}
			}
		}

		if ( ! $post_type ) {
			return $prepared;
		}

		$type = LRM_Backend::type_config( $config, $post_type );

		if ( ! empty( $type['create'] ) ) {
			return $prepared;
		}

		$object = get_post_type_object( $post_type );
		$label  = $object ? $object->labels->singular_name : $post_type;

		return new WP_Error(
			'lrm_create_not_allowed',
			sprintf(
				/* translators: %s: Bezeichnung des Inhaltstyps. */
				__( 'Sie dürfen hier nichts Neues anlegen (%s).', 'loheide-rights-management' ),
				$label
			),
			array( 'status' => 403 )
		);
	}

	/* ------------------------------------------------------------------ *
	 * Menüpunkte und Dashboard
	 * ------------------------------------------------------------------ */

	/**
	 * Verborgene Menüpunkte entfernen.
	 */
	public function hide_menus() {
		$config = $this->config();

		if ( null === $config ) {
			return;
		}

		$hidden    = (array) $config['hidden_menus'];
		$protected = $this->protected_menu_keys( $config );

		// Menüs, die erst nach dem Speichern der Regel hinzugekommen sind.
		if ( ! empty( $config['hide_new_menus'] ) ) {
			$known = (array) $config['known_menus'];

			foreach ( self::collect_menus() as $menu ) {
				if ( ! in_array( $menu['key'], $known, true ) && ! in_array( $menu['key'], $protected, true ) ) {
					$hidden[] = $menu['key'];
					continue;
				}

				foreach ( $menu['children'] as $child ) {
					if ( ! in_array( $child['key'], $known, true ) && ! in_array( $child['key'], $protected, true ) ) {
						$hidden[] = $child['key'];
					}
				}
			}
		}

		// Was freigegeben ist, muss erreichbar bleiben: WordPress sperrt
		// Seiten, die in keinem Menü stehen.
		$hidden = array_diff( array_unique( $hidden ), $protected );

		foreach ( $hidden as $entry ) {
			if ( false !== strpos( $entry, '|' ) ) {
				list( $parent, $child ) = explode( '|', $entry, 2 );
				remove_submenu_page( $parent, $child );
				continue;
			}

			remove_menu_page( $entry );
		}

		$this->keep_menus_reachable();

		// Neue Inhalte anlegen, sofern nicht erlaubt, auch aus dem Menü nehmen.
		foreach ( LRM_Backend::managed_post_types() as $slug => $label ) {
			$type = LRM_Backend::type_config( $config, $slug );

			if ( ! empty( $type['create'] ) ) {
				continue;
			}

			if ( 'post' === $slug ) {
				remove_submenu_page( 'edit.php', 'post-new.php' );
				continue;
			}

			remove_submenu_page( 'edit.php?post_type=' . $slug, 'post-new.php?post_type=' . $slug );
		}
	}

	/**
	 * Sicherstellen, dass jedes sichtbare Menü erreichbar bleibt.
	 *
	 * WordPress weist den Aufruf einer Seite ab, die in keinem Menü steht. Ein
	 * Hauptmenü, dessen Unterpunkte alle entfernt wurden, wäre damit nur noch
	 * Zierde – und der Klick darauf führte zu einer Fehlermeldung.
	 */
	protected function keep_menus_reachable() {
		global $menu, $submenu;

		foreach ( (array) $menu as $item ) {
			if ( empty( $item[2] ) ) {
				continue;
			}

			$slug = $item[2];

			// Menüs ohne eigene Unterpunkte sind selbst die Zielseite.
			if ( ! isset( $submenu[ $slug ] ) ) {
				continue;
			}

			if ( ! empty( $submenu[ $slug ] ) ) {
				continue;
			}

			// Der Unterpunkt wurde vollständig geleert: Menü ebenfalls entfernen,
			// statt einen toten Eintrag stehen zu lassen.
			remove_menu_page( $slug );
		}
	}

	/**
	 * Menüschlüssel, die wegen freigegebener Inhalte sichtbar bleiben müssen.
	 *
	 * @param array $config Regel.
	 * @return array
	 */
	protected function protected_menu_keys( $config ) {
		// Das Dashboard bleibt erreichbar: WordPress leitet nach der Anmeldung
		// dorthin. Das eigene Profil lässt sich dagegen bewusst abschalten.
		$keys = array( 'index.php' );

		$keys[] = 'index.php|index.php';

		foreach ( (array) $config['types'] as $slug => $type ) {
			$type = wp_parse_args( (array) $type, LRM_Backend::type_defaults() );

			if ( 'none' === $type['mode'] ) {
				continue;
			}

			$base = ( 'post' === $slug ) ? 'edit.php' : 'edit.php?post_type=' . $slug;
			$new  = ( 'post' === $slug ) ? 'post-new.php' : 'post-new.php?post_type=' . $slug;

			$keys[] = $base;

			// Auch die Unterpunkte: Ein Menü ohne erreichbaren Unterpunkt wird
			// von WordPress gesperrt.
			$keys[] = $base . '|' . $base;

			if ( ! empty( $type['create'] ) ) {
				$keys[] = $base . '|' . $new;
			}

			$object = get_post_type_object( $slug );

			// Hängt der Inhaltstyp im Menü eines Plugins, bleibt auch dieses sichtbar.
			if ( $object && is_string( $object->show_in_menu ) && 'edit.php' !== $object->show_in_menu ) {
				$keys[] = $object->show_in_menu;
				$keys[] = $object->show_in_menu . '|' . $base;

				if ( ! empty( $type['create'] ) ) {
					$keys[] = $object->show_in_menu . '|' . $new;
				}
			}
		}

		if ( ! empty( $config['allow_media'] ) ) {
			$keys[] = 'upload.php';
			$keys[] = 'upload.php|upload.php';
			$keys[] = 'upload.php|media-new.php';
		}

		/**
		 * Filtert die Menüpunkte, die trotz Beschränkung sichtbar bleiben.
		 *
		 * @param array $keys   Menüschlüssel.
		 * @param array $config Regel.
		 */
		return apply_filters( 'lrm_backend_protected_menus', array_values( array_unique( $keys ) ), $config );
	}

	/**
	 * Dashboard-Bereiche merken und verbergen.
	 */
	public function handle_dashboard() {
		global $wp_meta_boxes;

		$found = array();

		if ( ! empty( $wp_meta_boxes['dashboard'] ) ) {
			foreach ( (array) $wp_meta_boxes['dashboard'] as $context ) {
				foreach ( (array) $context as $priority ) {
					foreach ( (array) $priority as $id => $box ) {
						if ( empty( $box['title'] ) ) {
							continue;
						}

						$found[ $id ] = trim( wp_strip_all_tags( $box['title'] ) );
					}
				}
			}
		}

		// Nur Benutzer ohne Beschränkung sehen alle Bereiche – ihre Sicht ist
		// die Grundlage für die Auswahl in der Verwaltung.
		$config = $this->config();

		if ( null === $config ) {
			LRM_Backend::remember_widgets( $found );

			return;
		}

		foreach ( (array) $config['hidden_widgets'] as $id ) {
			foreach ( array( 'normal', 'side', 'column3', 'column4' ) as $context ) {
				remove_meta_box( $id, 'dashboard', $context );
			}
		}
	}

	/**
	 * Rollen ohne Zugang zum Verwaltungsbereich zur Website zurückschicken.
	 *
	 * Betrifft nur Seitenaufrufe: admin-ajax.php und admin-post.php bleiben
	 * erreichbar, weil auch das Frontend sie benötigt.
	 */
	public function block_admin_access() {
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		global $pagenow;

		if ( in_array( $pagenow, array( 'admin-ajax.php', 'admin-post.php' ), true ) ) {
			return;
		}

		$config = $this->config();

		if ( null === $config || empty( $config['block_admin'] ) ) {
			return;
		}

		/**
		 * Zieladresse für abgewiesene Aufrufe des Verwaltungsbereichs.
		 *
		 * @param string $url Zieladresse.
		 */
		$target = apply_filters( 'lrm_backend_blocked_redirect', home_url( '/' ) );

		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Direktaufruf gesperrter Verwaltungsseiten abfangen.
	 */
	public function block_forbidden_screens() {
		if ( wp_doing_ajax() ) {
			return;
		}

		$config = $this->config();

		if ( null === $config ) {
			return;
		}

		global $pagenow;

		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Neue Inhalte anlegen.
		if ( 'post-new.php' === $pagenow ) {
			$type_slug = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$type      = LRM_Backend::type_config( $config, $type_slug );

			if ( empty( $type['create'] ) ) {
				$this->deny( __( 'Sie dürfen hier nichts Neues anlegen.', 'loheide-rights-management' ) );
			}
		}

		if ( 'upload.php' === $pagenow && empty( $config['allow_media'] ) ) {
			$this->deny( __( 'Sie haben keinen Zugriff auf die Mediathek.', 'loheide-rights-management' ) );
		}

		$protected = $this->protected_menu_keys( $config );
		$hidden    = array_diff( (array) $config['hidden_menus'], $protected );

		if ( ! empty( $config['hide_new_menus'] ) && $page ) {
			$known = (array) $config['known_menus'];
			$found = false;

			foreach ( $known as $entry ) {
				if ( $this->screen_matches( str_replace( '|', '', $entry ), $pagenow, $page ) ) {
					$found = true;
					break;
				}
			}

			if ( ! $found ) {
				$this->deny();
			}
		}

		foreach ( $hidden as $entry ) {
			if ( $this->screen_matches( str_replace( '|', '', $entry ), $pagenow, $page ) ) {
				$this->deny();
			}
		}
	}

	/**
	 * Passt der Menüeintrag auf die aufgerufene Seite?
	 *
	 * @param string $entry   Menüeintrag.
	 * @param string $pagenow Aufgerufene Datei.
	 * @param string $page    Wert von ?page=.
	 * @return bool
	 */
	protected function screen_matches( $entry, $pagenow, $page ) {
		if ( '' === $entry ) {
			return false;
		}

		if ( false !== strpos( $entry, 'page=' ) ) {
			$parts = wp_parse_url( $entry );
			parse_str( isset( $parts['query'] ) ? $parts['query'] : '', $query );

			return ! empty( $query['page'] ) && $query['page'] === $page;
		}

		if ( false !== strpos( $entry, '.php' ) ) {
			$file = strtok( $entry, '?' );

			if ( $file !== $pagenow ) {
				return false;
			}

			$parts = wp_parse_url( $entry );
			parse_str( isset( $parts['query'] ) ? $parts['query'] : '', $query );

			$requested_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( ! empty( $query['post_type'] ) ) {
				return $query['post_type'] === $requested_type;
			}

			// "edit.php" und "post-new.php" ohne Zusatz meinen die Beiträge.
			// Ohne diese Unterscheidung würde der Eintrag auch die Listen
			// eigener Inhaltstypen sperren.
			if ( in_array( $file, array( 'edit.php', 'post-new.php' ), true ) ) {
				return 'post' === $requested_type;
			}

			return true;
		}

		return $entry === $page;
	}

	/**
	 * Zugriff abweisen.
	 *
	 * @param string $message Meldung.
	 */
	protected function deny( $message = '' ) {
		if ( '' === $message ) {
			$message = __( 'Sie haben keinen Zugriff auf diesen Bereich.', 'loheide-rights-management' );
		}

		wp_die(
			esc_html( $message ),
			esc_html__( 'Kein Zugriff', 'loheide-rights-management' ),
			array(
				'response'  => 403,
				'back_link' => true,
			)
		);
	}

	/**
	 * Einträge aus der Werkzeugleiste nehmen, die ins Leere führen.
	 *
	 * @param WP_Admin_Bar $bar Werkzeugleiste.
	 */
	public function clean_admin_bar( $bar ) {
		$config = $this->config();

		if ( null === $config ) {
			return;
		}

		foreach ( LRM_Backend::managed_post_types() as $slug => $label ) {
			$type = LRM_Backend::type_config( $config, $slug );

			if ( empty( $type['create'] ) ) {
				$bar->remove_node( 'new-' . $slug );
			}
		}

		if ( empty( $config['allow_media'] ) ) {
			$bar->remove_node( 'new-media' );
		}

		$bar->remove_node( 'comments' );
	}

	/* ------------------------------------------------------------------ *
	 * Hilfen für die Verwaltung
	 * ------------------------------------------------------------------ */

	/**
	 * Alle Menüpunkte des Verwaltungsbereichs auslesen.
	 *
	 * @return array Liste mit Schlüssel, Beschriftung und Untereinträgen.
	 */
	public static function collect_menus() {
		global $menu, $submenu;

		$result = array();

		foreach ( (array) $menu as $item ) {
			if ( empty( $item[0] ) || empty( $item[2] ) ) {
				continue;
			}

			$label = trim( wp_strip_all_tags( preg_replace( '#<span[^>]*>.*?</span>#s', '', $item[0] ) ) );

			if ( '' === $label ) {
				continue;
			}

			$slug     = $item[2];
			$children = array();

			if ( ! empty( $submenu[ $slug ] ) ) {
				foreach ( $submenu[ $slug ] as $child ) {
					if ( empty( $child[0] ) || empty( $child[2] ) ) {
						continue;
					}

					$child_label = trim( wp_strip_all_tags( preg_replace( '#<span[^>]*>.*?</span>#s', '', $child[0] ) ) );

					if ( '' === $child_label ) {
						continue;
					}

					$children[] = array(
						'key'   => $slug . '|' . $child[2],
						'label' => $child_label,
					);
				}
			}

			$result[] = array(
				'key'      => $slug,
				'label'    => $label,
				'children' => $children,
			);
		}

		return $result;
	}
}
