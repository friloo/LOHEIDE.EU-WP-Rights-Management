<?php
/**
 * Berechtigungsfeld im Editor.
 *
 * @package LOHEIDE_Rights_Management
 */

defined( 'ABSPATH' ) || exit;

/**
 * Metabox, Speicherlogik, Sammelbearbeitung und Statusspalte.
 */
class LRM_Metabox {

	/**
	 * Nonce-Feld.
	 */
	const NONCE = 'lrm_meta_nonce';

	/**
	 * Hooks registrieren.
	 */
	public function hooks() {
		add_action( 'add_meta_boxes', array( $this, 'register' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );

		add_action( 'admin_init', array( $this, 'register_columns' ) );
		add_action( 'bulk_edit_custom_box', array( $this, 'render_bulk_edit' ), 10, 2 );
	}

	/**
	 * Metabox registrieren.
	 *
	 * @param string $post_type Inhaltstyp.
	 */
	public function register( $post_type ) {
		if ( ! in_array( $post_type, LRM_Settings::protected_post_types(), true ) ) {
			return;
		}

		if ( ! current_user_can( LRM_Roles::CAP_MANAGE ) ) {
			return;
		}

		add_meta_box(
			'lrm-permissions',
			__( 'Zugriffsrechte', 'loheide-rights-management' ),
			array( $this, 'render' ),
			$post_type,
			'normal',
			'high'
		);
	}

	/**
	 * Spalten und Sammelbearbeitung registrieren.
	 */
	public function register_columns() {
		foreach ( LRM_Settings::protected_post_types() as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
		}
	}

	/**
	 * Metabox ausgeben.
	 *
	 * @param WP_Post $post Inhalt.
	 */
	public function render( $post ) {
		$rule      = LRM_Access::get_rule( $post->ID );
		$effective = LRM_Access::get_effective_rule( $post->ID );
		$roles     = LRM_Roles::all();

		wp_nonce_field( 'lrm_save_meta', self::NONCE );
		?>
		<div class="lrm-box" id="lrm-box">
			<div class="lrm-box__head">
				<label class="lrm-switch">
					<input type="checkbox" name="lrm[enabled]" value="1" id="lrm-enabled" <?php checked( $rule->enabled ); ?> />
					<span class="lrm-switch__track"><span class="lrm-switch__knob"></span></span>
					<span class="lrm-switch__label">
						<strong><?php esc_html_e( 'Zugriffsrechte für diesen Inhalt aktivieren', 'loheide-rights-management' ); ?></strong>
						<em><?php esc_html_e( 'Ist die Option aus, bleibt der Inhalt frei zugänglich – sofern keine Regel von einer übergeordneten Seite geerbt wird.', 'loheide-rights-management' ); ?></em>
					</span>
				</label>
			</div>

			<?php if ( $effective->inherited ) : ?>
				<div class="lrm-notice lrm-notice--info">
					<span class="dashicons dashicons-networking"></span>
					<div>
						<strong><?php esc_html_e( 'Geerbte Regel aktiv', 'loheide-rights-management' ); ?></strong>
						<p>
							<?php
							printf(
								/* translators: 1: Titel der übergeordneten Seite, 2: Beschreibung der Regel. */
								esc_html__( 'Von „%1$s“ wird übernommen: %2$s', 'loheide-rights-management' ),
								esc_html( get_the_title( $effective->source_id ) ),
								esc_html( $effective->describe() )
							);
							?>
						</p>
					</div>
				</div>
			<?php endif; ?>

			<div class="lrm-box__body" id="lrm-body">
				<div class="lrm-section">
					<h4 class="lrm-section__title"><?php esc_html_e( '1. Grundsichtbarkeit', 'loheide-rights-management' ); ?></h4>
					<div class="lrm-modes">
						<?php
						$modes = array(
							LRM_Rule::VISIBILITY_PUBLIC    => array(
								'icon'  => 'dashicons-visibility',
								'title' => __( 'Öffentlich', 'loheide-rights-management' ),
								'desc'  => __( 'Für alle sichtbar. Gesperrte Rollen greifen trotzdem.', 'loheide-rights-management' ),
							),
							LRM_Rule::VISIBILITY_LOGGED_IN => array(
								'icon'  => 'dashicons-admin-users',
								'title' => __( 'Nur angemeldet', 'loheide-rights-management' ),
								'desc'  => __( 'Sichtbar, sobald ein Benutzer angemeldet ist – unabhängig von der Rolle.', 'loheide-rights-management' ),
							),
							LRM_Rule::VISIBILITY_ROLES     => array(
								'icon'  => 'dashicons-groups',
								'title' => __( 'Nur ausgewählte Rollen', 'loheide-rights-management' ),
								'desc'  => __( 'Nur die unten freigegebenen Rollen erhalten Zugriff.', 'loheide-rights-management' ),
							),
						);

						foreach ( $modes as $value => $mode ) :
							?>
							<label class="lrm-mode">
								<input type="radio" name="lrm[visibility]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $rule->visibility, $value ); ?> />
								<span class="lrm-mode__inner">
									<span class="dashicons <?php echo esc_attr( $mode['icon'] ); ?>"></span>
									<span class="lrm-mode__title"><?php echo esc_html( $mode['title'] ); ?></span>
									<span class="lrm-mode__desc"><?php echo esc_html( $mode['desc'] ); ?></span>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="lrm-section">
					<h4 class="lrm-section__title"><?php esc_html_e( '2. Rollen', 'loheide-rights-management' ); ?></h4>
					<div class="lrm-grid">
						<div class="lrm-card lrm-card--allow" id="lrm-card-allow">
							<div class="lrm-card__head">
								<span class="lrm-card__icon dashicons dashicons-yes-alt"></span>
								<div>
									<h5><?php esc_html_e( 'Freigegebene Rollen', 'loheide-rights-management' ); ?></h5>
									<p><?php esc_html_e( 'Wirkt im Modus „Nur ausgewählte Rollen“.', 'loheide-rights-management' ); ?></p>
								</div>
								<button type="button" class="lrm-linkbtn" data-lrm-toggle-all="allow"><?php esc_html_e( 'Alle', 'loheide-rights-management' ); ?></button>
							</div>
							<div class="lrm-roles">
								<?php foreach ( $roles as $key => $label ) : ?>
									<label class="lrm-chip lrm-chip--allow">
										<input type="checkbox" name="lrm[allowed][]" value="<?php echo esc_attr( $key ); ?>" data-role="<?php echo esc_attr( $key ); ?>" data-list="allow" <?php checked( in_array( $key, $rule->allowed, true ) ); ?> />
										<span class="lrm-chip__label"><?php echo esc_html( $label ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>

						<div class="lrm-card lrm-card--deny">
							<div class="lrm-card__head">
								<span class="lrm-card__icon dashicons dashicons-dismiss"></span>
								<div>
									<h5><?php esc_html_e( 'Gesperrte Rollen', 'loheide-rights-management' ); ?></h5>
									<p><?php esc_html_e( 'Vorrang vor allem anderen – unabhängig vom Modus.', 'loheide-rights-management' ); ?></p>
								</div>
								<button type="button" class="lrm-linkbtn" data-lrm-toggle-all="deny"><?php esc_html_e( 'Alle', 'loheide-rights-management' ); ?></button>
							</div>
							<div class="lrm-roles">
								<?php foreach ( $roles as $key => $label ) : ?>
									<label class="lrm-chip lrm-chip--deny">
										<input type="checkbox" name="lrm[denied][]" value="<?php echo esc_attr( $key ); ?>" data-role="<?php echo esc_attr( $key ); ?>" data-list="deny" <?php checked( in_array( $key, $rule->denied, true ) ); ?> />
										<span class="lrm-chip__label"><?php echo esc_html( $label ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					</div>

					<div class="lrm-notice lrm-notice--warning lrm-hidden" id="lrm-conflict">
						<span class="dashicons dashicons-warning"></span>
						<div>
							<strong><?php esc_html_e( 'Regelkonflikt – das Verbot gewinnt', 'loheide-rights-management' ); ?></strong>
							<p id="lrm-conflict-text"></p>
						</div>
					</div>

					<p class="lrm-summary" id="lrm-summary"></p>
				</div>

				<div class="lrm-section">
					<h4 class="lrm-section__title"><?php esc_html_e( '3. Verhalten bei fehlendem Zugriff', 'loheide-rights-management' ); ?></h4>
					<div class="lrm-fields">
						<p class="lrm-field">
							<label for="lrm-action"><?php esc_html_e( 'Reaktion', 'loheide-rights-management' ); ?></label>
							<select name="lrm[action]" id="lrm-action">
								<?php
								$actions = array(
									'inherit'  => sprintf(
										/* translators: %s: Bezeichnung der globalen Aktion. */
										__( 'Globale Einstellung verwenden (%s)', 'loheide-rights-management' ),
										self::action_label( LRM_Settings::get( 'denied_action' ) )
									),
									'message'  => self::action_label( 'message' ),
									'login'    => self::action_label( 'login' ),
									'404'      => self::action_label( '404' ),
									'redirect' => self::action_label( 'redirect' ),
								);

								foreach ( $actions as $value => $label ) :
									?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $rule->action, $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
						<p class="lrm-field" id="lrm-field-redirect">
							<label for="lrm-redirect"><?php esc_html_e( 'Ziel der Weiterleitung', 'loheide-rights-management' ); ?></label>
							<input type="url" class="widefat" name="lrm[redirect_url]" id="lrm-redirect" value="<?php echo esc_attr( $rule->redirect_url ); ?>" placeholder="https://" />
						</p>
						<p class="lrm-field lrm-field--full" id="lrm-field-message">
							<label for="lrm-message"><?php esc_html_e( 'Eigener Hinweistext', 'loheide-rights-management' ); ?></label>
							<textarea class="widefat" rows="3" name="lrm[message]" id="lrm-message" placeholder="<?php echo esc_attr( LRM_Settings::get( 'denied_message' ) ); ?>"><?php echo esc_textarea( $rule->message ); ?></textarea>
							<span class="lrm-hint"><?php esc_html_e( 'Leer lassen, um den global hinterlegten Text zu verwenden.', 'loheide-rights-management' ); ?></span>
						</p>
					</div>
				</div>

				<div class="lrm-section">
					<h4 class="lrm-section__title"><?php esc_html_e( '4. Weitere Optionen', 'loheide-rights-management' ); ?></h4>
					<div class="lrm-fields">
						<p class="lrm-field">
							<label for="lrm-hide"><?php esc_html_e( 'In Menüs und Listen verbergen', 'loheide-rights-management' ); ?></label>
							<select name="lrm[hide]" id="lrm-hide">
								<option value="inherit" <?php selected( $rule->hide, 'inherit' ); ?>>
									<?php
									echo esc_html(
										LRM_Settings::get( 'hide_from_menus' )
											? __( 'Globale Einstellung (verbergen)', 'loheide-rights-management' )
											: __( 'Globale Einstellung (anzeigen)', 'loheide-rights-management' )
									);
									?>
								</option>
								<option value="yes" <?php selected( $rule->hide, 'yes' ); ?>><?php esc_html_e( 'Verbergen', 'loheide-rights-management' ); ?></option>
								<option value="no" <?php selected( $rule->hide, 'no' ); ?>><?php esc_html_e( 'Anzeigen', 'loheide-rights-management' ); ?></option>
							</select>
						</p>
						<p class="lrm-field lrm-field--checks">
							<label class="lrm-check">
								<input type="checkbox" name="lrm[inherit]" value="1" <?php checked( $rule->inherit ); ?> />
								<span><?php esc_html_e( 'Regeln übergeordneter Seiten übernehmen', 'loheide-rights-management' ); ?></span>
							</label>
							<label class="lrm-check">
								<input type="checkbox" name="lrm[propagate]" value="1" <?php checked( $rule->propagate ); ?> />
								<span><?php esc_html_e( 'Diese Regel auch für Unterseiten anwenden', 'loheide-rights-management' ); ?></span>
							</label>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Bezeichnung einer Aktion.
	 *
	 * @param string $action Aktion.
	 * @return string
	 */
	public static function action_label( $action ) {
		switch ( $action ) {
			case 'login':
				return __( 'Zur Anmeldung weiterleiten', 'loheide-rights-management' );
			case '404':
				return __( 'Seite nicht gefunden (404)', 'loheide-rights-management' );
			case 'redirect':
				return __( 'Zu eigener Adresse weiterleiten', 'loheide-rights-management' );
			case 'message':
			default:
				return __( 'Hinweis statt Inhalt anzeigen', 'loheide-rights-management' );
		}
	}

	/**
	 * Regel speichern.
	 *
	 * @param int     $post_id Inhalts-ID.
	 * @param WP_Post $post    Inhalt.
	 */
	public function save( $post_id, $post ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, LRM_Settings::protected_post_types(), true ) ) {
			return;
		}

		if ( ! current_user_can( LRM_Roles::CAP_MANAGE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Sammelbearbeitung.
		if ( isset( $_REQUEST['bulk_edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce wird von WordPress geprüft.
			$this->save_bulk( $post_id );
			return;
		}

		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), 'lrm_save_meta' ) ) {
			return;
		}

		$input = isset( $_POST['lrm'] ) && is_array( $_POST['lrm'] ) ? wp_unslash( $_POST['lrm'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Felder werden einzeln bereinigt.

		$enabled = ! empty( $input['enabled'] );

		update_post_meta( $post_id, LRM_Rule::META_ENABLED, $enabled ? 1 : 0 );
		update_post_meta( $post_id, LRM_Rule::META_VISIBILITY, LRM_Rule::sanitize_visibility( isset( $input['visibility'] ) ? $input['visibility'] : '' ) );
		update_post_meta( $post_id, LRM_Rule::META_ALLOWED, LRM_Roles::sanitize_list( isset( $input['allowed'] ) ? $input['allowed'] : array() ) );
		update_post_meta( $post_id, LRM_Rule::META_DENIED, LRM_Roles::sanitize_list( isset( $input['denied'] ) ? $input['denied'] : array() ) );
		update_post_meta( $post_id, LRM_Rule::META_ACTION, LRM_Rule::sanitize_action( isset( $input['action'] ) ? $input['action'] : '' ) );
		update_post_meta( $post_id, LRM_Rule::META_HIDE, LRM_Rule::sanitize_tristate( isset( $input['hide'] ) ? $input['hide'] : '' ) );
		update_post_meta( $post_id, LRM_Rule::META_REDIRECT, isset( $input['redirect_url'] ) ? esc_url_raw( trim( $input['redirect_url'] ) ) : '' );
		update_post_meta( $post_id, LRM_Rule::META_MESSAGE, isset( $input['message'] ) ? wp_kses_post( $input['message'] ) : '' );
		update_post_meta( $post_id, LRM_Rule::META_INHERIT, empty( $input['inherit'] ) ? 0 : 1 );
		update_post_meta( $post_id, LRM_Rule::META_PROPAGATE, empty( $input['propagate'] ) ? 0 : 1 );

		LRM_Access::flush( $post_id );
	}

	/**
	 * Felder der Sammelbearbeitung ausgeben.
	 *
	 * @param string $column_name Spaltenname.
	 * @param string $post_type   Inhaltstyp.
	 */
	public function render_bulk_edit( $column_name, $post_type ) {
		if ( 'lrm_access' !== $column_name ) {
			return;
		}

		if ( ! in_array( $post_type, LRM_Settings::protected_post_types(), true ) || ! current_user_can( LRM_Roles::CAP_MANAGE ) ) {
			return;
		}

		$roles = LRM_Roles::all();
		?>
		<fieldset class="inline-edit-col-right lrm-bulk">
			<div class="inline-edit-col">
				<span class="title"><?php esc_html_e( 'Zugriffsrechte', 'loheide-rights-management' ); ?></span>
				<?php wp_nonce_field( 'lrm_bulk_edit', 'lrm_bulk_nonce' ); ?>
				<label class="alignleft">
					<span class="title"><?php esc_html_e( 'Modus', 'loheide-rights-management' ); ?></span>
					<select name="lrm_bulk[mode]">
						<option value=""><?php esc_html_e( '— Nicht ändern —', 'loheide-rights-management' ); ?></option>
						<option value="off"><?php esc_html_e( 'Einschränkung entfernen', 'loheide-rights-management' ); ?></option>
						<option value="public"><?php esc_html_e( 'Öffentlich', 'loheide-rights-management' ); ?></option>
						<option value="logged_in"><?php esc_html_e( 'Nur angemeldet', 'loheide-rights-management' ); ?></option>
						<option value="roles"><?php esc_html_e( 'Nur ausgewählte Rollen', 'loheide-rights-management' ); ?></option>
					</select>
				</label>
				<div class="lrm-bulk__roles">
					<p class="lrm-bulk__legend"><?php esc_html_e( 'Freigegebene Rollen setzen', 'loheide-rights-management' ); ?></p>
					<?php foreach ( $roles as $key => $label ) : ?>
						<label class="lrm-bulk__chip">
							<input type="checkbox" name="lrm_bulk[allowed][]" value="<?php echo esc_attr( $key ); ?>" />
							<span><?php echo esc_html( $label ); ?></span>
						</label>
					<?php endforeach; ?>
					<p class="lrm-bulk__legend"><?php esc_html_e( 'Rollen sperren (Vorrang)', 'loheide-rights-management' ); ?></p>
					<?php foreach ( $roles as $key => $label ) : ?>
						<label class="lrm-bulk__chip lrm-bulk__chip--deny">
							<input type="checkbox" name="lrm_bulk[denied][]" value="<?php echo esc_attr( $key ); ?>" />
							<span><?php echo esc_html( $label ); ?></span>
						</label>
					<?php endforeach; ?>
					<label class="lrm-bulk__mode">
						<input type="checkbox" name="lrm_bulk[replace_roles]" value="1" />
						<span><?php esc_html_e( 'Bestehende Rollenauswahl ersetzen statt ergänzen', 'loheide-rights-management' ); ?></span>
					</label>
				</div>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Sammelbearbeitung speichern.
	 *
	 * @param int $post_id Inhalts-ID.
	 */
	protected function save_bulk( $post_id ) {
		if ( ! isset( $_REQUEST['lrm_bulk_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['lrm_bulk_nonce'] ) ), 'lrm_bulk_edit' ) ) {
			return;
		}

		$input = isset( $_REQUEST['lrm_bulk'] ) && is_array( $_REQUEST['lrm_bulk'] ) ? wp_unslash( $_REQUEST['lrm_bulk'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Felder werden einzeln bereinigt.

		if ( empty( $input ) ) {
			return;
		}

		$mode    = isset( $input['mode'] ) ? sanitize_key( $input['mode'] ) : '';
		$allowed = LRM_Roles::sanitize_list( isset( $input['allowed'] ) ? $input['allowed'] : array() );
		$denied  = LRM_Roles::sanitize_list( isset( $input['denied'] ) ? $input['denied'] : array() );
		$replace = ! empty( $input['replace_roles'] );

		if ( '' === $mode && empty( $allowed ) && empty( $denied ) ) {
			return;
		}

		if ( 'off' === $mode ) {
			update_post_meta( $post_id, LRM_Rule::META_ENABLED, 0 );
			LRM_Access::flush( $post_id );
			return;
		}

		if ( in_array( $mode, array( 'public', 'logged_in', 'roles' ), true ) ) {
			update_post_meta( $post_id, LRM_Rule::META_ENABLED, 1 );
			update_post_meta( $post_id, LRM_Rule::META_VISIBILITY, $mode );
		} elseif ( ! empty( $allowed ) || ! empty( $denied ) ) {
			// Rollen ohne Moduswechsel: Regel dennoch aktivieren.
			update_post_meta( $post_id, LRM_Rule::META_ENABLED, 1 );
		}

		if ( ! empty( $allowed ) || $replace ) {
			$current = $replace ? array() : LRM_Roles::sanitize_list( get_post_meta( $post_id, LRM_Rule::META_ALLOWED, true ) );
			update_post_meta( $post_id, LRM_Rule::META_ALLOWED, array_values( array_unique( array_merge( $current, $allowed ) ) ) );
		}

		if ( ! empty( $denied ) || $replace ) {
			$current = $replace ? array() : LRM_Roles::sanitize_list( get_post_meta( $post_id, LRM_Rule::META_DENIED, true ) );
			update_post_meta( $post_id, LRM_Rule::META_DENIED, array_values( array_unique( array_merge( $current, $denied ) ) ) );
		}

		LRM_Access::flush( $post_id );
	}

	/**
	 * Statusspalte anmelden.
	 *
	 * @param array $columns Spalten.
	 * @return array
	 */
	public function add_column( $columns ) {
		$new = array();

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;

			if ( 'title' === $key ) {
				$new['lrm_access'] = __( 'Zugriff', 'loheide-rights-management' );
			}
		}

		if ( ! isset( $new['lrm_access'] ) ) {
			$new['lrm_access'] = __( 'Zugriff', 'loheide-rights-management' );
		}

		return $new;
	}

	/**
	 * Inhalt der Statusspalte.
	 *
	 * @param string $column  Spaltenname.
	 * @param int    $post_id Inhalts-ID.
	 */
	public function render_column( $column, $post_id ) {
		if ( 'lrm_access' !== $column ) {
			return;
		}

		echo LRM_Admin::render_status_badge( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ausgabe ist bereits maskiert.
	}
}
