=== LOHEIDE.EU WP Rights Management ===
Contributors: loheide
Tags: access control, restrict content, membership, roles, permissions
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Seitenbasierte Zugriffsrechte nach Anmeldung und WordPress-Rollen. Gesperrte Rollen haben immer Vorrang. Entwickelt von LOHEIDE.EU.

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

= Schützt das Plugin auch Dateien im Uploads-Ordner? =

Nein. Geschützt wird die Ausgabe der Seite, nicht die direkt aufgerufene Datei.

== Screenshots ==

1. Übersicht der geschützten Inhalte mit Kennzahlen und Filtern.
2. Rollenmatrix und Zugriffssimulation.
3. Einstellungen.
4. Bereich „Zugriffsrechte“ im Editor.
5. Hinweis im Frontend.

== Changelog ==

= 1.0.0 =
* Erste Veröffentlichung.
