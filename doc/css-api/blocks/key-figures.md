# Block: key_figures — CSS API

Statistics / KPI block with five layout styles: a card grid, an inline row, horizontal progress bars, a vertical timeline, and a split layout with text beside the figures.

Every style renders the pictogram the block form offers. Four of them dropped it before 3.0.0, which is what made a sixth style, `with_icons`, look like a layout of its own: it was the inline row with the pictogram rendered. It was removed, and `KeyFigureIconContractTest` holds the rule that made it redundant.

Counter animation is driven by the `key-figures` Stimulus controller via `data-key-figures-target` attributes — these are preserved verbatim in every template.

> Conventions: strict BEM, `iw-` prefix. See [`../../css-conventions.md`](../../css-conventions.md).

---

## Classes

### Block + modifiers

| Class | Role |
|-------|------|
| `.iw-block-key-figures` | Root wrapper. Hook only. |
| `.iw-block-key-figures--grid-2x2` | Card grid, 2 to 4 columns. The name is the style key stored on published blocks and in the block styles of every theme, so it kept it when the column count became a setting. |
| `.iw-block-key-figures--cols-1` to `--cols-4` | How many columns `--grid-2x2` holds from `1024px`, from the **Settings > Columns** field. Below that width the grid folds to two columns, then to one. |
| `.iw-block-key-figures--grid-wide` | Present past two columns: drops the reading-width cap, which leaves three or four cards too cramped to hold a counter. |
| `.iw-block-key-figures--inline` | Centered flex row with large counters. |
| `.iw-block-key-figures--progress` | Stack of horizontal progress bars. |
| `.iw-block-key-figures--timeline` | Vertical timeline with dots and alternating cards. |
| `.iw-block-key-figures--split` | Text (titles, rich text, action buttons) on one side, figures stacked on the other from `lg`. Replaces the CTA block's counter accessory, removed in 3.0.0. |
| `.iw-block-key-figures--highlight` | Present unless the editor unticks **Settings > Highlight the figures**. Paints the counters with the variant's highlight colour, the one already colouring the `[[marked]]` words of a title. Not offered on `--progress`, whose percentage accompanies its bar. |
| `.iw-block-key-figures__content` | Text zone of `--split`; `--last` moves it after the figures on desktop. |
| `.iw-block-key-figures__figures` | Figures zone of `--split`; `--first` moves it before the text on desktop. |
| `.iw-block-key-figures__timeline-line` | Vertical line in the `--timeline` mode. |
| `.iw-block-key-figures__timeline-items` | Wrapper around timeline items (controls the vertical gap). |

### Subcomponent — `.iw-key-figure` (shared)

| Class | Role |
|-------|------|
| `.iw-key-figure` | Single figure container. |
| `.iw-key-figure--card` | Modifier — card variant used in `--grid-2x2`. Framed by the card surface of the variant, like every other card of the bundle. |
| `.iw-key-figure--inline` | Modifier — vertical flex stack used in `--inline`. |
| `.iw-key-figure--progress` | Modifier — progress-bar item used in `--progress`. |
| `.iw-key-figure--timeline` | Modifier — timeline item used in `--timeline`. Even items alternate sides via `:nth-child(even)`. |
| `.iw-key-figure__counter` | Animated numeric value (Stimulus `data-key-figures-target="counter"`). |
| `.iw-key-figure__counter--md` / `--lg` / `--xl` | Counter size modifiers. |
| `.iw-key-figure__title` | Figure title (text below the counter). Also carries `.iw-block__title`. |
| `.iw-key-figure__subtitle` | Optional caption under the title. Also carries `.iw-block__subtitle`. |
| `.iw-key-figure__icon` | Pictogram wrapper. Centred in `--grid-2x2` and `--inline`, aligned with the text in the three others. Each figure picks its own size in the form, the CSS only sets the default. |
| `.iw-key-figure__icon--beside` | The pictogram of `--progress`, which joins the label on one line rather than standing above the figure. |
| `.iw-key-figure__icon-img` | The `<img>` itself (`object-fit: contain`). |

