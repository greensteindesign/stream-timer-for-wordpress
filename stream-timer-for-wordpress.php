<?php
/**
 * Plugin Name:       Stream Timer
 * Plugin URI:        https://github.com/greensteindesign/stream-timer-for-wordpress
 * Description:       Schedule-based Twitch or YouTube stream embed. Plan multiple time windows in advance. Configurable in the backend, insert via shortcode [stream_timer]. Performance optimized: assets load only when the shortcode is present and a window is active.
 * Version:           3.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Greenstein Designagentur — Rene Grebenstein
 * Author URI:        https://greenstein.design
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       stream-timer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ============================================================
 * KONSTANTEN
 * ============================================================ */

define( 'STREAM_TIMER_VERSION', '3.1.0' );
define( 'STREAM_TIMER_OPTION',  'stream_timer_settings' );
define( 'STREAM_TIMER_SLUG',    'stream-timer' );
define( 'STREAM_TIMER_FILE',    __FILE__ );
define( 'STREAM_TIMER_URL',     plugin_dir_url( __FILE__ ) );

define( 'STREAM_TIMER_MAX_SCHEDULES', 20 );

// Branding (Backend-Footer) — über Filter überschreibbar.
define( 'STREAM_TIMER_BRAND_NAME', 'Greenstein.Design' );
define( 'STREAM_TIMER_BRAND_URL',  'https://greenstein.design' );

/* ============================================================
 * TEXT DOMAIN
 * ============================================================ */

add_action( 'init', 'stream_timer_load_textdomain' );
function stream_timer_load_textdomain() {
    // Manual lookup of plugin-bundled translations to avoid load_plugin_textdomain (discouraged since WP 4.6).
    // Translations downloaded from translate.wordpress.org are loaded automatically by WordPress.
    $locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- plugin_locale is a WordPress core filter, not a custom hook.
    $locale = apply_filters( 'plugin_locale', $locale, 'stream-timer' );
    $mofile = plugin_dir_path( STREAM_TIMER_FILE ) . 'languages/stream-timer-' . $locale . '.mo';
    if ( file_exists( $mofile ) ) {
        load_textdomain( 'stream-timer', $mofile );
    }
}

/* ============================================================
 * DEFAULTS
 * ============================================================ */

function stream_timer_default_settings() {
    return [
        'schedules'     => [],
        'height'        => 560,
        'hide_selector' => '',
        'wrapper_id'    => 'stream-timer-wrapper',
        'timezone'      => 'Europe/Berlin',
    ];
}

function stream_timer_default_schedule() {
    return [
        'label'        => '',
        'platform'     => 'twitch',
        'channel'      => '',
        'youtube_type' => 'live',
        'start'        => '',
        'end'          => '',
        'repeat_daily' => 0,
    ];
}

function stream_timer_get_settings() {
    $saved = get_option( STREAM_TIMER_OPTION, [] );
    if ( ! is_array( $saved ) ) {
        $saved = [];
    }
    $merged = array_merge( stream_timer_default_settings(), $saved );

    // Backward-compat: migrate legacy single-source keys + start/end into a schedules[] entry.
    $has_legacy_source = ! empty( $saved['platform'] ) || ! empty( $saved['channel'] ) || ! empty( $saved['youtube_type'] );
    $has_legacy_times  = ! empty( $saved['start_datetime'] ) || ! empty( $saved['end_datetime'] );

    if ( empty( $merged['schedules'] ) && ( $has_legacy_source || $has_legacy_times ) ) {
        $merged['schedules'] = [
            [
                'label'        => '',
                'platform'     => in_array( $saved['platform'] ?? '', [ 'twitch', 'youtube' ], true ) ? $saved['platform'] : 'twitch',
                'channel'      => (string) ( $saved['channel'] ?? '' ),
                'youtube_type' => in_array( $saved['youtube_type'] ?? '', [ 'live', 'video' ], true ) ? $saved['youtube_type'] : 'live',
                'start'        => (string) ( $saved['start_datetime'] ?? '' ),
                'end'          => (string) ( $saved['end_datetime'] ?? '' ),
                'repeat_daily' => ! empty( $saved['repeat_daily'] ) ? 1 : 0,
            ],
        ];
    }

    if ( ! is_array( $merged['schedules'] ) ) {
        $merged['schedules'] = [];
    }

    // Ensure each schedule has all expected keys (older entries may lack source fields).
    foreach ( $merged['schedules'] as $i => $row ) {
        $merged['schedules'][ $i ] = array_merge( stream_timer_default_schedule(), is_array( $row ) ? $row : [] );
    }

    return $merged;
}

/* ============================================================
 * ADMIN-SETTINGS
 * ============================================================ */

