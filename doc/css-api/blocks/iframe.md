# Block: iframe — CSS API

Embeds an external page (scheduling widget, video, map, third-party form) with two layout styles: inside the container (`--default`) or edge to edge (`--fullwidth`).

> There is deliberately no card style: an embed fills its whole box, so a card surface would never be visible behind it and only a hairline border would show. Use `cardRadius` on the default style to round the frame instead.

The `.iw-embed` component is shared with the sandboxed mode of the code block, so any override written here applies to both.

> Conventions: strict BEM, `iw-` prefix. See [`../../css-conventions.md`](../../css-conventions.md).

---

## Security model

Worth knowing before overriding anything, because part of it is enforced in the markup:

- **The URL is validated server-side** (`EmbedUrlValidator`): `https` only, no credentials, and an optional host allowlist from the bundle config. An invalid URL renders **nothing** — no frame, no error, no broken layout.
- **`sandbox` never includes `allow-top-navigation`**, in any mode: an embed must not be able to redirect the page on its own. A *Allow the page to be redirected* checkbox adds `allow-top-navigation-by-user-activation`, which permits it **only in response to a real user gesture** — required by booking engines and search forms that send the visitor to their results page.
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
| `.iw-embed__consent-details` | Line under the message, the address of a waiting map. |
| `.iw-embed__consent-provider` | Who receives the data and under which policy, from the [third-party services inventory](../../consent.md#the-third-party-services-inventory). Absent for a service nothing describes. |
| `.iw-embed__consent-company` / `.iw-embed__consent-policy` | The company, and the link to its privacy policy (new tab). |
| `.iw-embed__consent-button` | Accept / open-preferences button. |
| `.iw-embed__consent--compact` | Set by the `consent` controller when the full panel overflows its box. Only the summary, the button and **Learn more** stay ([why](../../consent.md#small-media-boxes-the-compact-panel)). |
| `.iw-embed__consent-summary` | "Content provided by …", shown in the compact panel only. |
| `.iw-embed__consent-more` | **Learn more**, compact panel only. Opens the details dialog. |
| `.iw-embed__consent-dialog` | Modal `<dialog>` with the whole notice and the accept button. |
| `.iw-embed__consent-dialog-title` / `-actions` / `-close` | Its title, its button row, and its **Close** button. |

### Facade

The poster image of a video widget, in front of a player that only loads on
click ([why](../../consent.md#the-poster-image-as-a-facade)).

| Class | Role |
|-------|------|
| `.iw-embed__facade` | The `<button>` covering the box. Hidden until the content is allowed, then until it is clicked. |
| `.iw-embed__facade-picture` | The editor's image, filling the box (`object-fit: cover`). |
| `.iw-embed__facade-play` | The play mark, centred. Takes no clicks of its own. |

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
| `--iw-embed-consent-image-opacity` | `0.35` | Opacity of the background visual, the block's or the one set for the site in **Default settings > Images > Consent**. |
| `--iw-embed-consent-text-size` | `0.9375rem` | Message font size. |
| `--iw-embed-consent-text-line-height` | `1.5` | Message line height. |
| `--iw-embed-consent-details-size` / `-opacity` | `0.875rem` / `0.8` | Line under the message, the address of a waiting map. |
| `--iw-embed-consent-provider-size` / `-opacity` | `0.8125rem` / `0.8` | Line naming the company and its policy. |
| `--iw-embed-consent-panel-bg` | the placeholder background at 88% | Panel behind the text and button, over a background picture only. |
| `--iw-embed-consent-panel-padding` / `-radius` | `0.75rem 1rem` / `var(--border-radius, 0.5rem)` | Same panel. |
| `--iw-embed-consent-compact-padding` / `-gap` | `0.75rem` / `0.5rem` | Compact panel spacing. |
| `--iw-embed-consent-summary-size` / `-weight` | `0.875rem` / `600` | "Content provided by …" line. |
| `--iw-embed-consent-more-size` | `0.8125rem` | **Learn more** link. |
| `--iw-embed-consent-dialog-bg` / `-text` | the placeholder colours | Details dialog colours. |
| `--iw-embed-consent-dialog-max-width` / `-padding` / `-radius` | `32rem` / `1.5rem` / `var(--border-radius, 0.5rem)` | Details dialog box. |
| `--iw-embed-consent-dialog-shadow` / `-backdrop` | soft shadow / `rgb(0 0 0 / 0.5)` | Its shadow, and the veil over the page. |
| `--iw-embed-consent-dialog-title-size` | `1.125rem` | Its title. |
| `--iw-embed-consent-focus-color` / `-width` / `-offset` | `var(--color-primary)` / `2px` / `2px` | Keyboard focus ring, the facade's included. |

### Facade

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-embed-facade-bg` | `#000` | Behind the image, while it loads. |
| `--iw-embed-facade-play-size` | `clamp(3rem, 15%, 4.5rem)` | Diameter of the play mark, a share of the video width. |
| `--iw-embed-facade-play-radius` | `50%` | Its corners, `0.75rem` for a rounded square. |
| `--iw-embed-facade-play-bg` / `-bg-hover` | `rgb(0 0 0 / 0.6)` / `var(--color-primary)` | Its background, at rest and on hover or focus. |
| `--iw-embed-facade-play-color` | `#fff` | The triangle. |
| `--iw-embed-facade-play-hover-scale` | `1.06` | Growth on hover or focus. None under `prefers-reduced-motion`. |
| `--iw-embed-facade-transition-duration` | `150ms` | Hover transition. |

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
