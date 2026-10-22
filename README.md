# Harriman Projects

Per-project templates for the existing `project` post type. A project stays on the current layout until an editor turns on `_ww_use_new_template` for that post.

The theme renders its Blade layout inside `template_include` at the highest priority, and `content-single-project.blade.php` prints only `the_content()`. This plugin replaces `the_content` for a flagged single project. The header, footer, and `/projects/` archive are unchanged. `single.blade.php` and `layouts/app.blade.php` do not print a title or hero.

## Hooks

### `ww_template_card_images`

Filters the three picker images. Return three URLs. Numeric keys `1`, `2`, and `3` match the templates. A plain list of three URLs is also accepted.

```php
add_filter( 'ww_template_card_images', function ( $images ) {
	$images[1] = 'https://example.com/template-1.jpg';
	$images[2] = 'https://example.com/template-2.jpg';
	$images[3] = 'https://example.com/template-3.jpg';
	return $images;
} );
```

### `ww_heading_levels`

Filters the heading tag for each template. Overview uses `strong` on template 1 and `h6` on templates 2 and 3. Template 2's statistic is `h3` until the Figma file says otherwise.

```php
add_filter( 'ww_heading_levels', function ( $levels ) {
	$levels[2]['stat'] = 'h2';
	return $levels;
} );
```

Slots: `title`, `subhead`, `location`, `stat`, `overview`.

## REST

`POST /wp-json/ww/v1/projects/{id}/delete-legacy`

Requires a logged-in user with `edit_others_posts`, the `wp_rest` nonce, and `_ww_use_new_template` already saved. It deletes `_ww_legacy_content` and blanks that post's `post_content`. It does not change market, service, or project-location terms, the featured image, testimonial posts, or reusable blocks.

## Meta

`_ww_use_new_template` turns the new template on for one project. `_ww_template` is `1`, `2`, or `3`. Field values use the `_ww_` keys in `includes/class-ww-fields.php`. Switching templates does not delete stored keys the new template does not show.
