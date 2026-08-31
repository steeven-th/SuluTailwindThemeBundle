// @flow
import React from 'react';
import {action, observable} from 'mobx';
import {observer} from 'mobx-react';
import {Button, Overlay} from 'sulu-admin-bundle/components';
import {translate} from 'sulu-admin-bundle/utils';
import {getSuluPrimaryColor, getSuluPrimaryAlpha, getSuluPrimaryTint} from '../../utils/suluColors';
import {renderWireframe} from '../StylePicker/wireframes';

/**
 * Scope selector for the theme-wide block maximum width.
 *
 * The field itself is a single button, so 16 block types and their 50-odd
 * styles do not weigh on the settings page. Everything happens in a modal:
 * blocks on the left, the styles of the selected block on the right, each with
 * the same wireframe the editor sees when picking a style on a block.
 *
 * Stored value: a list of `type` entries (the whole block, whatever its style)
 * and `type:style` entries (that style only). A block whose styles are all
 * selected is stored as the bare type, so a style added later stays covered.
 * `null` means the scope was never set, and both this field and the renderer
 * read it as the suggested selection - see ThemeAdmin::MAX_WIDTH_SUGGESTED_SCOPE.
 */
@observer
export default class BlockScopeSelector extends React.Component {
    /**
     * Styles per block type, from ThemeAdmin::BLOCK_STYLE_OPTIONS.
     * Set by the config hook in index.js.
     *
     * @type {Object<string, Array<{key: string, label: string}>>}
     */
    static blockStyles = {};

    /**
     * Block types the theme covers out of the box, from
     * ThemeAdmin::MAX_WIDTH_SUGGESTED_SCOPE. The "Suggested selection" button
     * restores exactly this, and it is what an untouched scope resolves to.
     *
     * @type {Array<string>}
     */
    static suggestedScope = [];

    @observable open = false;

    /** Working copy, so closing the modal without confirming changes nothing. */
    @observable draft = [];

    /** Block type shown in the detail panel. */
    @observable detailType = null;

    /**
     * The block types offering a maximum width, in the order the admin config
     * lists them.
     */
    get blockTypes(): Array<string> {
        return Object.keys(BlockScopeSelector.blockStyles);
    }

    /**
     * The scope in effect, falling back to the suggested selection when the
     * modal was never opened - the same fallback the renderer applies.
     *
     * A stored value arrives from the form store, and Sulu runs MobX 4, whose
     * observable arrays are not real arrays: `Array.isArray()` returns false
     * on them. Testing that way silently threw the saved scope away and showed
     * the suggested selection every time the modal reopened, while the site
     * kept rendering what was actually stored. `Array.from()` handles both.
     */
    get effectiveScope(): Array<string> {
        const {value} = this.props;

        if (!value || 'object' !== typeof value) {
            return BlockScopeSelector.suggestedScope;
        }

        return Array.from(value);
    }

    stylesOf(blockType: string): Array<Object> {
        return BlockScopeSelector.blockStyles[blockType] || [];
    }

    /**
     * Selection state of one block: "on" when the whole block is covered,
     * "mixed" when only some of its styles are, "off" otherwise.
     */
    stateOf(blockType: string): string {
        if (this.draft.includes(blockType)) {
            return 'on';
        }

        const picked = this.stylesOf(blockType).filter(
            (style) => this.draft.includes(blockType + ':' + style.key)
        ).length;

        if (0 === picked) {
            return 'off';
        }

        return picked === this.stylesOf(blockType).length ? 'on' : 'mixed';
    }

    isStyleSelected(blockType: string, styleKey: string): boolean {
        return this.draft.includes(blockType) || this.draft.includes(blockType + ':' + styleKey);
    }

    /** Count of selected styles, for the per-block counter. */
    selectedCount(blockType: string): number {
        if (this.draft.includes(blockType)) {
            return this.stylesOf(blockType).length;
        }

        return this.stylesOf(blockType).filter(
            (style) => this.draft.includes(blockType + ':' + style.key)
        ).length;
    }

    /**
     * Rewrite the entries of one block, collapsing a full selection back to
     * the bare block type.
     */
    @action setBlockStyles(blockType: string, styleKeys: Array<string>) {
        const others = this.draft.filter(
            (entry) => entry !== blockType && !entry.startsWith(blockType + ':')
        );

        if (0 === styleKeys.length) {
            this.draft = others;

            return;
        }

        this.draft = styleKeys.length === this.stylesOf(blockType).length
            ? [...others, blockType]
            : [...others, ...styleKeys.map((key) => blockType + ':' + key)];
    }

