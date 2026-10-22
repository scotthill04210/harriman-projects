# Template 2: "Project Template 2" (Slickplan page "Harriman 1.0", sample: "Central Provisions")

Source: warpweft.slickplan.com, project kajf00rq, page svgg4adc152u9a3bg97. Sample content: "Central Provisions" (Portland, ME).
Layout: one column, 13 stacked blocks in the order below. Each block has a collapsible header (type icon + label).
Image blocks show a thumbnail strip, a Caption / Alt text tab pair per image, and an "Add another" link. An empty image block instead shows "Drag & drop or select an option below to add a image" with three sources: Upload, From URL, Library.

| # | Label | Type | Repeatable | Input | Helper text (shown under block) |
|---|-------|------|-----------|-------|----------------------------------|
| 1 | Headline / Project Name | Text | no | Single-line, large heading (sample: "Central Provisions") | none |
| 2 | Hero Image | Image | no (1/∞ shown, intended single) | Image + Caption (optional) + Alt text | Image should be 16:9 proportions and less than 1 MB. Ideal 2000 x 1125 px at 72dpi, JPG. |
| 3 | Subhead | Text | no | Single-line heading (sample: "Rustic meets modern in a signature Portland eatery") | none |
| 4 | Location | Text | no | Single-line (sample: "Portland, ME") | none |
| 5 | Project Description | Text (rich) | no | WYSIWYG, 2-3 paragraphs (sample has 3, the last two long) | Approximately 100 words in 2-3 paragraphs. We recommend not including bullets in this description. |
| 6 | Markets + Services | Text (selection) | no | Two selection lists (see below) | none |
| 7 | Image Grid | Image | YES (fixed 3, square) | Per image: image + Caption (optional) + Alt text; "Add another". EMPTY in the sample (0/∞) | Upload 3 images. Image should be 1:1 (square) proportions and less than 1 MB. Ideal 1000 x 1000 px at 72dpi, JPG. |
| 8 | Project Overview | Text (labeled rows) | no | Five labeled rows (see below) | Categories left blank will not show on the website. |
| 9 | Image Slider | Image | YES (gallery, 3-5) | Per image: image + Caption (optional) + Alt text; "Add another". Sample has 3 images | Upload 3-5 images. Image should be 3:2 proportions and less than 1 MB. Ideal 1000 x 667 px at 72dpi, JPG. |
| 10 | Subhead or Statistic | Text | no | Single-line (sample: "An economical and space-efficient heat pump system heats and cools the premises") | For example: "Hunter's Shop 'n Save owners credit the integrated mechanical system with reducing their electric bills by 66%." Recommend no more than 20 words. |
| 11 | Content Block | Text (rich) | no | WYSIWYG. EMPTY in the sample (placeholder "Add short headline and content here.") | Approximately 100 words in 2-3 paragraphs with a short headline. We recommend not including bullets in this description. |
| 12 | Single Image | Image | no | Image + Caption (optional) + Alt text. EMPTY in the sample | Upload one image. 3:2 proportions, under 1 MB. Ideal 1000 x 667 px at 72dpi, JPG. |
| 13 | Quote/Testimonial | Text | no | Textarea | Include quote/testimonial here. All quotes should have quotation marks included. If left blank, a quote will not appear on the website. |

## Block 6: Markets + Services
Prompt 1: "Please highlight the market this project is in:" Options (same 9 as Templates 1 and 3): Commercial + Mixed Use, Community + Cultural, Federal, State + Municipal, Healthcare, Higher Education, Pre-K – 12 Education, Science + Technology, Workplace. Sample highlights Commercial + Mixed Use.
Prompt 2: "Please highlight the services this project includes:" Options in this sample: Architecture, Civil Engineering, Interior Design, Mechanical Engineering, Structural Engineering (5 only, same as Template 3; Template 1 also lists Electrical Engineering). Sample highlights NONE (no services selected).

## Block 8: Project Overview
Intro line: "Please add all the pertinent project information:"
Rows, in order (label: sample value):
1. Client: Central Provisions
2. Type: Renovation
3. Program: Restaurant and Bar
4. Key Contacts: Zach Smith, AIA (linked to the person's page on harriman.com)
5. Recognition: placeholder "–– List any awards received ––" (treat as empty)
Note: the third row is "Program" here. Templates 1 and 3 use "Size" in that position.

## Differences from Templates 1 and 3
- 13 blocks, the most of the three.
- Has BOTH an Image Grid (block 7, before the overview) and an Image Slider (block 9, after it). Template 1 has a slider only; Template 3 has a grid only.
- Image Grid here is 3 square (1:1) images. Template 3's grid is 4 mixed-orientation images.
- Has a Content Block and a Single Image (3:2), like Template 1.
- Overview third row is Program, not Size. Key Contacts is plural and links to a people page (as in Template 3).
- Subhead exports as h2 (same as Template 3).

## Exported HTML findings (central-provisions.html)
- Same old-site classes as Template 3's export (wp-block-heading, wp-block-paragraph, has-heading-1-alt-font-size, wp-block-anchour-body-text-column, has-paragraph-md-font-size) from pasted live-site content. Not Slickplan structure; do not copy.
- Heading levels: Headline h1, Subhead h2, Location h4, Overview labels h6.
- The export omits the empty blocks entirely: no Image Grid, no Single Image, and the empty Content Block exports as its placeholder text. Blank blocks should simply not render.
- Subhead or Statistic exports as a `<p class="MsoNormal">` with a stray `<o:p>` tag, i.e. it was pasted from Word. Heading level for this block (h3 in Templates 1 and 3) must come from the Figma. Strip Word markup on save.
- Overview is two columns: column 1 = Client + Type, column 2 = Program + Key Contacts. Recognition sits below. Confirm the two-column layout in the Figma.
- Key Contacts links to /about/people/zach-smith/ (type="leadership", id=23524), same as Template 3.
- Slider images are 3 bare `<img>` tags, no alt or caption. Image order in the export: hero, then the 3 slider images.
- Project Description is 3 `<p>` tags; the last two are well over the "about 100 words" guidance, so do not enforce a word limit.
- The red bold prompt lines are editor instructions, not site content. Do not render them.
- Quote/Testimonial in the export is placeholder instruction text; treat as empty.
- Block order in the export matches the form order, minus the empty blocks.
