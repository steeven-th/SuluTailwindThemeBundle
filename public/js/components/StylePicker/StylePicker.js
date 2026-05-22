// @flow
import React from 'react';
import {translate} from 'sulu-admin-bundle/utils';
import {getSuluPrimaryColor, getSuluPrimaryAlpha, getSuluPrimaryTint} from '../../utils/suluColors';

/**
 * SVG wireframe renderers for each layout style.
 * Keys match the style keys from ThemeAdmin::BLOCK_STYLE_OPTIONS
 * and Twig template filenames (_style_{key}.html.twig).
 */
const WIREFRAME_RENDERERS = {
    // ── text styles ──────────────────────────────────────────────
    one_column: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="8" y="8" width="55" height="6" rx="2" fill={accent} />
            <rect x="8" y="18" width="40" height="4" rx="2" fill={fill} opacity="0.6" />
            <line x1="8" y1="28" x2="112" y2="28" stroke={fill} strokeWidth="1" opacity="0.3" />
            <rect x="8" y="34" width="100" height="3" rx="2" fill={fill} />
            <rect x="8" y="41" width="95" height="3" rx="2" fill={fill} />
            <rect x="8" y="48" width="88" height="3" rx="2" fill={fill} />
            <rect x="8" y="58" width="104" height="3" rx="2" fill={fill} />
            <rect x="8" y="65" width="80" height="3" rx="2" fill={fill} />
        </svg>
    ),
    two_columns: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="30" y="5" width="60" height="6" rx="2" fill={accent} />
            <rect x="5" y="18" width="52" height="3" rx="2" fill={fill} />
            <rect x="5" y="24" width="50" height="3" rx="2" fill={fill} />
            <rect x="5" y="30" width="48" height="3" rx="2" fill={fill} />
            <rect x="5" y="36" width="52" height="3" rx="2" fill={fill} />
            <rect x="63" y="18" width="52" height="3" rx="2" fill={fill} />
            <rect x="63" y="24" width="50" height="3" rx="2" fill={fill} />
            <rect x="63" y="30" width="48" height="3" rx="2" fill={fill} />
            <rect x="63" y="36" width="52" height="3" rx="2" fill={fill} />
            <line x1="60" y1="16" x2="60" y2="42" stroke={fill} strokeWidth="1" opacity="0.3" />
        </svg>
    ),
    quote: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="10" y="10" width="4" height="50" rx="2" fill={accent} />
            <rect x="22" y="15" width="80" height="5" rx="2" fill={fill} />
            <rect x="22" y="25" width="70" height="5" rx="2" fill={fill} />
            <rect x="22" y="35" width="75" height="5" rx="2" fill={fill} />
            <rect x="22" y="50" width="40" height="4" rx="2" fill={accent} opacity="0.5" />
        </svg>
    ),
    // ── text_images styles ───────────────────────────────────────
    classic: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="5" y="10" width="45" height="60" rx="3" fill={fill} />
            <rect x="58" y="15" width="50" height="5" rx="2" fill={accent} />
            <rect x="58" y="26" width="55" height="3" rx="2" fill={fill} />
            <rect x="58" y="33" width="50" height="3" rx="2" fill={fill} />
            <rect x="58" y="40" width="45" height="3" rx="2" fill={fill} />
        </svg>
    ),
    overlay: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="5" y="5" width="110" height="70" rx="3" fill={fill} opacity="0.5" />
            <rect x="15" y="30" width="60" height="6" rx="2" fill={accent} />
            <rect x="15" y="42" width="50" height="3" rx="2" fill="#fff" />
            <rect x="15" y="50" width="45" height="3" rx="2" fill="#fff" />
        </svg>
    ),
    fullwidth: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="0" y="0" width="120" height="45" rx="0" fill={fill} opacity="0.4" />
            <rect x="10" y="52" width="60" height="5" rx="2" fill={accent} />
            <rect x="10" y="62" width="100" height="3" rx="2" fill={fill} />
            <rect x="10" y="69" width="90" height="3" rx="2" fill={fill} />
        </svg>
    ),
    mosaic: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="5" y="5" width="52" height="35" rx="3" fill={fill} />
            <rect x="63" y="5" width="52" height="35" rx="3" fill={fill} opacity="0.7" />
            <rect x="5" y="45" width="52" height="30" rx="3" fill={fill} opacity="0.7" />
            <rect x="63" y="45" width="52" height="30" rx="3" fill={fill} />
        </svg>
    ),
    sidebar: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="5" y="5" width="30" height="40" rx="3" fill={fill} />
            <rect x="42" y="5" width="50" height="5" rx="2" fill={accent} />
            <rect x="42" y="15" width="72" height="3" rx="2" fill={fill} />
            <rect x="42" y="22" width="65" height="3" rx="2" fill={fill} />
            <rect x="42" y="29" width="70" height="3" rx="2" fill={fill} />
            <rect x="42" y="36" width="68" height="3" rx="2" fill={fill} />
            <rect x="42" y="43" width="72" height="3" rx="2" fill={fill} />
            <rect x="42" y="50" width="60" height="3" rx="2" fill={fill} />
            <rect x="42" y="57" width="66" height="3" rx="2" fill={fill} />
            <rect x="42" y="64" width="55" height="3" rx="2" fill={fill} />
        </svg>
    ),
    hero_banner: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="0" y="0" width="120" height="80" rx="0" fill={fill} opacity="0.3" />
            <rect x="0" y="40" width="120" height="40" rx="0" fill={fill} opacity="0.3" />
            <rect x="25" y="20" width="70" height="8" rx="2" fill="#fff" />
            <rect x="30" y="34" width="60" height="4" rx="2" fill="#fff" opacity="0.7" />
            <rect x="40" y="52" width="40" height="10" rx="4" fill={accent} />
        </svg>
    ),
    split_screen: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="0" y="0" width="60" height="80" rx="0" fill={fill} opacity="0.4" />
            <rect x="68" y="20" width="45" height="6" rx="2" fill={accent} />
            <rect x="68" y="32" width="48" height="3" rx="2" fill={fill} />
            <rect x="68" y="39" width="42" height="3" rx="2" fill={fill} />
            <rect x="68" y="46" width="45" height="3" rx="2" fill={fill} />
        </svg>
    ),

    // ── gallery styles ───────────────────────────────────────────
    grid: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="5" y="5" width="34" height="34" rx="3" fill={fill} />
            <rect x="43" y="5" width="34" height="34" rx="3" fill={fill} />
            <rect x="81" y="5" width="34" height="34" rx="3" fill={fill} />
            <rect x="5" y="43" width="34" height="34" rx="3" fill={fill} />
            <rect x="43" y="43" width="34" height="34" rx="3" fill={fill} />
            <rect x="81" y="43" width="34" height="34" rx="3" fill={fill} />
        </svg>
    ),
    masonry: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="5" y="5" width="34" height="45" rx="3" fill={fill} />
            <rect x="43" y="5" width="34" height="30" rx="3" fill={fill} />
            <rect x="81" y="5" width="34" height="50" rx="3" fill={fill} />
            <rect x="5" y="54" width="34" height="22" rx="3" fill={fill} />
            <rect x="43" y="39" width="34" height="37" rx="3" fill={fill} />
            <rect x="81" y="59" width="34" height="17" rx="3" fill={fill} />
        </svg>
    ),
    slider: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="10" y="8" width="100" height="55" rx="3" fill={fill} />
            <polygon points="4,38 12,30 12,46" fill={accent} />
            <polygon points="116,38 108,30 108,46" fill={accent} />
            <rect x="45" y="70" width="30" height="3" rx="1.5" fill={fill} />
        </svg>
    ),
    carousel: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="2" y="15" width="28" height="45" rx="3" fill={fill} opacity="0.4" />
            <rect x="33" y="8" width="54" height="58" rx="3" fill={fill} />
            <rect x="90" y="15" width="28" height="45" rx="3" fill={fill} opacity="0.4" />
            <circle cx="52" cy="72" r="3" fill={accent} />
            <circle cx="60" cy="72" r="3" fill={fill} />
            <circle cx="68" cy="72" r="3" fill={fill} />
        </svg>
    ),
    wide_carousel: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="0" y="0" width="120" height="80" rx="0" fill={fill} opacity="0.4" />
            <rect x="25" y="30" width="70" height="8" rx="2" fill={accent} />
            <rect x="35" y="44" width="50" height="3" rx="2" fill="#fff" />
            <polygon points="5,40 12,34 12,46" fill={accent} />
            <polygon points="115,40 108,34 108,46" fill={accent} />
        </svg>
    ),
    filmstrip: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="5" y="5" width="110" height="48" rx="3" fill={fill} />
            <rect x="5" y="58" width="20" height="16" rx="2" fill={accent} opacity="0.8" />
            <rect x="28" y="58" width="20" height="16" rx="2" fill={fill} opacity="0.5" />
            <rect x="51" y="58" width="20" height="16" rx="2" fill={fill} opacity="0.5" />
            <rect x="74" y="58" width="20" height="16" rx="2" fill={fill} opacity="0.5" />
            <rect x="97" y="58" width="20" height="16" rx="2" fill={fill} opacity="0.5" />
        </svg>
    ),

    // ── key_figures styles ───────────────────────────────────────
    progress: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="8" y="12" width="40" height="4" rx="2" fill={accent} />
            <rect x="8" y="20" width="100" height="6" rx="3" fill={fill} opacity="0.2" />
            <rect x="8" y="20" width="75" height="6" rx="3" fill={accent} />
            <rect x="8" y="34" width="35" height="4" rx="2" fill={accent} />
            <rect x="8" y="42" width="100" height="6" rx="3" fill={fill} opacity="0.2" />
            <rect x="8" y="42" width="50" height="6" rx="3" fill={accent} />
            <rect x="8" y="56" width="45" height="4" rx="2" fill={accent} />
            <rect x="8" y="64" width="100" height="6" rx="3" fill={fill} opacity="0.2" />
            <rect x="8" y="64" width="90" height="6" rx="3" fill={accent} />
        </svg>
    ),
    timeline: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <line x1="60" y1="5" x2="60" y2="75" stroke={fill} strokeWidth="2" opacity="0.3" />
            <circle cx="60" cy="15" r="4" fill={accent} />
            <rect x="68" y="10" width="40" height="4" rx="2" fill={accent} />
            <rect x="68" y="17" width="35" height="3" rx="2" fill={fill} />
            <circle cx="60" cy="40" r="4" fill={accent} />
            <rect x="12" y="35" width="40" height="4" rx="2" fill={accent} />
            <rect x="12" y="42" width="35" height="3" rx="2" fill={fill} />
            <circle cx="60" cy="65" r="4" fill={accent} />
            <rect x="68" y="60" width="40" height="4" rx="2" fill={accent} />
            <rect x="68" y="67" width="35" height="3" rx="2" fill={fill} />
        </svg>
    ),
    inline: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="5" y="25" width="25" height="12" rx="2" fill={accent} />
            <rect x="5" y="42" width="20" height="3" rx="2" fill={fill} />
            <rect x="35" y="25" width="25" height="12" rx="2" fill={accent} />
            <rect x="35" y="42" width="20" height="3" rx="2" fill={fill} />
            <rect x="65" y="25" width="25" height="12" rx="2" fill={accent} />
            <rect x="65" y="42" width="20" height="3" rx="2" fill={fill} />
            <rect x="95" y="25" width="20" height="12" rx="2" fill={accent} />
            <rect x="95" y="42" width="18" height="3" rx="2" fill={fill} />
        </svg>
    ),
    with_icons: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <circle cx="20" cy="20" r="10" fill={accent} opacity="0.3" />
            <rect x="10" y="35" width="20" height="8" rx="2" fill={accent} />
            <rect x="8" y="48" width="24" height="3" rx="2" fill={fill} />
            <circle cx="60" cy="20" r="10" fill={accent} opacity="0.3" />
            <rect x="50" y="35" width="20" height="8" rx="2" fill={accent} />
            <rect x="48" y="48" width="24" height="3" rx="2" fill={fill} />
            <circle cx="100" cy="20" r="10" fill={accent} opacity="0.3" />
            <rect x="90" y="35" width="20" height="8" rx="2" fill={accent} />
            <rect x="88" y="48" width="24" height="3" rx="2" fill={fill} />
        </svg>
    ),
    grid_2x2: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="5" y="5" width="52" height="32" rx="3" fill={fill} opacity="0.15" />
            <rect x="15" y="12" width="30" height="8" rx="2" fill={accent} />
            <rect x="15" y="24" width="25" height="3" rx="2" fill={fill} />
            <rect x="63" y="5" width="52" height="32" rx="3" fill={fill} opacity="0.15" />
            <rect x="73" y="12" width="30" height="8" rx="2" fill={accent} />
            <rect x="73" y="24" width="25" height="3" rx="2" fill={fill} />
            <rect x="5" y="43" width="52" height="32" rx="3" fill={fill} opacity="0.15" />
            <rect x="15" y="50" width="30" height="8" rx="2" fill={accent} />
            <rect x="15" y="62" width="25" height="3" rx="2" fill={fill} />
            <rect x="63" y="43" width="52" height="32" rx="3" fill={fill} opacity="0.15" />
            <rect x="73" y="50" width="30" height="8" rx="2" fill={accent} />
            <rect x="73" y="62" width="25" height="3" rx="2" fill={fill} />
        </svg>
    ),

    // ── linked_pages styles ──────────────────────────────────────
    cards: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="5" y="10" width="34" height="28" rx="4" fill="none" stroke={fill} strokeWidth="1" />
            <rect x="10" y="20" width="24" height="4" rx="2" fill={accent} />
            <rect x="43" y="10" width="34" height="28" rx="4" fill="none" stroke={fill} strokeWidth="1" />
            <rect x="48" y="20" width="24" height="4" rx="2" fill={accent} />
            <rect x="81" y="10" width="34" height="28" rx="4" fill="none" stroke={fill} strokeWidth="1" />
            <rect x="86" y="20" width="24" height="4" rx="2" fill={accent} />
            <rect x="5" y="44" width="34" height="28" rx="4" fill="none" stroke={fill} strokeWidth="1" />
            <rect x="10" y="54" width="24" height="4" rx="2" fill={accent} />
            <rect x="43" y="44" width="34" height="28" rx="4" fill="none" stroke={fill} strokeWidth="1" />
            <rect x="48" y="54" width="24" height="4" rx="2" fill={accent} />
            <rect x="81" y="44" width="34" height="28" rx="4" fill="none" stroke={fill} strokeWidth="1" />
            <rect x="86" y="54" width="24" height="4" rx="2" fill={accent} />
        </svg>
    ),
    list: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="5" y="8" width="110" height="18" rx="2" fill={fill} opacity="0.1" />
            <rect x="10" y="12" width="40" height="4" rx="2" fill={accent} />
            <rect x="10" y="19" width="60" height="3" rx="2" fill={fill} />
            <rect x="5" y="30" width="110" height="18" rx="2" fill={fill} opacity="0.1" />
            <rect x="10" y="34" width="45" height="4" rx="2" fill={accent} />
            <rect x="10" y="41" width="55" height="3" rx="2" fill={fill} />
            <rect x="5" y="52" width="110" height="18" rx="2" fill={fill} opacity="0.1" />
            <rect x="10" y="56" width="35" height="4" rx="2" fill={accent} />
            <rect x="10" y="63" width="50" height="3" rx="2" fill={fill} />
        </svg>
    ),
    minimal: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="10" y="15" width="60" height="4" rx="2" fill={accent} />
            <rect x="90" y="15" width="20" height="4" rx="2" fill={accent} opacity="0.5" />
            <line x1="10" y1="26" x2="110" y2="26" stroke={fill} strokeWidth="0.5" opacity="0.3" />
            <rect x="10" y="33" width="55" height="4" rx="2" fill={accent} />
            <rect x="90" y="33" width="20" height="4" rx="2" fill={accent} opacity="0.5" />
            <line x1="10" y1="44" x2="110" y2="44" stroke={fill} strokeWidth="0.5" opacity="0.3" />
            <rect x="10" y="51" width="65" height="4" rx="2" fill={accent} />
            <rect x="90" y="51" width="20" height="4" rx="2" fill={accent} opacity="0.5" />
        </svg>
    ),

    // ── location styles ──────────────────────────────────────────
    location_map_only: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="5" y="5" width="110" height="70" rx="3" fill={fill} opacity="0.3" />
            <circle cx="60" cy="32" r="5" fill={accent} />
            <circle cx="60" cy="32" r="2" fill="#fff" />
            <polygon points="60,44 57,36 63,36" fill={accent} />
        </svg>
    ),
    location_map_with_info: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="5" y="5" width="65" height="70" rx="3" fill={fill} opacity="0.3" />
            <circle cx="38" cy="32" r="5" fill={accent} />
            <circle cx="38" cy="32" r="2" fill="#fff" />
            <polygon points="38,44 35,36 41,36" fill={accent} />
            <rect x="75" y="10" width="40" height="5" rx="2" fill={accent} />
            <rect x="75" y="20" width="38" height="3" rx="2" fill={fill} />
            <rect x="75" y="27" width="35" height="3" rx="2" fill={fill} />
            <rect x="75" y="37" width="30" height="3" rx="2" fill={fill} />
            <rect x="75" y="44" width="38" height="3" rx="2" fill={fill} />
        </svg>
    ),
    location_fullwidth: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="0" y="0" width="120" height="48" rx="0" fill={fill} opacity="0.3" />
            <circle cx="60" cy="18" r="5" fill={accent} />
            <circle cx="60" cy="18" r="2" fill="#fff" />
            <polygon points="60,30 57,22 63,22" fill={accent} />
            <rect x="10" y="54" width="50" height="5" rx="2" fill={accent} />
            <rect x="10" y="63" width="80" height="3" rx="2" fill={fill} />
            <rect x="10" y="70" width="70" height="3" rx="2" fill={fill} />
        </svg>
    ),
    location_overlay: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="5" y="5" width="110" height="70" rx="3" fill={fill} opacity="0.3" />
            <circle cx="45" cy="30" r="5" fill={accent} />
            <circle cx="45" cy="30" r="2" fill="#fff" />
            <polygon points="45,42 42,34 48,34" fill={accent} />
            <rect x="62" y="42" width="48" height="30" rx="3" fill="#fff" stroke={fill} strokeWidth="0.5" />
            <rect x="67" y="48" width="30" height="4" rx="2" fill={accent} />
            <rect x="67" y="55" width="38" height="3" rx="2" fill={fill} />
            <rect x="67" y="61" width="35" height="3" rx="2" fill={fill} />
        </svg>
    ),

    // ── form styles ──────────────────────────────────────────────
    split: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="5" y="5" width="52" height="70" rx="3" fill={accent} opacity="0.15" />
            <rect x="15" y="20" width="35" height="6" rx="2" fill={accent} />
            <rect x="15" y="32" width="30" height="3" rx="2" fill={fill} />
            <rect x="63" y="15" width="50" height="8" rx="3" fill={fill} opacity="0.3" />
            <rect x="63" y="30" width="50" height="8" rx="3" fill={fill} opacity="0.3" />
            <rect x="63" y="45" width="50" height="8" rx="3" fill={fill} opacity="0.3" />
            <rect x="73" y="60" width="30" height="10" rx="4" fill={accent} />
        </svg>
    ),

    // ── form styles (card) ──────────────────────────────────────
    // Note: 'centered' and 'split' wireframes are shared with text/form blocks above

    // ── document styles ──────────────────────────────────────────
    document_default: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            {/* Card row 1 */}
            <rect x="5" y="5" width="110" height="20" rx="3" fill={fill} opacity="0.15" stroke={fill} strokeWidth="0.5" />
            <rect x="11" y="10" width="8" height="10" rx="2" fill={accent} opacity="0.3" />
            <rect x="24" y="11" width="45" height="4" rx="2" fill={accent} />
            <rect x="24" y="17" width="25" height="3" rx="1" fill={fill} opacity="0.5" />
            <polygon points="107,12 107,18 104,15" fill={fill} opacity="0.4" />
            {/* Card row 2 */}
            <rect x="5" y="30" width="110" height="20" rx="3" fill={fill} opacity="0.15" stroke={fill} strokeWidth="0.5" />
            <rect x="11" y="35" width="8" height="10" rx="2" fill={accent} opacity="0.3" />
            <rect x="24" y="36" width="50" height="4" rx="2" fill={accent} />
            <rect x="24" y="42" width="30" height="3" rx="1" fill={fill} opacity="0.5" />
            <polygon points="107,37 107,43 104,40" fill={fill} opacity="0.4" />
            {/* Card row 3 */}
            <rect x="5" y="55" width="110" height="20" rx="3" fill={fill} opacity="0.15" stroke={fill} strokeWidth="0.5" />
            <rect x="11" y="60" width="8" height="10" rx="2" fill={accent} opacity="0.3" />
            <rect x="24" y="61" width="40" height="4" rx="2" fill={accent} />
            <rect x="24" y="67" width="20" height="3" rx="1" fill={fill} opacity="0.5" />
            <polygon points="107,62 107,68 104,65" fill={fill} opacity="0.4" />
        </svg>
    ),
    document_grid: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            {/* Card 1 */}
            <rect x="5" y="5" width="34" height="34" rx="3" fill={fill} opacity="0.15" stroke={fill} strokeWidth="0.5" />
            <rect x="13" y="11" width="10" height="12" rx="2" fill={accent} opacity="0.3" />
            <rect x="10" y="27" width="24" height="3" rx="1" fill={accent} />
            <rect x="12" y="33" width="18" height="2" rx="1" fill={fill} opacity="0.5" />
            {/* Card 2 */}
            <rect x="43" y="5" width="34" height="34" rx="3" fill={fill} opacity="0.15" stroke={fill} strokeWidth="0.5" />
            <rect x="51" y="11" width="10" height="12" rx="2" fill={accent} opacity="0.3" />
            <rect x="48" y="27" width="24" height="3" rx="1" fill={accent} />
            <rect x="50" y="33" width="18" height="2" rx="1" fill={fill} opacity="0.5" />
            {/* Card 3 */}
            <rect x="81" y="5" width="34" height="34" rx="3" fill={fill} opacity="0.15" stroke={fill} strokeWidth="0.5" />
            <rect x="89" y="11" width="10" height="12" rx="2" fill={accent} opacity="0.3" />
            <rect x="86" y="27" width="24" height="3" rx="1" fill={accent} />
            <rect x="88" y="33" width="18" height="2" rx="1" fill={fill} opacity="0.5" />
            {/* Card 4 */}
            <rect x="5" y="43" width="34" height="34" rx="3" fill={fill} opacity="0.15" stroke={fill} strokeWidth="0.5" />
            <rect x="13" y="49" width="10" height="12" rx="2" fill={accent} opacity="0.3" />
            <rect x="10" y="65" width="24" height="3" rx="1" fill={accent} />
            <rect x="12" y="71" width="18" height="2" rx="1" fill={fill} opacity="0.5" />
            {/* Card 5 */}
            <rect x="43" y="43" width="34" height="34" rx="3" fill={fill} opacity="0.15" stroke={fill} strokeWidth="0.5" />
            <rect x="51" y="49" width="10" height="12" rx="2" fill={accent} opacity="0.3" />
            <rect x="48" y="65" width="24" height="3" rx="1" fill={accent} />
            <rect x="50" y="71" width="18" height="2" rx="1" fill={fill} opacity="0.5" />
        </svg>
    ),

    // ── cta styles ───────────────────────────────────────────────
    cta_banner: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="0" y="0" width="120" height="80" rx="0" fill={accent} opacity="0.2" />
            <rect x="25" y="15" width="70" height="8" rx="2" fill={accent} />
            <rect x="30" y="30" width="60" height="4" rx="2" fill={fill} />
            <rect x="35" y="38" width="50" height="4" rx="2" fill={fill} />
            <rect x="38" y="52" width="44" height="12" rx="5" fill={accent} />
        </svg>
    ),
    cta_centered: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            {/* Title */}
            <rect x="25" y="10" width="70" height="7" rx="2" fill={accent} />
            {/* Subtitle */}
            <rect x="32" y="22" width="56" height="4" rx="2" fill={fill} opacity="0.5" />
            {/* Text lines */}
            <rect x="15" y="33" width="90" height="3" rx="2" fill={fill} />
            <rect x="20" y="40" width="80" height="3" rx="2" fill={fill} />
            <rect x="25" y="47" width="70" height="3" rx="2" fill={fill} />
            {/* CTA button */}
            <rect x="38" y="58" width="44" height="12" rx="5" fill={accent} />
        </svg>
    ),
    cta_split: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            {/* Image placeholder */}
            <rect x="5" y="5" width="52" height="70" rx="3" fill={accent} opacity="0.15" />
            <rect x="22" y="30" width="18" height="14" rx="2" fill={accent} opacity="0.3" />
            <polygon points="31,28 24,38 38,38" fill={accent} opacity="0.2" />
            {/* Title */}
            <rect x="63" y="12" width="50" height="6" rx="2" fill={accent} />
            {/* Text lines */}
            <rect x="63" y="24" width="50" height="3" rx="2" fill={fill} />
            <rect x="63" y="31" width="45" height="3" rx="2" fill={fill} />
            <rect x="63" y="38" width="48" height="3" rx="2" fill={fill} />
            {/* CTA button */}
            <rect x="63" y="50" width="36" height="10" rx="4" fill={accent} />
        </svg>
    ),

    // ── separator styles ─────────────────────────────────────────
    line: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <line x1="15" y1="40" x2="105" y2="40" stroke={fill} strokeWidth="2" opacity="0.4" />
        </svg>
    ),
    spacer: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <rect x="55" y="10" width="10" height="60" rx="2" fill={fill} opacity="0.1" />
            <path d="M60 15 L55 22 L65 22 Z" fill={fill} opacity="0.3" />
            <path d="M60 65 L55 58 L65 58 Z" fill={fill} opacity="0.3" />
        </svg>
    ),
    divider: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <line x1="10" y1="40" x2="45" y2="40" stroke={fill} strokeWidth="2" opacity="0.3" />
            <rect x="48" y="35" width="24" height="10" rx="3" fill={accent} opacity="0.5" />
            <line x1="75" y1="40" x2="110" y2="40" stroke={fill} strokeWidth="2" opacity="0.3" />
        </svg>
    ),
};

