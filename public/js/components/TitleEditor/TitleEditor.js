// @flow
import React from 'react';
import {observer} from 'mobx-react';
import {translate} from 'sulu-admin-bundle/utils';
import Button from 'sulu-admin-bundle/components/Button';
import Popover from 'sulu-admin-bundle/components/Popover';
import TextArea from 'sulu-admin-bundle/components/TextArea';
import PaletteGrid from '../PaletteGrid/PaletteGrid';

/**
 * A marker, with an optional palette color prefix.
 *
 * Mirrors TitleMarkupRenderer::MARKER_PATTERN on the PHP side. The reference
 * cases live in tests/Service/TitleMarkupRendererTest.php: keep the two in
 * sync, a divergence here shows up as a button that lights up on a selection
 * the server will not render the way the editor expects.
 */
const MARKER_PATTERN = /\[\[(?:([a-z0-9-]+):)?([^[\]]+)]]/g;

/**
 * Id of the style element holding the editor rules, injected once per document.
 */
const STYLE_ID = 'iw-title-editor-styles';

function ensureStyles() {
    if (document.getElementById(STYLE_ID)) {
        return;
    }

    const style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = `
        .iw-title-editor__toolbar {
            display: flex;
            gap: 6px;
            margin-bottom: 6px;
            align-items: center;
        }
        .iw-title-editor__hint {
            font-size: 11px;
            color: #999;
            margin-top: 4px;
        }
        .iw-title-editor__hint code {
            background: #f2f2f2;
            border-radius: 3px;
            padding: 0 3px;
        }
        /* Sulu's Popover only positions its child; the background is ours to
           paint, otherwise the form shows through the swatches. Matches the
           color field's own popover so both read as the same control. */
        .iw-title-editor__palette {
            background: #fff;
            border: 1px solid #c0c0c0;
            border-radius: 3px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }
        .iw-title-editor__palette-footer {
            padding: 6px 8px;
            border-top: 1px solid #e0e0e0;
        }
    `;
    document.head.appendChild(style);
}

/**
 * Read a boolean schema option coming from the XML params.
 *
 * A param may arrive as a real boolean or as the string "true"/"false"
 * depending on how the form schema was built, so both are handled rather than
 * relying on truthiness - `!!'false'` is true, which would silently turn an
 * option on.
 *
 * @param {Object} schemaOptions The field's schema options
 * @param {string} name The param name
 * @param {boolean} fallback The value to use when the param is absent
 * @returns {boolean} The option value
 */
function boolOption(schemaOptions: ?Object, name: string, fallback: boolean): boolean {
    const value = schemaOptions?.[name]?.value;

    if (undefined === value || null === value) {
        return fallback;
    }

    return 'false' !== value && false !== value;
}

/**
 * Button defaults when the project configured nothing.
 *
 * Mirrors the bundle's own configuration tree: a block heading takes its accent
 * color from its variant, so it needs no palette; a page title sits outside any
 * variant, so it does.
 */
const SHIPPED_DEFAULTS = {
    blocks: {highlight: true, color: false},
    pages: {highlight: false, color: true},
};

/**
 * Locate every marker in a stored title.
 *
 * @param {string} value The stored title
 * @returns {Array<Object>} Markers as {start, end, innerStart, innerEnd, color, text}
 */
export function findMarkers(value: string): Array<Object> {
    const markers = [];
    const pattern = new RegExp(MARKER_PATTERN.source, 'g');
    let match;

    while (null !== (match = pattern.exec(value))) {
        const color = match[1] || null;
        const text = match[2];
        // Where the text sits inside the marker: two brackets, plus the color
        // prefix and its colon when there is one.
        const innerStart = match.index + 2 + (color ? color.length + 1 : 0);

        markers.push({
            start: match.index,
            end: match.index + match[0].length,
            innerStart,
            innerEnd: innerStart + text.length,
            color,
            text,
        });
    }

    return markers;
}

