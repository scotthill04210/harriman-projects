# Template 3: "Project Template 3" (Slickplan page "Harriman 1.0", sample: "West End Yards")

Source: warpweft.slickplan.com, project kajf00rq, page svgw5tne2avxpsnthyu. Sample content: "West End Yards" (Portsmouth, NH).
Layout: one column, 11 stacked blocks in the order below. Each block has a collapsible header (type icon + label).
Image blocks show a thumbnail strip, a Caption / Alt text tab pair per image, and an "Add another" link. The Hero Image block also shows an "Image URL" tab (sample hero is a full URL to an existing harriman.com upload).

| # | Label | Type | Repeatable | Input | Helper text (shown under block) |
|---|-------|------|-----------|-------|----------------------------------|
| 1 | Headline / Project Name | Text | no | Single-line, large heading (sample: "West End Yards") | none |
| 2 | Hero Image | Image | no (1/∞ shown, intended single) | Image + Caption (optional) + Alt text (+ Image URL tab) | Image should be 16:9 proportions and less than 1 MB. Ideal 2000 x 1125 px at 72dpi, JPG. |
| 3 | Subhead | Text | no | Single-line heading (sample: "A nexus of city life.") | none |
| 4 | Location | Text | no | Single-line (sample: "Portsmouth, NH") | none |
| 5 | Project Description | Text (rich) | no | WYSIWYG, 2-3 paragraphs; sample has a second short italic credit paragraph ("Designed in association with PCA Design.") | Approximately 100 words in 2-3 paragraphs. We recommend not including bullets in this description. |
| 6 | Markets + Services | Text (selection) | no | Two selection lists (see below) | none |
| 7 | Project Overview | Text (labeled rows) | no | Five labeled rows, multi-line values allowed (see below) | Categories left blank will not show on the website. |
| 8 | Subhead or Statistic | Text | no | Single-line heading (sample: "West End Yards created a vibrant new "live-work-play" neighborhood") | For example: "Hunter's Shop 'n Save owners credit the integrated mechanical system with reducing their electric bills by 66%." Recommend no more than 20 words. |
| 9 | Single Image | Image | no | Image + Caption (optional) + Alt text | Upload one image. 1:1 proportions, under 1 MB. Ideal 1000 x 1000 px at 72dpi, JPG. |
| 10 | Image Grid | Image | YES (fixed 4) | Per image: image + Caption (optional) + Alt text; "Add another" | Upload 4 images. Two images 3:2 proportions, the other two 2:3 proportions. All under 1 MB, 72dpi, JPG. |
| 11 | Quote/Testimonial | Text | no | Textarea | Include quote/testimonial here. All quotes should have quotation marks included. If left blank, a quote will not appear on the website. |

## Block 6: Markets + Services
Prompt 1: "Please highlight the market this project is in:" Options (same 9 as Template 1): Commercial + Mixed Use, Community + Cultural, Federal, State + Municipal, Healthcare, Higher Education, Pre-K – 12 Education, Science + Technology, Workplace. Sample highlights Commercial + Mixed Use.
Prompt 2: "Please highlight the services this project includes:" Options in this sample: Architecture, Civil Engineering, Interior Design, Mechanical Engineering, Structural Engineering (5 only; Template 1 also lists Electrical Engineering). Sample highlights Architecture.

## Block 7: Project Overview
Intro line: "Please add all the pertinent project information:"
Rows, in order (label: sample value):
1. Client: Torrington Properties / The Dolben Company / Mount Vernon Company (three lines)
2. Type: Retail / Office
3. Size: Retail – 22,000 SF / Office – 22,000 SF (two lines)
4. Key Contacts: Zach Smith, AIA (linked to the person's page on harriman.com)
5. Recognition: placeholder "–– List any awards received ––" (treat as empty)
Note: label is plural "Key Contacts" here; Template 1 uses singular "Key Contact".

## Differences from Template 1
- 11 blocks vs 12: no Image Slider, no Content Block.
- Adds Image Grid (fixed 4, mixed orientation: two 3:2, two 2:3). Single Image is 1:1 (Template 1: 3:2).
- Subhead exports as h2 (Template 1: h3). Location wrapped in h4 + span.
- Overview values can be multi-line; Key Contacts links to a people page.
- Shared block order otherwise matches Template 1 (Markets + Services, then Project Overview, then Subhead or Statistic, then images, then Quote).

## Exported HTML findings (west-end-yards.html)
- The export carries some old-site classes (wp-block-heading, wp-block-paragraph, has-heading-1-alt-font-size, wp-block-anchour-body-text-column, has-paragraph-md-font-size) because the sample content was pasted from the live site. These are not Slickplan structure and we must not copy them; use our own class names.
- Heading levels: Headline h1, Subhead h2, Location h4, Subhead or Statistic h3, Overview labels h6.
- Overview in the export is two columns: column 1 = Client + Type, column 2 = Size + Key Contacts. Recognition sits below as `<div><strong>Recognition:</strong> ...</div>`. Confirm the intended two-column layout in the Figma.
- Key Contacts is a link to /about/people/zach-smith/ (attributes type="leadership", id=23524). Decide whether Key Contacts is free text, a link field, or a relationship to the existing people/leadership post type.
- Images are bare `<img>` tags without alt or caption. Order: hero, single image, then four grid images.
- The red bold prompt lines are editor instructions, not site content. Do not render them.
- Quote/Testimonial in the export is placeholder instruction text; treat as empty.
- Block order in the export matches the form order.
