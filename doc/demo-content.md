# Demo content

Creates a set of pages showing every content block of the bundle, each in all of
its styles. Two uses for the same command:

* **Showcase** - right after installing, to see what the bundle offers without
  building pages by hand.
* **Fixture** - a stable reference to check against when changing block markup
  or CSS.

```bash
php bin/adminconsole iw-sulu:theme:demo-content
```

```
Demo content "Test Blocks" in website (en)

Media
-----
  12 placeholders in collection "Test Blocks"

Pages
-----
  Test Blocks (index)
  Accordion
  Article Carousel
  ...

 [OK] 17 pages and 12 media created. Open "Test Blocks" in the admin.
```

## Requirements

* The **gd** extension, to draw the placeholder images.
* A homepage in the target webspace. On a fresh install, run
  `php bin/adminconsole sulu:page:initialize-homepage` first - the command stops
  with an explicit error otherwise, since the pages hang under it.

No theme is required: see [Placeholder images](#placeholder-images) below.

## What gets created

An index page holding a `linked_pages` block, with one child page per block
type underneath it:

```
<index page>
├── Accordion
├── Article Carousel
├── Articles
├── Call to action
├── Code - Widget
├── Document
├── Form
├── Gallery
├── iFrame
├── Key figures
├── Linked pages
├── Location
├── separator
├── testimonial
├── text
└── Text - Media
```

Pages hang under the index rather than at the site root, and everything is
published, so the set is browsable straight away. URLs are slugified from the
titles:

```
/test-blocks
/test-blocks/accordion
/test-blocks/text-images
```

A media collection named after the index page holds twelve generated
placeholder images.

## Options

| Option | Default | Purpose |
| --- | --- | --- |
| `name` (argument) | `Test Blocks` | Name of the index page |
| `--webspace`, `-w` | first configured | Target webspace |
| `--locale`, `-l` | webspace default | Locale to create the pages in |
| `--minimal` | off | Only `Text - Media`, `Gallery`, `Call to action` and `Accordion` |

Use `--minimal` to get the idea without clicking through seventeen pages.

## Running it more than once

The name is what separates one set from another:

```bash
php bin/adminconsole iw-sulu:theme:demo-content "Blocks before refactor"
php bin/adminconsole iw-sulu:theme:demo-content "Blocks after refactor"
```

Both sets live side by side, each with its own media collection. If a page with
that name already exists, the command stops and creates nothing rather than
touching what is there.

## Placeholder images

Nothing is shipped with the bundle. The images are drawn at run time by
`DemoImageGenerator`, as gradients taken from the colors of the theme assigned
to the webspace. A bare installation - no theme created yet, or none assigned -
falls back to a neutral palette, which is precisely the moment demo content is
most wanted.

They are PNG rather than SVG on purpose. Sulu crops images through Imagine,
backed by GD in a default installation, and GD cannot read SVG: no format would
be generated, so ratios, focus points and the avif/webp `<picture>` pipeline
would all silently fall back to the raw file. PNG travels through that pipeline
normally.

## Removing a set

There is no removal command. Delete the index page from the admin - Sulu removes
the children with it - then delete the media collection of the same name.

## Adding to the fixture

The pages come from `src/DataFixtures/demo-pages.json`, exported from a real
project. External references are held as symbolic markers so the fixture does
not depend on the database it came from:

* `@media:<n>` points into the generated pool of twelve images.
* `@page:<title>` points at another page of the set.

Under `--minimal` the index still links to every page, so markers pointing at a
page that was not created are dropped rather than left dangling. Locales frozen
into the exported links are rewritten to the locale of the run.
