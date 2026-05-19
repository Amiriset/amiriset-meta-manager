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
 * Class <b>ImageResolver</b> -- Centralized OG/Twitter image fallback logic.
 *
 * Resolution order:
 *   1. Custom image from TagManager (per-post)
 *   2. Featured image (post thumbnail)
 *   3. Global default OG image (from settings)
 *   4. null
 *
 * @version 1.0.0-a.5
 * @package Amiriset\MetaManager
 * @license GPL-3.0-or-later
 * @author Y.Frolov
 * @created 2026-05-19 16:08:31
 */
class ImageResolver {

    /**
     * Resolve OG image for a post.
     *
     * @param int        $post_id Post ID.
     * @param TagManager $tm      TagManager loaded with post meta.
     * @return string|null Image URL or null if nothing found.
     */
    public static function RESOLVE_OG_IMAGE( int $post_id, TagManager $tm ): ?string {
        // 1. Custom OG image from post meta
        $custom = $tm->getContent( 'meta::property::og:image' );
        if ( $custom ) {
            return $custom;
        }

        // 2. Featured image
        $thumb_id = get_post_thumbnail_id( $post_id );
        if ( $thumb_id ) {
            $src = wp_get_attachment_image_url( $thumb_id, 'full' );
            if ( $src ) {
                return $src;
            }
        }

        // 3. Global default OG image
        $opts    = Utils::GET_OPTIONS();
        $default = $opts->get( 'og_default_image' );
        if ( $default ) {
            return $default;
        }

        // 4. Nothing
        return null;
    }

    /**
     * Resolve Twitter image for a post.
     * Falls back to OG image chain if no custom Twitter image set.
     *
     * @param int        $post_id Post ID.
     * @param TagManager $tm      TagManager loaded with post meta.
     * @return string|null Image URL or null.
     */
    public static function RESOLVE_TWITTER_IMAGE( int $post_id, TagManager $tm ): ?string {
        // Custom Twitter image takes priority
        $custom = $tm->getContent( 'meta::property::twitter:image' );
        if ( $custom ) {
            return $custom;
        }

        // Fall back to OG image chain
        return self::RESOLVE_OG_IMAGE( $post_id, $tm );
    }
}
