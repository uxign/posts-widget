<?php
/**
 * Front-end markup shared by the widget and the shortcode.
 *
 * @package TDD_Posts_Widget
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Human readable comment count of one post.
 *
 * @param WP_Post $post Post object.
 * @return string
 */
function tdd_posts_widget_comments_label($post)
{
    $count = (int) $post->comment_count;

    if ($count < 1) {
        return '';
    }

    return sprintf(
        /* translators: %s: number of comments. */
        _n('%s comment', '%s comments', $count, 'tdd-posts-widget'),
        number_format_i18n($count)
    );
}

/**
 * Render the list of posts.
 *
 * Returns an empty string when there is nothing to show, so the widget never
 * prints an empty box.
 *
 * @param array $args Parsed or raw settings.
 * @return string
 */
function tdd_posts_widget_render_list($args)
{
    $args = tdd_posts_widget_parse_args($args);
    $posts = tdd_posts_widget_query_posts($args);

    if (empty($posts)) {
        return '';
    }

    // The stylesheet is registered on wp_enqueue_scripts. Enqueuing it here as
    // well covers shortcodes printed after the head, such as Elementor blocks.
    tdd_posts_widget_enqueue_style();

    $columns = (int) $args['columns'];
    $layout = $columns > 1 ? 'grid' : 'list';

    $classes = array(
        'tdd-posts-widget',
        'tdd-posts-widget--source-' . $args['source'],
        'tdd-posts-widget--' . $layout,
        'tdd-posts-widget--columns-' . $columns,
    );

    ob_start();
    ?>
    <div class="<?php echo esc_attr(implode(' ', $classes)); ?>"
         style="--tdd-columns:<?php echo (int) $columns; ?>">
        <ul class="tdd-posts-widget__grid">
            <?php foreach ($posts as $index => $post) : ?>
                <?php
                $permalink = get_permalink($post);
                $post_title = get_the_title($post);

                if ('' === trim((string) $post_title)) {
                    $post_title = __('(no title)', 'tdd-posts-widget');
                }

                $meta = array();

                if ($args['show_date']) {
                    $meta[] = sprintf(
                        '<time class="tdd-posts-widget__date" datetime="%1$s">%2$s</time>',
                        esc_attr(get_the_date('c', $post)),
                        esc_html(get_the_date('', $post))
                    );
                }

                if ($args['show_comments']) {
                    $comments_label = tdd_posts_widget_comments_label($post);

                    if ('' !== $comments_label) {
                        $meta[] = '<span class="tdd-posts-widget__comments">' . esc_html($comments_label) . '</span>';
                    }
                }
                ?>
                <li class="tdd-posts-widget__item">
                    <a class="tdd-posts-widget__link" href="<?php echo esc_url($permalink); ?>">
                        <?php if ($args['show_thumbnail']) : ?>
                            <span class="tdd-posts-widget__thumb">
                                <?php
                                $image_attr = array(
                                    'class'    => 'tdd-posts-widget__image',
                                    'loading'  => 0 === $index ? 'eager' : 'lazy',
                                    'decoding' => 'async',
                                    'sizes'    => 'grid' === $layout
                                        ? '(max-width: 480px) 100vw, (max-width: 782px) 50vw, 300px'
                                        : '76px',
                                );

                                if (0 === $index) {
                                    $image_attr['fetchpriority'] = 'high';
                                }

                                $thumbnail = get_the_post_thumbnail($post, 'medium', $image_attr);

                                if ($thumbnail) {
                                    echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                } else {
                                    echo '<span class="tdd-posts-widget__thumb-placeholder" aria-hidden="true"></span>';
                                }
                                ?>
                            </span>
                        <?php endif; ?>

                        <span class="tdd-posts-widget__content">
                            <span class="tdd-posts-widget__post-title"><?php echo esc_html($post_title); ?></span>

                            <?php if (!empty($meta)) : ?>
                                <span class="tdd-posts-widget__meta">
                                    <?php echo implode('<span class="tdd-posts-widget__sep" aria-hidden="true">&middot;</span>', $meta); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </span>
                            <?php endif; ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php

    return (string) ob_get_clean();
}
