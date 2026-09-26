=== LOHEIDE.EU WP Rights Management ===
Contributors: loheide
Tags: access control, restrict content, membership, roles, permissions
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Zugriffsrechte für Seiten, Dateien und den Verwaltungsbereich nach WordPress-Rollen. Gesperrte Rollen haben immer Vorrang. Entwickelt von LOHEIDE.EU.

== Description ==

Mit LOHEIDE.EU WP Rights Management werden komplette Seiten im Frontend
freigegeben oder gesperrt – auf Basis der von WordPress bereitgestellten Rollen.

Der Grundsatz: eine gesperrte Rolle beendet die Prüfung sofort. Das gilt auch
dann, wenn dieselbe Person über eine zweite, freigegebene Rolle verfügt, und
auch gegenüber dem Umgehungsrecht der Administratoren.

* Drei Sichtbarkeitsmodi: öffentlich, nur angemeldet, nur ausgewählte Rollen.
* Sperrliste mit Vorrang – wirkt in jedem Modus, auch auf öffentlichen Seiten.
* Virtuelle Rolle „Gast“ für nicht angemeldete Besucher.
* Vererbung auf Unterseiten; Sperren der Seitenkette bleiben bestehen.
* Reaktion wählbar: Hinweis mit Anmeldeformular, Anmeldung, eigene Adresse, 404.
* Wirkt in Einzelansicht, Menüs, Seitenlisten, Suche, Feeds, XML-Sitemap,
  Kommentaren und REST-API.
* Übersicht mit Kennzahlen, Rollenmatrix und Zugriffssimulation je Rolle.
* Sammelbearbeitung für mehrere Seiten.
* Dateischutz für den Uploads-Ordner: genereller Block ohne Anmeldung,
  Ausnahmeliste (Whitelist) und Regeln je Datei. Website-Logo und -Icon werden
  automatisch freigegeben, damit die Anmeldeseite vollständig bleibt.
* Backend-Rechte je Rolle und Inhaltstyp: nichts, alles, einzeln zugewiesene
  Inhalte oder alles innerhalb bestimmter Kategorien. Gilt auch für eigene
  Inhaltstypen anderer Plugins. Dazu Rechte zum Anlegen und Löschen.
* Menüpunkte und Dashboard-Bereiche je Rolle ein- und ausblenden, auf Wunsch
  auch später hinzukommende Menüs. Ganze Rollen lassen sich vom
  Verwaltungsbereich aussperren. Die nötigen Fähigkeiten vergibt das Plugin
  selbst und nimmt sie beim Abschalten zurück.
* Shortcodes für einzelne Abschnitte.

== Installation ==

1. Ordner nach wp-content/plugins/ kopieren.
2. Plugin im Backend aktivieren.
3. Unter „Rechte → Einstellungen“ die Inhaltstypen festlegen.

== Frequently Asked Questions ==

= Was passiert, wenn eine Rolle freigegeben und gleichzeitig gesperrt ist? =

Die Sperre gewinnt. Das gilt auch, wenn der Benutzer über eine weitere,
freigegebene Rolle verfügt.

= Sehen Administratoren gesperrte Seiten? =

Ja, über das Umgehungsrecht – es sei denn, ihre Rolle wurde ausdrücklich
gesperrt. Das Umgehungsrecht kann in den Einstellungen abgeschaltet werden.

= Werden eigene Rollen unterstützt? =

Ja. Alle im System registrierten Rollen stehen automatisch zur Auswahl.

= Kann eine Rolle nur eine einzelne Seite bearbeiten? =

Ja. Unter „Rechte → Backend“ werden einer Rolle die Seiten zugewiesen, die sie
bearbeiten darf. Alle anderen Seiten erscheinen nicht in der Liste und sind auch
über die Adresszeile gesperrt. Dasselbe gilt für Beiträge über die Kategorie.

= Funktioniert das auch mit den Inhalten anderer Plugins? =

Ja, sofern das Plugin einen regulären Inhaltstyp registriert. Dieser erscheint
in der Rollenkonfiguration und lässt sich genauso zuweisen wie Seiten und
Beiträge – etwa „alle Inhalte“ für die Gruppe, die ein Fachverfahren betreut.

= Kann ich ein Plugin-Menü einer einzelnen Rolle vorbehalten? =

Ja. Blenden Sie den Menüpunkt bei allen anderen Rollen aus. Administratoren
sehen ihn weiterhin. Mit der Option „Später hinzukommende Menüpunkte ausblenden“
bleiben auch neu installierte Plugins zunächst verborgen.

= Schützt das Plugin auch Dateien im Uploads-Ordner? =

Ja, sobald der Dateischutz eingeschaltet ist. Das Plugin schreibt dann eine
Regel in die .htaccess des Uploads-Ordners, sodass jede Anfrage geprüft wird.
Für nginx wird die passende Regel zum Eintragen angezeigt. Eine Schaltfläche
prüft, ob der Schutz tatsächlich greift.

= Bleibt das Logo auf der Anmeldeseite sichtbar? =

Ja. Website-Logo und Website-Icon werden automatisch freigegeben. Weitere
Ausnahmen lassen sich als Muster hinterlegen, etwa „briefkopf.png“ oder
„branding/*“.

== Screenshots ==

1. Übersicht der geschützten Inhalte mit Kennzahlen und Filtern.
2. Rollenmatrix und Zugriffssimulation.
3. Einstellungen.
4. Bereich „Zugriffsrechte“ im Editor.
5. Hinweis im Frontend.
6. Dateischutz mit Ausnahmeliste und Statusprüfung.
7. Backend-Rechte je Rolle und Inhaltstyp.

== Changelog ==

= 1.0.0 =
* Erste Veröffentlichung.
