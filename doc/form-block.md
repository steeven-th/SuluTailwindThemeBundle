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

The box itself comes from the shared partial `forms/_success.html.twig`, which a form
written in Twig template mode includes the same way - see
[Showing the confirmation](#showing-the-confirmation). Neither mode owns a copy of that
markup.

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
build it with the same public classes the form theme uses - `iw-form iw-form__grid` on the
`<form>`, one `iw-form__col` per field. A full example is in
[Forms - CSS API → Custom form template](css-api/forms.md#custom-form-template-without-suluformbundle).

The bundle includes that template and stops there: the fields, the recipient and the
business rules belong to the project. The three sections below cover what does *not* belong
to it - the contract of the include, the shared confirmation box, and the two consequences
of this theme's page caching that make a naive submission handler work in `dev` and fail in
production.

### What the included template receives

The include deliberately keeps the block's context, so the template reads:

| Variable | Type | Value |
|----------|------|-------|
| `formIndex` | `int` | Rank of this form block in the page, starting at `1`. Prefix your HTML ids with it whenever the same template can appear twice on a page. |
| `colorScheme` | `string` | `light`, `dark` or `auto`: the surface the form sits on, computed from the block variant (the `card` style always says `light`, it paints its own surface). |
| `useSuluFormBundle` | `bool` | Always `false` in this mode. |
| `twigTemplate` | `string` | The path the editor entered, i.e. the template being rendered. |
| `form` | `mixed` | The SuluFormBundle form of the other mode, `null` here. |

Twig globals are available as everywhere else, `app` included - that is where the current
path, the query string and the flash messages come from. The **page's own variables are
not**: the block reaches the template through an `only` include, so `content`, `page` and
anything a page template exposes do not cross over. Everything else the form needs comes
from `app.request` or from your own controller.

Two form blocks pointing at the same template are a legitimate layout, and `formIndex` is
what keeps it valid HTML:

```twig
{% set uid = 'contact-' ~ formIndex|default(1) %}

<form id="{{ uid }}" method="post" action="{{ path('app_contact_send') }}" class="iw-form iw-form__grid">
    <div class="iw-form__col iw-form__col--half">
        <label for="{{ uid }}-email" class="iw-form__label iw-form__label--required">{{ 'Email'|trans }}</label>
        <input type="email" id="{{ uid }}-email" name="email" required class="iw-form__field">
    </div>
</form>
```

`|default(1)` keeps the template renderable when it is included directly from a project
template rather than from the block.

### Showing the confirmation

The confirmation box is a shared partial, so a form written in Twig ends up with exactly
the same markup, the same CSS API and the same dark variant as a SuluFormBundle one - and
follows them when they change:

```twig
{% include '@ItechWorldSuluTailwindTheme/forms/_success.html.twig' with {
    text: 'Thank you, your message has been sent.'|trans,
    colorScheme: colorScheme|default('auto'),
    id: 'contact-' ~ formIndex|default(1)
} only %}
```

| Parameter | Role |
|-----------|------|
| `text` | The message. Rendered `raw`, since the SuluFormBundle text comes from a rich text editor: escape it yourself if it ever carries visitor input. |
| `colorScheme` | `light` / `dark` / `auto`. `dark` adds the `--dark` modifier, so pass the block's own value straight through. |
| `id` | The element id, and the anchor a redirect can point at. Optional: omit it and no id is emitted. |

The bundle's own default message is available too, translated in the page locale, when the
project would rather not write one:

```twig
text: 'iw_sulu_tailwind_theme.form_success_default'|trans
```

Restyling goes through the CSS API (`--iw-form-success-*`, see
[Forms - CSS API → Success message](css-api/forms.md#success-message)). To change the
markup itself, override the partial at the standard bundle path:
`templates/bundles/ItechWorldSuluTailwindThemeBundle/forms/_success.html.twig`.

### Anti-spam

A form the bundle does not build cannot get the SuluFormBundle protections, but the two
that matter are available.

**A honeypot** costs three lines and stops the robots that fill in everything they find.
Hide it from sight, from the keyboard and from screen readers, then treat a submission that
filled it as a success without sending anything - answering with an error teaches the robot
which field to avoid next time:

```twig
<div hidden aria-hidden="true">
    <label for="{{ uid }}-website">{{ 'Do not fill in this field'|trans }}</label>
    <input type="text" id="{{ uid }}-website" name="website" tabindex="-1" autocomplete="off">
</div>
```

**Cloudflare Turnstile** is a form field in SuluFormBundle mode, which a hand-written form
cannot use. Include the partial instead, and the widget uses the key already configured for
the bundle - no second declaration:

```twig
{% include '@ItechWorldSuluTailwindTheme/forms/_turnstile.html.twig' with {
    colorScheme: colorScheme|default('auto')
} only %}
```

| Parameter | Role |
|-----------|------|
| `colorScheme` | `light` / `dark` / `auto`. The widget renders in an iframe and cannot inherit the theme CSS, it only takes this hint. Pass the block's own value. |
| `attributes` | Optional map of extra data attributes (`{ 'data-action': 'contact' }`) for the Turnstile options the partial does not cover. |

It renders nothing when Turnstile is off or unconfigured, so the include is safe to leave in
place. Setup and keys are in [Cloudflare Turnstile](turnstile.md).

> **The widget alone stops nothing.** Cloudflare answers it with a token in the
> `cf-turnstile-response` field of the POST, and your handler has to verify that token
> server-side - see [Verifying the token yourself](turnstile.md#verifying-the-token-yourself).
> A widget nobody checks is decoration.

### Handling the submission

#### The page is cached, and that changes the rules

Every page template shipped by the theme declares `<cacheLifetime>604800</cacheLifetime>` -
seven days of HTTP proxy cache (the article listing page uses one hour). It is what makes
the theme fast, and it has two consequences a contact form has to be built around. Both are
invisible locally, where the cache is off, and both surface in production only:

- **A session-bound CSRF token is unusable.** The token in the HTML is the one of the first
  visitor who triggered the render. Every later visitor is served that same token from the
  cache, and their session does not match it: *every* submission fails, with no message
  that makes sense.
- **A redirect to the bare page URL is served from the cache.** The proxy answers with the
  stored copy, so neither the confirmation nor the errors are ever rendered. The visitor
  finds the empty form again and sends it a second time.

The bundle hits the second one in SuluFormBundle mode too, and answers it the same way:
`FormSubmissionRedirectSubscriber` turns Sulu's `?send=true` redirect into
`?send=true&iw_form=12#iw-form-12`.

Both are handled for you by `FormSubmissionHandler`, below. Writing the handling yourself is
still perfectly supported - see [Doing it by hand](#doing-it-by-hand) for what it takes.

#### Hand the submission to the bundle

Three files, none of which contains anything about tokens, redirects or caches.

**The DTO** - your fields and their rules, and the only place messages are written:

```php
// src/Form/ContactRequest.php
namespace App\Form;

use Symfony\Component\Validator\Constraints as Assert;

final class ContactRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 100)]
        public string $name = '',

        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email = '',

        #[Assert\NotBlank]
        #[Assert\Length(min: 10, max: 5000)]
        public string $message = '',

        #[Assert\IsTrue(message: 'Please accept the terms.')]
        public bool $consent = false,
    ) {
    }
}
```

The handler fills it from the POST fields carrying the same names. Strings, booleans,
integers and floats are mapped; a checkbox that was not ticked posts nothing and reads as
`false`. Anything richer stays yours to build.

**The controller** - your route, your business, five lines:

```php
#[Route('/contact/send', name: 'app_contact_send', methods: ['POST'])]
public function send(Request $request, FormSubmissionHandler $handler): RedirectResponse
{
    return $handler->handle($request, ContactRequest::class, $this->mailer->send(...));
}
```

The callable receives the **valid** DTO. An exception it throws is logged and reported to
the visitor as a technical failure - except in `dev`, where it surfaces instead of hiding
behind a polite message.

**The template** - one include for the hidden fields, one function for the outcome:

```twig
{% set index = formIndex|default(1) %}
{% set uid = 'contact-' ~ index %}

{% if iw_sulu_tailwind_theme_form_status(index) == 'sent' %}
    {% include '@ItechWorldSuluTailwindTheme/forms/_success.html.twig' with {
        text: 'iw_sulu_tailwind_theme.form_success_default'|trans,
        colorScheme: colorScheme|default('auto'),
        id: uid
    } only %}
{% else %}
    {# Flashes are consumed as they are read: read them once, at the top. #}
    {% set errors = app.flashes('iw_form_errors')|first|default({}) %}
    {% set values = app.flashes('iw_form_values')|first|default({}) %}
    {% set globalError = app.flashes('iw_form_error')|first|default('') %}

    <form method="post" action="{{ path('app_contact_send') }}" id="{{ uid }}" class="iw-form iw-form__grid">
        {% include '@ItechWorldSuluTailwindTheme/forms/_fields.html.twig' with { formIndex: index } only %}

        {% if globalError %}
            <div class="iw-form__col iw-form__col--full">
                <ul class="iw-form__errors text-sm" role="alert"><li>{{ globalError }}</li></ul>
            </div>
        {% endif %}

        <div class="iw-form__col iw-form__col--half">
            <label for="{{ uid }}-email" class="iw-form__label iw-form__label--required">{{ 'Email'|trans }}</label>
            <input type="email" id="{{ uid }}-email" name="email" required
                   value="{{ values.email|default('') }}"
                   class="iw-form__field{{ errors.email is defined ? ' iw-form__field--error' }}"
                   {% if errors.email is defined %}aria-invalid="true" aria-describedby="{{ uid }}-email-error"{% endif %}>
            {% if errors.email is defined %}
                <ul class="iw-form__errors mt-1.5 text-sm" id="{{ uid }}-email-error"><li>{{ errors.email }}</li></ul>
            {% endif %}
        </div>

        {# … the other fields, same shape … #}

        <div class="iw-form__col iw-form__col--full iw-form__actions pt-2">
            <button type="submit" class="iw-form__submit iw-button--variant">{{ 'Send'|trans }}</button>
        </div>
    </form>
{% endif %}
```

`_fields.html.twig` renders the CSRF token, the return path, the block index, the honeypot
and the anchor the redirect points at. `iw_sulu_tailwind_theme_form_status(index)` returns
`sent`, `error`, or `null` when this block was not the one submitted - so a page holding two
forms only confirms the right one.

#### What each side does

| Done by the bundle | Left to you |
|--------------------|-------------|
| CSRF token, declared stateless so a cached page never serves a stale one | The fields and their constraints, in the DTO |
| Honeypot, answered as a success so robots learn nothing | The template and its markup |
| Filling the DTO from the POST body | What happens on success: mail, API call, database |
| Validation, and one error message per field | The route and its URL |
| Redirect with a query parameter and an anchor, and a return path that cannot leave the site | The confirmation wording |
| Errors and submitted values carried back as flash messages | |

Reading a flash starts the session, which switches the response to `Cache-Control: private`.
The page with an error is therefore never stored by the proxy, while the plain page URL keeps
being cached for everyone.

Two dependencies are only needed once you handle a submission this way, and the handler names
the missing one rather than fataling:

```bash
composer require symfony/validator symfony/security-csrf
```

#### What it deliberately does not do

Stored submissions, file uploads, conditional fields, rate limiting, an acknowledgement mail
to the visitor, field labels and error messages managed from the admin. For any of those, the
answer is **SuluFormBundle** - it is a form engine, this is the plumbing around a form you
wrote yourself.

Behind a reverse proxy, `trusted_proxies` must be set. The stateless token is validated by
comparing the request's own scheme and host with the `Origin` header: without trusted proxies
Symfony ignores `X-Forwarded-Proto`, believes it answers in `http://` while the site is served
in `https://`, and rejects every submission.

#### Doing it by hand

Nothing forces you through the handler - the block includes a template, and what posts where
is yours to decide. The shape that works:

```
POST /contact/send        your own route, never the page URL
  → redirect to /the-page?contact=sent#contact-1
  → GET with a query parameter the cache has no entry for: the application runs,
    and renders the confirmation
```

The query parameter is not a convenience, it is what guarantees the request reaches the
application at all. Add the anchor too, so a visitor lands on the confirmation instead of at
the top of a long page.

The CSRF token has to be validated without a session, which Symfony does on the origin of the
request (`Sec-Fetch-Site`, then `Origin` / `Referer`):

```yaml
# config/packages/framework.yaml
framework:
    csrf_protection:
        # This token is validated without a session: the form lives in a cached
        # page, where a session-bound token would be the first visitor's.
        stateless_token_ids: ['contact']
```

Symfony already ships `submit`, `authenticate` and `logout` in that list, so naming the token
`submit` needs no configuration at all. Every other token of the application, the admin login
form included, keeps its session behaviour.

Then the controller, with the parts that are easy to get subtly wrong:

```php
#[Route('/contact/send', name: 'app_contact_send', methods: ['POST'])]
public function send(Request $request): RedirectResponse
{
    // The page to return to comes from a hidden field, so from the visitor:
    // only an absolute path of this site is accepted. Browsers read `//host`
    // and `/\host` as external URLs, hence the second and third tests.
    $target = (string) $request->request->get('_redirect');
    if (!str_starts_with($target, '/') || str_starts_with($target, '//') || str_starts_with($target, '/\\')) {
        $target = '/';
    }

    // A plain cast, not getInt(): that one answers a tampered hidden field with
    // a 400, for a value that only ever builds an anchor.
    $anchor = '#contact-' . max(1, (int) $request->request->get('_form_index', 1));

    if (!$this->isCsrfTokenValid('contact', (string) $request->request->get('_csrf_token'))) {
        $this->addFlash('contact_error', 'Your session expired. Please send the message again.');

        return new RedirectResponse($target . '?contact=error' . $anchor);
    }

    // Honeypot: a hidden field a human never fills in. Answered as a success,
    // since an error would tell the robot which field to avoid next time.
    if ('' !== (string) $request->request->get('website')) {
        return new RedirectResponse($target . '?contact=sent' . $anchor);
    }

    $result = $this->handler->handle($request);   // validation + mail, your code

    if ($result->isSuccessful) {
        return new RedirectResponse($target . '?contact=sent' . $anchor);
    }

    $this->addFlash('contact_errors', $result->fieldErrors);
    $this->addFlash('contact_values', $result->values);

    return new RedirectResponse($target . '?contact=error' . $anchor);
}
```

The template then posts the hidden fields the controller reads, and renders the errors and
values back from the flashes, exactly as in the section above.

#### Checklist

- [ ] The `<form>` posts to a route of your own, never to the page URL.
- [ ] The redirect back always carries a query parameter, on success *and* on error.
- [ ] The CSRF token is validated without a session (automatic with `FormSubmissionHandler`).
- [ ] `trusted_proxies` is set if a reverse proxy sits in front of the application.
- [ ] Ids are prefixed with `formIndex`.
- [ ] The confirmation comes from `forms/_success.html.twig`, not from a copy.
- [ ] Tested on a page whose cache is warm, not only in `dev`.

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
