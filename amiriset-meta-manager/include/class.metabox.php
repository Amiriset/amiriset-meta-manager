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
 * @version 1.0.0-a.5
 * @package Amiriset\MetaManager
 * @license GPL-3.0-or-later
 * @author Y.Frolov 
 * @created 2026-05-14 14:01:51
 */
class MetaBox {

    /**
     * Mapping: form field name → TagCollection key.
     * POST data arrives as amm[og_title], amm[description], etc.
     * Only meta tags — canonical and custom_meta handled separately.
     */
    private const FIELD_MAP = [
        'description'    => 'meta::name::description',
        'keywords'       => 'meta::name::keywords',
        'robots'         => 'meta::name::robots',
        'og_title'       => 'meta::property::og:title',
        'og_description' => 'meta::property::og:description',
        'og_image'       => 'meta::property::og:image',
        'og_type'        => 'meta::property::og:type',
        'tw_card'        => 'meta::property::twitter:card',
        'tw_title'       => 'meta::property::twitter:title',
        'tw_description' => 'meta::property::twitter:description',
        'tw_image'       => 'meta::property::twitter:image',
        'tw_creator'     => 'meta::property::twitter:creator',
    ];

    /**
     * Sanitizer per form field.
     */
    private const SANITIZERS = [
        'description'    => 'sanitize_textarea_field',
        'keywords'       => 'sanitize_text_field',
        'robots'         => 'sanitize_text_field',
        'canonical'      => 'esc_url_raw',
        'og_title'       => 'sanitize_text_field',
        'og_description' => 'sanitize_textarea_field',
        'og_image'       => 'esc_url_raw',
        'og_type'        => 'sanitize_text_field',
        'tw_card'        => 'sanitize_text_field',
        'tw_title'       => 'sanitize_text_field',
        'tw_description' => 'sanitize_textarea_field',
        'tw_image'       => 'esc_url_raw',
        'tw_creator'     => 'sanitize_text_field',
    ];

    /**
     * Load TagManager from post meta.
     *
     * @param int $post_id Post ID.
     * @return TagManager Populated TagManager (empty if no meta stored).
     */
    public static function load( int $post_id ): TagManager {
        $tm  = new TagManager();
        $raw = get_post_meta( $post_id, AMIRISET_META_MANAGER_DB_KEY, true );
        if ( $raw ) {
            $tm->deserealise( $raw );
        }
        return $tm;
    }

    /**
     * Read canonical URL from TagManager collection.
     *
     * @param TagManager $tm TagManager instance.
     * @return string Canonical URL or empty string.
     */
    public static function get_canonical( TagManager $tm ): string {
        $links = $tm->getCollection()->get( 'link' );
        if ( is_array( $links ) ) {
            foreach ( $links as $link ) {
                if ( $link instanceof LinkTag && $link->getValue( 'rel' ) === 'canonical' ) {
                    return $link->getValue( 'href' ) ?? '';
                }
            }
        }
        return '';
    }

