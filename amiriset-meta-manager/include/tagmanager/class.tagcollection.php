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
 * Class <b>TagCollection</b> -- indexed tag storage.
 *
 * Keys are semantic paths: meta::name::description, meta::property::og:title,
 * script, link, jsonld.  Values are either a single AbstractTag or an array
 * of AbstractTag instances (article:tag, script, link).
 *
 * @version 1.0.0-a.5
 * @package Amiriset\MetaManager
 * @license GPL-3.0-or-later
 * @author Y.Frolov 
 * @created 2026-05-17 17:34:26
 */
class TagCollection {
    private array $tags = [];

    // ── Create / Update ──────────────────────────────────────────────────

    /**
     * Set (create or replace) a value by key.
     */
    public function set( string $key, $value ): void {
        $this->tags[ $key ] = $value;
    }

    /**
     * Append a single tag to an array-valued key.
     * If the key does not exist or is scalar, it becomes an array.
     */
    public function append( string $key, AbstractTag $tag ): void {
        if ( ! isset( $this->tags[ $key ] ) ) {
            $this->tags[ $key ] = [];
        }
        if ( ! is_array( $this->tags[ $key ] ) ) {
            $this->tags[ $key ] = [ $this->tags[ $key ] ];
        }
        $this->tags[ $key ][] = $tag;
    }

    // ── Read ─────────────────────────────────────────────────────────────

    /**
     * Get a value by key (single tag, array of tags, or null).
     */
    public function get( string $key ) {
        return $this->tags[ $key ] ?? null;
    }

    /**
     * Check if a key exists.
     */
    public function has( string $key ): bool {
        return array_key_exists( $key, $this->tags );
    }

    /**
     * Return all entries.
     */
    public function getAll(): array {
        return $this->tags;
    }

    /**
     * Return all keys.
     */
    public function keys(): array {
        return array_keys( $this->tags );
    }

    /**
     * Total number of keys.
     */
    public function count(): int {
        return count( $this->tags );
    }

    /**
     * Get all entries whose key starts with the given prefix.
     *
     * Example: getByPrefix('meta::property::og:') → all OG tags.
     *
     * @param  string $prefix Key prefix.
     * @return array  [ key => value, ... ]
     */
    public function getByPrefix( string $prefix ): array {
        $result = [];
        foreach ( $this->tags as $key => $value ) {
            if ( str_starts_with( $key, $prefix ) ) {
                $result[ $key ] = $value;
            }
        }
        return $result;
    }

    // ── Delete ───────────────────────────────────────────────────────────

    /**
     * Remove a single key.
     */
    public function remove( string $key ): void {
        unset( $this->tags[ $key ] );
    }

    /**
     * Remove all keys that start with the given prefix.
     */
    public function removeByPrefix( string $prefix ): void {
        foreach ( array_keys( $this->tags ) as $key ) {
            if ( str_starts_with( $key, $prefix ) ) {
                unset( $this->tags[ $key ] );
            }
        }
    }

    /**
     * Remove an element from an array-valued key by its index.
     */
    public function removeAt( string $key, int $index ): void {
        if ( ! isset( $this->tags[ $key ] ) || ! is_array( $this->tags[ $key ] ) ) {
            return;
        }
        array_splice( $this->tags[ $key ], $index, 1 );
        if ( empty( $this->tags[ $key ] ) ) {
            unset( $this->tags[ $key ] );
        }
    }

    /**
     * Remove all entries.
     */
    public function clear(): void {
        $this->tags = [];
    }

    // ── Merge ────────────────────────────────────────────────────────────

    /**
     * Merge another collection into this one.
     * Scalar keys: $other wins (override).
     * Array keys: delegated to mergeArray().
     *
     * @param TagCollection $other  Overriding collection (e.g. post-level).
     */
    public function merge( TagCollection $other ): void {
        foreach ( $other->getAll() as $key => $value ) {
            if ( is_array( $value ) && isset( $this->tags[ $key ] ) && is_array( $this->tags[ $key ] ) ) {
                $this->mergeArray( $key, $value );
            } else {
                $this->tags[ $key ] = $value;
            }
        }
    }

    /**
     * Diff-merge for array-valued keys (article:tag, script, link).
     *
     * Algorithm:
     *   1. Take existing array from collection.
     *   2. Build new array:
     *      - Skip elements whose hash is in $deletedHashes.
     *      - If incoming element hash matches existing → keep existing (unchanged).
     *      - If incoming element hash is new → append at end.
     *   3. Overwrite collection key with the result.
     *
     * @param string $key            Collection key.
     * @param array  $incoming       New set of tags.
     * @param array  $deletedHashes  Hashes of elements to remove.
     */
    public function mergeArray( string $key, array $incoming, array $deletedHashes = [] ): void {
        $existing = $this->tags[ $key ] ?? [];
        if ( ! is_array( $existing ) ) {
            $existing = [ $existing ];
        }

        // Index existing by hash
        $existingByHash = [];
        foreach ( $existing as $tag ) {
            $existingByHash[ $tag->hash() ] = $tag;
        }

        // Remove deleted
        foreach ( $deletedHashes as $h ) {
            unset( $existingByHash[ $h ] );
        }

        // Walk incoming: match by hash or stage as new
        $result  = [];
        $newTags = [];

        foreach ( $incoming as $tag ) {
            $h = $tag->hash();
            if ( isset( $existingByHash[ $h ] ) ) {
                // Unchanged — keep existing, mark as processed
                $result[] = $existingByHash[ $h ];
                unset( $existingByHash[ $h ] );
            } else {
                // Modified or new
                $newTags[] = $tag;
            }
        }

        // Keep remaining untouched existing (not in incoming, not deleted)
        foreach ( $existingByHash as $tag ) {
            $result[] = $tag;
        }

        // Append new at end
        foreach ( $newTags as $tag ) {
            $result[] = $tag;
        }

        $this->tags[ $key ] = $result;
    }
}
