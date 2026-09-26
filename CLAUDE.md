# Arbeitsregeln für dieses Repository

## Was das Plugin ist

**LOHEIDE.EU WP Rights Management** – WordPress-Plugin, das komplette Seiten und
Dateien im Frontend nach Anmeldung und WordPress-Rollen freigibt oder sperrt.

Der Grundsatz, an dem sich jede Änderung messen lassen muss:
**Eine gesperrte Rolle beendet die Prüfung sofort** – auch gegen eine zweite,
freigegebene Rolle des Benutzers und auch gegen das Umgehungsrecht der
Administratoren. Die Reihenfolge in `LRM_Access::evaluate()` ist verbindlich.

## Bei jeder Änderung

1. **README.md mitpflegen.** Neue oder geänderte Funktionen gehören in die
   README, einschließlich Screenshots, wenn sich die Oberfläche ändert. Auch
   `readme.txt` (WordPress-Format) aktuell halten.
2. **Tests ausführen und erweitern:**
   ```bash
   php tests/test-access.php     # Zugriffslogik
   php tests/test-media.php      # Dateischutz
   php tests/test-backend.php    # Backend-Rechte
   php tests/test-bootstrap.php  # Laden, Kopfdaten, Deinstallation
   ```
   Neue Logik braucht neue Zusicherungen. Beide Sätze müssen grün sein.
3. **Texte übersetzbar halten:** Neue Zeichenketten in `__( '…', 'loheide-rights-management' )`
   kapseln und danach `php tools/make-pot.php` ausführen – die CI prüft, ob
   `languages/*.pot` zum Quelltext passt.
4. **Syntax prüfen:** `for f in $(find . -name '*.php' -not -path './.git/*'); do php -l "$f"; done`
5. **In den Hauptbranch mergen** und beide Branches pushen.

Die GitHub Action `.github/workflows/tests.yml` führt dasselbe bei jedem Push
gegen PHP 7.4, 8.1 und 8.3 aus.

## Änderungen an einer echten Installation prüfen

```bash
php tools/demo-setup.php /pfad/zur/wordpress-installation
```

Legt Rollen, Seiten, Kategorien, Beiträge, Benutzer und Regeln an (Passwort
aller Demo-Benutzer: `demo1234`). Danach als Administrator anmelden und unter
„Rechte“ prüfen; für die Sicht einer beschränkten Rolle mit `demo.mav` oder
`demo.kunst` anmelden.

## Konventionen

- **Sprache:** Oberfläche, Kommentare und Commit-Nachrichten auf Deutsch.
  Strings in `__( '…', 'loheide-rights-management' )` kapseln.
- **Code:** WordPress Coding Standards, Klassen mit Präfix `LRM_`, keine
  Namespaces, PHP 7.4 aufwärts, keine Abhängigkeiten, kein Build-Schritt.
- **Sicherheit:** Eingaben bereinigen, Ausgaben maskieren, Nonces und
  Fähigkeitsprüfungen (`LRM_Roles::CAP_MANAGE`) bei jeder Schreiboperation.
  Dateipfade immer über `realpath` gegen den Uploads-Ordner prüfen.
- **Backend-Rechte:** Verborgene Menüpunkte sind kein Schutz. Jede Beschränkung
  muss zusätzlich serverseitig greifen – über `map_meta_cap`, gefilterte Listen,
  eine Sperre beim Direktaufruf und die REST-Schnittstelle. Der Block-Editor
  arbeitet über REST, wo `is_admin()` nicht greift. Menüs freigegebener
  Inhaltstypen dürfen nie automatisch verborgen werden: WordPress sperrt
  Seiten, die in keinem Menü stehen. Was im Menü stehen bleibt, muss auch
  aufrufbar sein – der Direktaufruf-Schutz prüft dieselbe Schutzliste wie das
  Ausblenden. Verborgen wird zweimal (`admin_menu` und `admin_head`), weil
  Plugins ihre Menüpunkte teils sehr spät einhängen; ein stehengebliebener
  Unterpunkt wird sonst zum Ziel des übergeordneten Menüs.
  Fest geschützt sind das Dashboard (`index.php`) und die eigenen Seiten des
  Plugins für Benutzer mit `LRM_Roles::CAP_MANAGE` – ein Werkzeug, das seinen
  eigenen Zugang sperren kann, lässt sich nicht mehr zurücknehmen. Alles andere
  muss abschaltbar bleiben.
