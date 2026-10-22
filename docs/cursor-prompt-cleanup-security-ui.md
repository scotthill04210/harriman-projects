# Cursor Prompt: polish, cleanup, security and speed pass (`harriman-projects`, prefix `ww`)

## 0. Rules
- Commit and tag the current state first (`git tag pre-cleanup`) so every deletion below is recoverable. Work in small commits.
- Do not change saved data, meta keys, the importer's behavior, or the front-end design, except the one tag-chip change in section 1. If something here would change behavior, stop and report instead.
- Report at the end: files changed, files deleted (full list), findings by severity (high / medium / low) with what you fixed, plugin version.

## 1. Tag chips: remove the underline
In the project front end CSS, the market and service chips (links inside the terms list) must have no underline in any state: `text-decoration: none` for normal, hover, focus and visited. Keep a visible focus indicator (`:focus-visible` outline) for keyboard users. Scope it under `.ww-project`. Nothing else about the chips changes (11px Medium, `rgba(53,60,68,.8)` on `#f2f1ef`, links to archives stay).

## 2. New template card images
- I added three new images to `assets/img/`. List the folder, identify the three new files (they are SVGs, probably `Template1.svg`, `Template2.svg`, `Template3.svg`; match each to its template by name and report the mapping), and use these three instead of the old placeholders. Delete the old placeholders (`template-1.svg`, `template-2.svg`, `template-3.svg`). Careful: names differ only by case and hyphen, so verify before deleting.
- Update the default of the `ww_template_card_images` filter to the three new files. Keep the filter itself.
- The files are SVG wrappers around raster JPEGs and differ in shape: Template 1 viewBox 144 x 486, Template 2 144 x 630, Template 3 144 x 504 (all 144 wide, tall page-length previews). Do NOT edit the files and do NOT inline them; load them with `<img>`.
- Because they are 144px-wide rasters, never display them wider than 144 CSS px (no upscaling, or they blur). Layout: three equal-width cards in one centered row (card width about 220px, gap 24px), each card a radio (keep the current accessible radio inputs and keyboard focus), image centered at its natural 144px width with its own aspect ratio (set `width`/`height` attributes from the viewBox, `object-fit: contain`), image top-aligned, label under it ("Template 1", "Template 2", "Template 3"). Cards share one height (the tallest) so the row looks even, with the shorter previews top-aligned and neutral empty space below. Clear selected, hover and focus states. Below about 700px wide the cards wrap to a single column. Add alt text like "Template 1 layout preview".

## 3. Back end forms: narrower, centered, cleaner
Edit `assets/css/ww-admin.css` only (scoped under `#ww-project-template`); no markup changes unless unavoidable.
- The card picker and the active template form sit in one centered column: `max-width: 920px; margin-inline: auto; width: 100%`. Below 782px (WordPress mobile) use full width with 12px side padding.
- Tidy the look: consistent 16px vertical rhythm between blocks, a single light 1px border (`#dcdcde`) and 4px radius on each block, block headers (`summary`) with comfortable padding (12px 16px) and a clear hover and `:focus-visible` state, helper text 12-13px in muted gray under the field, inputs and textareas `width: 100%` with a 40px input height, image rows aligned in a tidy grid, repeater rows with consistent spacing, and the taxonomy checklists (Location, Markets, Services) in a scrollable box (max-height about 220px) in two or three columns on wide screens. Keep native WordPress colors and fonts; do not restyle global admin elements.
- Do not break: TinyMCE editors, the media modal, drag-to-reorder, the sticky bar, the Gutenberg sidebar. Check with the sidebar open and closed and at 1100, 1440 and 1920px wide, and at 390px. No horizontal scrollbar. Admin CSS must still load only on the project edit screen.

## 4. Remove files we do not need
- Delete the plugin's `docs/` folder (the spec MD files, the HTML exports and anything else in it). BEFORE deleting, search the whole plugin for `docs/` and `WW_..._DIR . 'docs'` references. The importer's mapping file (`ww_project_import_map.csv`) and any XML it reads must keep working: move the mapping file next to the private import folder that already holds the XML (outside the web root, same place as the uploaded export) and update the importer to read it from there. Confirm the XML is NOT under `docs/` any more.
- Audit the rest of the plugin (and report each file): remove unused PHP, templates, CSS, JS, images, backup or `.bak` files, test or diagnostic scripts, and any empty stub. If a template CSS file is empty (for example `ww-template-1.css`), delete it and stop enqueuing it (enqueue a template file only when it exists and is non-empty). Keep one short root `README.md` with the hooks and filters list. Keep `.gitignore`.
- Outside the plugin, report (do not delete without asking): `resources/post-types/project.php.bak` in the theme, and any other leftover backups or one-time files.