    @action handleOpen = () => {
        // The field is disabled rather than hidden when no width is set, and
        // the modal has nothing to scope then.
        if (this.props.disabled) {
            return;
        }

        this.draft = [...this.effectiveScope];
        this.detailType = this.blockTypes[0] || null;
        this.open = true;
    };

    @action handleClose = () => {
        this.open = false;
    };

    handleConfirm = () => {
        const {onChange, onFinish} = this.props;

        // Store in the order of the admin config, so two identical selections
        // never produce two different rows in the database.
        const ordered = [];
        this.blockTypes.forEach((blockType) => {
            if (this.draft.includes(blockType)) {
                ordered.push(blockType);

                return;
            }

            this.stylesOf(blockType).forEach((style) => {
                if (this.draft.includes(blockType + ':' + style.key)) {
                    ordered.push(blockType + ':' + style.key);
                }
            });
        });

        if (onChange) {
            onChange(ordered);
        }

        if (onFinish) {
            onFinish();
        }

        this.handleClose();
    };

    @action handleToggleBlock = (blockType: string) => {
        const keys = 'on' === this.stateOf(blockType)
            ? []
            : this.stylesOf(blockType).map((style) => style.key);

        this.setBlockStyles(blockType, keys);
    };

    @action handleToggleStyle = (blockType: string, styleKey: string) => {
        const current = this.stylesOf(blockType)
            .map((style) => style.key)
            .filter((key) => this.isStyleSelected(blockType, key));

        this.setBlockStyles(
            blockType,
            current.includes(styleKey)
                ? current.filter((key) => key !== styleKey)
                : [...current, styleKey]
        );
    };

    @action handleSelectDetail = (blockType: string) => {
        this.detailType = blockType;
    };

    @action handleAll = () => {
        this.draft = [...this.blockTypes];
    };

    @action handleNone = () => {
        this.draft = [];
    };

    @action handleSuggested = () => {
        this.draft = [...BlockScopeSelector.suggestedScope];
    };

    /**
     * Tri-state box: empty, checked, or a dash when only part of a block's
     * styles is selected.
     */
    renderTick(state: string) {
        const primary = getSuluPrimaryColor();
        const on = 'off' !== state;

        return (
            <span
                style={{
                    flex: 'none',
                    width: '16px',
                    height: '16px',
                    borderRadius: '3px',
                    border: '1px solid ' + (on ? primary : '#cfcfcf'),
                    background: on ? primary : '#fff',
                    color: '#fff',
                    fontSize: '11px',
                    lineHeight: '14px',
                    textAlign: 'center',
                }}
            >
                {'on' === state ? '✓' : ('mixed' === state ? '–' : '')}
            </span>
        );
    }

