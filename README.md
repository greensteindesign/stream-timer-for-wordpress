# Stream Timer

Zeigt einen Twitch- oder YouTube-Stream zeitgesteuert auf deiner WordPress-Seite. Plattform, Channel, Start- und Endzeitpunkt werden im Backend gepflegt. Eingebunden wird per Shortcode `[stream_timer]`.

Außerhalb des Zeitfensters wird **kein HTML, kein JavaScript und kein Twitch-SDK** ausgeliefert — der Embed verschwindet vollständig und kostet nichts.

---

## Features

- **Twitch oder YouTube** (Live-Stream über Channel-ID oder fertiges Video über Video-ID)
- **Mehrere Zeitfenster im Voraus planbar** (bis zu 20) — pro Eintrag separat **täglich wiederholbar**
- **Datum + Uhrzeit** pro Zeitfenster (über Mitternacht wird korrekt behandelt)
- **Konfigurierbare Zeitzone** (alle PHP-Zeitzonen wählbar)
- **Optionale Auto-Ausblendung** eines anderen Seitenelements via CSS-Selektor während des Streams (z. B. eine News-Loop ersetzen)
- **Server-seitige Logik** — kein "Flackern" beim Übergang, keine Cache-Probleme
- **Performance-optimiert** — Assets werden nur geladen, wenn Shortcode auf der Seite UND Zeitfenster aktiv
- **Strikte Eingabe-Validierung** für Channel-IDs, CSS-Selektor, HTML-IDs und Datumsangaben
- **Multisite-fähig**, sauberer Uninstall

---

## Installation

### Via ZIP

1. Aus den Releases die aktuelle `stream-timer-for-wordpress.zip` herunterladen
2. WordPress → **Plugins → Installieren → Plugin hochladen**
3. Aktivieren
4. **Einstellungen → Stream Timer** öffnen und konfigurieren
5. Shortcode `[stream_timer]` an gewünschter Stelle einfügen (Seite, Beitrag, Widget, Theme-Template via `do_shortcode()`)

### Via Git

```bash
cd wp-content/plugins/
git clone https://github.com/Greenstein-Design/stream-timer-for-wordpress.git
```

---

## Shortcode

```
[stream_timer]
```

Vorschau erzwingen (Zeitfenster wird ignoriert — nützlich zum Layout-Testen im Backend):

```
[stream_timer force="on"]
```

---

## Backend-Konfiguration

| Feld | Beschreibung |
|------|--------------|
| Plattform | Twitch oder YouTube |
| Channel / Video-ID | Twitch-Channel-Name **oder** YouTube-Channel-ID (UC…) / Video-ID |
| YouTube-Modus | Live (Channel-ID) oder Video (Video-ID) |
| Zeitzone | Beliebige PHP-Zeitzone, Standard `Europe/Berlin` |
| Zeitfenster | Beliebig viele Einträge (max. 20) mit Bezeichnung, Start, Ende und optionaler täglicher Wiederholung |
| Höhe (px) | 200–1200 |
| Auszublendendes Element | CSS-Selektor, optional |
| Wrapper-ID | HTML-ID des Containers |

---

## Branding anpassen (White-Label)

Backend-Footer-Branding lässt sich per Filter überschreiben:

```php
add_filter( 'stream_timer_brand_name', function () {
    return 'Meine Agentur';
} );

add_filter( 'stream_timer_brand_url', function () {
    return 'https://example.com';
} );
```

Cache-Control-Header auf Seiten mit Shortcode lässt sich abschalten:

```php
add_filter( 'stream_timer_set_cache_headers', '__return_false' );
```

---

## Performance

Auf Seiten **ohne** aktiven Stream:
- 0 KB zusätzliches JavaScript
- 0 zusätzliche HTTP-Requests
- Keine Inline-Daten im HTML

Auf Seiten **mit** aktivem Stream:
- Server-seitiges Embed-Markup (kein clientseitiges Toggle)
- Twitch-SDK nur bei Plattform = Twitch
- Plugin-JS nur bei Twitch oder wenn Hide-Selector gesetzt
- 30-Sekunden Transient-Cache für die Aktiv-Prüfung bei mehrfacher Shortcode-Nutzung
- Empfohlener `Cache-Control: max-age=60` Header auf Seiten mit Shortcode

---

## Sicherheit

- Capability-Check (`manage_options`) beim Rendern UND beim Speichern
- Nonce-Schutz über die WordPress Settings API
- Plattformspezifische Whitelist-Regex für alle externen IDs
- CSS-Selektor-Whitelist (keine `{`, `}`, `\`, `;`, Zeilenumbrüche)
- Kein `innerHTML` mit User-Input im Frontend-JS
- Alle Ausgaben mit `esc_attr` / `esc_html` / `esc_url`
- Defense-in-Depth: clientseitige Validierung zusätzlich zur Server-Validierung

---

## Anforderungen

- WordPress 6.0+
- PHP 7.4+

---

## Changelog

### 3.1.0

- **Feature:** Mehrere Zeitfenster im Voraus planbar (bis zu 20 pro Konfiguration), jedes Fenster mit optionaler täglicher Wiederholung und freier Bezeichnung
- **Status-Anzeige:** Aktives Fenster + nächstes geplantes Fenster im Backend sichtbar
- **Bugfix:** Datetime-Parser akzeptiert nun auch Browser-Eingaben mit Sekunden (`:ss`) und normalisiert
- **Bugfix:** YouTube-Handles werden vom `live_stream`-Endpoint nicht unterstützt und daher abgewiesen — nur Channel-IDs (UC…) sind im Live-Modus erlaubt
- **Bugfix:** `repeat_daily` Equal-Case (start == end) erzeugt kein 24h-Phantomfenster mehr
- **Security:** Cache-Control-Header setzt für eingeloggte Nutzer `private, no-cache` statt `public`
- **Security:** CSS-Selektor-Whitelist enger gefasst (keine Quotes/Klammern/Attributwerte mehr)
- **Backward-Compat:** Bestehende Single-Schedule-Konfigurationen werden automatisch in die neue Schedules-Struktur migriert

### 3.0.0

- **Bugfix:** Außerhalb des Zeitfensters wird kein Markup mehr gerendert (vorher konnte Page-Caching dazu führen, dass der Embed sichtbar blieb)
- **Performance:** Twitch-SDK und Plugin-JS werden nur noch geladen, wenn der Shortcode aktiv auf der Seite ist
- **Performance:** Transient-Cache für die Status-Prüfung
- **Cache-Header:** Automatischer `Cache-Control`-Header auf Seiten mit Shortcode
- Plugin-Slug umbenannt zu `stream-timer-for-wordpress`

### 2.0.0

- White-Label-Release, Security-Hardening, Zeitzonen-Konfiguration

### 1.0.0

- Initial Release

---

## Lizenz

GPL-2.0-or-later

---

## Autor

Made with 💚 by [Greenstein.Design](https://greenstein.design) — Rene Grebenstein, Greenstein Designagentur.