/**
 * Classify a selection against the markers around it.
 *
 * Three outcomes, and only three, which is what makes the button states
 * predictable:
 *
 * - `free`    the selection touches no marker, so it can be wrapped
 * - `inside`  the selection IS the text of a marker, so it can be recolored
 *             or unwrapped
 * - `blocked` anything else: an empty selection, or one that straddles a
 *             marker boundary. Acting on it would produce nested or truncated
 *             markers, so the buttons go disabled instead.
 *
 * @param {string} value The stored title
 * @param {number} start Selection start offset
 * @param {number} end Selection end offset
 * @returns {Object} {state, marker}
 */
export function classifySelection(value: string, start: number, end: number): Object {
    if (start === end) {
        return {state: 'blocked', marker: null};
    }

    const markers = findMarkers(value);

    for (const marker of markers) {
        if (start === marker.innerStart && end === marker.innerEnd) {
            return {state: 'inside', marker};
        }
    }

    const straddles = markers.some((marker) => start < marker.end && end > marker.start);

    return straddles ? {state: 'blocked', marker: null} : {state: 'free', marker: null};
}

/**
 * Title editor field for the Sulu admin.
 *
 * Edits a short title that can span several lines and carry highlighted words,
 * and stores it as PLAIN TEXT with a small, closed syntax:
 *
 * - `[[word]]`             highlighted, colored by the block variant
 * - `[[accent:word]]`      colored by an explicit palette color
 * - a real newline         a line break
 *
 * A plain `<textarea>` is used on purpose rather than a rich text editor: the
 * selection is two integers, so wrapping it is a string splice, the browser's
 * own undo and paste keep working, and nothing but text ever reaches the
 * database. Sulu's live preview shows the rendered result, so the field itself
 * does not try to preview anything.
 *
 * XML params:
 * - `context`   which set of project defaults applies: `blocks` (a block
 *               heading, the default) or `pages` (a page hero title or an
 *               article subtitle)
 * - `highlight` force the highlight button on or off for THIS field, whatever
 *               the project configured
 * - `color`     same, for the palette button
 *
 * Which buttons show up is resolved in three steps, most specific first: an
 * explicit XML param, then the project's `title_editor` config for the
 * declared context, then the shipped default. A project that configures
 * nothing keeps the behavior it had.
 */
@observer
class TitleEditor extends React.Component<*> {
    /**
     * Per-context button defaults, from the project's YAML config.
     *
     * Filled once at boot by the admin config hook (see index.js). Empty when a
     * project runs an older config: SHIPPED_DEFAULTS then applies.
     */
    static contextDefaults: Object = {};

    containerRef: ?HTMLElement = null;

    /** Anchor the color popover is positioned against. */
    colorButtonRef: ?HTMLElement = null;

    /**
     * Selection to restore after the value has been written back.
     *
     * Rewriting the value re-renders the textarea with the caret at its start,
     * so the range the editor was working on has to be put back by hand.
     */
    pendingSelection: ?Object = null;

    constructor(props: Object) {
        super(props);

        this.state = {
            selection: {start: 0, end: 0},
            colorOpen: false,
        };
    }

    componentDidMount() {
        ensureStyles();

        const textarea = this.textarea;
        if (textarea) {
            // The Sulu TextArea does not forward a ref to its <textarea>, and
            // the selection has to be tracked as it changes rather than read on
            // click: pressing a toolbar button moves the focus first.
            textarea.addEventListener('select', this.handleSelectionChange);
            textarea.addEventListener('keyup', this.handleSelectionChange);
            textarea.addEventListener('mouseup', this.handleSelectionChange);
            textarea.addEventListener('focus', this.handleSelectionChange);
        }
    }

    componentDidUpdate() {
        const pending = this.pendingSelection;
        const textarea = this.textarea;

        if (pending && textarea) {
            this.pendingSelection = null;
            textarea.focus();
            textarea.setSelectionRange(pending.start, pending.end);
            this.setState({selection: pending});
        }
    }

