// @flow
import React from 'react';
import type {Node} from 'react';
import ButtonLabel from '../components/ButtonLabel/ButtonLabel';

/**
 * Renders an `iw_theme_button` in the header of a collapsed block.
 *
 * The value is an object Sulu has no way to print. The header shows the label
 * a visitor reads on the button: the title attribute of the link, else the
 * name of the linked page or media, loaded by ButtonLabel, else the address of
 * an external link.
 */
export default class ButtonBlockPreviewTransformer {
    transform(value: *): Node {
        if (!value || typeof value !== 'object' || !value.link || typeof value.link !== 'object' || !value.link.href) {
            return null;
        }

        return <ButtonLabel key={value.link.provider + ':' + value.link.href} link={value.link} />;
    }
}
