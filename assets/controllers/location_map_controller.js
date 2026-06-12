import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/**
 * Location map controller — renders an interactive Leaflet map for a Sulu
 * location field (replaces the legacy OpenStreetMap iframes).
 *
 * Features:
 * - Lazy init through an IntersectionObserver: tiles are only requested when
 *   the map is close to the viewport (same spirit as the old loading="lazy").
 * - Cooperative gestures ("ctrl" scroll-zoom mode): the page keeps scrolling
 *   over the map unless Ctrl/Cmd is held; on touch devices one finger scrolls
 *   the page and two fingers pan/zoom the map. A translated hint overlay is
 *   shown when the user tries a blocked gesture.
 * - Themed SVG marker (DivIcon) colored through --iw-location-map-marker-color,
 *   which also avoids the classic Leaflet marker-image bundling issue.
 * - Optional POI popup on marker click: title, formatted address, external
 *   "open in maps" link.
 *
 * Tile URL and attribution are resolved server-side (theme config) and passed
 * as values. Attribution is always displayed (OSM/Carto tile usage policies).
 */
export default class extends Controller {
    static values = {
        lat: Number,
        lng: Number,
        zoom: { type: Number, default: 15 },
        tileUrl: String,
        attribution: String,
        /** Scroll-zoom behavior: 'ctrl' (cooperative), 'always' or 'never'. */
        scrollZoom: { type: String, default: 'ctrl' },
        /** Custom marker image URL (media library); empty = themed SVG pin. */
        markerUrl: { type: String, default: '' },
        popup: { type: Boolean, default: true },
        popupTitle: { type: String, default: '' },
        popupAddress: { type: String, default: '' },
        externalUrl: { type: String, default: '' },
        externalLabel: { type: String, default: '' },
        hintScroll: { type: String, default: '' },
        hintScrollMac: { type: String, default: '' },
        hintTouch: { type: String, default: '' },
    };

    /** @type {L.Map|null} Leaflet map instance */
    map = null;

    /** @type {IntersectionObserver|null} Lazy-init observer */
    _observer = null;

    /** @type {HTMLElement|null} Gesture hint overlay */
    _hint = null;

    /** @type {number|null} Hint auto-hide timeout id */
    _hintTimeout = null;

    /** @type {Function|null} Wheel interceptor (cooperative mode) */
    _onWheel = null;

    /** @type {Function|null} Touch interceptor (cooperative mode) */
    _onTouchStart = null;

    /** @type {Function|null} Touch-move hint trigger (cooperative mode) */
    _onTouchMove = null;

    connect() {
        // Defer the whole Leaflet init until the map is near the viewport.
        if ('IntersectionObserver' in window) {
            this._observer = new IntersectionObserver(
                (entries) => {
                    if (entries.some((entry) => entry.isIntersecting)) {
                        this._observer.disconnect();
                        this._observer = null;
                        this._initMap();
                    }
                },
                { rootMargin: '200px' }
            );
            this._observer.observe(this.element);
        } else {
            this._initMap();
        }
    }

    disconnect() {
        this._observer?.disconnect();
        this._observer = null;

        if (this._onWheel) {
            this.element.removeEventListener('wheel', this._onWheel, { capture: true });
            this._onWheel = null;
        }
        if (this._onTouchStart) {
            this.element.removeEventListener('touchstart', this._onTouchStart, { capture: true });
            this._onTouchStart = null;
        }
        if (this._onTouchMove) {
            this.element.removeEventListener('touchmove', this._onTouchMove, { capture: true });
            this._onTouchMove = null;
        }
        if (this._hintTimeout) {
            clearTimeout(this._hintTimeout);
            this._hintTimeout = null;
        }

        this.map?.remove();
        this.map = null;
        this._hint = null;
    }

    /**
     * Create the Leaflet map, tile layer, marker and gesture handling.
     */
    _initMap() {
        const cooperative = this.scrollZoomValue === 'ctrl';

        // Leaflet needs a dedicated child node: the controller element keeps
        // the hint overlay and capture-phase listeners outside the map root.
        const container = document.createElement('div');
        container.className = 'iw-location-map__canvas';
        this.element.appendChild(container);

        if (cooperative) {
            this.element.classList.add('iw-location-map--cooperative');
        }

        this.map = L.map(container, {
            center: [this.latValue, this.lngValue],
            zoom: this.zoomValue,
            scrollWheelZoom: this.scrollZoomValue !== 'never',
            // In cooperative mode one-finger dragging is toggled on touchstart.
            dragging: true,
            touchZoom: true,
        });

        L.tileLayer(this.tileUrlValue, {
            attribution: this.attributionValue,
            maxZoom: 19,
        }).addTo(this.map);

        const marker = L.marker([this.latValue, this.lngValue], {
            icon: this._buildMarkerIcon(),
            keyboard: true,
            title: this.popupTitleValue || undefined,
        }).addTo(this.map);

        const popupContent = this._buildPopupContent();
        if (this.popupValue && popupContent) {
            marker.bindPopup(popupContent, { className: 'iw-location-map__popup' });
        }

        if (cooperative) {
            this._setupCooperativeGestures();
        }
    }

