# Harriman Projects

Per-project templates for the existing `project` post type. A project renders a new template when `_ww_template` is `1`, `2`, or `3`. Every other project keeps the theme layout.

The theme renders its Blade layout inside `template_include` at the highest priority, and `content-single-project.blade.php` prints only `the_content()`. This plugin replaces `the_content` for a single project that has a saved template. The header, footer, and `/projects/` archive are unchanged. `single.blade.php` and `layouts/app.blade.php` do not print a title or hero.

## Hooks

### `ww_template_card_images`

Filters the three picker images. Return three URLs. Numeric keys `1`, `2`, and `3` match the templates. A plain list of three URLs is also accepted. The defaults are `assets/img/Template1.svg`, `Template2.svg`, and `Template3.svg`.

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

### Other filters

- `ww_term_aliases` maps an imported label onto an existing term name.
- `ww_title_abbreviations` expands short titles before they are compared.
- `ww_image_alt_fallback` replaces the generated alt when an image has none.
- `ww_related_projects_args` changes the related-projects query. `no_found_rows` and `fields` stay forced.
- `ww_cta_text` and `ww_cta_url` change the closing button.

## Updates

Version 1.2.4. A saved template number is what turns a project onto the new layout. The editor no longer has a Project template sidebar panel. Old stored content is left in place until someone clears it by hand: `post_content`, `_ww_legacy_content`, `_ww_legacy_deleted`, `_ww_use_new_template`, and `_ww_import_prev`. The Headline / Project Name block edits the post title. It is the only required field, and an existing project's permalink stays put. Image previews in the editor keep a gap before Select and Remove. A project page uses the theme page header for the breadcrumb and title, so the spacing matches `/projects/`. The hero still overlaps the gray band. Spaces around each breadcrumb `›` are wider, and words in the project title keep their normal spacing. Images use the full attachment size again. Chips and links inside the template are not underlined. The breadcrumb links stay underlined. In the editor, only the selected template's panel is visible, and the block canvas stays hidden.

WordPress installs newer versions from [GitHub releases](https://github.com/scotthill04210/harriman-projects/releases). On the Plugins screen, **Check for updates** looks immediately. Publish the next version as a release tag such as `v1.2.5`.

## Remove importer

After launch, remove the importer in one step:

1. Delete the folder `includes/importer/` (Slickplan import, project backup, and `ww-slickplan-admin.js`).
2. Delete the `require_once` for `includes/importer/load.php` in `harriman-projects.php`.

Leave stored meta alone: `_ww_slickplan_page_id`, `_ww_import_prev`, and `_ww_import_created`. The mapping file and the XML live outside the web root in `ww-private`, next to the project backups. They are not part of the plugin. To turn the importer off without deleting it, define `WW_ENABLE_IMPORTER` as false before the plugin loads.

## Meta

`_ww_template` is `1`, `2`, or `3` and chooses the layout. Field values use the `_ww_` keys in `includes/class-ww-fields.php`. Switching templates does not delete stored keys the new template does not show.

Older values stay in the database until they are cleared by hand: `post_content`, `_ww_legacy_content`, `_ww_legacy_deleted`, `_ww_use_new_template`, and `_ww_import_prev`. The plugin no longer reads the on/off flag to decide the layout, and it does not write or delete those values. The importer still reads `_ww_use_new_template` only so a project that was explicitly turned off stays unchecked in a dry run.
