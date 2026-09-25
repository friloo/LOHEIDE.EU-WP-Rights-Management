<?php
/**
 * Verwaltungsseite der Backend-Rechte.
 *
 * @package LOHEIDE_Rights_Management
 */

defined( 'ABSPATH' ) || exit;

/**
 * Oberfläche, mit der Rollen Seiten, Kategorien und Menüpunkte zugewiesen werden.
 */
class LRM_Backend_Admin {

	/**
	 * Slug der Seite.
	 */
	const PAGE = 'lrm-backend';

	/**
	 * Hooks registrieren.
	 */
	public function hooks() {
		add_action( 'admin_post_lrm_save_backend', array( $this, 'handle_save' ) );
	}

	/**
	 * Seite ausgeben.
	 */
	public function render() {
		if ( ! current_user_can( LRM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'Fehlende Berechtigung.', 'loheide-rights-management' ) );
		}

		$roles    = LRM_Roles::wp_only();
		$selected = isset( $_GET['lrm_role'] ) ? sanitize_key( wp_unslash( $_GET['lrm_role'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $selected && ! isset( $roles[ $selected ] ) ) {
			$selected = '';
		}
		?>
		<div class="wrap lrm-wrap">
			<?php lrm()->admin->page_header( self::PAGE ); ?>

			<?php if ( isset( $_GET['lrm_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="lrm-notice lrm-notice--ok">
					<span class="dashicons dashicons-yes-alt"></span>
					<div><strong><?php esc_html_e( 'Die Rechte wurden gespeichert.', 'loheide-rights-management' ); ?></strong></div>
				</div>
			<?php endif; ?>

			<div class="lrm-panel">
				<div class="lrm-panel__head">
					<h2><?php esc_html_e( 'Rolle wählen', 'loheide-rights-management' ); ?></h2>
					<p class="lrm-muted"><?php esc_html_e( 'Legen Sie je Rolle fest, welche Inhalte im Backend bearbeitet werden dürfen und welche Menüpunkte sichtbar sind.', 'loheide-rights-management' ); ?></p>
				</div>
				<div class="lrm-panel__body">
					<div class="lrm-rolegrid">
						<?php
						foreach ( $roles as $role => $label ) :
							$config     = LRM_Backend::get( $role );
							$restricted = ! empty( $config['enabled'] );
							$bypass     = get_role( $role ) && get_role( $role )->has_cap( LRM_Roles::CAP_BYPASS );
							$url        = add_query_arg(
								array(
									'page'     => self::PAGE,
									'lrm_role' => $role,
								),
								admin_url( 'admin.php' )
							);
							?>
							<a class="lrm-rolecard <?php echo $selected === $role ? 'is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>">
								<span class="lrm-rolecard__name"><?php echo esc_html( $label ); ?></span>
								<span class="lrm-rolecard__state">
									<?php if ( $bypass ) : ?>
										<span class="lrm-badge lrm-badge--bypass"><?php esc_html_e( 'uneingeschränkt', 'loheide-rights-management' ); ?></span>
									<?php elseif ( $restricted ) : ?>
										<span class="lrm-badge lrm-badge--roles">
											<?php
											$page_count = count( $config['pages'] );
											$cat_count  = count( $config['categories'] );

											printf(
												/* translators: 1: Anzahl Seiten mit Wortform, 2: Anzahl Kategorien mit Wortform. */
												esc_html__( '%1$s · %2$s', 'loheide-rights-management' ),
												esc_html( sprintf( _n( '%s Seite', '%s Seiten', $page_count, 'loheide-rights-management' ), number_format_i18n( $page_count ) ) ),
												esc_html( sprintf( _n( '%s Kategorie', '%s Kategorien', $cat_count, 'loheide-rights-management' ), number_format_i18n( $cat_count ) ) )
											);
											?>
										</span>
									<?php else : ?>
										<span class="lrm-muted"><?php esc_html_e( 'keine Beschränkung', 'loheide-rights-management' ); ?></span>
									<?php endif; ?>
								</span>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<?php
			if ( ! $selected ) {
				?>
				<div class="lrm-panel lrm-panel--hint">
					<h2><span class="dashicons dashicons-info-outline"></span> <?php esc_html_e( 'So wirkt die Beschränkung', 'loheide-rights-management' ); ?></h2>
					<ul class="lrm-steps">
						<li><?php esc_html_e( 'Eine beschränkte Rolle darf ausschließlich die zugewiesenen Seiten bearbeiten – alle anderen Seiten erscheinen nicht einmal in der Liste.', 'loheide-rights-management' ); ?></li>
						<li><?php esc_html_e( 'Bei Beiträgen sieht und bearbeitet sie nur die freigegebenen Kategorien. Andere Kategorien stehen nicht zur Auswahl.', 'loheide-rights-management' ); ?></li>
						<li><?php esc_html_e( 'Die nötigen Fähigkeiten vergibt das Plugin automatisch und nimmt sie zurück, sobald die Beschränkung endet.', 'loheide-rights-management' ); ?></li>
						<li><?php esc_html_e( 'Benutzer mit Umgehungsrecht – im Regelfall Administratoren – bleiben davon unberührt.', 'loheide-rights-management' ); ?></li>
					</ul>
				</div>
				<?php
				lrm()->admin->page_footer();
				echo '</div>';
				return;
			}

			$config = LRM_Backend::get( $selected );
			$this->render_form( $selected, $roles[ $selected ], $config );

			lrm()->admin->page_footer();
			?>
		</div>
		<?php
	}

	/**
	 * Formular für eine Rolle.
	 *
	 * @param string $role   Rollenschlüssel.
	 * @param string $label  Anzeigename.
	 * @param array  $config Regel.
	 */
	protected function render_form( $role, $label, $config ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lrm-form" id="lrm-backend-form">
			<input type="hidden" name="action" value="lrm_save_backend" />
			<input type="hidden" name="lrm_role" value="<?php echo esc_attr( $role ); ?>" />
			<?php wp_nonce_field( 'lrm_save_backend' ); ?>

			<div class="lrm-panel">
				<div class="lrm-panel__head">
					<h2>
						<?php
						printf(
							/* translators: %s: Rollenname. */
							esc_html__( 'Rechte der Rolle „%s“', 'loheide-rights-management' ),
							esc_html( $label )
						);
						?>
					</h2>
				</div>
				<div class="lrm-panel__body">
					<label class="lrm-switch lrm-switch--row">
						<input type="checkbox" name="lrm_backend[enabled]" value="1" <?php checked( ! empty( $config['enabled'] ) ); ?> />
						<span class="lrm-switch__track"><span class="lrm-switch__knob"></span></span>
						<span class="lrm-switch__label">
							<strong><?php esc_html_e( 'Backend-Rechte dieser Rolle beschränken', 'loheide-rights-management' ); ?></strong>
							<em><?php esc_html_e( 'Ist die Option aus, gelten die gewohnten WordPress-Rechte der Rolle.', 'loheide-rights-management' ); ?></em>
						</span>
					</label>
				</div>
			</div>

			<div class="lrm-panel">
				<div class="lrm-panel__head">
					<h2><?php esc_html_e( 'Bearbeitbare Seiten', 'loheide-rights-management' ); ?></h2>
					<p class="lrm-muted"><?php esc_html_e( 'Nur die hier gewählten Seiten darf die Rolle öffnen und bearbeiten.', 'loheide-rights-management' ); ?></p>
					<span class="lrm-searchbox">
						<span class="dashicons dashicons-search"></span>
						<input type="search" placeholder="<?php esc_attr_e( 'Seite suchen …', 'loheide-rights-management' ); ?>" data-lrm-filter="#lrm-pagelist" />
					</span>
				</div>
				<div class="lrm-panel__body">
					<?php
					$pages = get_pages(
						array(
							'sort_column' => 'menu_order,post_title',
							'post_status' => array( 'publish', 'draft', 'private' ),
							'number'      => 300,
						)
					);

					if ( empty( $pages ) ) {
						echo '<p class="lrm-muted">' . esc_html__( 'Es sind noch keine Seiten vorhanden.', 'loheide-rights-management' ) . '</p>';
					} else {
						echo '<div class="lrm-checklist-box" id="lrm-pagelist">';

						foreach ( $pages as $page ) {
							$depth  = count( get_post_ancestors( $page->ID ) );
							$prefix = str_repeat( '— ', min( $depth, 4 ) );
							printf(
								'<label class="lrm-listitem"><input type="checkbox" name="lrm_backend[pages][]" value="%1$d" %2$s /> <span>%3$s%4$s</span> <em>%5$s</em></label>',
								(int) $page->ID,
								checked( in_array( (int) $page->ID, array_map( 'absint', $config['pages'] ), true ), true, false ),
								esc_html( $prefix ),
								esc_html( $page->post_title ? $page->post_title : __( '(ohne Titel)', 'loheide-rights-management' ) ),
								esc_html( 'publish' === $page->post_status ? '' : $page->post_status )
							);
						}

						echo '</div>';
					}
					?>

					<label class="lrm-check lrm-check--spaced">
						<input type="checkbox" name="lrm_backend[create_pages]" value="1" <?php checked( ! empty( $config['create_pages'] ) ); ?> />
						<span><?php esc_html_e( 'Darf neue Seiten anlegen', 'loheide-rights-management' ); ?></span>
					</label>
				</div>
			</div>

			<div class="lrm-panel">
				<div class="lrm-panel__head">
					<h2><?php esc_html_e( 'Beiträge und Kategorien', 'loheide-rights-management' ); ?></h2>
					<p class="lrm-muted"><?php esc_html_e( 'Die Rolle sieht und bearbeitet ausschließlich Beiträge in diesen Kategorien. Andere Kategorien stehen nicht zur Auswahl.', 'loheide-rights-management' ); ?></p>
				</div>
				<div class="lrm-panel__body">
					<?php
					$categories = get_categories( array( 'hide_empty' => false ) );

					if ( empty( $categories ) ) {
						echo '<p class="lrm-muted">' . esc_html__( 'Es sind keine Kategorien vorhanden.', 'loheide-rights-management' ) . '</p>';
					} else {
						echo '<div class="lrm-roles">';

						foreach ( $categories as $category ) {
							printf(
								'<label class="lrm-chip lrm-chip--allow"><input type="checkbox" name="lrm_backend[categories][]" value="%1$d" %2$s /><span class="lrm-chip__label">%3$s</span></label>',
								(int) $category->term_id,
								checked( in_array( (int) $category->term_id, array_map( 'absint', $config['categories'] ), true ), true, false ),
								esc_html( $category->name )
							);
						}

						echo '</div>';
					}
					?>

					<div class="lrm-field--checks lrm-check--spaced">
						<label class="lrm-check">
							<input type="checkbox" name="lrm_backend[create_posts]" value="1" <?php checked( ! empty( $config['create_posts'] ) ); ?> />
							<span><?php esc_html_e( 'Darf neue Beiträge anlegen', 'loheide-rights-management' ); ?></span>
						</label>
						<label class="lrm-check">
							<input type="checkbox" name="lrm_backend[own_posts_only]" value="1" <?php checked( ! empty( $config['own_posts_only'] ) ); ?> />
							<span><?php esc_html_e( 'Nur selbst verfasste Beiträge bearbeiten', 'loheide-rights-management' ); ?></span>
						</label>
					</div>
				</div>
			</div>

			<div class="lrm-panel">
				<div class="lrm-panel__head">
					<h2><?php esc_html_e( 'Mediathek', 'loheide-rights-management' ); ?></h2>
				</div>
				<div class="lrm-panel__body">
					<label class="lrm-switch lrm-switch--row">
						<input type="checkbox" name="lrm_backend[allow_media]" value="1" <?php checked( ! empty( $config['allow_media'] ) ); ?> />
						<span class="lrm-switch__track"><span class="lrm-switch__knob"></span></span>
						<span class="lrm-switch__label">
							<strong><?php esc_html_e( 'Zugriff auf die Mediathek', 'loheide-rights-management' ); ?></strong>
							<em><?php esc_html_e( 'Wird die Option abgeschaltet, kann die Rolle keine Dateien hochladen und die Mediathek nicht öffnen.', 'loheide-rights-management' ); ?></em>
						</span>
					</label>
					<label class="lrm-switch lrm-switch--row">
						<input type="checkbox" name="lrm_backend[own_media_only]" value="1" <?php checked( ! empty( $config['own_media_only'] ) ); ?> />
						<span class="lrm-switch__track"><span class="lrm-switch__knob"></span></span>
						<span class="lrm-switch__label">
							<strong><?php esc_html_e( 'Nur eigene Dateien anzeigen', 'loheide-rights-management' ); ?></strong>
							<em><?php esc_html_e( 'Die Rolle sieht in der Mediathek ausschließlich ihre eigenen Uploads.', 'loheide-rights-management' ); ?></em>
						</span>
					</label>
				</div>
			</div>

			<div class="lrm-panel">
				<div class="lrm-panel__head">
					<h2><?php esc_html_e( 'Sichtbare Menüpunkte', 'loheide-rights-management' ); ?></h2>
					<p class="lrm-muted"><?php esc_html_e( 'Abgeschaltete Punkte verschwinden aus dem Menü und sind auch über die Adresszeile gesperrt.', 'loheide-rights-management' ); ?></p>
				</div>
				<div class="lrm-panel__body">
					<div class="lrm-menulist">
						<?php
						$hidden = (array) $config['hidden_menus'];

						foreach ( LRM_Backend_Guard::collect_menus() as $menu ) :
							$is_hidden = in_array( $menu['key'], $hidden, true );
							?>
							<div class="lrm-menuitem">
								<label class="lrm-switch lrm-switch--compact">
									<input type="checkbox" name="lrm_backend[visible_menus][]" value="<?php echo esc_attr( $menu['key'] ); ?>" <?php checked( ! $is_hidden ); ?> />
									<span class="lrm-switch__track"><span class="lrm-switch__knob"></span></span>
									<span class="lrm-switch__label"><strong><?php echo esc_html( $menu['label'] ); ?></strong></span>
								</label>

								<?php if ( ! empty( $menu['children'] ) ) : ?>
									<div class="lrm-menuitem__children">
										<?php foreach ( $menu['children'] as $child ) : ?>
											<label class="lrm-check">
												<input type="checkbox" name="lrm_backend[visible_menus][]" value="<?php echo esc_attr( $child['key'] ); ?>" <?php checked( ! in_array( $child['key'], $hidden, true ) ); ?> />
												<span><?php echo esc_html( $child['label'] ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
					<input type="hidden" name="lrm_backend[all_menus]" value="<?php echo esc_attr( wp_json_encode( $this->all_menu_keys() ) ); ?>" />
				</div>
			</div>

			<p class="lrm-submit">
				<?php submit_button( __( 'Rechte speichern', 'loheide-rights-management' ), 'primary', 'submit', false ); ?>
			</p>
		</form>
		<?php
	}

	/**
	 * Alle bekannten Menüschlüssel.
	 *
	 * Dient als Referenz beim Speichern: Was nicht angehakt ist, wird verborgen.
	 *
	 * @return array
	 */
	protected function all_menu_keys() {
		$keys = array();

		foreach ( LRM_Backend_Guard::collect_menus() as $menu ) {
			$keys[] = $menu['key'];

			foreach ( $menu['children'] as $child ) {
				$keys[] = $child['key'];
			}
		}

		return $keys;
	}

	/**
	 * Formular speichern.
	 */
	public function handle_save() {
		if ( ! current_user_can( LRM_Roles::CAP_MANAGE ) || ! check_admin_referer( 'lrm_save_backend' ) ) {
			wp_die( esc_html__( 'Fehlende Berechtigung.', 'loheide-rights-management' ) );
		}

		$role = isset( $_POST['lrm_role'] ) ? sanitize_key( wp_unslash( $_POST['lrm_role'] ) ) : '';

		if ( ! $role || ! get_role( $role ) ) {
			wp_die( esc_html__( 'Unbekannte Rolle.', 'loheide-rights-management' ) );
		}

		$input = isset( $_POST['lrm_backend'] ) && is_array( $_POST['lrm_backend'] ) ? wp_unslash( $_POST['lrm_backend'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Felder werden einzeln bereinigt.

		$config = LRM_Backend::get( $role );

		$config['enabled']        = empty( $input['enabled'] ) ? 0 : 1;
		$config['create_pages']   = empty( $input['create_pages'] ) ? 0 : 1;
		$config['create_posts']   = empty( $input['create_posts'] ) ? 0 : 1;
		$config['own_posts_only'] = empty( $input['own_posts_only'] ) ? 0 : 1;
		$config['allow_media']    = empty( $input['allow_media'] ) ? 0 : 1;
		$config['own_media_only'] = empty( $input['own_media_only'] ) ? 0 : 1;
		$config['pages']          = isset( $input['pages'] ) ? array_map( 'absint', (array) $input['pages'] ) : array();
		$config['categories']     = isset( $input['categories'] ) ? array_map( 'absint', (array) $input['categories'] ) : array();

		// Aus den sichtbaren Punkten die verborgenen ableiten.
		$all     = isset( $input['all_menus'] ) ? json_decode( (string) $input['all_menus'], true ) : array();
		$visible = isset( $input['visible_menus'] ) ? array_map( 'sanitize_text_field', (array) $input['visible_menus'] ) : array();

		if ( is_array( $all ) && $all ) {
			$all                     = array_map( 'sanitize_text_field', $all );
			$config['hidden_menus']  = array_values( array_diff( $all, $visible ) );
		}

		LRM_Backend::save( $role, $config );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => self::PAGE,
					'lrm_role'  => $role,
					'lrm_saved' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
