// @flow
import React from 'react';
import {translate} from 'sulu-admin-bundle/utils';
import checkerBackground from 'sulu-media-bundle/components/MediaCard/checkerBackground.gif';
import {parseRef} from '../../utils/colorRefResolver';
import {watchThemePreview} from '../../utils/themePreviewCss';
import ColorTokenEditor from '../ColorTokenEditor/ColorTokenEditor';
import type {ThemePreview} from '../../utils/themePreviewCss';

/**
 * Live preview of a button style, in the buttons form of a theme.
 *
 * The button is the one the site renders: the stylesheet comes from the theme
 * compiler, fed with the form as it stands, unsaved values included. So hover,
 * focus and active states, the background effects and the keyframes are the
 * real ones, not a copy of them kept in step by hand.
 *
 * It renders inside an iframe. The stylesheet is written for a page of its own
 * (`:root` variables, a font `@import`, rules on `body`), and the admin styles
 * must neither leak in nor be overridden by it. The iframe runs no script: the
 * component writes into its document, which `allow-same-origin` permits.
 *
 * The field holds no value. It sits in the item of a button and reads the form
 * through the form inspector.
 */

const STORAGE_KEY = 'iw_sulu_tailwind_theme.button_preview_background';

const HEX_COLOR = /^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/;

const TRANSPARENT = 'transparent';

// What the site sets before the theme: the body typography of the bundle's
// app.css, which the compiled stylesheet only feeds with variables, and the
// box sizing every Tailwind page has.
const BASE_CSS = `
*, ::before, ::after { box-sizing: border-box; }
html, body { margin: 0; height: 100%; }
body {
    font-family: var(--font-body-family, var(--font-family-body, sans-serif));
    font-size: var(--font-size-base, 16px);
    font-weight: var(--font-body-weight, 400);
    font-style: var(--font-body-style, normal);
    line-height: var(--line-height-base, 1.5);
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}
.iw-preview {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100%;
    padding: 24px;
}
.iw-preview--checker { background-image: url(${checkerBackground}); }
`;

const FRAME_DOCUMENT = '<!DOCTYPE html><html><head>'
    + '<style>' + BASE_CSS + '</style><style id="theme"></style>'
    + '</head><body>'
    + '<section id="stage" class="iw-preview iw-preview--checker">'
    + '<a id="button" href="#"><span class="iw-button__label"></span></a>'
    + '</section>'
    + '</body></html>';

/**
 * The background colour as CSS, or null for the checkerboard.
 *
 * A palette reference becomes the custom property the theme stylesheet
 * declares for it, so it resolves inside the preview against the palette of
 * the form, unsaved colours included.
 */
function backgroundColor(value: string): ?string {
    const ref = parseRef(value);
    if (ref) {
        return 'var(--color-' + ref.name + (null === ref.shade ? '' : '-' + ref.shade) + ')';
    }

    return HEX_COLOR.test(value) ? value : null;
}

/**
 * The background last picked, shared by every preview and kept across visits.
 *
 * Anything but a colour falls back to `transparent`, the checkerboard, which
 * is also what the picker writes when that choice is made by hand. A stored
 * value from an older version of this preview is not a colour.
 */
function readBackground(): string {
    let stored = null;
    try {
        stored = window.localStorage.getItem(STORAGE_KEY);
    } catch (error) {
        // Blocked storage: the default applies.
    }

    return stored && backgroundColor(stored) ? stored : TRANSPARENT;
}

function writeBackground(background: string) {
    try {
        window.localStorage.setItem(STORAGE_KEY, background);
    } catch (error) {
        // Private browsing or blocked storage: the choice lasts for this view only.
    }
}

type Props = {
    dataPath: string,
    formInspector: Object,
};

type State = {
    background: string,
    preview: ?ThemePreview,
};

export default class ButtonPreview extends React.Component<Props, State> {
    state = {background: readBackground(), preview: null};

    frame: ?HTMLIFrameElement = null;
    frameReady: boolean = false;
    paintedCss: ?string = null;
    stopWatching: ?() => void = null;

    componentDidMount() {
        this.stopWatching = watchThemePreview(this.props.formInspector, (preview) => this.setState({preview}));
    }

    componentDidUpdate() {
        this.paint();
    }

    componentWillUnmount() {
        if (this.stopWatching) {
            this.stopWatching();
        }
    }

    /**
     * The position of this button in the list, read from the field path
     * (`/buttons/<index>/<field>`).
     */
    get index(): number {
        const segments = this.props.dataPath.split('/');

        return parseInt(segments[segments.length - 2], 10);
    }

    setFrame = (frame: ?HTMLIFrameElement) => {
        this.frame = frame;
    };

    handleFrameLoad = () => {
        const doc = this.frame && this.frame.contentDocument;
        if (!doc) {
            return;
        }

        // The button is there to be clicked: the active state shows, the
        // preview goes nowhere.
        doc.addEventListener('click', (event: MouseEvent) => {
            event.preventDefault();
        });

        this.frameReady = true;
        this.paintedCss = null;
        this.paint();
    };

    handleBackgroundChange = (background: ?string) => {
        // Clearing the field reads as the checkerboard too.
        const value = background || TRANSPARENT;
        writeBackground(value);
        this.setState({background: value});
    };

    /**
     * Write the current state into the iframe, without reloading it: a reload
     * would drop the hover and focus the editor is looking at.
     */
    paint() {
        const {background, preview} = this.state;
        const doc = this.frame && this.frame.contentDocument;
        if (!this.frameReady || !doc || !preview) {
            return;
        }

        const theme = doc.getElementById('theme');
        if (theme && this.paintedCss !== preview.css) {
            theme.textContent = preview.css;
            this.paintedCss = preview.css;
        }

        const stage = doc.getElementById('stage');
        if (stage) {
            const color = backgroundColor(background);
            stage.className = color ? 'iw-preview' : 'iw-preview iw-preview--checker';
            stage.style.backgroundColor = color || '';
        }

        const button = doc.getElementById('button');
        const slug = preview.buttons[this.index];
        if (button) {
            button.className = slug ? 'iw-button--' + slug : '';
            const label = button.firstElementChild;
            if (label) {
                label.textContent = translate('iw_sulu_tailwind_theme.button_preview_text');
            }
        }
    }

    render() {
        const {formInspector} = this.props;
        const {background} = this.state;

        if (!formInspector.id) {
            return (
                <div style={{color: '#999', fontStyle: 'italic', padding: '8px 0'}}>
                    {translate('iw_sulu_tailwind_theme.button_preview_unsaved')}
                </div>
            );
        }

        return (
            <div style={{display: 'flex', flexDirection: 'column', gap: '8px'}}>
                <iframe
                    onLoad={this.handleFrameLoad}
                    ref={this.setFrame}
                    sandbox="allow-same-origin"
                    srcDoc={FRAME_DOCUMENT}
                    style={{
                        width: '100%',
                        height: '120px',
                        border: '1px solid #d0d0d0',
                        borderRadius: '4px',
                        display: 'block',
                    }}
                    title={translate('iw_sulu_tailwind_theme.button_preview')}
                />
                <div style={{maxWidth: '280px'}}>
                    <span style={{display: 'block', fontSize: '12px', color: '#555', marginBottom: '4px'}}>
                        {translate('iw_sulu_tailwind_theme.button_preview_background')}
                    </span>
                    <ColorTokenEditor
                        formInspector={formInspector}
                        onChange={this.handleBackgroundChange}
                        schemaOptions={{show_palette: {value: true}}}
                        value={background}
                    />
                </div>
            </div>
        );
    }
}
