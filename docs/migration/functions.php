<?php
/**
 * Child-Theme functions and definitions
 *
 * Bereinigte Fassung: Alles, was jetzt "LOHEIDE.EU WP Rights Management"
 * übernimmt, wurde entfernt. Die entfernten Blöcke sind am Ende dieser Datei
 * aufgelistet, damit nachvollziehbar bleibt, was wohin gewandert ist.
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Darstellung
 * ---------------------------------------------------------------------- */

/**
 * Stylesheet des übergeordneten Themes laden.
 */
function alliance_child_scripts() {
	wp_enqueue_style( 'alliance-style', get_template_directory_uri() . '/style.css' );
}
add_action( 'wp_enqueue_scripts', 'alliance_child_scripts' );

/**
 * WordPress-Logo und Zusatzlinks in der Werkzeugleiste ausblenden.
 */
function hide_wp_logo_in_admin_bar() {
	echo '<style>
		#wp-admin-bar-wp-logo { display: none !important; }
		#wpadminbar .quicklinks .ab-top-secondary > li { display: none; }
		#footer-thankyou { display: none; }
	</style>';
}
add_action( 'admin_head', 'hide_wp_logo_in_admin_bar' );

/**
 * Sprachauswahl auf der Anmeldeseite ausblenden.
 */
add_filter( 'login_display_language_dropdown', '__return_false' );

/**
 * Verweis auf "Passwort vergessen" auf der Anmeldeseite entfernen.
 */
function remove_lostpassword_text( $text ) {
	if ( 'Lost your password?' === $text ) {
		return '';
	}

	return $text;
}
add_filter( 'gettext', 'remove_lostpassword_text' );

/**
 * Englische Begriffe des Themes im Frontend ersetzen.
 */
function custom_search_replace( $content ) {
	$pairs = array(
		'Contact Us'        => 'Kontaktieren Sie uns',
		'You May Also Like' => 'Weitere Stellenangebote',
		'NEWEST ARTICLES'   => 'Neuste Artikel',
		'Call'              => 'Anrufen',
		'show phone'        => 'Zeige Telefonnummer',
	);

	return str_replace( array_keys( $pairs ), array_values( $pairs ), $content );
}
add_filter( 'the_content', 'custom_search_replace' );

/* -------------------------------------------------------------------------
 * Personalnummer und Abteilung aus dem Active Directory
 * ---------------------------------------------------------------------- */

/**
 * Shortcode zur Ausgabe der Personalnummer.
 */
function personalnummer_shortcode( $atts ) {
	$user_id = get_current_user_id();

	if ( ! $user_id ) {
		return '';
	}

	$personalnummer = get_user_meta( $user_id, 'next_ad_int_personalnummer', true );

	if ( empty( $personalnummer ) ) {
		return '';
	}

	return esc_html( $personalnummer );
}
add_shortcode( 'personalnummer', 'personalnummer_shortcode' );

/**
 * Personalnummer und Abteilung für JavaScript bereitstellen.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_user_logged_in() ) {
		return;
	}

	$user_id        = get_current_user_id();
	$personalnummer = get_user_meta( $user_id, 'next_ad_int_personalnummer', true );
	$abteilung      = get_user_meta( $user_id, 'next_ad_int_department', true );

	$script  = 'window.userPersonalnummer = "' . esc_js( $personalnummer ) . '";';
	$script .= 'window.userDepartment = "' . esc_js( $abteilung ) . '";';

	wp_add_inline_script( 'jquery-core', $script );
} );

/**
 * Felder in Fluent Forms vorbelegen und schreibgeschützt setzen.
 */
add_action( 'wp_footer', function () {
	if ( ! is_user_logged_in() ) {
		return;
	}
	?>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		if (typeof window.userPersonalnummer !== 'undefined') {
			const feld = document.querySelector('input[name="input_personalnummer"]');
			if (feld) { feld.value = window.userPersonalnummer; feld.readOnly = true; }
		}

		if (typeof window.userDepartment !== 'undefined') {
			const feld = document.querySelector('input[name="input_einsatzort"]');
			if (feld) { feld.value = window.userDepartment; feld.readOnly = true; }
		}
	});
	</script>
	<?php
} );

/**
 * Shortcode: Vor- und Nachname des angemeldeten Benutzers.
 *
 * Hinweis: Die frühere Fassung rief get_currentuserinfo() auf. Diese Funktion
 * wurde mit WordPress 4.5 entfernt und hätte einen Fehler ausgelöst.
 */
