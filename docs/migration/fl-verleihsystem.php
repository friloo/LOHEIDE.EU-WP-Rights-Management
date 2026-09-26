<?php
/**
 * Plugin Name: FL-Verleihsystem
 * Description: Ein System zum Verleihen von Objekten im Unternehmen.
 * Version:     1.2
 * Author:      Friederich Loheide
 * Author URI:  http://loheide.eu
 *
 * Geändert gegenüber Fassung 1.1:
 *
 * Die Rechtevergabe lief bei „admin_init" – da steht das Verwaltungsmenü
 * bereits. Beim ersten Aufruf nach der Umstellung fehlte der Punkt „Objekte"
 * deshalb, und war die Versionsnummer einmal gespeichert, wurde es nicht mehr
 * nachgeholt. Jetzt läuft die Vergabe früh bei „init", die Versionsnummer wird
 * nur nach erfolgreicher Vergabe gesetzt, der angemeldete Benutzer erhält seine
 * Rechte sofort – und Administratoren behalten die Objekt-Rechte in jedem Fall.
 *
 * Geändert gegenüber Fassung 1.0:
 *
 * Der Inhaltstyp „Objekt" nutzte bisher die Rechte gewöhnlicher Beiträge
 * (edit_posts und so fort). Damit ließ er sich nicht getrennt vergeben: Wer
 * Objekte bearbeiten durfte, durfte auch Beiträge – und umgekehrt. Jetzt hat
 * er eigene Rechte (edit_bv_objekte …), die sich einzeln zuweisen lassen,
 * etwa über „LOHEIDE.EU WP Rights Management".
 *
 * An den gespeicherten Daten ändert sich nichts: Die Objekte, ihre Bilder und
 * die Felder _bv_verliehen und _bv_verleihort bleiben unverändert. Damit nach
 * der Umstellung niemand ausgesperrt ist, erhält die Rolle Administrator die
 * neuen Rechte automatisch.
 *
 * @package FL_Verleihsystem
 */

// Sicherheitscheck: Direkter Zugriff auf das Plugin verhindern.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BV_CAPS_VERSION', '1.2' );

/* -------------------------------------------------------------------------
 * Rechte des Inhaltstyps
 * ---------------------------------------------------------------------- */

/**
 * Alle Rechte, die zum Inhaltstyp „Objekt" gehören.
 *
 * @return array
 */
function bv_get_capabilities() {
	return array(
		// Einzelne Objekte.
		'edit_bv_objekt',
		'read_bv_objekt',
		'delete_bv_objekt',
		// Alle Objekte.
		'edit_bv_objekte',
		'edit_others_bv_objekte',
		'edit_private_bv_objekte',
		'edit_published_bv_objekte',
		'publish_bv_objekte',
		'read_private_bv_objekte',
		'delete_bv_objekte',
		'delete_private_bv_objekte',
		'delete_published_bv_objekte',
		'delete_others_bv_objekte',
	);
}

/**
 * Der Rolle Administrator die Rechte geben.
 *
 * Ohne diesen Schritt sähen Administratoren nach der Umstellung keine Objekte
 * mehr – die Inhalte wären zwar vorhanden, aber nicht mehr erreichbar.
 */
function bv_grant_capabilities() {
	$role = get_role( 'administrator' );

	if ( ! $role ) {
		return false;
	}

	foreach ( bv_get_capabilities() as $cap ) {
		$role->add_cap( $cap );
	}

	// Der angemeldete Benutzer trägt seine Rechte als Kopie mit sich. Ohne
	// diesen Schritt griffe die Vergabe erst beim nächsten Seitenaufruf – das
	// Menü „Objekte" wäre einmal verschwunden.
	$user = wp_get_current_user();

	if ( $user && $user->ID && method_exists( $user, 'for_site' ) ) {
		$user->for_site( get_current_blog_id() );
	}

	return true;
}

/**
 * Rechte bei der Aktivierung vergeben.
 */
