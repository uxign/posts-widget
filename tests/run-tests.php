<?php
/**
 * Integration tests for Posts Widget.
 *
 * Run from the command line, for example:
 *
 *     php tests/run-tests.php
 *
 * The script boots the WordPress install that contains this plugin, then checks
 * the query, cache, counter and rendering helpers. It exits with status 1 when
 * a check fails.
 *
 * @package TDD_Posts_Widget
 */

error_reporting(E_ALL);

define('WP_USE_THEMES', false);

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/index.php';

$wp_load = dirname(__DIR__, 4) . '/wp-load.php';

if (!is_readable($wp_load)) {
    fwrite(STDERR, 'Could not find wp-load.php at ' . $wp_load . PHP_EOL);
    exit(1);
}

require $wp_load;

if (!function_exists('tdd_posts_widget_render_list')) {
    require dirname(__DIR__) . '/tdd-posts-widget.php';
}

// Make sure the indexed counter table exists before any best-by-views query.
tdd_posts_widget_create_views_table();
tdd_posts_widget_backfill_views_table();

$GLOBALS['tdd_test_failures'] = 0;
$GLOBALS['tdd_test_checks'] = 0;

/**
 * Compare two values and print the result.
 *
 * @param string $label    Check label.
 * @param mixed  $actual   Actual value.
 * @param mixed  $expected Expected value.
 */
function tdd_test_check($label, $actual, $expected)
{
    $GLOBALS['tdd_test_checks']++;
    $ok = $actual === $expected;

    if (!$ok) {
        $GLOBALS['tdd_test_failures']++;
    }

    echo ($ok ? 'PASS ' : 'FAIL ') . $label
        . ' | got ' . var_export($actual, true)
        . ' expected ' . var_export($expected, true)
        . PHP_EOL;
}

/**
 * Print a section title.
 *
 * @param string $title Section name.
 */
function tdd_test_section($title)
{
    echo PHP_EOL . '--- ' . $title . ' ---' . PHP_EOL;
}

/**
 * Delete every transient and lock that belongs to the plugin.
 */
function tdd_test_clean_cache()
{
    global $wpdb;

    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_tdd_posts_widget_%'
            OR option_name LIKE '_transient_timeout_tdd_posts_widget_%'
            OR option_name LIKE 'tdd_posts_widget_lock_%'"
    );
}

tdd_test_section('Settings parsing');

$parsed = tdd_posts_widget_parse_args(
    array(
        'count'         => 0,
        'columns'       => 99,
        'show_date'     => 'false',
        'show_comments' => '0',
        'heading_level' => 9,
    )
);

tdd_test_check('count is clamped low', $parsed['count'], 1);
tdd_test_check('columns is clamped high', $parsed['columns'], 4);
tdd_test_check('"false" hides the date', $parsed['show_date'], 0);
tdd_test_check('"0" hides comments', $parsed['show_comments'], 0);
tdd_test_check('heading level is clamped high', $parsed['heading_level'], 6);

$parsed = tdd_posts_widget_parse_args(array('count' => 99, 'heading_level' => 1));
tdd_test_check('count is clamped high', $parsed['count'], 12);
tdd_test_check('heading level is clamped low', $parsed['heading_level'], 2);

tdd_test_section('Visitor address normalization');

tdd_test_check('IPv4 is kept whole', tdd_posts_widget_normalize_ip('203.0.113.9'), '203.0.113.9');
tdd_test_check('same IPv6 /64', tdd_posts_widget_normalize_ip('2001:db8:1:2:ffff::9'), tdd_posts_widget_normalize_ip('2001:db8:1:2::1'));
tdd_test_check('different IPv6 /64', tdd_posts_widget_normalize_ip('2001:db8:1:3::1'), '2001:db8:1:3::');
tdd_test_check('IPv4 mapped in IPv6', tdd_posts_widget_normalize_ip('::ffff:192.0.2.10'), '192.0.2.10');

tdd_test_section('View throttle');

$throttle_post = 999999;
$throttle_key = 'tdd_posts_widget_view_' . $throttle_post;
delete_transient($throttle_key);

