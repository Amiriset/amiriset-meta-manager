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
 *                                                                           *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
/**
 * Plugin Name:       Amiriset Meta Manager
 * Plugin URI:        https://github.com/Amiriset/amiriset-meta-manager
 * Description:       WordPress Plug-In. Per-page/post SEO meta tags (description, keywords, OG, custom) stored as a single JSON object. Supports pages, posts and all CPTs. Built-in keyword extractor.
 * Version:           1.0.0-a.4
 * Author:            Y.Frolov 
 * Author URI:        https://amiriset.com
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0-standalone.html
 * Text Domain:       amiriset-meta-manager
 * Package:           Amiriset\MetaManager
 *
 * Copyright (C) 2016-2026 Amiriset.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin version.
 */
define('AMIRISET_META_MANAGER_VERSION', '1.0.0-a.1');
/**
 * Plugin main file
 */
define('AMIRISET_META_MANAGER_FILE', __FILE__);
/**
 * Plugin directory path.
 */
define('AMIRISET_META_MANAGER_PATH', plugin_dir_path(__FILE__));
/**
 * Plugin directory url.
 */
define('AMIRISET_META_MANAGER_URL', plugin_dir_url(__FILE__));
/**
 * Plugin base name.
 */
define('AMIRISET_META_MANAGER_BASENAME', plugin_basename(__FILE__));
/**
 * DB key. Single post-meta key → JSON
 */
define('AMIRISET_META_MANAGER_DB_KEY', '_amm_meta_data' );  
/**
 * Global settings
 */
define('AMIRISET_META_MANAGER_OPTION_KEY', '_amm_options' );
/**
 * Text Domain
 */
define('AMIRISET_META_MANAGER_TEXT_DOMAIN', 'amiriset-meta-manager' );
/**
 * Display Name
 */
define('AMIRISET_META_MANAGER_DISPLAY_NAME', '🔍 Amiriset Meta Manager' );

// Load classes
$plugin_files = [
    'include/class.utils.php',
    'include/class.loader.php',
    'include/class.activator.php',
    'include/class.deactivator.php',
    'include/class.keywords.php',
    'include/class.metabox.php',
    'include/class.frontend.php',
    'include/class.ajax.php',
    'include/class.core.php',
    'admin/class.admin.php',
];
foreach ( $plugin_files as $f ) {
    require_once AMIRISET_META_MANAGER_PATH . $f;
}

// Lifecycle hooks
use Amiriset\MetaManager\Activator;
register_activation_hook( __FILE__, [  Activator::class,   'activate'   ] );
use Amiriset\MetaManager\Deactivator;
register_deactivation_hook( __FILE__, [  Deactivator::class, 'deactivate' ] );

 // Plugin - run. 
use Amiriset\MetaManager\Core;
( new Core() )->run();
