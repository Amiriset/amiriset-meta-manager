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
 * Class <b>Options</b> -- Safe property bag for plugin settings.
 * 
 * Wraps the raw options array and provides null-safe access
 * with per-key defaults. Eliminates "Undefined array key" warnings.
 *
 * @version 1.0.0-a.5
 * @package Amiriset\MetaManager
 * @license GPL-3.0-or-later
 * @author Y.Frolov
 * @created 2026-05-17
 */
class Options {
    private array $data;

    /**
     * @param array $data Raw options array.
     */
    public function __construct( array $data ) {
        $this->data = $data;
    }

    /**
     * Load options from DB, merged with defaults.
     *
     * @return self
     */
    public static function load(): self {
        $stored   = get_option( AMIRISET_META_MANAGER_OPTION_KEY, [] );
        $defaults = Activator::defaults();
        return new self( array_merge( $defaults, is_array( $stored ) ? $stored : [] ) );
    }

    /**
     * Get a value by key. Never triggers "Undefined array key".
     *
     * @param string $key     Option key.
     * @param mixed  $default Fallback if key is missing.
     * @return mixed
     */
    public function get( string $key, mixed $default = '' ) {
        return $this->data[ $key ] ?? $default;
    }

    /**
     * Get a value as array.
     *
     * @param string $key     Option key.
     * @param array  $default Fallback.
     * @return array
     */
    public function getArray( string $key, array $default = [] ): array {
        $val = $this->data[ $key ] ?? $default;
        return is_array( $val ) ? $val : $default;
    }

    /**
     * Get a value as int.
     *
     * @param string $key     Option key.
     * @param int    $default Fallback.
     * @return int
     */
    public function getInt( string $key, int $default = 0 ): int {
        return (int) ( $this->data[ $key ] ?? $default );
    }

    /**
     * Check if a key exists and is non-empty.
     *
     * @param string $key Option key.
     * @return bool
     */
    public function has( string $key ): bool {
        return isset( $this->data[ $key ] ) && $this->data[ $key ] !== '';
    }

    /**
     * Set a value (in memory only — does not persist).
     *
     * @param string $key   Option key.
     * @param mixed  $value Value.
     */
    public function set( string $key, mixed $value ): void {
        $this->data[ $key ] = $value;
    }

    /**
     * Return the full raw array (for update_option).
     *
     * @return array
     */
    public function toArray(): array {
        return $this->data;
    }

    /**
     * Persist current state to DB.
     */
    public function save(): void {
        update_option( AMIRISET_META_MANAGER_OPTION_KEY, $this->data );
    }
}
