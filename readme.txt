=== Stream Timer ===
Contributors: greensteindesign
Tags: twitch, youtube, embed, livestream, scheduled
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 3.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Schedule-based Twitch or YouTube stream embed with multiple planned time windows. Assets load only while a window is active.

== Description ==

Stream Timer displays a Twitch or YouTube stream only during configured time windows. You can plan multiple windows in advance, each with optional daily repetition. Outside of all windows the plugin outputs no markup, no JavaScript and no Twitch SDK — zero performance impact.

**Features**

* Twitch and YouTube (Live + Video)
* Multiple time windows planned in advance, each optionally repeating daily
* Configurable timezone
* Optional auto-hide of another page element while the stream is live
* Performance optimized (lazy loading, transient cache, cache header)
* Strict input validation

**Shortcode**

`[stream_timer]`

== Installation ==

1. Upload the ZIP via Plugins → Add New → Upload Plugin
2. Activate the plugin
3. Go to Settings → Stream Timer and configure
4. Insert the `[stream_timer]` shortcode where you want the embed

== Changelog ==

= 3.1.0 =
* Feature: Multiple time windows can be planned in advance (max. 20), each with optional daily repetition
* Feature: Multilingual support (English, German, French, Spanish, Portuguese, Russian)
* Bugfix: Datetime parser now accepts browser input with seconds
* Bugfix: YouTube Live only accepts Channel IDs (UC…)
* Security: Cache-Control header sends "private, no-cache" for logged-in users
* Security: CSS selector whitelist tightened
* Renamed to "Stream Timer"

= 3.0.0 =
* Bugfix: no markup rendered outside the time window
* Performance: assets enqueued only when the shortcode is active
* Transient cache and cache header

= 2.0.0 =
* White-label release, security hardening, timezone configuration

= 1.0.0 =
* Initial release