    /**
     * Extract custom meta tags from TagManager collection.
     * Returns tags that are not in FIELD_MAP.
     *
     * @param TagManager $tm TagManager instance.
     * @return array [ ['attr_type'=>'name','attr_value'=>'author','content'=>'John'], ... ]
     */
    public static function extract_custom_meta( TagManager $tm ): array {
        $built_in = array_flip( self::FIELD_MAP );
        $result   = [];
        $collection = $tm->getCollection();

        foreach ( $collection->getAll() as $key => $value ) {
            if ( ! str_starts_with( $key, 'meta::' ) ) {
                continue;
            }
            if ( isset( $built_in[ $key ] ) ) {
                continue;
            }

            // Parse: meta::name::author → name, author
            // Parse: meta::property::fb:app_id → property, fb:app_id
            $parts = explode( '::', $key, 3 );
            if ( count( $parts ) < 3 ) {
                continue;
            }

            $content = ( $value instanceof MetaTag ) ? ( $value->getContent() ?? '' ) : '';
            if ( '' === $content ) {
                continue;
            }

            $result[] = [
                'attr_type'  => $parts[1],
                'attr_value' => $parts[2],
                'content'    => $content,
            ];
        }

        return $result;
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
        $options    = Utils::GET_OPTIONS();
        $post_types = $options->getArray( 'enabled_post_types', [ 'post', 'page' ] );

        foreach ( $post_types as $pt ) {
            add_meta_box(
                'amm_meta_box',
                Utils::LANG(AMIRISET_META_MANAGER_DISPLAY_NAME),
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
            AMIRISET_META_MANAGER_URL . 'admin/js/amm-admin.js',
            [ 'jquery' ],
            AMIRISET_META_MANAGER_VERSION,
            true
        );

        $options = Utils::GET_OPTIONS();

        wp_localize_script( 'amm-admin-js', 'ammData', [
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'amm_ajax_nonce' ),
            'postId'     => get_the_ID(),
            'minSymbols' => $options->getInt( 'kw_min_symbols', 4 ),
            'maxWords'   => $options->getInt( 'kw_max_words', 10 ),
            'lang'       => $options->get( 'kw_lang', 'auto' ),
            'i18n'       => [
                'generating'  => Utils::LANG('Generating…'),
                'addKeyword'  => Utils::LANG('Click a keyword to add it'),
                'noKeywords'  => Utils::LANG('No keywords found'),
                'selectImage' => Utils::LANG('Select image'),
                'removeImage' => Utils::LANG('Remove'),
                'useImage'    => Utils::LANG('Use this image'),
            ],
        ] );
    }