register_activation_hook( __FILE__, 'bv_grant_capabilities' );

/**
 * Rechte auch dann vergeben, wenn das Plugin bereits aktiv war.
 *
 * Bei einer Aktualisierung über FTP läuft der Aktivierungs-Hook nicht. Die
 * gespeicherte Version sorgt dafür, dass der Schritt genau einmal ausgeführt
 * wird – aber erst dann, wenn er auch geglückt ist. Der Aufruf hängt an „init"
 * und nicht an „admin_init": Das Verwaltungsmenü ist zu diesem Zeitpunkt noch
 * nicht gebaut, sonst käme die Vergabe für den laufenden Aufruf zu spät.
 */
function bv_maybe_upgrade() {
	if ( get_option( 'bv_caps_version' ) === BV_CAPS_VERSION ) {
		return;
	}

	if ( bv_grant_capabilities() ) {
		update_option( 'bv_caps_version', BV_CAPS_VERSION );
	}
}
add_action( 'init', 'bv_maybe_upgrade', 0 );

/**
 * Administratoren behalten die Objekt-Rechte in jedem Fall.
 *
 * Ein Sicherheitsnetz gegen den Fall, dass die Rollenvergabe einmal nicht
 * greift – etwa weil die Rolle „administrator" umbenannt oder von einem
 * anderen Plugin überschrieben wurde. Wer die Website verwalten darf
 * (manage_options), sieht und bearbeitet auch die Objekte. Damit lässt sich
 * niemand versehentlich selbst aussperren.
 *
 * @param array $allcaps Rechte des Benutzers.
 * @return array
 */
function bv_admin_capabilities( $allcaps ) {
	if ( empty( $allcaps['manage_options'] ) ) {
		return $allcaps;
	}

	foreach ( bv_get_capabilities() as $cap ) {
		if ( empty( $allcaps[ $cap ] ) ) {
			$allcaps[ $cap ] = true;
		}
	}

	return $allcaps;
}
add_filter( 'user_has_cap', 'bv_admin_capabilities' );

/* -------------------------------------------------------------------------
 * Inhaltstyp
 * ---------------------------------------------------------------------- */

/**
 * Inhaltstyp „Objekt" registrieren.
 */
function bv_register_custom_post_type() {
	$labels = array(
		'name'           => 'Objekte',
		'singular_name'  => 'Objekt',
		'menu_name'      => 'Objekte',
		'name_admin_bar' => 'Objekt',
		'add_new'        => 'Neues Objekt hinzufügen',
		'add_new_item'   => 'Neues Objekt hinzufügen',
		'edit_item'      => 'Objekt bearbeiten',
		'new_item'       => 'Neues Objekt',
		'view_item'      => 'Objekt ansehen',
		'all_items'      => 'Alle Objekte',
		'search_items'   => 'Objekte durchsuchen',
	);

	$args = array(
		'labels'          => $labels,
		'public'          => true,
		'has_archive'     => true,
		'supports'        => array( 'title', 'editor', 'thumbnail' ),
		'show_in_rest'    => true,
		'menu_icon'       => 'dashicons-images-alt2',

		// Eigene Rechte statt der Rechte gewöhnlicher Beiträge. Erst dadurch
		// lässt sich der Zugriff auf Objekte getrennt vergeben.
		'capability_type' => array( 'bv_objekt', 'bv_objekte' ),
		'map_meta_cap'    => true,
	);

	register_post_type( 'bv_objekt', $args );
}
add_action( 'init', 'bv_register_custom_post_type' );

/* -------------------------------------------------------------------------
 * Verleihstatus
 * ---------------------------------------------------------------------- */

/**
 * Metabox für Verleihstatus und Ort hinzufügen.
 */
