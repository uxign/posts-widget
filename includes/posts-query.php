<?php
/**
 * Post queries used by the widget and the shortcode.
 *
 * @package TDD_Posts_Widget
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Default settings of one widget instance.
 *
 * @return array
 */
function tdd_posts_widget_defaults()
{
    return array(
        'title'            => '',
        'source'           => 'latest',
        'best_by'          => 'views',
        'count'            => 6,
        'columns'          => 1,
        'category'         => 0,
        'show_thumbnail'   => 1,
        'show_date'        => 1,
        'show_comments'    => 1,
        'exclude_current'  => 1,
        'heading_level'    => 2,
    );
}

/**
 * Turn common truthy and falsy values into a boolean.
 *
 * Shortcode users write show_date="0" or show_date="false"; both must hide the
 * date. PHP's empty() would treat the string "false" as true.
 *
 * @param mixed $value Raw value.
 * @return bool
 */
function tdd_posts_widget_to_bool($value)
{
    if (is_bool($value)) {
        return $value;
    }

    if (is_numeric($value)) {
        return 0 !== (int) $value;
    }

    if (is_string($value)) {
        return in_array(strtolower(trim($value)), array('1', 'true', 'yes', 'on'), true);
    }

    return !empty($value);
}

/**
 * Clean and normalize one widget instance.
 *
 * Accepts the values saved by the widget form and the attributes of the
 * shortcode. Every value is clamped to a safe range, so a bad entry can never
 * break the query or the layout.
 *
 * @param array $args Raw settings.
 * @return array
 */
function tdd_posts_widget_parse_args($args)
{
    $defaults = tdd_posts_widget_defaults();
    $args = wp_parse_args((array) $args, $defaults);

    $args['title'] = sanitize_text_field((string) $args['title']);
    $args['source'] = in_array($args['source'], array('latest', 'best'), true) ? $args['source'] : 'latest';
    $args['best_by'] = in_array($args['best_by'], array('views', 'comments'), true) ? $args['best_by'] : 'views';
    $args['count'] = max(1, min(12, absint($args['count'])));
    $args['columns'] = max(1, min(4, absint($args['columns'])));
    $args['category'] = tdd_posts_widget_normalize_category($args['category']);
    $args['show_thumbnail'] = tdd_posts_widget_to_bool($args['show_thumbnail']) ? 1 : 0;
    $args['show_date'] = tdd_posts_widget_to_bool($args['show_date']) ? 1 : 0;
    $args['show_comments'] = tdd_posts_widget_to_bool($args['show_comments']) ? 1 : 0;
    $args['exclude_current'] = tdd_posts_widget_to_bool($args['exclude_current']) ? 1 : 0;
    $args['heading_level'] = max(2, min(6, absint($args['heading_level'])));

    return $args;
}

/**
 * Turn a category ID or slug into a category ID.
 *
 * @param mixed $value Category ID, slug or empty value.
 * @return int
 */
function tdd_posts_widget_normalize_category($value)
{
    if (is_numeric($value)) {
        return absint($value);
    }

    $slug = sanitize_title((string) $value);

    if ('' === $slug) {
        return 0;
    }

    $term = get_category_by_slug($slug);

    // A slug that does not exist should show nothing, not every post.
    return $term ? (int) $term->term_id : -1;
}

/**
 * Meta key that stores the view count.
 *
 * The site already has historical view counts in this key. A filter is
 * available in case the counter moves to another key later.
 *
 * @return string
 */
function tdd_posts_widget_views_meta_key()
{
    return (string) apply_filters('tdd_posts_widget_views_meta_key', '_benqu_views_count');
}

/**
 * Build the WP_Query arguments for one widget instance.
 *
 * @param array $args Parsed settings.
 * @return array
 */
function tdd_posts_widget_build_query_args($args)
{
    $query_args = array(
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => (int) $args['count'],
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
    );

    if ('best' === $args['source']) {
        if ('comments' === $args['best_by']) {
            // "Best" by the number of comments.
            $query_args['orderby'] = array(
                'comment_count' => 'DESC',
                'date'          => 'DESC',
            );
        } else {
            // "Best" by views. The indexed counter table is joined in
            // tdd_posts_widget_views_join(); this flag only marks the query.
            // Date is the fallback order when the table is not available.
            $query_args['tdd_views_order'] = 1;
            $query_args['orderby'] = array(
                'date' => 'DESC',
            );
        }
    } else {
        $query_args['orderby'] = 'date';
        $query_args['order'] = 'DESC';
    }

    if (!empty($args['category'])) {
        if ((int) $args['category'] > 0) {
            $query_args['cat'] = (int) $args['category'];
        } else {
            // The shortcode used a category slug that does not exist.
            $query_args['post__in'] = array(0);
        }
    }

    /**
     * Filter the query used by the widget and the shortcode.
     *
     * @param array $query_args WP_Query arguments.
     * @param array $args       Parsed widget settings.
     */
    return (array) apply_filters('tdd_posts_widget_query_args', $query_args, $args);
}