/**
 * StylePicker field component for the Sulu admin.
 *
 * Displays layout style options as simplified SVG wireframes showing
 * text and image arrangements. The block type is detected automatically
 * from the form data (via formInspector), with fallback to XML schema options.
 *
 * Available styles are injected via the static `blockStyles` property, set by the
 * initializer config hook in index.js from ThemeAdmin::getConfig().
 *
 * @param {Object} props - Component props from Sulu form field
 * @param {string} props.value - Currently selected style key
 * @param {Function} props.onChange - Callback when a style is selected
 * @param {Object} props.formInspector - Sulu form inspector
 * @param {string} props.dataPath - Data path in the form (e.g. /blocks/0/style)
 * @param {Object} props.schemaOptions - Schema options from the form XML
 */
export default class StylePicker extends React.Component {
    /**
     * Block styles from the admin config, keyed by block type name.
     * Set by the config hook from ThemeAdmin::BLOCK_STYLE_OPTIONS.
     *
     * @type {Object<string, Array<{key: string, label: string}>>}
     */
    static blockStyles = {};

    /**
     * Tracks whether the first-time default-value lookup has been performed
     * for the current block type. Reset to `false` whenever the block type
     * changes so the picker re-applies a default after a type switch.
     */
    _defaultApplied = false;

