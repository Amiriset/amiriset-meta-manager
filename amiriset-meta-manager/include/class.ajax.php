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
 * Class <b>Ajax</b> --  WordPress AJAX endpoints for the Amiriset Meta Manager.
 *
 * Registered actions (admin-only, logged-in):
 * amm_generate_keywords  — returns suggested keywords for a post
 *
 * @version 1.0.0-a.1
 * @package Amiriset\MetaManager
 * @license GPL-3.0-or-later
 * @author Y.Frolov 
 * @created 2026-05-13 22:50:53
 */
class Ajax {
    
    public function init_hooks(): void {
        add_action( 'wp_ajax_amm_generate_keywords', [ $this, 'handle_generate_keywords' ] );
    }

    /**
     * Generate Keywords
     * @return void
     */
    public function handle_generate_keywords(): void {
        // Security
        check_ajax_referer( 'amm_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'amiriset-meta-manager' ) ], 403 );
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid post ID.', 'amiriset-meta-manager' ) ], 400 );
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_send_json_error( [ 'message' => __( 'Post not found.', 'amiriset-meta-manager' ) ], 404 );
        }

        // Respect capability for this specific post
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'amiriset-meta-manager' ) ], 403 );
        }

        // Read options
        $opts       = get_option(AMIRISET_META_MANAGER_OPTION_KEY, Activator::defaults() );
        $minSymbols = absint( $opts['keyword_min_symbols'] ?? 4 );
        $maxWords   = absint( $opts['keyword_max_words']   ?? 25 );

        // Lang: user can override per-request (from the dropdown in the UI)
        $lang = sanitize_text_field( $_POST['lang'] ?? $opts['keyword_lang'] ?? '' );
        if ( '' === $lang ) {
            $lang = Keywords::detectLang( $post->post_content );
        }

        // Build text from title + content + excerpt
        $text = implode( ' ', [
            $post->post_title,
            wp_strip_all_tags( $post->post_content ),
            $post->post_excerpt,
        ] );

        $keywords = Keywords::genKeywordsFiltered( $text, $minSymbols, $maxWords, $lang );

        wp_send_json_success( [
            'keywords' => $keywords,
            'lang'     => $lang,
            'count'    => count( $keywords ),
        ] );
    }
}