    /**
     * Render meta box.
     * 
     * @param WP_Post $post
     * @return void
     */
    public function render_meta_box( \WP_Post $post ): void {
        $tm = self::load( $post->ID );

        // Flat view for template: form field name → value
        $f = [];
        foreach ( self::FIELD_MAP as $form_field => $tc_key ) {
            $f[ $form_field ] = $tm->getContent( $tc_key );
        }
        $f['canonical'] = self::get_canonical( $tm );

        // Custom meta rows
        $custom = self::extract_custom_meta( $tm );

        wp_nonce_field( 'amm_save_meta_' . $post->ID, 'amm_meta_nonce' );
        ?>
        <div class="amm-wrap">

            <!-- ── Tabs ── -->
            <ul class="amm-tabs">
                <li class="amm-tab-link active" data-tab="amm-tab-basic"><?php Utils::ESC_HTML_E('Basic SEO'); ?></li>
                <li class="amm-tab-link" data-tab="amm-tab-og"><?php Utils::ESC_HTML_E('Open Graph'); ?></li>
                <li class="amm-tab-link" data-tab="amm-tab-custom"><?php Utils::ESC_HTML_E('Custom Tags'); ?></li>
                <li class="amm-tab-link" data-tab="amm-tab-keywords"><?php Utils::ESC_HTML_E('💡 Keywords'); ?></li>
                <li class="amm-tab-link" data-tab="amm-tab-twitter"><?php Utils::ESC_HTML_E( '𝕏 Twitter' ); ?></li>
            </ul>

            <!-- ── Tab: Basic SEO ── -->
            <div id="amm-tab-basic" class="amm-tab-content active">
                <table class="amm-table">
                    <tr>
                        <th><label for="amm_description"><?php Utils::ESC_HTML_E('Description'); ?></label></th>
                        <td>
                            <textarea id="amm_description" name="amm[description]" rows="3"
                                      maxlength="320"><?php echo esc_textarea( $f['description'] ); ?></textarea>
                            <p class="description amm-counter" data-field="amm_description">0 / 320</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="amm_keywords"><?php Utils::ESC_HTML_E('Keywords'); ?></label></th>
                        <td>
                            <input type="text" id="amm_keywords" name="amm[keywords]"
                                   value="<?php echo esc_attr( $f['keywords'] ); ?>"
                                   placeholder="keyword1, keyword2, keyword3">
                            <p class="description"><?php Utils::ESC_HTML_E('Comma-separated. Use the Keywords tab for auto-suggestions.'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="amm_robots"><?php Utils::ESC_HTML_E('Robots'); ?></label></th>
                        <td>
                            <input type="text" id="amm_robots" name="amm[robots]"
                                   value="<?php echo esc_attr( $f['robots'] ); ?>"
                                   placeholder="index, follow">
                            <p class="description"><?php Utils::ESC_HTML_E('Leave empty to use global default.'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="amm_canonical"><?php Utils::ESC_HTML_E('Canonical URL'); ?></label></th>
                        <td>
                            <input type="url" id="amm_canonical" name="amm[canonical]"
                                   value="<?php echo esc_attr( $f['canonical'] ); ?>"
                                   placeholder="<?php echo esc_attr( get_permalink( $post ) ); ?>">
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ── Tab: Open Graph ── -->
            <div id="amm-tab-og" class="amm-tab-content">
                <table class="amm-table">
                    <tr>
                        <th><label for="amm_og_title"><?php Utils::ESC_HTML_E('OG Title'); ?></label></th>
                        <td><input type="text" id="amm_og_title" name="amm[og_title]"
                                   value="<?php echo esc_attr( $f['og_title'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="amm_og_description"><?php Utils::ESC_HTML_E('OG Description'); ?></label></th>
                        <td><textarea id="amm_og_description" name="amm[og_description]"
                                      rows="3"><?php echo esc_textarea( $f['og_description'] ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label><?php Utils::ESC_HTML_E('OG Image'); ?></label></th>
                        <td>
                            <div class="amm-og-image-wrap">
                                <?php if ( $f['og_image'] ) : ?>
                                    <img src="<?php echo esc_url( $f['og_image'] ); ?>" class="amm-og-preview" alt="">
                                <?php endif; ?>
                                <input type="hidden" id="amm_og_image" name="amm[og_image]"
                                       value="<?php echo esc_attr( $f['og_image'] ); ?>">
                                <button type="button" class="button amm-media-btn" data-target="amm_og_image">
                                    <?php Utils::ESC_HTML_E('Select image'); ?>
                                </button>
                                <?php if ( $f['og_image'] ) : ?>
                                    <button type="button" class="button amm-media-remove">
                                        <?php Utils::ESC_HTML_E('Remove'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="amm_og_type"><?php Utils::ESC_HTML_E('OG Type'); ?></label></th>
                        <td>
                            <select id="amm_og_type" name="amm[og_type]">
                                <?php
                                $og_types = [ 'website', 'article', 'product', 'profile', 'video.other', 'music.song' ];
                                foreach ( $og_types as $t ) {
                                    printf(
                                        '<option value="%1$s" %2$s>%1$s</option>',
                                        esc_attr( $t ),
                                        selected( $f['og_type'], $t, false )
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
                <p class="description"><?php Utils::ESC_HTML_E('Add arbitrary meta tags. Each row = one <meta> element.'); ?></p>
                <table class="amm-table amm-custom-meta-table">
                    <thead>
                        <tr>
                            <th><?php Utils::ESC_HTML_E('Attribute'); ?></th>
                            <th><?php Utils::ESC_HTML_E('Value'); ?></th>
                            <th><?php Utils::ESC_HTML_E('Content'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="amm-custom-meta-rows">
                        <?php
                        foreach ( $custom as $i => $cm ) :
                            $this->render_custom_meta_row( $i, $cm );
                        endforeach;
                        ?>
                    </tbody>
                </table>
                <button type="button" class="button amm-add-custom-meta">
                    + <?php Utils::ESC_HTML_E('Add Meta Tag'); ?>
                </button>

                <!-- Template row (hidden) -->
                <script type="text/html" id="amm-custom-meta-tpl">
                    <?php $this->render_custom_meta_row( '__IDX__', [ 'attr_type' => 'name', 'attr_value' => '', 'content' => '' ] ); ?>
                </script>
            </div>

            <!-- ── Tab: Keyword Suggestions ── -->
            <div id="amm-tab-keywords" class="amm-tab-content">
                <p><?php Utils::ESC_HTML_E('Auto-generate keyword suggestions from this post\'s content.'); ?></p>
                <div class="amm-kw-controls">
                    <button type="button" class="button button-secondary" id="amm-gen-keywords">
                        ⚡ <?php Utils::ESC_HTML_E('Generate Suggestions'); ?>
                    </button>
                    <select id="amm-kw-lang">
                        <option value=""><?php Utils::ESC_HTML_E('Auto-detect lang'); ?></option>
                        <option value="en">English</option>
                        <option value="ru">Русский</option>
                        <option value="uk">Українська</option>
                    </select>
                </div>
                <div id="amm-kw-result" class="amm-kw-chips"></div>
                <p class="description"><?php Utils::ESC_HTML_E('Click a chip to append it to the Keywords field.'); ?></p>
            </div>
            
            <!-- ── Tab: Twitter / X Card ── -->
            <div id="amm-tab-twitter" class="amm-tab-content">
                <?php
                $opts        = Utils::GET_OPTIONS();
                $tw_site_global = $opts->get( 'twitter_site' );
                ?>
                <p class="amm-copy-og-wrap">
                    <button type="button" class="button button-secondary" id="amm-copy-from-og">
                        📋 <?php Utils::ESC_HTML_E('Copy from Open Graph'); ?>
                    </button>
                    <span class="description"><?php Utils::ESC_HTML_E('Fill empty Twitter fields from OG values above.'); ?></span>
                </p>
                <table class="amm-table">
                    <tr>
                        <th><label for="amm_tw_card"><?php Utils::ESC_HTML_E('Card Type'); ?></label></th>
                        <td>
                            <select id="amm_tw_card" name="amm[tw_card]">
                                <?php
                                $tw_cards = [
                                    ''                   => Utils::LANG('— inherit from settings —'),
                                    'summary'            => 'summary',
                                    'summary_large_image' => 'summary_large_image',
                                    'app'                => 'app',
                                    'player'             => 'player',
                                ];
                                foreach ( $tw_cards as $val => $label ) {
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr( $val ),
                                        selected( $f['tw_card'], $val, false ),
                                        esc_html( $label )
                                    );
                                }
                                ?>
                            </select>
                            <p class="description">
                                <?php Utils::ESC_HTML_E('Default can be set in Settings → Twitter Site Handle.'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="amm_tw_title"><?php Utils::ESC_HTML_E('Twitter Title'); ?></label></th>
                        <td>
                            <input type="text" id="amm_tw_title" name="amm[tw_title]"
                                   value="<?php echo esc_attr( $f['tw_title'] ); ?>"
                                   placeholder="<?php Utils::ESC_HTML_E('Leave empty to inherit OG title or post title'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="amm_tw_description"><?php Utils::ESC_HTML_E('Twitter Description'); ?></label></th>
                        <td>
                            <textarea id="amm_tw_description" name="amm[tw_description]"
                                      rows="3" maxlength="200"><?php echo esc_textarea( $f['tw_description'] ); ?></textarea>
                            <p class="description amm-counter" data-field="amm_tw_description">0 / 200</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php Utils::ESC_HTML_E('Twitter Image'); ?></label></th>
                        <td>
                            <div class="amm-og-image-wrap">
                                <?php if ( $f['tw_image'] ) : ?>
                                    <img src="<?php echo esc_url( $f['tw_image'] ); ?>"
                                         class="amm-og-preview" alt="">
                                <?php endif; ?>
                                <input type="hidden" id="amm_tw_image" name="amm[tw_image]"
                                       value="<?php echo esc_attr( $f['tw_image'] ); ?>">
                                <button type="button" class="button amm-media-btn" data-target="amm_tw_image">
                                    <?php Utils::ESC_HTML_E('Select image'); ?>
                                </button>
                                <?php if ( $f['tw_image'] ) : ?>
                                    <button type="button" class="button amm-media-remove">
                                        <?php Utils::ESC_HTML_E('Remove'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <p class="description">
                                <?php Utils::ESC_HTML_E('Min 120×120px. summary_large_image: min 280×150px. Falls back to OG image.'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="amm_tw_creator"><?php Utils::ESC_HTML_E('Creator Handle'); ?></label></th>
                        <td>
                            <input type="text" id="amm_tw_creator" name="amm[tw_creator]"
                                   value="<?php echo esc_attr( $f['tw_creator'] ); ?>"
                                   placeholder="@author_handle">
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: %s: twitter:site handle from settings */
                                    Utils::ESC_HTML( 'twitter:creator for this post. Site handle (twitter:site): %s — set in Settings.'),
                                    '<code>' . esc_html( $tw_site_global ?: Utils::LANG('not set') ) . '</code>'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                </table>
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
                <button type="button" class="button-link amm-remove-row" title="<?php Utils::ESC_ATTR_E( 'Remove' ); ?>">✕</button>
            </td>
        </tr>
        <?php
    }

    
    /**
     * Save meta via TagManager.
     * Builds tag objects from form data → serializes to JSON → stores in post meta.
     * 
     * @param int $post_id
     * @param WP_Post $post
     * @return void
     */
    public function save_meta( int $post_id, \WP_Post $post ): void {
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
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( Utils::POST('amm_meta_nonce') ) ), 'amm_save_meta_' . $post_id )
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

        $raw = wp_unslash( Utils::POST_ARRAY( 'amm' ) );
        $tm  = new TagManager();
        $col = $tm->getCollection();

        // Map form fields → tag objects
        foreach ( self::FIELD_MAP as $form_field => $tc_key ) {
            $sanitizer = self::SANITIZERS[ $form_field ] ?? 'sanitize_text_field';
            $value     = $sanitizer( $raw[ $form_field ] ?? '' );

            if ( '' === $value ) {
                continue;
            }

            $col->set( $tc_key, self::create_meta_tag( $tc_key, $value ) );
        }

        // Canonical → LinkTag
        $canonical = esc_url_raw( $raw['canonical'] ?? '' );
        if ( $canonical ) {
            $link = ( new LinkTag() )->setRel( 'canonical' )->setHref( $canonical );
            $col->append( 'link', $link );
        }

        // Custom meta → individual tag objects
        $this->save_custom_meta( $col, $raw['custom_meta'] ?? [] );

        // Persist
        if ( $col->count() === 0 ) {
            delete_post_meta( $post_id, AMIRISET_META_MANAGER_DB_KEY );
        } else {
            update_post_meta( $post_id, AMIRISET_META_MANAGER_DB_KEY, $tm->toJson() );
        }
    }

    /**
     * Create the correct MetaTag subclass based on TagCollection key prefix.
     *
     * @param string $tc_key TagCollection key (e.g. 'meta::property::og:title').
     * @param string $value  Content value.
     * @return MetaTag
     */
    private static function create_meta_tag( string $tc_key, string $value ): MetaTag {
        $parts = explode( '::', $tc_key, 3 );
        $type  = $parts[1] ?? 'name';
        $attr  = $parts[2] ?? '';

        return match ( $type ) {
            'property'   => ( new PropertyMetaTag() )->setProperty( $attr )->setContent( $value ),
            'http-equiv' => ( new HttpEquivMetaTag() )->setHttpEquiv( $attr )->setContent( $value ),
            default      => ( new NameMetaTag() )->setName( $attr )->setContent( $value ),
        };
    }

    /**
     * Process custom meta rows from form and add as tag objects to collection.
     *
     * @param TagCollection $col  Target collection.
     * @param mixed         $raw  Raw custom_meta from $_POST.
     */
    private function save_custom_meta( TagCollection $col, $raw ): void {
        if ( ! is_array( $raw ) ) {
            return;
        }

        $allowed_attr_types = [ 'name', 'property', 'http-equiv' ];
        $built_in_keys      = array_flip( self::FIELD_MAP );

        foreach ( $raw as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $type    = sanitize_key( $row['attr_type'] ?? 'name' );
            $attr    = sanitize_text_field( $row['attr_value'] ?? '' );
            $content = sanitize_text_field( $row['content']    ?? '' );

            if ( ! in_array( $type, $allowed_attr_types, true ) ) {
                $type = 'name';
            }

            if ( '' === $attr || '' === $content ) {
                continue;
            }

            $tc_key = 'meta::' . $type . '::' . $attr;

            if ( isset( $built_in_keys[ $tc_key ] ) ) {
                continue;
            }

            $col->set( $tc_key, self::create_meta_tag( $tc_key, $content ) );
        }
    }
}
