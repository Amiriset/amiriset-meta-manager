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
//------------------------------------------------------------------------------
//    DESCRIPTIONS
//------------------------------------------------------------------------------
/**
 * File <b>constants</b> — shared constants.
 *
 * Included by the main plugin file and uninstall.php
 * to guarantee consistent key names across all entry points.
 *
 * @version 1.0.0-a.4
 * @package Amiriset\MetaManager
 * @license GPL-3.0-or-later
 * @author Y.Frolov 
 * @created 2026-05-17 01:56:08
 */

defined( 'ABSPATH' ) || exit;
 
/**
 * Plugin version.
 */
define( 'AMIRISET_META_MANAGER_VERSION', '1.0.0-a.4' );
 
/**
 * DB key. Single post-meta key → JSON.
 */
define( 'AMIRISET_META_MANAGER_DB_KEY', '_amm_meta_data' );
 
/**
 * Global settings option key.
 */
define( 'AMIRISET_META_MANAGER_OPTION_KEY', '_amm_options' );
 
/**
 * Text Domain.
 */
define( 'AMIRISET_META_MANAGER_TEXT_DOMAIN', 'amiriset-meta-manager' );
 
/**
 * Display Name.
 */
define( 'AMIRISET_META_MANAGER_DISPLAY_NAME', '🔍 Amiriset Meta Manager' );