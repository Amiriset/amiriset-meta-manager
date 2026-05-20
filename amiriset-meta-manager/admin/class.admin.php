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
 * Class <b>Admin</b> --  Admin pages:
 *   – Settings  (General defaults + Analytics scripts)
 *   – Pages     (list of WP pages + their meta data)
 *   – Posts     (list of posts + their meta data)
 *   – CPT       (pick a CPT → list its posts + meta data)
 *
 * @version 1.0.0-a.5
 * @package Amiriset\MetaManager
 * @license GPL-3.0-or-later
 * @author Y.Frolov
 * @created 2026-05-14 14:41:18
 */
class Admin {
    
    /** @var string[] Hook suffixes returned by add_menu_page / add_submenu_page. */
    private array $page_hooks = [];
    
    /** @var string[] Settings tabs. */
    private const TABS = [
        'seo'       => 'SEO',
        'technical' => 'Technical',
        'og'        => 'Open Graph',
        'twitter'   => 'Twitter / X',
        'jsonld'    => 'JSON-LD',
        'scripts'   => 'Scripts',
    ];

    /**
     * Init hooks.
     * 
     * @return void
     */
    public function init_hooks(): void {
        add_action( 'admin_menu',            [ $this, 'register_pages'  ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets'  ] );
        add_filter( 'plugin_action_links_' . AMIRISET_META_MANAGER_BASENAME, [ $this, 'plugin_action_links' ] );
        add_action( 'admin_post_amm_save_settings', [ $this, 'handle_save_settings' ] );
    }

    
    /**
     * Register pages
     * 
     * @return void
     */
    public function register_pages(): void {
        $this->page_hooks[] = add_menu_page(
            Utils::LANG('Amiriset Meta Manager'),
            Utils::LANG('Amiriset Meta'),
            'manage_options',
            'amm-settings',
            [ $this, 'page_settings' ],
            'dashicons-search',
            80
        );

        $sub_pages = [
            [ 'amm-settings', Utils::LANG('Settings'),      [ $this, 'page_settings' ]  ],
            [ 'amm-pages',    Utils::LANG('Pages'),      [ $this, 'page_post_list' ] ],
            [ 'amm-posts',    Utils::LANG('Posts'),      [ $this, 'page_post_list' ] ],
            [ 'amm-cpt',      Utils::LANG('Custom Post Types'), [ $this, 'page_post_list' ] ],
        ];

        foreach ( $sub_pages as [ $slug, $label, $cb ] ) {
            $this->page_hooks[] = add_submenu_page(
                'amm-settings',
                $label,
                $label,
                'manage_options',
                $slug,
                $cb
            );
        }
    }
    
    /**
     * Unified settings save handler.
     * All tabs submit to this single endpoint.
     *
     * @return void
     */
    public function handle_save_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( Utils::ESC_HTML('Access denied.') );
        }

        check_admin_referer( 'amm_save_settings' );

        $tab  = sanitize_key( Utils::POST( 'amm_tab', 'seo' ) );
        $opts = Utils::GET_OPTIONS();

        switch ( $tab ) {
            case 'technical':
                $opts->set( 'charset',          sanitize_text_field( Utils::POST('charset') ) );
                $opts->set( 'content_language',  sanitize_text_field( Utils::POST('content_language') ) );
                $opts->set( 'viewport',          sanitize_text_field( Utils::POST('viewport') ) );
                $opts->set( 'theme_color',       sanitize_hex_color( Utils::POST('theme_color') ) ?: '' );
                $opts->set( 'manifest',          esc_url_raw( Utils::POST('manifest') ) );
                break;

            case 'seo':
                $opts->set( 'distribution',    sanitize_text_field( Utils::POST('distribution') ) );
                $opts->set( 'classification',  sanitize_text_field( Utils::POST('classification') ) );
                $opts->set( 'copyright',       sanitize_text_field( Utils::POST('copyright') ) );
                $opts->set( 'developer',       sanitize_text_field( Utils::POST('developer') ) );
                $opts->set( 'default_robots',  sanitize_text_field( Utils::POST('default_robots') ) );
                $opts->set( 'title_suffix',    sanitize_text_field( Utils::POST('title_suffix') ) );
                $opts->set( 'enabled_post_types', array_map(
                    'sanitize_key',
                    Utils::POST_ARRAY( 'enabled_post_types', [ 'post', 'page' ] )
                ) );
                $opts->set( 'kw_min_symbols',  absint( Utils::POST('kw_min_symbols') ) ?: 4 );
                $opts->set( 'kw_max_words',    absint( Utils::POST('kw_max_words') ) ?: 10 );
                $opts->set( 'kw_lang',         sanitize_key( Utils::POST('kw_lang') ) );
                break;

            case 'og':
                $opts->set( 'og_site_name',      sanitize_text_field( Utils::POST('og_site_name') ) );
                $opts->set( 'og_default_image',  esc_url_raw( Utils::POST('og_default_image') ) );
                $opts->set( 'og_default_locale', sanitize_text_field( Utils::POST('og_default_locale') ) );
                $opts->set( 'og_default_type',   sanitize_text_field( Utils::POST('og_default_type') ) );
                break;

            case 'twitter':
                $opts->set( 'twitter_site', sanitize_text_field( Utils::POST('twitter_site') ) );
                $opts->set( 'twitter_card', sanitize_text_field( Utils::POST('twitter_card') ) );
                break;

            case 'jsonld':
                $opts->set( 'jsonld_entity_type',  sanitize_text_field( Utils::POST('jsonld_entity_type') ) );
                $opts->set( 'jsonld_entity_name',  sanitize_text_field( Utils::POST('jsonld_entity_name') ) );
                $opts->set( 'jsonld_entity_url',   esc_url_raw( Utils::POST('jsonld_entity_url') ) );
                $opts->set( 'jsonld_entity_logo',  esc_url_raw( Utils::POST('jsonld_entity_logo') ) );
                $opts->set( 'jsonld_website_name', sanitize_text_field( Utils::POST('jsonld_website_name') ) );
                $opts->set( 'jsonld_website_alt',  sanitize_text_field( Utils::POST('jsonld_website_alt') ) );
                break;

            case 'scripts':
                if ( ! current_user_can( 'unfiltered_html' ) ) {
                    wp_safe_redirect( Utils::ADD_QUERY_ARG_WITH_FRAGMENT(
                        admin_url( 'admin.php' ),
                        [ 'page' => 'amm-settings', 'tab' => 'scripts', 'amm_denied' => '1' ],
                        ''
                    ) );
                    exit;
                }
                $opts->set( 'analytics_head', wp_unslash( Utils::POST('analytics_head') ) );
                $opts->set( 'analytics_body', wp_unslash( Utils::POST('analytics_body') ) );
                break;
        }

        $opts->save();

        wp_safe_redirect( Utils::ADD_QUERY_ARG_WITH_FRAGMENT(
            admin_url( 'admin.php' ),
            [ 'page' => 'amm-settings', 'tab' => $tab, 'amm_saved' => '1' ],
            ''
        ) );
        exit;
    }