/**
 * Current version of the "best posts" cache.
 *
 * The version is increased whenever a comment or a post changes, so old
 * transients are ignored without having to delete them one by one.
 *
 * @return int
 */
function tdd_posts_widget_cache_version()
{
    return max(1, (int) get_option('tdd_posts_widget_cache_version', 1));
}

/**
 * Invalidate the "best posts" cache.
 */
function tdd_posts_widget_bump_cache_version()
{
    update_option('tdd_posts_widget_cache_version', tdd_posts_widget_cache_version() + 1, false);
}

/**
 * Invalidate the cache when a comment is added, edited or deleted.
 */
function tdd_posts_widget_bump_cache_on_comment()
{
    tdd_posts_widget_bump_cache_version();
}
add_action('wp_insert_comment', 'tdd_posts_widget_bump_cache_on_comment');
add_action('edit_comment', 'tdd_posts_widget_bump_cache_on_comment');
add_action('deleted_comment', 'tdd_posts_widget_bump_cache_on_comment');
add_action('transition_comment_status', 'tdd_posts_widget_bump_cache_on_comment');

/**
 * Invalidate the cache when a post is published, unpublished or deleted.
 *
 * @param string  $new_status New post status.
 * @param string  $old_status Old post status.
 * @param WP_Post $post       Post object.
 */
function tdd_posts_widget_bump_cache_on_post($new_status, $old_status, $post)
{
    if (!$post instanceof WP_Post || 'post' !== $post->post_type) {
        return;
    }

    if ($new_status !== $old_status) {
        tdd_posts_widget_bump_cache_version();
    }
}
add_action('transition_post_status', 'tdd_posts_widget_bump_cache_on_post', 10, 3);
add_action('deleted_post', 'tdd_posts_widget_bump_cache_version');

/**
 * Give every new post a view counter, even before its first visit.
 *
 * A post without the meta key would be missing from the "most viewed" list,
 * because WP_Query uses an inner join for meta_key ordering.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @param bool    $update  Whether this is an existing post.
 */
function tdd_posts_widget_seed_views_meta($post_id, $post, $update)
{
    if (!$post instanceof WP_Post || 'post' !== $post->post_type) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    $meta_key = tdd_posts_widget_views_meta_key();

    if (!metadata_exists('post', $post_id, $meta_key)) {
        add_post_meta($post_id, $meta_key, 0, true);
    }

    tdd_posts_widget_seed_views_row($post_id);
}
add_action('save_post_post', 'tdd_posts_widget_seed_views_meta', 10, 3);

/**
 * Normalize an address so one visitor maps to one throttle entry.
 *
 * IPv6 visitors usually control a whole /64 prefix and can rotate addresses
 * inside it, so only the network part is kept. IPv4 addresses are kept whole.
 *
 * @param string $ip Raw address.
 * @return string
 */
function tdd_posts_widget_normalize_ip($ip)
{
    $ip = trim((string) $ip);

    if ('' === $ip) {
        return '';
    }

    $packed = @inet_pton($ip); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

    if (false === $packed) {
        // Hostname or malformed value: keep it, it is only used as a hash input.
        return $ip;
    }

    if (4 === strlen($packed)) {
        return (string) inet_ntop($packed);
    }

    // IPv4-mapped IPv6 (::ffff:192.0.2.1) is an IPv4 visitor.
    if ("\0\0\0\0\0\0\0\0\0\0\xff\xff" === substr($packed, 0, 12)) {
        return (string) inet_ntop(substr($packed, 12));
    }

    // Keep the /64 prefix and drop the interface identifier.
    return (string) inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8));
}

/**
 * Decide whether this visitor is new for this post inside the throttle window.
 *
 * One transient per post holds a bounded map of visitor hashes, so the options
 * table does not grow by one row per visitor and post.
 *
 * @param int    $post_id Post ID.
 * @param string $ip      Visitor address.
 * @return bool
 */
function tdd_posts_widget_is_new_view($post_id, $ip)
{
    /**
     * Filter the throttle window, in seconds. Return 0 to count every view.
     *
     * @param int $window  Throttle window in seconds.
     * @param int $post_id Post ID.
     */
    $window = (int) apply_filters('tdd_posts_widget_view_window', 30 * MINUTE_IN_SECONDS, $post_id);

    if ($window < 1) {
        return true;
    }

    /**
     * Filter how many distinct visitors are tracked per post and window.
     *
     * The limit keeps the stored map small and caps how far the counter can be
     * inflated from many addresses. New visitors are ignored once it is reached.
     *
     * @param int $limit   Maximum tracked visitors.
     * @param int $post_id Post ID.
     */
    $limit = (int) apply_filters('tdd_posts_widget_view_window_limit', 1000, $post_id);

    $key = 'tdd_posts_widget_view_' . (int) $post_id;
    $seen = get_transient($key);
    $seen = is_array($seen) ? $seen : array();

    $visitor = md5(wp_hash(tdd_posts_widget_normalize_ip($ip)));

    if (isset($seen[$visitor])) {
        return false;
    }

    if ($limit > 0 && count($seen) >= $limit) {
        return false;
    }

    $seen[$visitor] = time();

    set_transient($key, $seen, $window);

    return true;
}

