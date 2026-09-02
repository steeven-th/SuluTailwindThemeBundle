# Widget types

One file per widget the second zone of a two-zone block can hold. Each one is a
`<types>` document holding a single `<type>`, included into a block's
`<block name="widget">`:

```xml
<block name="widget" default-type="image" minOccurs="1" maxOccurs="1">
    <meta><title>iw_sulu_tailwind_theme.widget</title></meta>
    <types>
        <xi:include href="../fragments/widgets/image.xml"
                    xpointer="xmlns(sulu=http://schemas.sulu.io/template/template) xpointer(/sulu:types/sulu:type)"/>
    </types>
</block>
```

**Why a block type rather than a select plus conditions.** The fields of a
widget only make sense for that widget. Expressed with `visibleCondition`, each
one repeats the whole chain: `style == 'classic' AND mediaType == 'video' AND
videoProvider == 'youtube'` was a real condition here, three levels deep for one
field. Sulu's own block types carry that for free: it renders a type selector,
shows the fields of the chosen type, and stores the choice. Adding a widget is
adding a file, not editing every condition in the block.

**Why one file per type.** A block composes the catalogue it wants. `location`
offers the map and nothing else, so it stays the map block rather than becoming
another `text_images`. Restricting by composition costs one include, restricting
by condition would cost a matrix.

Render them through `blocks/common/_widget.html.twig`, which dispatches on the
type the editor picked.
