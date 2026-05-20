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

/**
 * Class <b>MetadataMigrator</b> -- Schema migration engine.
 *
 * Discovers migration classes from include/migrations/,
 * applies them sequentially, and integrates with WordPress
 * admin notices for upgrade prompts.
 *
 * Adding a new migration:
 *   1. Create include/migrations/class.migration-X.Y.Z.php
 *   2. Implement MigrationInterface
 *   3. Bump AMIRISET_META_MANAGER_SCHEMA_VERSION in constants.php
 *   4. Done — migrator auto-discovers and applies it.
 *
 * @version 1.0.0-a.5
 * @package Amiriset\MetaManager
 * @license GPL-3.0-or-later
 * @author Y.Frolov
 * @created 2026-05-20 15:44:10
 */
class MetadataMigrator {

    /** Option key for tracking last-known schema version on this install. */
    private const OPTION_SCHEMA_STATE = AMIRISET_META_MANAGER_SCHEMA_OPTION_KEY;

    /** @var MigrationInterface[]|null Cached migrations, sorted by version. */
    private static ?array $migrations = null;

    // ── Per-post migration ───────────────────────────────────────────────

    /**
     * Migrate a single post's raw JSON to current schema.
     *
     * @param string $json Raw JSON from post meta.
     * @return string Migrated JSON (unchanged if current or unparseable).
     */
    public static function migrate( string $json ): string {
        $data = json_decode( $json, true );
        if ( ! is_array( $data ) ) {
            return $json;
        }

        $stored  = $data['data']['_schema'] ?? null;
        $target  = AMIRISET_META_MANAGER_SCHEMA_VERSION;

        if ( $stored === $target ) {
            return $json;
        }

        $migrations = self::load_migrations();
        $apply      = false;

        foreach ( $migrations as $migration ) {
            $version = $migration->getVersion();

            if ( ! $apply ) {
                if ( null === $stored ) {
                    $apply = true;
                } elseif ( $version === $stored ) {
                    $apply = true;
                    continue;
                } else {
                    continue;
                }
            }

            $data = $migration->apply( $data );

            // Stamp version after each step
            if ( ! isset( $data['data'] ) ) {
                $data['data'] = [];
            }
            $data['data']['_schema'] = $version;
        }

        return json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    }

    // ── Migration discovery ──────────────────────────────────────────────

    /**
     * Discover and load all migration classes from include/migrations/.
     * Cached after first call.
     *
     * @return MigrationInterface[] Sorted by version (ascending).
     */
    private static function load_migrations(): array {
        if ( null !== self::$migrations ) {
            return self::$migrations;
        }

        $dir   = AMIRISET_META_MANAGER_PATH . 'include/migrations/';
        $files = glob( $dir . 'class.migration-*.php' );

        self::$migrations = [];

        if ( ! $files ) {
            return self::$migrations;
        }

        foreach ( $files as $file ) {
            require_once $file;
        }

        // Find all classes implementing MigrationInterface in our namespace
        foreach ( get_declared_classes() as $class ) {
            if (
                str_starts_with( $class, 'Amiriset\\MetaManager\\Migration_' ) &&
                is_subclass_of( $class, MigrationInterface::class )
            ) {
                self::$migrations[] = new $class();
            }
        }

        // Sort by version ascending
        usort( self::$migrations, static function ( MigrationInterface $a, MigrationInterface $b ): int {
            return version_compare( $a->getVersion(), $b->getVersion() );
        } );

        return self::$migrations;
    }

    // ── WordPress admin integration ──────────────────────────────────────

    /**
     * Register admin hooks for schema upgrade notices.
     * Called from Core::run() or init_hooks().
     */
    public static function init_hooks(): void {
        add_action( 'admin_init',                       [ self::class, 'check_schema_state' ] );
        add_action( 'admin_notices',                    [ self::class, 'render_upgrade_notice' ] );
        add_action( 'admin_post_amm_run_migration',     [ self::class, 'handle_run_migration' ] );
    }

    /**
     * On admin_init: compare installed schema version with current.
     * Stores state in options for notice rendering.
     */
    public static function check_schema_state(): void {
        $installed = get_option( self::OPTION_SCHEMA_STATE, '' );
        $current   = AMIRISET_META_MANAGER_SCHEMA_VERSION;

        if ( $installed === $current ) {
            return;
        }

        // First install or just updated — if no posts with old data, stamp and skip
        if ( '' === $installed ) {
            update_option( self::OPTION_SCHEMA_STATE, $current, true );
            return;
        }

        // Version mismatch detected — notice will render
        // (state stays at old version until migration runs)
    }

    /**
     * Show admin notice when schema upgrade is available.
     */
    public static function render_upgrade_notice(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $installed = get_option( self::OPTION_SCHEMA_STATE, '' );
        $current   = AMIRISET_META_MANAGER_SCHEMA_VERSION;

        if ( $installed === $current || '' === $installed ) {
            return;
        }

        $migrations  = self::load_migrations();
        $pending     = [];
        $found       = false;

        foreach ( $migrations as $m ) {
            if ( ! $found ) {
                if ( $m->getVersion() === $installed ) {
                    $found = true;
                }
                continue;
            }
            $pending[] = $m;
        }

        if ( empty( $pending ) ) {
            update_option( self::OPTION_SCHEMA_STATE, $current, true );
            return;
        }

        $run_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=amm_run_migration' ),
            'amm_run_migration'
        );
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php echo esc_html( AMIRISET_META_MANAGER_DISPLAY_NAME ); ?></strong>
                — <?php esc_html_e( 'Database schema update available.', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?>
                (<?php echo esc_html( $installed ); ?> → <?php echo esc_html( $current ); ?>)
            </p>
            <ul style="list-style:disc;margin-left:20px">
                <?php foreach ( $pending as $m ) : ?>
                    <li>
                        <code><?php echo esc_html( $m->getVersion() ); ?></code>
                        — <?php echo esc_html( $m->getDescription() ); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p>
                <a href="<?php echo esc_url( $run_url ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Run Migration', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?>
                </a>
                <span class="description" style="margin-left:8px">
                    <?php esc_html_e( 'Existing posts will be migrated on next load. This action updates the schema version marker.', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?>
                </span>
            </p>
        </div>
        <?php
    }

    /**
     * Handle "Run Migration" button click.
     * Stamps current schema version — individual posts migrate lazily on load.
     */
    public static function handle_run_migration(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Access denied.' );
        }

        check_admin_referer( 'amm_run_migration' );

        update_option( self::OPTION_SCHEMA_STATE, AMIRISET_META_MANAGER_SCHEMA_VERSION, true );

        wp_safe_redirect( admin_url( 'admin.php?page=amm-settings&amm_migrated=1' ) );
        exit;
    }
}
