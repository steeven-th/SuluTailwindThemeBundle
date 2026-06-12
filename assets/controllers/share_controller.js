import { Controller } from '@hotwired/stimulus';

/**
 * Share controller — powers the share buttons component (_share.html.twig).
 *
 * Progressive enhancement: the native and copy buttons are hidden in markup
 * and only revealed here, so they never show without JavaScript (the email
 * link works on its own).
 *
 * The native button uses the Web Share API. When the API is unavailable it
 * falls back to copying the link — unless a dedicated copy button is already
 * shown, in which case it stays hidden to avoid two copy buttons.
 *
 * Values:
 *   - url: URL to share (falls back to the current location)
 *   - title: title passed to the share sheet
 *   - copiedText: feedback announced after copying the link
 *
 * Targets:
 *   - native: the Web Share API button
 *   - copy: the copy-link button
 *   - feedback: live region receiving the copy feedback
 */
export default class extends Controller {
    static targets = ['native', 'copy', 'feedback'];

    static values = {
        url: String,
        title: String,
        copiedText: String,
    };

    /** How long the copy feedback stays visible (ms). */
    static FEEDBACK_DURATION = 2000;

    connect() {
        this._canShare = typeof navigator.share === 'function';

        // Reveal the buttons JS can power (they were hidden in markup to avoid
        // dead controls before enhancement).
        if (this.hasNativeTarget && (this._canShare || !this.hasCopyTarget)) {
            this.nativeTarget.hidden = false;
        }
        if (this.hasCopyTarget) {
            this.copyTarget.hidden = false;
        }
    }

    disconnect() {
        clearTimeout(this._feedbackTimer);
    }

    /**
     * Open the native share sheet, or copy the link when the API is missing.
     */
    async share() {
        if (!this._canShare) {
            await this.copy();

            return;
        }

        try {
            await navigator.share({ title: this.titleValue, url: this._url() });
        } catch {
            // Dismissed by the user — nothing to do.
        }
    }

    /**
     * Copy the share URL to the clipboard and flash the feedback.
     */
    async copy() {
        const url = this._url();

        try {
            await navigator.clipboard.writeText(url);
        } catch {
            // Clipboard API unavailable (e.g. insecure context): legacy fallback.
            const textarea = document.createElement('textarea');
            textarea.value = url;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            textarea.remove();
        }

        this._flashFeedback();
    }

    /**
     * Resolve the URL to share.
     *
     * @returns {string}
     * @private
     */
    _url() {
        return this.urlValue || window.location.href;
    }

    /**
     * Announce the copy feedback in the live region, then clear it.
     *
     * @private
     */
    _flashFeedback() {
        if (!this.hasFeedbackTarget) {
            return;
        }

        this.feedbackTarget.textContent = this.copiedTextValue;
        clearTimeout(this._feedbackTimer);
        this._feedbackTimer = setTimeout(() => {
            this.feedbackTarget.textContent = '';
        }, this.constructor.FEEDBACK_DURATION);
    }
}
