// @flow
import React from 'react';
import type {Node} from 'react';

/**
 * Marked words, as `iw_theme_title_editor` stores them.
 *
 * Mirrors TitleMarkupRenderer::MARKER_PATTERN. An optional colour name, then
 * the words themselves: `[[accent]]`, `[[primary:accent]]`.
 */
const MARKER = /\[\[(?:[a-z0-9-]+:)?([^[\]]+)\]\]/g;

/**
 * How much of a title an admin block header shows.
 *
 * The same 50 characters Sulu's own string transformer uses, so a title and a
 * paragraph beside it are cut the same way.
 */
const MAX_LENGTH = 50;

/**
 * Renders a marked-up title in the header of a collapsed block.
 *
 * A collapsed block shows a few of its fields so an editor can tell one from
 * another on a long page. Sulu picks them from the field types it knows how to
 * render, and it knew nothing of `iw_theme_title_editor` - so the titles of
 * this bundle, which is to say almost every title, were skipped, and the
 * header fell back to whatever else was at hand: the heading-level select and
 * the body text.
 *
 * Registering this puts titles back in the running. It also gates the
 * `sulu.block_preview` tags on the title fields: a tagged property whose type
 * has no transformer is filtered out before rendering, so the tag alone would
 * have shown nothing.
 *
 * Markers are stripped rather than rendered. They are an authoring notation,
 * and `[[primary:Nos]] services` in a block header would read as a bug.
 */
export default class TitleBlockPreviewTransformer {
    transform(value: *): Node {
        if (typeof value !== 'string') {
            return null;
        }

        const plain = value.replace(MARKER, '$1').replace(/\s+/g, ' ').trim();

        if ('' === plain) {
            return null;
        }

        return <p>{plain.length > MAX_LENGTH ? plain.substring(0, MAX_LENGTH) + '...' : plain}</p>;
    }
}
