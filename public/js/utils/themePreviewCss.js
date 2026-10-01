// @flow
import {reaction, toJS} from 'mobx';
import {Requester} from 'sulu-admin-bundle/services';

/**
 * The stylesheet of a theme as its form currently holds it, unsaved values
 * included, compiled by the server (`preview-css` route).
 *
 * Several previews are open at once, one per expanded button, and all of them
 * react to the same change of the form. They share the request: a call with the
 * data of the previous one returns the promise already in flight or settled.
 */

export type ThemePreview = {
    buttons: Array<string>,
    css: string,
    missingButtons: Array<string>,
};

const DEBOUNCE_MS = 400;

let last: {key: ?string, promise: ?Promise<ThemePreview>} = {key: null, promise: null};

/**
 * @param themeId The id of the theme being edited.
 * @param data    The whole form data, as the form would save it.
 *
 * @return The compiled preview. Rejected while the form holds a duplicate
 *         slug, which callers treat as "keep the previous rendering".
 */
export default function loadThemePreviewCss(themeId: string | number, data: Object): Promise<ThemePreview> {
    const key = themeId + ':' + JSON.stringify(data);
    if (last.key === key && last.promise) {
        return last.promise;
    }

    const promise = Requester.post('/admin/api/iw-theme-configs/' + themeId + '/preview-css', data);
    last = {key, promise};

    // A failed request is not worth replaying to the next preview asking.
    promise.catch(() => {
        if (last.promise === promise) {
            last = {key: null, promise: null};
        }
    });

    return promise;
}

/**
 * Follow a theme form and hand over its compiled preview after each change.
 *
 * Changes are debounced, the first preview is asked for at once. A failed
 * request, typically a duplicate slug while one is typed, is skipped: the
 * previous preview stays until the next change compiles.
 *
 * @param formInspector The inspector of the theme form.
 * @param onPreview     Called with every preview received.
 *
 * @return A function that stops following the form.
 */
export function watchThemePreview(formInspector: Object, onPreview: (ThemePreview) => void): () => void {
    let stopped = false;
    let timer = null;

    const load = (data: Object) => {
        const {id} = formInspector;
        if (!id) {
            return;
        }

        loadThemePreviewCss(id, data)
            .then((preview) => {
                if (!stopped) {
                    onPreview(preview);
                }
            })
            .catch(() => {});
    };

    // Deep conversion, so a change anywhere in the form is seen: a palette
    // colour edited in another tab changes the buttons that reference it.
    const dispose = reaction(
        () => toJS(formInspector.formStore.data),
        (data) => {
            clearTimeout(timer);
            timer = setTimeout(() => load(data), DEBOUNCE_MS);
        },
    );

    load(toJS(formInspector.formStore.data));

    return () => {
        stopped = true;
        clearTimeout(timer);
        dispose();
    };
}
