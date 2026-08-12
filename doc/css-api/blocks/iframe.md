# Block: iframe — CSS API

Embeds an external page (scheduling widget, video, map, third-party form) with two layout styles: inside the container (`--default`) or edge to edge (`--fullwidth`).

> There is deliberately no card style: an embed fills its whole box, so a card surface would never be visible behind it and only a hairline border would show. Use `cardRadius` on the default style to round the frame instead.

The `.iw-embed` component is shared with the sandboxed mode of the code block, so any override written here applies to both.

> Conventions: strict BEM, `iw-` prefix. See [`../../css-conventions.md`](../../css-conventions.md).

---

## Security model

Worth knowing before overriding anything, because part of it is enforced in the markup:

- **The URL is validated server-side** (`EmbedUrlValidator`): `https` only, no credentials, and an optional host allowlist from the bundle config. An invalid URL renders **nothing** — no frame, no error, no broken layout.
- **`sandbox` never includes `allow-top-navigation`**, in any mode. An embed cannot redirect the page hosting it.
- **`allow` is built from explicit editor choices.** Camera, microphone and geolocation are opt-in per block, not granted wholesale.
- **When consent is required, the `src` attribute is absent from the DOM.** Do not write CSS that assumes an iframe is always loaded — style `.iw-embed__consent` instead.

Host allowlist, when a project knows its providers:

```yaml
itech_world_sulu_tailwind_theme:
    blocks:
        iframe:
            allowed_hosts: ['www.youtube.com', 'calendly.com']
```

An entry also covers its subdomains (`example.com` matches `widget.example.com`), and whole labels only — `evil-example.com` does not match `example.com`.

---

## Classes

### Block + modifiers

| Class | Role |
|-------|------|
| `.iw-block-iframe` | Root wrapper. Hook only. |
| `.iw-block-iframe--default` | Embed inside the page container. |
| `.iw-block-iframe--fullwidth` | Edge-to-edge embed (`100vw`, absorbed by `body { overflow-x: clip }`). |

### Embed component (shared with the code block)

| Class | Role |
|-------|------|
| `.iw-embed` | Sizing box. Positioning context for the consent placeholder. |
| `.iw-embed__frame` | The `<iframe>` itself. Borderless, fills its box. |
| `.iw-embed--h-300` … `--h-1000` | Enumerated fixed heights. |
| `.iw-embed--h-custom` | Free height, reads `--iw-embed-height`. |
| `.iw-ratio--16-9` / `--4-3` / `--1-1` / `--21-9` / `--9-16` | Aspect-ratio sizing (shared utility). |

### Consent placeholder

| Class | Role |
|-------|------|
| `.iw-embed__consent` | Overlay shown while the embed is not allowed to load. Fills the box so nothing reflows on load. |
| `.iw-embed__consent-image` | Decorative background visual. `pointer-events: none` so it never swallows the button click. |
| `.iw-embed__consent-body` | Text + button wrapper. |
| `.iw-embed__consent-text` | Explanatory message. |
| `.iw-embed__consent-button` | Accept / open-preferences button. |

---

## CSS variables

### Sizing

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-embed-height` | `500px` | Free height, set by a `<style>` block scoped to the embed id. Server-clamped to 50–5000 px. |

### Consent placeholder

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-embed-consent-bg` | `var(--iw-variant-paragraph-bg, var(--iw-variant-subtle-bg, …))` | Placeholder background. **Follows the active block variant**, like every other surface nested in a block. |
| `--iw-embed-consent-text` | `var(--iw-variant-paragraph-color, inherit)` | Placeholder text color. Follows the active block variant. |
| `--iw-embed-consent-padding` | `1.5rem` | Inner padding. |
| `--iw-embed-consent-gap` | `1rem` | Space between message and button. |
| `--iw-embed-consent-max-width` | `36rem` | Max width of the message column. |
| `--iw-embed-consent-image-opacity` | `0.35` | Opacity of the background visual. |
| `--iw-embed-consent-text-size` | `0.9375rem` | Message font size. |
| `--iw-embed-consent-text-line-height` | `1.5` | Message line height. |
| `--iw-embed-consent-focus-color` / `-width` / `-offset` | `var(--color-primary)` / `2px` / `2px` | Keyboard focus ring. |

> **The button has no colors of its own.** It carries the theme's `.iw-button--primary`, so it inherits the configured background, text color, radius, padding and hover effect — and stays consistent with every other button on the site. Restyle it through the theme's button settings, or by overriding `.iw-button--primary`.
>
> The bundle ships bare fallbacks inside `:where(.iw-embed__consent-button)` (specificity 0) so the button still looks like a button if a theme defines no "primary" style; they never win against a real `.iw-button--*` rule.
>
> To use another button style, override the partial and pass `consentButtonStyle` (e.g. `'secondary'`).

---

## Override examples

### Brand the consent placeholder

```css
.iw-embed__consent {
    --iw-embed-consent-bg: var(--color-primary-50);
    --iw-embed-consent-button-bg: var(--color-accent);
}
```

### Darken the placeholder visual for readability

```css
.iw-embed__consent {
    --iw-embed-consent-image-opacity: 0.15;
}
```

### Make a full-width embed taller on large screens only

```css
@media (min-width: 1024px) {
    .iw-block-iframe--fullwidth .iw-embed--h-custom {
        --iw-embed-height: 720px;
    }
}
```

### Round the embed without touching the block radius

```css
.iw-block-iframe .iw-embed {
    border-radius: var(--border-radius);
    overflow: hidden;
}
```
