<?php
/**
 * Durchsetzung der Rechte im Frontend.
 *
 * @package LOHEIDE_Rights_Management
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sperrt Inhalte, filtert Menüs, Listen, Feeds und die REST-API.
 */
class LRM_Frontend {

	/**
	 * Merker, ob der Hinweis bereits Styles benötigt.
	 *
	 * @var bool
	 */
	protected $needs_styles = false;

	/**
	 * Hooks registrieren.
	 */
	public function hooks() {
		add_action( 'template_redirect', array( $this, 'guard_singular' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );

		add_filter( 'the_content', array( $this, 'filter_content' ), 5 );
		add_filter( 'get_the_excerpt', array( $this, 'filter_excerpt' ), 5, 2 );

		add_filter( 'wp_nav_menu_objects', array( $this, 'filter_menu_objects' ), 10, 2 );
		add_filter( 'get_pages', array( $this, 'filter_pages' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'filter_queries' ) );

		add_filter( 'comments_open', array( $this, 'filter_comments_open' ), 10, 2 );
		add_filter( 'the_content_feed', array( $this, 'filter_feed_content' ), 10, 2 );
		add_filter( 'the_excerpt_rss', array( $this, 'filter_feed_excerpt' ) );

		add_action( 'rest_api_init', array( $this, 'register_rest_filters' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 90 );
	}

	/**
	 * Zugriff auf Einzelansichten prüfen.
	 */
	public function guard_singular() {
		if ( ! is_singular() || is_embed() ) {
			return;
		}

		$post = get_queried_object();

		if ( ! $post instanceof WP_Post || ! LRM_Access::is_supported( $post ) ) {
			return;
		}

		$check = LRM_Access::check( $post->ID );

		if ( ! empty( $check['allowed'] ) ) {
			return;
		}

		$rule   = $check['rule'];
		$action = $rule->effective_action();

		/**
		 * Ermöglicht eigene Reaktionen auf einen verweigerten Zugriff.
		 *
		 * @param string $action Aktion.
		 * @param array  $check  Prüfergebnis.
		 * @param WP_Post $post  Inhalt.
		 */
		$action = apply_filters( 'lrm_denied_action', $action, $check, $post );

		nocache_headers();

		switch ( $action ) {
			case 'login':
				// Angemeldete Benutzer würde die Anmeldeseite direkt zurückschicken –
				// das ergäbe eine Weiterleitungsschleife. Sie erhalten den Hinweis.
				if ( ! is_user_logged_in() ) {
					wp_safe_redirect( LRM_Settings::login_url( get_permalink( $post->ID ) ), 302 );
					exit;
				}

				status_header( 403 );
				break;

			case 'redirect':
				$target = $rule->redirect_url ? $rule->redirect_url : LRM_Settings::get( 'redirect_url' );

				// Ein Ziel, das auf den gesperrten Inhalt selbst zeigt, würde eine
				// Weiterleitungsschleife erzeugen.
				$is_self = $target && untrailingslashit( $target ) === untrailingslashit( (string) get_permalink( $post->ID ) );

				if ( $target && ! $is_self ) {
					// Das Ziel stammt aus einer Einstellung der Verwaltung und darf
					// daher auch auf eine andere Domain zeigen.
					wp_redirect( $target, 302 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
					exit;
				}

				// Ohne brauchbares Ziel wird der Hinweis angezeigt.
				status_header( 403 );
				break;

			case '404':
				global $wp_query;

				$wp_query->set_404();
				status_header( 404 );

				$template = get_query_template( '404' );

				if ( $template ) {
					include $template;
					exit;
				}

				break;

			case 'message':
			default:
				status_header( 403 );
				break;
		}
	}

	/**
	 * Styles laden, wenn ein Hinweis erscheint.
	 */
	public function assets() {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();

		if ( ! $post instanceof WP_Post || ! LRM_Access::is_supported( $post ) ) {
			return;
		}

		if ( ! LRM_Access::can_view( $post->ID ) ) {
			$this->enqueue_styles();
		}
	}

	/**
	 * Frontend-Styles einbinden.
	 */
	public function enqueue_styles() {
		if ( wp_style_is( 'lrm-frontend', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_style( 'lrm-frontend', LRM_URL . 'assets/css/frontend.css', array(), LRM_VERSION );
	}

	/**
	 * Inhalt durch Hinweis ersetzen.
	 *
	 * @param string $content Inhalt.
	 * @return string
	 */
	public function filter_content( $content ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $content;
		}

		$post = get_post();

		if ( ! $post instanceof WP_Post || ! LRM_Access::is_supported( $post ) ) {
			return $content;
		}

		$check = LRM_Access::check( $post->ID );

		if ( ! empty( $check['allowed'] ) ) {
			return $content;
		}

		$this->enqueue_styles();

		return $this->render_notice( $check, $post );
	}

	/**
	 * Auszug ersetzen.
	 *
	 * @param string       $excerpt Auszug.
	 * @param WP_Post|null $post    Inhalt.
	 * @return string
	 */
	public function filter_excerpt( $excerpt, $post = null ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $excerpt;
		}

		$post = get_post( $post );

		if ( ! $post instanceof WP_Post || ! LRM_Access::is_supported( $post ) ) {
			return $excerpt;
		}

		if ( LRM_Access::can_view( $post->ID ) ) {
			return $excerpt;
		}

		return esc_html( $this->notice_title( LRM_Access::get_effective_rule( $post->ID ) ) );
	}

	/**
	 * Feed-Inhalt ersetzen.
	 *
	 * @param string $content Inhalt.
	 * @return string
	 */
	public function filter_feed_content( $content ) {
		if ( ! LRM_Settings::get( 'protect_feeds' ) ) {
			return $content;
		}

		$post = get_post();

		if ( ! $post instanceof WP_Post || ! LRM_Access::is_supported( $post ) ) {
			return $content;
		}

		if ( LRM_Access::can_view( $post->ID ) ) {
			return $content;
		}

		return wp_kses_post( $this->notice_message( LRM_Access::get_effective_rule( $post->ID ) ) );
	}

	/**
	 * Feed-Auszug ersetzen.
	 *
	 * @param string $excerpt Auszug.
	 * @return string
	 */
	public function filter_feed_excerpt( $excerpt ) {
		return wp_strip_all_tags( $this->filter_feed_content( $excerpt ) );
	}

	/**
	 * Menüeinträge entfernen, auf die kein Zugriff besteht.
	 *
	 * @param array  $items Menüeinträge.
	 * @param object $args  Menüargumente.
	 * @return array
	 */
	public function filter_menu_objects( $items, $args = null ) {
		unset( $args );

		if ( is_admin() ) {
			return $items;
		}

		$removed = array();

		foreach ( $items as $key => $item ) {
			$is_post_type = isset( $item->type ) && 'post_type' === $item->type;
			$object_id    = isset( $item->object_id ) ? (int) $item->object_id : 0;

			$drop = false;

			if ( $is_post_type && $object_id && LRM_Access::is_supported( $object_id ) && ! LRM_Access::can_view( $object_id ) ) {
				$rule = LRM_Access::get_effective_rule( $object_id );
				$drop = $rule->should_hide();
			}

			// Kinder entfernter Einträge ebenfalls entfernen.
			if ( ! $drop && isset( $item->menu_item_parent ) && in_array( (int) $item->menu_item_parent, $removed, true ) ) {
				$drop = true;
			}

			if ( $drop ) {
				$removed[] = (int) $item->ID;
				unset( $items[ $key ] );
			}
		}

		return array_values( $items );
	}

	/**
	 * Seitenlisten filtern (wp_list_pages, Seiten-Widgets).
	 *
	 * @param array $pages Seiten.
	 * @param array $args  Argumente.
	 * @return array
	 */
	public function filter_pages( $pages, $args = array() ) {
		unset( $args );

		if ( is_admin() || empty( $pages ) || LRM_Access::is_computing() ) {
			return $pages;
		}

		foreach ( $pages as $key => $page ) {
			$post_id = isset( $page->ID ) ? (int) $page->ID : 0;

			if ( ! $post_id || ! LRM_Access::is_supported( $post_id ) ) {
				continue;
			}

			if ( LRM_Access::can_view( $post_id ) ) {
				continue;
			}

			$rule = LRM_Access::get_effective_rule( $post_id );

			if ( $rule->should_hide() ) {
				unset( $pages[ $key ] );
			}
		}

		return array_values( $pages );
	}

	/**
	 * Gesperrte Inhalte aus Abfragen entfernen.
	 *
	 * @param WP_Query $query Abfrage.
	 */
	public function filter_queries( $query ) {
		if ( ! LRM_Settings::get( 'hide_from_queries' ) ) {
			return;
		}

		if ( is_admin() || LRM_Access::is_computing() || ! $query instanceof WP_Query ) {
			return;
		}

		if ( $query->is_singular() || $query->get( 'lrm_skip' ) ) {
			return;
		}

		$restricted = LRM_Access::get_restricted_ids_for_current_user();

		if ( empty( $restricted ) ) {
			return;
		}

		$existing = (array) $query->get( 'post__not_in' );
		$query->set( 'post__not_in', array_values( array_unique( array_merge( $existing, $restricted ) ) ) );
	}

	/**
	 * Kommentare auf gesperrten Inhalten schließen.
	 *
	 * @param bool $open    Kommentare offen.
	 * @param int  $post_id Inhalts-ID.
	 * @return bool
	 */
	public function filter_comments_open( $open, $post_id ) {
		if ( ! $open || ! LRM_Settings::get( 'close_comments' ) ) {
			return $open;
		}

		if ( ! LRM_Access::is_supported( $post_id ) ) {
			return $open;
		}

		return LRM_Access::can_view( $post_id );
	}

	/**
	 * REST-Antworten für geschützte Inhaltstypen filtern.
	 */
	public function register_rest_filters() {
		if ( ! LRM_Settings::get( 'protect_rest' ) ) {
			return;
		}

		foreach ( LRM_Settings::protected_post_types() as $post_type ) {
			add_filter( "rest_prepare_{$post_type}", array( $this, 'filter_rest_response' ), 10, 3 );
		}
	}

	/**
	 * Inhalt aus REST-Antworten entfernen.
	 *
	 * @param WP_REST_Response $response Antwort.
	 * @param WP_Post          $post     Inhalt.
	 * @param WP_REST_Request  $request  Anfrage.
	 * @return WP_REST_Response
	 */
	public function filter_rest_response( $response, $post, $request = null ) {
		if ( ! $post instanceof WP_Post || LRM_Access::can_view( $post->ID ) ) {
			return $response;
		}

		// Im Bearbeitungskontext bleibt der Inhalt unverändert: andernfalls würde der
		// Editor den Hinweistext laden und beim Speichern den Inhalt überschreiben.
		if ( $request instanceof WP_REST_Request && 'edit' === $request->get_param( 'context' ) && current_user_can( 'edit_post', $post->ID ) ) {
			return $response;
		}

		$data = $response->get_data();
		$rule = LRM_Access::get_effective_rule( $post->ID );
		$text = wp_strip_all_tags( $this->notice_message( $rule ) );

		foreach ( array( 'content', 'excerpt' ) as $field ) {
			if ( isset( $data[ $field ] ) && is_array( $data[ $field ] ) ) {
				$data[ $field ]['rendered']  = $text;
				$data[ $field ]['protected'] = true;

				if ( isset( $data[ $field ]['raw'] ) ) {
					$data[ $field ]['raw'] = '';
				}
			}
		}

		$data['lrm_restricted'] = true;

		$response->set_data( $data );

		return $response;
	}

	/**
	 * Statusanzeige in der Werkzeugleiste.
	 *
	 * @param WP_Admin_Bar $bar Werkzeugleiste.
	 */
	public function admin_bar( $bar ) {
		if ( is_admin() || ! LRM_Settings::get( 'show_toolbar' ) || ! current_user_can( LRM_Roles::CAP_MANAGE ) ) {
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();

		if ( ! $post instanceof WP_Post || ! LRM_Access::is_supported( $post ) ) {
			return;
		}

		$rule = LRM_Access::get_effective_rule( $post->ID );

		if ( ! $rule->enabled ) {
			$title = __( 'Zugriff: frei', 'loheide-rights-management' );
			$icon  = '🔓';
		} else {
			$title = __( 'Zugriff: geschützt', 'loheide-rights-management' );
			$icon  = '🔒';
		}

		$bar->add_node(
			array(
				'id'    => 'lrm-status',
				'title' => $icon . ' ' . esc_html( $title ),
				'href'  => (string) get_edit_post_link( $post->ID ),
				'meta'  => array( 'title' => $rule->describe() ),
			)
		);

		$bar->add_node(
			array(
				'id'     => 'lrm-status-rule',
				'parent' => 'lrm-status',
				'title'  => esc_html( $rule->describe() ),
			)
		);

		if ( $rule->inherited ) {
			$bar->add_node(
				array(
					'id'     => 'lrm-status-source',
					'parent' => 'lrm-status',
					'title'  => esc_html(
						sprintf(
							/* translators: %s: Titel der übergeordneten Seite. */
							__( 'Geerbt von: %s', 'loheide-rights-management' ),
							get_the_title( $rule->source_id )
						)
					),
					'href'   => (string) get_edit_post_link( $rule->source_id ),
				)
			);
		}
	}

	/**
	 * Überschrift des Hinweises.
	 *
	 * @param LRM_Rule $rule Regel.
	 * @return string
	 */
	protected function notice_title( $rule ) {
		unset( $rule );

		$title = LRM_Settings::get( 'denied_title' );

		return $title ? $title : __( 'Kein Zugriff', 'loheide-rights-management' );
	}

	/**
	 * Text des Hinweises.
	 *
	 * @param LRM_Rule $rule Regel.
	 * @return string
	 */
	protected function notice_message( $rule ) {
		if ( $rule instanceof LRM_Rule && '' !== $rule->message ) {
			return $rule->message;
		}

		return (string) LRM_Settings::get( 'denied_message' );
	}

	/**
	 * Hinweisbox aufbauen.
	 *
	 * @param array   $check Prüfergebnis.
	 * @param WP_Post $post  Inhalt.
	 * @return string
	 */
	protected function render_notice( $check, $post ) {
		$rule    = $check['rule'];
		$title   = $this->notice_title( $rule );
		$message = $this->notice_message( $rule );
		$is_guest = ! is_user_logged_in();

		$html  = '<div class="lrm-gate">';
		$html .= '<div class="lrm-gate__icon" aria-hidden="true">';
		$html .= '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10.5" width="16" height="10" rx="2"></rect><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"></path></svg>';
		$html .= '</div>';
		$html .= '<h2 class="lrm-gate__title">' . esc_html( $title ) . '</h2>';
		$html .= '<div class="lrm-gate__text">' . wp_kses_post( wpautop( $message ) ) . '</div>';

		if ( $is_guest ) {
			$login_url = LRM_Settings::login_url( get_permalink( $post->ID ) );

			if ( LRM_Settings::get( 'show_login_form' ) && ! LRM_Settings::get( 'login_url' ) ) {
				$html .= '<div class="lrm-gate__form">';
				$html .= wp_login_form(
					array(
						'echo'     => false,
						'redirect' => get_permalink( $post->ID ),
						'label_log_in' => __( 'Anmelden', 'loheide-rights-management' ),
					)
				);
				$html .= '</div>';
			} else {
				$html .= '<p class="lrm-gate__actions"><a class="lrm-gate__button" href="' . esc_url( $login_url ) . '">' . esc_html__( 'Zur Anmeldung', 'loheide-rights-management' ) . '</a></p>';
			}
		} elseif ( 'denied_role' === $check['reason'] ) {
			$html .= '<p class="lrm-gate__hint">' . esc_html__( 'Ihr Benutzerkonto ist für diesen Bereich ausdrücklich gesperrt.', 'loheide-rights-management' ) . '</p>';
		} else {
			$html .= '<p class="lrm-gate__hint">' . esc_html__( 'Ihr Benutzerkonto verfügt nicht über die erforderliche Berechtigung.', 'loheide-rights-management' ) . '</p>';
		}

		if ( current_user_can( LRM_Roles::CAP_MANAGE ) ) {
			$html .= '<p class="lrm-gate__debug"><strong>' . esc_html__( 'Hinweis für Redaktionen:', 'loheide-rights-management' ) . '</strong> ' . esc_html( LRM_Access::reason_text( $check ) ) . ' ' . esc_html( $rule->describe() ) . '</p>';
		}

		$html .= '</div>';

		/**
		 * Filtert die Hinweisbox.
		 *
		 * @param string  $html  HTML.
		 * @param array   $check Prüfergebnis.
		 * @param WP_Post $post  Inhalt.
		 */
		return apply_filters( 'lrm_denied_notice', $html, $check, $post );
	}
}
