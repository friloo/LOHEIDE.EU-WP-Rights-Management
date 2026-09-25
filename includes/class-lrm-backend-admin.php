<?php
/**
 * Verwaltungsseite der Backend-Rechte.
 *
 * @package LOHEIDE_Rights_Management
 */

defined( 'ABSPATH' ) || exit;

/**
 * Oberfläche, mit der Rollen Inhalte, Menüpunkte und Dashboard-Bereiche
 * zugewiesen werden.
 */
class LRM_Backend_Admin {

	/**
	 * Slug der Seite.
	 */
	const PAGE = 'lrm-backend';

	/**
	 * Ab dieser Anzahl wird statt einer Liste gesucht.
	 */
	const LIST_LIMIT = 200;

	/**
	 * Hooks registrieren.
	 */
	public function hooks() {
		add_action( 'admin_post_lrm_save_backend', array( $this, 'handle_save' ) );
		add_action( 'wp_ajax_lrm_search_items', array( $this, 'ajax_search_items' ) );
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
							$role_obj   = get_role( $role );
							$bypass     = $role_obj && $role_obj->has_cap( LRM_Roles::CAP_BYPASS );
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
										<span class="lrm-badge lrm-badge--roles"><?php echo esc_html( $this->describe( $config ) ); ?></span>
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
				$this->render_intro();
				lrm()->admin->page_footer();
				echo '</div>';

				return;
			}

			$this->render_form( $selected, $roles[ $selected ], LRM_Backend::get( $selected ) );

