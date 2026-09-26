# Umstieg von eigenem Code

Vieles, was dieses Plugin übernimmt, wurde vorher in der `functions.php` eines
Child-Themes gelöst: Menüpunkte ausblenden, Dashboard leeren, Seiten und
Kategorien je Rolle begrenzen. Dieser Ordner hält ein reales Beispiel fest.

`functions.php` ist die bereinigte Fassung einer gewachsenen Theme-Datei. Am
Dateiende steht, welcher Block entfernt wurde und welche Einstellung ihn
ersetzt.

## Was wohin wandert

| Vorher in der functions.php | Jetzt im Plugin |
| --- | --- |
| `remove_menu_page()` je Rolle | Rechte → Backend → Sichtbare Menüpunkte |
| `remove_meta_box( …, 'dashboard', … )` | Rechte → Backend → Bereiche auf dem Dashboard |
| `pre_get_posts` mit `$query->set( 'cat', … )` | Rechte → Backend → Beiträge → Nach Kategorie |
| `parse_query` mit `page_id` oder `post__in` | Rechte → Backend → Seiten → Nur ausgewählte |
| `remove_node( 'new-content' )` | geschieht automatisch, sobald das Anlegen nicht erlaubt ist |
| Weiterleitung auf die Anmeldung | Rechte → Regel der Seite, Aktion „Zur Anmeldung weiterleiten" |
| `nocache_headers()` für geschützte Seiten | setzt das Plugin selbst |

## Eigene Inhaltstypen vorbereiten

`fl-verleihsystem.php` zeigt, was ein Plugin mit eigenem Inhaltstyp braucht,
damit sich der Zugriff darauf **getrennt** vergeben lässt.

Der Kern sind zwei Zeilen bei `register_post_type()`:

```php
'capability_type' => array( 'bv_objekt', 'bv_objekte' ),
'map_meta_cap'    => true,
```

Ohne sie nutzt der Inhaltstyp die Rechte gewöhnlicher Beiträge (`edit_posts`
und so fort). Wer die Objekte bearbeiten darf, darf dann auch Beiträge – und
umgekehrt. Mit eigenen Rechten (`edit_bv_objekte` …) erscheint der Typ in der
Rechteverwaltung als eigener Eintrag und lässt sich einzeln zuweisen.

Zwei Dinge gehören dazu:

1. **Die Rolle Administrator braucht die neuen Rechte.** Sonst sind die
   vorhandenen Inhalte zwar noch da, aber für niemanden mehr erreichbar. Das
   Beispiel vergibt sie bei der Aktivierung und einmalig über eine gespeicherte
   Versionsnummer, damit es auch bei einer Aktualisierung greift.

   > [!WARNING]
   > **Der Zeitpunkt entscheidet.** Die Vergabe muss an `init` hängen, nicht an
   > `admin_init`: Das Verwaltungsmenü wird vor `admin_init` gebaut, die Rechte
   > kämen für den laufenden Aufruf zu spät und der Menüpunkt fehlte. Wird die
   > Versionsnummer dabei gespeichert, obwohl die Vergabe nicht griff, bleibt er
   > dauerhaft weg. Deshalb speichert das Beispiel die Nummer erst nach
   > erfolgreicher Vergabe, frischt die Rechte des angemeldeten Benutzers sofort
   > auf (`WP_User::for_site()` – er trägt seine Rechte als Kopie mit sich) und
   > hält über `user_has_cap` als Sicherheitsnetz fest: Wer die Website verwalten
   > darf (`manage_options`), behält die Objekt-Rechte in jedem Fall.
2. **An den Daten ändert sich nichts.** Inhalte, Bilder und Metafelder bleiben
   unverändert – die Umstellung betrifft nur die Rechteprüfung.

## Worauf beim Aufräumen zu achten ist

- **Erst einstellen, dann löschen.** Solange beides parallel läuft, passiert
  nichts Schlimmes – die Beschränkungen addieren sich.
- **Fähigkeiten nicht von Hand vergeben.** Das Plugin setzt die nötigen Rechte
  selbst und nimmt sie beim Abschalten zurück. Ein Capability-Plugin, das
  dieselben Rechte dauerhaft setzt, hebelt diese Rücknahme aus.
- **Rollenprüfungen wie `current_user_can( 'mav' )`** funktionieren zwar, prüfen
  aber eine Rolle wie eine Fähigkeit. Im Plugin wird stattdessen die Rolle
  selbst ausgewählt.
