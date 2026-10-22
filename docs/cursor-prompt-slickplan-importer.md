# Cursor Prompt: one-time Slickplan project importer (`harriman-projects`, prefix `ww`)

## Goal
Read the Slickplan XML export and fill the new `_ww_*` project fields on the matching existing `project` posts. Dry run first, then apply only after I approve. The plugin must already be working (single Update saves; Phase 0 and the `the_content` front end are done).

## Inputs (put in the plugin's `/docs`)
- `harriman 1.0.xml` (Slickplan export)
- `slickplan-projects-manifest.csv` (expected pages, templates and known data problems; use it to cross-check the dry run)

## Hard limits
- Never change `post_content`, non-`_ww_` meta, the featured image, or `project-location` terms on existing posts. Never delete posts. The only posts the importer may create are draft projects for unmatched pages (see "Unmatched pages become drafts"). Never create or rename terms. Never touch ACF, testimonials, reusable blocks.
- Dry run is the default. Nothing is written until I click Apply, with a nonce and `manage_options`.
- Refuse to apply unless a project backup file made in the last 24 hours exists (the existing Tools > Project backup).
- Before overwriting any `_ww_*` key on a post, save the previous values as JSON in `_ww_import_prev` so an import can be reverted per post.

## Which XML pages are projects
- A project is a `<cell>` with `<level>3</level>` that has `<contents>` and a `<template>` of `ntgqklyujqem` (Template 1), `jta9kgzihmry0ba0` (Template 2) or `b9o8qbm6` (Template 3).
- Ignore everything else: the home page (level `home`), the `Markets` and `Services` cells (level 1), the nine market cells (level 2), and level-3 cells with no contents (4 stubs, listed as `skip-empty` in the manifest).
- Expected result: 56 projects (T1: 4, T2: 31, T3: 21). Report any difference from the manifest.
- Block ids differ by template (for example `z7h8nvqar` is the slider in T1 but the grid in T2 and T3). Match blocks by their `<options><label>`, never by id.
- The sitemap parent of each cell is its market (`Commercial + Mixed Use`, `Federal`, `State + Municipal`, `Workplace`, `PreK-12 Education`, `Higher Education`, `Science + Research`, `Healthcare`, `Community + Civic`).

## Decoding and cleaning
- After XML parsing, content strings still contain entities (`&lt;p&gt;`, `&rsquo;`). Run `html_entity_decode` once, then parse as HTML. A few blocks use CDATA; the same single decode applies.
- Treat as empty (exact or starts-with): `Include quote/testimonial here`, `Add short headline and content here.`, `Single sentence statistic here.`, `Single sentence subhead here.`, `Project description copy goes here.`, `Project Name`, `City, State`, and any value matching `–– … ––` (en dashes).
- Strip Word markup (`MsoNormal`, `<o:p>`, `mso-*` styles, `frg-*`, `data-bm`), `class`, `style`, `id`, and wrapper `div`s. Allowed in rich text: `p, br, em, strong, a[href], ul, ol, li, h3`. Convert `h2` and `h4` to `h3` inside Content Block. Drop empty paragraphs, stray `<h3>.</h3>`, spacer divs, `<kbd>`.
- Run `wp_kses` after cleaning. Keep real `<ul>` lists even where the helper text says to avoid bullets.

## Field extraction (per block label)
- **Headline / Project Name:** text of the first h1–h6. Some headlines are wrapped in old `page-header` divs; take the inner heading text. Used for the title decision below.
- **Hero Image / Single Image / Image Slider / Image Grid:** each `<contentelement>` is an image. `type=library` has `url`, `file_name`; `type=url` has only `url` (West End Yards hero). Keep order.
- **Subhead:** text of the inner heading (some are wrapped in old page-header HTML with an `<img>`; ignore the image). Plain text.
- **Location:** strip tags and whitespace. If two places appear (Geiger), join with ` / ` and list it in the report.
- **Project Description / Content Block:** cleaned rich text as above.
- **Subhead or Statistic:** `wp_strip_all_tags`, collapse whitespace; if it was a list, join items with a space.
- **Quote/Testimonial:** convert to plain text, `<br>` and `<p>` to newlines, trim. Do not add quotation marks. List projects whose quote lacks quotation marks in the report.
- **Markets + Services:** a list item is selected if highlighted text (a span with `background-color`) covers at least 60% of the item's label text. This handles split highlights (`Interior Desig` + `n`) and ignores a highlighted trailing note. Aliases (default, filter `ww_term_aliases`): `Commercial + Mixed Use`→`Commercial / Mixed-Use`, `Community + Cultural`→`Community Civic + Cultural`, `Pre-K – 12 Education`→`Pre-K and K-12 Education`, `Science + Technology`→`Science + Research`; sitemap names `Community + Civic`→`Community Civic + Cultural`, `PreK-12 Education`→`Pre-K and K-12 Education`. If an item contains `listed as landscape architecture`, select `Landscape Architecture` (Metromark) and do not select the item itself. Write terms only when the XML selection is non-empty (replace that taxonomy for that post); when no market is highlighted, use the sitemap market. Report any market that differs from the sitemap parent.
- **Project Overview:** the content mixes several layouts. Normalize into rows of (label, value):
  - `<h6>Label</h6>` followed by `<p>` values until the next label;
  - `<strong>Label:</strong> value` or a bold label line followed by `<p>` lines;
  - ignore the `Project Overview` h3 and empty h6/p.
  Label map: `Client`→client; `Type` and `Project Type`→type; `Size`→size; `Program`→program; `Key Contact` and `Key Contacts`→key_contacts; `Recognition`→recognition. Multi-line values: `<br>` and separate `<p>` become newlines. Rows with placeholder values are empty. Labels not in the map (for example `Project Components`) are not imported; list them in the report.
