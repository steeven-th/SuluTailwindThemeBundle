// @flow
import React from 'react';
import {render, unmountComponentAtNode} from 'react-dom';
import {action, observable} from 'mobx';
import {Observer} from 'mobx-react';
import {Plugin} from '@ckeditor/ckeditor5-core';
import {translate} from 'sulu-admin-bundle/utils';
import {ButtonView} from '@ckeditor/ckeditor5-ui';
import ColorTokenEditor from '../components/ColorTokenEditor/ColorTokenEditor';
import themeConfigStore from '../stores/themeConfigStore';
import {resolveRef} from '../utils/colorRefResolver';
import ensureEditorStyles from './editorStyles';
import {COLOR_ICON} from './icons';

/**
 * The model attribute holding a colour on a run of text.
 */
const ATTRIBUTE = 'iwTextColor';

/**
 * Prefix of the classes the theme compiler emits for palette colours.
 *
 * `generateTextColorClasses()` writes one per role, per brand slug and per
 * shade, each pointing at the palette variable rather than repeating a hex.
 * That is what makes a coloured word follow the theme when the palette
 * changes, and it is why a palette pick is stored as a reference here.
 */
const CLASS_PREFIX = 'iw-text--';

/**
 * Colours a run of text, from the theme palette or freely.
 *
 * The editor gets the same picker as the theme settings, so the palette and a
 * free colour sit behind one button. What is stored differs, and deliberately:
 *
 *   - a palette pick becomes `<span class="iw-text--primary-500">`, which
 *     follows the theme - recolour the palette and the text recolours with it
 *   - a free colour becomes `<span style="color: #abc123">`, which cannot
 *     follow anything, and is the price of picking outside the palette
 *
 * The picker itself is mounted the way Sulu mounts its link overlays: a div
 * beside the editor, React rendered into it, wrapped in an `Observer` so the
 * palette arriving later redraws it. That last part matters - the theme config
 * is loaded per webspace, after the editor is built.
 */
export default class TextColorPlugin extends Plugin {
    @observable open: boolean = false;
    @observable value: ?string = undefined;

    static get pluginName(): string {
        return 'IwTextColor';
    }

    init() {
        ensureEditorStyles();
        this.defineSchema();
        this.defineConverters();
        this.defineButton();
        this.mountPicker();
    }

    destroy() {
        super.destroy();

        if (this.element) {
            unmountComponentAtNode(this.element);
            this.element.remove();
        }
    }

    /**
     * CKEditor drops what its schema does not declare.
     *
     * Without this the span survives until the content is saved and reloaded,
     * then vanishes - the worst kind of bug to meet, since it looks like it
     * worked.
     */
    defineSchema() {
        this.editor.model.schema.extend('$text', {allowAttributes: ATTRIBUTE});
    }

    defineConverters() {
        const {conversion} = this.editor;

        // What is SAVED: a class for a palette pick, an inline colour otherwise.
        // The reference is what the theme can follow later, so it is kept as
        // written rather than resolved here.
        conversion.for('dataDowncast').attributeToElement({
            model: ATTRIBUTE,
            view: (value, {writer}) => {
                if ('string' !== typeof value || '' === value) {
                    return null;
                }

                if (0 === value.indexOf('ref:')) {
                    return writer.createAttributeElement(
                        'span',
                        {class: CLASS_PREFIX + value.substring(4)},
                        {priority: 7},
                    );
                }

                return writer.createAttributeElement('span', {style: 'color:' + value}, {priority: 7});
            },
        });

        // What is SHOWN in the editor: always an inline colour, resolved from
        // the palette. The class alone would do nothing there - the theme
        // stylesheet is compiled for the site and never loaded in the admin,
        // so the text changed on the page and not under the cursor.
        conversion.for('editingDowncast').attributeToElement({
            model: ATTRIBUTE,
            view: (value, {writer}) => {
                if ('string' !== typeof value || '' === value) {
                    return null;
                }

                const resolved = resolveRef(value, themeConfigStore.palette);

                return writer.createAttributeElement(
                    'span',
                    {style: 'color:' + resolved},
                    {priority: 7},
                );
            },
        });

        // Reading back: a class of ours becomes a reference again, an inline
        // colour stays a colour. Anything else is left alone.
        conversion.for('upcast').elementToAttribute({
            view: {name: 'span', classes: new RegExp('^' + CLASS_PREFIX)},
            model: {
                key: ATTRIBUTE,
                value: (viewElement) => {
                    const found = Array.from(viewElement.getClassNames())
                        .find((name) => 0 === name.indexOf(CLASS_PREFIX));

                    return found ? 'ref:' + found.substring(CLASS_PREFIX.length) : null;
                },
            },
        });

        conversion.for('upcast').elementToAttribute({
            view: {name: 'span', styles: {color: /.*/}},
            model: {
                key: ATTRIBUTE,
                value: (viewElement) => viewElement.getStyle('color') || null,
            },
        });
    }

    defineButton() {
        this.editor.ui.componentFactory.add('iwTextColor', (locale) => {
            const button = new ButtonView(locale);

            button.set({
                icon: COLOR_ICON,
                label: translate('iw_sulu_tailwind_theme.editor_text_color'),
                tooltip: true,
            });

            // Greyed out on an empty selection: colouring nothing would store
            // an attribute the editor cannot see and cannot remove.
            const command = this.editor.commands.get('bold');
            if (command) {
                button.bind('isEnabled').to(command, 'isEnabled');
            }

            this.listenTo(button, 'execute', action(() => {
                this.value = this.currentValue();
                this.open = true;
            }));

            return button;
        });
    }

    /**
     * The colour already on the selection, so reopening shows what is set.
     */
    currentValue(): ?string {
        const selection = this.editor.model.document.selection;

        return selection.getAttribute(ATTRIBUTE);
    }

    mountPicker() {
        this.element = document.createElement('div');
        this.element.className = 'iw-ckeditor-color-picker';

        // Under the toolbar and inside the editor's own element, so the picker
        // opens where the button is rather than at the bottom of the page. In
        // the flow rather than floating: pushing the content down beats
        // guessing an offset that a scrolled form would get wrong.
        const view = this.editor.ui.view;
        const host = (view && view.element) || this.editor.sourceElement;
        host.insertBefore(this.element, host.firstChild ? host.firstChild.nextSibling : null);

        render(
            (
                <Observer>
                    {() => (this.open
                        ? (
                            <ColorTokenEditor
                                autoOpen={true}
                                onChange={this.handleChange}
                                onFinish={this.handleFinish}
                                value={this.value}
                            />
                        )
                        : null
                    )}
                </Observer>
            ),
            this.element,
        );
    }

    handleChange = action((value: ?string) => {
        this.value = value;

        this.editor.model.change((writer) => {
            const ranges = this.editor.model.document.selection.getRanges();

            for (const range of ranges) {
                if (value) {
                    writer.setAttribute(ATTRIBUTE, value, range);
                } else {
                    writer.removeAttribute(ATTRIBUTE, range);
                }
            }
        });
    });

    handleFinish = action(() => {
        this.open = false;
        this.editor.editing.view.focus();
    });
}
