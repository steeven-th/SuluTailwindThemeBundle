# Buttons — CSS API

Buttons are the most theme-driven component of the bundle. Their visual identity (background, text, border, radius, hover effects) is fully derived from the admin **Settings > Themes > Default settings > Buttons** tab and compiled into `:root` custom properties by the `ThemeCompiler`.

This page lists every CSS variable and class involved.

> See [`button-effects.md`](../button-effects.md) for the catalog of hover effects (shadow, transform, opacity, duration, easing) and [`css-conventions.md`](../css-conventions.md) for the BEM naming policy.

---

## CSS variables

### Global

Shared by every variant.

| Variable | Description |
|----------|-------------|
| `--iw-button-padding-x` | Horizontal padding shared by every button variant |
| `--iw-button-padding-y` | Vertical padding shared by every button variant |

### Per-variant

| Variable pattern | Description |
|-----------------|-------------|
| `--iw-button-{variant}-bg` | Background color (the fallback color of a [gradient](../gradients.md), `transparent` for a translucent one) |
| `--iw-button-{variant}-bg-image` | The background gradient, when there is one |
| `--iw-button-{variant}-text` | Text color |
| `--iw-button-{variant}-border` | Full border shorthand (`{width} {style} {color}`) or `none` |
| `--iw-button-{variant}-radius` | Border radius |
| `--iw-button-{variant}-hover-bg` | Background on hover |
| `--iw-button-{variant}-hover-bg-image` | The hover gradient, when there is one. Painted on the `::before` layer, see [Button hover effects](../button-effects.md#with-a-gradient-background) |
| `--iw-button-{variant}-hover-text` | Text color on hover |
| `--iw-button-{variant}-hover-border` | Border shorthand on hover (or `none`) |
| `--iw-button-{variant}-accent` | Accent colour of the style, only when set |

### On the class of a style

| Variable | Description |
|----------|-------------|
| `--iw-button-accent` | Accent colour, set on `.iw-button--{slug}` (and on the variant copy of the style) when the style defines one. Not used by the bundle: a hook for project CSS |

Where `{variant}` is the **slug** of any button you defined in the admin
(buttons are unlimited and named by slug — no longer the 3 fixed roles).

> Border `width` and `style` are configured per button in the admin and folded directly into the `--iw-button-{slug}-border` shorthand. That shorthand always describes four sides: a style drawing its border on some sides only is written with longhands in its class, and the variable does not reflect the sides. Hover effects (shadow, transform, opacity, duration, easing) are applied in the generated `.iw-button--{slug}` rules and do not produce standalone CSS variables.

---

## CSS classes

Ready-to-use button classes with hover transitions. They follow the strict BEM convention.

| Class | Description |
|-------|-------------|
| `.iw-button` | Base button (rarely used alone — apply a style) |
| `.iw-button--<slug>` | One class per button defined in the admin (e.g. `.iw-button--primary`, `.iw-button--cta`, `.iw-button--employeur`) |

Each button rule includes `background-color`, `color`, `border`, `border-radius`, `padding`, `cursor: pointer`, `display: inline-block`, `text-decoration: none` and a `transition`. Hover states are also generated.

**Same box for every style.** The border is drawn inside the padding: each side of the padding is `var(--iw-button-padding-*)` minus the border width on that side (never below zero). A filled button and an outlined one side by side are therefore the same size, and their labels sit at the same place. Keep this in mind when restyling a button in CSS: changing its `border-width` without its `padding` brings the size difference back.

**Usage in Twig:**
```twig
<a href="/contact" class="iw-button iw-button--primary inline-block px-6 py-3">Contact us</a>
<a href="/learn-more" class="iw-button iw-button--secondary inline-block px-6 py-3">Learn more</a>
```

---

## Style settings

Beyond colours, radius and hover effects, each style in the **Buttons** tab takes:

| Field | Values | Compiled as |
|-------|--------|-------------|
| Border sides (`borderSides`) | `all` *(default)*, `top`, `right`, `bottom`, `left`, `x` (left and right), `y` (top and bottom) | `all` keeps the `border` shorthand. Any other value writes `border-style`, `border-color` and `border-width` with `0` on the sides left out, so the hover border colour only recolours the drawn sides |
| Accent colour (`accent`) | a palette reference or a colour, optional | `--iw-button-accent` on the class, see [Project-specific ornaments](#project-specific-ornaments) |
| Shadow at rest (`shadow`) | `none` *(default)*, `sm`, `md`, `lg` | `box-shadow`, replaced by the hover shadow while hovered, see [`button-effects.md`](../button-effects.md) |
| Label weight (`fontWeight`) | inherited *(default)*, normal, medium, semi-bold, bold | `font-weight: 400` to `700` |
| Label case (`textTransform`) | inherited *(default)*, as typed, uppercase | `text-transform` |

**Gradient label.** The text color and the hover text color accept a [gradient](../gradients.md#text-and-pictograms), clipped to `.iw-button__label` rather than to the button, whose background the clip would take away. The button keeps the fallback color, which its pictogram follows.

**Gradient border.** The border and the hover border accept a [gradient](../gradients.md). A gradient border is drawn as a ring on the `::after` of the button: the real border goes, the ring takes its place inside the box at the same thickness, on the sides `borderSides` keeps, so the button keeps its size and its padding. `border-image` would drop the radius, and painting the gradient under an opaque inside is impossible for an outlined button whose inside is transparent. The line style does not apply to a ring: a gradient border is solid. The hover border recolors the ring, color or gradient. The native file button of the forms has no pseudo-element and keeps a solid border in the gradient's fallback color. The ring takes `::after`, the pseudo-element the [ornaments](#project-specific-ornaments) below use, and `::before` belongs to the hover effects: a style carrying an ornament keeps a solid border.

A field left on its default writes nothing, so a style that never opens them compiles exactly as before. There is no font size per style: the size belongs to the context (a block, the menu), not to the style.

A border on one side keeps the corners of the button: with a large radius the rule curves up at its ends. A bottom rule reads best with a small radius or none.

---

## Project-specific ornaments

What a charter adds on top of a style (a coloured dot after the label, a slanted corner) has no field in the admin, on purpose: it would grow the form for one project at a time. The style is created in the admin, so it shows in the picker and works in every block, and the project completes it in CSS keyed on its slug.

**1. Create the styles in the admin.** For example three profile entries, white background, 4px bottom rule, one accent each: `profile-employer`, `profile-employee`, `profile-self-employed`. Point the border and the accent at the same palette colour to type it once.

**2. Contribute the rule** through `ThemeCompileEvent` (see [`extensibility.md`](../extensibility.md#4-contributing-css)). One rule serves the three styles, each dot taking the accent of its own style:

```php
public function onCompile(ThemeCompileEvent $event): void
{
    $event->addRule('[class*="iw-button--profile-"]::after { content: "."; color: var(--iw-button-accent); }');
}
```

**3. Declare the slugs** the rule depends on. A slug is typed in the admin, and renaming it there silently detaches the rule:

```yaml
# config/packages/itech_world_sulu_tailwind_theme.yaml
itech_world_sulu_tailwind_theme:
    required_button_styles: [profile-employer, profile-employee, profile-self-employed]
```

A theme lacking one of them is then reported, never blocked: a warning from `iw-sulu:theme:compile`, a warning in the log each time the theme is compiled (which is what a save in the admin does), and an orange line per site theme in `iw:tailwind-theme:check`.

The editor is warned too, where the mistake is made: the **Buttons** tab of the theme shows a warning above the list as soon as a declared slug is renamed or deleted, before the save. It names the missing slugs, goes away once they are back, and never prevents saving. A project declaring no slug gets no warning and no request.

**Inside a block variant**, the button of the variant carries `.iw-button--variant`, not the class of its style: it gets the colours, the rule and `--iw-button-accent` of the style, but a selector keyed on the slug does not reach it. Name those variants in the rule when they use a decorated style:

```css
[class*="iw-button--profile-"]::after,
.iw-variant--profiles .iw-button--variant::after { content: "."; color: var(--iw-button-accent); }
```

**Accessibility.** A white button with a bottom rule on a white page is recognised by its rule alone, which WCAG 1.4.11 asks to contrast by at least 3:1 with the page. The label keeps its own 4.5:1 against the button background, and the focus ring stays the one of the theme: do not remove the outline in the project rule.

---

## Override examples

### Custom button using the variables

```css
.my-custom-button {
    background-color: var(--iw-button-primary-bg);
    color: var(--iw-button-primary-text);
    border-radius: var(--iw-button-primary-radius);
    padding: var(--iw-button-padding-y) var(--iw-button-padding-x);
}
.my-custom-button:hover {
    background-color: var(--iw-button-primary-hover-bg);
    color: var(--iw-button-primary-hover-text);
}
```

### Restyle a variant in user CSS

```css
.iw-button--primary {
    border-radius: 0;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
```