- **Key Contacts:** for each `<a>`, resolve a `leadership` post: first by the `id` attribute if that post exists, then by the last path segment of the href as `post_name`, then by matching first and last name against `leadership` titles (handle odd slugs such as `leadershipleadershipwilliam-h-g-gatchell-aia-ncarb`, `mark-lee`, `mark-d-lee-aia-leed-ap`, and `Wil` vs `Will`). Unresolved links and plain-text names become plain-text rows. Remove duplicates, keep order.

## Matching XML pages to WordPress projects
1. By `_ww_slickplan_page_id` (the cell id) if already set.
2. Exact match of normalized sitemap title to `post_title`.
3. Normalized slug match.
4. Fuzzy match (token similarity at least 0.85, or edit distance at most 2) on sitemap title, then on the headline. Several sitemap titles have typos (`Sargnet`, `Yarmoth`, `Schoool`, `Souther`, `Vetical`, `COlby`, `FIre`, `INstitute`); fuzzy matching must catch them.
5. A mapping CSV (`ww_project_import_map.csv`: cell_id, post_id) that I can edit overrides all matches.
Before fuzzy matching, expand abbreviations in both the XML title and the project title: UNH = University of New Hampshire, USM = University of Southern Maine, NMCC = Northern Maine Community College, CMCC = Central Maine Community College, MMA = Maine Maritime Academy, EMCC = Eastern Maine Community College. Add a filter `ww_title_abbreviations` so more can be added.

Review states in the dry run: exact-title and slug matches are pre-checked for import. Fuzzy matches are unchecked and shown as "needs review", with the candidate post title next to the XML title. Rows with no match show the three closest candidates so I can pick one into the mapping file. Never write to a post that has not been explicitly checked.

## Unmatched pages become drafts
- A page with no match (after my mapping file is applied) is created as a NEW `project` post with `post_status = draft` (never published), `post_author` = the current user, `post_title` = the Slickplan headline (cleaned; fall back to the sitemap title if the headline is empty or a placeholder), an auto-generated slug, and empty `post_content`.
- Fill it exactly like a matched post: `_ww_template`, the `_ww_*` fields, `_ww_slickplan_page_id`, and markets/services terms (sitemap market as the fallback). On these new posts only, also set the featured image to the hero image.
- Mark it with `_ww_import_created` = 1. Never create two posts for one cell: a re-run matches by `_ww_slickplan_page_id` first.
- In the dry run, list these under "Will create as draft", unchecked by default; I check the ones I want. Never create posts from the four empty stub pages.
- Revert for a created post moves it to the trash only if it is still a draft; otherwise leave it and report.

## Titles
- Default: if the Slickplan headline is not empty or a placeholder, set `post_title` to it (cleaned). Keep `post_name` (slug) and permalink unchanged. Show every title change (old → new) in the dry run and apply it only for rows I approve. Otherwise keep the existing title (for example Rinck).

## Images
- For each image, first look for an existing attachment by filename (case-insensitive; ignore `-scaled`, `-NNNxNNN`, `-eNNN` suffixes) via `_wp_attached_file`. If none, download the Slickplan `url` with `download_url` and `media_handle_sideload`. For `type=url`, allow only `harriman.com` hosts.
- Anonymous fetch of a Slickplan download link returned image data, but handle failures: never drop an image silently; list failures in the report.
- Store `{id, alt: '', caption: ''}` per image. The XML has no alt text or captions; leave the overrides blank so the attachment alt is the fallback. Report the count of imported images with no alt text.
- Count mismatches against guidance (slider 3–5, T2 grid 3, T3 grid 4) are warnings only; import what exists.

## Writing
- Use the plugin's save/sanitize functions with an array (not `$_POST`). Set `_ww_template` from the XML template, the `_ww_<key>` fields, and `_ww_slickplan_page_id`. Do not write fields a template does not use except `size` for T2 (see below).
- Snapshot `post_content` into `_ww_legacy_content` once if it does not exist.
- Do not write `_ww_use_new_template` (the default is true) unless the post has an explicit false; report such posts and leave them alone.

## Plugin changes needed first
- Add `size` to Template 2's form and field list (almost every T2 page in the XML uses Size; only Central Provisions uses Program). Keep Program. On the front end, T2 overview column 2 shows Program and/or Size when filled; blank rows still do not render. Mark this for Figma confirmation.
- Register `_ww_slickplan_page_id` and `_ww_import_prev` (neither shown in REST).
- Add the `ww_term_aliases` filter (defaults above).
- Expose the label→key map per template in the field registry.

## UI
- Tools > Slickplan import (`manage_options`): choose the XML in `/docs`, run Dry run, show a table (cell id, title, template, matched post + confidence, title change, markets/services, image counts, warnings, status). Checkboxes per row; **Import selected** applies in batches of 5 via AJAX with a progress bar; **Revert import** per row restores `_ww_import_prev`.
- Also offer Download report (CSV) for the dry run and for the apply log.

## Acceptance
- Dry run recognizes exactly 56 projects (4 / 31 / 21) and 4 empty stubs; matches the manifest.
- Only checked rows are written. The only posts created are draft projects for checked unmatched pages (no duplicates on re-run; none from empty stubs). On existing posts, non-`_ww_` meta and `post_content` are unchanged (re-run the Step 3 meta row counts).
- Spot-check after applying five posts: Hunter's Shop 'n Save (T1, six services, bullet list), West End Yards (T3, URL hero, multi-line Client and Size, Zach Smith link), Central Provisions (T2, Program, empty grid and single image), Geiger (T3, two locations, placeholder Size), Metromark (T3, Landscape Architecture). Front end renders without prompt text or placeholders.
- Re-running the importer on the same posts is safe and idempotent; Revert restores previous values.