/**
 * Increase the view counter of one post.
 *
 * The increment happens in SQL so two concurrent visits cannot overwrite each
 * other's count.
 *
 * @param int    $post_id  Post ID.
 * @param string $meta_key View counter meta key.
 */
function tdd_posts_widget_increment_views($post_id, $meta_key)
{
    global $wpdb;

    $updated = $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->postmeta} SET meta_value = meta_value + 1 WHERE post_id = %d AND meta_key = %s",
            $post_id,
            $meta_key
        )
    );

    if (false === $updated) {
        return;
    }

    if (0 === (int) $updated) {
        // The post had no counter yet: create it without racing a duplicate.
        add_post_meta($post_id, $meta_key, 1, true);
    }

    wp_cache_delete($post_id, 'post_meta');

    // Keep the indexed table in step with the meta counter.
    tdd_posts_widget_increment_views_row($post_id);

    /**
     * Fires after a post view has been counted.
     *
     * @param int    $post_id  Post ID.
     * @param string $meta_key View counter meta key.
     */
    do_action('tdd_posts_widget_view_counted', $post_id, $meta_key);
}

/**
 * Count one front-end view of a single post.
 *
 * Views are ignored for editors, bots, previews, feeds, REST requests and
 * repeated visits from the same address within 30 minutes. Counting can be
 * disabled with the tdd_posts_widget_count_views filter.
 */
function tdd_posts_widget_count_view()
{
    if (!is_singular('post') || is_admin() || is_preview() || is_feed() || is_robots() || is_customize_preview()) {
        return;
    }

    if (wp_doing_ajax() || wp_doing_cron()) {
        return;
    }

    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }

    if (is_user_logged_in() && current_user_can('edit_posts')) {
        return;
    }

    $post_id = (int) get_queried_object_id();

    if (!$post_id) {
        return;
    }

    /**
     * Filter whether this request counts as a view.
     *
     * @param bool $count   Whether to count the view.
     * @param int  $post_id Post ID.
     */
    if (!apply_filters('tdd_posts_widget_count_views', true, $post_id)) {
        return;
    }

    $user_agent = isset($_SERVER['HTTP_USER_AGENT'])
        ? strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])))
        : '';

    $bots = array('bot', 'crawl', 'spider', 'slurp', 'facebookexternalhit', 'preview', 'monitor', 'curl', 'wget', 'python', 'go-http-client', 'headless');

    foreach ($bots as $bot) {
        if (false !== strpos($user_agent, $bot)) {
            return;
        }
    }

    $ip = isset($_SERVER['REMOTE_ADDR'])
        ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
        : '';

    /**
     * Filter the visitor address used for the view throttle.
     *
     * When the site runs behind a proxy or CDN, REMOTE_ADDR holds the proxy
     * address and every visitor shares one throttle entry. Only read a client
     * address from a trusted proxy: forwarding headers can be spoofed.
     *
     * @param string $ip      Visitor address.
     * @param int    $post_id Post ID.
     */
    $ip = (string) apply_filters('tdd_posts_widget_viewer_ip', $ip, $post_id);

    if (!tdd_posts_widget_is_new_view($post_id, $ip)) {
        return;
    }

    tdd_posts_widget_increment_views($post_id, tdd_posts_widget_views_meta_key());
}
add_action('template_redirect', 'tdd_posts_widget_count_view');

/**
 * Load one cache entry as post objects.
 *
 * An empty array means the query ran and found nothing; that result is cached
 * too, so a cold list is not rebuilt on every request. False means there is no
 * cache entry yet.
 *
 * @param string $key Cache key.
 * @return array|false
 */
function tdd_posts_widget_get_cached_posts($key)
{
    $ids = get_transient($key);

    if (!is_array($ids)) {
        return false;
    }

    $posts = array();

    foreach ($ids as $id) {
        $post = get_post((int) $id);

        if ($post instanceof WP_Post && 'publish' === $post->post_status) {
            $posts[] = $post;
        }
    }

    return $posts;
}

/**
 * Try to take the cache rebuild lock for one list.
 *
 * The lock makes a single request rebuild a cold or expired list while other
 * requests wait briefly, instead of all of them running the same heavy query.
 * A lock older than a few seconds is treated as abandoned and taken over.
 *
 * @param string $key Lock option name.
 * @param int    $ttl Seconds after which an existing lock is considered stale.
 * @return bool
 */
