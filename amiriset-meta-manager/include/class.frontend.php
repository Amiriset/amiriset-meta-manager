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
    private array $options;

    public function __construct() {
        $this->options = Utils::GET_OPTIONS();
    }

    public function init_hooks(): void {
        // Priority 1 ensures our tags appear before theme/plugin duplicates
        add_action( 'wp_head',            [ $this, 'output_meta_tags'    ], 1 );
        add_action( 'wp_head',            [ $this, 'output_analytics_head' ], 99 );
        add_action( 'wp_body_open',       [ $this, 'output_analytics_body' ], 1 );

        // Remove WP's default <title> handling when we have a custom title
        add_filter( 'pre_get_document_title', [ $this, 'filter_document_title' ], 10 );
    }
    
    /**
     * Title.
     * @param string $title
     * @return string
     */
    public function filter_document_title( string $title ): string {
        if ( ! is_singular() ) {
            return $title;
        }
        $meta = MetaBox::get_meta( get_the_ID() );
        if ( ! empty( $meta['title'] ) ) {
            return $meta['title'];
        }
        return $title;
    }

    /**
     * Meta tag
     * @global type $post
     * @return void
     */
    public function output_meta_tags(): void {
        if ( ! is_singular() ) {
            return;
        }

        global $post;
        $post_id = (int) $post->ID;
        $meta    = MetaBox::get_meta( $post_id );
        $opts    = $this->options;

        echo "\n<!-- Amiriset Meta Manager -->\n";

        // description
        $desc = $meta['description'] ?: '';
        if ( $desc ) {
            printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
        }

        // keywords
        $kw = $meta['keywords'] ?: '';
        if ( $kw ) {
            printf( '<meta name="keywords" content="%s">' . "\n", esc_attr( $kw ) );
        }

        // robots
        $robots = $meta['robots'] ?: ( $opts['default_robots'] ?? 'index, follow' );
        if ( $robots ) {
            printf( '<meta name="robots" content="%s">' . "\n", esc_attr( $robots ) );
        }

        // canonical
        $canonical = $meta['canonical'] ?: get_permalink( $post_id );
        if ( $canonical ) {
            printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $canonical ) );
        }

        // Open Graph
        $og_title = $meta['og_title'] ?: ( $meta['title'] ?: get_the_title( $post_id ) );
        $og_desc  = $meta['og_description'] ?: $desc;
        $og_image = $meta['og_image'] ?: ( $opts['og_default_image'] ?? '' );
        $og_type  = $meta['og_type'] ?: ( $opts['og_default_type'] ?? 'website' );

        printf( '<meta property="og:title" content="%s">' . "\n",       esc_attr( $og_title ) );
        printf( '<meta property="og:type" content="%s">' . "\n",        esc_attr( $og_type ) );
        printf( '<meta property="og:url" content="%s">' . "\n",         esc_url( $canonical ) );

        if ( $og_desc ) {
            printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $og_desc ) );
        }
        if ( $og_image ) {
            printf( '<meta property="og:image" content="%s">' . "\n",   esc_url( $og_image ) );
        }

        // Custom meta tags
        if ( ! empty( $meta['custom_meta'] ) && is_array( $meta['custom_meta'] ) ) {
            foreach ( $meta['custom_meta'] as $cm ) {
                $attr_type  = $cm['attr_type']  ?? 'name';
                $attr_value = $cm['attr_value'] ?? '';
                $content    = $cm['content']    ?? '';

                if ( $attr_value && $content ) {
                    printf(
                        '<meta %s="%s" content="%s">' . "\n",
                        esc_attr( $attr_type ),
                        esc_attr( $attr_value ),
                        esc_attr( $content )
                    );
                }
            }
        }
        
        $opts       = $this->options;
        $tw_card    = $meta['tw_card']        ?: ( $opts['twitter_card']  ?? 'summary' );
        $tw_title   = $meta['tw_title']       ?: $og_title;
        $tw_desc    = $meta['tw_description'] ?: $og_desc;
        $tw_image   = $meta['tw_image']       ?: $og_image;
        $tw_site    = $opts['twitter_site']   ?? '';
        $tw_creator = $meta['tw_creator']     ?? '';

        printf( '<meta name="twitter:card" content="%s">' . "\n",  esc_attr( $tw_card ) );
        printf( '<meta name="twitter:title" content="%s">' . "\n", esc_attr( $tw_title ) );
        if ( $tw_desc ) {
            printf( '<meta name="twitter:description" content="%s">' . "\n", esc_attr( $tw_desc ) );
        }
        if ( $tw_image ) {
            printf( '<meta name="twitter:image" content="%s">' . "\n", esc_url( $tw_image ) );
        }
        if ( $tw_site ) {
            printf( '<meta name="twitter:site" content="%s">' . "\n", esc_attr( '@' . ltrim( $tw_site, '@' ) ) );
        }
        if ( $tw_creator ) {
            printf( '<meta name="twitter:creator" content="%s">' . "\n", esc_attr( '@' . ltrim( $tw_creator, '@' ) ) );
        }

        echo "<!-- /Amiriset Meta Manager -->\n\n";
    }
    
    /**
     * Header scripts.
     * @return void
     */
    public function output_analytics_head(): void {
        $script = trim($this->options['analytics_head'] ?? '');
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
        $script = trim($this->options['analytics_body'] ?? '');
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