add_action( 'admin_menu', 'stream_timer_register_settings_page' );
function stream_timer_register_settings_page() {
    add_options_page(
        __( 'Stream Timer', 'stream-timer' ),
        __( 'Stream Timer', 'stream-timer' ),
        'manage_options',
        STREAM_TIMER_SLUG,
        'stream_timer_render_settings_page'
    );
}

add_action( 'admin_init', 'stream_timer_register_settings' );
function stream_timer_register_settings() {
    register_setting(
        'stream_timer_group',
        STREAM_TIMER_OPTION,
        [
            'type'              => 'array',
            'sanitize_callback' => 'stream_timer_sanitize_settings',
            'default'           => stream_timer_default_settings(),
        ]
    );
}

function stream_timer_sanitize_settings( $input ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return get_option( STREAM_TIMER_OPTION, stream_timer_default_settings() );
    }

    if ( ! is_array( $input ) ) {
        $input = [];
    }

    $defaults = stream_timer_default_settings();
    $clean    = [];

    $clean['height'] = max( 200, min( 1200, (int) ( $input['height'] ?? $defaults['height'] ) ) );

    $clean['hide_selector'] = stream_timer_sanitize_css_selector( $input['hide_selector'] ?? '' );
    $clean['wrapper_id']    = stream_timer_sanitize_html_id( $input['wrapper_id'] ?? $defaults['wrapper_id'] );

    $tz_input = (string) ( $input['timezone'] ?? $defaults['timezone'] );
    $clean['timezone'] = in_array( $tz_input, timezone_identifiers_list(), true )
        ? $tz_input
        : $defaults['timezone'];

    // Process schedules (each with its own source). Preserve user order.
    $clean['schedules'] = [];
    $raw_schedules = isset( $input['schedules'] ) && is_array( $input['schedules'] ) ? $input['schedules'] : [];
    $dropped_with_data = 0;
    $count = 0;
    foreach ( $raw_schedules as $row ) {
        if ( $count >= STREAM_TIMER_MAX_SCHEDULES ) {
            break;
        }
        if ( ! is_array( $row ) ) {
            continue;
        }
        $start  = stream_timer_sanitize_datetime( $row['start'] ?? '' );
        $end    = stream_timer_sanitize_datetime( $row['end'] ?? '' );
        $repeat = ! empty( $row['repeat_daily'] ) ? 1 : 0;
        $label  = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
        if ( mb_strlen( $label ) > 100 ) {
            $label = mb_substr( $label, 0, 100 );
        }

        $platform = in_array( $row['platform'] ?? '', [ 'twitch', 'youtube' ], true )
            ? $row['platform']
            : 'twitch';

        $youtube_type = in_array( $row['youtube_type'] ?? '', [ 'live', 'video' ], true )
            ? $row['youtube_type']
            : 'live';

        $channel_raw = isset( $row['channel'] ) ? trim( (string) $row['channel'] ) : '';
        $channel     = stream_timer_validate_channel( $channel_raw, $platform, $youtube_type );

        $has_any_data = ( '' !== $start || '' !== $end || '' !== $label || '' !== $channel );

        // Drop entirely empty rows silently.
        if ( ! $has_any_data ) {
            continue;
        }
        // Row has content but is unusable — count it for the user notice.
        if ( '' === $start || '' === $end || $start === $end ) {
            $dropped_with_data++;
            continue;
        }

        $clean['schedules'][] = [
            'label'        => $label,
            'platform'     => $platform,
            'channel'      => $channel,
            'youtube_type' => $youtube_type,
            'start'        => $start,
            'end'          => $end,
            'repeat_daily' => $repeat,
        ];
        $count++;
    }

    if ( $dropped_with_data > 0 ) {
        add_settings_error(
            'stream_timer_messages',
            'stream_timer_dropped_rows',
            sprintf(
                /* translators: %d: number of incomplete schedule rows that were ignored */
                _n(
                    '%d schedule row was ignored because start and end are required.',
                    '%d schedule rows were ignored because start and end are required.',
                    $dropped_with_data,
                    'stream-timer'
                ),
                $dropped_with_data
            ),
            'warning'
        );
    }

    // Legacy-Keys explizit entfernen.
    // (register_setting speichert nur $clean, alte Keys verschwinden damit.)

    // Transient-Cache invalidieren.
    delete_transient( 'stream_timer_is_active' );

    return $clean;
}

