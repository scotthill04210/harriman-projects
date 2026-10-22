# Template 1: "Project Template 1" (Slickplan page "Harriman 1.0")

Source: warpweft.slickplan.com, project kajf00rq, page ixdipjogw3c. Sample content: "Hunter's Shop 'n Save".
Layout: one column, 12 stacked blocks in the order below. Each block has a collapsible header (type icon + label).
Image blocks show a thumbnail strip, a Caption / Alt text tab pair per image, and an "Add another" link.

| # | Label | Type | Repeatable | Input | Helper text (shown under block) |
|---|-------|------|-----------|-------|----------------------------------|
| 1 | Headline / Project Name | Text | no | Single-line, large heading (sample: "Hunter's Shop 'n Save") | none |
| 2 | Hero Image | Image | no (1/∞ shown, intended single) | Image + Caption (optional) + Alt text | Image should be 16:9 proportions and less than 1 MB. Ideal 2000 x 1125 px at 72dpi, JPG. |
| 3 | Subhead | Text | no | Single-line heading (sample: "A new beginning for a veteran retailer") | none |
| 4 | Location | Text | no | Single-line, bold (sample: "Wolfeboro, NH") | none |
| 5 | Project Description | Text (rich) | no | WYSIWYG, 2-3 paragraphs | Approximately 100 words in 2-3 paragraphs. We recommend not including bullets in this description. |
| 6 | Markets + Services | Text (selection) | no | Two selection lists (see below) | none |
| 7 | Image Slider | Image | YES (gallery, 3-5) | Per image: image + Caption (optional) + Alt text; "Add another" | Upload 3-5 images. Image should be 3:2 proportions and less than 1 MB. Ideal 1000 x 667 px at 72dpi, JPG. |
| 8 | Project Overview | Text (labeled rows) | no | Five labeled single-line rows (see below) | Categories left blank will not show on the website. |
| 9 | Subhead or Statistic | Text | no | Single-line heading (sample: "Economical & Sustainable") | For example: "Hunter's Shop 'n Save owners credit the integrated mechanical system with reducing their electric bills by 66%." Recommend no more than 20 words. |
| 10 | Content Block | Text (rich) | no | WYSIWYG: paragraph(s) plus optional bullet list (sample uses 1 paragraph + 5 bullets) | Approximately 100 words in 2-3 paragraphs with a short headline. We recommend not including bullets in this description. |
| 11 | Single Image | Image | no | Image + Caption (optional) + Alt text | Upload one image. 3:2 proportions, under 1 MB. Ideal 1000 x 667 px at 72dpi, JPG. |
| 12 | Quote/Testimonial | Text | no | Textarea | Include quote/testimonial here. All quotes should have quotation marks included. If left blank, a quote will not appear on the website. |

## Block 6: Markets + Services
Prompt 1: "Please highlight the market this project is in:" Single or multi choice (Slickplan uses highlighting; sample highlights one).
Options: Commercial + Mixed Use, Community + Cultural, Federal, State + Municipal, Healthcare, Higher Education, Pre-K – 12 Education, Science + Technology, Workplace.

Prompt 2: "Please highlight the services this project includes:" Multi choice.
Options: Architecture, Civil Engineering, Interior Design, Mechanical Engineering, Structural Engineering, Electrical Engineering.
(Sample highlights Architecture, Interior Design, Mechanical Engineering, Structural Engineering, Electrical Engineering.)

## Block 8: Project Overview
Intro line: "Please add all the pertinent project information:"
Rows, in order (label: sample value):
1. Client: Hunter's Shop 'n Save
2. Type: New Construction
3. Size: 16,000 SF
4. Key Contact: Mark Lee and Zach Smith
5. Recognition: (blank in sample)

## Notes / open questions for the build
- Slickplan has no required-field markers visible; treat all as optional except Headline / Project Name.
- Slickplan "highlight" in block 6 should become real checkbox/radio inputs in WP. Markets and Services map to the existing `market` and `service` taxonomies on the Harriman site; confirm whether to write to taxonomies or store as meta.
- Overview label is "Key Contact" (singular) here; the live site's old layout says "Key Contacts".
- Block 10 hint says no bullets, but the sample includes a bullet list. Allow bullets in the editor.
- Block 7 shows "4/∞": no hard maximum in the UI; 3-5 is guidance only. Decide whether to enforce.
## Exported HTML findings (hunters-shop-n-save.html)
- The export is flat: no wrappers, no classes, no block labels. Only semantic tags in block order. Front-end markup must come from the Figma, not from this export.
- Heading levels used: Headline = h1, Subhead = h3, Location = h4, Subhead or Statistic = h3.
- Hero, slider and single image are bare `<img>` tags: no caption, no alt in the export. Slider sample has 4 images. Our output should emit alt and caption.
- Project Description and Content Block are plain `<p>` (and one `<ul>` in Content Block).
- Markets + Services: selected items are marked with a yellow background span; unselected items are plain `<li>`. In WP, selected = checked taxonomy term.
- Project Overview rows are `<div><strong>Label:</strong> value</div>`. Recognition is empty (`&nbsp;`), confirming blank rows are dropped on output.
- The red bold prompt lines ("Please highlight...", "Please add all the pertinent project information:") are editor instructions, not site content. Do not render them on the front end.
- Quote/Testimonial in the export contains the placeholder instruction text, not a real quote. Treat as empty (nothing renders).
- Block order in the export matches the form order exactly.
