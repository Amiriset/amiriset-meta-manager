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
 * Class <b>Core</b> -- Orchestrates all plugin components via the SMM_Loader.
 *
 * @version 1.0.0-a.1
 * @package Amiriset\MetaManager
 * @license GPL-3.0-or-later
 * @author Y.Frolov 
 * @created 2026-05-13 23:02:31
 */
final class Core {
    private Loader   $loader;
    private MetaBox  $metaBox;
    private Frontend $frontend;
    private Ajax     $ajax;
    private Admin    $admin;
    
    public function __construct() {
        $this->loader   = new Loader();
        $this->metaBox  = new MetaBox();
        $this->frontend = new Frontend();
        $this->ajax     = new Ajax();
        $this->admin    = new Admin();
    }
    
    /**
     * Run plugin modules.
     * @return void
     */
    public function run(): void {
        $this->load_text_domain();

        // Each component registers its own hooks via init_hooks()
        $this->metaBox->init_hooks();
        $this->frontend->init_hooks();
        $this->ajax->init_hooks();
        $this->admin->init_hooks();

        $this->loader->run();
    }
    
    private function load_text_domain(): void {
        add_action( 'init', static function () {
            load_plugin_textdomain(
                    AMIRISET_META_MANAGER_TEXT_DOMAIN,
                false,
                dirname(AMIRISET_META_MANAGER_BASENAME) . '/languages'
            );
        } );
    }
}
