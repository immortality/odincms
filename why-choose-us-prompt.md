# Task: "Why choose us" + "Marquee" widgets for OdinCMS

Attach the design screenshot to this prompt. It shows two blocks: a dark "Why choose us" section, and a
tilted lime ribbon of scrolling words under it. Build both as **widgets** that editors can place in any
widget area (normally `home-top`) from Admin → Widgets.

## 0. Read first, change nothing yet

Read these files to learn the existing conventions, and follow them exactly:

- `ODINCMS_DEVELOPMENT.md` §5 (modules), §9 (widgets), §12 (cross-cutting rules)
- `changelogs.md` (latest admin and form conventions)
- `docs/modules.md` (manifest reference, widgets, translations, tests)
- `app/Cms/Widgets/Widget.php`, `app/Cms/Widgets/WidgetRegistry.php`, `app/Cms/Widgets/HtmlWidget.php`
- `app/Cms/Support/FormFields.php` (the available field types, and how a **translatable textarea** is declared)
- `modules/Blog/Widgets/LatestPostsWidget.php` and its view (a module widget example)
- `modules/Blog/module.json`, `modules/Blog/Providers/BlogServiceProvider.php`
- `resources/views/themes/default/layouts/app.blade.php`, `resources/css/theme.scss`, `resources/js/theme.js`
- `tests/Feature/Cms/TranslationsTest.php` and an existing widget test

If a convention in this prompt conflicts with the code, **the code wins**. Tell me about the conflict.

## 1. New module: `Sections`

- Folder `modules/Sections`, slug `sections`, namespace `Modules\Sections`, `"core": false`, version `1.0.0`.
- Description (en/ru/ky): "Landing page sections as widgets" / "Секции лендинга в виде виджетов" /
  "Лендинг секциялары виджет түрүндө".
- This module only provides widgets: no model, no migration, no admin CRUD, no admin menu, no routes.
  If you start from `php artisan cms:make:module Sections`, delete everything the module doesn't need.
- `requires: ["widgets"]`, if the manifest validator allows it. Otherwise leave it out and say why.
- `widgets`: `Modules\Sections\Widgets\WhyChooseUsWidget`, `Modules\Sections\Widgets\MarqueeWidget`.
- `permissions`: none, unless the manifest requires at least one.

## 2. Widget `sections.why-choose-us`

`$name = 'sections::widgets.why_choose_us'`.

### Fields (`fields()`)

| Key | Type | Rules / default |
|---|---|---|
| `eyebrow` | translatable text | max 60 |
| `heading` | translatable text | required (default locale), max 120 |
| `text` | translatable textarea | max 400 |
| `badge` | translatable text | max 40; badge on the illustration, hidden when empty |
| `card_1_icon` | select | icon list below; default `code-slash` |
| `card_1_title` | translatable text | max 60 |
| `card_1_text` | translatable textarea | max 200 |
| `card_2_icon` | select | default `speedometer2` |
| `card_2_title` | translatable text | max 60 |
| `card_2_text` | translatable textarea | max 200 |
| `bar_label` | translatable text | max 60; the bar is hidden when empty |
| `bar_value` | number | `integer\|min:0\|max:100`, default 97 |
| `link_label` | translatable text | optional button under the bar, hidden when empty |
| `link_url` | text | `nullable\|max:255`; a relative path or an `https://` URL |

Icon select options are Bootstrap Icons names, with labels translated through `sections::widgets.icons.*`:
`code-slash, speedometer2, shield-check, phone, palette, cart3, search, graph-up-arrow, rocket-takeoff,
headset, gear, envelope-paper`.

A card with an empty title is not rendered. If both cards are empty, the cards block is not rendered.

### View `sections::widgets.why-choose-us`

- `<section class="why-choose-us">` with a two-column Bootstrap grid (`col-lg-6` / `col-lg-6`), stacked on
  mobile with the illustration first.
- **Left column, the illustration:** pure HTML/CSS, no image file.
  - A rounded blue panel with a dotted background (`radial-gradient` dots).
  - A 5×3 grid of alternating squares and circles in lime/olive tints at varying opacity.
  - A lime pill badge in the top-left corner with a check icon and the `badge` text.
  - A smaller overlapping card in the bottom-right corner: a lighter blue gradient, a thick dark ring
    drawn with CSS borders, and a dot.
  - The whole illustration is decorative: `aria-hidden="true"`.