function stream_timer_validate_channel( $value, $platform, $youtube_type = 'live' ) {
    $value = sanitize_text_field( $value );

    if ( '' === $value ) {
        return '';
    }

    if ( 'twitch' === $platform ) {
        if ( preg_match( '/^[a-zA-Z0-9_]{4,25}$/', $value ) ) {
            return strtolower( $value );
        }
        return '';
    }

    if ( 'youtube' === $platform ) {
        if ( 'video' === $youtube_type ) {
            if ( preg_match( '/^[a-zA-Z0-9_-]{11}$/', $value ) ) {
                return $value;
            }
            return '';
        }
        // Channel-ID (UC…) — vom Endpoint live_stream?channel= unterstützt.
        if ( preg_match( '/^UC[a-zA-Z0-9_-]{22}$/', $value ) ) {
            return $value;
        }
        return '';
    }

    return '';
}

function stream_timer_sanitize_datetime( $value ) {
    if ( empty( $value ) ) {
        return '';
    }
    $value = sanitize_text_field( (string) $value );
    // Accept several variations browsers may emit and normalize to "Y-m-d\TH:i":
    //   2026-05-12T18:00
    //   2026-05-12T18:00:30
    //   2026-05-12T18:00:30.123
    //   2026-05-12 18:00 (space separator)
    //   2026-05-12T18:00+02:00 (trailing zone)
    if ( preg_match( '/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})(?::\d{2}(?:\.\d+)?)?(?:[+\-]\d{2}:?\d{2}|Z)?$/', $value, $m ) ) {
        $normalized = $m[1] . 'T' . $m[2];
        if ( false !== strtotime( $normalized ) ) {
            return $normalized;
        }
    }
    return '';
}

function stream_timer_sanitize_css_selector( $value ) {
    $value = sanitize_text_field( (string) $value );
    if ( '' === $value ) {
        return '';
    }
    // Engere Whitelist: keine Quotes, kein @, kein *, keine Klammern mit Strings.
    // Erlaubt: Klassen, IDs, Tag-Selektoren, Kombinatoren, Pseudoklassen, einfache Attribut-Selektoren ohne Werte.
    if ( preg_match( '/^[a-zA-Z0-9.#_\- >+~,:]+$/', $value ) ) {
        return $value;
    }
    return '';
}

function stream_timer_sanitize_html_id( $value ) {
    $value = (string) $value;
    if ( preg_match( '/^[a-zA-Z][a-zA-Z0-9_-]{0,63}$/', $value ) ) {
        return $value;
    }
    return 'stream-timer-wrapper';
}

/* ============================================================
 * ADMIN: Settings-Seite rendern
 * ============================================================ */

