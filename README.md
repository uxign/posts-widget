# Posts Widget

Adds a widget and a shortcode that show the latest posts or the best posts
(most viewed or most commented). Each instance controls the number of posts and
the number of posts per row.

## Widget

1. Go to **Appearance → Widgets**.
2. Add **Posts List (Latest / Best)** to the sidebar.
3. Choose:
   - **Show:** latest posts or best posts.
   - **Rank best posts by:** most viewed or most commented.
   - **Number of posts:** 1 to 12.
   - **Posts per row:** 1 to 4. Use 1 in the sidebar; 2 to 4 suit wide areas.
   - **Category:** all categories or one category.
   - Show featured image, date and comment count.
   - Hide the post being viewed.

The widget prints nothing when no posts match, so it never leaves an empty box.

## Shortcode

The widget form also shows a ready shortcode built from that widget's saved
settings, so it can be copied without reading these docs. The same reference is
available from **Plugins → View details**.

```
[tdd_posts]
[tdd_posts source="best" count="6"]
[tdd_posts source="best" best_by="comments" count="6"]
[tdd_posts source="latest" count="8" columns="3" category="recipes"]
[tdd_posts source="best" count="4" columns="2" title="Popular Recipes" show_comments="0"]
[tdd_posts source="best" count="6" title="Popular" heading_level="3"]
```

### Attributes

| Attribute | Default | Description |
| --- | --- | --- |
| `title` | empty | Optional heading above the list. |
| `source` | `latest` | `latest` or `best`. |
| `best_by` | `views` | `views` or `comments`. Only used with `source="best"`. |
| `count` | `6` | Number of posts, 1 to 12. |
| `columns` | `1` | Posts per row, 1 to 4. |
| `category` | empty | Category ID or slug. Empty means all categories. |
| `show_thumbnail` | `1` | `1` or `0`. |
| `show_date` | `1` | `1` or `0`. |
| `show_comments` | `1` | `1` or `0`. |
| `exclude_current` | `1` | Hide the post being viewed. |
| `heading_level` | `2` | Heading tag for `title`, 2 to 6. |
| `class` | empty | Extra CSS class on the shortcode wrapper. |

## Best posts

- **Most viewed** reads the view counter stored in the `_benqu_views_count` post
  meta. Every counted view updates the meta and a small indexed table
  (`{prefix}tdd_post_views`), so ordering does not depend on a meta join or a
  filesort. On a site with thousands of posts the table index is read in order
  and MySQL stops after the requested number of rows.
- The table has one row per post (primary key `post_id`) plus a `views` index.
  It is created with InnoDB so each counted view takes a row lock instead of
  locking the whole table. The meta counter stays the source of truth; the table
  is a mirror. When the table is missing, or an upgrade was interrupted,
  best-by-views falls back to the meta counter automatically and the table is
  rebuilt on the next request.
- New views are counted on single posts, with repeated visits from the same
  address ignored for 30 minutes. Editors, bots, previews and REST requests are
  not counted.
- The counter is increased in SQL, so two visitors loading the same post at the
  same time cannot overwrite each other's count.
- IPv6 visitors are grouped by their /64 prefix, because one visitor usually
  controls a whole prefix and can rotate addresses inside it.
- Repeat visits are tracked in one transient per post (not one per visitor), so
  the options table does not grow with every visitor. The map holds up to 1000
  visitors per post and window; beyond that new visitors are not counted, which
  also caps how far the counter can be inflated.
- **Most commented** orders by the WordPress comment count, then by date.
- Most viewed ties are broken by the newest post ID; most commented ties fall
  back to the newest post.
- The best posts list is cached for 5 minutes and cleared automatically when a
  comment or a post changes. Use the `tdd_posts_widget_cache_ttl` filter to
  change the cache lifetime, or return `0` to disable it.
- Empty results are cached too, so a category with no matching posts does not
  run the same query on every request.
- When a cached list expires, one request rebuilds it while the others wait
  briefly and reuse the result (a lock prevents a cache stampede).
- A category filter lets MySQL choose the join order; the views index drives the
  join only when the list covers all categories.

## Filters

```php
// Change the meta key used for the view counter.
add_filter('tdd_posts_widget_views_meta_key', function () {
    return '_my_views_count';
});

// Stop counting views (for example when another plugin already does it).
add_filter('tdd_posts_widget_count_views', '__return_false');

// Keep the best posts list fresh for one minute.
add_filter('tdd_posts_widget_cache_ttl', function () {
    return 60;
});

// Read the client address from a trusted proxy (Cloudflare example).
add_filter('tdd_posts_widget_viewer_ip', function ($ip) {
    return isset($_SERVER['HTTP_CF_CONNECTING_IP'])
        ? sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']))
        : $ip;
});

// Change the repeat-visit window (0 counts every view) and the visitor cap.
add_filter('tdd_posts_widget_view_window', function () {
    return 15 * MINUTE_IN_SECONDS;
});
add_filter('tdd_posts_widget_view_window_limit', function () {
    return 500;
});
```

Only read forwarding headers in `tdd_posts_widget_viewer_ip` when a trusted
proxy sets them; otherwise they can be spoofed.

Best-by-views marks the query with `tdd_views_order` instead of `meta_key`; the
plugin adds the counter join and the order itself. Keep that flag when you
change the query through `tdd_posts_widget_query_args`, or the list will not be
ordered by views.

The `tdd_posts_widget_view_counted` action fires after a view is stored:

```php
add_action('tdd_posts_widget_view_counted', function ($post_id, $meta_key) {
    // Mirror the counter somewhere else.
}, 10, 2);
```

## Notes

- The stylesheet is only loaded when the widget is active or the shortcode is
  used, and its selectors stay inside the widget so it does not depend on a
  theme class.
- No build step and no external dependencies.
- Activating the plugin adds a view counter to published posts and fills the
  indexed counter table in batches, so a large site is not loaded into memory at
  once. On Multisite a network activation prepares every site, and each site
  gets its own table.
- Uninstalling the plugin removes the widget settings, cached lists and leftover
  locks on every site of a Multisite network. It drops the indexed counter table
  but keeps the `_benqu_views_count` post meta, so existing counts are not lost.
- The view throttle stores a keyed hash of the visitor address (never the raw
  address) for 30 minutes, in the options table or in the object cache when one
  is available.

## Development

- `phpcs.xml.dist` configures the WordPress coding standards for the plugin.
- `php tests/run-tests.php` boots this WordPress install and checks settings
  parsing, the view counter, the throttle, the cache, the indexed counter table
  and the rendered markup. The script exits with status 1 when a check fails.
"# posts-widget" 
