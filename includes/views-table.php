<?php
/**
 * Indexed view counter table.
 *
 * Historical counts live in the `_benqu_views_count` post meta. Ordering posts
 * by a meta value needs a join and a filesort, which gets slower as the site
 * grows. This table keeps one indexed row per post so "most viewed" can use an
 * index instead.
 *
 * The meta counter stays the source of truth. The table is updated on every
 * counted view and rebuilt from the meta value when a row is missing.
 *
 * @package TDD_Posts_Widget
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Name of the counter table for the current site.
 *
 * @return string
 */
function tdd_posts_widget_views_table()
{
    global $wpdb;

    return $wpdb->prefix . 'tdd_post_views';
}

/**
 * Create or update the counter table.
 */
function tdd_posts_widget_create_views_table()
{
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = tdd_posts_widget_views_table();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        post_id bigint(20) unsigned NOT NULL,
        views bigint(20) unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY  (post_id),
        KEY views_index (views)
    ) ENGINE=InnoDB {$charset_collate};";

    dbDelta($sql);

    // A server whose default storage engine is MyISAM would otherwise create
    // the counter with table-level locks, serializing every counted view.
    tdd_posts_widget_ensure_views_engine();
}

/**
 * Move the counter table to InnoDB when it was created with another engine.
 *
 * The table takes one small write per view, so row-level locking matters on a
 * busy site. dbDelta does not reliably change the engine of an existing table.
 */
function tdd_posts_widget_ensure_views_engine()
{
    global $wpdb;

    $table = tdd_posts_widget_views_table();

    $engine = $wpdb->get_var(
        $wpdb->prepare(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
            $table
        )
    );

    if ($engine && 'InnoDB' !== $engine) {
        $wpdb->query("ALTER TABLE {$table} ENGINE=InnoDB");
    }
}

/**
 * Whether the counter table exists for the current site.
 *
 * The result is cached for the request. Best-by-views falls back to the meta
 * counter when the table is not available yet.
 *
 * @return bool
 */
function tdd_posts_widget_views_table_ready()
{
    static $ready = null;

    if (null !== $ready) {
        return $ready;
    }

    global $wpdb;

    $table = tdd_posts_widget_views_table();
    $ready = (bool) $wpdb->get_var(
        $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))
    );

    return $ready;
}

/**
 * Give one post a counter row, using its meta value when the row is new.
 *
 * Existing rows are never lowered, so a newer table value is kept.
 *
 * @param int $post_id Post ID.
 */
function tdd_posts_widget_seed_views_row($post_id)
{
    if (!tdd_posts_widget_views_table_ready()) {
        return;
    }

    global $wpdb;

    $table = tdd_posts_widget_views_table();
    $views = max(0, (int) get_post_meta((int) $post_id, tdd_posts_widget_views_meta_key(), true));

    $wpdb->query(
        $wpdb->prepare(
            "INSERT INTO {$table} (post_id, views) VALUES (%d, %d)
             ON DUPLICATE KEY UPDATE views = IF(views < %d, %d, views)",
            $post_id,
            $views,
            $views,
            $views
        )
    );
}

/**
 * Add one view to the counter row of a post.
 *
 * The meta counter is updated separately; this only keeps the index in step.
 *
 * @param int $post_id Post ID.
 */
function tdd_posts_widget_increment_views_row($post_id)
{
    if (!tdd_posts_widget_views_table_ready()) {
        return;
    }

    global $wpdb;

    $table = tdd_posts_widget_views_table();

    $wpdb->query(
        $wpdb->prepare(
            "INSERT INTO {$table} (post_id, views) VALUES (%d, 1)
             ON DUPLICATE KEY UPDATE views = views + 1",
            $post_id
        )
    );
}

/**
 * Remove the counter row of a deleted post.
 *
 * @param int $post_id Post ID.
 */
function tdd_posts_widget_delete_views_row($post_id)
{
    if (!tdd_posts_widget_views_table_ready()) {
        return;
    }

    global $wpdb;

    $wpdb->delete(tdd_posts_widget_views_table(), array('post_id' => (int) $post_id), array('%d'));
}
add_action('deleted_post', 'tdd_posts_widget_delete_views_row');

/**
 * Fill the counter table from the meta counter, in batches.
 *
 * Existing rows are never lowered: the table may already hold views counted
 * after the last backfill.
 */
