# Cursor Prompt: `harriman-projects` plugin (prefix `ww`)

## 0. Read first
Put these in the plugin's `/docs` folder and treat them as the source of truth for fields, labels, order, helper text and output rules:
- `template-1-spec.md`, `template-2-spec.md`, `template-3-spec.md`
- Slickplan HTML exports: `hunters-shop-n-save.html`, `central-provisions.html`, `west-end-yards.html`

Rules:
- Copy structure and behavior only. Do NOT copy any Slickplan CSS/JS, and do NOT reuse the old-site classes in the exports (`wp-block-*`, `has-*-font-size`, `wp-block-anchour-body-text-column`). Write our own code and class names.
- Everything is prefixed `ww`: functions `ww_`, constants `WW_`, namespace `WW\HarrimanProjects`, meta keys `_ww_`, CSS classes `ww-`, script/style handles `ww-`, REST namespace `ww/v1`, text domain `ww-projects`.
- This plugin does NOT register a post type. It extends the existing `project` post type (registered by the theme in `resources/post-types/project.php`, ~192 posts). Everything acts on the current post ID and only when `post_type === 'project'`. If `project` is not registered, show an admin notice and do nothing.
- Never break existing projects. A project only changes behavior when its flag `_ww_use_new_template` is set.

Site context: WordPress, Sage/Blade theme `harriman`, WPMU DEV hosting. Plugins path: `wp-content/plugins`. Taxonomies on `project`: `project-location`, `market`, `service`. Related plugins: `anchour-blocks`, `advanced-custom-fields-pro`, `post-types-order`.

## 1. Phase 0: inspect (read-only, report before building)
Do not change anything. Report findings back as a short summary:
1. Read the theme's `single.blade.php`, `index.blade.php`, `layouts/`, `partials/` and `resources/post-types/project.php`. Say how single projects and `/projects/` render, and whether the theme hooks `template_include` (and at what priority) so ours can override it for flagged posts only.
2. Pick one real project: `wp post get <ID> --field=post_content` and `wp post meta list <ID>`. Check the 9 groups in `resources/acf-json`. State exactly what "old format" means: block layout in `post_content`, ACF meta, `acf/testimonials` block data, or a mix. List the meta keys and block names involved.
3. Find the post type slug used for people/leadership pages (the Key Contacts links in the exports use `type="leadership"`, example post ID 23524). Report the slug and how to query it.
4. Check the existing terms in the `market` and `service` taxonomies and compare with the lists in the specs (9 markets, 6 services). Report missing or extra terms. Do not create terms.
Wait for confirmation before Phase 2 if anything contradicts this prompt.

## 2. Plugin structure
```
harriman-projects/
  harriman-projects.php          (header, constants, bootstrap)
  includes/
    class-ww-plugin.php
    class-ww-fields.php          (field registry, per-template field lists)
    class-ww-meta-boxes.php      (picker + one form per template)
    class-ww-save.php            (sanitize + save)
    class-ww-switch.php          (flag, REST, legacy archive/delete)
    class-ww-render.php          (template_include, body class, assets)
    class-ww-rest.php
  templates/
    single-project.php           (loader, picks template part)
    parts/template-1.php  template-2.php  template-3.php
    parts/overview.php  image.php  gallery.php
  assets/
    css/ww-admin.css  ww-project-base.css  ww-template-1.css  ww-template-2.css  ww-template-3.css
    js/ww-admin.js  ww-switch-panel.js  ww-template-1.js ...
    img/template-1.svg template-2.svg template-3.svg   (placeholders)
  docs/
```
No build step. Plain PHP, plain JS (use `wp.element.createElement` for the editor panel), vanilla CSS.

## 3. The switch (Gutenberg document sidebar on `project`)
- Register post meta `_ww_use_new_template` (boolean, single, `show_in_rest`, `auth_callback` requires `edit_others_posts`).
- Document sidebar panel (use `PluginDocumentSettingPanel`) only on `project`, only for users with `edit_others_posts` (administrators and editors).
  - "Switch to new template" turns the flag on for THIS post.
  - "Revert to current template" turns it off. Must always be available while flagged. Toggle goes through the editor's normal meta state so it can be undone before saving.
