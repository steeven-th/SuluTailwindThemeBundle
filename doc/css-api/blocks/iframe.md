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
| `--iw-embed-consent-bg` | `var(--color-surface, #f3f4f6)` | Placeholder background. |
| `--iw-embed-consent-text` | `var(--color-surface-foreground, var(--color-text))` | Placeholder text color. |
| `--iw-embed-consent-padding` | `1.5rem` | Inner padding. |
| `--iw-embed-consent-gap` | `1rem` | Space between message and button. |
| `--iw-embed-consent-max-width` | `36rem` | Max width of the message column. |
| `--iw-embed-consent-image-opacity` | `0.35` | Opacity of the background visual. |
| `--iw-embed-consent-text-size` | `0.9375rem` | Message font size. |
| `--iw-embed-consent-text-line-height` | `1.5` | Message line height. |
| `--iw-embed-consent-button-bg` | `var(--color-primary)` | Button background. |
| `--iw-embed-consent-button-text` | `var(--color-surface-on-accent, #fff)` | Button label color. |
| `--iw-embed-consent-button-radius` | `var(--border-radius, 0.375rem)` | Button radius. |
| `--iw-embed-consent-button-padding-y` / `-x` | `0.625rem` / `1.25rem` | Button padding. |
| `--iw-embed-consent-button-weight` | `600` | Button font weight. |
| `--iw-embed-consent-button-hover-opacity` | `0.85` | Button opacity on hover. |
| `--iw-embed-consent-focus-color` / `-width` / `-offset` | `var(--color-primary)` / `2px` / `2px` | Keyboard focus ring. |
| `--iw-embed-consent-transition-duration` | `200ms` | Hover transition. |

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
