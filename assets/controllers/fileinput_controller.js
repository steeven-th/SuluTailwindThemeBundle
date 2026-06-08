import { Controller } from '@hotwired/stimulus';

/**
 * File input controller — transforms a native <input type="file"> into a styled
 * component with file badges, individual removal, and cumulative file addition.
 *
 * The original input is hidden and a proxy input is used for the file dialog.
 * A DataTransfer object accumulates files across multiple selections.
 *
 * Reads from the original input:
 *   - accept: allowed MIME types (e.g. "image/*,audio/*")
 *   - multiple: whether multiple files are allowed
 *   - data-max: maximum number of files allowed
 *
 * Values:
 *   - maxSizeLabel: server max upload size (passed from Twig)
 *
 * Targets:
 *   - input: the original <input type="file"> (kept in sync for form submission)
 *   - proxy: a hidden proxy input that opens the file dialog
 *   - list: container for file badges
 *   - info: container for hint text (max files, max size, accepted types)
 */
export default class extends Controller {
    static targets = ['input', 'proxy', 'list', 'info', 'error', 'dropzone'];

    static values = {
        maxSizeLabel: { type: String, default: '' },
        maxSizeBytes: { type: Number, default: 0 },
        maxFilesMessage: { type: String, default: 'Maximum number of files reached.' },
        maxSizeMessage: { type: String, default: 'File too large.' },
    };

    /** @type {DataTransfer} Accumulated files */
    _dataTransfer = new DataTransfer();

    /** @type {number} Max files allowed (0 = unlimited) */
    _maxFiles = 0;

    connect() {
        const nativeInput = this._getNativeInput();
        if (!nativeInput) return;

        // Read constraints from native input attributes
        const accept = nativeInput.getAttribute('accept') || '';
        const multiple = nativeInput.hasAttribute('multiple');
        this._maxFiles = parseInt(nativeInput.dataset.max || '0', 10);

        // Configure proxy input
        if (accept) this.proxyTarget.setAttribute('accept', accept);
        if (multiple) this.proxyTarget.setAttribute('multiple', '');

        // Prevent the parent <label> from triggering the hidden native input on click
        const parentLabel = this.element.closest('label');
        if (parentLabel) {
            parentLabel.addEventListener('click', (e) => {
                if (e.target === parentLabel || parentLabel.contains(this.element)) {
                    e.preventDefault();
                }
            });
        }
        // Also remove the for attribute from any label pointing to the native input
        if (nativeInput.id) {
            const associatedLabel = document.querySelector(`label[for="${nativeInput.id}"]`);
            if (associatedLabel) {
                associatedLabel.removeAttribute('for');
            }
        }

        this._syncFromInput();
        this._render();
        this._renderInfo(accept);
    }

    /** Open the file dialog via the proxy input. */
    openDialog() {
        this.proxyTarget.click();
    }

    /**
     * Handle new files selected from the dialog.
     * Appends to existing files instead of replacing.
     *
     * @param {Event} event
     */
    onFilesSelected(event) {
        const newFiles = event.target.files;
        let limitReached = false;
        let sizeExceeded = false;
        const maxBytes = this.maxSizeBytesValue;

        for (let i = 0; i < newFiles.length; i++) {
            // Check max files limit
            if (this._maxFiles > 0 && this._dataTransfer.files.length >= this._maxFiles) {
                limitReached = true;
                break;
            }
            // Check individual file size against server max
            if (maxBytes > 0 && newFiles[i].size > maxBytes) {
                sizeExceeded = true;
                continue;
            }
            // Avoid duplicates by name + size
            if (!this._hasFile(newFiles[i])) {
                this._dataTransfer.items.add(newFiles[i]);
            }
        }

        this._syncToInput();
        this._render();
        this._renderError(limitReached, sizeExceeded);

        // Reset proxy so the same file can be re-selected
        this.proxyTarget.value = '';
    }

    /**
     * Remove a file by index.
     *
     * @param {Event} event
     */
    removeFile(event) {
        const index = parseInt(event.currentTarget.dataset.index, 10);
        this._dataTransfer.items.remove(index);
        this._syncToInput();
        this._render();
        this._renderError(false);
    }

    /** Prevent default on dragover to allow drop. */
    onDragOver(event) {
        event.preventDefault();
    }

    /** Add visual feedback when dragging over the dropzone. */
    onDragEnter(event) {
        event.preventDefault();
        if (this.hasDropzoneTarget) {
            this.dropzoneTarget.classList.add('iw-fileinput__dropzone--dragover');
        }
    }

    /** Remove visual feedback when leaving the dropzone. */
    onDragLeave(event) {
        if (this.hasDropzoneTarget) {
            this.dropzoneTarget.classList.remove('iw-fileinput__dropzone--dragover');
        }
    }