    componentWillUnmount() {
        const textarea = this.textarea;
        if (textarea) {
            textarea.removeEventListener('select', this.handleSelectionChange);
            textarea.removeEventListener('keyup', this.handleSelectionChange);
            textarea.removeEventListener('mouseup', this.handleSelectionChange);
            textarea.removeEventListener('focus', this.handleSelectionChange);
        }
    }

    /**
     * The underlying textarea element.
     *
     * Reached through the wrapper because Sulu's TextArea takes no ref. The
     * component renders exactly one textarea, so the lookup is unambiguous.
     */
    get textarea(): ?HTMLTextAreaElement {
        return this.containerRef ? this.containerRef.querySelector('textarea') : null;
    }

    get value(): string {
        return this.props.value || '';
    }

    setContainerRef = (ref: ?HTMLElement) => {
        this.containerRef = ref;
    };

    setColorButtonRef = (ref: ?HTMLElement) => {
        this.colorButtonRef = ref;
    };

    handleSelectionChange = () => {
        const textarea = this.textarea;
        if (!textarea) {
            return;
        }

        const {selectionStart: start, selectionEnd: end} = textarea;
        const {selection} = this.state;

        if (selection.start !== start || selection.end !== end) {
            this.setState({selection: {start, end}});
        }
    };

    handleChange = (value: ?string) => {
        this.props.onChange(value);
    };

    handleBlur = () => {
        if (this.props.onFinish) {
            this.props.onFinish();
        }
    };

    /**
     * Write a new value back and remember where the caret should land.
     *
     * @param {string} value The new stored title
     * @param {number} start Offset the restored selection starts at
     * @param {number} end Offset the restored selection ends at
     */
    commit(value: string, start: number, end: number) {
        this.pendingSelection = {start, end};
        this.props.onChange(value);

        if (this.props.onFinish) {
            this.props.onFinish();
        }
    }

    /**
     * Wrap the selection in a marker, or retarget the marker it already is.
     *
     * @param {string|null} color The palette color name, or null for a plain highlight
     */
    applyMarker(color: ?string) {
        const {state, marker} = classifySelection(this.value, this.state.selection.start, this.state.selection.end);

        if ('blocked' === state) {
            return;
        }

        const prefix = color ? color + ':' : '';

        if ('inside' === state && marker) {
            // Already a marker: swap its prefix rather than nesting a new one.
            const replacement = '[[' + prefix + marker.text + ']]';
            const value = this.value.slice(0, marker.start) + replacement + this.value.slice(marker.end);
            const innerStart = marker.start + 2 + prefix.length;

            this.commit(value, innerStart, innerStart + marker.text.length);

            return;
        }

        const {start, end} = this.state.selection;
        const text = this.value.slice(start, end);
        const value = this.value.slice(0, start) + '[[' + prefix + text + ']]' + this.value.slice(end);
        const innerStart = start + 2 + prefix.length;

        this.commit(value, innerStart, innerStart + text.length);
    }

    /**
     * Unwrap the marker the selection sits in, keeping its text.
     */
    removeMarker() {
        const {state, marker} = classifySelection(this.value, this.state.selection.start, this.state.selection.end);

        if ('inside' !== state || !marker) {
            return;
        }

        const value = this.value.slice(0, marker.start) + marker.text + this.value.slice(marker.end);

        this.commit(value, marker.start, marker.start + marker.text.length);
    }

    handleHighlightClick = () => {
        const {state, marker} = classifySelection(this.value, this.state.selection.start, this.state.selection.end);

        // On a marker that is already a plain highlight the button reads as a
        // toggle and removes it; on a colored one it drops the color back to
        // the variant's highlight.
        if ('inside' === state && marker && null === marker.color) {
            this.removeMarker();

            return;
        }

        this.applyMarker(null);
    };

    handleColorButtonClick = () => {
        this.setState((prevState) => ({colorOpen: !prevState.colorOpen}));
    };

    handleColorClose = () => {
        this.setState({colorOpen: false});
    };

    handleColorSelect = (hex: string, colorKey: string, shade: ?number) => {
        this.setState({colorOpen: false});
        // The NAME is stored, never the hex: the title then follows the theme
        // when the palette is recolored. A null shade means the base color.
        this.applyMarker(null === shade || undefined === shade ? colorKey : colorKey + '-' + String(shade));
    };

