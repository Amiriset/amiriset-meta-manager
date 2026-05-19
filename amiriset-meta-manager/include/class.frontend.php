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
     * Loads per-post data, injects global defaults, renders via TagRenderer.
     */
    public function output_meta_tags(): void {
        if ( ! is_singular() ) {
            return;
        }

        global $post;
        $post_id = (int) $post->ID;
        $tm      = MetaBox::load( $post_id );
        $col     = $tm->getCollection();
        $opts    = $this->options;

        // ── Inject defaults for missing tags ─────────────────────────────

        // ── Read raw values before modifying collection ──────────────────

        $wp_title      = get_the_title( $post_id );
        $wp_permalink  = get_permalink( $post_id );
        $title_suffix  = $opts->get( 'title_suffix' );

        $raw_og_title  = $tm->getContent( 'meta::property::og:title' );
        $raw_og_desc   = $tm->getContent( 'meta::property::og:description' ) ?: $tm->getContent( 'meta::name::description' );
        $raw_og_image  = $tm->getContent( 'meta::property::og:image' );
        $raw_tw_title  = $tm->getContent( 'meta::property::twitter:title' );

        // ── Inject defaults ──────────────────────────────────────────────

        // robots
        if ( ! $col->has( 'meta::name::robots' ) ) {
            $robots = $opts->get( 'default_robots', 'index, follow' );
            if ( $robots ) {
                $col->set( 'meta::name::robots',
                    ( new NameMetaTag() )->setName( 'robots' )->setContent( $robots ) );
            }
        }

        // canonical
        if ( ! $col->has( 'link' ) ) {
            $col->append( 'link',
                ( new LinkTag() )->setRel( 'canonical' )->setHref( $wp_permalink ) );
        }

        // og:title + title suffix
        $og_title = ( $raw_og_title ?: $wp_title ) . $title_suffix;
        $col->set( 'meta::property::og:title',
            ( new PropertyMetaTag() )->setProperty( 'og:title' )->setContent( $og_title ) );

        // og:type
        if ( ! $col->has( 'meta::property::og:type' ) ) {
            $col->set( 'meta::property::og:type',
                ( new PropertyMetaTag() )->setProperty( 'og:type' )
                    ->setContent( $opts->get( 'og_default_type', 'website' ) ) );
        }

        // og:url
        if ( ! $col->has( 'meta::property::og:url' ) ) {
            $col->set( 'meta::property::og:url',
                ( new PropertyMetaTag() )->setProperty( 'og:url' )->setContent( $wp_permalink ) );
        }

        // og:description
        $og_desc = $raw_og_desc;
        if ( $og_desc && ! $col->has( 'meta::property::og:description' ) ) {
            $col->set( 'meta::property::og:description',
                ( new PropertyMetaTag() )->setProperty( 'og:description' )->setContent( $og_desc ) );
        }

        // og:image
        $og_image = $raw_og_image ?: $opts->get( 'og_default_image' );
        if ( $og_image && ! $col->has( 'meta::property::og:image' ) ) {
            $col->set( 'meta::property::og:image',
                ( new PropertyMetaTag() )->setProperty( 'og:image' )->setContent( $og_image ) );
        }

        // twitter:card
        if ( ! $col->has( 'meta::property::twitter:card' ) ) {
            $col->set( 'meta::property::twitter:card',
                ( new PropertyMetaTag() )->setProperty( 'twitter:card' )
                    ->setContent( $opts->get( 'twitter_card', 'summary' ) ) );
        }

        // twitter:title + title suffix (fallback from raw og:title, not suffixed)
        $tw_title = ( $raw_tw_title ?: $raw_og_title ?: $wp_title ) . $title_suffix;
        $col->set( 'meta::property::twitter:title',
            ( new PropertyMetaTag() )->setProperty( 'twitter:title' )->setContent( $tw_title ) );

        // twitter:description fallback from og:description
        if ( ! $col->has( 'meta::property::twitter:description' ) && $og_desc ) {
            $col->set( 'meta::property::twitter:description',
                ( new PropertyMetaTag() )->setProperty( 'twitter:description' )->setContent( $og_desc ) );
        }

        // twitter:image fallback from og:image
        if ( ! $col->has( 'meta::property::twitter:image' ) && $og_image ) {
            $col->set( 'meta::property::twitter:image',
                ( new PropertyMetaTag() )->setProperty( 'twitter:image' )->setContent( $og_image ) );
        }

        // twitter:site from global settings
        $tw_site = $opts->get( 'twitter_site' );
        if ( $tw_site && ! $col->has( 'meta::property::twitter:site' ) ) {
            $col->set( 'meta::property::twitter:site',
                ( new PropertyMetaTag() )->setProperty( 'twitter:site' )
                    ->setContent( '@' . ltrim( $tw_site, '@' ) ) );
        }

        // twitter:creator — add @ prefix if stored without it
        if ( $col->has( 'meta::property::twitter:creator' ) ) {
            $creator = $tm->getContent( 'meta::property::twitter:creator' );
            if ( $creator && ! str_starts_with( $creator, '@' ) ) {
                $col->set( 'meta::property::twitter:creator',
                    ( new PropertyMetaTag() )->setProperty( 'twitter:creator' )
                        ->setContent( '@' . $creator ) );
            }
        }

        // ── Render ───────────────────────────────────────────────────────

        echo "\n<!-- Amiriset Meta Manager -->\n";
        $tm->toHtml();
        echo "<!-- /Amiriset Meta Manager -->\n\n";
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