function tdd_posts_widget_backfill_views_table()
{
    global $wpdb;

    $table = tdd_posts_widget_views_table();
    $meta_key = tdd_posts_widget_views_meta_key();
    $batch_size = 200;
    $page = 1;

    do {
        $post_ids = get_posts(
            array(
                'post_type'        => 'post',
                'post_status'      => 'publish',
                'posts_per_page'   => $batch_size,
                'paged'            => $page,
                'orderby'          => 'ID',
                'order'            => 'ASC',
                'fields'           => 'ids',
                'no_found_rows'    => true,
            )
        );

        if (empty($post_ids)) {
            break;
        }

        update_meta_cache('post', $post_ids);

        foreach ($post_ids as $post_id) {
            $views = max(0, (int) get_post_meta($post_id, $meta_key, true));

            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$table} (post_id, views) VALUES (%d, %d)
                     ON DUPLICATE KEY UPDATE views = IF(views < %d, %d, views)",
                    $post_id,
                    $views,
                    $views,
                    $views
                )
            );
        }

        $page++;
    } while (count($post_ids) === $batch_size);
}

/**
 * Replace the meta join with the indexed counter table in a best-by-views query.
 *
 * @param array    $clauses Query clauses.
 * @param WP_Query $query   Query object.
 * @return array
 */
function tdd_posts_widget_views_join($clauses, $query)
{
    if (!$query instanceof WP_Query || !$query->get('tdd_views_order')) {
        return $clauses;
    }

    if (!tdd_posts_widget_views_table_ready()) {
        return $clauses;
    }

    global $wpdb;

    $table = tdd_posts_widget_views_table();

    $clauses['join'] .= " INNER JOIN {$table} AS tdd_pw_views ON tdd_pw_views.post_id = {$wpdb->posts}.ID";
    // Both sort columns come from the counter table so the views index can
    // return rows already sorted. Sorting by post date here would force MySQL
    // to read every joined row and sort them all.
    $clauses['orderby'] = "tdd_pw_views.views DESC, tdd_pw_views.post_id DESC";

    return $clauses;
}
add_filter('posts_clauses', 'tdd_posts_widget_views_join', 10, 2);

/**
 * Put the counter table first in the join so its views index drives the order.
 *
 * WP_Query always starts the FROM clause with the posts table. Rewriting the
 * finished SQL is the only way to let MySQL read the already sorted counter
 * index and stop after the requested number of posts.
 *
 * A taxonomy filter is left to the optimizer: when a category is narrow, MySQL
 * may prefer to filter posts first.
 *
 * @param string   $request The complete SQL query.
 * @param WP_Query $query   Query object.
 * @return string
 */
function tdd_posts_widget_views_request($request, $query)
{
    if (!$query instanceof WP_Query || !$query->get('tdd_views_order')) {
        return $request;
    }

    if (!tdd_posts_widget_views_table_ready()) {
        return $request;
    }

    if ($query->get('cat') || $query->get('category_name') || $query->get('tax_query')) {
        return $request;
    }

    global $wpdb;

    $table = tdd_posts_widget_views_table();

    $pattern = '/FROM\s+' . preg_quote($wpdb->posts, '/')
        . '\s+INNER\s+JOIN\s+' . preg_quote($table, '/')
        . '\s+AS\s+tdd_pw_views\s+ON\s+tdd_pw_views\.post_id\s*=\s*'
        . preg_quote($wpdb->posts, '/') . '\.ID/';

    $replacement = 'FROM ' . $table . ' AS tdd_pw_views STRAIGHT_JOIN ' . $wpdb->posts
        . ' ON tdd_pw_views.post_id = ' . $wpdb->posts . '.ID';

    return (string) preg_replace($pattern, $replacement, $request, 1);
}
add_filter('posts_request', 'tdd_posts_widget_views_request', 10, 2);

/**
 * Create and fill the counter table when the plugin or the site is updated.
 *
 * Running this on init covers sites of a network that were created after the
 * plugin was activated, and an interrupted activation.
 */
function tdd_posts_widget_maybe_upgrade()
{
    if (TDD_POSTS_WIDGET_DB_VERSION === (string) get_option('tdd_posts_widget_db_version')) {
        return;
    }

    tdd_posts_widget_create_views_table();
    tdd_posts_widget_backfill_views_table();

    update_option('tdd_posts_widget_db_version', TDD_POSTS_WIDGET_DB_VERSION);
}
add_action('init', 'tdd_posts_widget_maybe_upgrade', 5);