- **Right column:**
  - The eyebrow: small, uppercase, letter-spaced, lime, with a short lime line before it.
  - The heading as an `<h2>`.
  - The muted intro paragraph.
  - Two cards: a dark surface, a subtle border, rounded corners, a blue square icon tile with a white
    `bi-*` icon, a bold title and a muted text.
  - The progress bar: the label on the left and `NN%` on the right, a thin track, and a fill with a
    blue → lime gradient. Use `role="progressbar"` with `aria-valuenow`, `aria-valuemin`,
    `aria-valuemax` and `aria-label`.
- Escape everything with `{{ }}`. No HTML is accepted in any field.
- Use the current locale and fall back to the default locale (same as the other widgets).

## 3. Widget `sections.marquee`

`$name = 'sections::widgets.marquee'`.

| Key | Type | Rules / default |
|---|---|---|
| `words` | translatable textarea | one word or phrase per line; required in the default locale; max 500 |
| `speed` | number | seconds per loop, `integer\|min:10\|max:120`, default 40 |
| `tilt` | checkbox | default on: the ribbon is rotated about -2° |
| `direction` | select | `left` (default) / `right` |

### View `sections::widgets.marquee`

- Split `words` on new lines, trim each line, and drop empty lines. If no words are left, render nothing.
- The words alternate between **solid** dark text and **outline** text (`-webkit-text-stroke`, transparent
  fill). Put a blue `✳` separator between words (a `bi-asterisk` icon or a styled span).
- For a seamless loop, render the track twice. The second copy gets `aria-hidden="true"`. The first copy is a
  normal list (`<ul>`) for screen readers.
- Animate it with a CSS `@keyframes` translateX, and read the duration from a `--marquee-speed` custom
  property set inline. Pause the animation on hover.
- With `prefers-reduced-motion: reduce`, don't animate.
- The ribbon is full-bleed (edge to edge) even inside a container. Prevent horizontal page scroll
  (`overflow-x: clip` on the wrapper).

## 4. Styles and scripts (frontend theme)

- Styles go in `resources/css/theme/_sections.scss`, imported from `resources/css/theme.scss`.
  Scope every rule under `.why-choose-us` or `.marquee-ribbon`.
- Put the colors in CSS custom properties at the top of the partial, so a theme can override them:
  - `--sec-bg: #0b0b0c`
  - `--sec-surface: #151517`
  - `--sec-border: rgba(255, 255, 255, .08)`
  - `--sec-text: #fff`
  - `--sec-muted: #a3a3ad`
  - `--sec-lime: #d4ff3a`
  - `--sec-blue: #2557e8`
  - `--sec-blue-2: #1d3f9e`
- Headings use the theme's heading font, heavy weight, with tight line-height.
- Check that Bootstrap Icons are loaded in the frontend theme. The package was installed in Step 0.3.
  If the icons are not loaded, import them in `theme.scss`.
- Progress bar animation: in `resources/js/theme.js`, an `IntersectionObserver` grows the fill from 0 to
  its value when the section scrolls into view. The script runs only when `[data-progress]` is on the page.
  Without JS or with reduced motion, the bar shows its full value.
- Run `npm run build`.

## 5. Default content (seeder)

`Modules\Sections\Database\Seeders\SectionsDatabaseSeeder` runs on install. It places both widgets in the
`home-top` area of the current theme: first the "Why choose us" widget, then the marquee. It adds them
**only if** that area exists and no instance of the widget is in it yet. If the area is missing, the seeder
does nothing.

Use these translations (`title` of the instance: "Why choose us" / "Почему мы" / "Эмне үчүн биз"):

