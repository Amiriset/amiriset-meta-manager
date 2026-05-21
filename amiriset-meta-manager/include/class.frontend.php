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
        add_action( 'wp_head',            [ $this, 'output_global_meta'    ], 0 );
        add_action( 'wp_head',            [ $this, 'output_meta_tags'      ], 1 );
        add_action( 'wp_head',            [ $this, 'output_analytics_head' ], 99 );
        add_action( 'wp_body_open',       [ $this, 'output_analytics_body' ], 1 );
        add_filter( 'wp_robots',          [ $this, 'filter_wp_robots' ], 99 );
        add_action( 'wp_head',            [ $this, 'suppress_core_tags' ], 0 );

        $this->init_technical_overrides();
    }

    /**
     * Set up technical meta overrides.
     *
     * Strategy (two tiers):
     *   1. remove_action — zero cost, covers themes that use wp_head hooks.
     *   2. Output buffer  — fallback for themes that hardcode tags in header.php.
     *
     * Our tags are output via output_global_meta() at priority 0.
     */
    private function init_technical_overrides(): void {
        $opts = $this->options;

        // ── charset ──────────────────────────────────────────────────────
        // NOT using option_blog_charset filter — it affects mb_internal_encoding()
        // and invalid values crash the entire site. Handled via output buffer.
        if ( $opts->get( 'charset' ) ) {
            remove_action( 'wp_head', 'wp_charset', 1 );
        }

        // ── viewport ─────────────────────────────────────────────────────
        if ( $opts->get( 'viewport' ) ) {
            remove_action( 'wp_head', 'wp_viewport_meta_tag', 1 );
        }

        // ── Tier 2: output buffer for hardcoded duplicates ───────────────
        if ( $this->has_technical_overrides() ) {
            add_action( 'template_redirect', [ $this, 'start_head_buffer' ] );
        }
    }

    /**
     * Check if any technical overrides require head deduplication.
     */
    private function has_technical_overrides(): bool {
        return $this->options->get( 'charset' ) !== ''
            || $this->options->get( 'viewport' ) !== ''
            || $this->options->get( 'theme_color' ) !== '';
    }

    /**
     * Start output buffering to deduplicate technical tags.
     * Only active on frontend when overrides exist.
     */
    public function start_head_buffer(): void {
        if ( is_admin() ) {
            return;
        }
        ob_start( [ $this, 'process_head_buffer' ] );
    }

    /**
     * Process buffered output: remove duplicate viewport/theme-color from theme.
     * Keeps our tags (first occurrence), removes theme duplicates.
     *
     * @param string $html Full page HTML.
     * @return string Processed HTML.
     */
    public function process_head_buffer( string $html ): string {
        $head_end = strpos( $html, '</head>' );
        if ( false === $head_end ) {
            return $html;
        }

        $head = substr( $html, 0, $head_end );
        $rest = substr( $html, $head_end );

        $opts = $this->options;

        $charset = $opts->get( 'charset' );
        if ( $charset ) {
            $head = $this->dedup_charset( $head, $charset );
        }

        $viewport = $opts->get( 'viewport' );
        if ( $viewport ) {
            $head = $this->dedup_meta_name( $head, 'viewport', $viewport );
        }

        $theme_color = $opts->get( 'theme_color' );
        if ( $theme_color ) {
            $head = $this->dedup_meta_name( $head, 'theme-color', $theme_color );
        }

        return $head . $rest;
    }

    /**
     * Replace all <meta charset="..."> with our override.
     * Handles both HTML5 (<meta charset="...">) and legacy (<meta http-equiv="Content-Type">) formats.
     *
     * @param string $head    Head HTML.
     * @param string $charset Our charset value.
     * @return string Processed HTML.
     */
    private function dedup_charset( string $head, string $charset ): string {
        // Remove HTML5 charset tags
        $head = preg_replace( '/<meta\s+charset=["\'][^"\']*["\']\s*\/?>\s*/i', '', $head );
        // Remove legacy http-equiv Content-Type
        $head = preg_replace( '/<meta\s+http-equiv=["\']Content-Type["\']\s+content=["\'][^"\']*["\']\s*\/?>\s*/i', '', $head );

        // Inject our charset right after <head>
        $tag  = '<meta charset="' . esc_attr( $charset ) . '">';
        $head = preg_replace( '/(<head[^>]*>)/i', '$1' . "\n" . $tag, $head, 1 );

        return $head;
    }

    /**
     * Replace all <meta name="X"> with a single one containing our override.
     *
     * @param string $head    Head HTML content.
     * @param string $name    Meta name attribute value.
     * @param string $content Our override content value.
     * @return string Processed HTML.
     */
    private function dedup_meta_name( string $head, string $name, string $content ): string {
        $pattern = '/<meta\s+name=["\']' . preg_quote( $name, '/' ) . '["\']\s+content=["\'][^"\']*["\']\s*\/?>\s*/i';
        $head    = preg_replace( $pattern, '', $head );

        // Re-inject our tag right after <head...>
        $tag = '<meta name="' . esc_attr( $name ) . '" content="' . esc_attr( $content ) . '">';
        $head = preg_replace( '/(<head[^>]*>)/i', '$1' . "\n" . $tag, $head, 1 );

        return $head;
    }

    /**
     * Output global technical meta tags on ALL pages.
     * Charset, viewport, and theme-color are handled via output buffer dedup.
     */
    public function output_global_meta(): void {
        $opts = $this->options;

        // content-language
        $lang = $opts->get( 'content_language' );
        if ( $lang ) {
            printf( '<meta http-equiv="Content-Language" content="%s">' . "\n", esc_attr( $lang ) );
        }

        // SEO common — site-wide meta tags
        $seo_fields = [
            'distribution'   => 'distribution',
            'classification' => 'classification',
            'copyright'      => 'copyright',
            'developer'      => 'developer',
        ];

        foreach ( $seo_fields as $option_key => $meta_name ) {
            $value = $opts->get( $option_key );
            if ( $value ) {
                printf( '<meta name="%s" content="%s">' . "\n", esc_attr( $meta_name ), esc_attr( $value ) );
            }
        }

        // viewport and theme-color are handled via output buffer dedup (see process_head_buffer)

        // manifest
        $manifest = $opts->get( 'manifest' );
        if ( $manifest ) {
            printf( '<link rel="manifest" href="%s">' . "\n", esc_url( $manifest ) );
        }
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