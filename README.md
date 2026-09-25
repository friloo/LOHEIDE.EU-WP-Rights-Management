# LOHEIDE Rechteverwaltung

WordPress-Plugin, mit dem komplette Seiten im Frontend nach Anmeldung und
WordPress-Rollen freigegeben oder gesperrt werden.

**Grundsatz: Gesperrte Rollen haben immer Vorrang.** Wer die Rolle „Abonnent“
(freigegeben) und gleichzeitig eine gesperrte Rolle besitzt, erhält keinen
Zugriff.

## Funktionen

* **Drei Sichtbarkeitsmodi pro Seite** – öffentlich, nur angemeldet oder nur
  ausgewählte Rollen.
* **Sperrliste mit Vorrang** – gesperrte Rollen werden vor allem anderen
  geprüft, auch vor dem Umgehungsrecht der Administratoren.
* **Virtuelle Rolle „Gast“** – nicht angemeldete Besucher sind als eigene Rolle
  adressierbar, dadurch lässt sich auch eine öffentliche Seite auf
  „sichtbar, wenn angemeldet“ umstellen.
* **Ausschließlich WordPress-Rollen** – es werden keine eigenen Rollen
  eingeführt. Rollen aus anderen Plugins erscheinen automatisch.
* **Vererbung** – Unterseiten übernehmen die Regel der übergeordneten Seite.
  Sperren der gesamten Seitenkette bleiben bestehen und können von einer
  Unterseite nicht aufgehoben werden.
* **Reaktion frei wählbar** – Hinweis statt Inhalt (mit Anmeldeformular),
  Weiterleitung zur Anmeldung, eigene Zieladresse oder 404.
* **Durchsetzung an allen Stellen** – Einzelansicht, Inhalt, Auszug, Menüs,
  Seitenlisten, Suche und Archive, Feeds, Kommentare und REST-API.
* **Verwaltungsbereich** – Übersicht mit Kennzahlen, Rollenmatrix,
  Zugriffssimulation je Rolle und Einstellungen.
* **Sammelbearbeitung** – Rechte mehrerer Seiten in einem Schritt setzen.
* **Shortcodes** für einzelne Abschnitte innerhalb einer Seite.

## Installation

1. Den Ordner dieses Repositorys als `loheide-rights-management` nach
   `wp-content/plugins/` kopieren (oder das Repository direkt dorthin klonen).
2. Im Backend unter **Plugins** aktivieren.
3. Unter **Rechte → Einstellungen** die Inhaltstypen und das Standardverhalten
   festlegen.

Bei der Aktivierung erhält die Rolle „Administrator“ die Fähigkeiten
`lrm_manage_permissions` (Verwaltung) und `lrm_bypass_restrictions`
(Umgehungsrecht).

## Bedienung

Im Editor einer Seite erscheint der Bereich **Zugriffsrechte**:

1. **Zugriffsrechte aktivieren** – Hauptschalter. Ist er aus, bleibt die Seite
   frei zugänglich, sofern keine Regel geerbt wird.
2. **Grundsichtbarkeit** – öffentlich, nur angemeldet oder nur ausgewählte
   Rollen.
3. **Rollen** – links die freigegebenen, rechts die gesperrten Rollen. Rollen,
   die in beiden Listen stehen, werden durchgestrichen dargestellt: hier gilt
   die Sperre.
4. **Verhalten bei fehlendem Zugriff** – pro Seite überschreibbar.
5. **Weitere Optionen** – in Menüs verbergen, Vererbung übernehmen, Regel an
   Unterseiten weitergeben.

Unterhalb der Rollenauswahl steht jederzeit eine Klartext-Zusammenfassung der
Regel, die sich bei jeder Änderung aktualisiert.

## Reihenfolge der Auswertung

```
1. Gesperrte Rolle des Benutzers?        -> Zugriff verweigert  (hat immer Vorrang)
2. Umgehungsrecht (z. B. Administrator)? -> Zugriff erlaubt
3. Keine Regel aktiv?                    -> Zugriff erlaubt
4. Grundsichtbarkeit:
   - öffentlich       -> Zugriff erlaubt
   - nur angemeldet   -> Zugriff, wenn angemeldet
   - nur Rollen       -> Zugriff, wenn eine Rolle freigegeben ist
```