    renderMasterRow(blockType: string) {
        const selected = this.detailType === blockType;
        const total = this.stylesOf(blockType).length;

        return (
            <div
                key={blockType}
                onClick={() => this.handleSelectDetail(blockType)}
                role="button"
                tabIndex={0}
                onKeyDown={(event) => {
                    if ('Enter' === event.key || ' ' === event.key) {
                        this.handleSelectDetail(blockType);
                    }
                }}
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: '10px',
                    padding: '8px 12px',
                    cursor: 'pointer',
                    borderBottom: '1px solid #eee',
                    background: selected ? getSuluPrimaryTint() : 'transparent',
                    boxShadow: selected ? 'inset 3px 0 0 ' + getSuluPrimaryColor() : 'none',
                }}
            >
                <span
                    onClick={(event) => {
                        event.stopPropagation();
                        this.handleToggleBlock(blockType);
                    }}
                    role="checkbox"
                    aria-checked={'on' === this.stateOf(blockType)}
                    tabIndex={0}
                    onKeyDown={(event) => {
                        if ('Enter' === event.key || ' ' === event.key) {
                            event.stopPropagation();
                            this.handleToggleBlock(blockType);
                        }
                    }}
                    style={{display: 'flex'}}
                >
                    {this.renderTick(this.stateOf(blockType))}
                </span>
                <span style={{flex: 1, fontSize: '13px'}}>
                    {translate('iw_sulu_tailwind_theme.block.' + blockType)}
                </span>
                <span style={{fontSize: '11px', color: '#999', fontVariantNumeric: 'tabular-nums'}}>
                    {this.selectedCount(blockType) + '/' + total}
                </span>
            </div>
        );
    }

    renderStyleCard(blockType: string, style: Object) {
        const selected = this.isStyleSelected(blockType, style.key);
        const primary = getSuluPrimaryColor();

        return (
            <div
                key={style.key}
                onClick={() => this.handleToggleStyle(blockType, style.key)}
                role="checkbox"
                aria-checked={selected}
                tabIndex={0}
                onKeyDown={(event) => {
                    if ('Enter' === event.key || ' ' === event.key) {
                        this.handleToggleStyle(blockType, style.key);
                    }
                }}
                style={{
                    cursor: 'pointer',
                    border: selected ? '2px solid ' + primary : '2px solid #e6e6e6',
                    borderRadius: '6px',
                    padding: '8px',
                    background: selected ? getSuluPrimaryTint() : '#fafafa',
                    boxShadow: selected ? '0 0 0 3px ' + getSuluPrimaryAlpha(0.25) : 'none',
                    textAlign: 'center',
                }}
            >
                <div style={{display: 'flex', justifyContent: 'center'}}>
                    {renderWireframe(style.key, blockType, 0.75)}
                </div>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        gap: '6px',
                        marginTop: '6px',
                        fontSize: '11px',
                        color: selected ? primary : '#666',
                    }}
                >
                    {this.renderTick(selected ? 'on' : 'off')}
                    <span>{translate(style.label)}</span>
                </div>
            </div>
        );
    }

    renderDetail() {
        const blockType = this.detailType;
        if (!blockType) {
            return null;
        }

        return (
            <div style={{padding: '16px 18px', display: 'flex', flexDirection: 'column', gap: '14px'}}>
                <div style={{display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '12px'}}>
                    <strong style={{fontSize: '14px'}}>
                        {translate('iw_sulu_tailwind_theme.block.' + blockType)}
                    </strong>
                    <Button onClick={() => this.handleToggleBlock(blockType)} skin="secondary">
                        {translate(
                            'on' === this.stateOf(blockType)
                                ? 'iw_sulu_tailwind_theme.scope_unselect_block'
                                : 'iw_sulu_tailwind_theme.scope_select_block'
                        )}
                    </Button>
                </div>
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(auto-fill, minmax(130px, 1fr))',
                        gap: '10px',
                    }}
                >
                    {this.stylesOf(blockType).map((style) => this.renderStyleCard(blockType, style))}
                </div>
            </div>
        );
    }

    render() {
        const {disabled} = this.props;

        return (
            <div>
                <Button disabled={disabled} onClick={this.handleOpen} skin="secondary">
                    {translate('iw_sulu_tailwind_theme.scope_choose')}
                </Button>

                <Overlay
                    confirmText={translate('sulu_admin.confirm')}
                    onClose={this.handleClose}
                    onConfirm={this.handleConfirm}
                    open={this.open}
                    size="large"
                    title={translate('iw_sulu_tailwind_theme.defaults_blockMaxWidthScope')}
                >
                    <div style={{padding: '20px 24px', display: 'flex', flexDirection: 'column', gap: '12px'}}>
                        <p style={{margin: 0, fontSize: '13px', color: '#666'}}>
                            {translate('iw_sulu_tailwind_theme.scope_intro')}
                        </p>

                        <div style={{display: 'flex', gap: '8px', flexWrap: 'wrap'}}>
                            <Button onClick={this.handleSuggested} skin="primary">
                                {translate('iw_sulu_tailwind_theme.scope_suggested')}
                            </Button>
                            <Button onClick={this.handleAll} skin="secondary">
                                {translate('iw_sulu_tailwind_theme.scope_all')}
                            </Button>
                            <Button onClick={this.handleNone} skin="secondary">
                                {translate('iw_sulu_tailwind_theme.scope_none')}
                            </Button>
                        </div>

                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '260px 1fr',
                                border: '1px solid #e6e6e6',
                                borderRadius: '4px',
                                overflow: 'hidden',
                                minHeight: '360px',
                            }}
                        >
                            <div style={{borderRight: '1px solid #e6e6e6', maxHeight: '460px', overflowY: 'auto'}}>
                                {this.blockTypes.map((blockType) => this.renderMasterRow(blockType))}
                            </div>
                            <div style={{maxHeight: '460px', overflowY: 'auto'}}>{this.renderDetail()}</div>
                        </div>
                    </div>
                </Overlay>
            </div>
        );
    }
}
