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
 * @version 1.0.0-a.4
 * @package Amiriset\MetaManager
 * @license GPL-3.0-or-later
 * @author Y.Frolov
 * @created 2026-05-14 14:41:18
 */
class Admin {
    
    /**
     * Init hooks.
     * 
     * @return void
     */
    public function init_hooks(): void {
        add_action( 'admin_menu',            [ $this, 'register_pages'  ] );
        add_action( 'admin_init',            [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets'  ] );
        add_filter( 'plugin_action_links_' . AMIRISET_META_MANAGER_BASENAME, [ $this, 'plugin_action_links' ] );
        // Analytics fields bypass Settings API to preserve <script> tags
        add_action( 'admin_post_amm_save_analytics', [ $this, 'handle_save_analytics' ] );
    }

    
    /**
     * Register pages
     * 
     * @return void
     */
    public function register_pages(): void {
        add_menu_page(
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
            add_submenu_page(
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
     * Register settings.
     * 
     * @return void
     */
    public function register_settings(): void {
        register_setting(
            '_amm_options_group',
            AMIRISET_META_MANAGER_OPTION_KEY,
            [
                'sanitize_callback' => [ $this, 'sanitize_options' ],
                'default'           => Activator::defaults(),
            ]
        );
    }

    /**
     * Sinitize options.
     * Empty option replace with defaults.
     * 
     * @param type $raw
     * @return array
     */
    public function sanitize_options( $raw ): array {
        $defaults = Activator::defaults();

        return [
            'default_robots'       => sanitize_text_field( $raw['default_robots']      ?? $defaults['default_robots'] ),
            'default_og_type'      => sanitize_text_field( $raw['default_og_type']     ?? $defaults['default_og_type'] ),
            'default_og_image'     => esc_url_raw(          $raw['default_og_image']    ?? '' ),
            'default_title_suffix' => sanitize_text_field( $raw['default_title_suffix'] ?? '' ),
            // Admin-only fields (manage_options cap) — strip slashes only, allow <script> tags
            'analytics_head'       => wp_unslash( $raw['analytics_head'] ?? '' ),
            'analytics_body'       => wp_unslash( $raw['analytics_body'] ?? '' ),
            'enabled_post_types'   => array_map( 'sanitize_key', (array) ( $raw['enabled_post_types'] ?? [ 'post', 'page' ] ) ),
            'keyword_min_symbols'  => absint( $raw['keyword_min_symbols'] ?? 4 ),
            'keyword_max_words'    => absint( $raw['keyword_max_words']   ?? 25 ),
            'keyword_lang'         => sanitize_key( $raw['keyword_lang']  ?? '' ),
            'twitter_site'         => sanitize_text_field( $raw['twitter_site'] ?? '' ),
            'twitter_card'         => sanitize_text_field( $raw['twitter_card'] ?? 'summary_large_image' ),

        ];
    }
    
    /**
     * AJAX/POST request handler for saving analytics codes.
     * 
     * @return void none
     */
    public function handle_save_analytics(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( Utils::ESC_HTML('Access denied.') );
        }

        check_admin_referer( 'amm_save_analytics' );

        $opts = get_option(AMIRISET_META_MANAGER_OPTION_KEY, Activator::defaults() );

        // wp_unslash only — no kses — admin-only field, script tags must survive
        $opts['analytics_head'] = wp_unslash( Utils::POST('analytics_head') );
        $opts['analytics_body'] = wp_unslash( Utils::POST('analytics_body') );

        update_option( AMIRISET_META_MANAGER_OPTION_KEY, $opts );

        wp_safe_redirect( Utils::ADD_QUERY_ARG_WITH_FRAGMENT(
            admin_url( 'admin.php' ),
            [ 'page' => 'amm-settings', 'amm_saved' => '1' ],
            'amm-analytics'
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
        $amm_pages = [ 'toplevel_page_amm-settings', 'seo-meta_page_amm-pages',
                       'seo-meta_page_amm-posts',    'seo-meta_page_amm-cpt' ];
        // WP uses "toplevel" and "parent" slug to form hook names
        if ( ! str_contains( $hook, 'amm-' ) ) {
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
     * Page settings.
     * 
     * @return void
     */
    public function page_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( Utils::ESC_HTML('Access denied.') );
        }

        $opts     = get_option(AMIRISET_META_MANAGER_OPTION_KEY, Activator::defaults() );
        $all_cpts = $this->get_all_public_cpts();
        ?>
        <div class="wrap amm-admin-wrap">
            <h1><?php Utils::ESC_HTML_E('Amiriset Meta Manager — Settings' ); ?></h1>

            <?php settings_errors(AMIRISET_META_MANAGER_OPTION_KEY ); ?>

            <form method="post" action="options.php">
                <?php settings_fields( '_amm_options_group' ); ?>

                <!-- ── Section: Defaults ── -->
                <h2 class="amm-section-title"><?php Utils::ESC_HTML_E('Meta Tag Defaults'); ?></h2>
                <table class="form-table amm-settings-table">
                    <tr>
                        <th><?php Utils::ESC_HTML_E('Default Robots'); ?></th>
                        <td>
                            <input type="text" name="<?php echo AMIRISET_META_MANAGER_OPTION_KEY; ?>[default_robots]"
                                   value="<?php echo esc_attr( $opts['default_robots'] ); ?>">
                            <p class="description"><?php Utils::ESC_HTML_E('e.g. index, follow'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php Utils::ESC_HTML_E('Default OG Type'); ?></th>
                        <td>
                            <select name="<?php echo AMIRISET_META_MANAGER_OPTION_KEY; ?>[default_og_type]">
                                <?php foreach ( [ 'website', 'article', 'product' ] as $t ) : ?>
                                    <option value="<?php echo esc_attr( $t ); ?>"
                                        <?php selected( $opts['default_og_type'], $t ); ?>>
                                        <?php echo esc_html( $t ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php Utils::ESC_HTML_E('Default OG Image'); ?></th>
                        <td>
                            <div class="amm-og-image-wrap">
                                <?php if ( $opts['default_og_image'] ) : ?>
                                    <img src="<?php echo esc_url( $opts['default_og_image'] ); ?>" class="amm-og-preview" alt="">
                                <?php endif; ?>
                                <input type="hidden" id="amm_default_og_image"
                                       name="<?php echo AMIRISET_META_MANAGER_OPTION_KEY; ?>[default_og_image]"
                                       value="<?php echo esc_attr( $opts['default_og_image'] ); ?>">
                                <button type="button" class="button amm-media-btn" data-target="amm_default_og_image">
                                    <?php Utils::ESC_HTML_E('Select image'); ?>
                                </button>
                                <?php if ( $opts['default_og_image'] ) : ?>
                                    <button type="button" class="button amm-media-remove">
                                        <?php Utils::ESC_HTML_E('Remove'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><?php Utils::ESC_HTML_E('Title Suffix'); ?></th>
                        <td>
                            <input type="text" name="<?php echo AMIRISET_META_MANAGER_OPTION_KEY; ?>[default_title_suffix]"
                                   value="<?php echo esc_attr( $opts['default_title_suffix'] ); ?>">
                            <p class="description"><?php Utils::ESC_HTML_E('Appended to SEO title (e.g. " | My Site").'); ?></p>
                        </td>
                    </tr>
                </table>

                <!-- ── Section: Post types ── -->
                <h2 class="amm-section-title"><?php Utils::ESC_HTML_E('Enabled Post Types'); ?></h2>
                <p class="description"><?php Utils::ESC_HTML_E('The SEO meta box will appear on these post types.'); ?></p>
                <table class="form-table amm-settings-table">
                    <tr>
                        <th><?php Utils::ESC_HTML_E('Post Types'); ?></th>
                        <td>
                            <?php
                            $enabled  = $opts['enabled_post_types'] ?? [ 'post', 'page' ];
                            $builtins = [ 'post' => 'Posts', 'page' => 'Pages' ];
                            foreach ( array_merge( $builtins, $all_cpts ) as $slug => $label ) :
                                ?>
                                <label class="amm-checkbox-label">
                                    <input type="checkbox"
                                           name="<?php echo AMIRISET_META_MANAGER_OPTION_KEY; ?>[enabled_post_types][]"
                                           value="<?php echo esc_attr( $slug ); ?>"
                                           <?php checked( in_array( $slug, $enabled, true ) ); ?>>
                                    <?php echo esc_html( $label . ' (' . $slug . ')' ); ?>
                                </label><br>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>

                <!-- ── Section: Keywords ── -->
                <h2 class="amm-section-title"><?php Utils::ESC_HTML_E('Keyword Extractor Settings'); ?></h2>
                <table class="form-table amm-settings-table">
                    <tr>
                        <th><?php Utils::ESC_HTML_E( 'Min. word length'); ?></th>
                        <td>
                            <input type="number" min="2" max="10"
                                   name="<?php echo AMIRISET_META_MANAGER_OPTION_KEY; ?>[keyword_min_symbols]"
                                   value="<?php echo esc_attr( $opts['keyword_min_symbols'] ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><?php Utils::ESC_HTML_E('Max. keywords'); ?></th>
                        <td>
                            <input type="number" min="5" max="100"
                                   name="<?php echo AMIRISET_META_MANAGER_OPTION_KEY; ?>[keyword_max_words]"
                                   value="<?php echo esc_attr( $opts['keyword_max_words'] ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><?php Utils::ESC_HTML_E('Default language'); ?></th>
                        <td>
                            <select name="<?php echo AMIRISET_META_MANAGER_OPTION_KEY; ?>[keyword_lang]">
                                <option value=""   <?php selected( $opts['keyword_lang'], '' );   ?>><?php Utils::ESC_HTML_E('Auto-detect'); ?></option>
                                <option value="en" <?php selected( $opts['keyword_lang'], 'en' ); ?>>English</option>
                                <option value="ru" <?php selected( $opts['keyword_lang'], 'ru' ); ?>>Русский</option>
                                <option value="uk" <?php selected( $opts['keyword_lang'], 'uk' ); ?>>Українська</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <!-- ── Section: Twitter / X ── -->
                <h2 class="amm-section-title"><?php Utils::ESC_HTML_E('Twitter / X Card Defaults'); ?></h2>
                <table class="form-table amm-settings-table">
                    <tr>
                        <th><?php Utils::ESC_HTML_E('Site Handle'); ?></th>
                        <td>
                            <input type="text"
                                   name="<?php echo AMIRISET_META_MANAGER_OPTION_KEY; ?>[twitter_site]"
                                   value="<?php echo esc_attr( $opts['twitter_site'] ?? '' ); ?>"
                                   placeholder="@yoursite">
                            <p class="description">
                                <?php Utils::ESC_HTML_E('twitter:site — the @username of the website. Output on every singular page.'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php Utils::ESC_HTML_E('Default Card Type'); ?></th>
                        <td>
                            <select name="<?php echo AMIRISET_META_MANAGER_OPTION_KEY; ?>[twitter_card]">
                                <?php
                                $card_types = [
                                    'summary'             => 'summary',
                                    'summary_large_image' => 'summary_large_image',
                                    'app'                 => 'app',
                                    'player'              => 'player',
                                ];
                                $current_card = $opts['twitter_card'] ?? 'summary_large_image';
                                foreach ( $card_types as $val => $label ) {
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr( $val ),
                                        selected( $current_card, $val, false ),
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
                <?php submit_button(); ?>
            </form>
            
            <!-- ── Analytics form (separate — bypasses Settings API to keep <script> tags) ── -->
            <div id="amm-analytics">
                <h2 class="amm-section-title"><?php Utils::ESC_HTML_E('Analytics & Scripts'); ?></h2>
                <?php if ( isset( $_GET['amm_saved'] ) ) : ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php Utils::ESC_HTML_E('Analytics scripts saved.'); ?></p>
                    </div>
                <?php endif; ?>
                <p class="description" style="margin-bottom:12px">
                    <?php Utils::ESC_HTML_E('Paste full HTML blocks (including &lt;script&gt; tags) or raw JavaScript. Tags are preserved as-is.'); ?>
                </p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="amm_save_analytics">
                    <?php wp_nonce_field( 'amm_save_analytics' ); ?>
                    <table class="form-table amm-settings-table">
                        <tr>
                            <th>
                                <label for="amm_analytics_head">
                                    <?php Utils::ESC_HTML_E('&lt;head&gt; Scripts'); ?>
                                </label>
                            </th>
                            <td>
                                <textarea id="amm_analytics_head" name="analytics_head" rows="8"
                                          placeholder="&lt;!-- Google Tag Manager, Meta Pixel, etc --&gt;"
                                ><?php echo esc_textarea( $opts['analytics_head'] ); ?></textarea>
                                <p class="description">
                                    <?php Utils::ESC_HTML_E('Injected inside &lt;head&gt; (before &lt;/head&gt;). Wrap JS in &lt;script&gt; tags.'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="amm_analytics_body">
                                    <?php Utils::ESC_HTML_E( '&lt;body&gt; Scripts'); ?>
                                </label>
                            </th>
                            <td>
                                <textarea id="amm_analytics_body" name="analytics_body" rows="8"
                                          placeholder="&lt;!-- GTM noscript, etc --&gt;"
                                ><?php echo esc_textarea( $opts['analytics_body'] ); ?></textarea>
                                <p class="description">
                                    <?php Utils::ESC_HTML_E('Injected right after &lt;body&gt; open tag (requires theme to call wp_body_open()).' ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( Utils::LANG( 'Save Analytics Scripts' ), 'primary', 'amm_analytics_submit' ); ?>
                </form>
            </div>
        </div>
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
                $meta = MetaBox::get_meta( $pid );

                $has_meta   = ! empty( $meta['description'] ) || ! empty( $meta['keywords'] ) || ! empty( $meta['og_title'] );
                $row_class  = $has_meta ? 'amm-row-has-meta' : 'amm-row-no-meta';
                $status     = get_post_status();

                echo '<tr class="' . esc_attr( $row_class ) . '">';
                printf( '<td><strong>%s</strong></td>', esc_html( get_the_title() ) );
                printf( '<td><span class="amm-status amm-status-%s">%s</span></td>',
                    esc_attr( $status ),
                    esc_html( ucfirst( $status ) )
                );
                printf( '<td>%s</td>', esc_html( $meta['title'] ?: '—' ) );
                printf( '<td class="amm-desc-cell">%s</td>',
                    esc_html( $meta['description'] ? mb_substr( $meta['description'], 0, 80 ) . '…' : '—' )
                );
                printf( '<td class="amm-kw-cell">%s</td>',
                    esc_html( $meta['keywords'] ? mb_substr( $meta['keywords'], 0, 60 ) . '…' : '—' )
                );
                printf( '<td>%s</td>',
                    $meta['og_image']
                        ? '<img src="' . esc_url( $meta['og_image'] ) . '" class="amm-thumb" alt="">'
                        : '—'
                );
                printf( '<td>%s</td>',
                    ! empty( $meta['custom_meta'] )
                        ? '<span class="amm-badge">' . count( $meta['custom_meta'] ) . '</span>'
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
