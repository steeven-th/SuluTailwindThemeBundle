# Code block — security model

The code block lets an editor paste raw HTML/JavaScript to drop a third-party widget onto a page. That is genuinely useful, and it is also the single most dangerous field a CMS can expose. This page states plainly what the bundle does about it, what it does not, and what you are accepting when you change the defaults.

---

## The starting point: Sulu has no per-block permission

There is no way, in Sulu, to say "only administrators may use this block type". Anyone allowed to edit a page can add any block declared in its template. So the question is not *who* may use the block — it is **where the decision to execute pasted code is taken**.

The bundle's answer: that decision belongs to the developer, in configuration, never to the editor in a form.

---

## Default: everything runs sandboxed

Out of the box, pasted markup is placed in the `srcdoc` of an `<iframe sandbox="allow-scripts">`. Without `allow-same-origin`, the browser gives that document an **opaque origin**. Concretely, the pasted code:

| | |
|---|---|
| ✅ can | run its own JavaScript, fetch its own resources, render its own UI |
| ❌ cannot | read or modify the hosting page's DOM |
| ❌ cannot | read your cookies or `localStorage` |
| ❌ cannot | steal an admin session from the preview |
| ❌ cannot | navigate or redirect the page around it |

A stored XSS in this block is confined to a rectangle.

### What the sandbox costs, honestly

Sandboxing is not free, and pretending otherwise leads to support tickets:

- **A widget that must act on the whole page will not work.** Chat bubbles (Crisp, Intercom), analytics tags, and anything that positions itself `fixed` over the site are incompatible by design — they need the page, and the page is exactly what the sandbox withholds.
- **Cookies and `localStorage` are unavailable** in an opaque origin. Widgets that persist state across visits may misbehave.
- **Two problems the bundle solves for you**, which are usually why people give up on sandboxing:
  - *Unstyled output* — the theme stylesheet is linked into the sandboxed document (a frame with an opaque origin can still fetch subresources by absolute URL), so the widget inherits your colors and fonts. Toggle: **Apply the theme styles**.
  - *Fixed height* — since the bundle writes the sandbox document, it injects a `ResizeObserver` that posts the content height to the parent, where the `embed_resize` controller applies it. Sizing mode **Automatic** uses it; the parent authenticates the message by comparing `event.source` with its own frame's `contentWindow`, because an opaque-origin frame reports its origin as the string `"null"` and origin filtering would be worthless.

---

## Opting out: `allow_unsandboxed`

When a widget genuinely needs the page, a project can enable the escape hatch:

```yaml
itech_world_sulu_tailwind_theme:
    blocks:
        code:
            allow_unsandboxed: true
```

This does **not** disable the sandbox. It makes a per-block checkbox — *Run without isolation* — appear in the admin. Until then the checkbox does not exist in the form at all: a different XML file is registered, so an editor is never shown a decision the project has not made.

### What you accept by enabling it

Be explicit about this before turning it on:

1. **On the public site**, an editor can execute arbitrary JavaScript for every visitor of that page.
2. **In the admin**, Sulu's preview renders the page in a same-origin iframe. Pasted script therefore runs **in the administration context** as soon as anybody previews that page. An editor can, in practice, capture the session of an administrator who previews their draft.

Which amounts to: **with `allow_unsandboxed: true`, anyone who can edit a page is effectively an administrator of the site.** That may be perfectly fine — a solo site, an agency where every editor is staff. It is not fine on a site with external contributors.

---

## The rule that ties it together

> An editor-facing setting may only ever **add** restriction, never remove it.

Config is the ceiling; stored content can only sit under it. `CodeBlockPolicy` enforces this, and it is covered by tests:

- Without the opt-in, a stored `unsandboxed: true` — left over from a config that has since been turned off, or written by an import — **is ignored**. The block returns to the sandbox immediately.
- With the opt-in, the checkbox only *offers* the choice; blocks that did not tick it stay sandboxed.

Turning `allow_unsandboxed` back to `false` is therefore a safe, immediate rollback across all existing content. No migration, no sweep of stored blocks.

---

## Other guards

- **The field is a `text_area`, never a `text_editor`.** A rich-text editor would reformat and strip the pasted markup, silently corrupting widget code.
- **Length limit** (`CodeBlockPolicy::MAX_LENGTH`, 20 000 characters). A mis-paste — a whole page, a base64 blob — is dropped rather than shipped on every render. In `dev` a notice explains the drop; in production nothing is rendered.
- **Consent** — the code block supports the same consent modes as the iframe block. With consent required, neither `src` nor `srcdoc` is written into the DOM, so the pasted markup does not run and does not call its third party until the visitor agrees. See [`consent.md`](./consent.md).
- **CSP** — if your site sends a Content-Security-Policy, inline scripts in raw mode are blocked unless you allow them. That is a feature, not a bug: it is a second line of defence you control.

---

## Choosing

| Situation | Recommendation |
|---|---|
| Embedding a booking, form or review widget | Default (sandboxed). Nothing to configure. |
| Widget renders unstyled | Keep the sandbox, leave **Apply the theme styles** on. |
| Widget cut off or over-tall | Keep the sandbox, use the **Automatic** sizing mode. |
| Chat bubble, analytics tag, page-wide script | These need `allow_unsandboxed: true`. Consider whether a proper integration (a Twig template, a bundle) is not the better answer — the code block is a convenience, not an architecture. |
| Site with external or untrusted editors | Leave the default. Do not enable the opt-in. |
