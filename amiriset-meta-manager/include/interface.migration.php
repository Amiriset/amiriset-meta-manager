<?php

namespace Amiriset\MetaManager;
defined( 'ABSPATH' ) || exit;

/**
 * Interface <b>MigrationInterface</b> -- Contract for schema migrations.
 *
 * Each migration handles one version upgrade step.
 * Implementations live in include/migrations/ as individual files.
 *
 * @package Amiriset\MetaManager
 */
interface MigrationInterface {

    /**
     * Target schema version this migration upgrades TO.
     *
     * @return string Version string (e.g. '1.0.0-a.5').
     */
    public function getVersion(): string;

    /**
     * Apply migration to decoded JSON data.
     *
     * @param array $data Decoded post meta JSON.
     * @return array Migrated data.
     */
    public function apply( array $data ): array;

    /**
     * Human-readable description for admin UI.
     *
     * @return string
     */
    public function getDescription(): string;
}
