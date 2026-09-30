# Button and pictogram fields

Two field types that hold a whole button, or a pictogram, in **one property
named freely**: `iw_theme_button` and `iw_theme_icon_picker`.

A button used to take four to six properties side by side: a `link`, an
`iw_theme_button_style_picker`, and the `icon-picker.xml` and
`icon-placement.xml` fragments. Those fragments generate fixed names (`icon`,
`iconMedia`, `iconSize`, `iconPosition`, `iconGap`), so a level could hold only
one of them: a second inclusion overwrote the first without a word. With these
types, a template carries as many buttons and pictograms as it wants, on any
level.

Every button and pictogram of the bundle uses them: the call-to-action buttons
of the blocks (`cta-buttons.xml`), the cards and the link of a clickable card,
the key figures, the timeline steps and the buttons of the mega menu. Content
written before is moved by `iw-sulu:theme:migrate-buttons`, see
[the upgrade guide](upgrade-3.0.0.md#buttons-and-pictograms-move-onto-their-fields-breaking-migration-provided).

---

## Declaring the fields

```xml
<property name="cta" type="iw_theme_button">
    <meta><title lang="en">Button</title></meta>
    <params>
        <param name="with_display" value="true"/>
    </params>
</property>

<property name="badge" type="iw_theme_icon_picker">
    <meta><title lang="en">Pictogram</title></meta>
    <params>
        <param name="with_placement" value="true"/>
    </params>
</property>
```

### `iw_theme_button`

| Part | What the editor picks |
|------|-----------------------|
| **Link** | Sulu's own link field: a page, a media, an article or an external URL, with title, target, rel, anchor and query. **The title attribute is the label of the button.** Left empty, the label is the title of the linked page or media, then the URL. |
| **Button style** | A button style of the theme, per site where the project offers it (same picker as the call-to-action buttons). Unset, the variant button of the block (`iw-button--variant`). |
| **Pictogram** | An `iw_theme_icon_picker`, placement included. |
| **Pictogram alone** | A toggle beside the pictogram, offered with `with_display`. The pictogram then becomes the button: no background, no border, in the colour of the style. |

| Param | Default | Effect |
|-------|---------|--------|
| `with_icon` | `true` | Offer a pictogram. |
| `with_display` | `false` | Offer the "pictogram alone" toggle. |
| `with_style` | `true` | Offer a button style. Off for a link drawn as an entry of a list, as the links of a menu dropdown. |
| `excluded_types` | none | Link types left out, comma-separated, as for a `link` field. |
| `with_card` | outside a block | Wrap the fields in a collapsible card. By default a field inside a block item goes without, the block already being one. |

A collapsed block shows the label of its button in its header.

### `iw_theme_icon_picker`

The choices of the deprecated `icon-picker.xml` and `icon-placement.xml` fragments: a
pictogram of the theme library or an image of the media library, its size
(automatic or 16 to 72px) and, with `with_placement`, the side it sits on and
its gap to the label. A library pictogram also has a weight, outline or solid,
the two sets of Heroicons. The overlay browses the weight picked, and the two
sets share their names, so switching the weight keeps the pictogram. The
fragments only offer outline.

| Param | Default | Effect |
|-------|---------|--------|
| `with_placement` | `false` | Offer the side of the pictogram and its gap to the label. |
| `with_card` | outside a block | Wrap the fields in a collapsible card. By default a field inside a block item goes without, the block already being one. |

---

## Stored value

```json
{
  "link": {"provider": "page", "href": "<uuid>", "locale": "fr", "title": "Contact", "target": "_self"},
  "style": "primary",
  "icon": {"custom": false, "icon": "envelope", "weight": "outline", "media": null, "size": "", "position": "right", "gap": "gap-2"},
  "display": "button"
}
```

`display` is `icon` for the pictogram alone. `style` may be a map per site,
`{"_default": "primary", "other-site": "secondary"}`, as the button style picker
stores it.

---

## Reading it in Twig

On the website, both types are resolved by Sulu like its own fields
(`ButtonPropertyResolver`, `IconPickerPropertyResolver`): the link becomes a URL,
the media a media object, loaded in one query with the rest of the page.

```twig
{% set button = iw_sulu_tailwind_theme_button(content.cta, view.cta) %}
{% include '@ItechWorldSuluTailwindTheme/components/_button.html.twig' with {
    button: button,
    class: 'my-block__action',
} only %}

{% include '@ItechWorldSuluTailwindTheme/blocks/common/_icon.html.twig' with {
    icon: content.badge,
} only %}
```

`iw_sulu_tailwind_theme_button()` returns `null` when there is nothing to link
to, a link left blank or pointing to a page deleted or unpublished since, and
the partial then renders nothing. Otherwise it returns:

| Key | Holds |
|-----|-------|
| `url` | The address, query and anchor included |
| `label` | Title attribute, then title of the page or media, then URL |
| `target`, `rel` | Null for `_self`. A new tab without rel gets `noopener noreferrer` |
| `newTab` | Whether it opens a new tab (the partial tells screen readers) |
| `style` | The stored style, read per site by the partial |
| `icon` | The resolved pictogram, or null |
| `iconOnly` | Shown as its pictogram alone (only when there is one) |

`components/_button.html.twig` takes `button`, `class` (extra classes on the
anchor) and `iconOnly` (force the pictogram alone, or bring the label back).

---

## Classes

| Class | Role |
|-------|------|
| `.iw-button--<style>` / `.iw-button--variant` | The button style, as for every button of the theme. |
| `.iw-button--with-icon` | The button carries a pictogram. |
| `.iw-button--icon-gap-<n>` | The gap between pictogram and label, one class per step of the spacing picker (`--iw-button-icon-gap`). No inline style. |
| `.iw-button--icon-only` | Pictogram alone: the pictogram is the button. No background, no border, no shadow. A padding cancelled by a negative margin widens the clickable area to about 40px without moving the layout (`--iw-button-icon-only-hit`). The label stays in the anchor, in `sr-only`. |
| `.iw-button--<style>.iw-button--icon-only` | Generated per style: the pictogram takes the background of a filled style, the border of an outlined one, its text colour otherwise. On hover, the hover background then the hover border, skipping a colour equal to the resting text (an inverted button, whose hover colour is what surrounds it). |
| `.iw-button__icon`, `.iw-button__label` | The pictogram and the label. |

---

## Existing buttons

The call-to-action buttons of the blocks, the cards and the mega menu still use
the fragments. Moving them to these types, with a migration of the content
already published, is a separate step.
