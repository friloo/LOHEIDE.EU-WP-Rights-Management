=== LOHEIDE Rechteverwaltung ===
Contributors: loheide
Tags: access control, restrict content, membership, roles, permissions
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Seitenbasierte Zugriffsrechte nach Anmeldung und WordPress-Rollen. Gesperrte Rollen haben immer Vorrang.

== Description ==

Mit der LOHEIDE Rechteverwaltung werden komplette Seiten im Frontend freigegeben
oder gesperrt – auf Basis der von WordPress bereitgestellten Rollen.

* Drei Sichtbarkeitsmodi: öffentlich, nur angemeldet, nur ausgewählte Rollen.
* Sperrliste mit Vorrang: eine gesperrte Rolle schlägt jede Freigabe.
* Virtuelle Rolle „Gast“ für nicht angemeldete Besucher.
* Vererbung auf Unterseiten, Sperren der Seitenkette bleiben bestehen.
* Reaktion wählbar: Hinweis mit Anmeldeformular, Anmeldung, eigene Adresse, 404.
* Wirkt in Einzelansicht, Menüs, Listen, Suche, Feeds, Kommentaren und REST-API.
* Übersicht mit Kennzahlen, Rollenmatrix und Zugriffssimulation.
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

== Changelog ==

= 1.0.0 =
* Erste Veröffentlichung.
