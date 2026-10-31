<?php
/**
 * Plugin Name:       Posts Widget
 * Plugin URI:        https://www.inaikas.com/
 * Description:       Adds a widget and a shortcode that show the latest posts or the best posts (most viewed or most commented). You can set how many posts appear and how many posts per row.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            inaikas
 * Author URI:        https://www.inaikas.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       posts-widget
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TDD_POSTS_WIDGET_VERSION', '1.0.0');
define('TDD_POSTS_WIDGET_DB_VERSION', '2');
define('TDD_POSTS_WIDGET_FILE', __FILE__);
define('TDD_POSTS_WIDGET_DIR', plugin_dir_path(__FILE__));
define('TDD_POSTS_WIDGET_URL', plugin_dir_url(__FILE__));

require_once TDD_POSTS_WIDGET_DIR . 'includes/posts-query.php';
require_once TDD_POSTS_WIDGET_DIR . 'includes/views-table.php';
require_once TDD_POSTS_WIDGET_DIR . 'includes/posts-render.php';
require_once TDD_POSTS_WIDGET_DIR . 'includes/class-tdd-posts-widget.php';
require_once TDD_POSTS_WIDGET_DIR . 'includes/shortcode.php';

/**
 * Register the widget.
 */
function tdd_posts_widget_register_widget()
{
    register_widget('TDD_Posts_Widget');
}
add_action('widgets_init', 'tdd_posts_widget_register_widget');

/**
 * Add the view counter details to the WordPress privacy policy guide.
 */
function tdd_posts_widget_privacy_policy()
{
    if (!function_exists('wp_add_privacy_policy_content')) {
        return;
    }

    $content = '<p>' . esc_html__('When a visitor opens a single post, this plugin adds one to that post\'s view counter and stores the total number of views for each post.', 'posts-widget') . '</p>';
    $content .= '<p>' . esc_html__('To ignore repeated visits, the plugin keeps a keyed hash of the visitor IP address (not the raw address) for 30 minutes in a temporary option. The hash cannot be turned back into an address and is removed automatically.', 'posts-widget') . '</p>';
    $content .= '<p>' . esc_html__('View counting can be disabled with the tdd_posts_widget_count_views filter.', 'posts-widget') . '</p>';

    wp_add_privacy_policy_content(
        __('Posts Widget', 'posts-widget'),
        wp_kses_post($content)
    );
}
add_action('admin_init', 'tdd_posts_widget_privacy_policy');

/**
 * Register the front-end stylesheet.
 *
 * It is only enqueued when the widget is active or the shortcode is used.
 */
function tdd_posts_widget_register_style()
{
    wp_register_style(
        'tdd-posts-widget',
        TDD_POSTS_WIDGET_URL . 'assets/css/posts-widget.css',
        array(),
        TDD_POSTS_WIDGET_VERSION
    );
}
add_action('wp_enqueue_scripts', 'tdd_posts_widget_register_style');

/**
 * Enqueue the stylesheet.
 */
function tdd_posts_widget_enqueue_style()
{
    if (!wp_style_is('tdd-posts-widget', 'registered')) {
        wp_register_style(
            'tdd-posts-widget',
            TDD_POSTS_WIDGET_URL . 'assets/css/posts-widget.css',
            array(),
            TDD_POSTS_WIDGET_VERSION
        );
    }

    wp_enqueue_style('tdd-posts-widget');
}

/**
 * Enqueue the stylesheet when the widget or the shortcode is on the page.
 */
function tdd_posts_widget_maybe_enqueue_style()
{
    if (is_active_widget(false, false, 'tdd_posts_widget', true)) {
        tdd_posts_widget_enqueue_style();
        return;
    }

    $post = get_post();

    if ($post instanceof WP_Post && has_shortcode($post->post_content, 'tdd_posts')) {
        tdd_posts_widget_enqueue_style();
    }
}
add_action('wp_enqueue_scripts', 'tdd_posts_widget_maybe_enqueue_style', 20);

/**
 * Give existing posts a view counter when the plugin is activated.
 *
 * New posts get the counter from save_post_post. This covers posts published
 * before the plugin was installed, so they can appear in "most viewed" lists.
 *
 * Posts are read in small batches so activation does not load every post of a
 * large site into memory at once.
 */
function tdd_posts_widget_backfill_views_meta()
{
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

        // Prime the meta cache so metadata_exists() does not run one query per post.
        update_meta_cache('post', $post_ids);

        foreach ($post_ids as $post_id) {
            if (!metadata_exists('post', $post_id, $meta_key)) {
                add_post_meta($post_id, $meta_key, 0, true);
            }
        }

        $page++;
    } while (count($post_ids) === $batch_size);
}

/**
 * Prepare the view counter data of one site.
 */
function tdd_posts_widget_activate_site()
{
    tdd_posts_widget_backfill_views_meta();
    tdd_posts_widget_create_views_table();
    tdd_posts_widget_backfill_views_table();

    update_option('tdd_posts_widget_db_version', TDD_POSTS_WIDGET_DB_VERSION);
}

/**
 * Prepare the view counter data when the plugin is activated.
 *
 * A network-wide activation runs for every site, because each site has its own
 * posts and its own counter table.
 *
 * @param bool $network_wide Whether the plugin is activated for the network.
 */
function tdd_posts_widget_activate($network_wide = false)
{
    if (is_multisite() && $network_wide) {
        $site_ids = get_sites(
            array(
                'fields' => 'ids',
                'number' => 0,
            )
        );

        foreach ($site_ids as $site_id) {
            switch_to_blog((int) $site_id);
            tdd_posts_widget_activate_site();
            restore_current_blog();
        }

        return;
    }

    tdd_posts_widget_activate_site();
}
register_activation_hook(__FILE__, 'tdd_posts_widget_activate');