Schritt 1 läuft bewusst vor Schritt 2: Wird eine Rolle ausdrücklich gesperrt,
gilt das auch für Administratoren. Der Zugang zum Backend bleibt davon
unberührt, gesperrt wird nur die Ausgabe im Frontend.

### Vererbung

* Eine Unterseite ohne eigene Regel übernimmt die Regel der nächstgelegenen
  übergeordneten Seite (solange dort „für Unterseiten anwenden“ aktiv ist).
* Gesperrte Rollen werden über die **gesamte** Seitenkette gesammelt. Eine
  Unterseite kann eine Sperre der übergeordneten Seite nicht aufheben.
* Die Option „Regeln übergeordneter Seiten übernehmen“ schaltet die Vererbung
  für eine Seite vollständig ab.

## Shortcodes

```
[lrm_restrict roles="editor,author"]Nur für Redakteure und Autoren.[/lrm_restrict]
[lrm_restrict deny="subscriber"]Für Abonnenten unsichtbar.[/lrm_restrict]
[lrm_restrict logged_in="yes"]Nur für angemeldete Besucher.[/lrm_restrict]
[lrm_restrict roles="editor" message="Bitte anmelden."]Inhalt[/lrm_restrict]
[lrm_guest]Nur für nicht angemeldete Besucher.[/lrm_guest]
```

Auch hier gilt: `deny` schlägt `roles`.

## Hooks für Entwickler

| Hook | Typ | Zweck |
| --- | --- | --- |
| `lrm_selectable_roles` | Filter | Auswählbare Rollen ergänzen oder entfernen. |
| `lrm_user_roles` | Filter | Rollen, mit denen ein Benutzer geprüft wird. |
| `lrm_effective_rule` | Filter | Wirksame Regel eines Inhalts anpassen. |
| `lrm_check_access` | Filter | Prüfergebnis überschreiben. |
| `lrm_protected_post_types` | Filter | Geschützte Inhaltstypen anpassen. |
| `lrm_denied_action` | Filter | Reaktion bei verweigertem Zugriff ändern. |
| `lrm_denied_notice` | Filter | HTML der Hinweisbox ersetzen. |
| `lrm_loaded` | Action | Plugin ist vollständig geladen. |

Beispiel – eine Rolle immer durchlassen:

```php
add_filter( 'lrm_check_access', function ( $result, $post_id, $user ) {
    if ( $user && in_array( 'support', (array) $user->roles, true ) ) {
        $result['allowed'] = true;
        $result['reason']  = 'bypass';
    }

    return $result;
}, 10, 3 );
```

## Tests

Die Zugriffslogik ist ohne WordPress-Installation prüfbar:

```bash
php tests/test-access.php
```

Das Skript deckt unter anderem den Vorrang der Sperrliste, das Umgehungsrecht,
die virtuelle Gast-Rolle und die Vererbung über mehrere Seitenebenen ab.

## Gespeicherte Daten

Pro Inhalt werden folgende Meta-Felder verwendet:

`_lrm_enabled`, `_lrm_visibility`, `_lrm_allowed_roles`, `_lrm_denied_roles`,
`_lrm_inherit`, `_lrm_propagate`, `_lrm_action`, `_lrm_redirect_url`,
`_lrm_message`, `_lrm_hide`

Die globalen Einstellungen liegen in der Option `lrm_settings`. Bei der
Deinstallation werden Meta-Felder, Option und die vergebenen Fähigkeiten
entfernt.

## Hinweise zum Betrieb

* **Caching:** Seiten mit Zugriffsbeschränkung dürfen nicht als statische
  Seite für alle Besucher zwischengespeichert werden. In Caching-Plugins
  betroffene Seiten ausnehmen oder das Caching für angemeldete Benutzer
  abschalten.
* **Medien:** Das Plugin schützt Seiten und deren Ausgabe, keine direkt
  aufgerufenen Dateien im Uploads-Ordner.
* **Suchmaschinen:** Gesperrte Inhalte liefern je nach Einstellung den Status
  403 oder 404.

## Lizenz

GPL-2.0-or-later
