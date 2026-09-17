# Cloudflare Turnstile

An opt-in anti-spam field for forms built with SuluFormBundle. Once enabled, editors pick
**Cloudflare Turnstile** in the form builder like any other field, and submissions without
a valid token are rejected server-side — no mail sent, nothing stored.

Turnstile is Cloudflare's captcha alternative: free, privacy-friendly, and invisible for
most visitors. The widget, the field type and the token verification are all the bundle's
own, which is what lets a project serving several sites give each one its own Cloudflare
account — see [Several sites, several accounts](#several-sites-several-accounts).

> **Start with the honeypot.** SuluFormBundle ships a honeypot that costs nothing and stops
> a good share of naive bots, but it is **off by default** (`sulu_form.honeypot.field` is
> `null`). Set it and this theme already hides the field for you — the form theme defines a
> `honeypot_row` block. It complements Turnstile rather than replacing it.
>
> ```yaml
> # config/packages/sulu_form.yaml
> sulu_form:
>     honeypot:
>         field: 'website_url'
> ```

---

## Install

Nothing to install beyond SuluFormBundle and a HTTP client, both of which a Sulu project
normally already has:

```bash
composer require symfony/http-client
```

Get a site key and a secret key from the Cloudflare dashboard
(*Turnstile → Add site*) and put them in `.env`:

```dotenv
TURNSTILE_KEY=0x4AAAAAAA...
TURNSTILE_SECRET=0x4AAAAAAA...
```

Finally, enable the field:

```yaml
# config/packages/itech_world_sulu_tailwind_theme.yaml
itech_world_sulu_tailwind_theme:
    turnstile:
        enabled: true
        site_key: '%env(TURNSTILE_KEY)%'
        secret_key: '%env(TURNSTILE_SECRET)%'
```

That is the only place to configure Turnstile.

### Several sites, several accounts

On a multi-site project each site can answer for its own Cloudflare account:

```yaml
itech_world_sulu_tailwind_theme:
    turnstile:
        enabled: true
        site_key: '%env(TURNSTILE_KEY)%'          # used by every site that overrides nothing
        secret_key: '%env(TURNSTILE_SECRET)%'

    webspaces:
        client-a:
            turnstile:
                site_key: '%env(TURNSTILE_CLIENT_A_KEY)%'
                secret_key: '%env(TURNSTILE_CLIENT_A_SECRET)%'
```

The widget renders with the key of the site being visited, and the token is verified with
that same site's secret — a token is only ever valid against the pair that issued it, so
crossing them would reject every visitor.

**Keys stay in environment variables**, never in the theme. The site key is public and
already ships in the HTML, but a secret placed in the theme would travel into the database,
into backups, and into the exported JSON of that theme.

`enabled` stays project-wide: it decides whether the field type is registered in the form
builder, which happens once for the whole admin. A site that overrides no key pair uses the
project one, so equipping a single site with its own account is enough.

> **Upgrading from 2.x:** the feature used to be backed by
> [`pixelopen/cloudflare-turnstile-bundle`](https://github.com/Pixel-Open/cloudflare-turnstile-bundle),
> which holds one key pair for the whole application and therefore cannot serve several
> sites. It is no longer used, and `composer remove pixelopen/cloudflare-turnstile-bundle`
> is safe. Keeping it installed is safe too: the bundle still feeds it the project-wide
> pair, since that package refuses to boot without one.

### Using the field

In the admin, open a form, add a field, and pick **Cloudflare Turnstile** in the *special*
group (next to the reCAPTCHA field). Only two settings, both optional:

| Setting | What it does |
|---------|--------------|
| **Width** | Column width in the form grid, like every other field |
| **Title** | A label above the widget. Usually best left empty: the widget already shows its own "verify you are human" wording |

There is intentionally no *required* toggle and no *short title* — see
[Design notes](#design-notes).

### In Twig template mode

The field above only exists inside SuluFormBundle forms. A form rendered by the block's
**Twig template mode** is hand-written, so it includes the widget itself:

```twig
{% include '@ItechWorldSuluTailwindTheme/forms/_turnstile.html.twig' with {
    colorScheme: colorScheme|default('auto')
} only %}
```

The partial reads the site key from this bundle's own `turnstile` configuration, so the
credential stays declared in one place - it is not passed in, and there is nothing to add to
the project's configuration. It renders nothing when the feature is off or unconfigured,
which makes the include safe to leave in a template whatever the environment. The optional
`attributes` parameter takes extra data attributes for the Turnstile options the partial does
not cover.

Only the **site key** is exposed: it ships in the HTML of every page carrying a widget, which
is what it is for. The secret key never leaves the server.

### Verifying the token yourself

In SuluFormBundle mode the token is verified for you. In Twig template mode it is not, and a
widget whose token nobody checks stops nothing at all - it only looks like it does.

Cloudflare puts the token in the `cf-turnstile-response` field of the POST. The shortest
path is the bundle's own constraint, on the property your DTO holds it in — it verifies
against the secret of the site being served, so it works the same on a multi-site project:

```php
use ItechWorld\SuluTailwindThemeBundle\Validator\Turnstile;

final class ContactRequest
{
    public function __construct(
        // …
        #[Turnstile]
        public string $cfTurnstileResponse = '',
    ) {
    }
}
```

Read it from the request under its real name, which is not a valid PHP property name:

```php
$token = (string) $request->request->get('cf-turnstile-response');
```

Outside a Symfony form, inject `TurnstileVerifier` and ask it directly:

```php
use ItechWorld\SuluTailwindThemeBundle\Service\TurnstileVerifier;

if (!$verifier->verify((string) $request->request->get('cf-turnstile-response'))) {
    // refuse the submission
}
```

Never do that check client-side.

---

## Local development and CI

Cloudflare publishes test keys that never call a real challenge. The pair below always
passes, which is what you want in dev and in a test suite:

| Key | Value |
|-----|-------|
| Site key | `1x00000000000000000000AA` |
| Secret key | `1x0000000000000000000000000000000AA` |

Two other site keys are useful when checking the rendering: `2x00000000000000000000AB`
always blocks, and `3x00000000000000000000FF` forces the interactive challenge, which is
the only way to see the full widget on screen. The matching "always fails" secret is
`2x0000000000000000000000000000000AA`.

These keys validate **everything**. They belong in `.env`, never in production.

### Keys in production, and the two ways it goes wrong silently

Both failures below are invisible locally - the keys sit in `.env` - and appear only once
deployed, on the environment where nobody is watching the logs. The theme names them rather
than rendering nothing.

**The site key never reaches the application** (the variable is named differently on the
host, or set empty). No widget renders, while the server-side check keeps running: the token
is always empty, so **every submission is refused**, with an error the visitor cannot act on.

- In `dev`: a `.iw-block-form__notice` says so, right where the widget would be.
- In `prod`: a warning is logged, once per request.
- `iw_sulu_tailwind_theme_turnstile_status()` returns `missing_key`.

**The test keys reach production.** A committed `.env` that carries Cloudflare's test values
is enough: nothing is overridden on the host, so the application boots happily with a
challenge that validates *every* visitor, robots included. The form works, the anti-spam is
decoration - which is worse than the previous case, because nothing looks wrong.

- In `prod`: a warning is logged, once per request.
- In `dev` and CI: nothing, this is the documented way to work locally.
- `iw_sulu_tailwind_theme_turnstile_status()` returns `test_key`.

Before deploying, check the four states apart:

| `turnstile.enabled` | Site key reaching the app | Status | What the visitor gets |
|---|---|---|---|
| `false` | anything | `off` | No widget, no check. |
| `true` | none or empty | `missing_key` | No widget, and every submission refused. |
| `true` | Cloudflare's test key | `test_key` | A widget that passes everyone. |
| `true` | the real key | `ready` | The challenge, doing its job. |

The check is worth running against the deployed environment rather than the config file: the
value only exists once the environment variables are resolved.

### Turning it off

```yaml
itech_world_sulu_tailwind_theme:
    turnstile:
        enabled: false   # the default
```

Disabled means disabled end to end: the field is not offered in the form builder, no widget
is rendered, and no token is verified. Existing forms that already hold a Turnstile field
keep working — the field simply disappears from them.

Enabling without declaring the keys fails at compile time rather than at the first
submission:

```
Cloudflare Turnstile is enabled but "site_key" and "secret_key" are not both configured
under "itech_world_sulu_tailwind_theme.turnstile".
```

That guard reads the configuration file, so `%env(TURNSTILE_KEY)%` always satisfies it: it
catches a forgotten declaration, not an environment variable that is empty on the server.
The second case surfaces at runtime as the `missing_key` status above.

A project that still has `pixelopen/cloudflare-turnstile-bundle` installed keeps booting
either way: that package marks its own `key` and `secret` as required and non-empty, so this
bundle keeps feeding it the project-wide pair, and Cloudflare's test keys as placeholders
while the feature is off.

---

## Appearance

The widget renders inside an iframe, so it cannot inherit the theme through CSS. It only
takes a light/dark hint, and the bundle computes it from the block the form sits in:

- the block variant's background color is resolved to its final hex value (the same one
  the theme compiler emits) and its luminance decides `light` or `dark`;
- a block with its background turned off follows the theme's page background instead;
- the `card` layout always gets `light` — it paints its own white surface;
- when no color can be resolved, the hint falls back to `auto` and the widget follows the
  visitor's `prefers-color-scheme`.

The language follows the form locale, so a French page gets a French widget.

The widget is wrapped in a `.iw-form__turnstile` element, which is the hook to size or
center it:

```css
.iw-form__turnstile {
    display: flex;
    justify-content: center;
}
```

To change the markup itself, override the `turnstile_widget` block of the form theme —
see [Forms — CSS API](css-api/forms.md).

---

## Design notes

**No *required* toggle.** The widget renders no input of its own: Cloudflare posts the
token as a separate `cf-turnstile-response` parameter. A `NotBlank` on the field could
therefore never pass and would make the form permanently unsubmittable. The token is always
verified when the field is present, which is what "required" would have meant anyway.

**No *short title*.** That property only ever labels a submitted value (a column in the
submissions list, a line in the notification mail). The field is `mapped => false`, so it
never produces one.

**Its own error message.** The violation uses the
`iw_sulu_tailwind_theme.turnstile_failed` key from this bundle's `validators` catalog.
Override it in your project like any translation:

```json
// translations/validators.fr.json
{
    "iw_sulu_tailwind_theme.turnstile_failed": "Votre message n'a pas pu être envoyé…"
}
```

**Fails closed.** If Cloudflare cannot be reached, the verification returns false and the
submission is rejected — the error is logged. A captcha that lets everything through when
the network hiccups protects nothing.

---

## See also

- [Form block](form-block.md) — the block that puts a form on a page
- [Forms — CSS API](css-api/forms.md) — every `iw-form__*` class and form theme block
