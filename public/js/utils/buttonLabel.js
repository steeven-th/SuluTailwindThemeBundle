// @flow
import {translate} from 'sulu-admin-bundle/utils';
import {linkTypeRegistry} from 'sulu-admin-bundle/containers/Link';
import {ResourceRequester} from 'sulu-admin-bundle/services';

/**
 * The name of what a link points to, as Sulu's own link field shows it: the
 * display properties of its link type, read from the resource. Empty for an
 * external link, which has its address, and for a page deleted since.
 *
 * @param {Object} link - A stored link value
 * @param {string} locale - The content locale to read the name in
 * @return {Promise<string>}
 */
export function loadLinkName(link: ?Object, locale: string): Promise<string> {
    if (!link || !link.href || !link.provider || link.provider === 'external') {
        return Promise.resolve('');
    }

    const options = linkTypeRegistry.getOptions(link.provider);
    if (!options || !options.resourceKey || !options.displayProperties || !options.displayProperties.length) {
        return Promise.resolve('');
    }

    return ResourceRequester.get(options.resourceKey, {id: link.href, locale})
        .then((data) => (options.displayProperties || [])
            .map((key) => data[key])
            .filter((part) => typeof part === 'string' && part.trim())
            .join(' '))
        .catch(() => '');
}

/**
 * The label of a button as the website writes it: the title attribute of the
 * link, then the name of what it points to, then the address of an external
 * link. Until the name has loaded, a page or a media is named by its kind,
 * its id would say nothing.
 *
 * @param {Object} link - A stored link value
 * @param {string} linkName - What loadLinkName() returned, or empty
 * @return {string} Empty when the button has no link at all
 */
export function buttonLabel(link: ?Object, linkName: string): string {
    if (!link) {
        return '';
    }
    if (typeof link.title === 'string' && link.title.trim()) {
        return link.title.trim();
    }
    if (linkName) {
        return linkName;
    }
    if (!link.href) {
        return '';
    }

    return link.provider === 'external'
        ? String(link.href)
        : translate('iw_sulu_tailwind_theme.button_summary_' + (link.provider === 'media' ? 'media' : 'page'));
}