    /**
     * Enqueue assets
     * 
     * @param string $hook
     * @return void
     */
    public function enqueue_assets( string $hook ): void {
        if ( ! in_array( $hook, $this->page_hooks, true ) ) {
            return;
        }

        wp_enqueue_style(
            'amm-admin-css',
            AMIRISET_META_MANAGER_URL . 'admin/css/amm-admin.css',
            [],
            AMIRISET_META_MANAGER_VERSION
        );

        wp_enqueue_media();

        wp_enqueue_script(
            'amm-admin-js',
            AMIRISET_META_MANAGER_URL . 'admin/js/amm-admin.js',
            [ 'jquery' ],
            AMIRISET_META_MANAGER_VERSION,
            true
        );

        wp_localize_script( 'amm-admin-js', 'ammData', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'amm_ajax_nonce' ),
            'i18n'    => [
                'selectImage' => Utils::LANG('Select image'),
                'useImage'    => Utils::LANG( 'Use this image'),
                'removeImage' => Utils::LANG( 'Remove' ),
            ]
        ] );
    }

    /**
     * Plugin action links.
     * 
     * @param array $links
     * @return array
     */
    public function plugin_action_links( array $links ): array {
        array_unshift(
            $links,
            sprintf(
                '<a href="%s">%s</a>',
                admin_url( 'admin.php?page=amm-settings' ),
                Utils::LANG( 'Settings' )
            )
        );
        return $links;
    }

    
    /**
     * Settings page — tabbed layout.
     * 
     * @return void
     */
    public function page_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( Utils::ESC_HTML('Access denied.') );
        }

        $opts      = Utils::GET_OPTIONS();
        $active    = sanitize_key( Utils::GET( 'tab', 'seo' ) );
        if ( ! isset( self::TABS[ $active ] ) ) {
            $active = 'seo';
        }
        ?>
        <div class="wrap amm-admin-wrap">
            <h1><?php Utils::ESC_HTML_E('Amiriset Meta Manager — Settings'); ?></h1>

            <?php if ( isset( $_GET['amm_saved'] ) ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php Utils::ESC_HTML_E('Settings saved.'); ?></p>
                </div>
            <?php endif; ?>

            <nav class="nav-tab-wrapper amm-settings-tabs">
                <?php foreach ( self::TABS as $slug => $label ) :
                    $url   = admin_url( 'admin.php?page=amm-settings&tab=' . $slug );
                    $class = ( $slug === $active ) ? 'nav-tab nav-tab-active' : 'nav-tab';
                    ?>
                    <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>">
                        <?php Utils::ESC_HTML_E( $label ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="amm_save_settings">
                <input type="hidden" name="amm_tab" value="<?php echo esc_attr( $active ); ?>">
                <?php wp_nonce_field( 'amm_save_settings' ); ?>

                <?php
                switch ( $active ) {
                    case 'technical': $this->render_tab_technical( $opts ); break;
                    case 'seo':       $this->render_tab_seo( $opts );       break;
                    case 'og':        $this->render_tab_og( $opts );        break;
                    case 'twitter':   $this->render_tab_twitter( $opts );   break;
                    case 'jsonld':    $this->render_tab_jsonld( $opts );     break;
                    case 'scripts':   $this->render_tab_scripts( $opts );   break;
                }
                ?>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }


    // ── Tab renderers ────────────────────────────────────────────────────────

    /**
     * Technical tab — override-only fields.
     */
    private function render_tab_technical( Options $opts ): void {
        $fields = [
            'charset'          => [ 'Charset',          'e.g. UTF-8 (usually handled by WordPress)' ],
            'content_language' => [ 'Content-Language',  'e.g. en-GB' ],
            'viewport'         => [ 'Viewport',          'e.g. width=device-width, initial-scale=1 (usually handled by theme)' ],
            'theme_color'      => [ 'Theme Color',       'Hex color for mobile browser chrome, e.g. #ffffff' ],
            'manifest'         => [ 'Manifest (PWA)',    'URL to manifest.json' ],
        ];
        ?>
        <table class="form-table amm-settings-table">
            <?php foreach ( $fields as $key => $info ) :
                $value    = $opts->get( $key );
                $has_val  = ( $value !== '' );
                $field_id = 'amm_' . $key;
                ?>
                <tr>
                    <th><label for="<?php echo esc_attr( $field_id ); ?>"><?php Utils::ESC_HTML_E( $info[0] ); ?></label></th>
                    <td>
                        <label class="amm-override-label">
                            <input type="checkbox"
                                   class="amm-override-cb"
                                   data-target="<?php echo esc_attr( $field_id ); ?>"
                                   <?php checked( $has_val ); ?>>
                            <?php Utils::ESC_HTML_E('Override'); ?>
                        </label>
                        <input type="text"
                               id="<?php echo esc_attr( $field_id ); ?>"
                               name="<?php echo esc_attr( $key ); ?>"
                               value="<?php echo esc_attr( $value ); ?>"
                               class="regular-text"
                               <?php disabled( ! $has_val ); ?>>
                        <p class="description"><?php Utils::ESC_HTML_E( $info[1] ); ?></p>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php
    }

    /**
     * SEO tab — robots, distribution, classification, copyright, post types, keywords.
     */
    private function render_tab_seo( Options $opts ): void {
        $all_cpts = $this->get_all_public_cpts();
        $enabled  = $opts->getArray( 'enabled_post_types', [ 'post', 'page' ] );
        ?>
        <h2 class="amm-section-title"><?php Utils::ESC_HTML_E('SEO Defaults'); ?></h2>
        <table class="form-table amm-settings-table">
            <tr>
                <th><?php Utils::ESC_HTML_E('Default Robots'); ?></th>
                <td>
                    <input type="text" name="default_robots"
                           value="<?php echo esc_attr( $opts->get('default_robots') ); ?>"
                           class="regular-text">
                    <p class="description"><?php Utils::ESC_HTML_E('e.g. index, follow'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php Utils::ESC_HTML_E('Title Suffix'); ?></th>
                <td>
                    <input type="text" name="title_suffix"
                           value="<?php echo esc_attr( $opts->get('title_suffix') ); ?>"
                           class="regular-text">
                    <p class="description"><?php Utils::ESC_HTML_E('Appended to SEO title (e.g. " | My Site").'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php Utils::ESC_HTML_E('Distribution'); ?></th>
                <td>
                    <input type="text" name="distribution"
                           value="<?php echo esc_attr( $opts->get('distribution') ); ?>"
                           class="regular-text"
                           placeholder="global">
                </td>
            </tr>
            <tr>
                <th><?php Utils::ESC_HTML_E('Classification'); ?></th>
                <td>
                    <input type="text" name="classification"
                           value="<?php echo esc_attr( $opts->get('classification') ); ?>"
                           class="regular-text"
                           placeholder="e.g. IT, Education, Business">
                </td>
            </tr>
            <tr>
                <th><?php Utils::ESC_HTML_E('Copyright'); ?></th>
                <td>
                    <input type="text" name="copyright"
                           value="<?php echo esc_attr( $opts->get('copyright') ); ?>"
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th><?php Utils::ESC_HTML_E('Developer'); ?></th>
                <td>
                    <input type="text" name="developer"
                           value="<?php echo esc_attr( $opts->get('developer') ); ?>"
                           class="regular-text">
                </td>
            </tr>
        </table>

        <h2 class="amm-section-title"><?php Utils::ESC_HTML_E('Enabled Post Types'); ?></h2>
        <p class="description"><?php Utils::ESC_HTML_E('The SEO meta box will appear on these post types.'); ?></p>
        <table class="form-table amm-settings-table">
            <tr>
                <th><?php Utils::ESC_HTML_E('Post Types'); ?></th>
                <td>
                    <?php
                    $builtins = [ 'post' => 'Posts', 'page' => 'Pages' ];
                    foreach ( array_merge( $builtins, $all_cpts ) as $slug => $label ) : ?>
                        <label class="amm-checkbox-label">
                            <input type="checkbox" name="enabled_post_types[]"
                                   value="<?php echo esc_attr( $slug ); ?>"
                                   <?php checked( in_array( $slug, $enabled, true ) ); ?>>
                            <?php echo esc_html( $label . ' (' . $slug . ')' ); ?>
                        </label><br>
                    <?php endforeach; ?>
                </td>
            </tr>
        </table>

        <h2 class="amm-section-title"><?php Utils::ESC_HTML_E('Keyword Extractor Settings'); ?></h2>
        <table class="form-table amm-settings-table">
            <tr>
                <th><?php Utils::ESC_HTML_E('Min. word length'); ?></th>
                <td>
                    <input type="number" min="2" max="10" name="kw_min_symbols"
                           value="<?php echo esc_attr( $opts->get('kw_min_symbols') ); ?>">
                </td>
            </tr>
            <tr>
                <th><?php Utils::ESC_HTML_E('Max. keywords'); ?></th>
                <td>
                    <input type="number" min="5" max="100" name="kw_max_words"
                           value="<?php echo esc_attr( $opts->get('kw_max_words') ); ?>">
                </td>
            </tr>
            <tr>
                <th><?php Utils::ESC_HTML_E('Default language'); ?></th>
                <td>
                    <select name="kw_lang">
                        <option value="auto" <?php selected( $opts->get('kw_lang'), 'auto' ); ?>><?php Utils::ESC_HTML_E('Auto-detect'); ?></option>
                        <option value="en"   <?php selected( $opts->get('kw_lang'), 'en' ); ?>>English</option>
                        <option value="ru"   <?php selected( $opts->get('kw_lang'), 'ru' ); ?>>Русский</option>
                        <option value="uk"   <?php selected( $opts->get('kw_lang'), 'uk' ); ?>>Українська</option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Open Graph tab.
     */
    private function render_tab_og( Options $opts ): void {
        ?>
        <table class="form-table amm-settings-table">
            <tr>
                <th><?php Utils::ESC_HTML_E('Site Name'); ?></th>
                <td>
                    <input type="text" name="og_site_name"
                           value="<?php echo esc_attr( $opts->get('og_site_name') ); ?>"
                           class="regular-text"
                           placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
                </td>
            </tr>
            <tr>
                <th><?php Utils::ESC_HTML_E('Default OG Type'); ?></th>
                <td>
                    <select name="og_default_type">
                        <?php foreach ( [ 'website', 'article', 'product' ] as $t ) : ?>
                            <option value="<?php echo esc_attr( $t ); ?>"
                                <?php selected( $opts->get('og_default_type'), $t ); ?>>
                                <?php echo esc_html( $t ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php Utils::ESC_HTML_E('Default Locale'); ?></th>
                <td>
                    <input type="text" name="og_default_locale"
                           value="<?php echo esc_attr( $opts->get('og_default_locale') ); ?>"
                           class="regular-text"
                           placeholder="e.g. en_GB">
                </td>
            </tr>
            <tr>
                <th><?php Utils::ESC_HTML_E('Default OG Image'); ?></th>
                <td>
                    <div class="amm-og-image-wrap">
                        <?php if ( $opts->get('og_default_image') ) : ?>
                            <img src="<?php echo esc_url( $opts->get('og_default_image') ); ?>" class="amm-og-preview" alt="">
                        <?php endif; ?>
                        <input type="hidden" id="amm_og_default_image" name="og_default_image"
                               value="<?php echo esc_attr( $opts->get('og_default_image') ); ?>">
                        <button type="button" class="button amm-media-btn" data-target="amm_og_default_image">
                            <?php Utils::ESC_HTML_E('Select image'); ?>
                        </button>
                        <?php if ( $opts->get('og_default_image') ) : ?>
                            <button type="button" class="button amm-media-remove">
                                <?php Utils::ESC_HTML_E('Remove'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Twitter / X tab.
     */
    private function render_tab_twitter( Options $opts ): void {
        ?>
        <table class="form-table amm-settings-table">
            <tr>
                <th><?php Utils::ESC_HTML_E('Site Handle'); ?></th>
                <td>
                    <input type="text" name="twitter_site"
                           value="<?php echo esc_attr( $opts->get('twitter_site') ); ?>"
                           class="regular-text"
                           placeholder="@yoursite">
                    <p class="description">
                        <?php Utils::ESC_HTML_E('twitter:site — the @username of the website.'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><?php Utils::ESC_HTML_E('Default Card Type'); ?></th>
                <td>
                    <select name="twitter_card">
                        <?php
                        $card_types = [
                            'summary'             => 'summary',
                            'summary_large_image' => 'summary_large_image',
                            'app'                 => 'app',
                            'player'              => 'player',
                        ];
                        foreach ( $card_types as $val => $label ) {
                            printf(
                                '<option value="%s" %s>%s</option>',
                                esc_attr( $val ),
                                selected( $opts->get('twitter_card'), $val, false ),
                                esc_html( $label )
                            );
                        }
                        ?>
                    </select>
                    <p class="description">
                        <?php Utils::ESC_HTML_E('Per-post override is available in the meta box Twitter tab.'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * JSON-LD tab.
     */
    private function render_tab_jsonld( Options $opts ): void {
        ?>
        <h2 class="amm-section-title"><?php Utils::ESC_HTML_E('Publisher Entity'); ?></h2>
        <table class="form-table amm-settings-table">
            <tr>
                <th><?php Utils::ESC_HTML_E('Entity Type'); ?></th>
                <td>
                    <select name="jsonld_entity_type">
                        <option value="Organization" <?php selected( $opts->get('jsonld_entity_type'), 'Organization' ); ?>>Organization</option>
                        <option value="Person"       <?php selected( $opts->get('jsonld_entity_type'), 'Person' ); ?>>Person</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php Utils::ESC_HTML_E('Name'); ?></th>
                <td>
                    <input type="text" name="jsonld_entity_name"
                           value="<?php echo esc_attr( $opts->get('jsonld_entity_name') ); ?>"
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th><?php Utils::ESC_HTML_E('URL'); ?></th>
                <td>
                    <input type="url" name="jsonld_entity_url"
                           value="<?php echo esc_attr( $opts->get('jsonld_entity_url') ); ?>"
                           class="regular-text"
                           placeholder="<?php echo esc_attr( home_url() ); ?>">
                </td>
            </tr>
            <tr>
                <th><?php Utils::ESC_HTML_E('Logo'); ?></th>
                <td>
                    <div class="amm-og-image-wrap">
                        <?php if ( $opts->get('jsonld_entity_logo') ) : ?>
                            <img src="<?php echo esc_url( $opts->get('jsonld_entity_logo') ); ?>" class="amm-og-preview" alt="">
                        <?php endif; ?>
                        <input type="hidden" id="amm_jsonld_entity_logo" name="jsonld_entity_logo"
                               value="<?php echo esc_attr( $opts->get('jsonld_entity_logo') ); ?>">
                        <button type="button" class="button amm-media-btn" data-target="amm_jsonld_entity_logo">
                            <?php Utils::ESC_HTML_E('Select image'); ?>
                        </button>
                        <?php if ( $opts->get('jsonld_entity_logo') ) : ?>
                            <button type="button" class="button amm-media-remove">
                                <?php Utils::ESC_HTML_E('Remove'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        </table>

        <h2 class="amm-section-title"><?php Utils::ESC_HTML_E('WebSite Schema'); ?></h2>
        <table class="form-table amm-settings-table">
            <tr>
                <th><?php Utils::ESC_HTML_E('Website Name'); ?></th>
                <td>
                    <input type="text" name="jsonld_website_name"
                           value="<?php echo esc_attr( $opts->get('jsonld_website_name') ); ?>"
                           class="regular-text"
                           placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
                </td>
            </tr>
            <tr>
                <th><?php Utils::ESC_HTML_E('Alternate Name'); ?></th>
                <td>
                    <input type="text" name="jsonld_website_alt"
                           value="<?php echo esc_attr( $opts->get('jsonld_website_alt') ); ?>"
                           class="regular-text">
                    <p class="description"><?php Utils::ESC_HTML_E('schema.org alternateName — shown in search results.'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Scripts / Analytics tab.
     */
    private function render_tab_scripts( Options $opts ): void {
        $can_edit = current_user_can( 'unfiltered_html' );
        ?>

        <?php if ( isset( $_GET['amm_denied'] ) ) : ?>
            <div class="notice notice-error is-dismissible">
                <p><?php Utils::ESC_HTML_E('You do not have permission to save unfiltered scripts.'); ?></p>
            </div>
        <?php endif; ?>

        <?php if ( ! $can_edit ) : ?>
            <div class="notice notice-warning">
                <p><?php Utils::ESC_HTML_E('Your role does not have the unfiltered_html capability. Script fields are read-only.'); ?></p>
            </div>
        <?php endif; ?>

        <p class="description" style="margin-bottom:12px">
            <?php Utils::ESC_HTML_E('Paste full HTML blocks (including &lt;script&gt; tags) or raw JavaScript. Tags are preserved as-is.'); ?>
        </p>
        <table class="form-table amm-settings-table">
            <tr>
                <th><label for="amm_analytics_head"><?php Utils::ESC_HTML_E('&lt;head&gt; Scripts'); ?></label></th>
                <td>
                    <textarea id="amm_analytics_head" name="analytics_head" rows="8"
                              placeholder="&lt;!-- Google Tag Manager, Meta Pixel, etc --&gt;"
                              <?php disabled( ! $can_edit ); ?>
                    ><?php echo esc_textarea( $opts->get('analytics_head') ); ?></textarea>
                    <p class="description">
                        <?php Utils::ESC_HTML_E('Injected inside &lt;head&gt; (before &lt;/head&gt;). Wrap JS in &lt;script&gt; tags.'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="amm_analytics_body"><?php Utils::ESC_HTML_E('&lt;body&gt; Scripts'); ?></label></th>
                <td>
                    <textarea id="amm_analytics_body" name="analytics_body" rows="8"
                              placeholder="&lt;!-- GTM noscript, etc --&gt;"
                              <?php disabled( ! $can_edit ); ?>
                    ><?php echo esc_textarea( $opts->get('analytics_body') ); ?></textarea>
                    <p class="description">
                        <?php Utils::ESC_HTML_E('Injected right after &lt;body&gt; open tag (requires theme to call wp_body_open()).'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    
    /**
     * Post list page (Pages / Posts / CPT) 
     * 
     * @return void
     */
    public function page_post_list(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( Utils::ESC_HTML('Access denied.') );
        }

        $current_page = sanitize_key( Utils::GET('page') );

        // Determine which post type(s) to list
        $post_type = match ( $current_page ) {
            'amm-pages' => 'page',
            'amm-posts' => 'post',
            'amm-cpt'   => sanitize_key( Utils::GET('cpt') ),
            default     => '',
        };

        $all_cpts = $this->get_all_public_cpts();

        ?>
        <div class="wrap amm-admin-wrap">
            <h1>
                <?php
                echo esc_html( match ( $current_page ) {
                    'amm-pages' => Utils::LANG('Amiriset Meta Manager — Pages'),
                    'amm-posts' => Utils::LANG('Amiriset Meta Manager — Posts'),
                    'amm-cpt'   => Utils::LANG('Amiriset Meta Manager — Custom Post Types'),
                    default     => 'Amiriset Meta',
                } );
                ?>
            </h1>

            <?php if ( 'amm-cpt' === $current_page ) : ?>
                <!-- CPT picker -->
                <form method="get" class="amm-cpt-picker">
                    <input type="hidden" name="page" value="amm-cpt">
                    <select name="cpt" onchange="this.form.submit()">
                        <option value=""><?php Utils::ESC_HTML_E('— Select Post Type —'); ?></option>
                        <?php foreach ( $all_cpts as $slug => $label ) : ?>
                            <option value="<?php echo esc_attr( $slug ); ?>"
                                <?php selected( $post_type, $slug ); ?>>
                                <?php echo esc_html( $label . ' (' . $slug . ')' ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>

            <?php if ( $post_type ) : ?>
                <?php $this->render_post_table( $post_type ); ?>
            <?php elseif ( 'amm-cpt' === $current_page ) : ?>
                <p><?php Utils::ESC_HTML_E('Please select a post type above.'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render table.
     * 
     * @param string $post_type
     * @return void
     */
    private function render_post_table( string $post_type ): void {
        $paged = Utils::GET_INT('paged', 1);
        $per   = 20;

        $query = new \WP_Query( [
            'post_type'      => $post_type,
            'post_status'    => [ 'publish', 'draft', 'private' ],
            'posts_per_page' => $per,
            'paged'          => $paged,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ] );

        $total_pages = $query->max_num_pages;

        echo '<table class="wp-list-table widefat fixed striped amm-list-table">';
        echo '<thead><tr>';
        echo '<th>' . Utils::ESC_HTML('Title')       . '</th>';
        echo '<th>' . Utils::ESC_HTML('Status')      . '</th>';
        echo '<th>' . Utils::ESC_HTML('SEO Title')   . '</th>';
        echo '<th>' . Utils::ESC_HTML('Description') . '</th>';
        echo '<th>' . Utils::ESC_HTML('Keywords')    . '</th>';
        echo '<th>' . Utils::ESC_HTML('OG Image')    . '</th>';
        echo '<th>' . Utils::ESC_HTML('Custom Tags') . '</th>';
        echo '<th>' . Utils::ESC_HTML('Edit')        . '</th>';
        echo '</tr></thead><tbody>';

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $pid  = get_the_ID();
                $tm   = MetaBox::load( $pid );

                $m_desc  = $tm->getContent( 'meta::name::description' );
                $m_kw    = $tm->getContent( 'meta::name::keywords' );
                $m_og_t  = $tm->getContent( 'meta::property::og:title' );
                $m_img   = MetaBox::resolve_image_value( $tm->getContent( 'meta::property::og:image' ) );
                $custom  = MetaBox::extract_custom_meta( $tm );

                $has_meta   = $m_desc || $m_kw || $m_og_t;
                $row_class  = $has_meta ? 'amm-row-has-meta' : 'amm-row-no-meta';
                $status     = get_post_status();

                echo '<tr class="' . esc_attr( $row_class ) . '">';
                printf( '<td><strong>%s</strong></td>', esc_html( get_the_title() ) );
                printf( '<td><span class="amm-status amm-status-%s">%s</span></td>',
                    esc_attr( $status ),
                    esc_html( ucfirst( $status ) )
                );

                printf( '<td>%s</td>', esc_html( $m_og_t ?: '—' ) );
                printf( '<td class="amm-desc-cell">%s</td>',
                    esc_html( $m_desc ? mb_substr( $m_desc, 0, 80 ) . '…' : '—' )
                );
                printf( '<td class="amm-kw-cell">%s</td>',
                    esc_html( $m_kw ? mb_substr( $m_kw, 0, 60 ) . '…' : '—' )
                );
                printf( '<td>%s</td>',
                    $m_img
                        ? '<img src="' . esc_url( $m_img ) . '" class="amm-thumb" alt="">'
                        : '—'
                );
                printf( '<td>%s</td>',
                    ! empty( $custom )
                        ? '<span class="amm-badge">' . count( $custom ) . '</span>'
                        : '—'
                );
                printf(
                    '<td><a href="%s" class="button button-small">✏️</a></td>',
                    esc_url( get_edit_post_link( $pid ) )
                );
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="8">' . Utils::ESC_HTML( 'No posts found.' ) . '</td></tr>';
        }

        wp_reset_postdata();
        echo '</tbody></table>';

        // Pagination
        if ( $total_pages > 1 ) {
            $current_url = add_query_arg( [] );
            echo '<div class="amm-pagination tablenav-pages">';
            echo paginate_links( [
                'base'    => add_query_arg( 'paged', '%#%', $current_url ),
                'format'  => '',
                'current' => $paged,
                'total'   => $total_pages,
            ] );
            echo '</div>';
        }
    }

    /**
     * Get CPT items.
     * 
     * @return array Returns [ 'cpt_slug' => 'Label' ] for all registered public CPTs (excluding built-ins).
     */
    private function get_all_public_cpts(): array {
        $result = [];
        $cpts   = get_post_types( [ 'public' => true, '_builtin' => false ], 'objects' );
        foreach ( $cpts as $cpt ) {
            $result[ $cpt->name ] = $cpt->labels->singular_name ?? $cpt->name;
        }
        return $result;
    }
}
