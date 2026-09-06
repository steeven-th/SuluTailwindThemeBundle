// @flow
import {Plugin} from '@ckeditor/ckeditor5-core';
import {translate} from 'sulu-admin-bundle/utils';
import {ButtonView} from '@ckeditor/ckeditor5-ui';
import {UPPERCASE_ICON} from './icons';

const ATTRIBUTE = 'iwUppercase';
const CLASS_NAME = 'iw-uppercase';

/**
 * Puts a run of text in capitals, without destroying it.
 *
 * CKEditor only ships this behind its commercial licence, so it is written
 * here - and written the way it should be anyway. Uppercasing the characters
 * themselves would lose the original wording, cannot be undone once saved, and
 * leaves screen readers guessing between a word and an acronym.
 *
 * A class carrying `text-transform` gives the same result, comes off as easily
 * as it goes on, and keeps the text readable in the editor.
 */
export default class UppercasePlugin extends Plugin {
    static get pluginName(): string {
        return 'IwUppercase';
    }

    init() {
        const {conversion, model} = this.editor;

        model.schema.extend('$text', {allowAttributes: ATTRIBUTE});

        conversion.for('downcast').attributeToElement({
            model: ATTRIBUTE,
            view: (value, {writer}) => value
                ? writer.createAttributeElement('span', {class: CLASS_NAME}, {priority: 7})
                : null,
        });

        conversion.for('upcast').elementToAttribute({
            view: {name: 'span', classes: CLASS_NAME},
            model: {key: ATTRIBUTE, value: () => true},
        });

        this.defineButton();
    }

    defineButton() {
        this.editor.ui.componentFactory.add('iwUppercase', (locale) => {
            const button = new ButtonView(locale);

            button.set({
                icon: UPPERCASE_ICON,
                label: translate('iw_sulu_tailwind_theme.editor_uppercase'),
                tooltip: true,
                isToggleable: true,
            });

            const selection = this.editor.model.document.selection;

            // The button lights up on text that already carries the class, the
            // same way bold and italic do. Without it there is no way to tell
            // capitals that were typed from capitals that were applied.
            const refresh = () => {
                button.isOn = true === selection.getAttribute(ATTRIBUTE);
            };
            this.listenTo(this.editor.model.document, 'change', refresh);
            this.listenTo(selection, 'change:range', refresh);
            refresh();

            const bold = this.editor.commands.get('bold');
            if (bold) {
                button.bind('isEnabled').to(bold, 'isEnabled');
            }

            this.listenTo(button, 'execute', () => {
                const on = true === selection.getAttribute(ATTRIBUTE);

                this.editor.model.change((writer) => {
                    for (const range of selection.getRanges()) {
                        if (on) {
                            writer.removeAttribute(ATTRIBUTE, range);
                        } else {
                            writer.setAttribute(ATTRIBUTE, true, range);
                        }
                    }
                });

                this.editor.editing.view.focus();
            });

            return button;
        });
    }
}
