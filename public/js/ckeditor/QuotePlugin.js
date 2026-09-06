// @flow
import {Plugin} from '@ckeditor/ckeditor5-core';
import {translate} from 'sulu-admin-bundle/utils';
import {ButtonView} from '@ckeditor/ckeditor5-ui';
import {QUOTE_ICON} from './icons';

const ELEMENT = 'iwQuote';

/**
 * A quotation inside running text.
 *
 * CKEditor's own BlockQuote is free, but its package is not installed and is
 * not a dependency of Sulu, so using it would make every project installing
 * this bundle add an npm package for one button. A quotation is a `<blockquote>`
 * wrapping blocks - little enough to carry here rather than ask that of anyone.
 *
 * The text block of this bundle already has a quote STYLE, for a quotation
 * that is the whole block. This is the other case: a quotation among
 * paragraphs, which no block style can express.
 */
export default class QuotePlugin extends Plugin {
    static get pluginName(): string {
        return 'IwQuote';
    }

    init() {
        const {conversion, model} = this.editor;

        // A container that takes any block content: a quotation of several
        // paragraphs is a quotation, and refusing it would send the editor
        // back to pressing enter twice inside one.
        model.schema.register(ELEMENT, {
            allowWhere: '$block',
            allowContentOf: '$root',
        });

        conversion.elementToElement({model: ELEMENT, view: 'blockquote'});

        this.defineButton();
    }

    defineButton() {
        this.editor.ui.componentFactory.add('iwQuote', (locale) => {
            const button = new ButtonView(locale);

            button.set({
                icon: QUOTE_ICON,
                label: translate('iw_sulu_tailwind_theme.editor_quote'),
                tooltip: true,
                isToggleable: true,
            });

            const refresh = () => {
                button.isOn = this.isInQuote();
            };
            this.listenTo(this.editor.model.document, 'change', refresh);
            this.listenTo(this.editor.model.document.selection, 'change:range', refresh);
            refresh();

            this.listenTo(button, 'execute', () => {
                this.toggle();
                this.editor.editing.view.focus();
            });

            return button;
        });
    }

    /**
     * Whether the selection sits inside a quotation already.
     */
    isInQuote(): boolean {
        const block = this.editor.model.document.selection.getFirstPosition();

        for (let node = block ? block.parent : null; node; node = node.parent) {
            if (ELEMENT === node.name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Wraps the selected blocks in a quotation, or unwraps them.
     */
    toggle() {
        const {model} = this.editor;

        model.change((writer) => {
            if (this.isInQuote()) {
                const position = model.document.selection.getFirstPosition();
                for (let node = position ? position.parent : null; node; node = node.parent) {
                    if (ELEMENT === node.name) {
                        writer.unwrap(node);

                        return;
                    }
                }

                return;
            }

            const blocks = Array.from(model.document.selection.getSelectedBlocks());
            if (0 === blocks.length) {
                return;
            }

            // The range the SELECTION covers, not the whole parent: wrapping
            // the parent would swallow every sibling paragraph along with it.
            const range = writer.createRange(
                writer.createPositionBefore(blocks[0]),
                writer.createPositionAfter(blocks[blocks.length - 1]),
            );

            writer.wrap(range, writer.createElement(ELEMENT));
        });
    }
}