    /**
     * Last detected block type. Used to detect type switches across renders
     * (e.g. user changes the dropdown from "separator" to "text") so we can
     * re-run the default lookup against the new style catalog.
     */
    _lastBlockType = null;

    componentDidMount() {
        this.applyDefaultIfNeeded();
    }

    componentDidUpdate() {
        // Retry on every re-render. The internal flags guarantee idempotence
        // when nothing has actually changed and ensure a re-apply after a
        // block type switch.
        this.applyDefaultIfNeeded();
    }

    /**
     * Apply the first available style as default when the field has no value,
     * or when the stored value does not match any of the available styles
     * for the current block type (e.g. a style key that was renamed/removed,
     * or a stale style left over after a block type switch).
     *
     * Uses setTimeout to ensure the Sulu form has finished its own
     * initialization before we call onChange, which avoids race conditions
     * with form state setup.
     */
    applyDefaultIfNeeded() {
        const {value, onChange} = this.props;
        if (!onChange) return;

        const blockType = this.getBlockType();
        const styles = StylePicker.blockStyles[blockType] || [];
        if (styles.length === 0) return;

        // Reset the applied flag when the block type changes so the default
        // is re-evaluated against the new style catalog.
        if (blockType !== this._lastBlockType) {
            this._defaultApplied = false;
            this._lastBlockType = blockType;
        }

        if (this._defaultApplied) return;

        const isValid = typeof value === 'string'
            && styles.some((style) => style.key === value);

        this._defaultApplied = true;
        if (!isValid) {
            setTimeout(() => onChange(styles[0].key), 0);
        }
    }

