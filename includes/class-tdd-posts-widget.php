<?php
/**
 * Posts widget.
 *
 * @package TDD_Posts_Widget
 */

if (!defined('ABSPATH')) {
    exit;
}

class TDD_Posts_Widget extends WP_Widget
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(
            'tdd_posts_widget',
            __('Posts List (Latest / Best)', 'tdd-posts-widget'),
            array(
                'classname'                   => 'widget_tdd_posts_widget',
                'description'                 => __('Shows the latest posts or the best posts (most viewed or most commented), with controls for the number of posts and the number of posts per row.', 'tdd-posts-widget'),
                'customize_selective_refresh' => true,
            )
        );
    }

    /**
     * Front-end output.
     *
     * @param array $args     Sidebar arguments.
     * @param array $instance Widget settings.
     */
    public function widget($args, $instance)
    {
        $instance = tdd_posts_widget_parse_args($instance);
        $html = tdd_posts_widget_render_list($instance);

        if ('' === $html) {
            return;
        }

        $title = apply_filters('widget_title', $instance['title'], $instance, $this->id_base);

        echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        if ('' !== trim((string) $title)) {
            echo $args['before_title'] . $title . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Admin form.
     *
     * @param array $instance Widget settings.
     */
    public function form($instance)
    {
        $instance = tdd_posts_widget_parse_args($instance);
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php esc_html_e('Title:', 'tdd-posts-widget'); ?>
            </label>
            <input class="widefat"
                   id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>"
                   type="text"
                   value="<?php echo esc_attr($instance['title']); ?>"
                   placeholder="<?php esc_attr_e('e.g. Popular Recipes', 'tdd-posts-widget'); ?>">
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('source')); ?>">
                <?php esc_html_e('Show:', 'tdd-posts-widget'); ?>
            </label>
            <select class="widefat"
                    id="<?php echo esc_attr($this->get_field_id('source')); ?>"
                    name="<?php echo esc_attr($this->get_field_name('source')); ?>">
                <option value="latest" <?php selected($instance['source'], 'latest'); ?>>
                    <?php esc_html_e('Latest posts', 'tdd-posts-widget'); ?>
                </option>
                <option value="best" <?php selected($instance['source'], 'best'); ?>>
                    <?php esc_html_e('Best posts', 'tdd-posts-widget'); ?>
                </option>
            </select>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('best_by')); ?>">
                <?php esc_html_e('Rank best posts by:', 'tdd-posts-widget'); ?>
            </label>
            <select class="widefat"
                    id="<?php echo esc_attr($this->get_field_id('best_by')); ?>"
                    name="<?php echo esc_attr($this->get_field_name('best_by')); ?>">
                <option value="views" <?php selected($instance['best_by'], 'views'); ?>>
                    <?php esc_html_e('Most viewed', 'tdd-posts-widget'); ?>
                </option>
                <option value="comments" <?php selected($instance['best_by'], 'comments'); ?>>
                    <?php esc_html_e('Most commented', 'tdd-posts-widget'); ?>
                </option>
            </select>
            <span class="description">
                <?php esc_html_e('Only used when "Best posts" is selected.', 'tdd-posts-widget'); ?>
            </span>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('count')); ?>">
                <?php esc_html_e('Number of posts:', 'tdd-posts-widget'); ?>
            </label>
            <input class="tiny-text"
                   id="<?php echo esc_attr($this->get_field_id('count')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('count')); ?>"
                   type="number"
                   step="1"
                   min="1"
                   max="12"
                   value="<?php echo esc_attr($instance['count']); ?>">
            <span class="description"><?php esc_html_e('1 to 12.', 'tdd-posts-widget'); ?></span>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('columns')); ?>">
                <?php esc_html_e('Posts per row:', 'tdd-posts-widget'); ?>
            </label>
            <input class="tiny-text"
                   id="<?php echo esc_attr($this->get_field_id('columns')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('columns')); ?>"
                   type="number"
                   step="1"
                   min="1"
                   max="4"
                   value="<?php echo esc_attr($instance['columns']); ?>">
            <span class="description">
                <?php esc_html_e('1 is recommended in the sidebar. Use 2 to 4 in wide areas.', 'tdd-posts-widget'); ?>
            </span>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('category')); ?>">
                <?php esc_html_e('Category:', 'tdd-posts-widget'); ?>
            </label>
            <?php
            wp_dropdown_categories(
                array(
                    'name'             => $this->get_field_name('category'),
                    'id'               => $this->get_field_id('category'),
                    'class'            => 'widefat',
                    'selected'         => (int) $instance['category'],
                    'show_option_all'  => __('All categories', 'tdd-posts-widget'),
                    'option_none_value' => '0',
                    'hide_empty'       => false,
                    'hierarchical'     => true,
                    'value_field'      => 'term_id',
                )
            );
            ?>
        </p>

        <p>
            <input class="checkbox"
                   type="checkbox"
                   id="<?php echo esc_attr($this->get_field_id('show_thumbnail')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('show_thumbnail')); ?>"
                   value="1" <?php checked(1, $instance['show_thumbnail']); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_thumbnail')); ?>">
                <?php esc_html_e('Show featured image', 'tdd-posts-widget'); ?>
            </label>
        </p>

        <p>
            <input class="checkbox"
                   type="checkbox"
                   id="<?php echo esc_attr($this->get_field_id('show_date')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('show_date')); ?>"
                   value="1" <?php checked(1, $instance['show_date']); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_date')); ?>">
                <?php esc_html_e('Show date', 'tdd-posts-widget'); ?>
            </label>
        </p>

        <p>
            <input class="checkbox"
                   type="checkbox"
                   id="<?php echo esc_attr($this->get_field_id('show_comments')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('show_comments')); ?>"
                   value="1" <?php checked(1, $instance['show_comments']); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_comments')); ?>">
                <?php esc_html_e('Show comment count', 'tdd-posts-widget'); ?>
            </label>
        </p>

        <p>
            <input class="checkbox"
                   type="checkbox"
                   id="<?php echo esc_attr($this->get_field_id('exclude_current')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('exclude_current')); ?>"
                   value="1" <?php checked(1, $instance['exclude_current']); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('exclude_current')); ?>">
                <?php esc_html_e('Hide the post being viewed', 'tdd-posts-widget'); ?>
            </label>
        </p>

        <hr>
        <p>
            <strong><?php esc_html_e('Use the same list anywhere with a shortcode', 'tdd-posts-widget'); ?></strong>
        </p>
        <p class="description">
            <?php esc_html_e('Paste this into a post, a page, or an Elementor shortcode widget. It uses the saved settings above:', 'tdd-posts-widget'); ?>
        </p>
        <p>
            <input class="widefat code"
                   type="text"
                   readonly
                   onclick="this.select();"
                   value="<?php echo esc_attr(tdd_posts_widget_shortcode_example($instance)); ?>">
        </p>
        <p class="description">
            <?php esc_html_e('Attributes: source (latest or best), best_by (views or comments), count (1-12), columns (1-4), category (ID or slug), title, show_thumbnail, show_date, show_comments, exclude_current (1 or 0), heading_level (2-6), class.', 'tdd-posts-widget'); ?>
        </p>
        <?php
    }

    /**
     * Save settings.
     *
     * @param array $new_instance New settings.
     * @param array $old_instance Old settings.
     * @return array
     */
    public function update($new_instance, $old_instance)
    {
        $new_instance = (array) $new_instance;

        return tdd_posts_widget_parse_args(
            array(
                'title'           => isset($new_instance['title']) ? $new_instance['title'] : '',
                'source'          => isset($new_instance['source']) ? $new_instance['source'] : 'latest',
                'best_by'         => isset($new_instance['best_by']) ? $new_instance['best_by'] : 'views',
                'count'           => isset($new_instance['count']) ? $new_instance['count'] : 6,
                'columns'         => isset($new_instance['columns']) ? $new_instance['columns'] : 1,
                'category'        => isset($new_instance['category']) ? $new_instance['category'] : 0,
                'show_thumbnail'  => !empty($new_instance['show_thumbnail']),
                'show_date'       => !empty($new_instance['show_date']),
                'show_comments'   => !empty($new_instance['show_comments']),
                'exclude_current' => !empty($new_instance['exclude_current']),
            )
        );
    }
}
