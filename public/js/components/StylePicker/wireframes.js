// @flow
import React from 'react';
import {getSuluPrimaryColor} from '../../utils/suluColors';

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

    // ── timeline styles ──────────────────────────────────────────
    // Prefixed with the block type: `left`, `right` and `horizontal` are
    // generic enough that another block will want them one day, and the
    // unprefixed lookup would then hand it a timeline.
    timeline_alternate: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <line x1="60" y1="16" x2="60" y2="64" stroke={accent} strokeWidth="2" />
            <circle cx="60" cy="16" r="5" fill={accent} />
            <circle cx="60" cy="40" r="5" fill={accent} />
            <circle cx="60" cy="64" r="5" fill={accent} />
            <rect x="6" y="8" width="44" height="16" rx="3" fill={fill} />
            <rect x="70" y="32" width="44" height="16" rx="3" fill={fill} />
            <rect x="6" y="56" width="44" height="16" rx="3" fill={fill} />
        </svg>
    ),
    timeline_left: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <line x1="14" y1="16" x2="14" y2="64" stroke={accent} strokeWidth="2" />
            <circle cx="14" cy="16" r="5" fill={accent} />
            <circle cx="14" cy="40" r="5" fill={accent} />
            <circle cx="14" cy="64" r="5" fill={accent} />
            <rect x="26" y="8" width="88" height="16" rx="3" fill={fill} />
            <rect x="26" y="32" width="88" height="16" rx="3" fill={fill} />
            <rect x="26" y="56" width="88" height="16" rx="3" fill={fill} />
        </svg>
    ),
    timeline_right: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <line x1="106" y1="16" x2="106" y2="64" stroke={accent} strokeWidth="2" />
            <circle cx="106" cy="16" r="5" fill={accent} />
            <circle cx="106" cy="40" r="5" fill={accent} />
            <circle cx="106" cy="64" r="5" fill={accent} />
            <rect x="6" y="8" width="88" height="16" rx="3" fill={fill} />
            <rect x="6" y="32" width="88" height="16" rx="3" fill={fill} />
            <rect x="6" y="56" width="88" height="16" rx="3" fill={fill} />
        </svg>
    ),
    timeline_horizontal: (fill, accent) => (
        <svg viewBox="0 0 120 80" width="120" height="80">
            <line x1="20" y1="16" x2="100" y2="16" stroke={accent} strokeWidth="2" />
            <circle cx="20" cy="16" r="5" fill={accent} />
            <circle cx="60" cy="16" r="5" fill={accent} />
            <circle cx="100" cy="16" r="5" fill={accent} />
            <rect x="8" y="28" width="24" height="44" rx="3" fill={fill} />
            <rect x="48" y="28" width="24" height="44" rx="3" fill={fill} />
            <rect x="88" y="28" width="24" height="44" rx="3" fill={fill} />
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
 * Render the wireframe of a layout style.
 *
 * Shared by the StylePicker field, where the editor picks a style, and the
 * block max width scope modal, where the same drawing tells which style a
 * checkbox is about. A block-type-prefixed key wins over the generic one, so
 * two blocks can ship a `fullwidth` style that looks nothing alike.
 *
 * @param {string} styleKey  The style identifier (e.g. "mosaic")
 * @param {string} blockType The block type, for the prefixed lookup
 * @param {number} scale     Size multiplier, 1 being the 120x80 reference
 *
 * @return {React.Node} The SVG, or a neutral placeholder for an unknown style
 */
export function renderWireframe(styleKey: string, blockType: string, scale: number = 1) {
    const fill = '#d1d5db';
    const accent = getSuluPrimaryColor();
    const renderer = WIREFRAME_RENDERERS[blockType + '_' + styleKey] || WIREFRAME_RENDERERS[styleKey];

    const svg = renderer
        ? renderer(fill, accent)
        : (
            <svg viewBox="0 0 120 80" width="120" height="80">
                <rect x="10" y="10" width="100" height="60" rx="4" fill={fill} />
                <rect x="20" y="20" width="60" height="5" rx="2" fill={accent} />
                <rect x="20" y="30" width="80" height="3" rx="2" fill={fill} />
                <rect x="20" y="38" width="70" height="3" rx="2" fill={fill} />
            </svg>
        );

    return 1 === scale
        ? svg
        : React.cloneElement(svg, {width: Math.round(120 * scale), height: Math.round(80 * scale)});
}

export default WIREFRAME_RENDERERS;
