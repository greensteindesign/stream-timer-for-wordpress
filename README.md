# ⏰ Stream Timer for WordPress

> 🎮 Schedule Twitch and YouTube livestreams on your WordPress site — with zero performance impact when nothing's live.

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b?logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)
![Version](https://img.shields.io/badge/version-3.1.0-46b450)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)
![i18n](https://img.shields.io/badge/i18n-EN%20%C2%B7%20DE%20%C2%B7%20FR%20%C2%B7%20ES%20%C2%B7%20PT%20%C2%B7%20RU-orange)

Display a Twitch or YouTube embed only when your stream is actually live. Plan **multiple time windows** in advance, each with its **own source**. Outside of every scheduled window the plugin outputs **no markup, no JavaScript, no SDK** — your page stays as fast as if the plugin weren't there.

---

## ✨ Highlights

- 🎬 **Twitch & YouTube** — live channels or pre-recorded videos
- 🗓️ **Multiple schedules** — plan up to 20 windows in advance, each with **its own platform + channel**
- 🔁 **Per-schedule daily repeat** — recurring streams handled natively (incl. windows that span midnight)
- 🌍 **Timezone-aware** — every PHP timezone, defaults to `Europe/Berlin`
- 🫥 **Auto-hide other content** while the stream is live (CSS selector)
- ⚡ **Zero overhead when idle** — no script, no embed, no markup outside of active windows
- 🌐 **Multilingual** — ships with English, German, French, Spanish, Portuguese, Russian
- 🔒 **Hardened** — strict input validation, no innerHTML with user data, all output escaped
- 🧹 **Clean uninstall** — multisite-aware option + transient cleanup

---

## 🚀 Quick Start

### Install

**Option A — Download the ZIP**

1. Grab the latest [`stream-timer-3.1.0.zip`](https://github.com/greensteindesign/stream-timer-for-wordpress/releases)
2. WordPress → **Plugins → Add New → Upload Plugin** → upload → activate
3. Go to **Settings → Stream Timer** and configure
4. Drop the shortcode anywhere on your site:

```text
[stream_timer]
```

**Option B — Clone**

```bash
cd wp-content/plugins/
git clone https://github.com/greensteindesign/stream-timer-for-wordpress.git stream-timer
```

### Preview without waiting

```text
[stream_timer force="on"]
```

Useful for layout testing — bypasses the schedule check.

---

## 🎛️ How it works

Each schedule entry has its own source:

| Field | What it does |
|------|------|
| 🏷️ **Label** | Free-form name (optional) — shown in the admin status |
| 📡 **Platform** | `Twitch` or `YouTube` |
| 🔗 **Channel / Video ID** | Twitch name · YouTube Channel ID (`UC…`) · YouTube 11-char Video ID |
| 🎥 **YouTube mode** | `Live` (Channel ID) or `Video` (Video ID) — only for YouTube |
| ▶️ **Start** | Date + time (browser-native `datetime-local`) |
| ⏹️ **End** | Date + time |
| 🔁 **Daily** | Repeat the time-of-day window every day |

The active schedule is picked **server-side**. As soon as a window is active, only **that** schedule's source is rendered.

---

## ⚡ Performance

> The whole plugin is built around one principle: **if no window is live, ship nothing.**

When idle:
- 0 bytes of JavaScript
- 0 extra HTTP requests
- No inline data in the HTML

When active:
- Server-rendered embed markup — no client-side toggle, no flicker
- Twitch SDK loaded **only** when a Twitch schedule is active
- Frontend JS loaded **only** when needed (Twitch or hide-selector)
- 30-second transient cache for the "is any window active?" check
- `Cache-Control: max-age=60` on shortcode-bearing pages (configurable, automatically `private, no-cache` for logged-in users)

---

## 🛡️ Security

| Layer | What we do |
|------|------|
| 🔑 Capability | `manage_options` enforced on render **and** save |
| 🧪 Nonce | WordPress Settings API (built-in) |
| 🧼 Inputs | Per-platform whitelist regex for channel IDs, datetime, CSS selector, HTML ID |
| 🚫 Output | Every `echo` uses `esc_attr` / `esc_html` / `esc_url` / `wp_json_encode` |
| 🤖 Frontend | No `innerHTML` with user data, client-side re-validation of Twitch channel format, retry-cap on SDK init |
| 🧹 Uninstall | Options + transients removed (multisite-aware) |

Tested with [Plugin Check](https://wordpress.org/plugins/plugin-check/) v1.9.0 on WordPress 6.9 — **no errors, no warnings.**

---

## 🌐 Translations

Bundled out of the box:

| 🇬🇧 English | 🇩🇪 German | 🇫🇷 French | 🇪🇸 Spanish | 🇵🇹 Portuguese | 🇷🇺 Russian |
|---|---|---|---|---|---|
| ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

Want another language? Drop a `.po` file into `languages/` named `stream-timer-{locale}.mo` (the build script regenerates `.mo` from `.po`).

---

## 🛠️ Building from source

```bash
./build.sh             # regenerates .pot + .mo via Docker, then zips
./build.sh --skip-i18n # skip translation regeneration, use existing .mo
```

Output: `dist/stream-timer-<version>.zip` — ready to upload to WordPress.org or any WP install.

---

## 🎨 White-label / Filters

Override the backend footer branding:

```php
add_filter( 'stream_timer_brand_name', fn () => 'My Agency' );
add_filter( 'stream_timer_brand_url',  fn () => 'https://example.com' );
```

Disable the automatic `Cache-Control` header:

```php
add_filter( 'stream_timer_set_cache_headers', '__return_false' );
```

---

## 📋 Requirements

- WordPress **6.0+**
- PHP **7.4+**

---

## 📜 License

GPL-2.0-or-later

---

## 💚 Author

Built by [**Greenstein.Design**](https://greenstein.design) — Rene Grebenstein.

⭐ If this plugin saved you an hour, a star on GitHub is the easiest way to say thanks.
