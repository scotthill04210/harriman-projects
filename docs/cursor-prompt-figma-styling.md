# Cursor Prompt: style the three project templates from Figma (`harriman-projects`, prefix `ww`)

## 0. Scope and rules
- Style and finish the front end of the three templates. Do NOT change saved data, meta keys, the importer, the editor forms, or the switch panel (exceptions: section 4 adds one field and one related-projects/CTA output).
- Edit only: `assets/css/ww-project-base.css`, `ww-template-1.css`, `ww-template-2.css`, `ww-template-3.css`, small JS files (`ww-template-N.js` or one shared `ww-project.js`), and the templates in `templates/`. Markup changes are allowed ONLY where listed in section 3, and must keep the existing `ww-project__*` class names working.
- No libraries, no Tailwind. Plain CSS (custom properties) and plain JS. Own class names (`ww-`). Do not copy Figma-generated code; translate it.
- Header, top nav and footer belong to the theme. Do NOT rebuild them. Everything in Figma from "Top Nav" and "Footer" is out of scope.
- Phase 0 (read-only, report first): inspect how the theme wraps `the_content` on a single project (container width, padding, any `max-width`), because the Figma bands (header band, carousel, overview, quote) are full-bleed. Report the full-bleed technique you will use. Confirm the theme's Adobe/Typekit font kit includes Aktiv Grotesk weights 400, 500, 600 and 700; if 600 or 700 is missing, report it instead of faking bold.

## 0b. Reuse what the theme already ships (verified on staging and the live site)
The theme loads one compiled stylesheet (`dist/styles/main_*.css`, includes the old `wp-block-anchour-*` styles) and one script (`dist/scripts/main_*.js`, with Glide bundled inside; it is NOT a global). Both load on every page. Where this section conflicts with sections 3 and 4, THIS SECTION WINS. Keep project-only CSS in the plugin files; do not edit theme files.
1. **Slider (T1, T2): reuse the theme's carousel, no new slider JS.** The theme auto-mounts Glide on `.carousel-landscape` (type slider, gap 32, perView 1) and builds the bullets itself. Output the same markup the legacy pages used: `div.wp-block-anchour-carousel.carousel-landscape > div.glide__track > ul.glide__slides > li.wp-block-anchour-slide.glide__slide`, plus `div.controls-wrap` containing `div.glide__bullets` and `div.glide__arrows` (buttons `.glide__arrow--left` / `.glide__arrow--right`). Copy the exact structure and data attributes from a legacy page (the live site, e.g. https://www.harriman.com/projects/hunters-shop-n-save/, or the legacy block HTML for Hunter's #23505). The theme already styles the arrows and the bullets (3px charcoal underline bars, which is the Figma progress bar). Add only CSS: the full-bleed gray band, padding, and control-row spacing. Known difference from Figma: the theme config has no `peek`, so the next slide does not show at the right edge, and slides are full width like the live site today. Accept this.
2. **Related Projects: reuse the card markup and classes.** `div.wp-block-anchour-card-block > a.wp-block-anchour-card-block__link > div.wp-block-anchour-card-block__header > div.wp-block-anchour-card-block__image-wrapper > div.wp-block-anchour-card-block__image-scaler > img.wp-post-image`, then `div.wp-block-anchour-card-block__card-content > h4.has-heading-6-font-size + span.has-paragraph-sm`, inside a wrapper with the class `wp-block-anchour-featured-projects`. The hover zoom comes from the theme. Keep our own query (section 4, service rule); add CSS only for the 3-column / 2-column grid and the label bar if the theme card differs from Figma.
3. **CTA: render the existing reusable block.** `echo do_blocks( '<!-- wp:block {"ref":11273} /-->' );` ("Get in Touch": heading plus button, links to /contact/, editable by editors). If the block is missing, fall back to our own markup (section 4). Do NOT use reusable block 11270 (Newsletter Signup); it is not in the design.
4. **Buttons:** use `div.wp-block-buttons > div.wp-block-button (add .is-style-outline for outline) > a.wp-block-button__link` for "See our projects" and any other button. Do not restyle buttons.
5. **Breadcrumb:** the legacy layout used Breadcrumb NavXT (`bcn/breadcrumb-trail`). If `function_exists( 'bcn_display' )`, output it inside `nav.breadcrumbs` and style it to match the Figma breadcrumb (14px, Harriman › Work › title); otherwise use our own markup.
6. **Fonts (already checked):** the Typekit kit `vwi1unn` declares Aktiv Grotesk 400 and 500 only. Do not add font files. Use weights 400/500 where 600/700 are not available, set `font-synthesis: none`, and report which elements fall back so Scott can add 600/700 to the Adobe Fonts web project.
7. **No theme equivalent (write project-only CSS):** header band overlap, intro tags, overview band, the 3-up and 4-up image grids, content-block + single-image pairing, the T3 statistic card, the yellow quote card, and the mobile toggles. The theme is Tailwind-compiled, so do not depend on utility classes in markup (only classes already used elsewhere exist in the compiled CSS).