			lrm()->admin->page_footer();
			?>
		</div>
		<?php
	}

	/**
	 * Kurzbeschreibung einer Regel.
	 *
	 * @param array $config Regel.
	 * @return string
	 */
	protected function describe( $config ) {
		$parts = array();

		foreach ( (array) $config['types'] as $slug => $type ) {
			$object = get_post_type_object( $slug );
			$label  = $object ? $object->labels->name : $slug;
			$type   = wp_parse_args( (array) $type, LRM_Backend::type_defaults() );

			switch ( $type['mode'] ) {
				case 'all':
					$parts[] = sprintf(
						/* translators: %s: Bezeichnung des Inhaltstyps. */
						__( 'alle %s', 'loheide-rights-management' ),
						$label
					);
					break;

				case 'selected':
					$parts[] = sprintf(
						/* translators: 1: Anzahl, 2: Bezeichnung des Inhaltstyps. */
						__( '%1$d %2$s', 'loheide-rights-management' ),
						count( $type['items'] ),
						$label
					);
					break;

				case 'terms':
					$parts[] = sprintf(
						/* translators: 1: Bezeichnung des Inhaltstyps, 2: Anzahl der Begriffe. */
						__( '%1$s in %2$d Kategorien', 'loheide-rights-management' ),
						$label,
						count( $type['terms'] )
					);
					break;
			}
		}

		if ( empty( $parts ) ) {
			return __( 'nichts freigegeben', 'loheide-rights-management' );
		}

		return implode( ' · ', $parts );
	}

	/**
	 * Hinweistext, solange keine Rolle gewählt ist.
	 */
	protected function render_intro() {
		?>
		<div class="lrm-panel lrm-panel--hint">
			<h2><span class="dashicons dashicons-info-outline"></span> <?php esc_html_e( 'So wirkt die Beschränkung', 'loheide-rights-management' ); ?></h2>
			<ul class="lrm-steps">
				<li><?php esc_html_e( 'Je Inhaltstyp lässt sich festlegen: nichts, alles, einzeln ausgewählte Inhalte oder alles innerhalb bestimmter Kategorien.', 'loheide-rights-management' ); ?></li>
				<li><?php esc_html_e( 'Nicht freigegebene Inhalte erscheinen nicht in den Listen und sind auch über die Adresszeile gesperrt.', 'loheide-rights-management' ); ?></li>
				<li><?php esc_html_e( 'Die nötigen Fähigkeiten vergibt das Plugin automatisch und nimmt sie zurück, sobald die Beschränkung endet.', 'loheide-rights-management' ); ?></li>
				<li><?php esc_html_e( 'Benutzer mit Umgehungsrecht – im Regelfall Administratoren – bleiben davon unberührt.', 'loheide-rights-management' ); ?></li>
			</ul>
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

			<?php foreach ( LRM_Backend::managed_post_types() as $slug => $type_label ) : ?>
				<?php $this->render_type_panel( $slug, $type_label, LRM_Backend::type_config( $config, $slug ) ); ?>
			<?php endforeach; ?>

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

			<?php $this->render_menu_panel( $config ); ?>
			<?php $this->render_dashboard_panel( $config ); ?>

			<p class="lrm-submit">
				<?php submit_button( __( 'Rechte speichern', 'loheide-rights-management' ), 'primary', 'submit', false ); ?>
			</p>
		</form>
		<?php
	}

	/**
	 * Abschnitt für einen Inhaltstyp.
	 *
	 * @param string $slug  Inhaltstyp.
	 * @param string $label Bezeichnung.
	 * @param array  $type  Regel des Inhaltstyps.
	 */
	protected function render_type_panel( $slug, $label, $type ) {
		$field      = 'lrm_backend[types][' . $slug . ']';
		$id         = 'lrm-type-' . $slug;
		$taxonomies = LRM_Backend::taxonomies_for( $slug );
		$total      = (int) wp_count_posts( $slug )->publish + (int) wp_count_posts( $slug )->draft;
		?>
		<div class="lrm-panel lrm-typepanel" data-lrm-type="<?php echo esc_attr( $slug ); ?>">
			<div class="lrm-panel__head">
				<h2><?php echo esc_html( $label ); ?></h2>
				<p class="lrm-muted">
					<?php
					printf(
						/* translators: %d: Anzahl vorhandener Inhalte. */
						esc_html__( '%d Inhalte vorhanden', 'loheide-rights-management' ),
						(int) $total
					);
					?>
				</p>
			</div>
			<div class="lrm-panel__body">
				<div class="lrm-modes lrm-modes--compact">
					<?php
					$modes = array(
						'none'     => array( 'dashicons-minus', __( 'Nicht freigegeben', 'loheide-rights-management' ), __( 'Die Rolle sieht diesen Inhaltstyp nicht.', 'loheide-rights-management' ) ),
						'all'      => array( 'dashicons-yes', __( 'Alle Inhalte', 'loheide-rights-management' ), __( 'Alles von diesem Typ darf bearbeitet werden.', 'loheide-rights-management' ) ),
						'selected' => array( 'dashicons-list-view', __( 'Nur ausgewählte', 'loheide-rights-management' ), __( 'Einzeln zugewiesene Inhalte.', 'loheide-rights-management' ) ),
					);

					if ( $taxonomies ) {
						$modes['terms'] = array( 'dashicons-category', __( 'Nach Kategorie', 'loheide-rights-management' ), __( 'Alles innerhalb der gewählten Begriffe.', 'loheide-rights-management' ) );
					}

					foreach ( $modes as $value => $data ) :
						?>
						<label class="lrm-mode">
							<input type="radio" name="<?php echo esc_attr( $field ); ?>[mode]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $type['mode'], $value ); ?> data-lrm-mode="<?php echo esc_attr( $value ); ?>" />
							<span class="lrm-mode__inner">
								<span class="dashicons <?php echo esc_attr( $data[0] ); ?>"></span>
								<span class="lrm-mode__title"><?php echo esc_html( $data[1] ); ?></span>
								<span class="lrm-mode__desc"><?php echo esc_html( $data[2] ); ?></span>
							</span>
						</label>
					<?php endforeach; ?>
				</div>

				<div class="lrm-typeoptions" data-lrm-for="selected">
					<?php if ( $total > self::LIST_LIMIT ) : ?>
						<?php $this->render_item_search( $slug, $field, $type ); ?>
					<?php else : ?>
						<?php $this->render_item_list( $slug, $field, $type, $id ); ?>
					<?php endif; ?>
				</div>

				<?php if ( $taxonomies ) : ?>
					<div class="lrm-typeoptions" data-lrm-for="terms">
						<p class="lrm-field">
							<label for="<?php echo esc_attr( $id ); ?>-tax"><?php esc_html_e( 'Taxonomie', 'loheide-rights-management' ); ?></label>
							<select name="<?php echo esc_attr( $field ); ?>[taxonomy]" id="<?php echo esc_attr( $id ); ?>-tax" data-lrm-taxonomy>
								<?php foreach ( $taxonomies as $tax_slug => $tax_label ) : ?>
									<option value="<?php echo esc_attr( $tax_slug ); ?>" <?php selected( $type['taxonomy'] ? $type['taxonomy'] : 'category', $tax_slug ); ?>><?php echo esc_html( $tax_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>

						<?php
						$current_tax = $type['taxonomy'] ? $type['taxonomy'] : key( $taxonomies );

						foreach ( $taxonomies as $tax_slug => $tax_label ) :
							$terms = get_terms(
								array(
									'taxonomy'   => $tax_slug,
									'hide_empty' => false,
									'number'     => 300,
								)
							);
							?>
							<div class="lrm-termgroup" data-lrm-terms="<?php echo esc_attr( $tax_slug ); ?>" <?php echo $tax_slug === $current_tax ? '' : 'style="display:none"'; ?>>
								<?php if ( is_wp_error( $terms ) || empty( $terms ) ) : ?>
									<p class="lrm-muted"><?php esc_html_e( 'Keine Begriffe vorhanden.', 'loheide-rights-management' ); ?></p>
								<?php else : ?>
									<div class="lrm-roles">
										<?php foreach ( $terms as $term ) : ?>
											<label class="lrm-chip lrm-chip--allow">
												<input type="checkbox" name="<?php echo esc_attr( $field ); ?>[terms][]" value="<?php echo esc_attr( $term->term_id ); ?>" <?php checked( in_array( (int) $term->term_id, array_map( 'absint', $type['terms'] ), true ) ); ?> <?php disabled( $tax_slug !== $current_tax ); ?> />
												<span class="lrm-chip__label"><?php echo esc_html( $term->name ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>

						<label class="lrm-check lrm-check--spaced">
							<input type="checkbox" name="<?php echo esc_attr( $field ); ?>[force_term]" value="1" <?php checked( ! empty( $type['force_term'] ) ); ?> />
							<span><?php esc_html_e( 'Beim Speichern immer einen freigegebenen Begriff setzen', 'loheide-rights-management' ); ?></span>
						</label>
					</div>
				<?php endif; ?>

				<div class="lrm-typeoptions lrm-typeoptions--always" data-lrm-for="any">
					<div class="lrm-field--checks">
						<label class="lrm-check">
							<input type="checkbox" name="<?php echo esc_attr( $field ); ?>[create]" value="1" <?php checked( ! empty( $type['create'] ) ); ?> />
							<span><?php esc_html_e( 'Darf neue Inhalte anlegen', 'loheide-rights-management' ); ?></span>
						</label>
						<label class="lrm-check">
							<input type="checkbox" name="<?php echo esc_attr( $field ); ?>[delete]" value="1" <?php checked( ! empty( $type['delete'] ) ); ?> />
							<span><?php esc_html_e( 'Darf Inhalte löschen', 'loheide-rights-management' ); ?></span>
						</label>
						<label class="lrm-check">
							<input type="checkbox" name="<?php echo esc_attr( $field ); ?>[own_only]" value="1" <?php checked( ! empty( $type['own_only'] ) ); ?> />
							<span><?php esc_html_e( 'Nur selbst verfasste Inhalte', 'loheide-rights-management' ); ?></span>
						</label>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Auswahlliste der Inhalte.
	 *
	 * @param string $slug  Inhaltstyp.
	 * @param string $field Feldname.
	 * @param array  $type  Regel.
	 * @param string $id    Kennung.
	 */
	protected function render_item_list( $slug, $field, $type, $id ) {
		$items = get_posts(
			array(
				'post_type'      => $slug,
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => self::LIST_LIMIT,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'suppress_filters' => true,
			)
		);

		if ( empty( $items ) ) {
			echo '<p class="lrm-muted">' . esc_html__( 'Es sind noch keine Inhalte dieses Typs vorhanden.', 'loheide-rights-management' ) . '</p>';

			return;
		}
		?>
		<span class="lrm-searchbox lrm-searchbox--block">
			<span class="dashicons dashicons-search"></span>
			<input type="search" placeholder="<?php esc_attr_e( 'In der Liste suchen …', 'loheide-rights-management' ); ?>" data-lrm-filter="#<?php echo esc_attr( $id ); ?>-list" />
		</span>
		<div class="lrm-checklist-box" id="<?php echo esc_attr( $id ); ?>-list">
			<?php
			foreach ( $items as $item ) :
				$depth  = is_post_type_hierarchical( $slug ) ? count( get_post_ancestors( $item->ID ) ) : 0;
				$prefix = str_repeat( '— ', min( $depth, 4 ) );
				?>
				<label class="lrm-listitem">
					<input type="checkbox" name="<?php echo esc_attr( $field ); ?>[items][]" value="<?php echo esc_attr( $item->ID ); ?>" <?php checked( in_array( (int) $item->ID, array_map( 'absint', $type['items'] ), true ) ); ?> />
					<span><?php echo esc_html( $prefix . ( $item->post_title ? $item->post_title : __( '(ohne Titel)', 'loheide-rights-management' ) ) ); ?></span>
					<?php if ( 'publish' !== $item->post_status ) : ?>
						<em><?php echo esc_html( $item->post_status ); ?></em>
					<?php endif; ?>
				</label>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Suchfeld für große Bestände.
	 *
	 * @param string $slug  Inhaltstyp.
	 * @param string $field Feldname.
	 * @param array  $type  Regel.
	 */
	protected function render_item_search( $slug, $field, $type ) {
		?>
		<div class="lrm-itempicker" data-lrm-picker="<?php echo esc_attr( $slug ); ?>" data-lrm-field="<?php echo esc_attr( $field ); ?>">
			<p class="lrm-muted">
				<?php esc_html_e( 'Der Bestand ist zu groß für eine Liste. Suchen Sie die Inhalte, die zugewiesen werden sollen.', 'loheide-rights-management' ); ?>
			</p>
			<span class="lrm-searchbox lrm-searchbox--block">
				<span class="dashicons dashicons-search"></span>
				<input type="search" placeholder="<?php esc_attr_e( 'Titel suchen …', 'loheide-rights-management' ); ?>" data-lrm-search />
			</span>
			<div class="lrm-searchresults" data-lrm-results></div>
			<div class="lrm-chosen" data-lrm-chosen>
				<?php
				foreach ( array_map( 'absint', $type['items'] ) as $item_id ) :
					$item = get_post( $item_id );

					if ( ! $item ) {
						continue;
					}
					?>
					<span class="lrm-tag lrm-tag--allow lrm-tag--removable">
						<?php echo esc_html( $item->post_title ? $item->post_title : '#' . $item_id ); ?>
						<button type="button" class="lrm-tag__remove" aria-label="<?php esc_attr_e( 'Entfernen', 'loheide-rights-management' ); ?>">&times;</button>
						<input type="hidden" name="<?php echo esc_attr( $field ); ?>[items][]" value="<?php echo esc_attr( $item_id ); ?>" />
					</span>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Abschnitt der Menüpunkte.
	 *
	 * @param array $config Regel.
	 */
	protected function render_menu_panel( $config ) {
		$hidden = (array) $config['hidden_menus'];
		?>
		<div class="lrm-panel">
			<div class="lrm-panel__head">
				<h2><?php esc_html_e( 'Sichtbare Menüpunkte', 'loheide-rights-management' ); ?></h2>
				<p class="lrm-muted"><?php esc_html_e( 'Abgeschaltete Punkte verschwinden aus dem Menü und sind auch über die Adresszeile gesperrt.', 'loheide-rights-management' ); ?></p>
			</div>
			<div class="lrm-panel__body">
				<label class="lrm-switch lrm-switch--row">
					<input type="checkbox" name="lrm_backend[hide_new_menus]" value="1" <?php checked( ! empty( $config['hide_new_menus'] ) ); ?> />
					<span class="lrm-switch__track"><span class="lrm-switch__knob"></span></span>
					<span class="lrm-switch__label">
						<strong><?php esc_html_e( 'Später hinzukommende Menüpunkte ausblenden', 'loheide-rights-management' ); ?></strong>
						<em><?php esc_html_e( 'Wird ein neues Plugin installiert, bleibt dessen Menü für diese Rolle verborgen, bis Sie es hier freigeben.', 'loheide-rights-management' ); ?></em>
					</span>
				</label>

				<div class="lrm-menulist">
					<?php foreach ( LRM_Backend_Guard::collect_menus() as $menu ) : ?>
						<div class="lrm-menuitem">
							<label class="lrm-switch lrm-switch--compact">
								<input type="checkbox" name="lrm_backend[visible_menus][]" value="<?php echo esc_attr( $menu['key'] ); ?>" <?php checked( ! in_array( $menu['key'], $hidden, true ) ); ?> />
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
		<?php
	}

	/**
	 * Abschnitt der Dashboard-Bereiche.
	 *
	 * @param array $config Regel.
	 */
	protected function render_dashboard_panel( $config ) {
		$hidden  = (array) $config['hidden_widgets'];
		$widgets = LRM_Backend::known_widgets();
		?>
		<div class="lrm-panel">
			<div class="lrm-panel__head">
				<h2><?php esc_html_e( 'Bereiche auf dem Dashboard', 'loheide-rights-management' ); ?></h2>
				<p class="lrm-muted"><?php esc_html_e( 'Abgeschaltete Bereiche erscheinen für diese Rolle nicht mehr auf der Startseite des Backends.', 'loheide-rights-management' ); ?></p>
			</div>
			<div class="lrm-panel__body">
				<div class="lrm-roles">
					<?php foreach ( $widgets as $key => $title ) : ?>
						<label class="lrm-chip lrm-chip--allow">
							<input type="checkbox" name="lrm_backend[visible_widgets][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( ! in_array( $key, $hidden, true ) ); ?> />
							<span class="lrm-chip__label"><?php echo esc_html( $title ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
				<input type="hidden" name="lrm_backend[all_widgets]" value="<?php echo esc_attr( wp_json_encode( array_keys( $widgets ) ) ); ?>" />
			</div>
		</div>
		<?php
	}

	/**
	 * Alle bekannten Menüschlüssel.
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

	/* ------------------------------------------------------------------ *
	 * Speichern und Suche
	 * ------------------------------------------------------------------ */

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

		$input  = isset( $_POST['lrm_backend'] ) && is_array( $_POST['lrm_backend'] ) ? wp_unslash( $_POST['lrm_backend'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Felder werden einzeln bereinigt.
		$config = LRM_Backend::get( $role );

		$config['enabled']        = empty( $input['enabled'] ) ? 0 : 1;
		$config['allow_media']    = empty( $input['allow_media'] ) ? 0 : 1;
		$config['own_media_only'] = empty( $input['own_media_only'] ) ? 0 : 1;
		$config['hide_new_menus'] = empty( $input['hide_new_menus'] ) ? 0 : 1;
		$config['types']          = isset( $input['types'] ) ? (array) $input['types'] : array();

		// Aus den sichtbaren Punkten die verborgenen ableiten.
		$all_menus = isset( $input['all_menus'] ) ? json_decode( (string) $input['all_menus'], true ) : array();
		$visible   = isset( $input['visible_menus'] ) ? array_map( 'sanitize_text_field', (array) $input['visible_menus'] ) : array();

		if ( is_array( $all_menus ) && $all_menus ) {
			$all_menus               = array_map( 'sanitize_text_field', $all_menus );
			$config['hidden_menus']  = array_values( array_diff( $all_menus, $visible ) );
			$config['known_menus']   = $all_menus;
		}

		$all_widgets     = isset( $input['all_widgets'] ) ? json_decode( (string) $input['all_widgets'], true ) : array();
		$visible_widgets = isset( $input['visible_widgets'] ) ? array_map( 'sanitize_text_field', (array) $input['visible_widgets'] ) : array();

		if ( is_array( $all_widgets ) && $all_widgets ) {
			$all_widgets               = array_map( 'sanitize_text_field', $all_widgets );
			$config['hidden_widgets']  = array_values( array_diff( $all_widgets, $visible_widgets ) );
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

	/**
	 * Inhalte für die Zuweisung suchen.
	 */
	public function ajax_search_items() {
		check_ajax_referer( 'lrm_search_items', 'nonce' );

		if ( ! current_user_can( LRM_Roles::CAP_MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'Fehlende Berechtigung.', 'loheide-rights-management' ) ), 403 );
		}

		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		$term      = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

		if ( ! $post_type || ! array_key_exists( $post_type, LRM_Backend::managed_post_types() ) ) {
			wp_send_json_error( array( 'message' => __( 'Unbekannter Inhaltstyp.', 'loheide-rights-management' ) ), 400 );
		}

		$items = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page'   => 20,
				's'                => $term,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);

		$result = array();

		foreach ( $items as $item ) {
			$result[] = array(
				'id'    => (int) $item->ID,
				'title' => $item->post_title ? $item->post_title : sprintf( '#%d', $item->ID ),
			);
		}

		wp_send_json_success( $result );
	}
}
