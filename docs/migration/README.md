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

## Worauf beim Aufräumen zu achten ist

- **Erst einstellen, dann löschen.** Solange beides parallel läuft, passiert
  nichts Schlimmes – die Beschränkungen addieren sich.
- **Fähigkeiten nicht von Hand vergeben.** Das Plugin setzt die nötigen Rechte
  selbst und nimmt sie beim Abschalten zurück. Ein Capability-Plugin, das
  dieselben Rechte dauerhaft setzt, hebelt diese Rücknahme aus.
- **Rollenprüfungen wie `current_user_can( 'mav' )`** funktionieren zwar, prüfen
  aber eine Rolle wie eine Fähigkeit. Im Plugin wird stattdessen die Rolle
  selbst ausgewählt.