tdd_test_check('first IPv4 view counts', tdd_posts_widget_is_new_view($throttle_post, '203.0.113.9'), true);
tdd_test_check('repeat IPv4 view is ignored', tdd_posts_widget_is_new_view($throttle_post, '203.0.113.9'), false);
tdd_test_check('second IPv4 view counts', tdd_posts_widget_is_new_view($throttle_post, '203.0.113.10'), true);

delete_transient($throttle_key);
tdd_test_check('first IPv6 view counts', tdd_posts_widget_is_new_view($throttle_post, '2001:db8:1:2::1'), true);
tdd_test_check('rotated IPv6 /64 is ignored', tdd_posts_widget_is_new_view($throttle_post, '2001:db8:1:2:ffff::9'), false);

delete_transient($throttle_key);
$limit_filter = static function () {
    return 1;
};
add_filter('tdd_posts_widget_view_window_limit', $limit_filter);
tdd_test_check('visitor cap allows the first visitor', tdd_posts_widget_is_new_view($throttle_post, '198.51.100.1'), true);
tdd_test_check('visitor cap blocks a new visitor', tdd_posts_widget_is_new_view($throttle_post, '198.51.100.2'), false);
remove_filter('tdd_posts_widget_view_window_limit', $limit_filter);

delete_transient($throttle_key);

tdd_test_section('Rendering and shortcode');

$html = tdd_posts_widget_render_list(array('source' => 'latest', 'count' => 3));
tdd_test_check('list contains three items', substr_count($html, 'tdd-posts-widget__item'), 3);
tdd_test_check('list has the image sizes attribute', false !== strpos($html, 'sizes='), true);

$shortcode = do_shortcode('[tdd_posts count="3" title="Popular" heading_level="3"]');
tdd_test_check('shortcode uses the requested heading', false !== strpos($shortcode, '<h3 class="tdd-posts-widget-shortcode__title">'), true);
tdd_test_check('shortcode closes the heading', false !== strpos($shortcode, '</h3>'), true);

$shortcode = do_shortcode('[tdd_posts count="3" title="Popular" heading_level="9"]');
tdd_test_check('shortcode clamps the heading to h6', false !== strpos($shortcode, '<h6 class="tdd-posts-widget-shortcode__title">'), true);

$shortcode = do_shortcode('[tdd_posts count="3" title="Popular" heading_level="1"]');
tdd_test_check('shortcode clamps the heading to h2', false !== strpos($shortcode, '<h2 class="tdd-posts-widget-shortcode__title">'), true);

$shortcode = do_shortcode('[tdd_posts count="3" show_thumbnail="false"]');
tdd_test_check('"false" hides thumbnails', false !== strpos($shortcode, 'tdd-posts-widget__thumb'), false);

tdd_test_check('unknown category shows nothing', do_shortcode('[tdd_posts category="tdd-does-not-exist"]'), '');

$example_shortcode = tdd_posts_widget_shortcode_example(
    array(
        'source'  => 'best',
        'best_by' => 'comments',
        'count'   => 4,
        'columns' => 3,
    )
);
tdd_test_check(
    'shortcode example matches widget settings',
    $example_shortcode,
    '[tdd_posts source="best" best_by="comments" count="4" columns="3"]'
);
tdd_test_check('shortcode example renders posts', false !== strpos(do_shortcode($example_shortcode), 'tdd-posts-widget__item'), true);

tdd_test_section('Cache helpers');

$missing_key = 'tdd_posts_widget_test_missing';
delete_transient($missing_key);
tdd_test_check('missing cache returns false', tdd_posts_widget_get_cached_posts($missing_key), false);

set_transient($missing_key, array(), 60);
tdd_test_check('stored empty list returns an empty array', tdd_posts_widget_get_cached_posts($missing_key), array());
delete_transient($missing_key);

$first_post = get_posts(array('numberposts' => 1, 'fields' => 'ids', 'post_status' => 'publish'));
set_transient($missing_key, array((int) $first_post[0]), 60);
tdd_test_check('stored post id returns one post', count(tdd_posts_widget_get_cached_posts($missing_key)), 1);
delete_transient($missing_key);