    /**
     * Build the POI marker icon: the custom media-library image when one is
     * configured, the themed SVG pin otherwise.
     *
     * The SVG pin is a DivIcon with an inline SVG inheriting currentColor, so
     * its color is fully driven by the --iw-location-map-marker-color CSS
     * variable. The custom image keeps the same box and bottom-center anchor.
     *
     * @returns {L.DivIcon} Marker icon
     */
    _buildMarkerIcon() {
        if (this.markerUrlValue) {
            const img = document.createElement('img');
            img.src = this.markerUrlValue;
            img.alt = '';

            return L.divIcon({
                className: 'iw-location-map__marker iw-location-map__marker--custom',
                html: img,
                iconSize: [36, 36],
                iconAnchor: [18, 36],
                popupAnchor: [0, -36],
            });
        }

        return L.divIcon({
            className: 'iw-location-map__marker',
            html:
                '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' +
                '<path d="M12 2a8 8 0 0 0-8 8c0 5.4 6.4 11 7.3 11.8a1 1 0 0 0 1.4 0C13.6 21 20 15.4 20 10a8 8 0 0 0-8-8zm0 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/>' +
                '</svg>',
            iconSize: [36, 36],
            iconAnchor: [18, 34],
            popupAnchor: [0, -32],
        });
    }

    /**
     * Build the popup DOM (title, address, external link) with safe text nodes.
     *
     * @returns {HTMLElement|null} Popup content, or null when nothing to show
     */
    _buildPopupContent() {
        const hasContent = this.popupTitleValue || this.popupAddressValue || this.externalUrlValue;
        if (!hasContent) return null;

        const content = document.createElement('div');
        content.className = 'iw-location-map__popup-content';

        if (this.popupTitleValue) {
            const title = document.createElement('strong');
            title.className = 'iw-location-map__popup-title';
            title.textContent = this.popupTitleValue;
            content.appendChild(title);
        }

        if (this.popupAddressValue) {
            const address = document.createElement('p');
            address.className = 'iw-location-map__popup-address';
            address.textContent = this.popupAddressValue;
            content.appendChild(address);
        }

        if (this.externalUrlValue && this.externalLabelValue) {
            const link = document.createElement('a');
            link.className = 'iw-location-map__popup-link';
            link.href = this.externalUrlValue;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.textContent = `${this.externalLabelValue} ↗`;
            content.appendChild(link);
        }

        return content;
    }

    /**
     * Cooperative gestures: page scroll wins over map zoom/pan unless the
     * user opts in (Ctrl/Cmd + wheel on desktop, two fingers on touch).
     *
     * Wheel events are intercepted in the capture phase on the controller
     * element (parent of the Leaflet container): without the modifier key the
     * event never reaches Leaflet and the page scrolls naturally. Touch
     * dragging is toggled per gesture based on the number of fingers, with
     * `touch-action: pan-x pan-y` restored via the --cooperative class so
     * one-finger scrolling keeps working over the map.
     */
    _setupCooperativeGestures() {
        this._onWheel = (event) => {
            if (event.ctrlKey || event.metaKey) {
                this._hideHint();
                return;
            }
            event.stopPropagation();
            this._showHint(this._scrollHintLabel());
        };
        this.element.addEventListener('wheel', this._onWheel, { capture: true, passive: true });

        this._onTouchStart = (event) => {
            if (event.touches.length >= 2) {
                this.map.dragging.enable();
                this._hideHint();
            } else {
                this.map.dragging.disable();
            }
        };
        this.element.addEventListener('touchstart', this._onTouchStart, { capture: true, passive: true });

        // Show the hint only when the user actually tries to pan with one
        // finger — a simple tap (marker click) must not flash it.
        this._onTouchMove = (event) => {
            if (event.touches.length === 1) {
                this._showHint(this.hintTouchValue);
            }
        };
        this.element.addEventListener('touchmove', this._onTouchMove, { capture: true, passive: true });
    }

    /**
     * Pick the scroll hint matching the platform modifier key: the Cmd (⌘)
     * variant on macOS, the Ctrl variant everywhere else. Both labels are
     * translated server-side and passed as values.
     *
     * @returns {string} Translated hint text
     */
    _scrollHintLabel() {
        const platform = navigator.userAgentData?.platform || navigator.platform || '';
        const isMac = /mac/i.test(platform);

        return (isMac && this.hintScrollMacValue) ? this.hintScrollMacValue : this.hintScrollValue;
    }

    /**
     * Show the gesture hint overlay with the given label, then auto-hide it.
     *
     * @param {string} label Translated hint text
     */
    _showHint(label) {
        if (!label) return;

        if (!this._hint) {
            this._hint = document.createElement('div');
            this._hint.className = 'iw-location-map__hint';
            this._hint.setAttribute('aria-hidden', 'true');
            const text = document.createElement('span');
            text.className = 'iw-location-map__hint-text';
            this._hint.appendChild(text);
            this.element.appendChild(this._hint);
        }

        this._hint.querySelector('.iw-location-map__hint-text').textContent = label;
        this._hint.classList.add('iw-location-map__hint--visible');

        if (this._hintTimeout) clearTimeout(this._hintTimeout);
        this._hintTimeout = setTimeout(() => this._hideHint(), 1500);
    }

    /**
     * Hide the gesture hint overlay.
     */
    _hideHint() {
        this._hint?.classList.remove('iw-location-map__hint--visible');
    }
}
