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
   php tests/test-access.php   # Zugriffslogik
   php tests/test-media.php    # Dateischutz
   ```
   Neue Logik braucht neue Zusicherungen. Beide Sätze müssen grün sein.
3. **Syntax prüfen:** `for f in $(find . -name '*.php' -not -path './.git/*'); do php -l "$f"; done`
4. **In den Hauptbranch mergen** und beide Branches pushen.

## Konventionen

- **Sprache:** Oberfläche, Kommentare und Commit-Nachrichten auf Deutsch.
  Strings in `__( '…', 'loheide-rights-management' )` kapseln.
- **Code:** WordPress Coding Standards, Klassen mit Präfix `LRM_`, keine
  Namespaces, PHP 7.4 aufwärts, keine Abhängigkeiten, kein Build-Schritt.
- **Sicherheit:** Eingaben bereinigen, Ausgaben maskieren, Nonces und
  Fähigkeitsprüfungen (`LRM_Roles::CAP_MANAGE`) bei jeder Schreiboperation.
  Dateipfade immer über `realpath` gegen den Uploads-Ordner prüfen.
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
| `includes/class-lrm-frontend.php` | Durchsetzung im Frontend |
| `includes/class-lrm-metabox.php` | Bereich im Editor, Sammelbearbeitung |
| `includes/class-lrm-admin.php` | Verwaltungsseiten |
| `includes/class-lrm-settings.php` | Einstellungen |
