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
 * @version 1.0.0-a.2
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
            __( 'SEO Meta Manager', AMIRISET_META_MANAGER_TEXT_DOMAIN ),
            __( 'SEO Meta',         AMIRISET_META_MANAGER_TEXT_DOMAIN ),
            'manage_options',
            'amm-settings',
            [ $this, 'page_settings' ],
            'dashicons-search',
            80
        );

        $sub_pages = [
            [ 'amm-settings', __( 'Settings', AMIRISET_META_MANAGER_TEXT_DOMAIN ),      [ $this, 'page_settings' ]  ],
            [ 'amm-pages',    __( 'Pages',    AMIRISET_META_MANAGER_TEXT_DOMAIN ),      [ $this, 'page_post_list' ] ],
            [ 'amm-posts',    __( 'Posts',    AMIRISET_META_MANAGER_TEXT_DOMAIN ),      [ $this, 'page_post_list' ] ],
            [ 'amm-cpt',      __( 'Custom Post Types', AMIRISET_META_MANAGER_TEXT_DOMAIN ), [ $this, 'page_post_list' ] ],
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
            'amm_options_group',
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
        ];
    }
    
    /**
     * AJAX/POST request handler for saving analytics codes.
     * 
     * @return void none
     */
    public function handle_save_analytics(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Access denied.', AMIRISET_META_MANAGER_TEXT_DOMAIN ) );
        }

        check_admin_referer( 'amm_save_analytics' );

        $opts = get_option(AMIRISET_META_MANAGER_OPTION_KEY, Activator::defaults() );

        // wp_unslash only — no kses — admin-only field, script tags must survive
        $opts['analytics_head'] = wp_unslash( $_POST['analytics_head'] ?? '' );
        $opts['analytics_body'] = wp_unslash( $_POST['analytics_body'] ?? '' );

        update_option( SMM_OPTION_KEY, $opts );

        wp_safe_redirect( add_query_arg(
            [ 'page' => 'amm-settings', 'amm_saved' => '1', '#' => 'amm-analytics' ],
            admin_url( 'admin.php' )
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
                __( 'Settings', AMIRISET_META_MANAGER_TEXT_DOMAIN )
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
            wp_die( esc_html__( 'Access denied.', AMIRISET_META_MANAGER_TEXT_DOMAIN ) );
        }

        $opts     = get_option(AMIRISET_META_MANAGER_OPTION_KEY, Activator::defaults() );
        $all_cpts = $this->get_all_public_cpts();
        ?>
        <div class="wrap amm-admin-wrap">
            <h1><?php esc_html_e( 'SEO Meta Manager — Settings', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></h1>

            <?php settings_errors(AMIRISET_META_MANAGER_OPTION_KEY ); ?>

            <form method="post" action="options.php">
                <?php settings_fields( 'amm_options_group' ); ?>

                <!-- ── Section: Defaults ── -->
                <h2 class="amm-section-title"><?php esc_html_e( 'Meta Tag Defaults', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></h2>
                <table class="form-table amm-settings-table">
                    <tr>
                        <th><?php esc_html_e( 'Default Robots', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></th>
                        <td>
                            <input type="text" name="<?php echo AMIRISET_META_MANAGER_OPTION_KEY; ?>[default_robots]"
                                   value="<?php echo esc_attr( $opts['default_robots'] ); ?>">
                            <p class="description"><?php esc_html_e( 'e.g. index, follow', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Default OG Type', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></th>
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
                        <th><?php esc_html_e( 'Default OG Image', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></th>
                        <td>
                            <div class="amm-og-image-wrap">
                                <?php if ( $opts['default_og_image'] ) : ?>
                                    <img src="<?php echo esc_url( $opts['default_og_image'] ); ?>" class="amm-og-preview" alt="">
                                <?php endif; ?>
                                <input type="hidden" id="amm_default_og_image"
                                       name="<?php echo AMIRISET_META_MANAGER_OPTION_KEY; ?>[default_og_image]"
                                       value="<?php echo esc_attr( $opts['default_og_image'] ); ?>">
                                <button type="button" class="button amm-media-btn" data-target="amm_default_og_image">
                                    <?php esc_html_e( 'Select image', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Title Suffix', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></th>
                        <td>
                            <input type="text" name="<?php echo AMIRISET_META_MANAGER_OPTION_KEY; ?>[default_title_suffix]"
                                   value="<?php echo esc_attr( $opts['default_title_suffix'] ); ?>">
                            <p class="description"><?php esc_html_e( 'Appended to SEO title (e.g. " | My Site").', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></p>
                        </td>
                    </tr>
                </table>

                <!-- ── Section: Post types ── -->
                <h2 class="amm-section-title"><?php esc_html_e( 'Enabled Post Types', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></h2>
                <p class="description"><?php esc_html_e( 'The SEO meta box will appear on these post types.', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></p>
                <table class="form-table amm-settings-table">
                    <tr>
                        <th><?php esc_html_e( 'Post Types', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></th>
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
                <h2 class="amm-section-title"><?php esc_html_e( 'Keyword Extractor Settings', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></h2>
                <table class="form-table amm-settings-table">
                    <tr>
                        <th><?php esc_html_e( 'Min. word length', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></th>
                        <td>
                            <input type="number" min="2" max="10"
                                   name="<?php echo AMIRISET_META_MANAGER_OPTION_KEY; ?>[keyword_min_symbols]"
                                   value="<?php echo esc_attr( $opts['keyword_min_symbols'] ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Max. keywords', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></th>
                        <td>
                            <input type="number" min="5" max="100"
                                   name="<?php echo AMIRISET_META_MANAGER_OPTION_KEY; ?>[keyword_max_words]"
                                   value="<?php echo esc_attr( $opts['keyword_max_words'] ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Default language', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></th>
                        <td>
                            <select name="<?php echo AMIRISET_META_MANAGER_OPTION_KEY; ?>[keyword_lang]">
                                <option value=""   <?php selected( $opts['keyword_lang'], '' );   ?>><?php esc_html_e( 'Auto-detect', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></option>
                                <option value="en" <?php selected( $opts['keyword_lang'], 'en' ); ?>>English</option>
                                <option value="ru" <?php selected( $opts['keyword_lang'], 'ru' ); ?>>Русский</option>
                                <option value="uk" <?php selected( $opts['keyword_lang'], 'uk' ); ?>>Українська</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <!-- ── Section: Analytics ── -->
                <?php submit_button(); ?>
            </form>
            
            <!-- ── Analytics form (separate — bypasses Settings API to keep <script> tags) ── -->
            <div id="amm-analytics">
                <h2 class="amm-section-title"><?php esc_html_e( 'Analytics & Scripts', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></h2>
                <?php if ( isset( $_GET['amm_saved'] ) ) : ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php esc_html_e( 'Analytics scripts saved.', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></p>
                    </div>
                <?php endif; ?>
                <p class="description" style="margin-bottom:12px">
                    <?php esc_html_e( 'Paste full HTML blocks (including &lt;script&gt; tags) or raw JavaScript. Tags are preserved as-is.', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?>
                </p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="amm_save_analytics">
                    <?php wp_nonce_field( 'amm_save_analytics' ); ?>
                    <table class="form-table amm-settings-table">
                        <tr>
                            <th>
                                <label for="amm_analytics_head">
                                    <?php esc_html_e( '&lt;head&gt; Scripts', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?>
                                </label>
                            </th>
                            <td>
                                <textarea id="amm_analytics_head" name="analytics_head" rows="8"
                                          placeholder="&lt;!-- Google Tag Manager, Meta Pixel, etc --&gt;"
                                ><?php echo esc_textarea( $opts['analytics_head'] ); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e( 'Injected inside &lt;head&gt; (before &lt;/head&gt;). Wrap JS in &lt;script&gt; tags.', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="amm_analytics_body">
                                    <?php esc_html_e( '&lt;body&gt; Scripts', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?>
                                </label>
                            </th>
                            <td>
                                <textarea id="amm_analytics_body" name="analytics_body" rows="8"
                                          placeholder="&lt;!-- GTM noscript, etc --&gt;"
                                ><?php echo esc_textarea( $opts['analytics_body'] ); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e( 'Injected right after &lt;body&gt; open tag (requires theme to call wp_body_open()).', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( __( 'Save Analytics Scripts', AMIRISET_META_MANAGER_TEXT_DOMAIN ), 'primary', 'amm_analytics_submit' ); ?>
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
            wp_die( esc_html__( 'Access denied.', AMIRISET_META_MANAGER_TEXT_DOMAIN ) );
        }

        $current_page = sanitize_key( $_GET['page'] ?? 'amm-settings' );

        // Determine which post type(s) to list
        $post_type = match ( $current_page ) {
            'amm-pages' => 'page',
            'amm-posts' => 'post',
            'amm-cpt'   => sanitize_key( $_GET['cpt'] ?? '' ),
            default     => '',
        };

        $all_cpts = $this->get_all_public_cpts();

        ?>
        <div class="wrap amm-admin-wrap">
            <h1>
                <?php
                echo esc_html( match ( $current_page ) {
                    'amm-pages' => __( 'SEO Meta — Pages', AMIRISET_META_MANAGER_TEXT_DOMAIN ),
                    'amm-posts' => __( 'SEO Meta — Posts', AMIRISET_META_MANAGER_TEXT_DOMAIN ),
                    'amm-cpt'   => __( 'SEO Meta — Custom Post Types', AMIRISET_META_MANAGER_TEXT_DOMAIN ),
                    default     => 'SEO Meta',
                } );
                ?>
            </h1>

            <?php if ( 'amm-cpt' === $current_page ) : ?>
                <!-- CPT picker -->
                <form method="get" class="amm-cpt-picker">
                    <input type="hidden" name="page" value="amm-cpt">
                    <select name="cpt" onchange="this.form.submit()">
                        <option value=""><?php esc_html_e( '— Select Post Type —', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></option>
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
                <p><?php esc_html_e( 'Please select a post type above.', AMIRISET_META_MANAGER_TEXT_DOMAIN ); ?></p>
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
        $paged = absint( $_GET['paged'] ?? 1 );
        $per   = 20;

        $query = new WP_Query( [
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
        echo '<th>' . esc_html__( 'Title', AMIRISET_META_MANAGER_TEXT_DOMAIN )       . '</th>';
        echo '<th>' . esc_html__( 'Status', AMIRISET_META_MANAGER_TEXT_DOMAIN )      . '</th>';
        echo '<th>' . esc_html__( 'SEO Title', AMIRISET_META_MANAGER_TEXT_DOMAIN )   . '</th>';
        echo '<th>' . esc_html__( 'Description', AMIRISET_META_MANAGER_TEXT_DOMAIN ) . '</th>';
        echo '<th>' . esc_html__( 'Keywords', AMIRISET_META_MANAGER_TEXT_DOMAIN )    . '</th>';
        echo '<th>' . esc_html__( 'OG Image', AMIRISET_META_MANAGER_TEXT_DOMAIN )    . '</th>';
        echo '<th>' . esc_html__( 'Custom Tags', AMIRISET_META_MANAGER_TEXT_DOMAIN ) . '</th>';
        echo '<th>' . esc_html__( 'Edit', AMIRISET_META_MANAGER_TEXT_DOMAIN )        . '</th>';
        echo '</tr></thead><tbody>';

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $pid  = get_the_ID();
                $meta = SMM_Meta_Box::get_meta( $pid );

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
            echo '<tr><td colspan="8">' . esc_html__( 'No posts found.', AMIRISET_META_MANAGER_TEXT_DOMAIN ) . '</td></tr>';
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
