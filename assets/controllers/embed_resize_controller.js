import { Controller } from '@hotwired/stimulus';

/**
 * Embed resize controller — lets a sandboxed code embed size itself.
 *
 * A sandboxed iframe cannot resize its parent, which is the usual reason people
 * abandon sandboxing and paste widgets straight into the page. The bundle owns
 * the document served to that frame (see ThemeExtension::getCodeSrcdoc), so it
 * injects a reporter that posts the content height out; this controller applies
 * it to the frame.
 *
 * Message authentication: a sandboxed frame without allow-same-origin has an
 * opaque origin, so `event.origin` is the string "null" and is worthless for
 * filtering. The message is therefore authenticated by comparing `event.source`
 * with our own iframe's `contentWindow` — an identity check no other document
 * can forge.
 */
export default class extends Controller {
    static targets = ['frame'];

    static values = {
        maxHeight: { type: Number, default: 5000 },
    };

    connect() {
        this._onMessage = this.handleMessage.bind(this);
        window.addEventListener('message', this._onMessage);
    }

    disconnect() {
        window.removeEventListener('message', this._onMessage);
    }

    /**
     * @param {MessageEvent} event
     */
    handleMessage(event) {
        if (!this.hasFrameTarget || event.source !== this.frameTarget.contentWindow) {
            return;
        }

        const data = event.data;
        if (!data || 'iw-embed-height' !== data.type) {
            return;
        }

        const height = Number(data.height);
        if (!Number.isFinite(height) || height <= 0) {
            return;
        }

        // Clamped so a runaway widget cannot grow the page without bound.
        this.element.style.setProperty(
            '--iw-embed-height',
            `${Math.min(Math.ceil(height), this.maxHeightValue)}px`
        );
    }
}