tdd_test_section('Negative cache and stampede lock');

tdd_test_clean_cache();
$cache_args = tdd_posts_widget_parse_args(
    array(
        'source'   => 'best',
        'category' => 'tdd-does-not-exist',
        'count'    => 3,
    )
);

$captured_key = '';
$capture = static function ($key) use (&$captured_key) {
    if (0 === strpos($key, 'tdd_posts_widget_')) {
        $captured_key = $key;
    }
};
add_action('set_transient', $capture);

$query_count = 0;
$query_counter = static function () use (&$query_count) {
    $query_count++;
};
add_action('pre_get_posts', $query_counter);

tdd_posts_widget_query_posts($cache_args);
$first_query_count = $query_count;
tdd_posts_widget_query_posts($cache_args);
$second_query_count = $query_count;

remove_action('pre_get_posts', $query_counter);
remove_action('set_transient', $capture);

tdd_test_check('first empty result runs one query', $first_query_count, 1);
tdd_test_check('second empty result is served from cache', $second_query_count, $first_query_count);
tdd_test_check('empty result was cached', '' !== $captured_key, true);
tdd_test_check('cached empty result is an array', get_transient($captured_key), array());

$lock_key = 'tdd_posts_widget_lock_' . md5('tdd-test-lock');
delete_option($lock_key);
tdd_test_check('first lock request succeeds', tdd_posts_widget_acquire_lock($lock_key), true);
tdd_test_check('second lock request is blocked', tdd_posts_widget_acquire_lock($lock_key), false);
update_option($lock_key, time() - 60, false);
tdd_test_check('stale lock can be taken over', tdd_posts_widget_acquire_lock($lock_key), true);
tdd_posts_widget_release_lock($lock_key);
tdd_test_check('released lock is gone', get_option($lock_key), false);

tdd_test_clean_cache();

tdd_test_section('Indexed counter table');

global $wpdb;
$views_table = tdd_posts_widget_views_table();

tdd_test_check('counter table exists', tdd_posts_widget_views_table_ready(), true);

$views_engine = $wpdb->get_var(
    $wpdb->prepare(
        'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
        $views_table
    )
);
tdd_test_check('counter table uses InnoDB', $views_engine, 'InnoDB');

$view_rows = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$views_table}");
tdd_test_check('backfill filled the counter table', $view_rows > 0, true);

$top_expected = array_map(
    'intval',
    $wpdb->get_col(
        $wpdb->prepare(
            "SELECT v.post_id
             FROM {$views_table} v
             INNER JOIN {$wpdb->posts} p ON p.ID = v.post_id
             WHERE p.post_type = 'post' AND p.post_status = 'publish'
             ORDER BY v.views DESC, v.post_id DESC
             LIMIT %d",
            3
        )
    )
);

tdd_test_clean_cache();
$order_args = tdd_posts_widget_parse_args(
    array(
        'source'          => 'best',
        'best_by'         => 'views',
        'count'           => 3,
        'exclude_current' => 0,
    )
);
$ordered_posts = tdd_posts_widget_query_posts($order_args);
tdd_test_check('best order matches the index', wp_list_pluck($ordered_posts, 'ID'), $top_expected);

$join_query = new WP_Query(tdd_posts_widget_build_query_args($order_args));
tdd_test_check('best query joins the counter table', false !== strpos($join_query->request, 'tdd_post_views'), true);
tdd_test_check('best query orders by the table', false !== strpos($join_query->request, 'tdd_pw_views.views DESC'), true);
tdd_test_check(
    'best query lets the views index drive the join',
    false !== strpos($join_query->request, 'FROM ' . $views_table . ' AS tdd_pw_views STRAIGHT_JOIN'),
    true
);
tdd_test_check(
    'the sort has no cross-table column',
    false !== strpos($join_query->request, 'ORDER BY tdd_pw_views.views DESC, tdd_pw_views.post_id DESC'),
    true
);