- **Gemeinsame Verwaltungsseiten:** Die Listen aller Inhaltstypen laufen über
  `edit.php` und `post-new.php`. Fehlt `edit_posts`, merkt sich WordPress die
  Datei in `$_wp_menu_nopriv` und weist danach jede Liste ab – auch die eines
  freigegebenen Typs. Die Sperre wird für freigegebene Typen über
  `custom_menu_order` gelöst: Der Filter liegt hinter dem Menüaufbau und vor
  WordPress' Zugriffsprüfung, die am Ende von `menu.php` abbricht, lange vor
  `admin_init`.
- **Fähigkeiten:** Die Deaktivierung nimmt den Rollen ihre Fähigkeiten
  (`revoke_all_capabilities`), die Aktivierung gibt sie zurück
  (`restore_capabilities`) – sonst stünden die Regeln da und blieben wirkungslos.
  Fähigkeiten, die nur bei der Aktivierung vergeben werden, fehlen nach einem
  Upload über FTP: `CAP_MANAGE` hat deshalb ein Netz über `user_has_cap` für
  alle mit `manage_options`.
- **Mehrere Rollen:** Freigaben addieren sich (die weiter gefasste gewinnt),
  Sperren ebenfalls (was eine Rolle ausblendet, bleibt ausgeblendet). Das hält
  den Grundsatz „Sperre gewinnt“ auch im Backend ein, ohne eine strenge
  Zweitrolle zur Totalsperre zu machen. Im Backend gilt zusätzlich: Eine Rolle
  **ohne** eingeschaltete Regel ist die weitestgehende Freigabe und hebt die
  Beschränkung der übrigen Rollen auf – sonst legt eine Nebenrolle wie
  „Abonnent“ jede noch nicht konfigurierte Arbeitsrolle lahm. Umgekehrt gibt eine
  Rolle mit „Kein Zugang zum Verwaltungsbereich“ nichts frei: Öffnet eine zweite
  Rolle den Zugang, bleibt von ihr kein Menüpunkt und kein Dashboard-Bereich
  übrig – sonst wäre eine vollständige Sperre großzügiger als eine teilweise.
  Deshalb ist `known_menus` beim Zusammenführen die Schnittmenge der Rollen, die
  neue Menüs verbergen, nie ihre Vereinigung. Im Frontend bleibt es beim harten
  Vorrang der Sperre.
- **Branding:** Der Hinweis „Entwickelt von LOHEIDE.EU“ erscheint im Kopf und
  Fuß der Plugin-Seiten, im Editor-Bereich, in der Plugin-Liste und in der
  Werkzeugleiste. Im Frontend ist er abschaltbar.

## Screenshots

Die Bilder in `docs/images/` stammen aus einer echten WordPress-Installation.
Wird die Oberfläche geändert, sind die betroffenen Bilder neu aufzunehmen und
auf höchstens 1500 Pixel Breite zu verkleinern.

## Aufbau

| Datei | Zweck |
| --- | --- |
| `includes/class-lrm-access.php` | Kern: Auswertung der Zugriffsrechte |
| `includes/class-lrm-rule.php` | Regel eines Inhalts |
| `includes/class-lrm-roles.php` | Rollen inklusive virtueller Gast-Rolle |
| `includes/class-lrm-media.php` | Dateischutz im Uploads-Ordner |
| `includes/class-lrm-backend.php` | Datenmodell der Backend-Rechte |
| `includes/class-lrm-backend-guard.php` | Durchsetzung im Verwaltungsbereich |
| `includes/class-lrm-backend-admin.php` | Oberfläche der Backend-Rechte |
| `tools/demo-setup.php` | Baut eine Demo-Umgebung zum Prüfen auf |
| `includes/class-lrm-frontend.php` | Durchsetzung im Frontend |
| `includes/class-lrm-metabox.php` | Bereich im Editor, Sammelbearbeitung |
| `includes/class-lrm-admin.php` | Verwaltungsseiten |
| `includes/class-lrm-settings.php` | Einstellungen |