function stream_timer_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Insufficient permissions.', 'stream-timer' ) );
    }
    $s = stream_timer_get_settings();
    $opt = STREAM_TIMER_OPTION;

    // Show "Settings saved" notice. WordPress only auto-displays this on the core options pages,
    // not on custom pages registered via add_options_page, so we trigger it manually.
    if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag from WordPress redirect, no state change.
        add_settings_error( 'stream_timer_messages', 'stream_timer_saved', __( 'Settings saved.', 'stream-timer' ), 'updated' );
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Stream Timer', 'stream-timer' ); ?></h1>
        <?php settings_errors( 'stream_timer_messages' ); ?>

        <p>
            <?php esc_html_e( 'Configuration for the scheduled stream embed. Insert via shortcode:', 'stream-timer' ); ?>
            <code>[stream_timer]</code>
            &nbsp;·&nbsp;
            <?php esc_html_e( 'Preview (always show):', 'stream-timer' ); ?>
            <code>[stream_timer force="on"]</code>
        </p>

        <form method="post" action="options.php">
            <?php settings_fields( 'stream_timer_group' ); ?>

            <h2><?php esc_html_e( 'General', 'stream-timer' ); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="st_timezone"><?php esc_html_e( 'Timezone', 'stream-timer' ); ?></label>
                    </th>
                    <td>
                        <select name="<?php echo esc_attr( $opt ); ?>[timezone]" id="st_timezone">
                            <?php foreach ( timezone_identifiers_list() as $tz ) : ?>
                                <option value="<?php echo esc_attr( $tz ); ?>" <?php selected( $s['timezone'], $tz ); ?>>
                                    <?php echo esc_html( $tz ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e( 'Time Windows (Schedule)', 'stream-timer' ); ?></h2>
            <p class="description">
                <?php
                printf(
                    /* translators: %d: maximum number of schedule entries */
                    esc_html__( 'Plan multiple windows in advance (max. %d). Each window has its own source (platform + channel). The embed is shown as soon as any window is active.', 'stream-timer' ),
                    (int) STREAM_TIMER_MAX_SCHEDULES
                );
                ?>
            </p>

            <style>
                .st-schedule-card { background:#fff; border:1px solid #c3c4c7; border-radius:4px; margin:0 0 12px; padding:14px 16px; max-width:1000px; }
                .st-schedule-card .st-row { display:flex; flex-wrap:wrap; gap:14px 24px; align-items:flex-end; margin-bottom:10px; }
                .st-schedule-card .st-field { display:flex; flex-direction:column; gap:4px; min-width:160px; }
                .st-schedule-card .st-field label { font-weight:600; font-size:12px; }
                .st-schedule-card .st-field-grow { flex:1 1 220px; }
                .st-schedule-card .st-footer { display:flex; justify-content:space-between; align-items:center; border-top:1px solid #f0f0f1; padding-top:10px; margin-top:6px; }
                .st-schedule-card .st-status-active { color:#46b450; font-weight:600; }
                .st-schedule-card .st-status-idle { color:#999; }
                .st-schedule-card.st-hide-ytmode .st-field-yt { display:none; }
            </style>

            <div id="st-schedules-body">
                <?php
                $rows = $s['schedules'];
                if ( empty( $rows ) ) {
                    $rows = [ stream_timer_default_schedule() ];
                }
                foreach ( $rows as $i => $row ) {
                    stream_timer_render_schedule_row( $i, $row, $s );
                }
                ?>
            </div>

            <p>
                <button type="button" class="button" id="st-add-schedule">
                    + <?php esc_html_e( 'Add time window', 'stream-timer' ); ?>
                </button>
            </p>

            <script type="text/template" id="st-schedule-row-template">
                <?php stream_timer_render_schedule_row( '__INDEX__', stream_timer_default_schedule(), $s, true ); ?>
            </script>

            <script>
            (function(){
                var body = document.getElementById('st-schedules-body');
                var addBtn = document.getElementById('st-add-schedule');
                var tpl = document.getElementById('st-schedule-row-template').innerHTML;
                var max = <?php echo (int) STREAM_TIMER_MAX_SCHEDULES; ?>;

                function nextIndex() {
                    var cards = body.querySelectorAll('.st-schedule-card');
                    var n = -1;
                    cards.forEach(function(c){
                        var v = parseInt(c.getAttribute('data-st-index'), 10);
                        if (!isNaN(v) && v > n) n = v;
                    });
                    return n + 1;
                }

                function count() {
                    return body.querySelectorAll('.st-schedule-card').length;
                }

                function applyYtToggle(card) {
                    var sel = card.querySelector('.st-platform-select');
                    if (!sel) return;
                    if (sel.value === 'youtube') {
                        card.classList.remove('st-hide-ytmode');
                    } else {
                        card.classList.add('st-hide-ytmode');
                    }
                }

                body.querySelectorAll('.st-schedule-card').forEach(applyYtToggle);

                addBtn.addEventListener('click', function(){
                    if (count() >= max) {
                        alert(<?php echo wp_json_encode( __( 'Maximum number of time windows reached.', 'stream-timer' ) ); ?>);
                        return;
                    }
                    var html = tpl.replace(/__INDEX__/g, String(nextIndex()));
                    var wrapper = document.createElement('div');
                    wrapper.innerHTML = html.trim();
                    var card = wrapper.querySelector('.st-schedule-card');
                    if (card) {
                        body.appendChild(card);
                        applyYtToggle(card);
                    }
                });

                body.addEventListener('click', function(e){
                    var btn = e.target.closest('.st-remove-row');
                    if (!btn) return;
                    var card = btn.closest('.st-schedule-card');
                    if (card) card.remove();
                });

                body.addEventListener('change', function(e){
                    if (e.target.matches('.st-platform-select')) {
                        var card = e.target.closest('.st-schedule-card');
                        if (card) applyYtToggle(card);
                    }
                });
            })();
            </script>

            <h2><?php esc_html_e( 'Display', 'stream-timer' ); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="st_height"><?php esc_html_e( 'Height (px)', 'stream-timer' ); ?></label>
                    </th>
                    <td>
                        <input type="number" min="200" max="1200" step="10"
                               name="<?php echo esc_attr( $opt ); ?>[height]"
                               id="st_height" class="small-text"
                               value="<?php echo esc_attr( $s['height'] ); ?>">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="st_hide"><?php esc_html_e( 'Element to hide (CSS selector)', 'stream-timer' ); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               name="<?php echo esc_attr( $opt ); ?>[hide_selector]"
                               id="st_hide" class="regular-text"
                               value="<?php echo esc_attr( $s['hide_selector'] ); ?>">
                        <p class="description">
                            <?php esc_html_e( 'Optional. Allowed: classes, IDs, tags, combinators (>+~,), pseudo-classes (:hover etc.). No attribute values, no quotes.', 'stream-timer' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="st_wrapper"><?php esc_html_e( 'Wrapper ID', 'stream-timer' ); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               name="<?php echo esc_attr( $opt ); ?>[wrapper_id]"
                               id="st_wrapper" class="regular-text"
                               value="<?php echo esc_attr( $s['wrapper_id'] ); ?>">
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>

        <hr>

        <h2><?php esc_html_e( 'Current Status', 'stream-timer' ); ?></h2>
        <?php
        $active_info = stream_timer_active_schedule( $s, true );
        if ( $active_info ) {
            echo '<p><strong style="color:#46b450;">● ' . esc_html__( 'A stream window is currently active.', 'stream-timer' ) . '</strong></p>';
            $label  = $active_info['label'] !== '' ? $active_info['label'] : __( '(no label)', 'stream-timer' );
            $source = ucfirst( $active_info['platform'] ) . ' / ' . ( $active_info['channel'] !== '' ? $active_info['channel'] : '—' );
            printf(
                /* translators: 1: schedule label, 2: source (platform / channel) */
                '<p>' . esc_html__( 'Active window: %1$s (%2$s)', 'stream-timer' ) . '</p>',
                '<code>' . esc_html( $label ) . '</code>',
                '<code>' . esc_html( $source ) . '</code>'
            );
        } else {
            echo '<p><strong style="color:#dc3232;">● ' . esc_html__( 'No stream window is currently active.', 'stream-timer' ) . '</strong></p>';
        }

        $now = stream_timer_now( $s['timezone'] );
        printf(
            /* translators: 1: current server time, 2: configured timezone identifier */
            '<p>' . esc_html__( 'Current server time (%2$s): %1$s', 'stream-timer' ) . '</p>',
            '<code>' . esc_html( $now->format( 'Y-m-d H:i:s' ) ) . '</code>',
            '<code>' . esc_html( $s['timezone'] ) . '</code>'
        );

        // Show next upcoming window.
        $next = stream_timer_next_schedule( $s );
        if ( $next ) {
            $label = $next['label'] !== '' ? $next['label'] : __( '(no label)', 'stream-timer' );
            printf(
                /* translators: 1: schedule label, 2: start datetime, 3: end datetime */
                '<p>' . esc_html__( 'Next window: %1$s — %2$s to %3$s', 'stream-timer' ) . '</p>',
                '<code>' . esc_html( $label ) . '</code>',
                '<code>' . esc_html( $next['start_display'] ) . '</code>',
                '<code>' . esc_html( $next['end_display'] ) . '</code>'
            );
        }

        stream_timer_render_admin_footer();
        ?>
    </div>
    <?php
}

function stream_timer_render_schedule_row( $index, $row, $settings, $is_template = false ) {
    $opt = STREAM_TIMER_OPTION;
    $row = array_merge( stream_timer_default_schedule(), is_array( $row ) ? $row : [] );

    $is_active = false;
    if ( ! $is_template && ! empty( $row['start'] ) && ! empty( $row['end'] ) ) {
        $is_active = stream_timer_schedule_matches( $row, $settings['timezone'] ?? 'Europe/Berlin' );
    }

    $idx        = (string) $index;
    $field_name = function ( $key ) use ( $opt, $idx ) {
        return esc_attr( $opt ) . '[schedules][' . esc_attr( $idx ) . '][' . esc_attr( $key ) . ']';
    };
    $hide_yt_class = ( 'youtube' === $row['platform'] ) ? '' : ' st-hide-ytmode';
    ?>
    <div class="st-schedule-card<?php echo esc_attr( $hide_yt_class ); ?>" data-st-index="<?php echo esc_attr( $idx ); ?>">
        <div class="st-row">
            <div class="st-field st-field-grow">
                <label><?php esc_html_e( 'Label (optional)', 'stream-timer' ); ?></label>
                <input type="text"
                       name="<?php echo $field_name( 'label' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped in closure. ?>"
                       value="<?php echo esc_attr( $row['label'] ); ?>"
                       class="regular-text"
                       placeholder="<?php esc_attr_e( 'e.g. Wednesday Stream', 'stream-timer' ); ?>">
            </div>
        </div>

        <div class="st-row">
            <div class="st-field">
                <label><?php esc_html_e( 'Platform', 'stream-timer' ); ?></label>
                <select class="st-platform-select"
                        name="<?php echo $field_name( 'platform' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
                    <option value="twitch"  <?php selected( $row['platform'], 'twitch' ); ?>>Twitch</option>
                    <option value="youtube" <?php selected( $row['platform'], 'youtube' ); ?>>YouTube</option>
                </select>
            </div>
            <div class="st-field st-field-grow">
                <label><?php esc_html_e( 'Channel / Video ID', 'stream-timer' ); ?></label>
                <input type="text"
                       name="<?php echo $field_name( 'channel' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"
                       value="<?php echo esc_attr( $row['channel'] ); ?>"
                       class="regular-text"
                       placeholder="<?php esc_attr_e( 'Twitch name, YouTube UC… ID, or 11-char video ID', 'stream-timer' ); ?>">
            </div>
            <div class="st-field st-field-yt">
                <label><?php esc_html_e( 'YouTube mode', 'stream-timer' ); ?></label>
                <select name="<?php echo $field_name( 'youtube_type' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
                    <option value="live"  <?php selected( $row['youtube_type'], 'live' ); ?>><?php esc_html_e( 'Live (Channel ID)', 'stream-timer' ); ?></option>
                    <option value="video" <?php selected( $row['youtube_type'], 'video' ); ?>><?php esc_html_e( 'Video (Video ID)', 'stream-timer' ); ?></option>
                </select>
            </div>
        </div>

        <div class="st-row">
            <div class="st-field">
                <label><?php esc_html_e( 'Start', 'stream-timer' ); ?></label>
                <input type="datetime-local"
                       name="<?php echo $field_name( 'start' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"
                       value="<?php echo esc_attr( $row['start'] ); ?>">
            </div>
            <div class="st-field">
                <label><?php esc_html_e( 'End', 'stream-timer' ); ?></label>
                <input type="datetime-local"
                       name="<?php echo $field_name( 'end' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"
                       value="<?php echo esc_attr( $row['end'] ); ?>">
            </div>
            <div class="st-field">
                <label><?php esc_html_e( 'Daily', 'stream-timer' ); ?></label>
                <label>
                    <input type="checkbox"
                           name="<?php echo $field_name( 'repeat_daily' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"
                           value="1" <?php checked( $row['repeat_daily'], 1 ); ?>>
                    <?php esc_html_e( 'repeat', 'stream-timer' ); ?>
                </label>
            </div>
        </div>

        <div class="st-footer">
            <span>
                <?php if ( $is_active ) : ?>
                    <span class="st-status-active">● <?php esc_html_e( 'active', 'stream-timer' ); ?></span>
                <?php else : ?>
                    <span class="st-status-idle">— <?php esc_html_e( 'Status', 'stream-timer' ); ?></span>
                <?php endif; ?>
            </span>
            <button type="button" class="button-link-delete st-remove-row">
                <?php esc_html_e( 'Remove', 'stream-timer' ); ?>
            </button>
        </div>
    </div>
    <?php
}

function stream_timer_render_admin_footer() {
    $brand_name = apply_filters( 'stream_timer_brand_name', STREAM_TIMER_BRAND_NAME );
    $brand_url  = apply_filters( 'stream_timer_brand_url',  STREAM_TIMER_BRAND_URL );
    ?>
    <div style="margin-top:40px; padding-top:20px; border-top:1px solid #ddd; color:#666; font-size:13px;">
        <?php
        printf(
            /* translators: 1: heart emoji span, 2: brand link HTML */
            esc_html__( 'Made with %1$s by %2$s', 'stream-timer' ),
            '<span style="color:#21a366;" aria-hidden="true">&#128154;</span>',
            '<a href="' . esc_url( $brand_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $brand_name ) . '</a>'
        );
        ?>
    </div>
    <?php
}

/* ============================================================
 * ZEITFENSTER-LOGIK (mit Transient-Cache)
 * ============================================================ */

function stream_timer_now( $timezone = 'Europe/Berlin' ) {
    try {
        $tz = new DateTimeZone( $timezone );
    } catch ( Exception $e ) {
        $tz = new DateTimeZone( 'Europe/Berlin' );
    }
    return new DateTime( 'now', $tz );
}

function stream_timer_tz( $timezone ) {
    try {
        return new DateTimeZone( $timezone );
    } catch ( Exception $e ) {
        return new DateTimeZone( 'Europe/Berlin' );
    }
}

/**
 * Prüft ein einzelnes Schedule-Item gegen "jetzt".
 */
function stream_timer_schedule_matches( $schedule, $timezone, $now = null ) {
    if ( empty( $schedule['start'] ) || empty( $schedule['end'] ) ) {
        return false;
    }
    $tz = stream_timer_tz( $timezone );
    if ( null === $now ) {
        $now = new DateTime( 'now', $tz );
    }
    $start = DateTime::createFromFormat( 'Y-m-d\TH:i', $schedule['start'], $tz );
    $end   = DateTime::createFromFormat( 'Y-m-d\TH:i', $schedule['end'], $tz );
    if ( ! $start || ! $end ) {
        return false;
    }
    if ( $start >= $end ) {
        // start == end oder ungültig.
        return false;
    }

    if ( ! empty( $schedule['repeat_daily'] ) ) {
        $today      = $now->format( 'Y-m-d' );
        $startToday = DateTime::createFromFormat( 'Y-m-d H:i', $today . ' ' . $start->format( 'H:i' ), $tz );
        $endToday   = DateTime::createFromFormat( 'Y-m-d H:i', $today . ' ' . $end->format( 'H:i' ), $tz );
        if ( ! $startToday || ! $endToday ) {
            return false;
        }
        if ( $endToday < $startToday ) {
            // Über Mitternacht — passende 24h-Schiene wählen.
            if ( $now < $endToday ) {
                $startToday->modify( '-1 day' );
            } else {
                $endToday->modify( '+1 day' );
            }
        } elseif ( $endToday->getTimestamp() === $startToday->getTimestamp() ) {
            return false;
        }
        return ( $now >= $startToday && $now < $endToday );
    }

    return ( $now >= $start && $now < $end );
}

/**
 * Liefert das aktuell aktive Schedule oder null.
 *
 * @return array|null
 */
function stream_timer_active_schedule( $settings = null, $bypass_cache = false ) {
    if ( null === $settings ) {
        $settings = stream_timer_get_settings();
    }
    if ( empty( $settings['schedules'] ) ) {
        return null;
    }
    $tz_string = $settings['timezone'] ?? 'Europe/Berlin';
    $now = new DateTime( 'now', stream_timer_tz( $tz_string ) );
    foreach ( $settings['schedules'] as $row ) {
        if ( stream_timer_schedule_matches( $row, $tz_string, $now ) ) {
            return $row;
        }
    }
    return null;
}

/**
 * Liefert das nächste anstehende Schedule (in der Zukunft) — für Admin-Statusanzeige.
 *
 * @return array|null mit Keys label, start_display, end_display
 */
function stream_timer_next_schedule( $settings ) {
    if ( empty( $settings['schedules'] ) ) {
        return null;
    }
    $tz_string = $settings['timezone'] ?? 'Europe/Berlin';
    $tz  = stream_timer_tz( $tz_string );
    $now = new DateTime( 'now', $tz );

    $best = null;
    $best_start = null;

    foreach ( $settings['schedules'] as $row ) {
        if ( empty( $row['start'] ) || empty( $row['end'] ) ) {
            continue;
        }
        $start = DateTime::createFromFormat( 'Y-m-d\TH:i', $row['start'], $tz );
        $end   = DateTime::createFromFormat( 'Y-m-d\TH:i', $row['end'], $tz );
        if ( ! $start || ! $end ) {
            continue;
        }

        if ( ! empty( $row['repeat_daily'] ) ) {
            // Nächste Wiederholung berechnen.
            $today = $now->format( 'Y-m-d' );
            $cand  = DateTime::createFromFormat( 'Y-m-d H:i', $today . ' ' . $start->format( 'H:i' ), $tz );
            if ( $cand <= $now ) {
                $cand->modify( '+1 day' );
            }
            $cand_end = clone $cand;
            $diff_min = ( (int) $end->format( 'H' ) - (int) $start->format( 'H' ) ) * 60
                      + ( (int) $end->format( 'i' ) - (int) $start->format( 'i' ) );
            if ( $diff_min <= 0 ) {
                $diff_min += 24 * 60;
            }
            $cand_end->modify( '+' . $diff_min . ' minutes' );
            $start_eff = $cand;
            $end_eff   = $cand_end;
        } else {
            if ( $start <= $now ) {
                continue; // bereits vorbei oder läuft (läuft → von active_schedule abgedeckt).
            }
            $start_eff = $start;
            $end_eff   = $end;
        }

        if ( null === $best_start || $start_eff < $best_start ) {
            $best_start = $start_eff;
            $best = [
                'label'         => $row['label'],
                'platform'      => $row['platform'],
                'channel'       => $row['channel'],
                'start_display' => $start_eff->format( 'Y-m-d H:i' ),
                'end_display'   => $end_eff->format( 'Y-m-d H:i' ),
            ];
        }
    }
    return $best;
}

/**
 * Public Convenience: ist irgendein Fenster aktiv? (mit Transient-Cache 30s)
 */
function stream_timer_is_active( $settings = null, $bypass_cache = false ) {
    if ( ! $bypass_cache ) {
        $cached = get_transient( 'stream_timer_is_active' );
        if ( false !== $cached ) {
            return ( '1' === $cached );
        }
    }

    $active = stream_timer_active_schedule( $settings, true );
    $result = ( null !== $active );

    if ( ! $bypass_cache ) {
        set_transient( 'stream_timer_is_active', $result ? '1' : '0', 30 );
    }

    return $result;
}

/* ============================================================
 * SHORTCODE
 * ============================================================ */

add_shortcode( 'stream_timer', 'stream_timer_shortcode' );
function stream_timer_shortcode( $atts = [] ) {
    $atts = shortcode_atts( [
        'force' => '',
    ], $atts, 'stream_timer' );

    $s        = stream_timer_get_settings();
    $force_on = ( 'on' === $atts['force'] );

    // Pick the active (or first valid) schedule and use ITS source.
    $schedule = stream_timer_active_schedule( $s );
    if ( ! $schedule && $force_on ) {
        // Force-preview: take the first schedule with a channel, else the first schedule.
        foreach ( $s['schedules'] as $row ) {
            if ( ! empty( $row['channel'] ) ) {
                $schedule = $row;
                break;
            }
        }
        if ( ! $schedule && ! empty( $s['schedules'] ) ) {
            $schedule = $s['schedules'][0];
        }
    }

    if ( ! $schedule || empty( $schedule['channel'] ) ) {
        return '';
    }

    stream_timer_mark_assets_needed( $s, $schedule );

    $wrapper_id = $s['wrapper_id'] ?: 'stream-timer-wrapper';
    $height     = (int) $s['height'];

    ob_start();
    ?>
    <div id="<?php echo esc_attr( $wrapper_id ); ?>" class="stream-timer-wrapper">
        <?php echo stream_timer_render_embed_html( $schedule, $s['wrapper_id'], $height ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns already-escaped output. ?>
    </div>
    <?php
    return ob_get_clean();
}

function stream_timer_render_embed_html( $schedule, $wrapper_id, $height ) {
    $height = (int) $height;

    if ( 'twitch' === $schedule['platform'] ) {
        $target_id = sanitize_html_class( $wrapper_id ) . '-target';
        return '<div id="' . esc_attr( $target_id ) . '" class="stream-timer-target"></div>';
    }

    if ( 'youtube' === $schedule['platform'] ) {
        $channel = $schedule['channel'];
        if ( 'video' === $schedule['youtube_type'] ) {
            $src = 'https://www.youtube.com/embed/' . rawurlencode( $channel ) . '?autoplay=1';
        } else {
            $src = 'https://www.youtube.com/embed/live_stream?channel=' . rawurlencode( $channel );
        }
        return sprintf(
            '<iframe src="%s" width="100%%" height="%d" frameborder="0" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>',
            esc_url( $src ),
            $height
        );
    }

    return '';
}

/* ============================================================
 * PERFORMANCE: Assets nur bei aktivem Shortcode
 * ============================================================ */

add_action( 'wp_enqueue_scripts', 'stream_timer_register_assets' );
function stream_timer_register_assets() {
    wp_register_script(
        'twitch-embed-sdk',
        'https://embed.twitch.tv/embed/v1.js',
        [],
        '1.0',
        true
    );

    wp_register_script(
        'stream-timer-frontend',
        STREAM_TIMER_URL . 'assets/stream-timer.js',
        [],
        STREAM_TIMER_VERSION,
        true
    );
}

function stream_timer_mark_assets_needed( $settings, $schedule ) {
    static $marked = false;
    if ( $marked ) return;
    $marked = true;

    $platform = $schedule['platform'] ?? 'twitch';
    $channel  = $schedule['channel']  ?? '';
    $needs_js = ( 'twitch' === $platform ) || ! empty( $settings['hide_selector'] );

    if ( 'twitch' === $platform ) {
        wp_enqueue_script( 'twitch-embed-sdk' );
    }

    if ( $needs_js ) {
        wp_enqueue_script( 'stream-timer-frontend' );
        wp_localize_script( 'stream-timer-frontend', 'STREAM_TIMER_CFG', [
            'wrapperId'    => $settings['wrapper_id'],
            'platform'     => $platform,
            'channel'      => $channel,
            'height'       => (int) $settings['height'],
            'hideSelector' => $settings['hide_selector'],
        ] );
    }
}

/* ============================================================
 * CACHE-HINWEIS für Page-Cache-Plugins
 * ============================================================ */

add_action( 'template_redirect', 'stream_timer_maybe_set_cache_headers', 99 );
function stream_timer_maybe_set_cache_headers() {
    if ( is_admin() || headers_sent() ) {
        return;
    }
    if ( ! apply_filters( 'stream_timer_set_cache_headers', true ) ) {
        return;
    }
    global $post;
    if ( ! $post instanceof WP_Post ) {
        return;
    }
    if ( ! has_shortcode( (string) $post->post_content, 'stream_timer' ) ) {
        return;
    }
    // Bei eingeloggten Nutzern keine Public-Caches setzen (Admin-Bar/personalisierter Content).
    if ( is_user_logged_in() ) {
        header( 'Cache-Control: private, no-cache, max-age=0' );
        return;
    }
    header( 'Cache-Control: public, max-age=60, s-maxage=60' );
}
