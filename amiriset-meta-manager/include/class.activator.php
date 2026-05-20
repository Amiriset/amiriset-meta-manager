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
        // Migrate option key rename: amm_options → _amm_options
        $legacy = get_option( 'amm_options' );
        if ( false !== $legacy && false === get_option( AMIRISET_META_MANAGER_OPTION_KEY ) ) {
            add_option( AMIRISET_META_MANAGER_OPTION_KEY, $legacy );
            delete_option( 'amm_options' );
        }

        if ( false === get_option( AMIRISET_META_MANAGER_OPTION_KEY ) ) {
            // Fresh install
            add_option( AMIRISET_META_MANAGER_OPTION_KEY, self::defaults() );
            update_option( AMIRISET_META_MANAGER_SCHEMA_OPTION_KEY, AMIRISET_META_MANAGER_SCHEMA_VERSION, true );
        } else {
            // Existing install — migrate old field keys if present
            self::migrate_options();
        }
        flush_rewrite_rules();
    }

    /**
     * Migrate renamed option keys from previous versions.
     * Runs on re-activation so existing installs pick up new schema.
     */
    private static function migrate_options(): void {
        $opts    = get_option( AMIRISET_META_MANAGER_OPTION_KEY, [] );
        $changed = false;

        // Old key => new key (simple renames)
        $map = [
            'default_og_type'      => 'og_default_type',
            'default_og_image'     => 'og_default_image',
            'default_title_suffix' => 'title_suffix',
            'keyword_min_symbols'  => 'kw_min_symbols',
            'keyword_max_words'    => 'kw_max_words',
            'keyword_lang'         => 'kw_lang',
        ];

        foreach ( $map as $old => $new ) {
            if ( isset( $opts[ $old ] ) && ! isset( $opts[ $new ] ) ) {
                $opts[ $new ] = $opts[ $old ];
                unset( $opts[ $old ] );
                $changed = true;
            }
        }

        // Migrate og_default_type → og_type_map
        if ( isset( $opts['og_default_type'] ) && ! isset( $opts['og_type_map'] ) ) {
            $defaults   = self::defaults();
            $type_map   = $defaults['og_type_map'];
            $type_map['_default'] = $opts['og_default_type'];
            $opts['og_type_map']  = $type_map;
            unset( $opts['og_default_type'] );
            $changed = true;
        }

        // Merge with defaults to fill any missing new keys
        $opts = array_merge( self::defaults(), $opts );

        if ( $changed ) {
            update_option( AMIRISET_META_MANAGER_OPTION_KEY, $opts );
        }
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
            'og_type_map'       => [
                'post'       => 'article',
                'page'       => 'website',
                '_default'   => 'article',   // fallback for CPTs not explicitly mapped
            ],

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
