=== Inaikas Posts Widget ===
Contributors: inaikas
Tags: widget, posts, popular posts, most viewed, shortcode
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A widget and a shortcode that show the latest posts or the best posts (most viewed or most commented).

== Description ==

Add a list of posts to a sidebar, or place the same list anywhere with a shortcode.

* Choose latest posts or best posts.
* Rank best posts by views or by comment count.
* Set the number of posts (1 to 12) and how many posts fit in one row (1 to 4).
* Filter by category.
* Show or hide the featured image, date and comment count.
* Hide the post that is currently being viewed.

The widget prints nothing when no posts match, so it never leaves an empty box.

== Installation ==

1. Upload the plugin folder to `wp-content/plugins/`.
2. Activate the plugin in **Plugins**.
3. Go to **Appearance → Widgets** and add **Posts List (Latest / Best)** to a sidebar.

== Shortcode ==

The same list can be placed in a post, a page, or an Elementor shortcode widget.
The widget form shows a ready example built from its current settings.

`[tdd_posts]`

`[tdd_posts source="best" count="6"]`

`[tdd_posts source="best" best_by="comments" count="6"]`

`[tdd_posts source="latest" count="8" columns="3" category="recipes"]`

`[tdd_posts source="best" count="4" columns="2" title="Popular Recipes" show_comments="0"]`

Attributes:

* `title` - optional heading above the list.
* `source` - `latest` or `best`. Default `latest`.
* `best_by` - `views` or `comments`. Default `views`. Only used with `source="best"`.
* `count` - number of posts, 1 to 12. Default `6`.
* `columns` - posts per row, 1 to 4. Default `1`.
* `category` - category ID or slug. Empty means all categories.
* `show_thumbnail` - `1` or `0`. Default `1`.
* `show_date` - `1` or `0`. Default `1`.
* `show_comments` - `1` or `0`. Default `1`.
* `exclude_current` - hide the post being viewed, `1` or `0`. Default `1`.
* `heading_level` - heading tag for `title`, 2 to 6. Default `2`.
* `class` - extra CSS class on the shortcode wrapper.

== Frequently Asked Questions ==

= Where do the view counts come from? =

The widget reads the view counter stored in the `_benqu_views_count` post meta.
Views are counted on single posts. Editors, bots, previews and REST requests are
not counted, and a repeat visit from the same address is ignored for 30 minutes.

= Does it work on a large site? =

Yes. Counts are also kept in an indexed table, so ordering by most viewed does
not depend on a meta join or a filesort. The table is filled in batches when the
plugin is activated and stays in sync with the meta counter.

= What happens when I uninstall it? =

Widget settings, cached lists and the index table are removed. The view counts
stored in post meta are kept.

== Privacy ==

When a visitor opens a single post, the plugin adds one to that post's view
counter and stores the total number of views for each post.

To ignore repeated visits, the plugin keeps a keyed hash of the visitor IP
address (not the raw address) for 30 minutes in a temporary option. The hash
cannot be turned back into an address, and it is removed automatically when the
30-minute window ends. The plugin does not send data to any external service.

View counting can be disabled with the `tdd_posts_widget_count_views` filter.
Suggested privacy policy text is also available in **Settings → Privacy → Policy
Guide**.

== Changelog ==

= 1.0.0 =
* First public release: widget and shortcode with latest, most viewed and most commented lists.
* Controls for the number of posts, posts per row, category, featured image, date and comment count.
* Indexed view counter table for large sites, with an automatic fallback to post meta.
* View throttling, IPv6 grouping and suggested privacy policy text.
* Multisite activation and clean uninstall.
