<?php
/**
 * Uninstall — Cleanup beim Plugin-Delete.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'stream_timer_settings' );
delete_transient( 'stream_timer_is_active' );

if ( is_multisite() ) {
    $stream_timer_blog_ids = get_sites( [ 'fields' => 'ids' ] );
    foreach ( $stream_timer_blog_ids as $stream_timer_blog_id ) {
        switch_to_blog( $stream_timer_blog_id );
        delete_option( 'stream_timer_settings' );
        delete_transient( 'stream_timer_is_active' );
        restore_current_blog();
    }
}