function bv_add_meta_boxes() {
	add_meta_box(
		'bv_verleihstatus',
		'Verleihstatus',
		'bv_render_verleihstatus_meta_box',
		'bv_objekt',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'bv_add_meta_boxes' );

/**
 * Inhalt der Metabox.
 *
 * @param WP_Post $post Objekt.
 */
function bv_render_verleihstatus_meta_box( $post ) {
	$verliehen  = get_post_meta( $post->ID, '_bv_verliehen', true );
	$verleihort = get_post_meta( $post->ID, '_bv_verleihort', true );

	wp_nonce_field( 'bv_save_verleihstatus', 'bv_verleihstatus_nonce' );
	?>
	<p>
		<label for="bv_verliehen">Verliehen:</label>
		<select name="bv_verliehen" id="bv_verliehen">
			<option value="nein" <?php selected( $verliehen, 'nein' ); ?>>Nein</option>
			<option value="ja" <?php selected( $verliehen, 'ja' ); ?>>Ja</option>
		</select>
	</p>
	<p>
		<label for="bv_verleihort">Verleihort:</label>
		<input type="text" name="bv_verleihort" id="bv_verleihort" value="<?php echo esc_attr( $verleihort ); ?>" />
	</p>
	<?php
}

/**
 * Metadaten speichern.
 *
 * @param int $post_id Objekt-ID.
 */
function bv_save_verleihstatus( $post_id ) {
	if ( ! isset( $_POST['bv_verleihstatus_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bv_verleihstatus_nonce'] ) ), 'bv_save_verleihstatus' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// Ohne diese Prüfung könnte jeder angemeldete Benutzer mit gültigem
	// Formular den Verleihstatus fremder Objekte ändern.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['bv_verliehen'] ) ) {
		$verliehen = sanitize_text_field( wp_unslash( $_POST['bv_verliehen'] ) );
		update_post_meta( $post_id, '_bv_verliehen', 'ja' === $verliehen ? 'ja' : 'nein' );
	}

	if ( isset( $_POST['bv_verleihort'] ) ) {
		update_post_meta( $post_id, '_bv_verleihort', sanitize_text_field( wp_unslash( $_POST['bv_verleihort'] ) ) );
	}
}
add_action( 'save_post', 'bv_save_verleihstatus' );

/* -------------------------------------------------------------------------
 * Anzeige im Frontend
 * ---------------------------------------------------------------------- */

/**
 * Shortcode [bv_objekte]: Übersicht der Objekte mit Filter.
 *
 * @param array $atts Attribute.
 * @return string
 */