### Elements of `--progress`

| Class | Role |
|-------|------|
| `.iw-key-figure__progress-header` | Flex row with label + value. |
| `.iw-key-figure__progress-label` | Figure label (left), a flex row so it can carry the pictogram. |
| `.iw-key-figure__progress-value` | The displayed value (right, Stimulus counter). Free text: the bar reads its own field, so this can say `Sulu 3.0` or `12/20`. Rendered with its real value rather than a zero the script replaces, so it reads without JavaScript. |
| `.iw-key-figure__progress-track` | Track of the bar (tinted background). |
| `.iw-key-figure__progress-bar` | Animated fill (`data-key-figures-target="progressBar"`, width starts at `0%`). |

### Elements of `--timeline`

| Class | Role |
|-------|------|
| `.iw-key-figure__timeline-dot` | Round dot positioned on the vertical line. |
| `.iw-key-figure__timeline-card` | Content card sitting next to the dot. |

---

## CSS variables

### Counter

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-key-figure-counter-color` | `var(--iw-variant-paragraph-color, inherit)` | Counter colour when the figures are not highlighted. |
| `--iw-key-figure-counter-highlight-color` | `var(--iw-variant-highlight, var(--color-accent))` | Counter colour under `.iw-block-key-figures--highlight`. Follows the variant's highlight colour by default, so one setting governs the marked words of a title, the number of a card and the figures here. |
| `--iw-key-figure-counter-weight` | `700` | Counter font-weight. |
| `--iw-key-figure-counter-size` | `1.5rem` | Default counter size. |
| `--iw-key-figure-counter-size-md` | `1.5rem` | `__counter--md` mobile size. |
| `--iw-key-figure-counter-size-md-bp` | `1.875rem` | `__counter--md` desktop size. |
| `--iw-key-figure-counter-size-lg` | `1.875rem` | `__counter--lg` mobile size. |
| `--iw-key-figure-counter-size-lg-bp` | `2.25rem` | `__counter--lg` desktop size. |
| `--iw-key-figure-counter-size-xl` | `2.25rem` | `__counter--xl` mobile size. |
| `--iw-key-figure-counter-size-xl-md` | `3rem` | `__counter--xl` `>=768px`. |
| `--iw-key-figure-counter-size-xl-lg` | `3.75rem` | `__counter--xl` `>=1024px`. |

### Title / subtitle / icon

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-key-figure-title-margin-top` | `0.5rem` | Default top margin of the title. |
| `--iw-key-figure-title-size` | `1rem` | Default title font-size. |
| `--iw-key-figure-title-weight` | `600` | Title font-weight. |
| `--iw-key-figure-subtitle-margin-top` | `0.25rem` | Top margin of the subtitle. |
| `--iw-key-figure-subtitle-size` | `0.875rem` | Subtitle font-size. |
| `--iw-key-figure-subtitle-opacity` | `0.75` | Subtitle opacity. |
| `--iw-key-figure-icon-size` | `3rem` | Default pictogram size, when the figure picks none of its own. |
| `--iw-key-figure-icon-size-beside` | `1.5rem` | Pictogram size in `--progress`, where it shares a line with the label. |
| `--iw-key-figure-icon-color` | `var(--iw-variant-highlight, var(--color-accent))` | Pictogram colour. Library icons follow it, an editor's own media keeps its colours. |
| `--iw-key-figure-icon-margin-bottom` | `0.75rem` | Space below the pictogram. |

