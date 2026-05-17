<?php

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *        ___             _        _                 _                       *
 *       /   |           |_|      |_|               | |                      *
 *      / /| | _________  _  _ __  _   ____   ___  _| |_                     *
 *     / /_| ||  _   _  \| || / _|| | / ___| / _ \|_   _|                    *
 *    / ___  || | | | | || ||  /  | ||___  ||  __/  | |_                     *
 *   /_/   |_||_| |_| |_||_||_|   |_||____/  \___|  |___|                    *
 *                                                                           *
 *   AMIRISET [https://amiriset.com]                                         *
 *   __________________                                                      *
 *                                                                           *
 *   Copyright (C) 2016-2026 Amiriset                                        *
 *   Licensed under GNU GPLv3 or later.                                      *
 *                                                                           *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

namespace Amiriset\MetaManager;
defined( 'ABSPATH' ) || exit;


//------------------------------------------------------------------------------
//    DESCRIPTIONS
//------------------------------------------------------------------------------
/**
 * Class <b>Activator</b> -- activates plugin.
 *
 * @version 1.0.0-a.5
 * @package Amiriset\MetaManager
 * @license GPL-3.0-or-later
 * @author Y.Frolov 
 * @created 2026-05-13 22:31:34
 */
class Activator {
    public static function activate(): void {
        // Write defaults only once
        if ( false === get_option(AMIRISET_META_MANAGER_OPTION_KEY ) ) {
            add_option( AMIRISET_META_MANAGER_OPTION_KEY, self::defaults() );
        }
        flush_rewrite_rules();
    }

    /**
     * Default global settings structure.
     *
     * @return array
     */
    public static function defaults(): array {
        return [
            // ── Technical (override-only) ────────────
            'charset'           => '',
            'content_language'  => '',
            'viewport'          => '',
            'theme_color'       => '',
            'manifest'          => '',

            // ── SEO common ───────────────────────────
            'distribution'      => 'global',
            'classification'    => '',
            'copyright'         => '',
            'developer'         => '',
            'default_robots'    => 'index, follow',
            'title_suffix'      => ' | ' . get_bloginfo( 'name' ),

            // ── Open Graph global ────────────────────
            'og_site_name'      => '',
            'og_default_image'  => '',
            'og_default_locale' => '',
            'og_default_type'   => 'website',

            // ── Twitter global ───────────────────────
            'twitter_site'      => '',
            'twitter_card'      => 'summary_large_image',

            // ── JSON-LD global ───────────────────────
            'jsonld_entity_type'  => 'Organization',
            'jsonld_entity_name'  => '',
            'jsonld_entity_url'   => '',
            'jsonld_entity_logo'  => '',
            'jsonld_website_name' => '',
            'jsonld_website_alt'  => '',

            // ── Post types ───────────────────────────
            'enabled_post_types' => [ 'post', 'page' ],

            // ── Keywords ─────────────────────────────
            'kw_min_symbols'    => 4,
            'kw_max_words'      => 10,
            'kw_lang'           => 'auto',

            // ── Scripts / Analytics ──────────────────
            'analytics_head'    => '',
            'analytics_body'    => '',
        ];
    }
}
