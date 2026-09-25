<?php
/**
 * Verwaltungsoberfläche.
 *
 * @package LOHEIDE_Rights_Management
 */

defined( 'ABSPATH' ) || exit;

/**
 * Menüs, Übersichtsseiten und Einstellungen.
 */
class LRM_Admin {

	/**
	 * Slug des Hauptmenüs.
	 */
	const PAGE = 'lrm-overview';

	/**
	 * Einträge pro Seite in der Übersicht.
	 */
	const PER_PAGE = 20;

	/**
	 * Hooks registrieren.
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( 'LRM_Settings', 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . LRM_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Menüeinträge.
	 */
	public function menu() {
		$cap = LRM_Roles::CAP_MANAGE;

		add_menu_page(
			LRM_NAME,
			__( 'Rechte', 'loheide-rights-management' ),
			$cap,
			self::PAGE,
			array( $this, 'render_overview' ),
			'dashicons-lock',
			71
		);

		add_submenu_page(
			self::PAGE,
			__( 'Übersicht', 'loheide-rights-management' ),
			__( 'Übersicht', 'loheide-rights-management' ),
			$cap,
			self::PAGE,
			array( $this, 'render_overview' )
		);

		add_submenu_page(
			self::PAGE,
			__( 'Rollen', 'loheide-rights-management' ),
			__( 'Rollen', 'loheide-rights-management' ),
			$cap,
			'lrm-roles',
			array( $this, 'render_roles' )
		);

		add_submenu_page(
			self::PAGE,
			__( 'Einstellungen', 'loheide-rights-management' ),
			__( 'Einstellungen', 'loheide-rights-management' ),
			$cap,
			'lrm-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Verweis in der Plugin-Liste.
	 *
	 * @param array $links Vorhandene Verweise.
	 * @return array
	 */
	public function action_links( $links ) {
		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=lrm-settings' ) ),
			esc_html__( 'Einstellungen', 'loheide-rights-management' )
		);

		$links[] = sprintf(
			'<a href="%1$s" target="_blank" rel="noopener">%2$s</a>',
			esc_url( LRM_VENDOR_URL ),
			esc_html(
				sprintf(
					/* translators: %s: Name des Herstellers. */
					__( 'Entwickelt von %s', 'loheide-rights-management' ),
					LRM_VENDOR
				)
			)
		);

		return $links;
	}

	/**
	 * Einheitlicher Entwicklerhinweis.
	 *
	 * @param bool $linked Mit Verweis auf die Herstellerseite.
	 * @return string HTML.
	 */
	public static function credit( $linked = true ) {
		$text = sprintf(
			/* translators: %s: Name des Herstellers. */
			__( 'Entwickelt von %s', 'loheide-rights-management' ),
			LRM_VENDOR
		);

		if ( ! $linked ) {
			return esc_html( $text );
		}

		return sprintf(
			'<a href="%1$s" target="_blank" rel="noopener">%2$s</a>',
			esc_url( LRM_VENDOR_URL ),
			esc_html( $text )
		);
	}

	/**
	 * Fußzeile der Plugin-Seiten.
	 */
	public static function render_footer() {
		?>
		<div class="lrm-credit">
			<span class="lrm-credit__mark">LOHEIDE<span>.EU</span></span>
			<span class="lrm-credit__text">
				<?php
				printf(
					/* translators: 1: Plugin-Name, 2: Version. */
					esc_html__( '%1$s · Version %2$s', 'loheide-rights-management' ),
					esc_html( LRM_NAME ),
					esc_html( LRM_VERSION )
				);
				?>
			</span>
			<span class="lrm-credit__link"><?php echo self::credit(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
		<?php
	}

	/**
	 * Skripte und Styles laden.
	 *
	 * @param string $hook Aktueller Admin-Screen.
	 */
	public function assets( $hook ) {
		$screen    = get_current_screen();
		$is_editor = $screen && 'post' === $screen->base && in_array( $screen->post_type, LRM_Settings::protected_post_types(), true );
		$is_list   = $screen && 'edit' === $screen->base && in_array( $screen->post_type, LRM_Settings::protected_post_types(), true );
		$is_plugin = false !== strpos( $hook, 'lrm-' ) || false !== strpos( $hook, self::PAGE );

		if ( ! $is_editor && ! $is_plugin && ! $is_list ) {
			return;
		}

		wp_enqueue_style( 'lrm-admin', LRM_URL . 'assets/css/admin.css', array( 'dashicons' ), LRM_VERSION );

		if ( $is_editor || $is_list || $is_plugin ) {
			wp_enqueue_script( 'lrm-admin', LRM_URL . 'assets/js/admin.js', array(), LRM_VERSION, true );
			wp_localize_script(
				'lrm-admin',
				'lrmAdmin',
				array(
					'i18n' => array(
						'conflict'    => __( 'Folgende Rollen sind freigegeben und gleichzeitig gesperrt: %s. Für diese Rollen gilt die Sperre.', 'loheide-rights-management' ),
						'sumPublic'   => __( 'Ergebnis: Der Inhalt ist öffentlich sichtbar.', 'loheide-rights-management' ),
						'sumLoggedIn' => __( 'Ergebnis: Sichtbar für alle angemeldeten Benutzer.', 'loheide-rights-management' ),
						'sumRoles'    => __( 'Ergebnis: Sichtbar für %s.', 'loheide-rights-management' ),
						'sumNoRoles'  => __( 'Achtung: Es ist keine Rolle freigegeben – niemand außer Benutzern mit Umgehungsrecht erhält Zugriff.', 'loheide-rights-management' ),
						'sumDenied'   => __( 'Gesperrt: %s (Vorrang).', 'loheide-rights-management' ),
						'sumOff'      => __( 'Ergebnis: Keine eigene Einschränkung für diesen Inhalt.', 'loheide-rights-management' ),
						'none'        => __( 'keine Rolle', 'loheide-rights-management' ),
					),
				)
			);
		}
	}

	/**
	 * Gemeinsamer Seitenkopf.
	 *
	 * @param string $current Aktive Seite.
	 */
	protected function header( $current ) {
		$tabs = array(
			self::PAGE     => array(
				'label' => __( 'Übersicht', 'loheide-rights-management' ),
				'icon'  => 'dashicons-chart-bar',
			),
			'lrm-roles'    => array(
				'label' => __( 'Rollen', 'loheide-rights-management' ),
				'icon'  => 'dashicons-groups',
			),
			'lrm-settings' => array(
				'label' => __( 'Einstellungen', 'loheide-rights-management' ),
				'icon'  => 'dashicons-admin-generic',
			),
		);
		?>
		<div class="lrm-header">
			<div class="lrm-header__brand">
				<span class="lrm-header__logo dashicons dashicons-lock"></span>
				<div>
					<h1><?php echo esc_html( LRM_NAME ); ?></h1>
					<p><?php esc_html_e( 'Seiten nach Anmeldung und WordPress-Rollen freigeben oder sperren.', 'loheide-rights-management' ); ?></p>
				</div>
				<span class="lrm-header__meta">
					<span class="lrm-header__version">v<?php echo esc_html( LRM_VERSION ); ?></span>
					<a class="lrm-header__vendor" href="<?php echo esc_url( LRM_VENDOR_URL ); ?>" target="_blank" rel="noopener">
						<?php
						printf(
							/* translators: %s: Name des Herstellers. */
							esc_html__( 'Entwickelt von %s', 'loheide-rights-management' ),
							esc_html( LRM_VENDOR )
						);
						?>
					</a>
				</span>
			</div>
			<nav class="lrm-tabs">
				<?php foreach ( $tabs as $slug => $tab ) : ?>
					<a class="lrm-tab <?php echo $current === $slug ? 'is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>">
						<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
						<?php echo esc_html( $tab['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
		</div>
		<?php
	}

	/**
	 * Übersichtsseite.
	 */
	public function render_overview() {
		if ( ! current_user_can( LRM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'Fehlende Berechtigung.', 'loheide-rights-management' ) );
		}

		$ids   = LRM_Access::get_ruled_post_ids();
		$items = array();
		$stats = array(
			'total'     => 0,
			'logged_in' => 0,
			'roles'     => 0,
			'public'    => 0,
			'denied'    => 0,
		);

		foreach ( $ids as $post_id ) {
			$rule = LRM_Access::get_rule( $post_id );

			if ( ! $rule->enabled ) {
				continue;
			}

			$stats['total']++;

			if ( isset( $stats[ $rule->visibility ] ) ) {
				$stats[ $rule->visibility ]++;
			}

			if ( ! empty( $rule->denied ) ) {
				$stats['denied']++;
			}

			$items[] = array(
				'id'   => $post_id,
				'rule' => $rule,
			);
		}

		// Filter.
		$filter = isset( $_GET['lrm_filter'] ) ? sanitize_key( wp_unslash( $_GET['lrm_filter'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['lrm_s'] ) ? sanitize_text_field( wp_unslash( $_GET['lrm_s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$filtered = array_values(
			array_filter(
				$items,
				function ( $item ) use ( $filter, $search ) {
					$rule = $item['rule'];

					if ( 'denied' === $filter && empty( $rule->denied ) ) {
						return false;
					}

					if ( in_array( $filter, array( 'public', 'logged_in', 'roles' ), true ) && $rule->visibility !== $filter ) {
						return false;
					}

					if ( '' !== $search && false === stripos( get_the_title( $item['id'] ), $search ) ) {
						return false;
					}

					return true;
				}
			)
		);

		$total_pages = max( 1, (int) ceil( count( $filtered ) / self::PER_PAGE ) );
		$paged       = min( $paged, $total_pages );
		$page_items  = array_slice( $filtered, ( $paged - 1 ) * self::PER_PAGE, self::PER_PAGE );

		$filters = array(
			'all'       => __( 'Alle', 'loheide-rights-management' ),
			'logged_in' => __( 'Nur angemeldet', 'loheide-rights-management' ),
			'roles'     => __( 'Rollenbasiert', 'loheide-rights-management' ),
			'public'    => __( 'Öffentlich mit Sperren', 'loheide-rights-management' ),
			'denied'    => __( 'Mit gesperrten Rollen', 'loheide-rights-management' ),
		);
		?>
		<div class="wrap lrm-wrap">
			<?php $this->header( self::PAGE ); ?>

			<div class="lrm-stats">
				<?php
				$cards = array(
					array(
						'label' => __( 'Geschützte Inhalte', 'loheide-rights-management' ),
						'value' => $stats['total'],
						'icon'  => 'dashicons-lock',
						'tone'  => 'primary',
					),
					array(
						'label' => __( 'Nur angemeldet', 'loheide-rights-management' ),
						'value' => $stats['logged_in'],
						'icon'  => 'dashicons-admin-users',
						'tone'  => 'info',
					),
					array(
						'label' => __( 'Rollenbasiert', 'loheide-rights-management' ),
						'value' => $stats['roles'],
						'icon'  => 'dashicons-groups',
						'tone'  => 'success',
					),
					array(
						'label' => __( 'Mit Rollensperre', 'loheide-rights-management' ),
						'value' => $stats['denied'],
						'icon'  => 'dashicons-dismiss',
						'tone'  => 'danger',
					),
					array(
						'label' => __( 'Verfügbare Rollen', 'loheide-rights-management' ),
						'value' => count( LRM_Roles::wp_only() ),
						'icon'  => 'dashicons-id',
						'tone'  => 'neutral',
					),
				);

				foreach ( $cards as $card ) :
					?>
					<div class="lrm-stat lrm-stat--<?php echo esc_attr( $card['tone'] ); ?>">
						<span class="lrm-stat__icon dashicons <?php echo esc_attr( $card['icon'] ); ?>"></span>
						<span class="lrm-stat__value"><?php echo esc_html( number_format_i18n( $card['value'] ) ); ?></span>
						<span class="lrm-stat__label"><?php echo esc_html( $card['label'] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="lrm-panel">
				<div class="lrm-panel__head">
					<h2><?php esc_html_e( 'Geschützte Inhalte', 'loheide-rights-management' ); ?></h2>
					<form method="get" class="lrm-filterbar">
						<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
						<div class="lrm-pills">
							<?php foreach ( $filters as $key => $label ) : ?>
								<button type="submit" name="lrm_filter" value="<?php echo esc_attr( $key ); ?>" class="lrm-pill <?php echo $filter === $key ? 'is-active' : ''; ?>">
									<?php echo esc_html( $label ); ?>
								</button>
							<?php endforeach; ?>
						</div>
						<span class="lrm-searchbox">
							<span class="dashicons dashicons-search"></span>
							<input type="search" name="lrm_s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Titel suchen …', 'loheide-rights-management' ); ?>" />
						</span>
					</form>
				</div>

				<?php if ( empty( $page_items ) ) : ?>
					<div class="lrm-empty">
						<span class="dashicons dashicons-unlock"></span>
						<h3><?php esc_html_e( 'Keine Einträge gefunden', 'loheide-rights-management' ); ?></h3>
						<p><?php esc_html_e( 'Öffnen Sie eine Seite im Editor und aktivieren Sie dort den Bereich „Zugriffsrechte“.', 'loheide-rights-management' ); ?></p>
						<a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>">
							<?php esc_html_e( 'Zu den Seiten', 'loheide-rights-management' ); ?>
						</a>
					</div>
				<?php else : ?>
					<table class="lrm-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Inhalt', 'loheide-rights-management' ); ?></th>
								<th><?php esc_html_e( 'Sichtbarkeit', 'loheide-rights-management' ); ?></th>
								<th><?php esc_html_e( 'Freigegeben', 'loheide-rights-management' ); ?></th>
								<th><?php esc_html_e( 'Gesperrt', 'loheide-rights-management' ); ?></th>
								<th><?php esc_html_e( 'Bei fehlendem Zugriff', 'loheide-rights-management' ); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $page_items as $item ) :
								$rule = $item['rule'];
								$post = get_post( $item['id'] );

								if ( ! $post ) {
									continue;
								}
								?>
								<tr>
									<td>
										<strong><a href="<?php echo esc_url( (string) get_edit_post_link( $post->ID ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></strong>
										<div class="lrm-table__meta">
											<?php
											$type_object = get_post_type_object( $post->post_type );
											echo esc_html( $type_object ? $type_object->labels->singular_name : $post->post_type );
											?>
											<?php if ( 'publish' !== $post->post_status ) : ?>
												· <?php echo esc_html( get_post_status_object( $post->post_status ) ? get_post_status_object( $post->post_status )->label : $post->post_status ); ?>
											<?php endif; ?>
											<?php if ( ! $rule->propagate ) : ?>
												· <?php esc_html_e( 'ohne Unterseiten', 'loheide-rights-management' ); ?>
											<?php endif; ?>
										</div>
									</td>
									<td><?php echo self::visibility_badge( $rule ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
									<td><?php echo self::role_chips( $rule->allowed, 'allow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
									<td><?php echo self::role_chips( $rule->denied, 'deny' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
									<td><span class="lrm-muted"><?php echo esc_html( LRM_Metabox::action_label( $rule->effective_action() ) ); ?></span></td>
									<td class="lrm-table__actions">
										<a class="button button-small" href="<?php echo esc_url( (string) get_edit_post_link( $post->ID ) ); ?>"><?php esc_html_e( 'Bearbeiten', 'loheide-rights-management' ); ?></a>
										<a class="button button-small" href="<?php echo esc_url( (string) get_permalink( $post->ID ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Ansehen', 'loheide-rights-management' ); ?></a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php if ( $total_pages > 1 ) : ?>
						<div class="lrm-pagination">
							<?php
							echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								array(
									'base'      => add_query_arg( 'paged', '%#%' ),
									'format'    => '',
									'current'   => $paged,
									'total'     => $total_pages,
									'prev_text' => '‹',
									'next_text' => '›',
								)
							);
							?>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<div class="lrm-panel lrm-panel--hint">
				<h2><span class="dashicons dashicons-info-outline"></span> <?php esc_html_e( 'So wird entschieden', 'loheide-rights-management' ); ?></h2>
				<ol class="lrm-steps">
					<li><strong><?php esc_html_e( 'Gesperrte Rollen', 'loheide-rights-management' ); ?></strong> – <?php esc_html_e( 'Trifft eine Rolle des Benutzers auf die Sperrliste, ist der Zugriff beendet. Das gilt auch, wenn eine andere Rolle des Benutzers freigegeben wäre.', 'loheide-rights-management' ); ?></li>
					<li><strong><?php esc_html_e( 'Umgehungsrecht', 'loheide-rights-management' ); ?></strong> – <?php esc_html_e( 'Benutzer mit dem Recht „lrm_bypass_restrictions“ (Standard: Administratoren) sehen alles, sofern ihre Rolle nicht gesperrt ist.', 'loheide-rights-management' ); ?></li>
					<li><strong><?php esc_html_e( 'Grundsichtbarkeit', 'loheide-rights-management' ); ?></strong> – <?php esc_html_e( 'Öffentlich, nur angemeldet oder nur ausgewählte Rollen.', 'loheide-rights-management' ); ?></li>
					<li><strong><?php esc_html_e( 'Vererbung', 'loheide-rights-management' ); ?></strong> – <?php esc_html_e( 'Unterseiten übernehmen die Regel der nächstgelegenen übergeordneten Seite. Sperren aus der gesamten Kette bleiben bestehen.', 'loheide-rights-management' ); ?></li>
				</ol>
			</div>
			<?php self::render_footer(); ?>
		</div>
		<?php
	}

	/**
	 * Rollenseite mit Matrix und Simulation.
	 */
	public function render_roles() {
		if ( ! current_user_can( LRM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'Fehlende Berechtigung.', 'loheide-rights-management' ) );
		}

		$roles     = LRM_Roles::all();
		$ids       = LRM_Access::get_ruled_post_ids();
		$matrix    = array();
		$simulated = isset( $_GET['lrm_role'] ) ? sanitize_key( wp_unslash( $_GET['lrm_role'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		foreach ( array_keys( $roles ) as $role ) {
			$matrix[ $role ] = array(
				'allowed' => array(),
				'denied'  => array(),
			);
		}

		foreach ( $ids as $post_id ) {
			$rule = LRM_Access::get_rule( $post_id );

			if ( ! $rule->enabled ) {
				continue;
			}

			foreach ( $rule->allowed as $role ) {
				if ( isset( $matrix[ $role ] ) ) {
					$matrix[ $role ]['allowed'][] = $post_id;
				}
			}

			foreach ( $rule->denied as $role ) {
				if ( isset( $matrix[ $role ] ) ) {
					$matrix[ $role ]['denied'][] = $post_id;
				}
			}
		}
		?>
		<div class="wrap lrm-wrap">
			<?php $this->header( 'lrm-roles' ); ?>

			<div class="lrm-panel">
				<div class="lrm-panel__head">
					<h2><?php esc_html_e( 'Rollen im Überblick', 'loheide-rights-management' ); ?></h2>
					<p class="lrm-muted"><?php esc_html_e( 'Alle von WordPress bereitgestellten Rollen, ergänzt um die virtuelle Rolle „Gast“ für nicht angemeldete Besucher.', 'loheide-rights-management' ); ?></p>
				</div>
				<table class="lrm-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Rolle', 'loheide-rights-management' ); ?></th>
							<th><?php esc_html_e( 'Schlüssel', 'loheide-rights-management' ); ?></th>
							<th><?php esc_html_e( 'Benutzer', 'loheide-rights-management' ); ?></th>
							<th><?php esc_html_e( 'Freigegeben auf', 'loheide-rights-management' ); ?></th>
							<th><?php esc_html_e( 'Gesperrt auf', 'loheide-rights-management' ); ?></th>
							<th><?php esc_html_e( 'Umgehungsrecht', 'loheide-rights-management' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $roles as $role => $label ) :
							$count  = LRM_Roles::user_count( $role );
							$bypass = false;

							if ( LRM_Roles::GUEST !== $role ) {
								$role_object = get_role( $role );
								$bypass      = $role_object && $role_object->has_cap( LRM_Roles::CAP_BYPASS );
							}
							?>
							<tr>
								<td><strong><?php echo esc_html( $label ); ?></strong></td>
								<td><code><?php echo esc_html( $role ); ?></code></td>
								<td><?php echo null === $count ? '<span class="lrm-muted">—</span>' : esc_html( number_format_i18n( $count ) ); ?></td>
								<td>
									<?php if ( $matrix[ $role ]['allowed'] ) : ?>
										<span class="lrm-count lrm-count--allow"><?php echo esc_html( number_format_i18n( count( $matrix[ $role ]['allowed'] ) ) ); ?></span>
										<span class="lrm-muted"><?php echo esc_html( self::title_list( $matrix[ $role ]['allowed'] ) ); ?></span>
									<?php else : ?>
										<span class="lrm-muted">—</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $matrix[ $role ]['denied'] ) : ?>
										<span class="lrm-count lrm-count--deny"><?php echo esc_html( number_format_i18n( count( $matrix[ $role ]['denied'] ) ) ); ?></span>
										<span class="lrm-muted"><?php echo esc_html( self::title_list( $matrix[ $role ]['denied'] ) ); ?></span>
									<?php else : ?>
										<span class="lrm-muted">—</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $bypass ) : ?>
										<span class="lrm-badge lrm-badge--bypass"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'ja', 'loheide-rights-management' ); ?></span>
									<?php else : ?>
										<span class="lrm-muted">—</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="lrm-panel">
				<div class="lrm-panel__head">
					<h2><?php esc_html_e( 'Zugriff simulieren', 'loheide-rights-management' ); ?></h2>
					<form method="get" class="lrm-filterbar">
						<input type="hidden" name="page" value="lrm-roles" />
						<select name="lrm_role">
							<option value=""><?php esc_html_e( '— Rolle wählen —', 'loheide-rights-management' ); ?></option>
							<?php foreach ( $roles as $role => $label ) : ?>
								<option value="<?php echo esc_attr( $role ); ?>" <?php selected( $simulated, $role ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Prüfen', 'loheide-rights-management' ); ?></button>
					</form>
				</div>

				<?php if ( $simulated && isset( $roles[ $simulated ] ) ) : ?>
					<p class="lrm-muted">
						<?php
						printf(
							/* translators: %s: Rollenname. */
							esc_html__( 'Ergebnis für die Rolle „%s“ ohne Umgehungsrecht:', 'loheide-rights-management' ),
							esc_html( $roles[ $simulated ] )
						);
						?>
					</p>
					<table class="lrm-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Inhalt', 'loheide-rights-management' ); ?></th>
								<th><?php esc_html_e( 'Zugriff', 'loheide-rights-management' ); ?></th>
								<th><?php esc_html_e( 'Begründung', 'loheide-rights-management' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$logged_in = LRM_Roles::GUEST !== $simulated;

							foreach ( $ids as $post_id ) :
								$check = LRM_Access::evaluate( $post_id, array( $simulated ), false, $logged_in );
								?>
								<tr>
									<td><a href="<?php echo esc_url( (string) get_edit_post_link( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a></td>
									<td>
										<?php if ( $check['allowed'] ) : ?>
											<span class="lrm-badge lrm-badge--allow"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Zugriff', 'loheide-rights-management' ); ?></span>
										<?php else : ?>
											<span class="lrm-badge lrm-badge--deny"><span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'gesperrt', 'loheide-rights-management' ); ?></span>
										<?php endif; ?>
									</td>
									<td><span class="lrm-muted"><?php echo esc_html( LRM_Access::reason_text( $check ) ); ?></span></td>
								</tr>
							<?php endforeach; ?>
							<?php if ( empty( $ids ) ) : ?>
								<tr><td colspan="3"><span class="lrm-muted"><?php esc_html_e( 'Es sind noch keine Inhalte eingeschränkt.', 'loheide-rights-management' ); ?></span></td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
			<?php self::render_footer(); ?>
		</div>
		<?php
	}

	/**
	 * Einstellungsseite.
	 */
	public function render_settings() {
		if ( ! current_user_can( LRM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'Fehlende Berechtigung.', 'loheide-rights-management' ) );
		}

		$settings = LRM_Settings::all();
		$option   = LRM_Settings::OPTION;
		?>
		<div class="wrap lrm-wrap">
			<?php $this->header( 'lrm-settings' ); ?>

			<form method="post" action="options.php" class="lrm-form">
				<?php settings_fields( LRM_Settings::GROUP ); ?>

				<div class="lrm-panel">
					<div class="lrm-panel__head">
						<h2><?php esc_html_e( 'Inhaltstypen', 'loheide-rights-management' ); ?></h2>
						<p class="lrm-muted"><?php esc_html_e( 'Für welche Inhaltstypen sollen Zugriffsrechte verfügbar sein?', 'loheide-rights-management' ); ?></p>
					</div>
					<div class="lrm-panel__body">
						<div class="lrm-roles">
							<?php foreach ( LRM_Settings::available_post_types() as $slug => $label ) : ?>
								<label class="lrm-chip lrm-chip--allow">
									<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[post_types][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, (array) $settings['post_types'], true ) ); ?> />
									<span class="lrm-chip__label"><?php echo esc_html( $label ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				</div>

				<div class="lrm-panel">
					<div class="lrm-panel__head">
						<h2><?php esc_html_e( 'Position im Editor', 'loheide-rights-management' ); ?></h2>
						<p class="lrm-muted"><?php esc_html_e( 'Wo der Bereich „Zugriffsrechte“ beim Bearbeiten erscheint.', 'loheide-rights-management' ); ?></p>
					</div>
					<div class="lrm-panel__body">
						<div class="lrm-modes lrm-modes--compact">
							<?php
							$contexts = array(
								'side'   => array( 'dashicons-align-pull-right', __( 'Seitenleiste', 'loheide-rights-management' ), __( 'Sofort sichtbar – auch im Block-Editor. Empfohlen.', 'loheide-rights-management' ) ),
								'normal' => array( 'dashicons-align-wide', __( 'Unter dem Inhalt', 'loheide-rights-management' ), __( 'Breites Layout mit zwei Rollenspalten. Im Block-Editor muss die untere Leiste aufgezogen werden.', 'loheide-rights-management' ) ),
							);

							foreach ( $contexts as $value => $data ) :
								?>
								<label class="lrm-mode">
									<input type="radio" name="<?php echo esc_attr( $option ); ?>[metabox_context]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $settings['metabox_context'], $value ); ?> />
									<span class="lrm-mode__inner">
										<span class="dashicons <?php echo esc_attr( $data[0] ); ?>"></span>
										<span class="lrm-mode__title"><?php echo esc_html( $data[1] ); ?></span>
										<span class="lrm-mode__desc"><?php echo esc_html( $data[2] ); ?></span>
									</span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				</div>

				<div class="lrm-panel">
					<div class="lrm-panel__head">
						<h2><?php esc_html_e( 'Standardverhalten bei fehlendem Zugriff', 'loheide-rights-management' ); ?></h2>
					</div>
					<div class="lrm-panel__body">
						<div class="lrm-modes lrm-modes--compact">
							<?php
							$actions = array(
								'message'  => array( 'dashicons-info', __( 'Hinweis anzeigen', 'loheide-rights-management' ), __( 'Die Seite wird geladen, der Inhalt durch einen Hinweis ersetzt.', 'loheide-rights-management' ) ),
								'login'    => array( 'dashicons-admin-network', __( 'Zur Anmeldung', 'loheide-rights-management' ), __( 'Weiterleitung zum Anmeldeformular mit Rücksprung.', 'loheide-rights-management' ) ),
								'404'      => array( 'dashicons-warning', __( 'Seite nicht gefunden', 'loheide-rights-management' ), __( 'Der Inhalt wird wie nicht vorhanden behandelt.', 'loheide-rights-management' ) ),
								'redirect' => array( 'dashicons-external', __( 'Weiterleiten', 'loheide-rights-management' ), __( 'Weiterleitung an eine frei wählbare Adresse.', 'loheide-rights-management' ) ),
							);

							foreach ( $actions as $value => $data ) :
								?>
								<label class="lrm-mode">
									<input type="radio" name="<?php echo esc_attr( $option ); ?>[denied_action]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $settings['denied_action'], $value ); ?> />
									<span class="lrm-mode__inner">
										<span class="dashicons <?php echo esc_attr( $data[0] ); ?>"></span>
										<span class="lrm-mode__title"><?php echo esc_html( $data[1] ); ?></span>
										<span class="lrm-mode__desc"><?php echo esc_html( $data[2] ); ?></span>
									</span>
								</label>
							<?php endforeach; ?>
						</div>

						<div class="lrm-fields">
							<p class="lrm-field">
								<label for="lrm-denied-title"><?php esc_html_e( 'Überschrift des Hinweises', 'loheide-rights-management' ); ?></label>
								<input type="text" class="widefat" id="lrm-denied-title" name="<?php echo esc_attr( $option ); ?>[denied_title]" value="<?php echo esc_attr( $settings['denied_title'] ); ?>" />
							</p>
							<p class="lrm-field">
								<label for="lrm-redirect-url"><?php esc_html_e( 'Ziel der Weiterleitung', 'loheide-rights-management' ); ?></label>
								<input type="url" class="widefat" id="lrm-redirect-url" name="<?php echo esc_attr( $option ); ?>[redirect_url]" value="<?php echo esc_attr( $settings['redirect_url'] ); ?>" placeholder="https://" />
							</p>
							<p class="lrm-field lrm-field--full">
								<label for="lrm-denied-message"><?php esc_html_e( 'Hinweistext', 'loheide-rights-management' ); ?></label>
								<textarea class="widefat" rows="3" id="lrm-denied-message" name="<?php echo esc_attr( $option ); ?>[denied_message]"><?php echo esc_textarea( $settings['denied_message'] ); ?></textarea>
							</p>
							<p class="lrm-field">
								<label for="lrm-login-url"><?php esc_html_e( 'Eigene Anmeldeseite', 'loheide-rights-management' ); ?></label>
								<input type="url" class="widefat" id="lrm-login-url" name="<?php echo esc_attr( $option ); ?>[login_url]" value="<?php echo esc_attr( $settings['login_url'] ); ?>" placeholder="<?php echo esc_attr( wp_login_url() ); ?>" />
								<span class="lrm-hint"><?php esc_html_e( 'Leer lassen, um die Standard-Anmeldeseite von WordPress zu verwenden.', 'loheide-rights-management' ); ?></span>
							</p>
						</div>
					</div>
				</div>

				<div class="lrm-panel">
					<div class="lrm-panel__head">
						<h2><?php esc_html_e( 'Wirkungsbereich', 'loheide-rights-management' ); ?></h2>
					</div>
					<div class="lrm-panel__body">
						<?php
						$toggles = array(
							'hide_from_menus'   => array( __( 'Gesperrte Inhalte aus Menüs entfernen', 'loheide-rights-management' ), __( 'Navigationspunkte, auf die kein Zugriff besteht, werden ausgeblendet.', 'loheide-rights-management' ) ),
							'hide_from_queries' => array( __( 'Gesperrte Inhalte aus Suche und Listen entfernen', 'loheide-rights-management' ), __( 'Betrifft Suchergebnisse, Archive und Seitenlisten im Frontend.', 'loheide-rights-management' ) ),
							'protect_rest'      => array( __( 'REST-API schützen', 'loheide-rights-management' ), __( 'Inhalt und Auszug werden für nicht berechtigte Zugriffe entfernt.', 'loheide-rights-management' ) ),
							'protect_feeds'     => array( __( 'Feeds schützen', 'loheide-rights-management' ), __( 'Im RSS-Feed erscheint statt des Inhalts der Hinweistext.', 'loheide-rights-management' ) ),
							'close_comments'    => array( __( 'Kommentare auf gesperrten Inhalten schließen', 'loheide-rights-management' ), __( 'Verhindert das Mitlesen und Schreiben von Kommentaren.', 'loheide-rights-management' ) ),
							'admin_bypass'      => array( __( 'Umgehungsrecht aktivieren', 'loheide-rights-management' ), __( 'Administratoren sehen alle Inhalte – außer ihre Rolle wurde ausdrücklich gesperrt.', 'loheide-rights-management' ) ),
							'inherit_default'   => array( __( 'Vererbung standardmäßig aktiv', 'loheide-rights-management' ), __( 'Neue Inhalte übernehmen die Regeln übergeordneter Seiten.', 'loheide-rights-management' ) ),
							'show_toolbar'      => array( __( 'Status in der Werkzeugleiste anzeigen', 'loheide-rights-management' ), __( 'Zeigt Redaktionen im Frontend, welche Regel gerade greift.', 'loheide-rights-management' ) ),
							'show_login_form'   => array( __( 'Anmeldeformular im Hinweis anzeigen', 'loheide-rights-management' ), __( 'Nicht angemeldete Besucher können sich direkt auf der Seite anmelden.', 'loheide-rights-management' ) ),
							'show_credit'       => array(
								sprintf(
									/* translators: %s: Name des Herstellers. */
									__( 'Hinweis „Zugriffsschutz von %s“ im Frontend anzeigen', 'loheide-rights-management' ),
									LRM_VENDOR
								),
								__( 'Erscheint als dezente Zeile unter dem Hinweistext auf gesperrten Seiten.', 'loheide-rights-management' ),
							),
						);

						foreach ( $toggles as $key => $data ) :
							?>
							<label class="lrm-switch lrm-switch--row">
								<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $settings[ $key ] ) ); ?> />
								<span class="lrm-switch__track"><span class="lrm-switch__knob"></span></span>
								<span class="lrm-switch__label">
									<strong><?php echo esc_html( $data[0] ); ?></strong>
									<em><?php echo esc_html( $data[1] ); ?></em>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="lrm-panel lrm-panel--hint">
					<h2><span class="dashicons dashicons-editor-code"></span> <?php esc_html_e( 'Shortcodes', 'loheide-rights-management' ); ?></h2>
					<ul class="lrm-codelist">
						<li><code>[lrm_restrict roles="editor,author"]…[/lrm_restrict]</code> <?php esc_html_e( 'zeigt den Inhalt nur diesen Rollen.', 'loheide-rights-management' ); ?></li>
						<li><code>[lrm_restrict deny="subscriber"]…[/lrm_restrict]</code> <?php esc_html_e( 'verbirgt den Inhalt vor diesen Rollen (Vorrang).', 'loheide-rights-management' ); ?></li>
						<li><code>[lrm_restrict logged_in="yes"]…[/lrm_restrict]</code> <?php esc_html_e( 'zeigt den Inhalt nur angemeldeten Besuchern.', 'loheide-rights-management' ); ?></li>
						<li><code>[lrm_guest]…[/lrm_guest]</code> <?php esc_html_e( 'zeigt den Inhalt nur nicht angemeldeten Besuchern.', 'loheide-rights-management' ); ?></li>
					</ul>
				</div>

				<p class="lrm-submit">
					<?php submit_button( __( 'Einstellungen speichern', 'loheide-rights-management' ), 'primary', 'submit', false ); ?>
				</p>
			</form>
			<?php self::render_footer(); ?>
		</div>
		<?php
	}

	/**
	 * Titel mehrerer Inhalte als gekürzte Liste.
	 *
	 * @param array $ids   Inhalts-IDs.
	 * @param int   $limit Maximale Anzahl.
	 * @return string
	 */
	protected static function title_list( $ids, $limit = 3 ) {
		$titles = array();

		foreach ( array_slice( $ids, 0, $limit ) as $id ) {
			$titles[] = get_the_title( $id );
		}

		$text = implode( ', ', $titles );

		if ( count( $ids ) > $limit ) {
			$text .= ' ' . sprintf(
				/* translators: %s: Anzahl weiterer Inhalte. */
				__( 'und %s weitere', 'loheide-rights-management' ),
				number_format_i18n( count( $ids ) - $limit )
			);
		}

		return $text;
	}

	/**
	 * Abzeichen für den Sichtbarkeitsmodus.
	 *
	 * @param LRM_Rule $rule Regel.
	 * @return string HTML.
	 */
	public static function visibility_badge( $rule ) {
		switch ( $rule->visibility ) {
			case LRM_Rule::VISIBILITY_PUBLIC:
				$class = 'public';
				$icon  = 'dashicons-visibility';
				$label = __( 'Öffentlich', 'loheide-rights-management' );
				break;
			case LRM_Rule::VISIBILITY_ROLES:
				$class = 'roles';
				$icon  = 'dashicons-groups';
				$label = __( 'Rollen', 'loheide-rights-management' );
				break;
			case LRM_Rule::VISIBILITY_LOGGED_IN:
			default:
				$class = 'login';
				$icon  = 'dashicons-admin-users';
				$label = __( 'Angemeldet', 'loheide-rights-management' );
				break;
		}

		return sprintf(
			'<span class="lrm-badge lrm-badge--%1$s"><span class="dashicons %2$s"></span> %3$s</span>',
			esc_attr( $class ),
			esc_attr( $icon ),
			esc_html( $label )
		);
	}

	/**
	 * Rollen als Chips ausgeben.
	 *
	 * @param array  $roles Rollenschlüssel.
	 * @param string $tone  allow|deny.
	 * @return string HTML.
	 */
	public static function role_chips( $roles, $tone = 'allow' ) {
		if ( empty( $roles ) ) {
			return '<span class="lrm-muted">—</span>';
		}

		$html = '<span class="lrm-chiplist">';

		foreach ( LRM_Roles::labels( $roles ) as $label ) {
			$html .= sprintf(
				'<span class="lrm-tag lrm-tag--%1$s">%2$s</span>',
				esc_attr( $tone ),
				esc_html( $label )
			);
		}

		return $html . '</span>';
	}

	/**
	 * Statusabzeichen für die Listenansicht.
	 *
	 * @param int $post_id Inhalts-ID.
	 * @return string HTML.
	 */
	public static function render_status_badge( $post_id ) {
		$rule = LRM_Access::get_effective_rule( $post_id );

		if ( ! $rule->enabled ) {
			return '<span class="lrm-muted">' . esc_html__( 'frei', 'loheide-rights-management' ) . '</span>';
		}

		$html = self::visibility_badge( $rule );

		if ( $rule->inherited ) {
			$html .= ' <span class="lrm-badge lrm-badge--inherited" title="' . esc_attr(
				sprintf(
					/* translators: %s: Titel der übergeordneten Seite. */
					__( 'Geerbt von „%s“', 'loheide-rights-management' ),
					get_the_title( $rule->source_id )
				)
			) . '"><span class="dashicons dashicons-networking"></span></span>';
		}

		if ( ! empty( $rule->denied ) ) {
			$html .= '<div class="lrm-column-denied"><span class="dashicons dashicons-dismiss"></span> ' . esc_html( implode( ', ', LRM_Roles::labels( $rule->denied ) ) ) . '</div>';
		}

		if ( LRM_Rule::VISIBILITY_ROLES === $rule->visibility && ! empty( $rule->allowed ) ) {
			$html .= '<div class="lrm-column-allowed">' . esc_html( implode( ', ', LRM_Roles::labels( $rule->allowed ) ) ) . '</div>';
		}

		return $html;
	}
}