function username_anzeigen() {
	if ( ! is_user_logged_in() ) {
		return 'Hallo Unbekannter! (Nicht eingeloggt)';
	}

	$user = wp_get_current_user();

	return esc_html( trim( $user->user_firstname . ' ' . $user->user_lastname ) );
}
add_shortcode( 'username-anzeigen', 'username_anzeigen' );

/* -------------------------------------------------------------------------
 * Werkzeuge für Redaktion und Betrieb
 * ---------------------------------------------------------------------- */

/**
 * Updates für angepasste Plugins unterdrücken.
 */
function disable_modified_plugin_updates( $value ) {
	$plugins = array(
		'wp-notification-bell/wp-notification-bell.php',
		'download-manager/download-manager.php',
	);

	foreach ( $plugins as $plugin ) {
		if ( isset( $value->response[ $plugin ] ) ) {
			unset( $value->response[ $plugin ] );
		}
	}

	return $value;
}
add_filter( 'site_transient_update_plugins', 'disable_modified_plugin_updates' );

/**
 * Shortcode: Speisepläne drucken.
 */
function print_icon_shortcode() {
	ob_start();
	?>
	<style>
		.print-icon { cursor: pointer; font-size: 25px; }
		.print-content img { display: none; }
	</style>
	<script>
	jQuery(document).ready(function ($) {
		$('.print-icon').click(function () {
			var fenster = window.open('', '_blank');
			fenster.document.write('<html><head><title>Print</title></head><body class="print-content">');
			fenster.document.write('<div id="imageContainer1"><img src="https://ff.ledderwerkstaetten.de/speiseplan/ledde/diesewoche.jpg" alt="Diese Woche"></div>');
			fenster.document.write('<div id="imageContainer2"><img src="https://ff.ledderwerkstaetten.de/speiseplan/ledde/naechstewoche.jpg" alt="Nächste Woche"></div>');
			fenster.document.write('</body></html>');
			fenster.document.close();

			fenster.addEventListener('load', function () {
				setTimeout(function () {
					fenster.document.querySelectorAll('.print-content img').forEach(function (img) {
						img.style.display = 'block';
						img.style.maxWidth = '100%';
						img.style.maxHeight = '100vh';
						img.style.width = 'auto';
						img.style.height = 'auto';
						img.parentElement.style.pageBreakAfter = 'always';
						img.parentElement.style.width = '210mm';
						img.parentElement.style.height = '297mm';
						img.parentElement.style.display = 'flex';
						img.parentElement.style.justifyContent = 'center';
						img.parentElement.style.alignItems = 'center';
					});

					fenster.print();
					fenster.close();
				}, 500);
			}, false);
		});
	});
	</script>
	<div class="print-icon">&#128438; Speisepläne drucken</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'print_icon', 'print_icon_shortcode' );

/**
 * Seiten mit wechselnden Inhalten nicht zwischenspeichern.
 *
 * Für Seiten, die über das Rechte-Plugin geschützt sind, ist das nicht mehr
 * nötig: Dort werden die Kopfzeilen automatisch gesetzt. Dieser Block ist nur
 * noch für frei zugängliche Seiten mit wechselndem Inhalt gedacht.
 */
function no_cache_for_specific_pages() {
	if ( is_page( array( 'speiseplan-ledde' ) ) ) {
		nocache_headers();
	}
}
add_action( 'wp', 'no_cache_for_specific_pages' );

/**
 * Shortcode: Text erst auf Klick in die Zwischenablage kopieren.
 *
 * Hinweis: Der Text steht weiterhin im Quelltext der Seite (data-copy-text)
 * und ist damit für jeden lesbar, der die Seite aufrufen darf. Ein echter
 * Schutz entsteht erst dadurch, dass die Seite selbst nur berechtigten Rollen
 * zugänglich ist – das übernimmt das Rechte-Plugin.
 */
function verschluesselter_text_shortcode( $atts, $content = '' ) {
	$anzeige_text = 'Hier klicken, um das Passwort in die Zwischenablage zu kopieren';

	$output  = '<span class="verschluesselt" data-copy-text="' . esc_attr( $content ) . '">' . esc_html( $anzeige_text ) . '</span>';
	$output .= '<style>.verschluesselt { cursor: pointer; border-bottom: 1px dotted transparent; } .verschluesselt:hover { border-bottom: 1px dotted #000; }</style>';

	return $output;
}
add_shortcode( 'verschluesselter_text', 'verschluesselter_text_shortcode' );

/**
 * JavaScript zum Kopieren in die Zwischenablage.
 */
function custom_js_for_clipboard() {
	?>
	<script type="text/javascript">
	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.verschluesselt').forEach(function (element) {
			element.addEventListener('click', function () {
				var text = this.getAttribute('data-copy-text');

				if (navigator.clipboard) {
					navigator.clipboard.writeText(text);
				} else {
					var hilfsfeld = document.createElement('textarea');
					document.body.appendChild(hilfsfeld);
					hilfsfeld.value = text;
					hilfsfeld.select();
					document.execCommand('copy');
					document.body.removeChild(hilfsfeld);
				}

				alert('Text wurde in die Zwischenablage kopiert: ' + text);
			});
		});
	});
	</script>
	<?php
}
add_action( 'wp_footer', 'custom_js_for_clipboard' );

