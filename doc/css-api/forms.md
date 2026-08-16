# Forms — CSS API

Styling for forms rendered through the bundle's **SuluFormBundle theme integration**
(`templates/form/theme.html.twig`). The theme turns every Sulu form into a responsive
flex grid and exposes a strict-BEM class surface plus `--iw-form-*` custom properties so
the whole form can be restyled in a few lines of CSS — without touching the Twig theme.

This is distinct from the **`form` block wrapper** (`.iw-block-form*`), which only handles
the surrounding layout (centered / card / split). See [`blocks/form.md`](blocks/form.md)
for the wrapper. This page documents the **fields inside** the form.

> **You do not have to apply the theme yourself.** The form block renders SuluFormBundle
> forms through a bridge template shipped with the bundle, which already declares
> `{% form_theme %}` with the theme below — a selected form comes out styled. You only need
> the theme name when rendering a form outside the block:
> `{% form_theme myForm '@ItechWorldSuluTailwindTheme/form/theme.html.twig' %}`.
> See [`../form-block.md`](../form-block.md).

> Conventions: strict BEM, `iw-` prefix. See [`../css-conventions.md`](../css-conventions.md).
>
> All colors come from `--iw-form-*` custom properties that **cascade from the active block
> variant** (`.iw-variant--N` sets them — see [`block-variants.md`](block-variants.md)). Set
> them yourself to override a single form without touching any variant.

---

## Classes

### Structure

| Class | Role |
|-------|------|
| `.iw-form` | Root override hook on the `<form>` element. No default styling — target it to scope overrides to bundle forms. |
| `.iw-form__grid` | Flex-wrap grid applied to the form and its Symfony-generated row wrappers (`gap: 1rem 1.25rem`). |
| `.iw-form__col` | Column base. Full width on mobile, `min-width: 0` to allow shrinking. |
| `.iw-form__col--half` | 50 % column at `md+` (`768px`). |
| `.iw-form__col--third` | 33 % column at `md+`. |
| `.iw-form__col--two-third` | 66 % column at `md+`. |
| `.iw-form__col--quarter` | 25 % column at `md+`. |
| `.iw-form__col--three-quarter` | 75 % column at `md+`. |
| `.iw-form__col--full` | Full width (marker; the base is already full width). |
| `.iw-form__actions` | Wrapper around the submit row (override hook, no default styling). |

The column width is driven by the SuluFormBundle `width` field (`half`, `one-third`,
`two-third`, `one-quarter`, `three-quarter`, default `full`).

### Fields

