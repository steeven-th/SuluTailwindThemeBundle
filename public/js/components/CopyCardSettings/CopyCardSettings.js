// @flow
import React from 'react';
import {action, observable} from 'mobx';
import {observer} from 'mobx-react';
import {Button, Dialog} from 'sulu-admin-bundle/components';
import {translate} from 'sulu-admin-bundle/utils';

/**
 * Fields of Blocks > Cards and their twin in the Cards tab.
 *
 * Mirrors ThemeCompiler::BLOCK_CARD_KEYS, which `CopyCardSettingsContractTest`
 * checks. The margin round the image has no plain twin and is mapped apart, see
 * `placementTo()`.
 */
export const BLOCK_CARD_KEYS: {[string]: string} = {
    blockCardTitleSize: 'cardTitleSize',
    blockCardTextSize: 'cardTextSize',
    blockCardPadding: 'cardPadding',
    blockCardImageRatio: 'cardImageRatio',
    blockCardHoverImage: 'cardHoverImage',
    blockCardHoverTransform: 'cardHoverTransform',
    blockCardHoverDuration: 'cardHoverDuration',
    blockCardHoverEasing: 'cardHoverEasing',
    blockCardShadow: 'cardShadow',
};

// Values of the block padding the Cards tab does not offer.
const BLOCK_ONLY_PADDINGS = ['', 'variant'];

/**
 * The values to write so one set of card settings matches the other.
 *
 * `toBlocks` copies the Cards tab into Blocks > Cards, otherwise the other
 * way round. A value never saved on the source side is written as null, so
 * the target lands on its default as the source does. Values one side cannot
 * hold are left out rather than forced.
 */
export function copiedValues(getValue: (string) => mixed, toBlocks: boolean): {[string]: mixed} {
    const values = {};

    Object.keys(BLOCK_CARD_KEYS).forEach((blockKey) => {
        const cardKey = BLOCK_CARD_KEYS[blockKey];
        const source = getValue('/' + (toBlocks ? cardKey : blockKey));

        if (!toBlocks && 'blockCardPadding' === blockKey && BLOCK_ONLY_PADDINGS.includes(source ?? '')) {
            return;
        }

        // A shadow is an object: the two tabs must not share it.
        values[toBlocks ? blockKey : cardKey] = undefined === source
            ? null
            : JSON.parse(JSON.stringify(source));
    });

    const placement = placementTo(getValue, toBlocks);
    if (undefined !== placement) {
        Object.assign(values, placement);
    }

    return values;
}

/**
 * The place of the image, between a select and a checkbox.
 *
 * Blocks > Cards gives the margin round the image. The Cards tab only says
 * whether the image is padded: padded follows the padding, not padded lies
 * edge to edge. `auto` and a length have no twin there and are not copied.
 */
function placementTo(getValue: (string) => mixed, toBlocks: boolean): ?{[string]: mixed} {
    if (toBlocks) {
        const padded = getValue('/cardImagePadded');

        return {blockCardImageInset: false === padded ? '0' : 'padding'};
    }

    const inset = getValue('/blockCardImageInset');
    if ('padding' === inset || '0' === inset) {
        return {cardImagePadded: 'padding' === inset};
    }

    return undefined;
}

type Props = {
    formInspector: Object,
    schemaOptions: Object,
};

/**
 * A button copying one set of card settings onto the other, after a confirm.
 *
 * Both tabs edit the same theme record, so the values of the other tab are
 * already in the form store: the copy only changes the form, and the editor
 * saves it like any edit. The `direction` param says which way it goes,
 * `to_blocks` or `to_cards`.
 */
@observer
class CopyCardSettings extends React.Component<Props> {
    @observable open: boolean = false;

    get toBlocks(): boolean {
        const {schemaOptions} = this.props;

        return 'to_cards' !== schemaOptions?.direction?.value;
    }

    @action handleOpen = () => {
        this.open = true;
    };

    @action handleCancel = () => {
        this.open = false;
    };

    @action handleConfirm = () => {
        const {formInspector} = this.props;
        formInspector.formStore.changeMultiple(copiedValues((path) => formInspector.getValueByPath(path), this.toBlocks));
        this.open = false;
    };

    render() {
        const prefix = 'iw_sulu_tailwind_theme.copy_card_settings_' + (this.toBlocks ? 'to_blocks' : 'to_cards');

        return (
            <>
                <Button icon="su-copy" onClick={this.handleOpen} skin="secondary">
                    {translate(prefix)}
                </Button>
                <Dialog
                    cancelText={translate('sulu_admin.cancel')}
                    confirmText={translate('iw_sulu_tailwind_theme.copy_card_settings_confirm')}
                    onCancel={this.handleCancel}
                    onConfirm={this.handleConfirm}
                    open={this.open}
                    title={translate(prefix)}
                >
                    <p>{translate(prefix + '_warning')}</p>
                </Dialog>
            </>
        );
    }
}

export default CopyCardSettings;
