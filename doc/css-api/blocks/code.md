# Block: code — CSS API

Pasted HTML/JavaScript embed (third-party widget) with two layout styles: inside the container (`--default`) or edge to edge (`--fullwidth`).

> **Read [`../../code-block-security.md`](../../code-block-security.md) first.** How this block renders depends on an execution mode resolved server-side, and that changes what you can style.

> Conventions: strict BEM, `iw-` prefix. See [`../../css-conventions.md`](../../css-conventions.md).

---

## Two very different DOMs

| Mode | What lands in the page | What you can style |
|---|---|---|
| **Sandboxed** (default) | An `<iframe>` reusing the shared `.iw-embed` component | The frame and its box. **Not its contents** — they live in a separate document. Use the *Apply the theme styles* option so the widget inherits your tokens. |
| **Raw** (opt-in only) | The pasted markup, inlined in `.iw-block-code__raw` | Everything, but the bundle deliberately styles nothing inside it. |

In sandboxed mode, everything under [`iframe.md`](./iframe.md) applies — `.iw-embed`, sizing classes, consent placeholder, and their variables are shared.

---

## Classes

| Class | Role |
|-------|------|
| `.iw-block-code` | Root wrapper. Hook only. |
| `.iw-block-code--default` | Embed inside the page container. |
| `.iw-block-code--fullwidth` | Edge-to-edge embed. |
| `.iw-block-code__raw` | Container for inlined markup (raw mode). `display: flow-root` only. |
| `.iw-block-code__notice` | Dev-only notice when a snippet exceeded the length limit. Never rendered in production. |
| `.iw-embed--auto` | Self-sizing box driven by the height reported from the sandbox. |

### Why `.iw-block-code__raw` carries almost no styling

It sets `display: flow-root` and nothing else. That single rule contains the widget's floats so they cannot drag the rest of the page along — a real failure mode with pasted markup. Beyond that, imposing typography or spacing on third-party HTML would fight the widget's own stylesheet and produce results nobody can predict. The block stays out of the way on purpose.

---

## CSS variables

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-embed-height` | `300px` | Sandbox height. In **Automatic** mode it is overwritten at runtime by the `embed_resize` controller with the reported content height. |
| `--iw-embed-auto-transition-duration` | `150ms` | Smoothing applied when the reported height changes, so a widget settling in does not snap. |
| `--iw-block-code-notice-padding` | `1rem` | Dev notice padding. |
| `--iw-block-code-notice-border` | `var(--color-border, #e5e7eb)` | Dev notice border. |
| `--iw-block-code-notice-radius` | `var(--border-radius, 0.375rem)` | Dev notice radius. |
| `--iw-block-code-notice-text` | `var(--color-secondary-600, inherit)` | Dev notice text color. |
| `--iw-block-code-notice-size` | `0.875rem` | Dev notice font size. |

The consent placeholder and frame variables are shared with the iframe block — see [`iframe.md`](./iframe.md#css-variables).

---

## Override examples

### Give the sandbox a minimum height while the widget loads

```css
.iw-block-code .iw-embed--auto {
    --iw-embed-height: 480px;
}
```

### Remove the resize easing (instant jumps)

```css
.iw-block-code .iw-embed--auto {
    --iw-embed-auto-transition-duration: 0ms;
}
```

### Constrain a raw widget's width without touching its internals

```css
.iw-block-code__raw {
    max-width: 42rem;
    margin-inline: auto;
}
```

### Style the widget itself (sandboxed mode)

You cannot from the page stylesheet — the widget is in another document. Keep **Apply the theme styles** enabled: the theme stylesheet is linked into the sandbox, so your CSS custom properties and any rule you add to the theme CSS reach the widget.