| Class | Role |
|-------|------|
| `.iw-form__field` | Base input class — applied to text/email/url/tel/number/password/search inputs **and** textareas. Width 100 %, padding, border, radius, variant-aware colors. |
| `.iw-form__field--focused` | State hook mirroring `:focus` (force the focused appearance, e.g. from JS). |
| `.iw-form__field--error` | State hook — red border (`--iw-form-border-error`). Apply to mark a field invalid. |
| `.iw-form__select` | Single `<select>` — `appearance: none` + custom chevron + right padding. |
| `.iw-form__select--multiple` | Multiple `<select>` — list style (no chevron), scrollable, constrained height. Emitted together with `.iw-form__select`. |
| `.iw-form__check` | Checkbox / radio input (`accent-color` from the variant). |
| `.iw-form__file` | Native `<input type="file">` (simple styled fallback). The enhanced drag-and-drop widget is documented under [File input](#file-input). |
| `.iw-form__errors` | `<ul>` of validation error messages (`--iw-form-border-error` color). |

### Labels, submit & special fields

| Class | Role |
|-------|------|
| `.iw-form__label` | Field label. Centralises the label color (`--iw-form-label`). |
| `.iw-form__label--required` | Marker added when the field is required (override hook — add your own `::after { content: " *" }` if desired). |
| `.iw-form__submit` | Submit button override hook (the variant styling is carried by `.iw-button--variant`). |
| `.iw-form__headline` | SuluFormBundle headline field — owns the bottom border color. |

---

## CSS variables

All default to a token (`--color-*`) so forms look correct out of the box; the active
block variant overrides them automatically.

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-form-bg` | `transparent` | Field background. |
| `--iw-form-text` | `inherit` | Field text color. |
| `--iw-form-label` | `inherit` | Label color. |
| `--iw-form-placeholder` | `var(--iw-form-text, inherit)` | Placeholder color (rendered at `opacity: 0.5`). |
| `--iw-form-border` | `var(--color-border, #d1d5db)` | Field border color (also the headline bottom border). |
| `--iw-form-border-focus` | `var(--color-primary, #3b82f6)` | Border + focus ring color, checkbox/radio accent, select option highlight. |
| `--iw-form-border-error` | `#ef4444` | Error border and error-message color. |

The field radius follows the global `--border-radius` token (not a form-specific variable),
so forms inherit the theme's corner rounding.

---

## Override examples

### Restyle every form field (pill inputs, soft background)

```css
.iw-form {
    --iw-form-bg: #f9fafb;
    --iw-form-border: #e5e7eb;
    --iw-form-border-focus: var(--color-accent);
}

.iw-form__field {
    border-radius: 9999px;
}
```

### Show a red asterisk on required labels

```css
.iw-form__label--required::after {
    content: " *";
    color: var(--iw-form-border-error);
}
```

### Make the submit button full width

```css
.iw-form__actions {
    width: 100%;
}

.iw-form__submit {
    width: 100%;
}
```

---

## Combobox

Every single/multiple `<select>` is progressively enhanced into a searchable dropdown by
the `combobox` Stimulus controller (`assets/controllers/combobox_controller.js`). The native
`<select>` stays in the DOM (hidden) for form submission; the controller builds the visible
UI and keeps both in sync. All combobox colors reuse the same `--iw-form-*` variables as the
fields, so a combobox automatically matches the surrounding form.

| Class | Role |
|-------|------|
| `.iw-combobox` | Root wrapper (relative-positioned). |
| `.iw-combobox__trigger` | The clickable button (also carries `.iw-form__field` for shared field styling). |
| `.iw-combobox__display` | Selected-value area inside the trigger (text in single mode, tags in multiple mode). |
| `.iw-combobox__placeholder` | Placeholder span shown when nothing is selected. |
| `.iw-combobox__chevron` | Dropdown chevron icon. |
| `.iw-combobox__dropdown` | Floating dropdown panel (absolute, `z-index: 50`). |
| `.iw-combobox__search-wrap` | Wrapper around the search input. |
| `.iw-combobox__search` | Search input (also carries `.iw-form__field`). |
| `.iw-combobox__list` | Scrollable options list. |
| `.iw-combobox__item` | A single option. |
| `.iw-combobox__item--active` | Selected / active option (JS-toggled). |
| `.iw-combobox__label` | Label wrapper inside an option in multiple mode (holds the checkbox + text). |
| `.iw-combobox__tag` | A selected-value chip in the trigger (multiple mode). |
| `.iw-combobox__tag-remove` | The `×` button inside a tag. |

> The open/closed state is driven by the `hidden` class on `.iw-combobox__dropdown`, toggled
> by the controller — not by a BEM modifier.

### Override example — accent-colored active option & tags

```css
.iw-combobox__item:hover,
.iw-combobox__item--active {
    background-color: var(--color-accent);
}

.iw-combobox__tag {
    background-color: var(--color-accent);
}
```

---

## File input

`<input type="file">` fields are enhanced into a drag-and-drop dropzone with file badges by
the `fileinput` Stimulus controller (`assets/controllers/fileinput_controller.js`). The
original input stays hidden and in sync for submission. Colors reuse the `--iw-form-*`
variables, so the dropzone matches the rest of the form.

| Class | Role |
|-------|------|
| `.iw-fileinput` | Root wrapper. |
| `.iw-fileinput__dropzone` | The dashed drop area (click to browse, or drag files onto it). |
| `.iw-fileinput__dropzone--dragover` | State modifier added while files are dragged over (JS-toggled). |
| `.iw-fileinput__icon` | Upload icon SVG inside the dropzone. |
| `.iw-fileinput__text` | "Drop files or" hint text. |
| `.iw-fileinput__link` | The "browse" call-to-action (underlined, accent color). |
| `.iw-fileinput__info` | Hint line (accepted types, max files, max size). |
| `.iw-fileinput__error` | Validation error message (hidden until an error occurs). |
| `.iw-fileinput__list` | Container holding the selected-file badges. |
| `.iw-fileinput__badge` | A single selected-file chip. |
| `.iw-fileinput__badge-icon` | Icon wrapper inside the badge. |
| `.iw-fileinput__badge-svg` | The file-type SVG. |
| `.iw-fileinput__badge-name` | File name (truncated with ellipsis). |
| `.iw-fileinput__badge-size` | Human-readable file size. |
| `.iw-fileinput__badge-remove` | The `×` button to remove a file. |

> The simple, non-enhanced native file input (`.iw-form__file`) is documented under
> [Fields](#fields). The dropzone above is the enhanced widget.

### Override example — branded dropzone

```css
.iw-fileinput__dropzone {
    --iw-form-border: var(--color-accent);
    background-color: color-mix(in srgb, var(--color-accent) 4%, transparent);
}

.iw-fileinput__dropzone--dragover {
    background-color: color-mix(in srgb, var(--color-accent) 12%, transparent);
}
```

---

## Custom form template (without SuluFormBundle)

The `form` block can render a **custom Twig template** instead of a SuluFormBundle form:
turn the `useSuluFormBundle` admin toggle off and point the `twigTemplate` field at a
project template. The bundle does **not** ship this template — you create it in your own
project (e.g. `templates/forms/contact.html.twig`).

To match the SuluFormBundle theme look and inherit the active block variant colors, build
it with the same `iw-form__*` classes documented above: make the `<form>` the
`iw-form iw-form__grid` container and wrap each field in an `iw-form__col`. The width
modifiers drive the responsive layout (two `--half` columns sit side by side at `md+`).

```twig
{# templates/forms/contact.html.twig #}
<form method="post" action="{{ path('your_form_handler') }}" class="iw-form iw-form__grid">
    <div class="iw-form__col iw-form__col--half">
        <label for="name" class="iw-form__label iw-form__label--required block text-sm font-medium mb-1.5">{{ 'Name'|trans }}</label>
        <input type="text" id="name" name="name" required class="iw-form__field">
    </div>

    <div class="iw-form__col iw-form__col--half">
        <label for="email" class="iw-form__label iw-form__label--required block text-sm font-medium mb-1.5">{{ 'Email'|trans }}</label>
        <input type="email" id="email" name="email" required class="iw-form__field">
    </div>

    <div class="iw-form__col iw-form__col--full">
        <label for="message" class="iw-form__label iw-form__label--required block text-sm font-medium mb-1.5">{{ 'Message'|trans }}</label>
        <textarea id="message" name="message" rows="5" required class="iw-form__field resize-y"></textarea>
    </div>

    <div class="iw-form__col iw-form__col--full iw-form__actions pt-2">
        <button type="submit" class="iw-form__submit iw-button--variant inline-flex items-center justify-center px-8 py-3 text-sm font-medium transition cursor-pointer">
            {{ 'Send'|trans }}
        </button>
    </div>
</form>
```

Because the template only uses the public classes and `--iw-form-*` variables, it
automatically restyles with the active block variant and with any user override — exactly
like a SuluFormBundle form, without a single hardcoded color.

