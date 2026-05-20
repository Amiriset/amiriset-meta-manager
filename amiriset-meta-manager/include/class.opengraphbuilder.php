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
 * Class <b>OpenGraphBuilder</b> -- Builds deterministic meta tag set.
 *
 * Injects:
 *   - SEO defaults (robots, canonical)
 *   - Open Graph tags (title, description, image, type, url, site_name, locale)
 *   - Twitter Card tags (card, title, description, image, site, creator)
 *   - Article meta (published_time, modified_time, section, tags) — future
 *
 * All procedural OG/Twitter logic lives here, not in Frontend.
 *
 * @version 1.0.0-a.5
 * @package Amiriset\MetaManager
 * @license GPL-3.0-or-later
 * @author Y.Frolov
 * @created 2026-05-20 12:40:32
 */
class OpenGraphBuilder {
    private TagManager     $tm;
    private TagCollection  $col;
    private Options        $opts;
    private int            $post_id;

    // WordPress data — resolved once
    private string $wp_title;
    private string $wp_permalink;

    // Raw per-post values — read before any modification
    private string $raw_og_title;
    private string $raw_og_desc;
    private string $raw_tw_title;

    // Resolved images
    private ?string $og_image;
    private ?string $tw_image;

    // Computed values — shared between OG and Twitter
    private string $og_title;
    private string $og_desc;

    /**
     * Build full meta tag set for a singular post.
     *
     * @param int        $post_id Post ID.
     * @param TagManager $tm      TagManager loaded with per-post data.
     * @param Options    $opts    Global plugin options.
     */
    public function build( int $post_id, TagManager $tm, Options $opts ): void {
        $this->tm      = $tm;
        $this->col     = $tm->getCollection();
        $this->opts    = $opts;
        $this->post_id = $post_id;

        $this->resolve_wp_data();
        $this->resolve_raw_values();

        $this->inject_seo_defaults();
        $this->inject_open_graph();
        $this->inject_twitter();
        $this->inject_article();
    }

    // ── WordPress data ───────────────────────────────────────────────────

    private function resolve_wp_data(): void {
        $this->wp_title     = get_the_title( $this->post_id );
        $this->wp_permalink = get_permalink( $this->post_id );
    }

    /**
     * Read raw per-post values before any collection modification.
     * Prevents double-suffix and other mutation side effects.
     */
    private function resolve_raw_values(): void {
        $this->raw_og_title  = $this->tm->getContent( 'meta::property::og:title' );
        $this->raw_og_desc   = $this->tm->getContent( 'meta::property::og:description' )
                            ?: $this->tm->getContent( 'meta::name::description' );
        $this->raw_tw_title  = $this->tm->getContent( 'meta::property::twitter:title' );

        $this->og_image = ImageResolver::RESOLVE_OG_IMAGE( $this->post_id, $this->tm );
        $this->tw_image = ImageResolver::RESOLVE_TWITTER_IMAGE( $this->post_id, $this->tm );
    }

    // ── SEO defaults ─────────────────────────────────────────────────────

    private function inject_seo_defaults(): void {
        // robots
        if ( ! $this->col->has( 'meta::name::robots' ) ) {
            $robots = $this->opts->get( 'default_robots', 'index, follow' );
            if ( $robots ) {
                $this->set_name( 'robots', $robots );
            }
        }

        // canonical
        if ( ! $this->col->has( 'link' ) ) {
            $this->col->append( 'link',
                ( new LinkTag() )->setRel( 'canonical' )->setHref( $this->wp_permalink ) );
        }
    }

    // ── Open Graph ───────────────────────────────────────────────────────