## 5. Make the importer removable in one step
We will delete the whole importer after launch. Isolate it: all importer PHP, JS, CSS, the admin pages (Tools: Slickplan import, Project backup) and the mapping data live in one folder (for example `includes/importer/` with its own assets), loaded by exactly ONE `require` line guarded by a constant or filter (`WW_ENABLE_IMPORTER`, default true). Nothing outside that folder may call importer code. Then deleting the folder plus that one line leaves the plugin working. Prove it: with the importer disabled via the constant, the editor, forms, saves and front end all work. Keep the stored import meta (`_ww_slickplan_page_id`, `_ww_import_prev`) untouched. Add a "Remove importer" section to the README listing exactly what to delete.

## 6. Security review (fix what you find, report the rest)
Check and fix, with file and line for each finding:
- Every save, REST route and AJAX handler has a nonce check, a capability check and a `permission_callback` (no `__return_true`). The importer and backup screens require `manage_options`. The delete-legacy action stays admin or editor with the typed confirmation and stays refused without an archive.
- Input is sanitized and output escaped everywhere (`sanitize_*`, `esc_*`, `wp_kses_post` for rich text). No raw `$_GET` / `$_POST` / `$_REQUEST` use. Every PHP file starts with the `ABSPATH` guard. No `unserialize()` on user-controlled data, no `eval`, no `extract`.
- SQL: any custom query uses `$wpdb->prepare()`; prefer `WP_Query` and core APIs.
- Importer: XML is parsed with external entities and network access disabled (`LIBXML_NONET`, never `LIBXML_NOENT`, no DTD loading); image downloads go only to an allow-list of hosts (Slickplan and, only for the one known case, harriman.com), use `wp_safe_remote_get` / `wp_http_validate_url`, enforce a size limit and an image MIME check, and block internal / private IP addresses (SSRF). Uploaded replacement XML is stored outside the web root with a non-guessable name and an `index.php` / deny rule; the folder cannot be listed or fetched over HTTP (verify with a request).
- The project backup file: outside the web root or denied, random name, admin-only download with a nonce, and old backups pruned (keep the last 3).
- No debug output, `var_dump`, `error_log` of personal data, or leftover test endpoints. No secrets in the repo. No open redirects.
- REST: `_ww_*` meta that should not be public is not exposed (`_ww_legacy_content`, `_ww_import_prev`, `_ww_slickplan_page_id` stay out of REST).

## 7. Speed and database review (measure before and after)
- On staging only, use a temporary `SAVEQUERIES` check (or Query Monitor if installed) and record, for Hunter's Shop (#23505), Lyseth (#21441) and Maine Air National Guard Civil Engineering Building: total queries, the slowest 5 queries with timings, and plugin-added queries. Remove the temporary check afterwards. Report before and after numbers.
- Related Projects: one query with `no_found_rows => true`, `fields => 'ids'` then prime caches in bulk (`_prime_post_caches` / `update_post_thumbnail_cache`), no `ORDER BY RAND`, no `posts_per_page => -1`, no `meta_query` on unindexed meta. Keep the 12-hour transient and clear it on `save_post`, term changes, and post deletion. Fix anything slower than about 50ms.
- Front-end render: read post meta once per request (one `get_post_meta( $id )` array), prefetch every attachment (`_prime_post_caches`) for hero, slider, grid and single images before the loop over them, no N+1 queries, no work on non-project requests (early exit at the top of the `the_content` filter, and the plugin must add zero queries on other pages).
- Images: use `wp_get_attachment_image()` (or equivalent) with `srcset` and `sizes` so large originals are not served to small screens; hero image `loading="eager"` with `fetchpriority="high"`, all other images `loading="lazy"` and `decoding="async"`; keep width/height or `aspect-ratio` to avoid layout shift. Report the largest image bytes served on Hunter's at 1512 and at 393 before and after.
- Assets: only the chosen template's CSS/JS, no render-blocking JS (`defer` or footer), no unused CSS file (see section 4).
- Admin edit screen: the Location / Markets / Services lists are currently rendered once per template form (three copies in the DOM). Render each list once and share it, or render only the active template's copy and build the others on switch. Same for any other large duplicated markup. Lazy-initialize TinyMCE only for the active template. Admin assets load only on the project edit screen. No autoloaded options added by the plugin (check `wp_options` autoload for `ww_` rows). `flush_rewrite_rules()` never runs on a normal request.
- Importer: batch work stays chunked (5 projects per request), no unbounded loops, no long-running request over about 20 seconds.

## 8. Regression and acceptance
- Dry run still shows 56 rows, all matched or mapped, no images to fetch, no term changes; the import screen loads; the mapping file is read from its new location.
- Front end: the same 56-page scan as before passes (descriptions present, images load, alt text present, location and tags match the terms, no empty wrappers, one h1). Hunter's, Lyseth and Maine Air National Guard look unchanged except for the chip underline.
- Editor: pick a template, edit, save, switch templates, revert: all still work. No console errors on the front end or the edit screen. No PHP notices.
- Report plugin version, the deleted-files list, the security and speed findings, and the before/after numbers.