    handleColorRemove = () => {
        this.setState({colorOpen: false});
        this.removeMarker();
    };

    /**
     * Whether a swatch matches the color of the marker being edited.
     */
    isSwatchSelected = (colorKey: string, shade: ?number) => {
        const {state, marker} = classifySelection(this.value, this.state.selection.start, this.state.selection.end);

        if ('inside' !== state || !marker || !marker.color) {
            return false;
        }

        return marker.color === (null === shade ? colorKey : colorKey + '-' + String(shade));
    };

    render() {
        const {disabled, schemaOptions, valid} = this.props;
        const {colorOpen, selection} = this.state;

        const context = schemaOptions?.context?.value || 'blocks';
        const shipped = SHIPPED_DEFAULTS[context] || SHIPPED_DEFAULTS.blocks;
        const configured = TitleEditor.contextDefaults[context] || {};

        const showHighlight = boolOption(
            schemaOptions,
            'highlight',
            undefined === configured.highlight ? shipped.highlight : configured.highlight,
        );
        const showColor = boolOption(
            schemaOptions,
            'color',
            undefined === configured.color ? shipped.color : configured.color,
        );

        const {state, marker} = classifySelection(this.value, selection.start, selection.end);
        const actionable = !disabled && 'blocked' !== state;
        const isPlainHighlight = 'inside' === state && marker && null === marker.color;

        const disabledHint = translate('iw_sulu_tailwind_theme.title_editor_select_first');

        return (
            <div className="iw-title-editor" ref={this.setContainerRef}>
                {(showHighlight || showColor) &&
                    <div className="iw-title-editor__toolbar">
                        {showHighlight &&
                            <Button
                                disabled={!actionable}
                                icon="su-magic"
                                onClick={this.handleHighlightClick}
                                size="small"
                                skin={isPlainHighlight ? 'primary' : 'secondary'}
                            >
                                {translate(isPlainHighlight
                                    ? 'iw_sulu_tailwind_theme.title_editor_highlight_remove'
                                    : 'iw_sulu_tailwind_theme.title_editor_highlight')}
                            </Button>
                        }

                        {showColor &&
                            <Button
                                buttonRef={this.setColorButtonRef}
                                disabled={!actionable}
                                icon="su-paint"
                                onClick={this.handleColorButtonClick}
                                size="small"
                                skin={'inside' === state && marker && marker.color ? 'primary' : 'secondary'}
                            >
                                {translate('iw_sulu_tailwind_theme.title_editor_color')}
                            </Button>
                        }

                        {!actionable &&
                            <span className="iw-title-editor__hint">{disabledHint}</span>
                        }
                    </div>
                }

                <TextArea
                    disabled={disabled}
                    onBlur={this.handleBlur}
                    onChange={this.handleChange}
                    rows={2}
                    valid={false !== valid}
                    value={this.props.value}
                />

                <div className="iw-title-editor__hint">
                    {translate('iw_sulu_tailwind_theme.title_editor_hint')}
                </div>

                {showColor &&
                    <Popover
                        anchorElement={this.colorButtonRef}
                        onClose={this.handleColorClose}
                        open={colorOpen}
                    >
                        {(setPopoverElementRef, popoverStyle) => (
                            <div className="iw-title-editor__palette" ref={setPopoverElementRef} style={popoverStyle}>
                                <PaletteGrid
                                    isSelected={this.isSwatchSelected}
                                    onSelect={this.handleColorSelect}
                                />
                                {'inside' === state && marker && marker.color &&
                                    <div className="iw-title-editor__palette-footer">
                                        <Button onClick={this.handleColorRemove} size="small" skin="link">
                                            {translate('iw_sulu_tailwind_theme.title_editor_color_remove')}
                                        </Button>
                                    </div>
                                }
                            </div>
                        )}
                    </Popover>
                }
            </div>
        );
    }
}

export default TitleEditor;
