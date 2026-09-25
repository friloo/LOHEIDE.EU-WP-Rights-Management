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
 * zusätzlich serverseitig geprüft – über map_meta_cap für einzelne Inhalte und
 * über eine Sperre beim direkten Aufruf einer Verwaltungsseite.
 */
class LRM_Backend_Guard {

	/**
	 * Hooks registrieren.
	 */
	public function hooks() {
		// Rechte an einzelnen Inhalten – gelten in jedem Kontext.
		add_filter( 'map_meta_cap', array( $this, 'map_meta_cap' ), 10, 4 );

		// Kategorien: Der Block-Editor lädt sie über die REST-Schnittstelle, wo
		// is_admin() nicht greift. Beide Filter laufen deshalb immer und prüfen
		// den Kontext selbst.
		add_filter( 'get_terms_args', array( $this, 'filter_term_args' ), 10, 2 );
		add_filter( 'rest_category_query', array( $this, 'filter_rest_terms' ), 10, 2 );

		// Über die REST-Schnittstelle werden die Kategorien erst nach save_post
		// geschrieben. wp_after_insert_post läuft danach und fasst beide Wege.
		add_action( 'wp_after_insert_post', array( $this, 'enforce_categories' ), 99, 2 );
		add_action( 'save_post', array( $this, 'enforce_categories' ), 99, 2 );

		// Neuanlage über die REST-Schnittstelle, die das Backend-Menü umgeht.
		add_filter( 'rest_pre_insert_page', array( $this, 'guard_rest_create_page' ), 10, 2 );
		add_filter( 'rest_pre_insert_post', array( $this, 'guard_rest_create_post' ), 10, 2 );

		if ( ! is_admin() ) {
			return;
		}

		// Listen im Verwaltungsbereich.
		add_action( 'pre_get_posts', array( $this, 'filter_admin_queries' ) );
		add_filter( 'ajax_query_attachments_args', array( $this, 'filter_attachment_query' ) );
		add_filter( 'wp_count_posts', array( $this, 'filter_counts' ), 10, 2 );

		// Menüpunkte und Direktaufrufe.
		add_action( 'admin_menu', array( $this, 'hide_menus' ), 9999 );
		add_action( 'admin_init', array( $this, 'block_forbidden_screens' ), 1 );
		add_action( 'admin_bar_menu', array( $this, 'clean_admin_bar' ), 999 );
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

	/**
	 * Regel des aktuellen Benutzers.
	 *
	 * @return array|null
	 */
	protected function config() {
		return LRM_Backend::for_user();
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
		$post_caps = array( 'edit_post', 'edit_page', 'delete_post', 'delete_page', 'publish_post' );

		if ( ! in_array( $cap, $post_caps, true ) || empty( $args[0] ) ) {
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

		// Inhaltstypen ohne Zuweisung bleiben unberührt, sofern die Regel sie
		// nicht betrifft. Seiten und Beiträge werden immer geprüft.
		if ( ! in_array( $post->post_type, array( 'page', 'post', 'attachment' ), true ) ) {
			return $caps;
		}

		if ( LRM_Backend::can_edit_post( $post->ID, $config, $user ) ) {
			return $caps;
		}

		return array( 'do_not_allow' );
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

		switch ( $post_type ) {
			case 'page':
				$allowed = LRM_Backend::allowed_page_ids( $config );
				// Ein leeres Ergebnis erzwingen, wenn nichts zugewiesen ist.
				$query->set( 'post__in', $allowed ? $allowed : array( 0 ) );
				break;

			case 'post':
				if ( empty( $config['categories'] ) ) {
					$query->set( 'post__in', array( 0 ) );
					break;
				}

				$query->set( 'category__in', array_map( 'absint', $config['categories'] ) );

				if ( ! empty( $config['own_posts_only'] ) ) {
					$query->set( 'author', get_current_user_id() );
				}
				break;

			case 'attachment':
				if ( ! empty( $config['own_media_only'] ) ) {
					$query->set( 'author', get_current_user_id() );
				}
				break;
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
	 * @param object $counts    Zähler.
	 * @param string $post_type Inhaltstyp.
	 * @return object
	 */
	public function filter_counts( $counts, $post_type ) {
		$config = $this->config();

		if ( null === $config || ! in_array( $post_type, array( 'page', 'post' ), true ) ) {
			return $counts;
		}

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'lrm_skip'       => true,
		);

		if ( 'page' === $post_type ) {
			$allowed             = LRM_Backend::allowed_page_ids( $config );
			$args['post__in']    = $allowed ? $allowed : array( 0 );
		} else {
			if ( empty( $config['categories'] ) ) {
				$args['post__in'] = array( 0 );
			} else {
				$args['category__in'] = array_map( 'absint', $config['categories'] );
			}

			if ( ! empty( $config['own_posts_only'] ) ) {
				$args['author'] = get_current_user_id();
			}
		}

		remove_action( 'pre_get_posts', array( $this, 'filter_admin_queries' ) );
		$query = new WP_Query( $args );
		add_action( 'pre_get_posts', array( $this, 'filter_admin_queries' ) );

		$fresh = array();

		foreach ( (array) $counts as $status => $value ) {
			$fresh[ $status ] = 0;
		}

		foreach ( $query->posts as $post_id ) {
			$status = get_post_status( $post_id );

			if ( isset( $fresh[ $status ] ) ) {
				$fresh[ $status ]++;
			}
		}

		return (object) $fresh;
	}

	/* ------------------------------------------------------------------ *
	 * Kategorien
	 * ------------------------------------------------------------------ */

	/**
	 * Kategorieauswahl auf die freigegebenen Begriffe begrenzen.
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

		if ( null === $config || empty( $config['categories'] ) ) {
			return $args;
		}

		if ( ! in_array( 'category', (array) $taxonomies, true ) ) {
			return $args;
		}

		$allowed = array_map( 'absint', $config['categories'] );

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
	 * Kategorieabfragen der REST-Schnittstelle begrenzen.
	 *
	 * Betrifft vor allem die Auswahl im Block-Editor.
	 *
	 * @param array           $args    Abfrageargumente.
	 * @param WP_REST_Request $request Anfrage.
	 * @return array
	 */
	public function filter_rest_terms( $args, $request = null ) {
		unset( $request );

		$config = $this->config();

		if ( null === $config || empty( $config['categories'] ) ) {
			return $args;
		}

		$allowed = array_map( 'absint', $config['categories'] );

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
	 * Beim Speichern nur freigegebene Kategorien zulassen.
	 *
	 * @param int     $post_id Inhalts-ID.
	 * @param WP_Post $post    Inhalt.
	 */
	public function enforce_categories( $post_id, $post = null ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! $post instanceof WP_Post ) {
			$post = get_post( $post_id );
		}

		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
			return;
		}

		$config = $this->config();

		if ( null === $config || empty( $config['categories'] ) ) {
			return;
		}

		$allowed = array_map( 'absint', $config['categories'] );
		$current = array_map( 'absint', wp_get_post_categories( $post_id ) );
		$kept    = array_values( array_intersect( $current, $allowed ) );

		// Ohne freigegebene Kategorie wird die erste zugewiesene gesetzt.
		if ( empty( $kept ) ) {
			$kept = array( (int) $allowed[0] );
		}

		if ( $kept !== $current ) {
			wp_set_post_categories( $post_id, $kept );
		}
	}

	/**
	 * Neue Seiten über die REST-Schnittstelle abweisen.
	 *
	 * Im Backend fängt block_forbidden_screens den Aufruf ab; die Schnittstelle
	 * prüft dagegen nur die allgemeine Fähigkeit, die für das Bearbeiten der
	 * zugewiesenen Seiten gebraucht wird.
	 *
	 * @param stdClass        $prepared Vorbereiteter Inhalt.
	 * @param WP_REST_Request $request  Anfrage.
	 * @return stdClass|WP_Error
	 */
	public function guard_rest_create_page( $prepared, $request ) {
		return $this->guard_rest_create( $prepared, $request, 'create_pages', __( 'Sie dürfen keine neuen Seiten anlegen.', 'loheide-rights-management' ) );
	}

	/**
	 * Neue Beiträge über die REST-Schnittstelle abweisen.
	 *
	 * @param stdClass        $prepared Vorbereiteter Inhalt.
	 * @param WP_REST_Request $request  Anfrage.
	 * @return stdClass|WP_Error
	 */
	public function guard_rest_create_post( $prepared, $request ) {
		return $this->guard_rest_create( $prepared, $request, 'create_posts', __( 'Sie dürfen keine neuen Beiträge anlegen.', 'loheide-rights-management' ) );
	}

	/**
	 * Gemeinsame Prüfung für die Neuanlage über die REST-Schnittstelle.
	 *
	 * @param stdClass        $prepared Vorbereiteter Inhalt.
	 * @param WP_REST_Request $request  Anfrage.
	 * @param string          $flag     Schlüssel der Erlaubnis.
	 * @param string          $message  Meldung.
	 * @return stdClass|WP_Error
	 */
	protected function guard_rest_create( $prepared, $request, $flag, $message ) {
		$config = $this->config();

		if ( null === $config || ! empty( $config[ $flag ] ) ) {
			return $prepared;
		}

		// Eine vorhandene ID bedeutet Bearbeitung – dafür greift map_meta_cap.
		$id = $request instanceof WP_REST_Request ? $request->get_param( 'id' ) : 0;

		if ( $id ) {
			return $prepared;
		}

		return new WP_Error( 'lrm_create_not_allowed', $message, array( 'status' => 403 ) );
	}

	/* ------------------------------------------------------------------ *
	 * Menüpunkte
	 * ------------------------------------------------------------------ */

	/**
	 * Verborgene Menüpunkte entfernen.
	 */
	public function hide_menus() {
		$config = $this->config();

		if ( null === $config ) {
			return;
		}

		foreach ( (array) $config['hidden_menus'] as $entry ) {
			if ( false !== strpos( $entry, '|' ) ) {
				list( $parent, $child ) = explode( '|', $entry, 2 );
				remove_submenu_page( $parent, $child );
				continue;
			}

			remove_menu_page( $entry );
		}

		// Neue Inhalte anlegen, sofern nicht erlaubt, auch aus dem Menü nehmen.
		if ( empty( $config['create_pages'] ) ) {
			remove_submenu_page( 'edit.php?post_type=page', 'post-new.php?post_type=page' );
		}

		if ( empty( $config['create_posts'] ) ) {
			remove_submenu_page( 'edit.php', 'post-new.php' );
		}
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

		$page    = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current = $page ? $pagenow . '?page=' . $page : (string) $pagenow;

		// Neue Inhalte anlegen.
		if ( 'post-new.php' === $pagenow ) {
			$type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( 'page' === $type && empty( $config['create_pages'] ) ) {
				$this->deny( __( 'Sie dürfen keine neuen Seiten anlegen.', 'loheide-rights-management' ) );
			}

			if ( 'post' === $type && empty( $config['create_posts'] ) ) {
				$this->deny( __( 'Sie dürfen keine neuen Beiträge anlegen.', 'loheide-rights-management' ) );
			}
		}

		if ( 'upload.php' === $pagenow && empty( $config['allow_media'] ) ) {
			$this->deny( __( 'Sie haben keinen Zugriff auf die Mediathek.', 'loheide-rights-management' ) );
		}

		foreach ( (array) $config['hidden_menus'] as $entry ) {
			$entry = str_replace( '|', '', $entry );

			if ( $this->screen_matches( $entry, $pagenow, $page ) ) {
				$this->deny();
			}
		}

		unset( $current );
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

		// Eintrag der Form "admin.php?page=slug" oder nur "slug".
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

			// Bei "edit.php?post_type=x" muss auch der Typ übereinstimmen.
			$parts = wp_parse_url( $entry );
			parse_str( isset( $parts['query'] ) ? $parts['query'] : '', $query );

			if ( ! empty( $query['post_type'] ) ) {
				$type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

				return $query['post_type'] === $type;
			}

			return true;
		}

		// Reiner Slug einer Plugin-Seite.
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

		if ( empty( $config['create_posts'] ) ) {
			$bar->remove_node( 'new-post' );
		}

		if ( empty( $config['create_pages'] ) ) {
			$bar->remove_node( 'new-page' );
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
	 * Wird auf der Einstellungsseite gebraucht, um die Auswahl anzubieten.
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

			$label = wp_strip_all_tags( preg_replace( '#<span[^>]*>.*?</span>#s', '', $item[0] ) );
			$label = trim( $label );

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