    /**
     * Detect the block type from the schema options or the form data.
     *
     * Primary: reads block_type from schemaOptions (XML `<param name="block_type" ... />`).
     * Always available at mount time, so it is the reliable source of truth.
     *
     * Fallback: reads the "type" field of the parent block via formInspector.
     * Kept for safety when a host project consumes this picker without
     * supplying the block_type schema option.
     *
     * @returns {string} The detected block type name
     */
    getBlockType() {
        const {formInspector, dataPath, schemaOptions} = this.props;

        // Primary: XML schema option (always available at mount)
        if (schemaOptions && schemaOptions.block_type && schemaOptions.block_type.value) {
            return schemaOptions.block_type.value;
        }

        // Fallback: detect from form data via formInspector
        // dataPath is e.g. "/blocks/0/style" → parent block is "/blocks/0"
        if (formInspector && dataPath) {
            const pathParts = dataPath.split('/');
            pathParts.pop(); // Remove field name ("style")
            const blockTypePath = pathParts.join('/') + '/type';

            try {
                const blockType = formInspector.getValueByPath(blockTypePath);
                if (blockType && typeof blockType === 'string') {
                    return blockType;
                }
            } catch (e) {
                // formInspector may throw if path is invalid
            }
        }

        return 'default';
    }

    /**
     * Render a wireframe SVG for the given style key.
     *
     * Tries a block-type-specific renderer first (e.g. "location_fullwidth"),
     * then falls back to the generic style key (e.g. "fullwidth").
     *
     * @param {string} styleKey - The style identifier (e.g. "fullwidth")
     * @param {string} blockType - The block type (e.g. "location")
     * @returns {React.Element} An SVG wireframe visualization
     */
    renderWireframeSvg(styleKey, blockType) {
        const fill = '#d1d5db';
        const accent = getSuluPrimaryColor();

        // Try block-type-specific renderer first (e.g. location_fullwidth)
        const prefixedKey = blockType + '_' + styleKey;
        const prefixedRenderer = WIREFRAME_RENDERERS[prefixedKey];
        if (prefixedRenderer) {
            return prefixedRenderer(fill, accent);
        }

        // Fall back to generic style key
        const renderer = WIREFRAME_RENDERERS[styleKey];
        if (renderer) {
            return renderer(fill, accent);
        }

        // Fallback for unknown styles
        return (
            <svg viewBox="0 0 120 80" width="120" height="80">
                <rect x="10" y="10" width="100" height="60" rx="4" fill={fill} />
                <rect x="20" y="20" width="60" height="5" rx="2" fill={accent} />
                <rect x="20" y="30" width="80" height="3" rx="2" fill={fill} />
                <rect x="20" y="38" width="70" height="3" rx="2" fill={fill} />
            </svg>
        );
    }

