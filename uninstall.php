<?php
/**
 * Uninstall cleanup for Inaikas Posts Widget.
 *
 * Removes the widget settings, the cache version, the cached "best posts"
 * lists and any leftover cache rebuild locks. On Multisite every site is
 * cleaned. This runs only when the plugin is deleted from WordPress.
 *
 * @package TDD_Posts_Widget
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Remove the plugin data of the current site.
 */
function tdd_posts_widget_uninstall_site()
{
    delete_option('widget_tdd_posts_widget');
    delete_option('tdd_posts_widget_cache_version');
    delete_option('tdd_posts_widget_db_version');

    global $wpdb;

    $patterns = array(
        '_transient_tdd_posts_widget_',
        '_transient_timeout_tdd_posts_widget_',
        'tdd_posts_widget_lock_',
    );

    foreach ($patterns as $pattern) {
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like($pattern) . '%'
            )
        );
    }

    // The counts stay in post meta; only the index table is removed.
    $table = $wpdb->prefix . 'tdd_post_views';
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

if (is_multisite()) {
    $tdd_posts_widget_site_ids = get_sites(
        array(
            'fields' => 'ids',
            'number' => 0,
        )
    );

    foreach ($tdd_posts_widget_site_ids as $tdd_posts_widget_site_id) {
        switch_to_blog((int) $tdd_posts_widget_site_id);
        tdd_posts_widget_uninstall_site();
        restore_current_blog();
    }
} else {
    tdd_posts_widget_uninstall_site();
}
