/*
 * Behaviour of the consent controller, outside any browser or build.
 *
 * The controller is plain ES module code around a Stimulus class, so a
 * stubbed `@hotwired/stimulus`, a localStorage in memory and Node's own
 * EventTarget are enough to run it. Each scenario loads a fresh copy of the
 * module: its state (granted services, mounted embeds) is module-level, as on
 * a real page.
 *
 * Called by ConsentBehaviourTest. Run by hand with:
 *
 *     node tests/js/consent-behaviour.mjs
 *
 * Prints one line per failing scenario and exits non-zero.
 */
import { readFileSync, writeFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const source = readFileSync(join(root, 'assets/controllers/consent_controller.js'), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", 'class Controller {}');
const work = mkdtempSync(join(tmpdir(), 'iw-consent-'));

let copies = 0;
let failures = 0;

/** A fresh page: empty globals, the given storage and queue, a new copy of the module. */
async function page(storedServices = [], queue = undefined) {
    const store = new Map(storedServices.length ? [['iw-consent', JSON.stringify(storedServices)]] : []);
    globalThis.window = globalThis;
    delete globalThis.iwConsent;
    globalThis.iwConsentQueue = queue;
    globalThis.localStorage = {
        getItem: (key) => (store.has(key) ? store.get(key) : null),
        setItem: (key, value) => store.set(key, String(value)),
    };
    globalThis.document = new EventTarget();

    const file = join(work, `consent-${++copies}.mjs`);
    writeFileSync(file, source);
    const Embed = (await import(file)).default;

    return {
        store,
        stored: () => JSON.parse(store.get('iw-consent') ?? '[]'),
        mount(service, mode, remember = true, controller = '', declared = undefined, { facade = false, src = '' } = {}) {
            const frame = { hidden: true, dataset: {}, attributes: {}, setAttribute(k, v) { this.attributes[k] = v; }, getAttribute(k) { return this.attributes[k] ?? null; }, removeAttribute(k) { delete this.attributes[k]; } };
            const embed = Object.assign(new Embed(), {
                serviceValue: service,
                modeValue: mode,
                srcValue: src || (controller ? '' : `https://${service}.example/embed`),
                controllerValue: controller,
                srcdocValue: '',
                rememberValue: remember,
                hasFrameTarget: true,
                frameTarget: frame,
                hasPlaceholderTarget: true,
                placeholderTarget: { hidden: false, dataset: declared === undefined ? {} : { consentDeclared: String(declared) } },
                facadeValue: facade,
                hasFacadeTarget: facade,
                facadeTarget: facade ? { hidden: true } : undefined,
            });
            frame.focus = () => {};
            embed.connect();
            embed.loaded = () => frame.getAttribute('src') !== null;
            embed.frame = frame;

            return embed;
        },
        button(mode) {
            const notice = { textContent: '' };
            const button = Object.assign(new Embed(), {
                serviceValue: '',
                modeValue: mode,
                unavailableValue: 'unavailable',
                clearedValue: 'cleared',
                hasFrameTarget: false,
                hasPlaceholderTarget: false,
                hasNoticeTarget: true,
                noticeTarget: notice,
            });
            button.connect();
            button.notice = () => notice.textContent;

            return button;
        },
    };
}

async function scenario(name, run) {
    try {
        await run();
    } catch (error) {
        failures++;
        console.log(`${name}: ${error.message}`);
    }
}

function expect(condition, message) {
    if (!condition) {
        throw new Error(message);
    }
}

await scenario('a grant from a cookie manager is not stored', async () => {
    const p = await page();
    const embed = p.mount('youtube', 'delegated');
    window.iwConsent.grant('youtube');
    expect(embed.loaded(), 'the embed did not load');
    expect(p.stored().length === 0, `stored ${p.stored()}`);
});

await scenario('a grant queued before the bundle loaded is replayed, and not stored', async () => {
    const p = await page([], [['grant', 'youtube', { remember: false }]]);
    const embed = p.mount('youtube', 'delegated');
    expect(embed.loaded(), 'the queued grant was lost');
    expect(p.stored().length === 0, 'the queued grant was stored');

    window.iwConsentQueue.push(['revoke', 'youtube']);
    expect(!embed.loaded(), 'a push after install did not run');
});

await scenario('an old placeholder click does not outlive a cookie manager', async () => {
    const p = await page(['youtube']);
    const embed = p.mount('youtube', 'delegated');
    expect(!embed.loaded(), 'a stored grant loaded a delegated embed');
});

await scenario('a placeholder click is remembered on a site without a manager', async () => {
    const p = await page();
    const embed = p.mount('youtube', 'placeholder');
    embed.accept();
    expect(embed.loaded(), 'the embed did not load');
    expect(p.stored().includes('youtube'), 'the click was not stored');

    const next = await page(p.stored());
    expect(next.mount('youtube', 'placeholder').loaded(), 'the next page asked again');
});

await scenario('a manager taking the request keeps the decision', async () => {
    const p = await page();
    const embed = p.mount('youtube', 'delegated');
    let opened = false;
    document.addEventListener('iw:consent-request', (event) => {
        opened = event.detail.service === 'youtube';
        event.preventDefault();
    });
    embed.accept();
    expect(opened, 'the manager was not asked');
    expect(!embed.loaded(), 'the embed loaded before the manager decided');
});

await scenario('a blocked manager falls back to the click, for the page only', async () => {
    const p = await page();
    const embed = p.mount('youtube', 'delegated');
    embed.accept();
    expect(embed.loaded(), 'the click did nothing');
    expect(p.stored().length === 0, 'the fallback was stored');
});

await scenario('revoking everything unloads and forgets', async () => {
    const p = await page(['vimeo']);
    const video = p.mount('vimeo', 'placeholder');
    const map = p.mount('openstreetmap', 'placeholder');
    window.iwConsent.grant('openstreetmap');
    expect(video.loaded() && map.loaded(), 'setup');
    window.iwConsent.revoke('*');
    expect(!video.loaded() && !map.loaded(), 'an embed stayed loaded');
    expect(p.stored().length === 0, 'the stored click survived');
});

await scenario('the services of the page are listed once', async () => {
    const p = await page();
    p.mount('youtube', 'delegated');
    p.mount('vimeo', 'delegated');
    p.mount('youtube', 'delegated');
    expect(JSON.stringify(window.iwConsent.services()) === '["vimeo","youtube"]', JSON.stringify(window.iwConsent.services()));
});

await scenario('the manage button opens a manager that answers, silently', async () => {
    const p = await page();
    const button = p.button('delegated');
    document.addEventListener('iw:consent-preferences', (event) => event.preventDefault());
    button.preferences();
    expect(button.notice() === '', `said "${button.notice()}"`);
    expect(window.iwConsent.services().length === 0, 'the button counted as a service');
});

await scenario('the manage button explains a blocked manager', async () => {
    const p = await page();
    const button = p.button('delegated');
    button.preferences();
    expect(button.notice() === 'unavailable', `said "${button.notice()}"`);
});

await scenario('the manage button forgets placeholder choices without a manager', async () => {
    const p = await page(['youtube']);
    const embed = p.mount('youtube', 'placeholder');
    expect(embed.loaded(), 'setup');
    p.button('placeholder').preferences();
    expect(!embed.loaded() && p.stored().length === 0, 'the choice survived');
});

await scenario('a map starts only once its tile server is granted, and stops on revoke', async () => {
    const p = await page();
    const map = p.mount('openstreetmap', 'delegated', true, 'location-map');
    expect(map.frame.dataset.controller === undefined && map.frame.hidden, 'the map started before consent');
    window.iwConsent.grant('openstreetmap');
    expect(map.frame.dataset.controller === 'location-map' && !map.frame.hidden, 'the map did not start');
    window.iwConsent.grant('openstreetmap');
    expect(map.frame.dataset.controller === 'location-map', 'the controller was attached twice');
    window.iwConsent.revoke('openstreetmap');
    expect(map.frame.dataset.controller === undefined && map.frame.hidden, 'the map kept running');
});

await scenario('content on a service the inventory lacks is reported once, under a manager only', async () => {
    const warnings = [];
    const warn = console.warn;
    console.warn = (message) => warnings.push(message);
    try {
        const p = await page();
        p.mount('calendly', 'delegated', true, '', false);
        p.mount('calendly', 'delegated', true, '', false);
        p.mount('youtube', 'delegated', true, '', true);
        p.mount('vimeo', 'delegated');
        p.mount('typeform', 'placeholder', true, '', false);
        expect(warnings.length === 1 && warnings[0].includes('"calendly"'), `warned ${JSON.stringify(warnings)}`);
    } finally {
        console.warn = warn;
    }
});

await scenario('generic content is reported under a manager unless the inventory declares it', async () => {
    const warnings = [];
    const warn = console.warn;
    console.warn = (message) => warnings.push(message);
    try {
        (await page()).mount('embed', 'delegated');
        expect(warnings.length === 1 && warnings[0].includes('"embed"'), `without an inventory, warned ${JSON.stringify(warnings)}`);

        (await page()).mount('embed', 'delegated', true, '', false);
        expect(warnings.length === 2, `undeclared, warned ${JSON.stringify(warnings)}`);

        (await page()).mount('embed', 'delegated', true, '', true);
        (await page()).mount('embed', 'placeholder');
        expect(warnings.length === 2, `declared or without a manager, warned ${JSON.stringify(warnings)}`);
    } finally {
        console.warn = warn;
    }
});

await scenario('behind a facade, a placeholder click both allows and plays', async () => {
    const p = await page();
    const embed = p.mount('youtube', 'placeholder', true, '', undefined, { facade: true });
    embed.accept();
    expect(embed.frame.getAttribute('src') === 'https://youtube.example/embed?autoplay=1', `loaded ${embed.frame.getAttribute('src')}`);
    expect(embed.facadeTarget.hidden && !embed.frame.hidden, 'the facade stayed over the player');
});

await scenario('a manager granting shows the facade, the click plays', async () => {
    const p = await page();
    const embed = p.mount('youtube', 'delegated', true, '', undefined, { facade: true });
    window.iwConsent.grant('youtube');
    expect(!embed.loaded(), 'the player loaded without a click');
    expect(!embed.facadeTarget.hidden && embed.placeholderTarget.hidden, 'the facade did not replace the placeholder');
    embed.play();
    expect(embed.frame.getAttribute('src') === 'https://youtube.example/embed?autoplay=1', `loaded ${embed.frame.getAttribute('src')}`);

    window.iwConsent.revoke('youtube');
    expect(!embed.loaded() && embed.facadeTarget.hidden && !embed.placeholderTarget.hidden, 'revoking left the facade or the player');
    window.iwConsent.grant('youtube');
    expect(!embed.loaded() && !embed.facadeTarget.hidden, 'a later grant played without a click');
});

await scenario('a manager answering a placeholder request is no click to play', async () => {
    const p = await page();
    const embed = p.mount('youtube', 'delegated', true, '', undefined, { facade: true });
    document.addEventListener('iw:consent-request', (event) => event.preventDefault());
    embed.accept();
    window.iwConsent.grant('youtube');
    expect(!embed.loaded() && !embed.facadeTarget.hidden, 'the player started on the manager\'s answer');
});

await scenario('without a consent requirement the facade waits for the click alone', async () => {
    const p = await page();
    const embed = p.mount('vimeo', 'none', true, '', undefined, { facade: true });
    expect(!embed.loaded() && !embed.facadeTarget.hidden, 'the player loaded on page load');
    embed.play();
    expect(embed.loaded(), 'the click did not play');
});

await scenario('a click remembered from a previous page shows the facade, not the player', async () => {
    const p = await page(['youtube']);
    const embed = p.mount('youtube', 'placeholder', true, '', undefined, { facade: true });
    expect(!embed.loaded() && !embed.facadeTarget.hidden, 'the remembered click played the video');
});

await scenario('autoplay joins the playlist of the address', async () => {
    const p = await page();
    const embed = p.mount('youtube', 'none', true, '', undefined, { facade: true, src: 'https://www.youtube.com/embed/videoseries?list=PLCKWohU37CZY' });
    embed.play();
    expect(embed.frame.getAttribute('src') === 'https://www.youtube.com/embed/videoseries?list=PLCKWohU37CZY&autoplay=1', `loaded ${embed.frame.getAttribute('src')}`);
});

process.exit(failures ? 1 : 0);