$category_args = array_merge($order_args, array('category' => 1));
$category_query = new WP_Query(tdd_posts_widget_build_query_args($category_args));
tdd_test_check(
    'a category filter keeps the default join order',
    false !== strpos($category_query->request, 'FROM ' . $wpdb->posts . ' '),
    true
);
tdd_test_check(
    'a category filter still joins the counter table',
    false !== strpos($category_query->request, 'INNER JOIN ' . $views_table),
    true
);

tdd_test_section('View counter and activation backfill');

$test_post_id = wp_insert_post(
    array(
        'post_title'  => 'TDD Posts Widget test post',
        'post_status' => 'publish',
        'post_type'   => 'post',
    )
);

if (!$test_post_id || is_wp_error($test_post_id)) {
    tdd_test_check('test post was created', false, true);
} else {
    $meta_key = tdd_posts_widget_views_meta_key();
    delete_post_meta($test_post_id, $meta_key);
    update_post_meta($test_post_id, $meta_key, 0);

    tdd_posts_widget_increment_views($test_post_id, $meta_key);
    tdd_posts_widget_increment_views($test_post_id, $meta_key);

    tdd_test_check('two increments add two', (int) get_post_meta($test_post_id, $meta_key, true), 2);

    $table_views = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT views FROM {$views_table} WHERE post_id = %d",
            $test_post_id
        )
    );
    tdd_test_check('the index mirror adds two', $table_views, 2);

    $rows = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
            $test_post_id,
            $meta_key
        )
    );
    tdd_test_check('the counter uses one meta row', $rows, 1);

    delete_post_meta($test_post_id, $meta_key);
    tdd_posts_widget_activate();
    tdd_test_check('activation backfills the counter', metadata_exists('post', $test_post_id, $meta_key), true);

    $row_exists = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$views_table} WHERE post_id = %d",
            $test_post_id
        )
    );
    tdd_test_check('activation keeps the index row', $row_exists, 1);

    wp_delete_post($test_post_id, true);

    $row_after = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$views_table} WHERE post_id = %d",
            $test_post_id
        )
    );
    tdd_test_check('deleting a post removes its index row', $row_after, 0);
}

tdd_test_section('Template independence');

$css = file_get_contents(dirname(__DIR__) . '/assets/css/posts-widget.css');
tdd_test_check('stylesheet does not depend on a template class', false !== strpos($css, '.single-post-area .widget'), false);
tdd_test_check('stylesheet resets the list item', false !== strpos($css, 'li.tdd-posts-widget__item'), true);

tdd_test_section('Package files');

tdd_test_check('uninstall handles Multisite', false !== strpos(file_get_contents(dirname(__DIR__) . '/uninstall.php'), 'is_multisite()'), true);
tdd_test_check('uninstall drops the index table', false !== strpos(file_get_contents(dirname(__DIR__) . '/uninstall.php'), 'DROP TABLE IF EXISTS'), true);
tdd_test_check('phpcs ruleset exists', file_exists(dirname(__DIR__) . '/phpcs.xml.dist'), true);
tdd_test_check('readme.txt ships with the plugin', file_exists(dirname(__DIR__) . '/readme.txt'), true);
tdd_test_check('readme.txt documents the shortcode', false !== strpos(file_get_contents(dirname(__DIR__) . '/readme.txt'), '[tdd_posts'), true);
tdd_test_check(
    'widget form shows a shortcode example',
    false !== strpos(file_get_contents(dirname(__DIR__) . '/includes/class-tdd-posts-widget.php'), 'tdd_posts_widget_shortcode_example'),
    true
);

echo PHP_EOL
    . ($GLOBALS['tdd_test_failures'] > 0
        ? 'FAILURES: ' . $GLOBALS['tdd_test_failures'] . ' of ' . $GLOBALS['tdd_test_checks'] . ' checks'
        : 'ALL ' . $GLOBALS['tdd_test_checks'] . ' CHECKS PASSED')
    . PHP_EOL;

exit($GLOBALS['tdd_test_failures'] > 0 ? 1 : 0);
