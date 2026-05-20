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
 * Class <b>Frontend</b> -- Outputs SEO meta tags and analytics scripts
 *  on the frontend. 
 * Hooked into wp_head (priority 1 — before theme outputs anything)
 *
 * @version 1.0.0-a.5
 * @package Amiriset\MetaManager
 * @license GPL-3.0-or-later
 * @author Y.Frolov 
 * @created 2026-05-14 11:25:33
 */
class Frontend {
    private Options $options;

    public function __construct() {
        $this->options = Utils::GET_OPTIONS();
    }

    public function init_hooks(): void {
        add_action( 'wp_head',            [ $this, 'output_meta_tags'      ], 1 );
        add_action( 'wp_head',            [ $this, 'output_analytics_head' ], 99 );
        add_action( 'wp_body_open',       [ $this, 'output_analytics_body' ], 1 );
        add_filter( 'wp_robots',          [ $this, 'filter_wp_robots' ], 99 );
        add_action( 'wp_head',            [ $this, 'suppress_core_tags' ], 0 );
    }

    /**
     * Remove core-generated tags that we replace on singular pages.
     */
    public function suppress_core_tags(): void {
        if ( is_singular() ) {
            remove_action( 'wp_head', 'rel_canonical' );
            remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
        }
    }

    /**
     * Suppress core robots meta on singular pages — we render our own via TagManager.
     *
     * @param array $robots Core robots directives.
     * @return array Empty on singular (our tag replaces it), unchanged otherwise.
     */
    public function filter_wp_robots( array $robots ): array {
        if ( is_singular() ) {
            return [];
        }
        return $robots;
    }

    /**
     * Output all meta tags via TagManager.
     * Loads per-post data, builds full tag set via OpenGraphBuilder, renders.
     */
    public function output_meta_tags(): void {
        if ( ! is_singular() ) {
            return;
        }

        global $post;
        $post_id = (int) $post->ID;
        $tm      = MetaBox::load( $post_id );

        // Build deterministic meta tag set
        ( new OpenGraphBuilder() )->build( $post_id, $tm, $this->options );

        // Render
        echo "\n<!-- Amiriset Meta Manager -->\n";
        $tm->toHtml();
        echo "<!-- /Amiriset Meta Manager -->\n";
        DebugLog::render();
        echo "\n";
    }
    
    /**
     * Header scripts.
     * @return void
     */
    public function output_analytics_head(): void {
        $script = trim( $this->options->get( 'analytics_head' ) );
        if ( $script && ! is_admin() ) {
            // Raw HTML/JS – trust is on the admin who saved it
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo "\n" . self::maybe_wrap_script( $script ) . "\n";
        }
    }

    /**
     * Body scripts.
     * @return void
     */
    public function output_analytics_body(): void {
        $script = trim( $this->options->get( 'analytics_body' ) );
        if ( $script && ! is_admin() ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo "\n" . self::maybe_wrap_script( $script ) . "\n";
        }
    }
    
     /**
     * If the stored value contains no HTML tags (i.e. it's raw JS),
     * wrap it in <script> tags so it executes correctly.     * 
     *
     * @param string $content
     * @return string Full HTML snippets (GTM, Pixel etc.) are passed through as-is.
     */            
    private static function maybe_wrap_script( string $content ): string {
        // Already contains HTML tags — output as-is
        if ( preg_match( '/<[a-z]/i', $content ) ) {
            return $content;
        }
        // Raw JS — wrap it
        return "<script>\n" . $content . "\n</script>";
    }
}