function tdd_posts_widget_acquire_lock($key, $ttl = 10)
{
    if (add_option($key, time(), '', 'no')) {
        return true;
    }

    $started = (int) get_option($key);

    if ($started > 0 && (time() - $started) > $ttl) {
        update_option($key, time(), false);

        return true;
    }

    return false;
}

/**
 * Release the cache rebuild lock.
 *
 * @param string $key Lock option name.
 */
function tdd_posts_widget_release_lock($key)
{
    delete_option($key);
}

/**
 * Fetch the posts for one widget instance.
 *
 * The "best posts" list is cached for a few minutes because ordering by a meta
 * value or by comment count cannot use the date index. Latest posts are never
 * cached.
 *
 * @param array $args Parsed settings.
 * @return WP_Post[]
 */
function tdd_posts_widget_get_posts($args)
{
    return tdd_posts_widget_query_posts(tdd_posts_widget_parse_args($args));
}

/**
 * Fetch the posts for already parsed settings.
 *
 * Internal companion of tdd_posts_widget_get_posts() so an instance is parsed
 * once per render instead of once per call.
 *
 * @param array $args Parsed settings.
 * @return WP_Post[]
 */
function tdd_posts_widget_query_posts($args)
{
    $query_args = tdd_posts_widget_build_query_args($args);

    // The indexed table is the fast path. Fall back to the meta counter when
    // it is not available yet, for example during an interrupted upgrade.
    if (!empty($query_args['tdd_views_order']) && !tdd_posts_widget_views_table_ready()) {
        unset($query_args['tdd_views_order']);
        $query_args['meta_key'] = tdd_posts_widget_views_meta_key();
        $query_args['orderby'] = array(
            'meta_value_num' => 'DESC',
            'date'           => 'DESC',
        );
    }

    // Fetch one extra post so the post being viewed can be removed without
    // leaving a gap in the list.
    $current_id = 0;

    if (!empty($args['exclude_current']) && is_singular('post')) {
        $current_id = (int) get_queried_object_id();

        if ($current_id) {
            $query_args['posts_per_page'] = (int) $args['count'] + 1;
        }
    }

    /**
     * Filter how long the "best posts" list is cached, in seconds.
     *
     * Return 0 to disable the cache.
     *
     * @param int   $ttl  Cache lifetime in seconds.
     * @param array $args Parsed widget settings.
     */
    $cache_ttl = (int) apply_filters('tdd_posts_widget_cache_ttl', 300, $args);

    $cache_key = '';
    $lock_key = '';
    $locked = false;

    if ('best' === $args['source'] && $cache_ttl > 0) {
        $cache_key = 'tdd_posts_widget_' . md5(
            wp_json_encode(
                array(
                    'query'   => $query_args,
                    'version' => tdd_posts_widget_cache_version(),
                )
            )
        );

        $cached = tdd_posts_widget_get_cached_posts($cache_key);

        if (false !== $cached) {
            return tdd_posts_widget_finish_posts($cached, $args, $current_id);
        }

        $lock_key = 'tdd_posts_widget_lock_' . md5($cache_key);
        $locked = tdd_posts_widget_acquire_lock($lock_key);

        if (!$locked) {
            // Another request is already rebuilding this list. Wait a moment,
            // then use its result instead of running the same query again.
            usleep(100000);

            $cached = tdd_posts_widget_get_cached_posts($cache_key);

            if (false !== $cached) {
                return tdd_posts_widget_finish_posts($cached, $args, $current_id);
            }
        }
    }

    try {
        $query = new WP_Query($query_args);
        $posts = $query->posts;

        if (!is_array($posts)) {
            $posts = array();
        }

        if ('' !== $cache_key) {
            set_transient($cache_key, wp_list_pluck($posts, 'ID'), $cache_ttl);
        }
    } finally {
        if ($locked && '' !== $lock_key) {
            tdd_posts_widget_release_lock($lock_key);
        }
    }

    return tdd_posts_widget_finish_posts($posts, $args, $current_id);
}

/**
 * Remove the current post and trim the list to the requested length.
 *
 * @param WP_Post[] $posts      Posts from the query or the cache.
 * @param array     $args       Parsed settings.
 * @param int       $current_id Post ID to remove (0 to keep every post).
 * @return WP_Post[]
 */
function tdd_posts_widget_finish_posts($posts, $args, $current_id)
{
    if ($current_id) {
        $posts = array_values(
            array_filter(
                $posts,
                static function ($post) use ($current_id) {
                    return (int) $post->ID !== $current_id;
                }
            )
        );
    }

    return array_slice($posts, 0, (int) $args['count']);
}
