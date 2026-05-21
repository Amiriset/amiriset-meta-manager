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
 * Class <b>DebugLog</b> -- Collects diagnostic messages for frontend HTML comments.
 *
 * Active only when WP_DEBUG is true and the current user has manage_options.
 * Messages are rendered as HTML comments after the meta block.
 *
 * Usage:
 *   DebugLog::log( 'OpenGraphBuilder', 'og:type resolved from type_map: article' );
 *   DebugLog::render();
 *
 * @version 1.0.0-a.5
 * @package Amiriset
 * @license GPL-3.0-or-later
 * @author Y.Frolov <frolov@amiriset.com>
 * @created 2026-05-20 18:34:09
 */
class DebugLog {

    /** @var string[] Collected messages. */
    private static array $messages = [];

    /**
     * Check if debug output is active.
     */
    public static function isActive(): bool {
        return defined( 'WP_DEBUG' ) && WP_DEBUG
            && current_user_can( 'manage_options' );
    }

    /**
     * Add a diagnostic message.
     *
     * @param string $source Component name (e.g. 'ImageResolver').
     * @param string $message Description.
     */
    public static function log( string $source, string $message ): void {
        if ( ! self::isActive() ) {
            return;
        }
        self::$messages[] = $source . ': ' . $message;
    }

    /**
     * Render collected messages as HTML comments.
     * Clears the buffer after output.
     */
    public static function render(): void {
        if ( empty( self::$messages ) ) {
            return;
        }

        echo "<!-- AMM Debug -->\n";
        foreach ( self::$messages as $msg ) {
            echo '<!-- AMM: ' . esc_html( $msg ) . " -->\n";
        }
        echo "<!-- /AMM Debug -->\n";

        self::$messages = [];
    }
}