    render() {
        const {value, onChange} = this.props;
        const blockType = this.getBlockType();
        const styles = StylePicker.blockStyles[blockType] || [];

        if (styles.length === 0) {
            return (
                <div style={{padding: '16px', color: '#999', fontStyle: 'italic'}}>
                    No styles available for block type "{blockType}".
                </div>
            );
        }

        return (
            <div style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(140px, 1fr))',
                gap: '12px',
                padding: '8px',
            }}>
                {styles.map((style) => {
                    const isSelected = value === style.key;
                    const primary = getSuluPrimaryColor();
                    const primaryShadow = getSuluPrimaryAlpha(0.3);
                    const primaryTint = getSuluPrimaryTint();

                    return (
                        <div
                            key={style.key}
                            onClick={() => onChange && onChange(style.key)}
                            role="button"
                            tabIndex={0}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' || e.key === ' ') {
                                    onChange && onChange(style.key);
                                }
                            }}
                            style={{
                                cursor: 'pointer',
                                border: isSelected ? `3px solid ${primary}` : '2px solid #e0e0e0',
                                borderRadius: '8px',
                                padding: '8px',
                                backgroundColor: isSelected ? primaryTint : '#fafafa',
                                transition: 'all 0.2s',
                                textAlign: 'center',
                                boxShadow: isSelected ? `0 0 0 3px ${primaryShadow}` : 'none',
                            }}
                        >
                            <div style={{width: '100%', display: 'flex', justifyContent: 'center'}}>
                                {this.renderWireframeSvg(style.key, blockType)}
                            </div>
                            <div style={{
                                marginTop: '6px',
                                fontSize: '11px',
                                fontWeight: isSelected ? 'bold' : 'normal',
                                color: isSelected ? primary : '#666',
                            }}>
                                {translate(style.label)}
                            </div>
                        </div>
                    );
                })}
            </div>
        );
    }
}