- Safety: switching never rewrites `post_content`. On first switch, snapshot the current `post_content` into `_ww_legacy_content` (non-destructive copy) so the old layout is archived and recoverable.
- When flagged: show a notice "Legacy layout archived. Content is unchanged." Hide the block editor canvas by default (class on `body`, with a "Show legacy content" toggle). The template picker and form (section 4) appear in its place.
- "Delete old format data": a separate button in the same panel, hidden unless flagged. Opens a confirmation modal where the user must type `DELETE`. Calls `POST ww/v1/projects/<id>/delete-legacy` (nonce + `edit_others_posts` + must be flagged). It removes `_ww_legacy_content` and blanks `post_content` for that post. Phase 0 found the old format is the block layout in `post_content`, not ACF meta. It never touches the `market`, `service` or `project-location` terms, the featured image, `testimonial` posts, or reusable blocks 11273 and 11270, because the archive and other pages use them. Revert must remain possible until this is used. After deletion, revert is disabled with a message.

## 4. Template picker and forms
- A "Project Template" meta box at the top of the `project` edit screen, shown only when flagged. It shows three image cards (radio inputs, accessible, keyboard focusable) for Template 1, 2, 3. Placeholder images come from `assets/img/`. Card images are read through the filter `ww_template_card_images` (array of three URLs) so real images can be swapped in later without code changes.
- Selected value saved as `_ww_template` (`1|2|3`). Until chosen, no form is shown, only the picker.
- Choosing a card shows that template's form and hides the others (no reload). Only the chosen template's fields are saved.
- Each form replicates its spec: same blocks, labels, order, helper text. Each block has a collapsible header (use `<details>`/`<summary>` or equivalent), type icon, and label. One column. Helper text appears under the block. Do not show the Slickplan red prompt lines as content; for Markets + Services show the two prompt lines as normal field labels.
- Blocks per template: follow the table in each spec exactly (T1: 12 blocks, T2: 13, T3: 11).
- "Headline / Project Name" is the post title (the existing title field). Do not add a duplicate field.
- Only required field: the post title. Everything else optional.

### Field registry (one registry; templates list which keys they use)
Flat meta keys `_ww_<key>`. Shared keys carry over automatically when switching templates; template-only keys stay saved but unused.

| key | kind | used by |
|---|---|---|
| hero | image {id, alt, caption} | 1,2,3 |
| subhead | text | 1,2,3 |
| location | text | 1,2,3 |
| description | rich text | 1,2,3 |
| markets, services | taxonomy terms (checkboxes) | 1,2,3 |
| client | multiline text | 1,2,3 |
| type | text | 1,2,3 |
| size | multiline text | 1,3 |
| program | text | 2 |
| key_contacts | repeater of {person_id or plain text} | 1,2,3 |
| recognition | multiline text | 1,2,3 |
| stat | single-line text | 1,2,3 |
| content_block | rich text | 1,2 |
| single_image | image | 1 (3:2), 2 (3:2), 3 (1:1) |
| slider | image repeater (guidance 3-5) | 1,2 |
| grid | image repeater (T2: 3 square; T3: 4, two 3:2 and two 2:3) | 2,3 |
| quote | textarea | 1,2,3 |

Switching templates after creation: keep all saved data. The new template displays only the keys it uses. Never delete data on switch. Show a short notice listing which saved fields the new template does not use.

