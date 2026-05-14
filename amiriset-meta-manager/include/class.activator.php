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
 * @version 1.0.0-a.1
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
            'default_robots'        => 'index, follow',
            'default_og_type'       => 'website',
            'default_og_image'      => '',
            'default_title_suffix'  => ' | ' . get_bloginfo( 'name' ),
            'analytics_head'        => '', // raw HTML/JS injected in <head>
            'analytics_body'        => '',  // raw HTML/JS injected after <body>
            'enabled_post_types'    => [ 'post', 'page' ],  // CPTs user opted-in
            'keyword_min_symbols'   => 4,
            'keyword_max_words'     => 25,
            'keyword_lang'          => '', // '' = auto (all chars)
        ];
    }
}