    private function inject_open_graph(): void {
        $suffix = $this->opts->get( 'title_suffix' );

        // og:title (always set — with suffix)
        $this->og_title = ( $this->raw_og_title ?: $this->wp_title ) . $suffix;
        $this->set_property( 'og:title', $this->og_title );

        // og:type (manual override wins → attachment mime → type map → _default)
        if ( ! $this->col->has( 'meta::property::og:type' ) ) {
            $this->set_property( 'og:type', $this->resolve_og_type() );
        }

        // og:url
        if ( ! $this->col->has( 'meta::property::og:url' ) ) {
            $this->set_property( 'og:url', $this->wp_permalink );
        }

        // og:description
        $this->og_desc = $this->raw_og_desc;
        if ( $this->og_desc && ! $this->col->has( 'meta::property::og:description' ) ) {
            $this->set_property( 'og:description', $this->og_desc );
        }

        // og:image
        if ( $this->og_image ) {
            $this->set_property( 'og:image', $this->og_image );
        }

        // og:site_name
        if ( ! $this->col->has( 'meta::property::og:site_name' ) ) {
            $site_name = $this->opts->get( 'og_site_name' ) ?: get_bloginfo( 'name' );
            if ( $site_name ) {
                $this->set_property( 'og:site_name', $site_name );
            }
        }

        // og:locale
        if ( ! $this->col->has( 'meta::property::og:locale' ) ) {
            $locale = $this->opts->get( 'og_default_locale' ) ?: get_locale();
            if ( $locale ) {
                $this->set_property( 'og:locale', $locale );
            }
        }
    }

    // ── Twitter ──────────────────────────────────────────────────────────

    private function inject_twitter(): void {
        $suffix = $this->opts->get( 'title_suffix' );

        // twitter:card
        if ( ! $this->col->has( 'meta::property::twitter:card' ) ) {
            $this->set_property( 'twitter:card',
                $this->opts->get( 'twitter_card', 'summary' ) );
        }

        // twitter:title (with suffix, fallback from raw og:title)
        $tw_title = ( $this->raw_tw_title ?: $this->raw_og_title ?: $this->wp_title ) . $suffix;
        $this->set_property( 'twitter:title', $tw_title );

        // twitter:description
        if ( ! $this->col->has( 'meta::property::twitter:description' ) && $this->og_desc ) {
            $this->set_property( 'twitter:description', $this->og_desc );
        }

        // twitter:image
        if ( $this->tw_image ) {
            $this->set_property( 'twitter:image', $this->tw_image );
        }

        // twitter:site
        $tw_site = $this->opts->get( 'twitter_site' );
        if ( $tw_site && ! $this->col->has( 'meta::property::twitter:site' ) ) {
            $this->set_property( 'twitter:site', '@' . ltrim( $tw_site, '@' ) );
        }

        // twitter:creator — normalize @ prefix
        if ( $this->col->has( 'meta::property::twitter:creator' ) ) {
            $creator = $this->tm->getContent( 'meta::property::twitter:creator' );
            if ( $creator && ! str_starts_with( $creator, '@' ) ) {
                $this->set_property( 'twitter:creator', '@' . $creator );
            }
        }
    }

    // ── Article meta (future expansion) ──────────────────────────────────

    private function inject_article(): void {
        // Placeholder for future tasks:
        // article:published_time, article:modified_time,
        // article:author, article:section, article:tag
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Resolve og:type for the current post.
     *
     * Priority:
     *   1. Attachment → resolved from MIME type
     *   2. og_type_map[post_type] from settings
     *   3. og_type_map[_default] fallback
     *
     * @return string OG type value.
     */
    private function resolve_og_type(): string {
        $post_type = get_post_type( $this->post_id );

        // Attachments: resolve from MIME type
        if ( 'attachment' === $post_type ) {
            return $this->resolve_attachment_og_type();
        }

        // Type map from settings
        $type_map = $this->opts->getArray( 'og_type_map', [
            'post' => 'article', 'page' => 'website', '_default' => 'article',
        ] );

        return $type_map[ $post_type ] ?? $type_map['_default'] ?? 'article';
    }

    /**
     * Resolve og:type for an attachment based on MIME type.
     *
     * @return string OG type value.
     */
    private function resolve_attachment_og_type(): string {
        $mime = get_post_mime_type( $this->post_id );
        if ( ! $mime ) {
            return 'website';
        }

        $major = explode( '/', $mime )[0];

        return match ( $major ) {
            'video' => 'video.other',
            'audio' => 'music.song',
            default => 'website',
        };
    }

    private function set_property( string $property, string $content ): void {
        $this->col->set(
            'meta::property::' . $property,
            ( new PropertyMetaTag() )->setProperty( $property )->setContent( $content )
        );
    }

    private function set_name( string $name, string $content ): void {
        $this->col->set(
            'meta::name::' . $name,
            ( new NameMetaTag() )->setName( $name )->setContent( $content )
        );
    }
}