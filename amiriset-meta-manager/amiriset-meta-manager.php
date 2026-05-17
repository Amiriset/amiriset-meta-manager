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
 * Version:           1.0.0-a.5
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

// Shared constants (also loaded by uninstall.php)
include __DIR__ . '\include\constants.php';

/**
 * Plugin main file.
 */
define( 'AMIRISET_META_MANAGER_FILE', __FILE__ );
/**
 * Plugin directory path.
 */
define( 'AMIRISET_META_MANAGER_PATH', plugin_dir_path( __FILE__ ) );
/**
 * Plugin directory url.
 */
define( 'AMIRISET_META_MANAGER_URL', plugin_dir_url( __FILE__ ) );
/**
 * Plugin base name.
 */
define( 'AMIRISET_META_MANAGER_BASENAME', plugin_basename( __FILE__ ) );

// Load classes
$plugin_files = [
    'include/class.utils.php',
    'include/class.activator.php',
    'include/class.deactivator.php',
    'include/class.keywords.php',
    // TagManager — object model (dependency order)
    'include/tagmanager/class.attributecontainer.php',
    'include/tagmanager/class.abstracttag.php',
    'include/tagmanager/class.metatag.php',
    'include/tagmanager/class.namemetatag.php',
    'include/tagmanager/class.propertymetatag.php',
    'include/tagmanager/class.httpequivmetatag.php',
    'include/tagmanager/class.scripttag.php',
    'include/tagmanager/class.linktag.php',
    'include/tagmanager/class.tagcollection.php',
    'include/tagmanager/class.tagparser.php',
    'include/tagmanager/class.tagserializer.php',
    'include/tagmanager/class.tagrenderer.php',
    'include/tagmanager/class.tagmanager.php',
    // Plugin components
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