## 0c. Compatibility rules: do not break the theme or any other page (hard rules)
Verified baseline: plugin assets already load only on flagged projects (base CSS plus the chosen template's CSS/JS), and all 8 existing plugin selectors are scoped under `.ww-` / `body.ww-`. The theme chain is `div.wrap.overflow-hidden > div.content > main.main` (full width, no max-width) `> article.ww-project`. Our own `article.ww-project` currently has `max-width: 1400px` and 32px padding: remove that, let the bands run edge to edge, and cap each band's INNER content at 1512px, centered (no `100vw` tricks). The theme wrapper hides horizontal overflow; still make sure nothing causes a horizontal scrollbar at 393, 768, 1512 and 1920px.

**CSS**
1. Every selector starts with `body.ww-project` or `.ww-project` (or is a `ww-` class). No bare element selectors (`img`, `a`, `h1`, `p`, `ul`, `button`), and no `:root`, `html` or `body` rules except via `body.ww-project`. Custom properties are `--ww-*`, declared on `.ww-project`, not `:root`.
2. Never restyle theme classes outside our scope (`.glide*`, `.wp-block-*`, `.btn`, `.controls-wrap`, `.breadcrumbs`). If a reused theme component needs an adjustment, write a scoped override such as `.ww-project .glide__arrow`, nothing broader.
3. No `!important`, no `@layer` (unlayered theme CSS would beat layered rules), no resets, no `box-sizing` rule outside `.ww-project *`. Win by specificity (`.ww-project .ww-project__x`), not load order.
4. Do not touch fonts, `@font-face`, the viewport meta, or global `scroll-behavior` / `overflow`.
5. Do not define component classes that collide with the theme or Tailwind (`.card`, `.grid`, `.container`, `.btn`, `.hidden`).
6. CSS and JS never load in wp-admin or the block editor, and never on other post types, archives, or unflagged / reverted projects. Enqueue after the theme stylesheet (use its handle as a dependency if you can find it, otherwise priority 20) and version files with `filemtime`.

**JS**
1. One IIFE in strict mode, no globals (at most one `window.WWProjects` namespace), no jQuery dependency, no libraries. Run on `DOMContentLoaded`, scoped to `.ww-project`, and exit quietly if our elements are not on the page.
2. Never call or re-initialise Glide, and never touch `.glide` instances outside `.ww-project`. The theme mounts Glide on `.carousel-landscape` at load, so the slider markup must be in the initial HTML (never injected later), and its classes must not change after load.
3. Attach listeners to our own elements (or delegate from `.ww-project`). No listeners on `document` or `window` except one passive `resize` if truly needed. No `preventDefault` on links outside our scope.
4. Mobile toggles are progressive enhancement: without JS everything is visible; with JS add a `ww-js` class on `.ww-project` and collapse only below 900px. Use a real `button`, `aria-expanded`, and respect `prefers-reduced-motion`.
5. No inline `<script>` or `<style>` in templates, no `document.write`, no timers or polling, and no new analytics events (GTM and GA are already on the page; do not touch `dataLayer`).

**PHP**
1. The `the_content` filter stays guarded (single `project`, main query, in the loop, flag on, valid template, re-entrancy check). `do_blocks()` for the CTA runs inside that guard behind a static re-entrancy flag so it can never recurse.
2. The Related Projects query uses its own `WP_Query` instance (never `query_posts()`, never alter the main query) with `no_found_rows`, `posts_per_page` 3, limited fields, and `wp_reset_postdata()` afterwards.
3. Escape all output. New functions keep the `ww_` prefix. No new body classes beyond `ww-project` and `ww-template-N`.
4. Do not touch theme files, `resources/post-types/project.php`, ACF, anchour-blocks, or the reusable blocks.

**Regression pass (run it, report the results)**
- Screenshots and computed styles (header, nav, footer, fonts) before and after, at 393, 768, 1512 and 1920px, for: Home, `/projects/`, one market page, one page that uses Glide (featured posts, testimonial or carousel), a normal page, and a project reverted to the old layout (use a throwaway project; do not change real ones). No differences outside `.ww-project`.
- Console: no errors or warnings on those pages. Network: the plugin's CSS and JS are not requested outside flagged projects.
- Glide still mounts on other pages (arrows and bullets work), the mobile nav opens and closes on project pages, and GTM / GA still fire there.
- The block editor and the project edit screen look and behave the same (no front-end CSS in admin).
- Mobile Lighthouse on one project before and after: no worse layout shift (CLS). Images keep their dimensions or an `aspect-ratio`.
- No horizontal scrollbar at any width. Clear the host page cache after deploy.

## 1. Figma source (read-only reference)
File: https://www.figma.com/design/z5fBpkrrdM3659nYunkC2n/Harriman-Work-Rework (key `z5fBpkrrdM3659nYunkC2n`)

| Template | Desktop frame (1512 wide) | Mobile frame (393 wide) | Real project to compare |
|---|---|---|---|
| 1 | `377:1148` Work > Sample 1 | `589:3177` | Hunter's Shop 'n Save (#23505) |
| 2 | `461:343` Work > Sample 2 | `592:3950` | Lyseth Elementary School (#21441) |
| 3 | `491:843` Work > Sample 3 | `592:4339` | Maine Air National Guard Civil Engineering Building (find by title) |

If you have the Figma connector, use `get_design_context` on those nodes for exact values. If not, the values below are the source of truth.

## 2. Design tokens (put in `ww-project-base.css` as custom properties)
- Font: `aktiv-grotesk, sans-serif` (the theme already loads it). Weights: Regular 400, Medium 500, SemiBold 600, Bold 700.
- Colors: charcoal `#353c44` (text, dark band, solid buttons), warm gray `#f2f1ef` (bands, tags, label bars), yellow `#f8c046` (quote card, mobile toggles), white.
- Breakpoint: mobile layout below 900px, desktop layout from 900px up. Desktop type scales with `clamp()` between 900px and 1512px. Design is 1512 wide desktop with 80px outer padding; mobile is 393 wide with 24px gutters.
- Type scale (desktop / mobile):
  - Page title: 67px / 50px, SemiBold, line-height normal.
  - Intro subhead and statistic callout: 53px/69px / 35px/45px (intro subhead mobile is 27px/36px), SemiBold.
  - Section headings (Related Projects, overview title, content-block heading): 39px/45px / 27px/36px, SemiBold.
  - Location: 20px/69px / 18px. Body: 18px/29px / 16px/27px, Regular, 24px between paragraphs.
  - Overview label: 20px Medium (mobile 18px); overview value: 18px (mobile 16px/24px).
  - Quote: 53px/68px Medium / 27px/36px Medium; attribution 18px/29px Regular (mobile 16px/27px).
  - Tag chip: 11px Medium, color `rgba(53,60,68,.8)`, padding 4px 10px, background `#f2f1ef`.
  - Breadcrumb: 14px Medium, last item `rgba(53,60,68,.7)`.
  - Buttons: 16px Regular, height 55px, padding 15px 30px; solid = charcoal bg + white text; outline = 1px charcoal border.

## 3. Layout per section (desktop first, then mobile)

**Header band + hero** (all templates). Gray band (`#f2f1ef`) holds the breadcrumb ("Harriman › Work › {title}") and the title; padding 72px 80px 24px. The hero image sits inside 30px side margins, 1452px wide, about 718px tall, `object-fit: cover`, and overlaps the band: the band's bottom edge falls about two thirds down the image (471px of 718px). Mobile: band padding 56px 24px, hero 345px wide, 195px tall (16:9), band edge falls halfway down the image. Needs a wrapper `ww-project__header` around breadcrumb + title + hero. Breadcrumb links: Harriman = home, Work = `/projects/`.

**Intro** (all templates). Desktop: two columns, 80px padding, 30px gap. Left: subhead, then location, then tags (3-column grid of equal chips, 8px gap, chips 160px wide, min 145px). Right: description. Mobile: left block first (tags in a 2-column grid), then the description collapsed behind a full-width yellow toggle bar labelled "Project Introduction" (chevron icon, 34px tall). Tags are the market and service terms; link to term archives only if the archive exists. Needs wrapper `ww-project__intro` with `__intro-main` and `__intro-text`.

**Image Grid, Template 2** (before the overview). Desktop: CSS grid, 3 columns, 30px gap, padding 15px 30px 80px; first image spans 2 columns and 2 rows (rows 450px each), images 2 and 3 stack in column 3. All `object-fit: cover`. Mobile: image 1 full width (345 x 347), images 2 and 3 side by side (160.5 x 173), 24px gap.

**Image Grid, Template 3** (after the single image). Desktop: 3-column grid, 30px gap, rows 629px and 721px: image 1 = column 1 row 1; image 2 = columns 2-3 row 1; image 3 = columns 1-2 row 2; image 4 = column 3 row 2. Mobile: two rows (142px and 163px tall), widths 99/222 then 222/99, 24px gap. If fewer than 4 images, fall back to a simple 2-column grid.

**Project Overview** (all). Full-bleed band inside 30px margins, padding 50px, three columns with 90px gap.
- Desktop colors: background charcoal `#353c44`, white text. Mobile (as designed in Figma): background `#f2f1ef`, charcoal text. Use CSS variables so this can change in one line (the desktop and mobile Figma frames disagree; I will confirm with the designer).
- Column 1: heading "Project Overview" (two lines). Column 2 and 3 by template: T1: Client, Type | Size, Key Contacts. T2: Client, Type, Size (plus Program if filled) | Key Contacts, Recognition. T3: Client, Type, Size | Program Components, Key Contacts. Blank rows do not render. Key Contacts link is underlined. Mobile: one column, 50px side padding, rows stacked with a blank line between.
- Add wrapper columns (`ww-project__overview-title`, `ww-project__overview-col`) as needed. Keep Recognition out of T1/T3 unless filled.

**Image Slider** (T1, T2). Gray full-bleed band (`#f2f1ef`), padding-top 80px. Slides 1216 x 811, 30px gap, first slide starts at 80px from the left, the next slide peeks in at the right edge. Controls row below: left and right arrow buttons (35px), centered progress bars (50px wide, 15px gap, active bar darker), padded 150px from the edges, band bottom padding about 100px. Mobile: slides 393 x 280, 14px gap, full bleed, controls below (arrows and bars). Use CSS scroll-snap plus a small JS for arrows and the progress bars; keyboard accessible, `aria-label` on buttons, no autoplay.

**Statistic ("Subhead or Statistic")**.
- T1 and T2: full-width callout, padding 80px 150px 15px, 53px/69px SemiBold, left aligned. Mobile: padding 24px, 35px/45px, centered.
- T3: gray card (`#f2f1ef`, full bleed) with padding 80px: statistic text on the left (53px/69px SemiBold) and the Single Image on the right (about 660px tall, `object-fit: contain`), 30px gap. Mobile: the image sits half over a gray band (band 124px tall behind the top of the 345 x 345 image), then the statistic text below, 35px/45px.

**Content Block + Single Image** (T1, T2). One side-by-side card: padding 80px, 30px gap. Left: content block (first heading 39px/45px SemiBold, paragraphs 18px/29px, bullets allowed). Right: Single Image about 488px tall, `object-fit: contain`. If only one of the two exists, render it alone (full width for text). Mobile: the content block is collapsed behind a yellow "Read More" toggle under the statistic, and the single image follows as 345 x 195. Wrapper `ww-project__feature`. In T3 the Single Image is part of the statistic card above, so there is no separate content block.

**Quote**. Full-bleed yellow card inside 30px margins, padding 90px 150px (mobile 40px). The textarea may contain multiple lines: the first paragraph is the quote (53px/68px Medium), later lines are the attribution, separated by a short rule, 18px Regular. No added quotation marks.

**Related Projects + CTA** (new output, all templates, after the quote). See section 4.

**Hover and motion**: related-project images zoom to about 1.05 on hover with overflow hidden; respect `prefers-reduced-motion`.

## 3b. Empty-state rules (a blank form field must leave NO trace in the design)
Today every block is already skipped when its field is empty (verified on all 56 imported projects: no empty wrappers, headings or lists). The new wrappers in this design (header band, intro, overview columns, feature pair, T3 statistic card, slider band, Related Projects, CTA) must keep that guarantee. Build every wrapper inside its own `if` that checks the actual content, and put spacing on the section wrapper itself (never margins on siblings), so a skipped section leaves no gap.
- **Header band:** title is the only required piece. No hero image: the band renders without the overlap spacing. Breadcrumb is not a form field and always renders.
- **Intro:** subhead, location, tags and description are each conditional. If one of the two columns has nothing, render only the other (keep its normal column width). If both are empty, render no intro section. No tags: no tag grid and no spacing for it.
- **Overview:** row-level, then column-level, then whole-band. A blank row never renders; a column with no rows collapses; if no rows exist at all, the whole band (including the "Project Overview" heading) is omitted. Whitespace-only and placeholder values count as blank.
- **Slider:** no images: omit. Exactly one image: render it as a plain image, not a carousel (no arrows or bullets). Two or more: carousel.
- **Image grids:** T2 grid with 0 images: omit; with 1 or 2: simple full-width / two-column fallback; with 3: the Figma layout. T3 grid with 0: omit; with 1 to 3: simple fallback (full width / two columns); with 4: the Figma layout.
- **Statistic:** none: omit. T3 statistic card: statistic and Single Image are each conditional. Only the image: show the image in the gray card without a text column. Only the statistic: show the text on the gray card without an image column. Neither: omit the card. The mobile half-band behind the image renders only when the image exists.
- **Content Block + Single Image (T1, T2):** both present: side by side; only one: render it alone (text full width; image centered). Neither: omit. The mobile "Read More" toggle renders only when the content block has text.
- **Quote:** only if non-empty. The attribution rule renders only when there is more than one line.
- **Related Projects:** only when the query returns at least one project; no heading, button or wrapper otherwise. **CTA** is site-wide content (not a field) and always renders.
- **Never render:** editor prompt lines, placeholder text, whitespace-only values, or empty tags such as `<strong> </strong>`. If you find empty inline tags (2 imported content blocks currently contain an empty `<strong>`), strip them in the output filter; do not change saved data.
- **Test it with real projects (read-only) and one throwaway:** Central Provisions (no services, no grid, no single image), Rinck Advertising (no overview), Wellesley Middle School (no statistic), St. Mary's (hero only), and a throwaway project with only a title and nothing else. Report that each renders with no empty wrapper, heading, list, tag grid or gap. Then delete the throwaway.

## 4. New or changed output
1. **Related Projects.** Heading "Related Projects" and an outline button "See our projects" (to `/projects/`) on the right of the heading row (desktop) or below the grid (mobile). Query: published `project` posts, excluding the current one, that share at least one term in the `service` taxonomy. Rank by number of shared services (descending), then newest first. Take 3, only projects that have an image (featured image, fallback to `_ww_hero`). Card: image (16:9, 430 x 242 at desktop), label bar `#f2f1ef` with title SemiBold 18px/29px and a small arrow icon, and the location (`project-location` term name, fallback `_ww_location`) in 18px Regular, padding 14px 30px (mobile 10px, 16px/22px text). Cards link to the project. Desktop: 3 columns, 31px gap, min card width 328px. Mobile: 2 columns of 160.5px with 24px gap, third card wraps. Omit the whole block if there are no results. Filter: `ww_related_projects_args`. Use a short transient (about 12 hours) keyed by post ID, cleared on `save_post` of any project.
2. **CTA.** Centered heading "We'd love to work on a project together." (53px/69px SemiBold, mobile 35px/45px) and a solid button "Get in touch" linking to `home_url('/contact/')`. Filters: `ww_cta_text`, `ww_cta_url`.
3. **Program Components field.** Add `components` (multiline text, label "Program Components") to all three template forms and the field registry, shown in the overview where filled (column 3, above Key Contacts). Update the importer's label map so overview rows labelled "Program Components" or "Project Components" fill it. Do not change other fields.

## 5. Acceptance checklist
- Compare screenshots at 1512px and 393px against the Figma frames for the three real projects in section 1. Section order, spacing, type sizes, colors and image proportions match; differences you cannot match are listed.
- Unflagged projects and `/projects/` are unchanged. No changes to saved data.
- Section 3b (empty-state rules) is satisfied and tested on the listed projects and the throwaway title-only project. Blank blocks never render; no empty wrappers, headings, lists, tag grids or gaps. Placeholder text never renders.
- Keyboard and screen reader checks: toggles (`aria-expanded`), slider buttons (labels), focus visible. Images keep their alt (including the fallback).
- Section 0c (compatibility rules and regression pass) is fully satisfied, with results reported.
- No console errors. CSS and JS load only on flagged projects, and only the chosen template's files.
- Report: files changed, new filters (`ww_related_projects_args`, `ww_cta_text`, `ww_cta_url`), and the plugin version.
- Out of scope: header and footer, tablet-specific layouts (use the mobile layout below 900px), the project archive page.