    /**
     * Handle files dropped onto the dropzone.
     *
     * @param {DragEvent} event
     */
    onDrop(event) {
        event.preventDefault();
        if (this.hasDropzoneTarget) {
            this.dropzoneTarget.classList.remove('iw-fileinput__dropzone--dragover');
        }

        const droppedFiles = event.dataTransfer.files;
        if (!droppedFiles.length) return;

        let limitReached = false;
        let sizeExceeded = false;
        const maxBytes = this.maxSizeBytesValue;

        for (let i = 0; i < droppedFiles.length; i++) {
            if (this._maxFiles > 0 && this._dataTransfer.files.length >= this._maxFiles) {
                limitReached = true;
                break;
            }
            if (maxBytes > 0 && droppedFiles[i].size > maxBytes) {
                sizeExceeded = true;
                continue;
            }
            if (!this._hasFile(droppedFiles[i])) {
                this._dataTransfer.items.add(droppedFiles[i]);
            }
        }

        this._syncToInput();
        this._render();
        this._renderError(limitReached, sizeExceeded);
    }

    // ─── Private ──────────────────────────────────────────────

    /**
     * Get the actual native <input type="file"> from the wrapper div.
     *
     * @returns {HTMLInputElement|null}
     * @private
     */
    _getNativeInput() {
        return this.inputTarget.querySelector('input[type="file"]') || this.inputTarget;
    }

    /**
     * Copy existing files from the original input into the DataTransfer.
     *
     * @private
     */
    _syncFromInput() {
        const input = this._getNativeInput();
        const files = input.files;
        for (let i = 0; i < files.length; i++) {
            this._dataTransfer.items.add(files[i]);
        }
    }

    /**
     * Write accumulated files back to the original input.
     *
     * @private
     */
    _syncToInput() {
        const input = this._getNativeInput();
        input.files = this._dataTransfer.files;
    }

    /**
     * Check if a file with the same name and size already exists.
     *
     * @param {File} file
     * @returns {boolean}
     * @private
     */
    _hasFile(file) {
        const files = this._dataTransfer.files;
        for (let i = 0; i < files.length; i++) {
            if (files[i].name === file.name && files[i].size === file.size) {
                return true;
            }
        }
        return false;
    }

    /**
     * Render file badges in the list target.
     *
     * @private
     */
    _render() {
        const files = this._dataTransfer.files;
        this.listTarget.innerHTML = '';

        if (files.length === 0) return;

        for (let i = 0; i < files.length; i++) {
            const badge = document.createElement('span');
            badge.className = 'iw-fileinput__badge';

            const icon = this._getFileIcon(files[i]);
            const size = this._formatSize(files[i].size);

            badge.innerHTML = `
                <span class="iw-fileinput__badge-icon">${icon}</span>
                <span class="iw-fileinput__badge-name">${files[i].name}</span>
                <span class="iw-fileinput__badge-size">${size}</span>
                <button type="button" data-index="${i}"
                        data-action="fileinput#removeFile"
                        class="iw-fileinput__badge-remove" aria-label="Remove">&times;</button>
            `;
            this.listTarget.appendChild(badge);
        }
    }

    /**
     * Render info hints (accepted types, max files, max upload size).
     *
     * @param {string} accept
     * @private
     */
    _renderInfo(accept) {
        if (!this.hasInfoTarget) return;

        const hints = [];

        // Accepted file types
        if (accept) {
            const types = accept.split(',').map((t) => {
                t = t.trim();
                if (t === 'image/*') return 'Images';
                if (t === 'video/*') return 'Vidéo';
                if (t === 'audio/*') return 'Audio';
                return t;
            });
            hints.push(types.join(', '));
        }

        // Max files
        if (this._maxFiles > 0) {
            hints.push(`Max. ${this._maxFiles} fichier${this._maxFiles > 1 ? 's' : ''}`);
        }

        // Max upload size (from server via Twig)
        if (this.maxSizeLabelValue) {
            hints.push(`Max. ${this.maxSizeLabelValue}`);
        }

        this.infoTarget.textContent = hints.length > 0 ? hints.join(' · ') : '';
    }

    /**
     * Show or hide the max files error message.
     *
     * @param {boolean} show
     * @private
     */
    _renderError(limitReached, sizeExceeded = false) {
        if (!this.hasErrorTarget) return;

        const messages = [];
        if (limitReached && this._maxFiles > 0) {
            messages.push(this.maxFilesMessageValue);
        }
        if (sizeExceeded) {
            messages.push(this.maxSizeMessageValue.replace('{size}', this.maxSizeLabelValue));
        }

        if (messages.length > 0) {
            this.errorTarget.textContent = messages.join(' ');
            this.errorTarget.classList.remove('hidden');
        } else {
            this.errorTarget.textContent = '';
            this.errorTarget.classList.add('hidden');
        }
    }

    /**
     * Get an SVG icon based on file MIME type.
     *
     * @param {File} file
     * @returns {string}
     * @private
     */
    _getFileIcon(file) {
        const type = file.type || '';
        if (type.startsWith('image/')) {
            return '<svg class="iw-fileinput__badge-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>';
        }
        if (type.startsWith('video/')) {
            return '<svg class="iw-fileinput__badge-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M10 9l5 3-5 3V9z"/></svg>';
        }
        if (type.startsWith('audio/')) {
            return '<svg class="iw-fileinput__badge-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>';
        }
        if (type.includes('pdf')) {
            return '<svg class="iw-fileinput__badge-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M10 13h4"/><path d="M10 17h4"/></svg>';
        }
        return '<svg class="iw-fileinput__badge-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg>';
    }

    /**
     * Format file size to human-readable string.
     *
     * @param {number} bytes
     * @returns {string}
     * @private
     */
    _formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }
}
