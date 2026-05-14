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
 * Class <b>MetaBox</b> -- Adds the "Amiriset Meta Manager" meta box to 
 * post/page/CPT edit screens. 
 * Stores/reads a single JSON object under the key defined in 
 * AMIRISET_META_MANAGER_META_KEY.
 *
 * @version 1.0.0-a.1
 * @package Amiriset\MetaManager
 * @license GPL-3.0-or-later
 * @author Y.Frolov 
 * @created 2026-05-14 14:01:51
 */
class MetaBox {
       
    /**
     * Default (empty) meta object structure
     * 
     * @return array
     */
    public static function empty_meta(): array {
        return [
            'title'        => '',
            'description'  => '',
            'keywords'     => '',
            'robots'       => '',
            'canonical'    => '',
            'og_title'     => '',
            'og_description' => '',
            'og_image'     => '',
            'og_type'      => '',
            'custom_meta'  => [],   // [ ['attr_type'=>'name','attr_value'=>'','content'=>''], … ]
        ];
    }
    
    /**
     * Init hooks.
     * 
     * @return void
     */
    public function init_hooks(): void {
        add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ] );
        add_action( 'save_post',      [ $this, 'save_meta' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Register Meta Box.
     * 
     * @return void
     */
    public function register_meta_box(): void {
        $options    = get_option(AMIRISET_META_MANAGER_OPTION_KEY, Activator::defaults() );
        $post_types = $options['enabled_post_types'] ?? [ 'post', 'page' ];

        foreach ( $post_types as $pt ) {
            add_meta_box(
                'amm_meta_box',
                __(AMIRISET_META_MANAGER_DISPLAY_NAME, AMIRISET_META_MANAGER_TEXT_DOMAIN ),
                [ $this, 'render_meta_box' ],
                $pt,
                'normal',
                'high'
            );
        }
    }

    /**
     * Enqueue assets.
     * 
     * @param string $hook
     * @return void
     */
    public function enqueue_assets( string $hook ): void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }

        wp_enqueue_style(
            'amm-admin-css',
            AMIRISET_META_MANAGER_URL . 'admin/css/amm-admin.css',
            [],
            AMIRISET_META_MANAGER_VERSION
        );

        wp_enqueue_media(); // for OG image picker

        wp_enqueue_script(
            'amm-admin-js',
            AMIRISET_META_MANAGER . 'admin/js/amm-admin.js',
            [ 'jquery' ],
            AMIRISET_META_MANAGER_VERSION,
            true
        );

        $options = get_option( AMIRISET_META_MANAGER_OPTION_KEY, Activator::defaults() );

        wp_localize_script( 'amm-admin-js', 'ammData', [
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'amm_ajax_nonce' ),
            'postId'     => get_the_ID(),
            'minSymbols' => $options['keyword_min_symbols'] ?? 4,
            'maxWords'   => $options['keyword_max_words']   ?? 25,
            'lang'       => $options['keyword_lang']        ?? '',
            'i18n'       => [
                'generating'  => __( 'Generating…', AMIRISET_META_MANAGER_TEXT_DOMAIN ),
                'addKeyword'  => __( 'Click a keyword to add it', AMIRISET_META_MANAGER_TEXT_DOMAIN ),
                'noKeywords'  => __( 'No keywords found', AMIRISET_META_MANAGER_TEXT_DOMAIN ),
                'selectImage' => __( 'Select image', AMIRISET_META_MANAGER_TEXT_DOMAIN ),
                'useImage'    => __( 'Use this image', AMIRISET_META_MANAGER_TEXT_DOMAIN ),
            ],
        ] );
    }

    /**
     * Render meta box.
     * 
     * @param WP_Post $post
     * @return void
     */
    public function render_meta_box( WP_Post $post ): void {
        $raw  = get_post_meta( $post->ID, AMIRISET_META_MANAGER_DB_KEY, true );
        $meta = $raw ? array_merge( self::empty_meta(), json_decode( $raw, true ) ?? [] )
                     : self::empty_meta();

        wp_nonce_field( 'amm_save_meta_' . $post->ID, 'amm_meta_nonce' );
        ?>
        <div class="amm-wrap">

            <!-- ── Tabs ── -->
            <ul class="amm-tabs">
                <li class="amm-tab-link active" data-tab="amm-tab-basic"><?php esc_html_e( 'Basic SEO', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></li>
                <li class="amm-tab-link" data-tab="amm-tab-og"><?php esc_html_e( 'Open Graph', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></li>
                <li class="amm-tab-link" data-tab="amm-tab-custom"><?php esc_html_e( 'Custom Tags', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></li>
                <li class="amm-tab-link" data-tab="amm-tab-keywords"><?php esc_html_e( '💡 Keywords', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></li>
            </ul>

            <!-- ── Tab: Basic SEO ── -->
            <div id="amm-tab-basic" class="amm-tab-content active">
                <table class="amm-table">
                    <tr>
                        <th><label for="amm_title"><?php esc_html_e( 'SEO Title', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></label></th>
                        <td>
                            <input type="text" id="amm_title" name="amm[title]"
                                   value="<?php echo esc_attr( $meta['title'] ); ?>"
                                   placeholder="<?php echo esc_attr( get_the_title( $post ) ); ?>">
                            <p class="description"><?php esc_html_e( 'Leave empty to use post title.', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="amm_description"><?php esc_html_e( 'Description', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></label></th>
                        <td>
                            <textarea id="amm_description" name="amm[description]" rows="3"
                                      maxlength="320"><?php echo esc_textarea( $meta['description'] ); ?></textarea>
                            <p class="description amm-counter" data-field="amm_description">0 / 320</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="amm_keywords"><?php esc_html_e( 'Keywords', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></label></th>
                        <td>
                            <input type="text" id="amm_keywords" name="amm[keywords]"
                                   value="<?php echo esc_attr( $meta['keywords'] ); ?>"
                                   placeholder="keyword1, keyword2, keyword3">
                            <p class="description"><?php esc_html_e( 'Comma-separated. Use the Keywords tab for auto-suggestions.', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="amm_robots"><?php esc_html_e( 'Robots', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></label></th>
                        <td>
                            <input type="text" id="amm_robots" name="amm[robots]"
                                   value="<?php echo esc_attr( $meta['robots'] ); ?>"
                                   placeholder="index, follow">
                            <p class="description"><?php esc_html_e( 'Leave empty to use global default.', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="amm_canonical"><?php esc_html_e( 'Canonical URL', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></label></th>
                        <td>
                            <input type="url" id="amm_canonical" name="amm[canonical]"
                                   value="<?php echo esc_attr( $meta['canonical'] ); ?>"
                                   placeholder="<?php echo esc_attr( get_permalink( $post ) ); ?>">
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ── Tab: Open Graph ── -->
            <div id="amm-tab-og" class="amm-tab-content">
                <table class="amm-table">
                    <tr>
                        <th><label for="amm_og_title"><?php esc_html_e( 'OG Title', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></label></th>
                        <td><input type="text" id="amm_og_title" name="amm[og_title]"
                                   value="<?php echo esc_attr( $meta['og_title'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="amm_og_description"><?php esc_html_e( 'OG Description', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></label></th>
                        <td><textarea id="amm_og_description" name="amm[og_description]"
                                      rows="3"><?php echo esc_textarea( $meta['og_description'] ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'OG Image', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></label></th>
                        <td>
                            <div class="amm-og-image-wrap">
                                <?php if ( $meta['og_image'] ) : ?>
                                    <img src="<?php echo esc_url( $meta['og_image'] ); ?>" class="amm-og-preview" alt="">
                                <?php endif; ?>
                                <input type="hidden" id="amm_og_image" name="amm[og_image]"
                                       value="<?php echo esc_attr( $meta['og_image'] ); ?>">
                                <button type="button" class="button amm-media-btn" data-target="amm_og_image">
                                    <?php esc_html_e( 'Select image', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?>
                                </button>
                                <?php if ( $meta['og_image'] ) : ?>
                                    <button type="button" class="button amm-media-remove">
                                        <?php esc_html_e( 'Remove', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="amm_og_type"><?php esc_html_e( 'OG Type', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></label></th>
                        <td>
                            <select id="amm_og_type" name="amm[og_type]">
                                <?php
                                $og_types = [ 'website', 'article', 'product', 'profile', 'video.other', 'music.song' ];
                                foreach ( $og_types as $t ) {
                                    printf(
                                        '<option value="%1$s" %2$s>%1$s</option>',
                                        esc_attr( $t ),
                                        selected( $meta['og_type'], $t, false )
                                    );
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ── Tab: Custom Meta Tags ── -->
            <div id="amm-tab-custom" class="amm-tab-content">
                <p class="description"><?php esc_html_e( 'Add arbitrary meta tags. Each row = one <meta> element.', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></p>
                <table class="amm-table amm-custom-meta-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Attribute', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></th>
                            <th><?php esc_html_e( 'Value', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></th>
                            <th><?php esc_html_e( 'Content', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="amm-custom-meta-rows">
                        <?php
                        $custom = is_array( $meta['custom_meta'] ) ? $meta['custom_meta'] : [];
                        foreach ( $custom as $i => $cm ) :
                            $this->render_custom_meta_row( $i, $cm );
                        endforeach;
                        ?>
                    </tbody>
                </table>
                <button type="button" class="button amm-add-custom-meta">
                    + <?php esc_html_e( 'Add Meta Tag', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?>
                </button>

                <!-- Template row (hidden) -->
                <script type="text/html" id="amm-custom-meta-tpl">
                    <?php $this->render_custom_meta_row( '__IDX__', [ 'attr_type' => 'name', 'attr_value' => '', 'content' => '' ] ); ?>
                </script>
            </div>

            <!-- ── Tab: Keyword Suggestions ── -->
            <div id="amm-tab-keywords" class="amm-tab-content">
                <p><?php esc_html_e( 'Auto-generate keyword suggestions from this post\'s content.', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></p>
                <div class="amm-kw-controls">
                    <button type="button" class="button button-secondary" id="amm-gen-keywords">
                        ⚡ <?php esc_html_e( 'Generate Suggestions', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?>
                    </button>
                    <select id="amm-kw-lang">
                        <option value=""><?php esc_html_e( 'Auto-detect lang', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></option>
                        <option value="en">English</option>
                        <option value="ru">Русский</option>
                        <option value="uk">Українська</option>
                    </select>
                </div>
                <div id="amm-kw-result" class="amm-kw-chips"></div>
                <p class="description"><?php esc_html_e( 'Click a chip to append it to the Keywords field.', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></p>
            </div>

        </div><!-- .amm-wrap -->
        <?php
    }

    /** Renders a single custom-meta table row    
     * 
     * @param type $idx
     * @param array $row
     * @return void
     */
    private function render_custom_meta_row( $idx, array $row ): void {
        $attr_type  = $row['attr_type']  ?? 'name';
        $attr_value = $row['attr_value'] ?? '';
        $content    = $row['content']    ?? '';
        ?>
        <tr class="amm-custom-meta-row">
            <td>
                <select name="amm[custom_meta][<?php echo esc_attr( $idx ); ?>][attr_type]">
                    <option value="name"     <?php selected( $attr_type, 'name'     ); ?>>name</option>
                    <option value="property" <?php selected( $attr_type, 'property' ); ?>>property</option>
                    <option value="http-equiv" <?php selected( $attr_type, 'http-equiv' ); ?>>http-equiv</option>
                </select>
            </td>
            <td>
                <input type="text" name="amm[custom_meta][<?php echo esc_attr( $idx ); ?>][attr_value]"
                       value="<?php echo esc_attr( $attr_value ); ?>"
                       placeholder="e.g. author / og:locale">
            </td>
            <td>
                <input type="text" name="amm[custom_meta][<?php echo esc_attr( $idx ); ?>][content]"
                       value="<?php echo esc_attr( $content ); ?>"
                       placeholder="meta content value">
            </td>
            <td>
                <button type="button" class="button-link amm-remove-row" title="<?php esc_attr_e( 'Remove', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?>">✕</button>
            </td>
        </tr>
        <?php
    }

    
    /**
     * Save meta.
     * 
     * @param int $post_id
     * @param WP_Post $post
     * @return void
     */
    public function save_meta( int $post_id, WP_Post $post ): void {
        // Autosave / revision guard
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        // Nonce check
        if (
            ! isset( $_POST['amm_meta_nonce'] ) ||
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['amm_meta_nonce'] ) ), 'amm_save_meta_' . $post_id )
        ) {
            return;
        }

        // Capability check
        $post_type_obj = get_post_type_object( $post->post_type );
        if ( ! current_user_can( $post_type_obj->cap->edit_post, $post_id ) ) {
            return;
        }

        if ( ! isset( $_POST['amm'] ) || ! is_array( $_POST['amm'] ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $raw = wp_unslash( $_POST['amm'] );

        $data = [
            'title'          => sanitize_text_field( $raw['title']          ?? '' ),
            'description'    => sanitize_textarea_field( $raw['description'] ?? '' ),
            'keywords'       => sanitize_text_field( $raw['keywords']       ?? '' ),
            'robots'         => sanitize_text_field( $raw['robots']         ?? '' ),
            'canonical'      => esc_url_raw( $raw['canonical']              ?? '' ),
            'og_title'       => sanitize_text_field( $raw['og_title']       ?? '' ),
            'og_description' => sanitize_textarea_field( $raw['og_description'] ?? '' ),
            'og_image'       => esc_url_raw( $raw['og_image']               ?? '' ),
            'og_type'        => sanitize_text_field( $raw['og_type']        ?? '' ),
            'custom_meta'    => $this->sanitize_custom_meta( $raw['custom_meta'] ?? [] ),
        ];

        // Remove rows that are completely empty
        $data['custom_meta'] = array_values( array_filter(
            $data['custom_meta'],
            static fn( $cm ) => '' !== $cm['attr_value'] || '' !== $cm['content']
        ) );

        // Only store non-trivial records (avoid noise in DB)
        $meaningful = array_filter( $data, static fn( $v ) => '' !== $v && [] !== $v );

        if ( empty( $meaningful ) ) {
            delete_post_meta( $post_id, AMIRISET_META_MANAGER_DB_KEY );
        } else {
            update_post_meta( $post_id, AMIRISET_META_MANAGER_DB_KEY, wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) );
        }
    }
    
    /**
     * Santize custom meta
     * 
     * @param type $raw
     * @return array
     */
    private function sanitize_custom_meta( $raw ): array {
        if ( ! is_array( $raw ) ) {
            return [];
        }
        $allowed_attr_types = [ 'name', 'property', 'http-equiv' ];
        $result = [];
        foreach ( $raw as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $type = sanitize_key( $row['attr_type'] ?? 'name' );
            if ( ! in_array( $type, $allowed_attr_types, true ) ) {
                $type = 'name';
            }
            $result[] = [
                'attr_type'  => $type,
                'attr_value' => sanitize_text_field( $row['attr_value'] ?? '' ),
                'content'    => sanitize_text_field( $row['content']    ?? '' ),
            ];
        }
        return $result;
    }

    /**
     * Read and decode the JSON meta for a given post.
     * Always returns a full array (merged with defaults).
     * 
     * @param int $post_id
     * @return array
     */
    public static function get_meta( int $post_id ): array {
        $raw = get_post_meta( $post_id, AMIRISET_META_MANAGER, true );
        if ( ! $raw ) {
            return self::empty_meta();
        }
        return array_merge( self::empty_meta(), json_decode( $raw, true ) ?? [] );
    }
}
