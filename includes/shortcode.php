<?php
/**
 * Shortcode: [tdd_posts]
 *
 * @package TDD_Posts_Widget
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the posts list as a shortcode.
 *
 * Example:
 * [tdd_posts source="best" best_by="views" count="6" columns="2" category="recipes" title="Popular Recipes" heading_level="3"]
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function tdd_posts_widget_shortcode($atts)
{
    $atts = shortcode_atts(
        array(
            'title'           => '',
            'source'          => 'latest',
            'best_by'         => 'views',
            'count'           => 6,
            'columns'         => 1,
            'category'        => '',
            'show_thumbnail'  => '1',
            'show_date'       => '1',
            'show_comments'   => '1',
            'exclude_current' => '1',
            'heading_level'   => 2,
            'class'           => '',
        ),
        $atts,
        'tdd_posts'
    );

    $args = tdd_posts_widget_parse_args($atts);
    $html = tdd_posts_widget_render_list($args);

    if ('' === $html) {
        return '';
    }

    $classes = array('tdd-posts-widget-shortcode');
    $extra_classes = preg_split('/\s+/', trim((string) $atts['class']));

    foreach ((array) $extra_classes as $extra_class) {
        $extra_class = sanitize_html_class($extra_class);

        if ('' !== $extra_class) {
            $classes[] = $extra_class;
        }
    }

    $output = '<div class="' . esc_attr(implode(' ', $classes)) . '">';

    if ('' !== trim((string) $args['title'])) {
        $heading_level = (int) $args['heading_level'];
        $output .= '<h' . $heading_level . ' class="tdd-posts-widget-shortcode__title">' . esc_html($args['title']) . '</h' . $heading_level . '>';
    }

    $output .= $html;
    $output .= '</div>';

    return $output;
}
add_shortcode('tdd_posts', 'tdd_posts_widget_shortcode');

/**
 * Build a copy-ready shortcode from widget settings.
 *
 * It is shown inside the widget form, so the shortcode is discoverable from
 * WordPress itself without reading the plugin files.
 *
 * @param array $args Parsed settings.
 * @return string
 */
function tdd_posts_widget_shortcode_example($args)
{
    $args = tdd_posts_widget_parse_args($args);

    $parts = array(
        'source="' . $args['source'] . '"',
    );

    if ('best' === $args['source']) {
        $parts[] = 'best_by="' . $args['best_by'] . '"';
    }

    $parts[] = 'count="' . (int) $args['count'] . '"';

    if ((int) $args['columns'] > 1) {
        $parts[] = 'columns="' . (int) $args['columns'] . '"';
    }

    if (!empty($args['category'])) {
        $category = get_category((int) $args['category']);
        $category_value = $category && !is_wp_error($category)
            ? $category->slug
            : (string) (int) $args['category'];

        $parts[] = 'category="' . $category_value . '"';
    }

    $title = (string) $args['title'];

    if ('' !== trim($title) && false === strpos($title, '"')) {
        $parts[] = 'title="' . $title . '"';
    }

    if (empty($args['show_thumbnail'])) {
        $parts[] = 'show_thumbnail="0"';
    }

    if (empty($args['show_date'])) {
        $parts[] = 'show_date="0"';
    }

    if (empty($args['show_comments'])) {
        $parts[] = 'show_comments="0"';
    }

    if (empty($args['exclude_current'])) {
        $parts[] = 'exclude_current="0"';
    }

    if (2 !== (int) $args['heading_level']) {
        $parts[] = 'heading_level="' . (int) $args['heading_level'] . '"';
    }

    return '[tdd_posts ' . implode(' ', $parts) . ']';
}
