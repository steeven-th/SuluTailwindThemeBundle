# Block: key_figures — CSS API

Statistics / KPI block with five layout styles: a 2-column card grid, an inline row, horizontal progress bars, a vertical timeline, and a grid with large icons.

Counter animation is driven by the `key-figures` Stimulus controller via `data-key-figures-target` attributes — these are preserved verbatim in every template.

> Conventions: strict BEM, `iw-` prefix. See [`../../css-conventions.md`](../../css-conventions.md).

---

## Classes

### Block + modifiers

| Class | Role |
|-------|------|
| `.iw-block-key-figures` | Root wrapper. Hook only. |
| `.iw-block-key-figures--grid-2x2` | 2-column card grid (centered, max-width). |
| `.iw-block-key-figures--inline` | Centered flex row with large counters. |
| `.iw-block-key-figures--progress` | Stack of horizontal progress bars. |
| `.iw-block-key-figures--timeline` | Vertical timeline with dots and alternating cards. |
| `.iw-block-key-figures--with-icons` | Grid with large icons (1–4 columns on desktop). |
| `.iw-block-key-figures--cols-1` to `--cols-4` | Column-count modifier used together with `--with-icons` to select the desktop layout. |
| `.iw-block-key-figures__timeline-line` | Vertical line in the `--timeline` mode. |
| `.iw-block-key-figures__timeline-items` | Wrapper around timeline items (controls the vertical gap). |

### Subcomponent — `.iw-key-figure` (shared)

| Class | Role |
|-------|------|
| `.iw-key-figure` | Single figure container. |
| `.iw-key-figure--card` | Modifier — card variant used in `--grid-2x2`. |
| `.iw-key-figure--inline` | Modifier — vertical flex stack used in `--inline`. |
| `.iw-key-figure--progress` | Modifier — progress-bar item used in `--progress`. |
| `.iw-key-figure--timeline` | Modifier — timeline item used in `--timeline`. Even items alternate sides via `:nth-child(even)`. |
| `.iw-key-figure--with-icon` | Modifier — centered icon item used in `--with-icons`. |
| `.iw-key-figure__counter` | Animated numeric value (Stimulus `data-key-figures-target="counter"`). |
| `.iw-key-figure__counter--md` / `--lg` / `--xl` | Counter size modifiers. |
| `.iw-key-figure__title` | Figure title (text below the counter). Also carries `.iw-block__title`. |
| `.iw-key-figure__subtitle` | Optional caption under the title. Also carries `.iw-block__subtitle`. |
| `.iw-key-figure__icon` | Image wrapper (square). |
| `.iw-key-figure__icon--lg` | Larger icon used in `--with-icons`. |
| `.iw-key-figure__icon-img` | The `<img>` itself (`object-fit: contain`). |

### Elements of `--progress`

| Class | Role |
|-------|------|
| `.iw-key-figure__progress-header` | Flex row with label + value. |
| `.iw-key-figure__progress-label` | Figure label (left). |
| `.iw-key-figure__progress-value` | Animated percentage (right, Stimulus counter). |
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
| `--iw-key-figure-counter-color` | `var(--iw-variant-paragraph-color, inherit)` | Counter color. |
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
| `--iw-key-figure-icon-size` | `3rem` | Default icon square size. |
| `--iw-key-figure-icon-size-lg` | `4rem` (mobile) / `5rem` (`>=768px`) | Larger icon for `--with-icons`. |
| `--iw-key-figure-icon-margin-bottom` | `0.75rem` | Space below the default icon. |
| `--iw-key-figure-icon-margin-bottom-lg` | `1rem` | Space below `--lg` icon. |

### Grid-2x2

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-block-key-figures-gap` | `var(--iw-cards-gap, 1.5rem)` | Gap between cards. |
| `--iw-block-key-figures-grid-2x2-max-width` | `48rem` | Max-width of the grid (centered). |
| `--iw-key-figure-card-padding` | `1.5rem` / `2rem` (`>=768px`) | Card padding. |
| `--iw-key-figure-border` | `var(--iw-variant-hr-color, var(--color-border, #e5e7eb))` | Card border. |

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

### Timeline

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-block-key-figures-timeline-accent` | `var(--iw-variant-hr-color, var(--color-primary))` | Dot fill, card border, default line color. |
| `--iw-block-key-figures-timeline-ring` | `var(--color-secondary, var(--color-primary))` | Dot outer ring color. |
| `--iw-block-key-figures-timeline-line` | inherits `--accent` | Overrides the line color if you want it different from the accent. |
| `--iw-block-key-figures-timeline-card-border` | inherits `--accent` | Card border color. |
| `--iw-block-key-figures-timeline-card-bg` | `var(--iw-variant-paragraph-bg, var(--iw-variant-subtle-bg))` | Card background. Uses the variant's `paragraphBg` token (admin-configurable), with the auto-computed `--iw-variant-subtle-bg` as fallback when `paragraphBg` is `transparent`. |
| `--iw-block-key-figures-timeline-card-color` | `var(--iw-variant-paragraph-color, inherit)` | Default text color inside the timeline card. |
| `--iw-key-figure-timeline-dot-size` | `1rem` | Dot diameter. |
| `--iw-key-figure-timeline-dot-ring-width` | `4px` | Dot ring thickness. |
| `--iw-key-figure-timeline-card-padding` | `1.5rem` | Card padding. |
| `--iw-block-key-figures-timeline-gap` | `2rem` / `3rem` (`>=768px`) | Vertical gap between timeline items. |

### With-icons

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-block-key-figures-with-icons-gap` | `2rem` | Gap between items. |
| `--iw-key-figure-with-icon-padding` | `1.5rem` | Item padding. |

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

### Force 3 columns on the with-icons mode even when 5+ figures exist

```css
.iw-block-key-figures--with-icons.iw-block-key-figures--cols-4 {
    grid-template-columns: repeat(3, 1fr);
}
```
