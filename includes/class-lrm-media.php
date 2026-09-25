<?php
/**
 * Schutz der Dateien im Uploads-Ordner.
 *
 * @package LOHEIDE_Rights_Management
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sperrt den direkten Aufruf von Dateien und liefert erlaubte Dateien über PHP aus.
 *
 * Ohne diesen Schutz bleibt eine Datei unter ihrer Adresse erreichbar, auch wenn
 * die Seite, auf der sie eingebunden ist, gesperrt ist. Der Webserver liefert
 * solche Dateien aus, ohne WordPress zu starten. Deshalb wird eine Regel in die
 * .htaccess des Uploads-Ordners geschrieben, die alle Anfragen über WordPress
 * leitet – mit Ausnahme der freigegebenen Dateien.
 */
class LRM_Media {

	/**
	 * Abfragevariable für den Dateiaufruf.
	 */
	const QUERY_VAR = 'lrm_file';

	/**
	 * Marker in der .htaccess.
	 */
	const MARKER = 'LOHEIDE.EU WP Rights Management';

	/**
	 * Endungen, die niemals ausgeliefert werden.
	 *
	 * @var array
	 */
	protected static $forbidden_extensions = array( 'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'phps', 'cgi', 'pl', 'py', 'sh', 'htaccess', 'htpasswd', 'ini', 'conf' );

	/**
	 * Hooks registrieren.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'maybe_serve_file' ), 1 );

		// Anhänge werden wie Inhalte behandelt, sobald der Schutz aktiv ist.
		add_filter( 'lrm_protected_post_types', array( $this, 'add_attachment_type' ) );

		// Regeln in der Medienbibliothek.
		add_filter( 'attachment_fields_to_edit', array( $this, 'attachment_field' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'save_attachment_field' ), 10, 2 );
		add_filter( 'manage_media_columns', array( $this, 'media_column' ) );
		add_action( 'manage_media_custom_column', array( $this, 'media_column_content' ), 10, 2 );

		// Serverregel pflegen.
		add_action( 'update_option_' . LRM_Settings::OPTION, array( $this, 'sync_rules' ), 10, 0 );
	}

	/**
	 * Anhänge in die geschützten Inhaltstypen aufnehmen.
	 *
	 * @param array $types Inhaltstypen.
	 * @return array
	 */
	public function add_attachment_type( $types ) {
		if ( ! LRM_Settings::get( 'protect_uploads' ) ) {
			return $types;
		}

		if ( ! in_array( 'attachment', $types, true ) ) {
			$types[] = 'attachment';
		}

		return $types;
	}

	/* ------------------------------------------------------------------ *
	 * Auslieferung
	 * ------------------------------------------------------------------ */

	/**
	 * Dateianfrage beantworten, wenn die Serverregel darauf verweist.
	 */
	public function maybe_serve_file() {
		if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Öffentlicher Dateiaufruf.
			return;
		}

		$requested = wp_unslash( $_GET[ self::QUERY_VAR ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- Wird unten streng geprüft.
		$path      = self::resolve_path( $requested );

		if ( ! $path ) {
			self::send_status( 404 );
		}

		$relative = self::relative_path( $path );

		if ( self::is_allowed( $relative, $path ) ) {
			self::serve( $path );
		}

		$this->deny( $relative );
	}

	/**
	 * Angeforderten Pfad prüfen und in einen echten Dateipfad auflösen.
	 *
	 * Verhindert das Ausbrechen aus dem Uploads-Ordner und die Auslieferung
	 * ausführbarer Dateien.
	 *
	 * @param string $requested Angeforderter Pfad.
	 * @return string|false Absoluter Pfad oder false.
	 */
	public static function resolve_path( $requested ) {
		$requested = (string) $requested;

		// Nullbytes und Query-Reste entfernen.
		$requested = str_replace( chr( 0 ), '', $requested );
		$requested = ltrim( preg_replace( '/[?#].*$/', '', $requested ), '/' );

		if ( '' === $requested ) {
			return false;
		}

		$uploads = wp_get_upload_dir();
		$basedir = realpath( $uploads['basedir'] );

		if ( ! $basedir ) {
			return false;
		}

		$candidate = realpath( $basedir . '/' . $requested );

		if ( ! $candidate || ! is_file( $candidate ) ) {
			return false;
		}

		// Die Datei muss innerhalb des Uploads-Ordners liegen.
		if ( 0 !== strpos( $candidate, $basedir . DIRECTORY_SEPARATOR ) ) {
			return false;
		}

		$extension = strtolower( (string) pathinfo( $candidate, PATHINFO_EXTENSION ) );

		if ( in_array( $extension, self::$forbidden_extensions, true ) ) {
			return false;
		}

		return $candidate;
	}

	/**
	 * Pfad relativ zum Uploads-Ordner.
	 *
	 * @param string $path Absoluter Pfad.
	 * @return string
	 */
	public static function relative_path( $path ) {
		$uploads = wp_get_upload_dir();
		$basedir = realpath( $uploads['basedir'] );

		if ( ! $basedir ) {
			return '';
		}

		return ltrim( str_replace( $basedir, '', $path ), '/\\' );
	}

	/**
	 * Darf die Datei ausgeliefert werden?
	 *
	 * Reihenfolge:
	 *   1. Freigegebene Datei (Ausnahmeliste) -> immer erlaubt.
	 *   2. Eigene Regel des Anhangs (inklusive der Regel der Seite, an der er hängt).
	 *   3. Genereller Block für nicht angemeldete Besucher.
	 *
	 * @param string $relative Pfad relativ zum Uploads-Ordner.
	 * @param string $absolute Absoluter Pfad.
	 * @return bool
	 */
	public static function is_allowed( $relative, $absolute = '' ) {
		// 1. Ausnahmeliste.
		if ( self::is_whitelisted( $relative ) ) {
			return true;
		}

		// 2. Regel des Anhangs.
		$attachment_id = self::find_attachment( $relative );

		if ( $attachment_id ) {
			$check = LRM_Access::check( $attachment_id );

			if ( empty( $check['allowed'] ) ) {
				return false;
			}
		}

		// 3. Genereller Block.
		if ( 'login' === LRM_Settings::get( 'uploads_mode' ) && ! is_user_logged_in() ) {
			return false;
		}

		/**
		 * Filtert die Entscheidung über eine Datei.
		 *
		 * @param bool   $allowed  Zugriff erlaubt.
		 * @param string $relative Pfad relativ zum Uploads-Ordner.
		 * @param string $absolute Absoluter Pfad.
		 */
		return apply_filters( 'lrm_file_access', true, $relative, $absolute );
	}

	/**
	 * Steht die Datei auf der Ausnahmeliste?
	 *
	 * @param string $relative Pfad relativ zum Uploads-Ordner.
	 * @return bool
	 */
	public static function is_whitelisted( $relative ) {
		$relative = ltrim( str_replace( '\\', '/', $relative ), '/' );

		foreach ( self::whitelist_patterns() as $pattern ) {
			if ( self::matches( $pattern, $relative ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Alle Muster der Ausnahmeliste, inklusive der automatisch ergänzten.
	 *
	 * @return array
	 */
	public static function whitelist_patterns() {
		$patterns = self::parse_patterns( LRM_Settings::get( 'uploads_whitelist' ) );

		if ( LRM_Settings::get( 'whitelist_branding' ) ) {
			foreach ( self::branding_files() as $file ) {
				$patterns[] = $file;
			}
		}

		/**
		 * Filtert die Ausnahmeliste.
		 *
		 * @param array $patterns Muster.
		 */
		return array_values( array_unique( apply_filters( 'lrm_uploads_whitelist', $patterns ) ) );
	}

	/**
	 * Dateien des Website-Logos und des Website-Icons.
	 *
	 * Sie werden auf der Anmeldeseite und im Browser-Tab angezeigt und müssen
	 * daher ohne Anmeldung erreichbar bleiben.
	 *
	 * @return array Pfade relativ zum Uploads-Ordner, inklusive aller Größen.
	 */
	public static function branding_files() {
		$files = array();
		$ids   = array();

		$logo = get_theme_mod( 'custom_logo' );

		if ( $logo ) {
			$ids[] = (int) $logo;
		}

		$icon = get_option( 'site_icon' );

		if ( $icon ) {
			$ids[] = (int) $icon;
		}

		/**
		 * Filtert die Anhänge, die immer öffentlich bleiben.
		 *
		 * @param array $ids Anhang-IDs.
		 */
		$ids = apply_filters( 'lrm_branding_attachments', array_unique( array_filter( $ids ) ) );

		foreach ( $ids as $id ) {
			$file = get_post_meta( $id, '_wp_attached_file', true );

			if ( ! $file ) {
				continue;
			}

			$files[] = $file;

			// Auch die erzeugten Bildgrößen freigeben.
			$dir  = trim( (string) dirname( $file ), './' );
			$meta = wp_get_attachment_metadata( $id );

			if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
				foreach ( $meta['sizes'] as $size ) {
					if ( ! empty( $size['file'] ) ) {
						$files[] = ( $dir ? $dir . '/' : '' ) . $size['file'];
					}
				}
			}
		}

		return $files;
	}

	/**
	 * Muster aus der Eingabe lesen.
	 *
	 * @param string $raw Eingabe, ein Muster je Zeile.
	 * @return array
	 */
	public static function parse_patterns( $raw ) {
		$lines    = preg_split( '/\R/', (string) $raw );
		$patterns = array();

		foreach ( (array) $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line || 0 === strpos( $line, '#' ) ) {
				continue;
			}

			// Vollständige Adressen auf den relativen Pfad kürzen.
			$uploads = wp_get_upload_dir();

			if ( false !== strpos( $line, $uploads['baseurl'] ) ) {
				$line = ltrim( str_replace( $uploads['baseurl'], '', $line ), '/' );
			}

			$patterns[] = ltrim( str_replace( '\\', '/', $line ), '/' );
		}

		return $patterns;
	}

	/**
	 * Einfacher Mustervergleich mit * und ?.
	 *
	 * @param string $pattern Muster.
	 * @param string $subject Pfad.
	 * @return bool
	 */
	public static function matches( $pattern, $subject ) {
		if ( '' === $pattern ) {
			return false;
		}

		$regex = '#^' . str_replace( array( '\*', '\?' ), array( '.*', '.' ), preg_quote( $pattern, '#' ) ) . '$#i';

		if ( preg_match( $regex, $subject ) ) {
			return true;
		}

		// Ein Muster ohne Ordnerangabe gilt für jeden Ordner: „logo.png“ und
		// „logo*.png“ passen damit auch auf „2026/01/logo-300x200.png“.
		if ( false === strpos( $pattern, '/' ) ) {
			return (bool) preg_match( $regex, basename( $subject ) );
		}

		return false;
	}

	/**
	 * Anhang zu einer Datei finden – auch für erzeugte Bildgrößen.
	 *
	 * @param string $relative Pfad relativ zum Uploads-Ordner.
	 * @return int Anhang-ID oder 0.
	 */
	public static function find_attachment( $relative ) {
		global $wpdb;

		$relative = ltrim( str_replace( '\\', '/', $relative ), '/' );

		$candidates = array( $relative );

		// bild-300x200.jpg -> bild.jpg, bild-scaled.jpg -> bild.jpg
		$stripped = preg_replace( '/-\d+x\d+(?=\.[^.]+$)/', '', $relative );

		if ( $stripped !== $relative ) {
			$candidates[] = $stripped;
		}

		foreach ( array( '-scaled', '-rotated' ) as $suffix ) {
			foreach ( $candidates as $candidate ) {
				$plain = str_replace( $suffix . '.', '.', $candidate );

				if ( $plain !== $candidate ) {
					$candidates[] = $plain;
				}
			}
		}

		foreach ( array_unique( $candidates ) as $candidate ) {
			$cache_key = 'lrm_att_' . md5( $candidate );
			$cached    = wp_cache_get( $cache_key, 'lrm' );

			if ( false !== $cached ) {
				if ( $cached ) {
					return (int) $cached;
				}

				continue;
			}

			$id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
					$candidate
				)
			);

			wp_cache_set( $cache_key, $id, 'lrm', HOUR_IN_SECONDS );

			if ( $id ) {
				return $id;
			}
		}

		return 0;
	}

	/**
	 * Datei ausliefern.
	 *
	 * @param string $path Absoluter Pfad.
	 */
	public static function serve( $path ) {
		$size      = filesize( $path );
		$modified  = filemtime( $path );
		$mime      = self::mime_type( $path );
		$etag      = '"' . md5( $path . $size . $modified ) . '"';
		$if_none   = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) ) : '';

		nocache_headers();
		header_remove( 'Cache-Control' );
		header_remove( 'Expires' );

		header( 'Content-Type: ' . $mime );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $modified ) . ' GMT' );
		header( 'ETag: ' . $etag );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		// Nur im Browser des angemeldeten Besuchers zwischenspeichern.
		header( 'Cache-Control: private, max-age=0, must-revalidate' );
		header( 'Content-Disposition: ' . ( self::is_inline( $mime ) ? 'inline' : 'attachment' ) . '; filename="' . basename( $path ) . '"' );
		header( 'Accept-Ranges: bytes' );

		if ( $if_none && $if_none === $etag ) {
			status_header( 304 );
			exit;
		}

		$start  = 0;
		$end    = $size - 1;
		$range  = isset( $_SERVER['HTTP_RANGE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_RANGE'] ) ) : '';
		$status = 200;

		// Teilabrufe, z. B. beim Abspielen von Videos.
		if ( $range && preg_match( '/bytes=(\d*)-(\d*)/', $range, $m ) ) {
			$start = '' === $m[1] ? 0 : (int) $m[1];
			$end   = '' === $m[2] ? $size - 1 : (int) $m[2];

			if ( $start > $end || $start >= $size ) {
				status_header( 416 );
				header( 'Content-Range: bytes */' . $size );
				exit;
			}

			$end    = min( $end, $size - 1 );
			$status = 206;
			header( 'Content-Range: bytes ' . $start . '-' . $end . '/' . $size );
		}

		status_header( $status );
		header( 'Content-Length: ' . ( $end - $start + 1 ) );

		if ( function_exists( 'wp_ob_end_flush_all' ) ) {
			wp_ob_end_flush_all();
		}

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( ! $handle ) {
			self::send_status( 500 );
		}

		fseek( $handle, $start );
		$remaining = $end - $start + 1;

		while ( $remaining > 0 && ! feof( $handle ) ) {
			$chunk      = min( 512 * 1024, $remaining );
			echo fread( $handle, $chunk ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_system_operations_fread
			$remaining -= $chunk;
			flush();
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Zugriff verweigern.
	 *
	 * @param string $relative Pfad relativ zum Uploads-Ordner.
	 */
	protected function deny( $relative ) {
		/**
		 * Wird vor dem Abweisen einer Datei ausgelöst.
		 *
		 * @param string $relative Pfad relativ zum Uploads-Ordner.
		 */
		do_action( 'lrm_file_denied', $relative );

		$action = LRM_Settings::get( 'uploads_denied_action' );

		if ( 'login' === $action && ! is_user_logged_in() ) {
			$target = home_url( add_query_arg( array() ) );

			nocache_headers();
			wp_safe_redirect( LRM_Settings::login_url( $target ), 302 );
			exit;
		}

		self::send_status( 403 );
	}

	/**
	 * Mit Status abbrechen.
	 *
	 * @param int $code Statuscode.
	 */
	protected static function send_status( $code ) {
		nocache_headers();
		status_header( $code );
		header( 'Content-Type: text/plain; charset=utf-8' );

		if ( 403 === $code ) {
			echo esc_html__( 'Kein Zugriff auf diese Datei.', 'loheide-rights-management' );
		} elseif ( 404 === $code ) {
			echo esc_html__( 'Datei nicht gefunden.', 'loheide-rights-management' );
		}

		exit;
	}

	/**
	 * MIME-Typ ermitteln.
	 *
	 * @param string $path Pfad.
	 * @return string
	 */
	protected static function mime_type( $path ) {
		$type = wp_check_filetype( basename( $path ) );

		if ( ! empty( $type['type'] ) ) {
			return $type['type'];
		}

		return 'application/octet-stream';
	}

	/**
	 * Wird der Typ im Browser angezeigt?
	 *
	 * @param string $mime MIME-Typ.
	 * @return bool
	 */
	protected static function is_inline( $mime ) {
		return (bool) preg_match( '#^(image/|video/|audio/|text/plain|application/pdf)#', $mime );
	}

	/* ------------------------------------------------------------------ *
	 * Serverregeln
	 * ------------------------------------------------------------------ */

	/**
	 * Serverregel nach einer Änderung der Einstellungen angleichen.
	 */
	public function sync_rules() {
		LRM_Settings::flush();

		if ( LRM_Settings::get( 'protect_uploads' ) ) {
			self::write_htaccess();
		} else {
			self::remove_htaccess();
		}
	}

	/**
	 * Pfad zur .htaccess im Uploads-Ordner.
	 *
	 * @return string
	 */
	public static function htaccess_path() {
		$uploads = wp_get_upload_dir();

		return trailingslashit( $uploads['basedir'] ) . '.htaccess';
	}

	/**
	 * Regeln für die .htaccess erzeugen.
	 *
	 * @return array Zeilen.
	 */
	public static function htaccess_rules() {
		$uploads = wp_get_upload_dir();
		$base    = wp_parse_url( $uploads['baseurl'], PHP_URL_PATH );
		$base    = trailingslashit( $base ? $base : '/wp-content/uploads' );
		$home    = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home    = trailingslashit( $home ? $home : '/' );

		$lines = array(
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'RewriteBase ' . $base,
		);

		// Freigegebene Dateien werden weiterhin direkt ausgeliefert.
		foreach ( self::whitelist_patterns() as $pattern ) {
			$lines[] = 'RewriteRule ^' . self::pattern_to_regex( $pattern ) . '$ - [L]';
		}

		// Sicherung: Wird das Plugin gelöscht, ohne vorher deaktiviert zu werden,
		// greift die Regel nicht mehr. Andernfalls wären sämtliche Dateien im
		// Uploads-Ordner nicht mehr erreichbar.
		$lines[] = 'RewriteCond ' . LRM_FILE . ' -f';
		$lines[] = 'RewriteCond %{REQUEST_FILENAME} -f';
		$lines[] = 'RewriteRule ^(.+)$ ' . $home . 'index.php?' . self::QUERY_VAR . '=$1 [QSA,L]';
		$lines[] = '</IfModule>';

		return $lines;
	}

	/**
	 * Muster in einen regulären Ausdruck für die .htaccess übersetzen.
	 *
	 * @param string $pattern Muster.
	 * @return string
	 */
	protected static function pattern_to_regex( $pattern ) {
		$escaped = preg_quote( $pattern, '#' );
		$escaped = str_replace( array( '\*', '\?' ), array( '.*', '.' ), $escaped );

		// preg_quote maskiert Zeichen, die in der .htaccess unmaskiert bleiben dürfen.
		$escaped = str_replace( array( '\-', '\/' ), array( '-', '/' ), $escaped );

		// Ein Muster ohne Ordnerangabe gilt für jeden Ordner.
		if ( false === strpos( $pattern, '/' ) ) {
			$escaped = '(.*/)?' . $escaped;
		}

		return $escaped;
	}

	/**
	 * Regeln schreiben.
	 *
	 * @return bool
	 */
	public static function write_htaccess() {
		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		$path = self::htaccess_path();

		if ( ! file_exists( $path ) && ! is_writable( dirname( $path ) ) ) {
			return false;
		}

		if ( file_exists( $path ) && ! is_writable( $path ) ) {
			return false;
		}

		return (bool) insert_with_markers( $path, self::MARKER, self::htaccess_rules() );
	}

	/**
	 * Regeln entfernen.
	 *
	 * @return bool
	 */
	public static function remove_htaccess() {
		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		$path = self::htaccess_path();

		if ( ! file_exists( $path ) || ! is_writable( $path ) ) {
			return false;
		}

		return (bool) insert_with_markers( $path, self::MARKER, array() );
	}

	/**
	 * Sind die Regeln vorhanden?
	 *
	 * @return bool
	 */
	public static function htaccess_active() {
		$path = self::htaccess_path();

		if ( ! file_exists( $path ) ) {
			return false;
		}

		$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		return false !== strpos( (string) $content, '# BEGIN ' . self::MARKER );
	}

	/**
	 * Art des Webservers.
	 *
	 * @return string apache|nginx|unbekannt.
	 */
	public static function server_type() {
		$software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) ) : '';

		if ( false !== strpos( $software, 'nginx' ) ) {
			return 'nginx';
		}

		if ( false !== strpos( $software, 'apache' ) || false !== strpos( $software, 'litespeed' ) ) {
			return 'apache';
		}

		return 'unbekannt';
	}

	/**
	 * Beispielregeln für nginx.
	 *
	 * @return string
	 */
	public static function nginx_rules() {
		$uploads = wp_get_upload_dir();
		$base    = untrailingslashit( (string) wp_parse_url( $uploads['baseurl'], PHP_URL_PATH ) );
		$home    = trailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );

		$lines = array( 'location ' . $base . '/ {' );

		foreach ( self::whitelist_patterns() as $pattern ) {
			$lines[] = '    # Ausnahme: ' . $pattern;
		}

		$lines[] = '    try_files $uri @lrm_protected;';
		$lines[] = '}';
		$lines[] = '';
		$lines[] = 'location @lrm_protected {';
		$lines[] = '    rewrite ^' . $base . '/(.*)$ ' . $home . 'index.php?' . self::QUERY_VAR . '=$1 last;';
		$lines[] = '}';

		return implode( "\n", $lines );
	}

	/**
	 * Selbsttest: eine geschützte Datei ohne Anmeldung abrufen.
	 *
	 * @return array {
	 *     @type string $status ok|offen|unbekannt|keine_datei.
	 *     @type string $text   Beschreibung.
	 *     @type string $url    Geprüfte Adresse.
	 * }
	 */
	public static function self_test() {
		$attachment = self::find_test_attachment();

		if ( ! $attachment ) {
			return array(
				'status' => 'keine_datei',
				'text'   => __( 'Es wurde keine Datei gefunden, die zum Prüfen geeignet ist. Laden Sie eine Datei hoch.', 'loheide-rights-management' ),
				'url'    => '',
			);
		}

		$url      = wp_get_attachment_url( $attachment );
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 10,
				'redirection' => 0,
				'sslverify'   => false,
				'cookies'     => array(),
				'headers'     => array( 'Cache-Control' => 'no-cache' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status' => 'unbekannt',
				'text'   => sprintf(
					/* translators: %s: Fehlermeldung. */
					__( 'Die Prüfung konnte nicht durchgeführt werden: %s. Manche Server beantworten keine Anfragen an sich selbst; das sagt nichts über den Schutz aus. Rufen Sie die Datei stattdessen in einem privaten Browserfenster auf.', 'loheide-rights-management' ),
					$response->get_error_message()
				),
				'url'    => $url,
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( in_array( $code, array( 401, 403, 404 ), true ) || ( $code >= 300 && $code < 400 ) ) {
			return array(
				'status' => 'ok',
				'text'   => sprintf(
					/* translators: %d: Statuscode. */
					__( 'Der Schutz greift: Der Abruf ohne Anmeldung wurde mit Status %d abgewiesen.', 'loheide-rights-management' ),
					$code
				),
				'url'    => $url,
			);
		}

		return array(
			'status' => 'offen',
			'text'   => sprintf(
				/* translators: %d: Statuscode. */
				__( 'Die Datei war ohne Anmeldung abrufbar (Status %d). Die Serverregel greift nicht – prüfen Sie die Hinweise unten.', 'loheide-rights-management' ),
				$code
			),
			'url'    => $url,
		);
	}

	/**
	 * Eine Datei finden, die für den Selbsttest gesperrt sein müsste.
	 *
	 * @return int Anhang-ID oder 0.
	 */
	protected static function find_test_attachment() {
		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 20,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		foreach ( $attachments as $id ) {
			$file = get_post_meta( $id, '_wp_attached_file', true );

			if ( $file && ! self::is_whitelisted( $file ) ) {
				return (int) $id;
			}
		}

		return 0;
	}

	/* ------------------------------------------------------------------ *
	 * Medienbibliothek
	 * ------------------------------------------------------------------ */

	/**
	 * Auswahlfeld im Medienfenster.
	 *
	 * @param array   $fields Felder.
	 * @param WP_Post $post   Anhang.
	 * @return array
	 */
	public function attachment_field( $fields, $post ) {
		if ( ! LRM_Settings::get( 'protect_uploads' ) || ! current_user_can( LRM_Roles::CAP_MANAGE ) ) {
			return $fields;
		}

		$rule    = LRM_Access::get_rule( $post->ID );
		$current = $rule->enabled ? $rule->visibility : '';

		$options = array(
			''                             => __( 'Ohne eigene Regel', 'loheide-rights-management' ),
			LRM_Rule::VISIBILITY_LOGGED_IN => __( 'Nur angemeldete Benutzer', 'loheide-rights-management' ),
			LRM_Rule::VISIBILITY_ROLES     => __( 'Nur ausgewählte Rollen (unten bearbeiten)', 'loheide-rights-management' ),
			LRM_Rule::VISIBILITY_PUBLIC    => __( 'Öffentlich', 'loheide-rights-management' ),
		);

		$html = '<select name="attachments[' . (int) $post->ID . '][lrm_visibility]" id="attachments-' . (int) $post->ID . '-lrm_visibility">';

		foreach ( $options as $value => $label ) {
			$html .= '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}

		$html .= '</select>';

		$parent = $post->post_parent ? get_the_title( $post->post_parent ) : '';

		if ( $parent ) {
			$html .= '<p class="description">' . sprintf(
				/* translators: %s: Titel der Seite. */
				esc_html__( 'Ohne eigene Regel gilt die Regel von „%s“.', 'loheide-rights-management' ),
				esc_html( $parent )
			) . '</p>';
		}

		$html .= '<p class="description"><a href="' . esc_url( (string) get_edit_post_link( $post->ID ) ) . '">' . esc_html__( 'Alle Zugriffsrechte bearbeiten', 'loheide-rights-management' ) . '</a></p>';

		$fields['lrm_visibility'] = array(
			'label' => __( 'Zugriff', 'loheide-rights-management' ),
			'input' => 'html',
			'html'  => $html,
		);

		return $fields;
	}

	/**
	 * Auswahl aus dem Medienfenster speichern.
	 *
	 * @param array $post       Anhangsdaten.
	 * @param array $attachment Eingaben.
	 * @return array
	 */
	public function save_attachment_field( $post, $attachment ) {
		if ( ! isset( $attachment['lrm_visibility'] ) || ! current_user_can( LRM_Roles::CAP_MANAGE ) ) {
			return $post;
		}

		$post_id = isset( $post['ID'] ) ? (int) $post['ID'] : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return $post;
		}

		$value = sanitize_key( $attachment['lrm_visibility'] );

		if ( '' === $value ) {
			update_post_meta( $post_id, LRM_Rule::META_ENABLED, 0 );
		} else {
			update_post_meta( $post_id, LRM_Rule::META_ENABLED, 1 );
			update_post_meta( $post_id, LRM_Rule::META_VISIBILITY, LRM_Rule::sanitize_visibility( $value ) );
		}

		LRM_Access::flush( $post_id );

		return $post;
	}

	/**
	 * Spalte in der Medienliste.
	 *
	 * @param array $columns Spalten.
	 * @return array
	 */
	public function media_column( $columns ) {
		if ( LRM_Settings::get( 'protect_uploads' ) ) {
			$columns['lrm_access'] = __( 'Zugriff', 'loheide-rights-management' );
		}

		return $columns;
	}

	/**
	 * Inhalt der Spalte.
	 *
	 * @param string $column  Spalte.
	 * @param int    $post_id Anhang-ID.
	 */
	public function media_column_content( $column, $post_id ) {
		if ( 'lrm_access' !== $column ) {
			return;
		}

		$file = get_post_meta( $post_id, '_wp_attached_file', true );

		if ( $file && self::is_whitelisted( $file ) ) {
			echo '<span class="lrm-badge lrm-badge--public"><span class="dashicons dashicons-unlock"></span> ' . esc_html__( 'freigegeben', 'loheide-rights-management' ) . '</span>';
			return;
		}

		$rule = LRM_Access::get_effective_rule( $post_id );

		if ( ! $rule->enabled && 'login' === LRM_Settings::get( 'uploads_mode' ) ) {
			echo '<span class="lrm-badge lrm-badge--login"><span class="dashicons dashicons-admin-users"></span> ' . esc_html__( 'nur angemeldet', 'loheide-rights-management' ) . '</span>';
			return;
		}

		echo LRM_Admin::render_status_badge( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
