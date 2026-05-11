/* Stream Timer for WordPress — Frontend
 *
 * Wird NUR geladen, wenn der Shortcode auf der Seite gerendert wurde
 * UND das Zeitfenster aktiv ist (server-seitige Entscheidung).
 *
 * Aufgabe hier:
 *  1. Twitch-SDK-Init (falls Plattform=twitch)
 *  2. Optional: Hide-Selector-Element ausblenden
 */
(function () {
    'use strict';

    if (typeof window.STREAM_TIMER_CFG === 'undefined') {
        return;
    }

    var cfg = window.STREAM_TIMER_CFG;

    function isValidTwitchChannel(v) {
        return typeof v === 'string' && /^[a-zA-Z0-9_]{4,25}$/.test(v);
    }

    function hideOtherElement() {
        if (!cfg.hideSelector) return;
        try {
            var el = document.querySelector(cfg.hideSelector);
            if (el) el.style.display = 'none';
        } catch (e) { /* ignore invalid selector */ }
    }

    var twitchRetries = 0;
    var TWITCH_MAX_RETRIES = 50; // ~10s total at 200ms

    function initTwitch() {
        if (cfg.platform !== 'twitch') return;
        if (!isValidTwitchChannel(cfg.channel)) return;

        var targetId = cfg.wrapperId + '-target';
        var target = document.getElementById(targetId);
        if (!target) return;
        if (target.dataset.embedInitialized === '1') return;

        if (typeof window.Twitch === 'undefined' || !window.Twitch.Embed) {
            if (twitchRetries++ < TWITCH_MAX_RETRIES) {
                setTimeout(initTwitch, 200);
            }
            return;
        }

        target.dataset.embedInitialized = '1';
        try {
            new window.Twitch.Embed(targetId, {
                width: '100%',
                height: cfg.height || 560,
                channel: cfg.channel,
                parent: [window.location.hostname]
            });
        } catch (e) {
            target.dataset.embedInitialized = '';
        }
    }

    function start() {
        hideOtherElement();
        initTwitch();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
