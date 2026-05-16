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
 * Class <b>Utils</b> -- without description.
 *
 * @version 1.0.0-a.4
 * @package Amiriset
 * @license GPL-3.0-or-later
 * @author Y.Frolov
 * @created 2026-05-15 22:14:50
 */
class Utils {
    private static string $TEXT_DOMAIN=AMIRISET_META_MANAGER_TEXT_DOMAIN;
    
    // Superglobals (sanitized) 

    /**
     * Read a string from $_GET.
     * 
     * @param string $key Key.
     * @param string $default Default value.
     * @return string GET value or default.
     */
    public static function GET( string $key, string $default = '' ): string {
        if ( ! isset( $_GET[ $key ] ) ) {
            return $default;
        }
        return $_GET[ $key ];
    }

    /**
     * Read an array from $_GET.
     * 
     * @param string $key Key.
     * @param array  $default Default value.
     * @return array GET array or default.
     */
    public static function GET_ARRAY( string $key, array $default = [] ): array {
        if ( ! isset( $_GET[ $key ] ) || ! is_array( $_GET[ $key ] ) ) {
            return $default;
        }
        return $_GET[ $key ];
    }
    
    /**
     * Read a string from $_POST.
     * 
     * @param string $key Key.
     * @param string $default Default value.
     * @return string POST value or default.
     */
    public static function POST( string $key, string $default = '' ): string {
        if ( ! isset( $_POST[ $key ] ) ) {
            return $default;
        }
        return  $_POST[ $key ];
    }
    
    /**
     * Read an array from $_POST.
     * 
     * @param string $key Key.
     * @param array  $default Default value.
     * @return array POST array or default.
     */
    public static function POST_ARRAY( string $key, array $default = [] ): array {
        if ( ! isset( $_POST[ $key ] ) || ! is_array( $_POST[ $key ] ) ) {
            return $default;
        }
        return $_POST[ $key ];
    }
 

    /**
     * Read an absint value from $_GET.
     * 
     * @param string $key Key.
     * @param string $default Default value.
     * @return int GET value or default.
     */
    public static function GET_INT( string $key, int $default = 0 ): int {
        if ( ! isset( $_GET[ $key ] ) ) {
            return $default;
        }
        return absint( $_GET[ $key ] );
    }

    /**
     * Read an absint value from $_POST.
     * 
     * @param string $key Key.
     * @param string $default Default value.
     * @return int POST value or default.
     */
    public static function POST_INT( string $key, int $default = 0 ): int {
        if ( ! isset( $_POST[ $key ] ) ) {
            return $default;
        }
        return absint( self::POST($key) );
    }

    /**
     * Read a sanitized_key value from $_GET (slugs, CPT names, etc.).
     * 
     * @param string $key Key.
     * @param string $default Default value.
     * @return string Key value or default.
     */
    public static function GET_KEY( string $key, string $default = '' ): string {
        if ( ! isset( $_GET[ $key ] ) ) {
            return $default;
        }
        return sanitize_key( self::GET($key) );
    }

    // i18n 

    /**
     * Echo escaped attribute + translated string (equivalent of esc_attr_e()).
     * 
     * @param string $text Original text.
     * @return string Translated text.
     */
    public static function ESC_ATTR_E( string $text ): string {
        return esc_attr_e( $text, self::$TEXT_DOMAIN ) ?? '';
    }

    /**
     * Translate a string (equivalent of __()).
     * 
     * @param string $text Original text.
     * @return string Translated text.
     */
    public static function LANG( string $text ): string {
        return __( $text, self::$TEXT_DOMAIN );
    }

    /**
     * Return escaped-and-translated string (equivalent of esc_html__()).
     * 
     * @param string $text Original text.
     * @return string Translated text.
     */
    public static function ESC_HTML( string $text ): string {
        return esc_html__( $text, self::$TEXT_DOMAIN );
    }

    /**
     * Echo escaped-and-translated string (equivalent of esc_html_e()).
     * 
     * @param string $text Original text.
     * @return string Translated text.
     */
    public static function ESC_HTML_E( string $text ): string {
        return esc_html_e( $text, self::$TEXT_DOMAIN ) ?? '';
    }
}


