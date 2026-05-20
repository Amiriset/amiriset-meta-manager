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
 * Class <b>Migration_1_0_0_a_5</b> -- Migration: legacy (no schema / flat format) 
 * → 1.0.0-a.5
 *
 * Converts pre-TagManager flat JSON:
 *   {"title":"...","og_title":"...","custom_meta":[...]}
 * To nested TagManager format:
 *   {"meta":{"name":{...},"property":{"og":{...}}},...}
 *
 * @version 1.0.0-a.5
 * @package Amiriset\MetaManager
 * @license GPL-3.0-or-later
 * @author Y.Frolov 
 * @created 2026-05-20 15:48:03
 */
class Migration_1_0_0_a_5 implements MigrationInterface {

    public function getVersion(): string {
        return '1.0.0-a.5';
    }

    public function getDescription(): string {
        return 'Convert legacy flat meta format to TagManager nested structure.';
    }

    public function apply( array $data ): array {
        // Already in TagManager format — no-op
        if ( isset( $data['meta'] ) || isset( $data['link'] ) || isset( $data['script'] ) ) {
            return $data;
        }

        $result = [ 'meta' => [ 'name' => [], 'property' => [] ] ];

        // Flat key → [section, namespace, key]
        $map = [
            'description'    => [ 'name', null, 'description' ],
            'keywords'       => [ 'name', null, 'keywords' ],
            'robots'         => [ 'name', null, 'robots' ],
            'og_title'       => [ 'property', 'og', 'title' ],
            'og_description' => [ 'property', 'og', 'description' ],
            'og_image'       => [ 'property', 'og', 'image' ],
            'og_type'        => [ 'property', 'og', 'type' ],
            'tw_card'        => [ 'property', 'twitter', 'card' ],
            'tw_title'       => [ 'property', 'twitter', 'title' ],
            'tw_description' => [ 'property', 'twitter', 'description' ],
            'tw_image'       => [ 'property', 'twitter', 'image' ],
            'tw_creator'     => [ 'property', 'twitter', 'creator' ],
        ];

        foreach ( $map as $old_key => $path ) {
            $value = $data[ $old_key ] ?? '';
            if ( '' === $value ) {
                continue;
            }

            [ $section, $ns, $key ] = $path;
            if ( $ns ) {
                $result['meta'][ $section ][ $ns ][ $key ] = $value;
            } else {
                $result['meta'][ $section ][ $key ] = $value;
            }
        }

        // Canonical
        $canonical = $data['canonical'] ?? '';
        if ( $canonical ) {
            $result['link'] = [ [ 'rel' => 'canonical', 'href' => $canonical ] ];
        }

        // Custom meta tags
        if ( ! empty( $data['custom_meta'] ) && is_array( $data['custom_meta'] ) ) {
            foreach ( $data['custom_meta'] as $cm ) {
                $type  = $cm['attr_type']  ?? 'name';
                $attr  = $cm['attr_value'] ?? '';
                $value = $cm['content']    ?? '';
                if ( $attr && $value ) {
                    if ( ! isset( $result['meta'][ $type ] ) ) {
                        $result['meta'][ $type ] = [];
                    }
                    $result['meta'][ $type ][ $attr ] = $value;
                }
            }
        }

        // Clean empty sections
        $result['meta'] = array_filter( $result['meta'] );

        return $result;
    }
}