### Grid

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-block-key-figures-split-gap` | `var(--iw-blocks-gap, 1.5rem)` | Gap between the text zone and the figures in `--split`. |
| `--iw-block-key-figures-gap` | `var(--iw-blocks-component-gap, 1.5rem)` | Gap between cards. |
| `--iw-block-key-figures-grid-2x2-max-width` | `48rem` | Reading-width cap, applied up to two columns. |
| `--iw-block-key-figures-grid-wide-max-width` | `none` | What replaces it past two columns, under `--grid-wide`. |
| `--iw-key-figure-card-padding` | `1.5rem` / `2rem` (`>=768px`) | Card padding. |
| `--iw-key-figure-card-bg` | `var(--iw-variant-card-bg, transparent)` | Card background, from the variant's **card surface**. The card declared none at all before 3.0.0, so no variant and no surface setting could fill it. |
| `--iw-key-figure-card-color` | `var(--iw-variant-paragraph-color, inherit)` | Text colour on that background. |
| `--iw-key-figure-card-border` | `var(--iw-variant-paragraph-border, transparent)` | Card border colour. Its width comes from `--iw-variant-paragraph-border-width` and defaults to zero, so a variant asking for no border gets none. Replaces `--iw-key-figure-border`, which drew a hairline whatever the variant said. |

### Inline

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-block-key-figures-inline-gap` | `2rem` / `4rem` (`>=768px`) | Gap between inline items. |
| `--iw-key-figure-inline-min-width` | `7.5rem` | Min-width per inline item to avoid squashed columns. |

### Progress

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-block-key-figures-progress-color` | `var(--iw-variant-hr-color, var(--color-primary))` | Bar fill + track tint. |
| `--iw-block-key-figures-progress-gap` | `1.5rem` | Gap between rows. |
| `--iw-block-key-figures-progress-max-width` | `48rem` | Max-width of the progress block. |
| `--iw-key-figure-progress-height` | `0.75rem` | Bar height. |
| `--iw-key-figure-progress-label-gap` | `0.5rem` | Space between the pictogram and the label. |

### Timeline

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-block-key-figures-timeline-accent` | `var(--iw-variant-hr-color, var(--color-primary))` | Dot fill, card border, default line color. |
| `--iw-block-key-figures-timeline-ring` | `var(--color-secondary, var(--color-primary))` | Dot outer ring color. |
| `--iw-block-key-figures-timeline-line` | inherits `--accent` | Overrides the line color if you want it different from the accent. |
| `--iw-block-key-figures-timeline-card-border` | inherits `--accent` | Card border color. |
| `--iw-block-key-figures-timeline-card-bg` | `var(--iw-variant-card-bg, transparent)` | Card background, from the variant's **card surface**. A variant that fills no card draws none. |
| `--iw-block-key-figures-timeline-card-color` | `var(--iw-variant-paragraph-color, inherit)` | Default text color inside the timeline card. |
| `--iw-key-figure-timeline-dot-size` | `1rem` | Dot diameter. |
| `--iw-key-figure-timeline-dot-ring-width` | `4px` | Dot ring thickness. |
| `--iw-key-figure-timeline-card-padding` | `1.5rem` | Card padding. |
| `--iw-block-key-figures-timeline-gap` | `2rem` / `3rem` (`>=768px`) | Vertical gap between timeline items. |

---

## Override examples

### Accent-tinted progress bars

```css
.iw-block-key-figures--progress {
    --iw-block-key-figures-progress-color: var(--color-accent);
    --iw-key-figure-progress-height: 1rem;
}
```

### Vertical timeline with a custom accent

```css
.iw-block-key-figures--timeline {
    --iw-block-key-figures-timeline-accent: var(--color-accent);
    --iw-block-key-figures-timeline-ring: var(--color-background);
}
```

### Bigger inline counters

```css
.iw-block-key-figures--inline {
    --iw-key-figure-counter-size-xl: 3rem;
    --iw-key-figure-counter-size-xl-md: 4rem;
    --iw-key-figure-counter-size-xl-lg: 5rem;
}
```

### Fold the grid to three columns whatever the setting asks for

```css
.iw-block-key-figures--grid-2x2.iw-block-key-figures--cols-4 {
    grid-template-columns: repeat(3, 1fr);
}
```

### Frame the grid cards without filling them

```css
.iw-block-key-figures--grid-2x2 .iw-key-figure--card {
    --iw-key-figure-card-bg: transparent;
    --iw-key-figure-card-border: var(--color-border);
    border-width: 1px;
}
```

### Bigger pictograms on the inline row

```css
.iw-block-key-figures--inline {
    --iw-key-figure-icon-size: 4rem;
}
```
