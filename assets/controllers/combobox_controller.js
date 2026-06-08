import { Controller } from '@hotwired/stimulus';

/**
 * Combobox controller — transforms a native <select> (single or multiple) into
 * a modern dropdown with search and styled options.
 *
 * The original <select> is hidden and kept in sync for form submission.
 *
 * Values:
 *   - multiple: whether the select allows multiple selections
 *   - placeholder: placeholder text when nothing is selected
 *   - searchPlaceholder: placeholder for the search input
 *
 * Targets:
 *   - select: the original <select> element (hidden)
 *   - trigger: the clickable button
 *   - dropdown: the floating dropdown panel
 *   - search: the search input
 *   - list: the options list container
 *   - display: the text/tags display in the trigger
 */
export default class extends Controller {
    static targets = ['select', 'trigger', 'dropdown', 'search', 'list', 'display'];

    static values = {
        multiple: { type: Boolean, default: false },
        placeholder: { type: String, default: '—' },
        searchPlaceholder: { type: String, default: 'Search...' },
        emptyLabel: { type: String, default: '—' },
    };

    /** @type {boolean} */
    isOpen = false;

    connect() {
        // The select target is a wrapper div; find the actual <select> inside it
        this._nativeSelect = this.selectTarget.querySelector('select');
        if (!this._nativeSelect) return;

        this._buildOptions();
        this._updateDisplay();
        this._onDocumentClick = this._handleDocumentClick.bind(this);
        this._onKeydown = this._handleKeydown.bind(this);
        document.addEventListener('click', this._onDocumentClick);
    }

    disconnect() {
        document.removeEventListener('click', this._onDocumentClick);
        document.removeEventListener('keydown', this._onKeydown);
    }

    /** Toggle dropdown open/close. */
    toggle() {
        this.isOpen ? this.close() : this.open();
    }

    /** Open the dropdown. */
    open() {
        this.isOpen = true;
        this.dropdownTarget.classList.remove('hidden');
        document.addEventListener('keydown', this._onKeydown);

        if (this.hasSearchTarget) {
            this.searchTarget.value = '';
            this._filterOptions('');
            // Delay focus to avoid the click event closing the dropdown
            requestAnimationFrame(() => this.searchTarget.focus());
        }
    }

    /** Close the dropdown. */
    close() {
        this.isOpen = false;
        this.dropdownTarget.classList.add('hidden');
        document.removeEventListener('keydown', this._onKeydown);
    }

    /**
     * Handle option selection (single mode: click on an option item).
     *
     * @param {Event} event
     */
    selectOption(event) {
        const item = event.currentTarget;
        const value = item.dataset.value;

        if (this.multipleValue) {
            // Toggle the checkbox
            const checkbox = item.querySelector('input[type="checkbox"]');
            if (checkbox && event.target !== checkbox) {
                checkbox.checked = !checkbox.checked;
            }
            this._syncFromCheckboxes();
        } else {
            // Single: select and close
            this._nativeSelect.value = value;
            this._markActive(value);
            this.close();
        }

        this._updateDisplay();
        // Dispatch change event on the native select
        this._nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
    }

    /**
     * Handle checkbox change in multiple mode.
     *
     * @param {Event} event
     */
    onCheckboxChange(event) {
        // Prevent the click from bubbling to selectOption
        event.stopPropagation();
        this._syncFromCheckboxes();
        this._updateDisplay();
        this._nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
    }

    /**
     * Filter options via search input.
     *
     * @param {Event} event
     */
    onSearch(event) {
        this._filterOptions(event.target.value.toLowerCase());
    }

    /**
     * Remove a tag (multiple mode).
     *
     * @param {Event} event
     */
    removeTag(event) {
        event.stopPropagation();
        const value = event.currentTarget.dataset.value;
        const option = this._nativeSelect.querySelector(`option[value="${CSS.escape(value)}"]`);
        if (option) option.selected = false;

        const checkbox = this.listTarget.querySelector(`input[type="checkbox"][value="${CSS.escape(value)}"]`);
        if (checkbox) checkbox.checked = false;

        this._updateDisplay();
        this._nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // ─── Private ──────────────────────────────────────────────

    /**
     * Build the options list from the native select.
     *
     * @private
     */
    _buildOptions() {
        const options = [...this._nativeSelect.options];
        this.listTarget.innerHTML = '';

        options.forEach((opt) => {
            const item = document.createElement('div');
            item.className = 'iw-combobox__item';
            item.dataset.value = opt.value;
            item.dataset.action = 'click->combobox#selectOption';

            // Display label: use emptyLabel for options with empty value and no text
            const label = (opt.value === '' && opt.textContent.trim() === '')
                ? this.emptyLabelValue
                : opt.textContent;

            if (this.multipleValue) {
                item.innerHTML = `
                    <label class="iw-combobox__label">
                        <input type="checkbox" value="${opt.value}"
                            ${opt.selected ? 'checked' : ''}
                            data-action="change->combobox#onCheckboxChange"
                            class="iw-form__check">
                        <span>${label}</span>
                    </label>
                `;
            } else {
                item.innerHTML = `<span>${label}</span>`;
                if (opt.selected) {
                    item.classList.add('iw-combobox__item--active');
                }
            }

            this.listTarget.appendChild(item);
        });
    }

    /**
     * Update the trigger display (text for single, tags for multiple).
     *
     * @private
     */
    _updateDisplay() {
        const selected = [...this._nativeSelect.selectedOptions];
        this.displayTarget.innerHTML = '';

        if (selected.length === 0 || (selected.length === 1 && selected[0].value === '')) {
            this.displayTarget.innerHTML = `<span class="iw-combobox__placeholder">${this.placeholderValue}</span>`;
            return;
        }

        if (this.multipleValue) {
            selected.forEach((opt) => {
                const tag = document.createElement('span');
                tag.className = 'iw-combobox__tag';
                tag.innerHTML = `
                    ${opt.textContent}
                    <button type="button" data-value="${opt.value}"
                            data-action="click->combobox#removeTag"
                            class="iw-combobox__tag-remove" aria-label="Remove">&times;</button>
                `;
                this.displayTarget.appendChild(tag);
            });
        } else {
            this.displayTarget.textContent = selected[0].textContent;
        }
    }

    /**
     * Mark the active option in single mode.
     *
     * @param {string} value
     * @private
     */
    _markActive(value) {
        this.listTarget.querySelectorAll('.iw-combobox__item').forEach((item) => {
            item.classList.toggle('iw-combobox__item--active', item.dataset.value === value);
        });
    }

    /**
     * Sync native select from checkboxes state (multiple mode).
     *
     * @private
     */
    _syncFromCheckboxes() {
        const checkboxes = this.listTarget.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach((cb) => {
            const option = this._nativeSelect.querySelector(`option[value="${CSS.escape(cb.value)}"]`);
            if (option) option.selected = cb.checked;
        });
    }

    /**
     * Filter options by search term.
     *
     * @param {string} term
     * @private
     */
    _filterOptions(term) {
        this.listTarget.querySelectorAll('.iw-combobox__item').forEach((item) => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(term) ? '' : 'none';
        });
    }

    /**
     * Close when clicking outside.
     *
     * @param {Event} event
     * @private
     */
    _handleDocumentClick(event) {
        if (!this.element.contains(event.target)) {
            this.close();
        }
    }

    /**
     * Handle keyboard navigation.
     *
     * @param {KeyboardEvent} event
     * @private
     */
    _handleKeydown(event) {
        if (event.key === 'Escape') {
            this.close();
        }
    }
}