/**
 * Bereits gesehene Benachrichtigungen ausblenden (WP Notification Bell).
 */
add_filter( 'wnbell_notification_conditions', 'add_notification_conditions' );
function add_notification_conditions() {
	$condition   = '';
	$user_id     = get_current_user_id();
	$seen_posts  = get_user_meta( $user_id, 'wnbell_seen_notification_post', true );

	if ( ! $seen_posts ) {
		return $condition;
	}

	$condition = ' AND posts.ID NOT IN(' . wnbell_escape_array( $seen_posts ) . ') ';

	if ( count( $seen_posts ) >= 20 ) {
		$arr     = array_slice( $seen_posts, -5 );
		$last_id = filter_var( max( $arr ), FILTER_SANITIZE_NUMBER_INT );

		$condition .= " AND posts.ID > $last_id ";
	}

	return $condition;
}

/**
 * Nach der Anmeldung zur Ausgangsseite oder zur Startseite weiterleiten.
 */
function custom_redirect_after_login( $user_login, $user ) {
	if ( ! empty( $_REQUEST['redirect_to'] ) ) {
		wp_safe_redirect( esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) );
		exit;
	}

	wp_safe_redirect( home_url() );
	exit;
}
add_action( 'wp_login', 'custom_redirect_after_login', 10, 2 );

/*
 * ACHTUNG – bewusst beibehalten, aber prüfenswert:
 *
 * add_filter( 'https_ssl_verify', '__return_false' );
 *
 * Diese Zeile schaltet die Prüfung von TLS-Zertifikaten für alle ausgehenden
 * Anfragen von WordPress ab. Das war vermutlich wegen eines internen
 * Zertifikats nötig. Falls möglich, stattdessen das Wurzelzertifikat auf dem
 * Server hinterlegen und die Zeile entfernen. Bis dahin bleibt sie hier
 * stehen, damit nichts unerwartet ausfällt:
 */
add_filter( 'https_ssl_verify', '__return_false' );

/* -------------------------------------------------------------------------
 * Entfernt – wird jetzt von "LOHEIDE.EU WP Rights Management" übernommen
 * -------------------------------------------------------------------------
 *
 * custom_hide_menu_items()             Menüpunkte je Rolle ausblenden
 *                                      → Rechte → Backend → Sichtbare Menüpunkte
 *
 * restrict_profile_editing_for_mav_role()
 *                                      Profil ausblenden und sperren
 *                                      → Rechte → Backend → Menüpunkt "Profil"
 *
 * entferne_dashboard_widgets()         Dashboard leeren
 *                                      → Rechte → Backend → Bereiche auf dem Dashboard
 *
 * beschränke_beitragszugriff()         Beiträge auf eine Kategorie begrenzen
 *                                      → Rechte → Backend → Beiträge → Nach Kategorie
 *
 * limit_pages_for_mav_role()           Seiten je Rolle zuweisen
 *                                      → Rechte → Backend → Seiten → Nur ausgewählte
 *
 * custom_remove_new_menu_item()        "+ Neu" aus der Werkzeugleiste nehmen
 *                                      → geschieht automatisch, sobald das
 *                                        Anlegen neuer Inhalte nicht erlaubt ist
 *
 * capture_redirect_url()               Gesamte Website nur nach Anmeldung
 *                                      → Rechte → Seiten auf "Nur angemeldet"
 *                                        setzen (Sammelbearbeitung), Aktion
 *                                        "Zur Anmeldung weiterleiten"
 *
 * add_no_cache_headers()               Kopfzeilen gegen Zwischenspeicherung
 *                                      → für geschützte Seiten automatisch
 *
 * Ebenfalls entfernt (nicht mehr benötigt):
 *
 * check_server_status_func()           Shortcode [check_server_status]
 */