function bv_render_objekte( $atts ) {
	unset( $atts );

	// Filterwert aus der Adresse holen.
	$filter_verliehen = isset( $_GET['verliehen'] ) ? sanitize_text_field( wp_unslash( $_GET['verliehen'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( ! in_array( $filter_verliehen, array( '', 'ja', 'nein' ), true ) ) {
		$filter_verliehen = '';
	}

	$query   = new WP_Query(
		array(
			'post_type'      => 'bv_objekt',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		)
	);
	$objekte = array();

	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			$verliehen = get_post_meta( get_the_ID(), '_bv_verliehen', true );

			if ( '' === $filter_verliehen || $verliehen === $filter_verliehen ) {
				$objekte[] = array(
					'ID'         => get_the_ID(),
					'title'      => get_the_title(),
					'content'    => apply_filters( 'the_content', get_post_field( 'post_content', get_the_ID() ) ),
					'verliehen'  => $verliehen,
					'verleihort' => get_post_meta( get_the_ID(), '_bv_verleihort', true ),
					'thumbnail'  => has_post_thumbnail( get_the_ID() ) ? wp_get_attachment_image_src( get_post_thumbnail_id( get_the_ID() ), 'full' ) : null,
				);
			}
		}

		wp_reset_postdata();
	}

	// Nicht verliehene Objekte zuerst.
	usort(
		$objekte,
		function ( $a, $b ) {
			return strcmp( $b['verliehen'], $a['verliehen'] );
		}
	);

	$output  = '<form method="get" id="bv-filter-form">';
	$output .= '<label for="verliehen">Filtern nach Verleihstatus:</label> ';
	$output .= '<select name="verliehen" id="verliehen" onchange="document.getElementById(\'bv-filter-form\').submit();">';
	$output .= '<option value="">Alle</option>';
	$output .= '<option value="nein"' . selected( $filter_verliehen, 'nein', false ) . '>Nicht verliehen</option>';
	$output .= '<option value="ja"' . selected( $filter_verliehen, 'ja', false ) . '>Verliehen</option>';
	$output .= '</select>';
	$output .= '</form>';

	if ( ! empty( $objekte ) ) {
		$output .= '<div class="bv-objekte-grid">';

		foreach ( $objekte as $objekt ) {
			$output .= '<div class="bv-objekt">';

			if ( $objekt['thumbnail'] ) {
				$output .= '<div class="bv-objekt-bild">';
				$output .= '<a href="' . esc_url( $objekt['thumbnail'][0] ) . '" class="lightbox-link">';
				$output .= get_the_post_thumbnail( $objekt['ID'], 'medium' );
				$output .= '</a>';
				$output .= '<center><a href="mailto:s.bulenda@ledderwerkstaetten.de&#59;l.ewering@ledderwerkstaetten.de?subject=Anfrage%20Kuenstlerteam-Kipp%20-%20' . rawurlencode( $objekt['title'] ) . '&amp;body=Hallo%20zusammen,%0D%0A%0D%0Aich%20möchte%20gerne%20das%20Bild%20-' . rawurlencode( $objekt['title'] ) . '-%20anfragen." class="bv-anfrage-button">Bild anfragen</a></center>';
				$output .= '</div>';
			}

			$output .= '<div class="bv-objekt-infos">';
			$output .= '<h2>' . esc_html( $objekt['title'] ) . '</h2>';
			$output .= $objekt['content'];

			if ( ! empty( $objekt['verleihort'] ) ) {
				$output .= '<p>Aktueller Ort: ' . esc_html( $objekt['verleihort'] ) . '</p>';
			}

			$output .= '<p>Verliehen: <span class="bv-verliehen-status ' . esc_attr( $objekt['verliehen'] ) . '">' . esc_html( $objekt['verliehen'] ) . '</span></p>';
			$output .= '</div>';

			$output .= '</div>';
		}

		$output .= '</div>';
	} else {
		$output .= '<p>Keine Objekte gefunden.</p>';
	}

	return $output;
}
add_shortcode( 'bv_objekte', 'bv_render_objekte' );

/**
 * Gestaltung der Übersicht.
 */
function bv_enqueue_styles() {
	?>
	<style>
		.bv-objekte-grid {
			display: flex;
			flex-wrap: wrap;
			gap: 20px;
			margin-top: 20px;
		}

		.bv-objekt {
			display: flex;
			width: 80%;
			max-width: 50%;
			background: #f9f9f9;
			border: 0 solid #e1e1e1;
			padding: 20px;
			box-shadow: 0 2px 8px rgba(0, 0, 0, .1);
			flex: 1 1 48%;
			box-sizing: border-box;
		}

		.bv-objekt-bild {
			max-width: 290px;
			margin-right: 20px;
		}

		.bv-objekt-infos {
			width: 60%;
		}

		.bv-objekt h2 {
			margin-top: 0;
			font-size: 1.3rem;
		}

		.bv-objekt p {
			margin: 5px 0;
		}

		.bv-verliehen-status.ja {
			color: red;
		}

		.bv-verliehen-status.nein {
			color: green;
		}

		.bv-anfrage-button {
			display: inline-block;
			width: 90%;
			margin-top: 10px;
			padding: 5px 7px;
			background-color: #0073aa;
			color: #fff;
			text-decoration: none;
			border-radius: 3px;
			transition: background-color .3s ease;
		}

		.bv-anfrage-button:hover {
			background-color: #005177;
		}
	</style>
	<?php
}
add_action( 'wp_head', 'bv_enqueue_styles' );