### Field behavior
- Markets + Services: write to the existing `market` and `service` taxonomies (`wp_set_object_terms`). Show checkbox lists built from the live terms, using the site's own term names (Phase 0: four market names differ from Slickplan, and services include an extra "Landscape Architecture"). Markets allow more than one. Same lists for all templates. Do not create or rename terms.
- Key Contacts: label is "Key Contacts" in all templates. Each row is either a link to a person (searchable select of the people post type found in Phase 0) or plain text. Add/remove rows.
- Overview labels per template: T1: Client, Type, Size, Key Contacts, Recognition. T2: Client, Type, Program, Key Contacts, Recognition. T3: Client, Type, Size, Key Contacts, Recognition. Blank rows do not render.
- Rich text fields use `wp_editor` and allow bullets everywhere, even where the helper text says to avoid them. Do not enforce word limits.
- Subhead or Statistic: plain text. Strip tags and Word markup (`MsoNormal`, `<o:p>`) on save with `wp_strip_all_tags` and a cleanup pass.
- Images: use the media library only (`wp.media`). Each image stores attachment ID plus its own caption and alt override. Show caption/alt as a tab pair like Slickplan. Fall back to the attachment alt text when the override is blank. Aspect ratios, file size and pixel sizes are shown as helper text only, not enforced. Skip "From URL" in v1.
- Repeaters (own JS, no libraries beyond what core provides): add, remove, drag-to-reorder (jQuery UI sortable is fine). Image repeaters have "Add another". Show the 3-5 guidance for the slider and fixed counts for grids as helper text only (no enforcement, but show a soft warning if the count differs).
- Capability: all UI and saves require `edit_others_posts` for flagged projects. Nonces on all saves, `sanitize_*` on input, `esc_*` on output.

## 5. Front-end output (unstyled, stable markup)
- Do NOT use `template_include`. Phase 0 found the theme renders Blade inside its own `template_include` callback at `PHP_INT_MAX`, which swallows any plugin filter. Instead, filter `the_content` for the single `project` (guard: `is_singular('project')`, `is_main_query()`, `in_the_loop()`, flag true, valid `_ww_template`, and a re-entrancy check) and return the output of our template part from `templates/`. The theme's `content-single-project.blade.php` is only `the_content()`, so the header, footer and layout stay intact. Unflagged projects and the `/projects/` archive are untouched. First verify that `single.blade.php` and `layouts/app.blade.php` print no title or hero of their own; if they do, tell me before building.
- Add body classes `ww-project` and `ww-template-1|2|3` via `body_class`. Enqueue `ww-project-base.css` plus only the chosen template's `ww-template-N.css`/`.js`, and only on flagged projects.
- Output each block in spec order, using semantic HTML and our own BEM-style classes (`ww-project__hero`, `ww-project__overview-col`, `ww-project__gallery-item`, etc.). Skip any block with no content (no empty wrappers, no headings). Never output the editor prompt lines or placeholder text.
- Heading levels:
  - T1: title h1, subhead h3, location h4, statistic h3.
  - T2: title h1, subhead h2, location h4, statistic h3 (confirm in Figma).
  - T3: title h1, subhead h2, location h4, statistic h3.
  - Keep levels in one config array so they can change later.
- Images: output `<figure>` with `<img>` (with alt) and `<figcaption>` when a caption exists. Hero first.
- Overview: two column containers (column 1: Client + Type; column 2: Size or Program + Key Contacts), with Recognition below. Labels as `<h6>` in T2 and T3 and `<strong>` in T1. Multiline values use `<br>`. Key Contacts links to the person page when set.
- Markets + Services: output the selected terms as a list, linking to term archives only if the archive exists.
- Quote: output only if not empty, wrapped in `<blockquote>`. Do not add quotation marks; the editor includes them.
- Block order matches each spec, with the T2 Image Grid before the overview and the Slider after it; T3 Single Image then Image Grid.
- No styling beyond a minimal base (spacing, image max-width). Figma styling comes later by editing the `ww-template-N.css` files only.

## 6. Acceptance checklist
- Unflagged projects render exactly as today. `/projects/` unchanged.
- Flag on/off works per post ID only; other posts unaffected.
- Revert works, including after saving. Nothing deletes until the confirmed delete action.
- Picker shows three cards; choosing one shows only that form; all three forms match their specs.
- Switching T1 to T3 to T2 keeps data; shared keys carry over; unused fields remain stored.
- Blank blocks and blank overview rows do not render; prompt/placeholder text never renders.
- Editors can use it; users without `edit_others_posts` cannot (REST and UI).
- No PHP notices; output escaped; no code or class names copied from Slickplan or the exports.
- Deliver a short README listing hooks and filters (`ww_template_card_images`, heading levels config).

## 7. Out of scope for now
Figma styling, real picker images, bulk conversion of existing projects, "From URL" images.
