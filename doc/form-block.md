# Form block

The `form` block puts a contact form on a page. It has two modes, and the admin form
only offers the first one when SuluFormBundle is installed.

| Mode | When to use | What the editor picks |
|------|-------------|-----------------------|
| **SuluFormBundle** | Forms built and managed in the admin, with stored submissions and notification mails | A form, from a dropdown |
| **Twig template** | A form the project renders itself (custom controller, third-party service, newsletter embed) | A template path |

Both modes share the block's layout options — `centered`, `card`, `split` — and its
info column widgets (text, image, location map).

---

## SuluFormBundle mode

### Install

```bash
composer require sulu/form-bundle
```

That is the whole setup. The bundle detects SuluFormBundle and, from then on:

- the block's admin form gains the **Use SuluFormBundle** toggle and the form dropdown
  (without the bundle, the block only offers the Twig template path);
- the selected form renders through the bridge template shipped at
  `@ItechWorldSuluTailwindTheme/forms/_sulu_form.html.twig`;
- the bundle's **form theme** is applied to it, so every field comes out with the
  `iw-form__*` classes and follows the active block variant's colors.

Nothing has to be created in the project. The bridge template is included only when
SuluFormBundle is present — Twig never compiles a template it does not include, so the
form helpers it calls cannot fail on a project without the bundle.

### What `single_form_selection` gives you

In Sulu 3, `SingleFormSelectionPropertyResolver` resolves the field to an **already-built
Symfony `FormView`** — not an id. Passing it to `sulu_form_get_by_id()` fails:

```
getFormById(): Argument #1 ($id) must be of type int, Symfony\Component\Form\FormView given
```

The bridge template receives it as `formView` and renders it directly. A bare numeric id
is still accepted and resolved through `sulu_form_get_by_id()`, for content stored by an
earlier version.

### Anti-spam

SuluFormBundle ships no active protection out of the box: its honeypot defaults to `null`,
and its reCAPTCHA field only registers when the Google EWZ bundle is installed. This bundle
adds an opt-in **Cloudflare Turnstile** field, and the form theme already hides the honeypot
field for you — see [Cloudflare Turnstile](turnstile.md).

### After a successful submission

SuluFormBundle answers a valid submission with a redirect to `?send=true` and stores a
**success text per locale** on the form (admin → the form's settings), but exposes neither
to Twig. The bundle wires the two together: on that follow-up request the block renders the
success text **in place of the form**, in a `.iw-form-success` box.

| Case | What the visitor gets |
|------|----------------------|
| Success text filled in | That text, rendered as rich text, in the page locale |
| Success text empty | A translated default (`iw_sulu_tailwind_theme.form_success_default`) |
| Submission invalid | The form again, with its errors — no success box |

Two forms on the same page each confirm on their own: the bundle's
`FormSubmissionRedirectSubscriber` completes Sulu's redirect with the id of the form that
was posted (`?send=true&iw_form=12#iw-form-12`), and the anchor scrolls the visitor down to
the confirmation rather than leaving them at the top of a long page. The **same** form in
two blocks confirms in both — both blocks POST the same id, and nothing in the request tells
them apart.

Overriding the default message project-wide is a translation override:

```json
// translations/messages.fr.json
{
    "iw_sulu_tailwind_theme.form_success_default": "C'est noté, merci !"
}
```

A project that overrides the bridge template must keep the
`iw_sulu_tailwind_theme_form_submitted()` branch to preserve this — see
[Twig Reference](twig-reference.md).

### The same form in several blocks

A contact form at the top *and* at the bottom of a long page is a legitimate layout, and it
works: put two form blocks on the page and pick the same form in both.

This needs handling because Sulu hands every block the **same** `FormView` instance, and
Symfony refuses to render one twice — it would emit duplicate HTML ids and break every
`for` attribute on the page. The bridge template detects it and renders an independent copy
with suffixed ids (`dynamic_form1_email`, then `dynamic_form1_email-2`), leaving the POST
field names untouched.

Two consequences worth knowing:

- both copies submit to the same form, because they *are* the same form;
- after a failed submission, every copy on the page shows the error — the browser sends no
  hint about which one was filled in.

A project that overrides the bridge template needs
`{% set suluForm = iw_sulu_tailwind_theme_reusable_form(suluForm) %}` before rendering to
keep this behaviour.

### Customising the rendering

Override the bridge at the standard Symfony bundle path:

```
templates/bundles/ItechWorldSuluTailwindThemeBundle/forms/_sulu_form.html.twig
```

A project-level `templates/forms/_sulu_form.html.twig` also still takes precedence when it
exists — that was the required setup in earlier versions, so those projects keep working.
It receives `formId` alongside `formView` for the same reason. Prefer `formView` in new code.

To keep the theme but change a single field's markup, override a block of the form theme
instead — see [Forms — CSS API](css-api/forms.md).

---

## Twig template mode

Turn **Use SuluFormBundle** off and enter a template path, relative to the project's
`templates/` directory:

```
forms/contact.html.twig
```

The bundle does not ship this template: it is your form. To match the rest of the site,
build it with the same public classes the form theme uses — `iw-form iw-form__grid` on the
`<form>`, one `iw-form__col` per field. A full example is in
[Forms — CSS API → Custom form template](css-api/forms.md#custom-form-template-without-suluformbundle).

---

## When the block renders nothing

A block that renders nothing used to do so in complete silence. It now explains itself in
the `dev` environment, in a `.iw-block-form__notice` box:

| Situation | What you see in `dev` |
|-----------|----------------------|
| SuluFormBundle mode, bundle not installed | A note pointing at `composer require sulu/form-bundle` |
| SuluFormBundle mode, no form selected | A note asking to pick a form in the admin |
| Twig mode, template path not found | The path that was looked up, so a typo is obvious |
| Twig mode, no path entered | A note asking for a path |

In `prod` the block stays empty — a visitor never sees these. The notice inherits the
theme's text colors and can be restyled through `--iw-block-form-notice-*`.

---

## Testing a form locally

If the linked page of a `sulu-link` in a field label is not published, Sulu strips the tag
and keeps only its text. A consent label showing no `<a>` therefore does not mean the label
is broken — publish the target page before judging the rendering.

---

## See also

- [Cloudflare Turnstile](turnstile.md) — the opt-in anti-spam field, its keys and its
  light/dark handling
- [Forms — CSS API](css-api/forms.md) — every `iw-form__*` class, the `--iw-form-*`
  variables, the combobox and file-input widgets, and the custom-template example
- [Form block wrapper — CSS API](css-api/blocks/form.md) — the surrounding layout
  (`centered` / `card` / `split`) and its info column
