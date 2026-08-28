# Extending the theme configuration

Real projects need settings the bundle does not ship: a navbar height, a
box-shadow preset, an extra brand color slot. This page covers how to add such a
setting to the theme admin, persist it, and turn it into CSS.

Three pieces are involved, and you rarely need all three:

1. **A form field** — declared in your project, merged into the bundle's admin form.
2. **A `custom` namespace** — where the value is stored, no migration required.
3. **A compile listener** — optional, only if the value has to reach the stylesheet.

## 1. Declaring the field

Sulu merges admin forms by key. Drop an XML file in your project's
`config/forms/` reusing the key of the form you want to extend, and your
properties are appended to it. Project directories are read last, so you can
also override an existing property by redeclaring its name.

```xml
<?xml version="1.0" ?>
<form xmlns="http://schemas.sulu.io/template/template"
      xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:schemaLocation="http://schemas.sulu.io/template/template http://schemas.sulu.io/template/form-1.0.xsd">

    <key>iw_theme_config_menu</key>

    <properties>
        <property name="menuConfig_custom_navbarHeight" type="number" colspan="6">
            <meta>
                <title>Navbar height (px)</title>
            </meta>
            <params>
                <param name="default_value" value="64"/>
                <param name="min" value="40"/>
                <param name="max" value="200"/>
            </params>
        </property>
    </properties>
</form>
```

The theme forms are `iw_theme_config_details`, `iw_theme_config_colors`,
`iw_theme_config_typography`, `iw_theme_config_buttons`,
`iw_theme_config_defaults`, `iw_theme_config_variants`, `iw_theme_config_menu`,
`iw_theme_config_footer`, `iw_theme_config_components` and
`iw_theme_config_articles`.

## 2. Naming the field so it persists

**The prefix is not cosmetic.** The bundle validates incoming theme data against
a closed list of keys, so a property it does not recognise is dropped on save -
the field renders, accepts input, and loses it silently. The `custom_` segment
is what opts your field into an open namespace.

There is one namespace per storage column:

| Prefix | Stored in | Read from Twig |
|--------|-----------|----------------|
| `custom_*` | `tokens.custom` | `iw_sulu_tailwind_theme_tokens().custom` |
| `menuConfig_custom_*` | `menuConfig.custom` | `iw_sulu_tailwind_theme_menu_config().custom` |
| `footerConfig_custom_*` | `footerConfig.custom` | `iw_sulu_tailwind_theme_footer_config().custom` |

Pick the one matching the form you extended: a field on the menu form belongs in
`menuConfig_custom_*`. Everything else - colors, typography, buttons, defaults,
components, articles - lives in the tokens column, so use plain `custom_*`.

These are JSON columns, so **no migration is ever needed**, whatever you add.

### What a value may hold

Values are checked before storage. Scalars, `null`, and one level of array all
pass, which covers every admin field type - including a media selection
(`{id: 12}`) and a repeatable of scalars.

Rejected, and dropped without failing the editor's save:

- keys that are not plain identifiers (`navbar-height`, `1height`, `navbar.height`)
- arrays nested more than one level deep
- strings over 4096 characters, lists over 256 items, more than 128 fields per namespace

The identifier rule exists so values stay reachable with Twig's dot notation. If
a field never shows up in storage, its name is the first thing to check.

## 3. Reading the value in Twig

Nothing to wire: the three columns are already exposed.

```twig
{% set menu = iw_sulu_tailwind_theme_menu_config() %}
{% set height = menu.custom.navbarHeight|default(64) %}
```

Always use `|default()`. A theme saved before you added the field has no value
for it, and neither does a freshly installed preset.

## 4. Contributing CSS

For anything beyond a Twig value - a custom property, a rule keyed on a setting -
subscribe to `ThemeCompileEvent`. It fires once per theme compilation.

```php
namespace App\EventSubscriber;

use ItechWorld\SuluTailwindThemeBundle\Event\ThemeCompileEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NavbarHeightSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [ThemeCompileEvent::class => 'onCompile'];
    }

    public function onCompile(ThemeCompileEvent $event): void
    {
        $height = $event->getMenuCustom()['navbarHeight'] ?? null;

        if (null === $height) {
            return;
        }

        $event->addVariable('--app-navbar-height', $height . 'px');
        $event->addRule('.iw-menu > nav { min-height: var(--app-navbar-height); }');
    }
}
```

Recompile with `php bin/console iw-sulu:theme:compile` and the output carries:

```css
:root {
  /* ... */
  /* Project contributions */
  --app-navbar-height: 64px;
}

/* ... every built-in class ... */

/* Project contributions */
.iw-menu > nav { min-height: var(--app-navbar-height); }
```

### Where contributions land

You do not choose, and do not need to. Variables go inside `:root`; rules are
appended after every built-in class, so a contributed rule wins over a bundle
rule of equal specificity. That ordering is the point: it is what lets you
adjust a shipped component without touching its Twig.

### The event API

| Member | Purpose |
|--------|---------|
| `getTheme()` | The `ThemeConfig` being compiled - full token access |
| `getCustom()` | Project fields from the tokens column |
| `getMenuCustom()` | Project fields from the menu column |
| `getFooterCustom()` | Project fields from the footer column |
| `addVariable(name, value)` | Contribute a custom property to `:root` |
| `addRule(css)` | Contribute one or more complete CSS rules |

### What gets rejected

Contributions are spliced into a stylesheet loaded on every page, so a malformed
one does not fail locally - it can take the whole file down. These throw a
`ThemeCompileContributionException` at compile time, loudly, because the
audience is you and not a content editor:

- a variable name that is not a valid custom property (must match `--[a-zA-Z0-9_-]+`)
- a value containing `{`, `}`, `;` or `</`, which would escape its declaration
- a rule with unbalanced braces, which would swallow everything after it
- a rule containing `</`, which would close an inlined `<style>` tag

Contributing the same variable twice keeps the last value, exactly as the
cascade would.

## Cache busting

Compiled filenames carry a hash (`theme-12-9bd4ff32.css`) built from the theme's
`updatedAt` **and** a fingerprint of the contributions. Editing your listener
therefore produces a new filename even though nobody re-saved the theme in the
admin - without that, the file would be rewritten under the same URL and
browsers and CDNs would keep serving the previous one.

A theme with no contributions keeps the filename it has always had, so upgrading
busts nothing needlessly.

## Choosing between the three

**Twig only** — the value affects markup, not styling. Read it in your template
and stop there.

**A CSS variable** — the value feeds styling that already exists, or you want it
overridable further down the cascade. Contribute a variable and reference it.

**A full rule** — you need a selector the bundle does not provide, a media
query, or to override a shipped component. Contribute a rule.

When unsure, prefer a variable: it stays overridable by anything loaded after
the theme, whereas a rule hard-codes a decision into the generated file.
