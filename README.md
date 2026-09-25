<div align="center">

# LOHEIDE.EU WP Rights Management

**Komplette Seiten im Frontend nach Anmeldung und WordPress-Rollen freigeben oder sperren.**

[![Version](https://img.shields.io/badge/Version-1.0.0-3858e9)](#)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759b)](#)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)](#)
[![Lizenz](https://img.shields.io/badge/Lizenz-GPL--2.0--or--later-green)](#lizenz)
[![Tests](https://img.shields.io/badge/Logiktests-34%20Pr%C3%BCfungen-16a34a)](#tests)

Entwickelt von [LOHEIDE.EU](https://loheide.eu)

</div>

<div align="center">
  <img src="docs/images/01-uebersicht.png" alt="Übersicht der geschützten Inhalte mit Kennzahlen, Filtern und Rollen-Auszeichnung" width="900">
</div>

---

## Der Grundsatz: Sperren gewinnen immer

Die meisten Rechte-Plugins arbeiten nur mit Freigaben. Sobald ein Benutzer **mehrere Rollen** besitzt, wird das unübersichtlich – und im Zweifel zu großzügig.

Dieses Plugin dreht die Logik um: **Eine gesperrte Rolle beendet die Prüfung sofort.** Das gilt auch dann, wenn dieselbe Person über eine zweite, freigegebene Rolle verfügt.

| Benutzer hat die Rollen | Seitenregel | Ergebnis |
| --- | --- | --- |
| Abonnent | freigegeben: Abonnent | ✅ Zugriff |
| Abonnent + **Gesperrtes Konto** | freigegeben: Abonnent · gesperrt: Gesperrtes Konto | ⛔ **Kein Zugriff** |
| Administrator | keine Sperre | ✅ Zugriff über Umgehungsrecht |
| Administrator | **gesperrt: Administrator** | ⛔ **Kein Zugriff** |
| Gast (nicht angemeldet) | Modus „Nur angemeldet“ | ⛔ Weiterleitung zur Anmeldung |

> Die Sperre steht bewusst **vor** dem Umgehungsrecht der Administratoren. Wer eine Rolle ausdrücklich sperrt, meint es auch so. Der Zugang zum Backend bleibt davon unberührt – gesperrt wird ausschließlich die Ausgabe im Frontend.

---

## Inhalt

- [Funktionen](#funktionen)
- [Screenshots](#screenshots)
- [Installation](#installation)
- [Bedienung](#bedienung)
- [Auswertungsreihenfolge](#auswertungsreihenfolge)
- [Vererbung auf Unterseiten](#vererbung-auf-unterseiten)
- [Einstellungen](#einstellungen)
- [Shortcodes](#shortcodes)
- [Für Entwickler](#für-entwickler)
- [Tests](#tests)
- [Betrieb](#betrieb)
- [Grenzen](#grenzen)
- [Datenhaltung](#datenhaltung)

---

## Funktionen

| | |
| --- | --- |
| 🔒 **Drei Sichtbarkeitsmodi** | Öffentlich · Nur angemeldet · Nur ausgewählte Rollen |
| ⛔ **Sperrliste mit Vorrang** | Wirkt in **jedem** Modus – auch auf öffentlichen Seiten |
| 👤 **Virtuelle Rolle „Gast“** | Nicht angemeldete Besucher sind wie eine Rolle adressierbar |
| 🧩 **Nur WordPress-Rollen** | Keine eigene Rollenwelt. Rollen aus anderen Plugins erscheinen automatisch |
| 🌳 **Vererbung** | Unterseiten übernehmen die Regel; Sperren der Kette bleiben bestehen |
| 🚪 **Vier Reaktionen** | Hinweis mit Anmeldeformular · Anmeldung · eigene Adresse · 404 |
| 👁️ **Konsequent versteckt** | Menüs, Seitenlisten, Suche, Archive, Feeds, XML-Sitemap, REST-API |
| 📊 **Übersicht & Simulation** | Kennzahlen, Rollenmatrix und „Was sieht Rolle X?“ auf Knopfdruck |
| ⚡ **Sammelbearbeitung** | Rechte für viele Seiten in einem Schritt setzen |
| 🧱 **Shortcodes** | Einzelne Abschnitte innerhalb einer Seite schützen |

---

## Screenshots

> Alle Aufnahmen stammen aus einer laufenden WordPress-Installation mit Demo-Inhalten.

### Bereich „Zugriffsrechte“ im Editor

Freigaben links, Sperren rechts. Eine Rolle in beiden Listen wird durchgestrichen – das Verbot gewinnt. Unter der Auswahl steht jederzeit das Ergebnis im Klartext.

<img src="docs/images/05-metabox.png" alt="Bereich Zugriffsrechte mit Modus-Auswahl, Rollenspalten und Klartext-Zusammenfassung" width="800">

### Geerbte Sperren sind sichtbar

Eine Unterseite kann Sperren der übergeordneten Seite **nicht** aufheben. Das Plugin weist sie daher ausdrücklich aus:

<img src="docs/images/08-metabox-vererbung.png" alt="Hinweis auf zusätzliche Sperren aus übergeordneten Seiten" width="800">

### In der Editor-Seitenleiste

Standardmäßig sitzt der Bereich in der Seitenleiste – dort ist er auch im Block-Editor sofort sichtbar, ohne die untere Metabox-Leiste aufziehen zu müssen.

<img src="docs/images/09-metabox-seitenleiste.png" alt="Zugriffsrechte in der Seitenleiste des Block-Editors" width="380">

### Rollen im Überblick und Zugriffssimulation

Welche Rolle ist wo freigegeben, wo gesperrt? Und was sieht sie tatsächlich – inklusive Begründung je Seite:

<img src="docs/images/02-rollen.png" alt="Rollenmatrix und Zugriffssimulation für die Rolle Kunde" width="900">

### Einstellungen

<img src="docs/images/03-einstellungen.png" alt="Einstellungsseite mit Inhaltstypen, Standardverhalten und Wirkungsbereich" width="900">

### Statusspalte in der Seitenliste

<img src="docs/images/04-seitenliste.png" alt="Seitenliste mit Spalte Zugriff" width="900">

### Was Besucher sehen

<table>
<tr>
<td width="50%"><img src="docs/images/06-frontend-gast.png" alt="Hinweis mit Anmeldeformular für nicht angemeldete Besucher"></td>
<td width="50%"><img src="docs/images/07-frontend-gesperrte-rolle.png" alt="Hinweis für ein gesperrtes Benutzerkonto"></td>
</tr>
<tr>
<td><b>Gast</b> auf einer geschützten Seite – mit Anmeldeformular und eigenem Hinweistext.</td>
<td><b>Gesperrte Rolle</b> auf einer <i>öffentlichen</i> Seite. Die Sperre greift trotzdem, und die Seite verschwindet aus der Navigation.</td>
</tr>
</table>

---

## Installation

```bash
cd wp-content/plugins
git clone https://github.com/friloo/LOHEIDE.EU-WP-Rights-Management.git loheide-rights-management
```

Alternativ das Verzeichnis als ZIP hochladen. Danach:

1. Unter **Plugins** aktivieren.
2. **Rechte → Einstellungen** öffnen und die Inhaltstypen wählen (Voreinstellung: Seiten).
3. Eine Seite bearbeiten und im Bereich **Zugriffsrechte** die Regel setzen.

Bei der Aktivierung erhält die Rolle *Administrator* die Fähigkeiten `lrm_manage_permissions` (Verwaltung) und `lrm_bypass_restrictions` (Umgehungsrecht).

**Voraussetzungen:** WordPress 5.8+, PHP 7.4+. Keine Abhängigkeiten, kein Build-Schritt.

---

## Bedienung

<table>
<tr><td width="34"><b>1</b></td><td><b>Zugriffsrechte aktivieren</b><br>Hauptschalter. Ist er aus, bleibt die Seite frei zugänglich – sofern keine Regel geerbt wird.</td></tr>
<tr><td><b>2</b></td><td><b>Grundsichtbarkeit wählen</b><br><i>Öffentlich</i>, <i>Nur angemeldet</i> oder <i>Nur ausgewählte Rollen</i>.</td></tr>
<tr><td><b>3</b></td><td><b>Rollen setzen</b><br>Links freigeben, rechts sperren. Konflikte werden markiert, die Sperre gewinnt.</td></tr>
<tr><td><b>4</b></td><td><b>Reaktion festlegen</b> <i>(optional)</i><br>Pro Seite überschreibbar, sonst gilt die globale Einstellung.</td></tr>
<tr><td><b>5</b></td><td><b>Vererbung steuern</b> <i>(optional)</i><br>Regeln übergeordneter Seiten übernehmen und/oder an Unterseiten weitergeben.</td></tr>
</table>

**Mehrere Seiten auf einmal:** In der Seitenliste mehrere Einträge markieren → *Aktion wählen → Bearbeiten → Übernehmen*. Dort lassen sich Modus und Rollen für alle markierten Seiten setzen.

---

## Auswertungsreihenfolge

```text
┌─ 1. Hat der Benutzer eine gesperrte Rolle? ──────────── ⛔ Zugriff verweigert
│      (hat immer Vorrang – auch gegen das Umgehungsrecht)
│
├─ 2. Darf der Benutzer Beschränkungen umgehen? ───────── ✅ Zugriff erlaubt
│      (Fähigkeit lrm_bypass_restrictions, Standard: Administrator)
│
├─ 3. Ist überhaupt eine Regel aktiv? ─────────── nein → ✅ Zugriff erlaubt
│
└─ 4. Grundsichtbarkeit
       ├─ öffentlich ───────────────────────────────────  ✅ Zugriff erlaubt
       ├─ nur angemeldet ──── angemeldet? ───────────────  ✅ / ⛔
       └─ nur Rollen ──────── eine Rolle freigegeben? ───  ✅ / ⛔
```

Jede Entscheidung trägt eine Begründung, die in der Simulation und – für Verwalter – im Frontend-Hinweis sichtbar ist.

---

## Vererbung auf Unterseiten

- Eine Unterseite **ohne** eigene Regel übernimmt die Regel der nächstgelegenen übergeordneten Seite.
- **Sperren werden über die gesamte Seitenkette gesammelt.** Eine Unterseite kann eine Sperre der übergeordneten Seite nicht aufheben – auch nicht mit einer eigenen Regel.
- Die übergeordnete Seite kann die Weitergabe abschalten („Diese Regel auch für Unterseiten anwenden“).
- Die Unterseite kann die Übernahme abschalten („Regeln übergeordneter Seiten übernehmen“). Dann entfallen auch die geerbten Sperren.

```text
Intranet                    Regel: nur angemeldet · gesperrt: Gesperrtes Konto
└── Lohnabrechnungen        eigene Regel: nur Administrator, Redakteur
                            ⇒ wirksam: Administrator + Redakteur,
                               zusätzlich gesperrt: Gesperrtes Konto (geerbt)
```

---

## Einstellungen

| Einstellung | Standard | Wirkung |
| --- | --- | --- |
| Inhaltstypen | Seiten | Für welche Typen der Bereich erscheint |
| Position im Editor | Seitenleiste | Seitenleiste (sofort sichtbar) oder breit unter dem Inhalt |
| Standardverhalten | Hinweis anzeigen | Hinweis · Anmeldung · 404 · Weiterleitung |
| Überschrift & Hinweistext | „Kein Zugriff“ | Global, pro Seite überschreibbar |
| Eigene Anmeldeseite | – | Leer = Standard-Anmeldung von WordPress |
| Aus Menüs entfernen | an | Navigationspunkte ohne Zugriff ausblenden |
| Aus Suche und Listen entfernen | aus | Suchergebnisse, Archive, Seitenlisten |
| REST-API schützen | an | Inhalt und Auszug werden entfernt |
| Feeds schützen | an | Statt des Inhalts erscheint der Hinweistext |
| Kommentare schließen | an | Auf gesperrten Inhalten |
| Umgehungsrecht | an | Administratoren sehen alles – außer ihre Rolle ist gesperrt |
| Vererbung standardmäßig | an | Für neu angelegte Inhalte |
| Status in der Werkzeugleiste | an | Zeigt Redaktionen die wirksame Regel im Frontend |
| Anmeldeformular im Hinweis | an | Direkte Anmeldung auf der gesperrten Seite |
| Hinweis „Zugriffsschutz von LOHEIDE.EU“ | an | Dezente Zeile unter dem Hinweistext |

---

## Shortcodes

Für einzelne Abschnitte **innerhalb** einer Seite:

```
[lrm_restrict roles="editor,author"]Nur für Redakteure und Autoren.[/lrm_restrict]

[lrm_restrict deny="subscriber"]Für Abonnenten unsichtbar.[/lrm_restrict]

[lrm_restrict logged_in="yes"]Nur für angemeldete Besucher.[/lrm_restrict]

[lrm_restrict roles="kunde" message="Bitte melden Sie sich an."]Kundenpreise[/lrm_restrict]

[lrm_guest]Nur für nicht angemeldete Besucher.[/lrm_guest]
```

Auch hier schlägt `deny` jedes `roles`.

---

## Für Entwickler

| Hook | Typ | Zweck |
| --- | --- | --- |
| `lrm_selectable_roles` | Filter | Auswählbare Rollen ergänzen oder entfernen |
| `lrm_user_roles` | Filter | Rollen, mit denen ein Benutzer geprüft wird |
| `lrm_effective_rule` | Filter | Wirksame Regel eines Inhalts anpassen |
| `lrm_check_access` | Filter | Prüfergebnis überschreiben |
| `lrm_protected_post_types` | Filter | Geschützte Inhaltstypen |
| `lrm_denied_action` | Filter | Reaktion bei verweigertem Zugriff |
| `lrm_denied_notice` | Filter | HTML der Hinweisbox |
| `lrm_loaded` | Action | Plugin vollständig geladen |

<details>
<summary><b>Beispiel: eigene Prüfung ergänzen</b></summary>

```php
add_filter( 'lrm_check_access', function ( $result, $post_id, $user ) {
	// Support-Konten sehen alles – sofern sie nicht gesperrt sind.
	if ( 'denied_role' === $result['reason'] ) {
		return $result; // Sperre nicht aushebeln.
	}

	if ( $user && in_array( 'support', (array) $user->roles, true ) ) {
		$result['allowed'] = true;
		$result['reason']  = 'bypass';
	}

	return $result;
}, 10, 3 );
```

</details>

<details>
<summary><b>Beispiel: Zugriff im Theme prüfen</b></summary>

```php
if ( class_exists( 'LRM_Access' ) && ! LRM_Access::can_view( $post_id ) ) {
	echo '<p>Dieser Beitrag ist geschützt.</p>';
}

// Mit Begründung:
$check = LRM_Access::check( $post_id );
echo esc_html( LRM_Access::reason_text( $check ) );
```

</details>

---

## Tests

Die Zugriffslogik läuft ohne WordPress-Installation:

```bash
php tests/test-access.php
```

```
1) Gesperrte Rolle hat Vorrang
  OK   Abonnent + gesperrte Rolle "kunde" erhält keinen Zugriff
  OK   Begründung ist die Rollensperre
  …
Alle 34 Prüfungen erfolgreich.
```

Abgedeckt sind der Vorrang der Sperrliste, das Umgehungsrecht, die virtuelle Gast-Rolle, alle drei Sichtbarkeitsmodi, die Vererbung über mehrere Seitenebenen sowie die Bereinigung ungültiger Eingaben.

---

## Betrieb

> [!IMPORTANT]
> **Caching:** Geschützte Seiten dürfen nicht als statische Kopie für alle Besucher ausgeliefert werden. Das Plugin setzt `nocache_headers()` und die Konstanten `DONOTCACHEPAGE`, `DONOTCACHEOBJECT` und `DONOTCACHEDB`. Die meisten Caching-Plugins beachten das – prüfen Sie es nach der Einrichtung mit einem Testkonto.

> [!NOTE]
> **Statuscodes:** Gesperrte Seiten antworten mit `403` (Hinweis) beziehungsweise `404`. Suchmaschinen nehmen sie damit nicht in den Index; zusätzlich werden sie aus der XML-Sitemap entfernt.

---

## Grenzen

Damit klar ist, was das Plugin **nicht** leistet:

- **Mediendateien** im Uploads-Ordner bleiben über ihre direkte URL erreichbar. Geschützt wird die Seitenausgabe, nicht die Datei. Für echten Dateischutz ist eine Auslieferung über PHP oder eine Server-Regel nötig.
- **Keine Zeitsteuerung** (Zugriff ab/bis Datum) und **keine Rechte für einzelne Benutzer** – ausschließlich Rollen.
- **Sitemaps von SEO-Plugins** (Yoast, Rank Math) verwenden eigene Abfragen; nur die WordPress-eigene Sitemap wird gefiltert.
- **Mehrsprachigkeit:** Übersetzte Seiten (WPML, Polylang) erben die Regel des Originals nicht automatisch.
- Die Übersicht wertet bis zu **500 Regeln** aus; das reicht für typische Websites, nicht für sehr große Portale.

---

## Datenhaltung

Pro Inhalt:

```
_lrm_enabled  _lrm_visibility  _lrm_allowed_roles  _lrm_denied_roles
_lrm_inherit  _lrm_propagate   _lrm_action         _lrm_redirect_url
_lrm_message  _lrm_hide
```

Global: Option `lrm_settings`. Bei der Deinstallation werden Meta-Felder, Option und die vergebenen Fähigkeiten vollständig entfernt.

---

## Lizenz

GPL-2.0-or-later

<div align="center">

**Entwickelt von [LOHEIDE.EU](https://loheide.eu)**

</div>