| Field | en | ru | ky |
|---|---|---|---|
| eyebrow | Why choose us | Почему мы | Эмне үчүн биз |
| heading | Fast, secure websites built to grow your business | Быстрые и надёжные сайты, которые помогают бизнесу расти | Бизнесиңиздин өсүшүнө жардам берген тез жана ишенимдүү сайттар |
| text | Senior developers, fixed estimates and weekly demos. You always see what we're building, why, and when it ships. | Опытные разработчики, фиксированная смета и демо каждую неделю. Вы всегда видите, что мы делаем, зачем и когда запуск. | Тажрыйбалуу иштеп чыгуучулар, белгиленген смета жана ар жумалык демо. Эмне кылып жатканыбызды, эмне үчүн жана качан ишке киргизерибизди дайыма көрүп турасыз. |
| badge | Laravel experts | Эксперты Laravel | Laravel адистери |
| card_1_title | Custom Development | Разработка под задачу | Тапшырмага ылайык иштеп чыгуу |
| card_1_text | Laravel and modern front-ends built around your workflow, not squeezed into a template. | Laravel и современный фронтенд под ваши процессы, а не подгонка под шаблон. | Шаблонго ылайыкташтыруу эмес, сиздин иш процесстериңизге курулган Laravel жана заманбап фронтенд. |
| card_2_title | Performance & SEO | Скорость и SEO | Ылдамдык жана SEO |
| card_2_text | Green Core Web Vitals, clean markup and structured data that search engines reward. | Зелёные Core Web Vitals, чистая вёрстка и микроразметка, которую ценят поисковики. | Жашыл Core Web Vitals, таза белгилөө жана издөө системалары баалаган структураланган маалыматтар. |
| bar_label | Projects launched on schedule | Проекты, сданные в срок | Убагында тапшырылган долбоорлор |
| link_label | Start a project | Обсудить проект | Долбоорду талкуулоо |

Other settings: `card_1_icon = code-slash`, `card_2_icon = speedometer2`, `bar_value = 97`,
`link_url = /contact`.

Marquee words, one per line:

- en: Design, Development, E-commerce, Performance, SEO, Security, Support
- ru: Дизайн, Разработка, E-commerce, Скорость, SEO, Безопасность, Поддержка
- ky: Дизайн, Иштеп чыгуу, E-commerce, Ылдамдык, SEO, Коопсуздук, Колдоо

Marquee settings: `speed 40`, `tilt on`, `direction left`.

## 6. Translations

`modules/Sections/Resources/lang/{en,ru,ky}/widgets.php` holds:

- the widget names
- every field label and help text
- the icon labels
- the direction options

`TranslationsTest` must pass, so every key needs all three languages.

## 7. Tests (Pest, `modules/Sections/Tests/Feature/SectionsWidgetsTest.php`)

- Both widgets are registered while the module is enabled, and are not offered after it is disabled.
- "Why choose us" renders the heading, the cards and the progress bar with the right `aria-valuenow`.
  An empty card, bar, badge and link are not rendered.
- A Russian visitor (`/ru`) sees the Russian text. A missing ky value falls back to en.
- Validation: `bar_value` 101 fails, an icon not on the list fails, and `link_url` `javascript:alert(1)`
  fails.
- HTML in a field is escaped (`<script>` appears as text).
- Marquee: blank lines are dropped, the words render twice with the second copy `aria-hidden`, and no
  words means empty output.
- The seeder places both widgets in `home-top` once, a second run adds nothing, and it does nothing when
  `home-top` is missing.

## 8. Docs

- Add an entry at the top of `changelogs.md`, dated today, in the same style as the existing entries:
  - what was added
  - the files
  - the fields
  - how to place the widgets
  - "Adding a widget to Sections" in 3–4 steps, so the next section widgets (hero, stats,
    testimonials) follow the same pattern
- Add a line under Phase 9 in `ODINCMS_STEPS.md`.

## 9. Constraints

- No new Composer or npm packages.
- No cross-module imports. Don't read Services data.
- No raw SQL, no `data-height` attributes, no inline `<script>` in the widget views.
- Thin classes. If the views need non-trivial logic (word splitting, URL checks), put it in a small helper
  class in the module.

## Done when

- `php artisan cms:modules:sync` and `php artisan cms:module:install sections` work, and the home page shows
  both sections in en, ru and ky, matching the screenshot on desktop and on a 375 px phone with no
  horizontal scroll.
- The widgets can be edited, reordered and restricted in Admin → Widgets, and the frontend changes right
  after saving.
- `composer test` (Pint, Larastan, Pest) and `npm run build` pass.

At the end, tell me what you changed, anything you couldn't follow, and any decision you had to make.
