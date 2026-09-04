<?php
/**
 * Registers the Custom Post Type and Taxonomies.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TCA_RE_CPT_Taxonomies {

    public function __construct() {
        add_action( 'init', [ $this, 'register_cpt' ] );
        add_action( 'init', [ $this, 'register_taxonomies' ] );

        // Admin Dashboard Permit Expiry Views
        add_filter( 'manage_tca_unit_posts_columns', [ $this, 'add_permit_column' ] );
        add_action( 'manage_tca_unit_posts_custom_column', [ $this, 'display_permit_column' ], 10, 2 );
        add_action( 'admin_head', [ $this, 'permit_column_css' ] );
        add_action( 'admin_footer', [ $this, 'quick_toggle_js' ] );

        // Sortable columns
        add_filter( 'manage_edit-tca_unit_sortable_columns', [ $this, 'make_columns_sortable' ] );
        add_action( 'pre_get_posts', [ $this, 'handle_sortable_columns' ] );
        add_filter( 'posts_clauses', [ $this, 'sort_units_by_purpose' ], 10, 2 );

        // Reference column filter (search by reference)
        add_action( 'restrict_manage_posts', [ $this, 'reference_filter_dropdown' ] );
        add_action( 'pre_get_posts', [ $this, 'filter_posts_by_reference' ] );

        // AJAX Handler for the toggle
        add_action( 'wp_ajax_tca_quick_toggle', [ $this, 'ajax_quick_toggle' ] );
        add_action( 'wp_ajax_tca_toggle_featured', [ $this, 'ajax_toggle_featured' ] );

        // Filter for dynamic permalinks: /unit/{purpose}/{project}/{reference}/
        add_filter( 'post_type_link', [ $this, 'filter_unit_permalink' ], 1, 2 );

        // Auto-sync: when a unit is saved, set its slug = reference number
        add_action( 'save_post_tca_unit', [ $this, 'sync_reference_to_slug' ], 99, 2 );

        // One-click migration for existing units
        add_action( 'wp_ajax_tca_fix_all_slugs', [ $this, 'ajax_fix_all_slugs' ] );
        add_action( 'admin_notices', [ $this, 'show_slug_migration_notice' ] );

        // Custom rewrite rules for purpose-based URLs
        add_action( 'init', [ $this, 'add_unit_rewrite_rules' ], 99 );

        // 301 redirect old /unit/* URLs to new format
        add_action( 'template_redirect', [ $this, 'redirect_old_unit_urls' ] );

        // Smart 404 Redirect for sold/deleted units
        add_action( 'template_redirect', [ $this, 'redirect_404_units' ] );

        // Auto-filter all property queries (including Crafto Theme widgets) on Developer pages
        add_action( 'pre_get_posts', [ $this, 'auto_filter_developer_queries' ], 99 );

        // Taxonomy Sort Order field hooks
        $order_taxs = [ 'tca_purpose', 'tca_property_type', 'tca_location', 'tca_developer', 'tca_project' ];
        foreach ( $order_taxs as $tax ) {
            add_action( "{$tax}_add_form_fields",  [ $this, 'tax_order_add_field' ] );
            add_action( "{$tax}_edit_form_fields", [ $this, 'tax_order_edit_field' ], 10, 2 );
            add_action( "created_{$tax}",          [ $this, 'tax_order_save' ], 10, 2 );
            add_action( "edited_{$tax}",           [ $this, 'tax_order_save' ], 10, 2 );
            add_filter( "manage_edit-{$tax}_columns",        [ $this, 'tax_order_column_header' ] );
            add_filter( "manage_{$tax}_custom_column",       [ $this, 'tax_order_column_content' ], 10, 3 );
        }

        // Developer Logo fields hooks
        add_action( 'tca_developer_add_form_fields',  [ $this, 'tca_developer_add_logo_field' ] );
        add_action( 'tca_developer_edit_form_fields', [ $this, 'tca_developer_edit_logo_field' ], 10, 2 );
        add_action( 'created_tca_developer',          [ $this, 'tca_developer_save_logo' ] );
        add_action( 'edited_tca_developer',           [ $this, 'tca_developer_save_logo' ] );
        add_action( 'admin_enqueue_scripts',          [ $this, 'tca_developer_admin_scripts' ] );
    }

    public function register_cpt() {
        $labels = [
            'name'               => 'Units',
            'singular_name'      => 'Unit',
            'menu_name'          => 'Real Estate',
            'name_admin_bar'     => 'Real Estate Unit',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Unit',
            'new_item'           => 'New Unit',
            'edit_item'          => 'Edit Unit',
            'view_item'          => 'View Unit',
            'all_items'          => 'All Units',
            'search_items'       => 'Search Units',
            'parent_item_colon'  => 'Parent Units:',
            'not_found'          => 'No units found.',
            'not_found_in_trash' => 'No units found in Trash.'
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => [ 'slug' => 'unit/%tca_project%', 'with_front' => false ],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 20,
            'menu_icon'          => 'dashicons-building',
            'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
            'show_in_rest'       => true, // Enables Gutenberg and REST API
        ];

        register_post_type( 'tca_unit', $args );
    }

    public function register_taxonomies() {
        // Taxonomy arguments template for standard hierarchical tax
        $base_args = [
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'show_in_rest'      => true,
        ];

        // 1. Purpose (Buy/Rent)
        $args_purpose = $base_args;
        $args_purpose['labels'] = $this->get_tax_labels('Purpose', 'Purposes');
        $args_purpose['rewrite'] = [ 'slug' => 'purpose' ];
        register_taxonomy( 'tca_purpose', 'tca_unit', $args_purpose );

        // 2. Property Type
        $args_type = $base_args;
        $args_type['labels'] = $this->get_tax_labels('Property Type', 'Property Types');
        $args_type['rewrite'] = [ 'slug' => 'property-type' ];
        register_taxonomy( 'tca_property_type', 'tca_unit', $args_type );

        // 3. Location
        $args_location = $base_args;
        $args_location['labels'] = $this->get_tax_labels('Location', 'Locations');
        $args_location['rewrite'] = [ 'slug' => 'location' ];
        register_taxonomy( 'tca_location', 'tca_unit', $args_location );

        // 4. Developer
        $args_dev = $base_args;
        $args_dev['labels'] = $this->get_tax_labels('Developer', 'Developers');
        $args_dev['rewrite'] = [ 'slug' => 'developer' ];
        register_taxonomy( 'tca_developer', [ 'tca_unit', 'page', 'properties' ], $args_dev );

        // 5. Project Name
        $args_project = $base_args;
        $args_project['labels'] = $this->get_tax_labels('Project Name', 'Projects');
        $args_project['rewrite'] = [ 'slug' => 'project' ];
        register_taxonomy( 'tca_project', [ 'tca_unit', 'page', 'properties' ], $args_project );

        // 6. Amenities
        $args_amenity = $base_args;
        $args_amenity['labels'] = $this->get_tax_labels('Amenity', 'Amenities');
        $args_amenity['rewrite'] = [ 'slug' => 'amenity' ];
        register_taxonomy( 'tca_amenity', 'tca_unit', $args_amenity );
    }

    private function get_tax_labels($singular, $plural) {
        return [
            'name'              => $plural,
            'singular_name'     => $singular,
            'search_items'      => 'Search ' . $plural,
            'all_items'         => 'All ' . $plural,
            'parent_item'       => 'Parent ' . $singular,
            'parent_item_colon' => 'Parent ' . $singular . ':',
            'edit_item'         => 'Edit ' . $singular,
            'update_item'       => 'Update ' . $singular,
            'add_new_item'      => 'Add New ' . $singular,
            'new_item_name'     => 'New ' . $singular . ' Name',
            'menu_name'         => $plural,
        ];
    }

    public function add_permit_column( $columns ) {
        // Insert custom columns before the final Date/SEO blocks
        $new_columns = [];
        foreach($columns as $key => $title) {
            if ($key === 'title') {
                $new_columns[$key] = $title;
                $new_columns['tca_reference'] = 'Reference';
                continue;
            }
            if ($key === 'date') {
                $new_columns['tca_featured']   = 'Featured';
                $new_columns['tca_status']     = 'Live Status';
                $new_columns['permit_expiry']  = 'Madhmoun Expiry';
            }
            $new_columns[$key] = $title;
        }
        return $new_columns;
    }

    public function display_permit_column( $column, $post_id ) {
        if ( $column === 'tca_reference' ) {
            $ref = get_post_meta( $post_id, '_tca_reference', true );
            if ( $ref ) {
                echo '<code style="background:#f0f4f8;padding:2px 7px;border-radius:4px;font-size:12px;color:#0a3c61;">' . esc_html( $ref ) . '</code>';
            } else {
                echo '<span style="color:#aaa;">—</span>';
            }
        }

        if ( $column === 'tca_featured' ) {
            $is_featured = get_post_meta( $post_id, '_tca_featured', true );
            $nonce = wp_create_nonce( 'tca_toggle_featured_' . $post_id );
            if ( $is_featured == '1' ) {
                echo '<span class="tca-featured-star active" data-post-id="' . esc_attr($post_id) . '" data-nonce="' . esc_attr($nonce) . '" title="Click to unfeature">&#9733; Featured</span>';
            } else {
                echo '<span class="tca-featured-star" data-post-id="' . esc_attr($post_id) . '" data-nonce="' . esc_attr($nonce) . '" title="Click to feature">&#9734;</span>';
            }
        }

        if ( $column === 'permit_expiry' ) {
            $expiry = get_post_meta( $post_id, '_tca_permit_expiry', true );
            if ( ! $expiry ) {
                echo '<span style="color:#aaa;">Not Set</span>';
                return;
            }

            $expiry_time = strtotime($expiry);
            $now = time();
            $diff_days = ($expiry_time - $now) / DAY_IN_SECONDS;

            if ( $diff_days < 0 ) {
                echo '<span class="tca-permit-expired">EXPIRED (' . esc_html($expiry) . ')</span>';
            } elseif ( $diff_days <= 3 ) {
                echo '<span class="tca-permit-warning">Expires in ' . ceil($diff_days) . ' days (' . esc_html($expiry) . ')</span>';
            } else {
                echo '<span class="tca-permit-safe">' . esc_html($expiry) . '</span>';
            }
        }
        
        if ( $column === 'tca_status' ) {
            $status = get_post_status( $post_id );
            $is_checked = ( $status === 'publish' ) ? 'checked' : '';
            $nonce = wp_create_nonce('tca_quick_toggle_' . $post_id);
            echo '<label class="tca-switch">
                    <input type="checkbox" class="tca-quick-status-toggle" data-post-id="' . esc_attr($post_id) . '" data-nonce="' . esc_attr($nonce) . '" ' . $is_checked . '>
                    <span class="tca-slider round"></span>
                  </label>';
            echo '<span class="tca-status-lbl" id="tca-lbl-' . $post_id . '">' . ($is_checked ? 'Published' : 'Draft') . '</span>';
        }
    }

    public function permit_column_css() {
        echo '<style>
            .tca-permit-expired { color: #d63638; font-weight: bold; background: #fcf0f1; padding: 4px 8px; border-radius: 4px; display: inline-block; }
            .tca-permit-warning { color: #8a6d3b; font-weight: bold; background: #fcf8e3; padding: 4px 8px; border-radius: 4px; display: inline-block; }
            .tca-permit-safe { color: #00a32a; font-weight: 500; }
            
            /* Featured Star */
            .tca-featured-star { font-size: 18px; color: #ccc; cursor: pointer; transition: color 0.2s; user-select: none; white-space: nowrap; }
            .tca-featured-star.active { color: #f0b429; font-weight: bold; }
            .tca-featured-star:hover { color: #f0b429; }

            /* Toggle Switch */
            .tca-switch { position: relative; display: inline-block; width: 34px; height: 20px; vertical-align: middle; margin-right: 8px;}
            .tca-switch input { opacity: 0; width: 0; height: 0; }
            .tca-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 20px;}
            .tca-slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%;}
            input:checked + .tca-slider { background-color: #2271b1; }
            input:focus + .tca-slider { box-shadow: 0 0 1px #2271b1; }
            input:checked + .tca-slider:before { transform: translateX(14px); }
            .tca-status-lbl { vertical-align: middle; font-size: 13px; font-weight: 500; }
        </style>';
    }

    public function quick_toggle_js() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('.tca-quick-status-toggle').on('change', function() {
                var checkbox = $(this);
                var postId = checkbox.data('post-id');
                var nonce = checkbox.data('nonce');
                var isChecked = checkbox.is(':checked');
                var newState = isChecked ? 'publish' : 'draft';
                var lbl = $('#tca-lbl-' + postId);
                
                lbl.text('Updating...');
                checkbox.prop('disabled', true);

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'tca_quick_toggle',
                        post_id: postId,
                        state: newState,
                        security: nonce
                    },
                    success: function(response) {
                        if(response.success) {
                            lbl.text(newState === 'publish' ? 'Published' : 'Draft');
                            lbl.css('color', newState === 'publish' ? '#2271b1' : '#646970');
                            
                            // Also update the native WP Date column status text slightly
                            var titleCol = checkbox.closest('tr').find('.column-title .post-state');
                            if(newState === 'draft') {
                                if(titleCol.length === 0) checkbox.closest('tr').find('.row-title').after(' — <span class="post-state">Draft</span>');
                            } else {
                                titleCol.remove();
                            }
                        } else {
                            alert('Toggle failed: ' + response.data);
                            checkbox.prop('checked', !isChecked);
                            lbl.text(!isChecked ? 'Published' : 'Draft');
                        }
                    },
                    error: function() {
                        alert('Server error.');
                        checkbox.prop('checked', !isChecked);
                        lbl.text(!isChecked ? 'Published' : 'Draft');
                    },
                    complete: function() {
                        checkbox.prop('disabled', false);
                    }
                });
            });

            // Featured star toggle (AJAX)
            $(document).on('click', '.tca-featured-star', function() {
                var star = $(this);
                var postId = star.data('post-id');
                var nonce = star.data('nonce');
                var currentlyFeatured = star.hasClass('active');
                var newVal = currentlyFeatured ? '0' : '1';

                star.css('opacity', 0.5);
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: { action: 'tca_toggle_featured', post_id: postId, value: newVal, security: nonce },
                    success: function(response) {
                        if (response.success) {
                            if (newVal === '1') {
                                star.addClass('active').html('&#9733; Featured').attr('title', 'Click to unfeature');
                            } else {
                                star.removeClass('active').html('&#9734;').attr('title', 'Click to feature');
                            }
                        }
                    },
                    complete: function() { star.css('opacity', 1); }
                });
            });
        });
        </script>
        <?php
    }
 
    public function ajax_quick_toggle() {
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $state = isset($_POST['state']) ? sanitize_text_field($_POST['state']) : '';
        
        if ( ! check_ajax_referer( 'tca_quick_toggle_' . $post_id, 'security', false ) ) {
            wp_send_json_error( 'Invalid security token.' );
        }
        
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        if ( ! in_array( $state, ['publish', 'draft'] ) ) {
            wp_send_json_error( 'Invalid state.' );
        }

        wp_update_post([
            'ID' => $post_id,
            'post_status' => $state
        ]);

        wp_send_json_success( 'Updated' );
    }

    /**
     * AJAX: Toggle Featured meta.
     */
    public function ajax_toggle_featured() {
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $value   = isset($_POST['value']) && $_POST['value'] === '1' ? '1' : '0';

        if ( ! check_ajax_referer( 'tca_toggle_featured_' . $post_id, 'security', false ) ) {
            wp_send_json_error( 'Invalid security token.' );
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        update_post_meta( $post_id, '_tca_featured', $value );
        wp_send_json_success( 'Updated' );
    }

    /**
     * Make taxonomy and meta columns sortable in admin list.
     */
    public function make_columns_sortable( $columns ) {
        $columns['tca_featured']  = 'tca_featured';
        $columns['tca_reference'] = 'tca_reference';
        $columns['permit_expiry'] = 'permit_expiry';
        $columns['taxonomy-tca_purpose'] = 'tca_purpose';
        return $columns;
    }

    /**
     * Sort units by purpose taxonomy term.
     */
    public function sort_units_by_purpose( $clauses, $query ) {
        global $wpdb;
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return $clauses;
        }
        if ( $query->get('post_type') !== 'tca_unit' ) {
            return $clauses;
        }

        $orderby = $query->get('orderby');
        if ( $orderby === 'tca_purpose' ) {
            $order = strtoupper( $query->get('order') ) === 'ASC' ? 'ASC' : 'DESC';
            
            $clauses['join'] .= "
                LEFT OUTER JOIN {$wpdb->term_relationships} ON {$wpdb->posts}.ID = {$wpdb->term_relationships}.object_id
                LEFT OUTER JOIN {$wpdb->term_taxonomy} ON {$wpdb->term_relationships}.term_taxonomy_id = {$wpdb->term_taxonomy}.term_taxonomy_id AND {$wpdb->term_taxonomy}.taxonomy = 'tca_purpose'
                LEFT OUTER JOIN {$wpdb->terms} ON {$wpdb->term_taxonomy}.term_id = {$wpdb->terms}.term_id
            ";
            
            if ( strpos( $clauses['groupby'], "{$wpdb->posts}.ID" ) === false ) {
                $clauses['groupby'] = empty( $clauses['groupby'] ) ? "{$wpdb->posts}.ID" : $clauses['groupby'] . ", {$wpdb->posts}.ID";
            }
            
            $clauses['orderby'] = "{$wpdb->terms}.name {$order}";
        }

        return $clauses;
    }

    /**
     * Handle sorting when admin clicks column headers.
     */
    public function handle_sortable_columns( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) return;
        if ( $query->get('post_type') !== 'tca_unit' ) return;

        $orderby = $query->get('orderby');

        if ( $orderby === 'tca_featured' ) {
            $query->set('meta_key', '_tca_featured');
            $query->set('orderby', 'meta_value');
        }
        if ( $orderby === 'tca_reference' ) {
            $query->set('meta_key', '_tca_reference');
            $query->set('orderby', 'meta_value');
        }
        if ( $orderby === 'permit_expiry' ) {
            $query->set('meta_key', '_tca_permit_expiry');
            $query->set('orderby', 'meta_value');
        }

        // Filter by reference from dropdown
        $this->filter_posts_by_reference( $query );
    }

    /**
     * Reference search: adds a text input in admin filters bar.
     */
    public function reference_filter_dropdown() {
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== 'tca_unit' ) return;

        $current = isset( $_GET['tca_ref_search'] ) ? sanitize_text_field( $_GET['tca_ref_search'] ) : '';
        echo '<input type="text" name="tca_ref_search" placeholder="Search by Reference..." value="' . esc_attr( $current ) . '" style="margin-left:5px;height:32px;line-height:32px;padding:0 8px;border:1px solid #ccc;border-radius:4px;font-size:13px;width:200px;">';
    }

    /**
     * Reference search: filters the main query when a reference is typed.
     */
    public function filter_posts_by_reference( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) return;
        if ( $query->get('post_type') !== 'tca_unit' ) return;

        $ref = isset( $_GET['tca_ref_search'] ) ? sanitize_text_field( $_GET['tca_ref_search'] ) : '';
        if ( empty( $ref ) ) return;

        $meta_query = $query->get('meta_query') ?: [];
        $meta_query[] = [
            'key'     => '_tca_reference',
            'value'   => $ref,
            'compare' => 'LIKE',
        ];
        $query->set( 'meta_query', $meta_query );
    }

    // =========================================================
    // TAXONOMY SORT ORDER FIELDS
    // =========================================================

    /**
     * Add Sort Order field on the "Add New Term" form.
     */
    public function tax_order_add_field( $taxonomy ) {
        echo '<div class="form-field">
            <label for="tca_menu_order">Sort Order</label>
            <input type="number" name="tca_menu_order" id="tca_menu_order" value="0" min="0" style="width:80px;">
            <p>Lower numbers appear first in dropdowns and filters. Default: 0.</p>
        </div>';
    }

    /**
     * Add Sort Order field on the "Edit Term" form.
     */
    public function tax_order_edit_field( $term, $taxonomy ) {
        $order = (int) get_term_meta( $term->term_id, 'tca_menu_order', true );
        echo '<tr class="form-field">
            <th scope="row"><label for="tca_menu_order">Sort Order</label></th>
            <td>
                <input type="number" name="tca_menu_order" id="tca_menu_order" value="' . esc_attr( $order ) . '" min="0" style="width:80px;">
                <p class="description">Lower numbers appear first in dropdowns and filters.</p>
            </td>
        </tr>';
    }

    /**
     * Save the Sort Order term meta.
     */
    public function tax_order_save( $term_id ) {
        if ( isset( $_POST['tca_menu_order'] ) ) {
            update_term_meta( $term_id, 'tca_menu_order', (int) $_POST['tca_menu_order'] );
        }
    }

    /**
     * Add Sort Order column header to taxonomy list tables.
     */
    public function tax_order_column_header( $columns ) {
        $columns['tca_menu_order'] = 'Order';
        return $columns;
    }

    /**
     * Display Sort Order in the taxonomy list table.
     */
    public function tax_order_column_content( $content, $column_name, $term_id ) {
        if ( $column_name === 'tca_menu_order' ) {
            $order = (int) get_term_meta( $term_id, 'tca_menu_order', true );
            return '<strong>' . $order . '</strong>';
        }
        return $content;
    }

    /**
     * Generates clean URLs: /unit/{purpose}/{project}/{reference}/
     * e.g. /unit/buy/al-maryah-vista/capitalave-91873
     */
    public function filter_unit_permalink( $post_link, $post ) {
        if ( 'tca_unit' !== $post->post_type ) {
            return $post_link;
        }

        $projects = get_the_terms( $post->ID, 'tca_project' );
        $project_slug = ( ! empty( $projects ) && ! is_wp_error( $projects ) ) ? $projects[0]->slug : 'all';

        $purposes = get_the_terms( $post->ID, 'tca_purpose' );
        $purpose_slug = ( ! empty( $purposes ) && ! is_wp_error( $purposes ) ) ? $purposes[0]->slug : 'buy';

        // Use reference number as slug — clean and informative
        $ref  = get_post_meta( $post->ID, '_tca_reference', true );
        $slug = $ref ? sanitize_title( $ref ) : $post->post_name;

        return user_trailingslashit( home_url( '/unit/' . $purpose_slug . '/' . $project_slug . '/' . $slug ) );
    }

    /**
     * When a unit is saved, auto-update its WordPress post_name to match the reference.
     * Required so the rewrite rule (which resolves by post_name) finds the correct post.
     */
    public function sync_reference_to_slug( $post_id, $post ) {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;

        $ref = get_post_meta( $post_id, '_tca_reference', true );
        if ( ! $ref ) return;

        $new_slug = sanitize_title( $ref );
        if ( $new_slug === $post->post_name ) return;

        remove_action( 'save_post_tca_unit', [ $this, 'sync_reference_to_slug' ], 99 );
        wp_update_post( [ 'ID' => $post_id, 'post_name' => $new_slug ] );
        add_action( 'save_post_tca_unit', [ $this, 'sync_reference_to_slug' ], 99, 2 );
    }

    /**
     * AJAX: bulk-update all existing unit slugs to their reference numbers.
     */
    public function ajax_fix_all_slugs() {
        check_ajax_referer( 'tca_fix_slugs_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied.' );

        $units   = get_posts( [ 'post_type' => 'tca_unit', 'posts_per_page' => -1, 'post_status' => 'any' ] );
        $updated = 0;
        foreach ( $units as $unit ) {
            $ref = get_post_meta( $unit->ID, '_tca_reference', true );
            if ( ! $ref ) continue;
            $new_slug = sanitize_title( $ref );
            if ( $new_slug === $unit->post_name ) continue;
            wp_update_post( [ 'ID' => $unit->ID, 'post_name' => $new_slug ] );
            $updated++;
        }
        wp_send_json_success( [ 'updated' => $updated, 'total' => count( $units ) ] );
    }

    /**
     * Admin notice with a one-click "Fix All Slugs" button on the Units list screen.
     */
    public function show_slug_migration_notice() {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'edit-tca_unit' ) return;
        $nonce = wp_create_nonce( 'tca_fix_slugs_nonce' );
        echo '<div class="notice notice-info" id="tca-slug-notice" style="padding:12px 16px;">
            <p><strong>TCA:</strong> Click below to update all unit URLs to use short reference-based slugs (e.g. <code>/unit/buy/project/<strong>capitalave-91873</strong></code>). One-time action — safe to run.</p>
            <p>
                <button class="button button-primary" id="tca-fix-slugs-btn" data-nonce="' . esc_attr( $nonce ) . '">Update All Unit Slugs</button>
                <span id="tca-fix-slugs-msg" style="margin-left:12px;font-weight:500;"></span>
            </p>
        </div>
        <script>
        document.getElementById("tca-fix-slugs-btn").addEventListener("click", function() {
            var btn = this, msg = document.getElementById("tca-fix-slugs-msg");
            btn.disabled = true; msg.textContent = "Updating slugs...";
            fetch(ajaxurl, { method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"},
                body:"action=tca_fix_all_slugs&nonce="+btn.dataset.nonce })
            .then(r=>r.json()).then(data=>{
                if(data.success){
                    msg.style.color="#00a32a";
                    msg.textContent = "Done! Updated "+data.data.updated+" of "+data.data.total+" units. Now go to Settings > Permalinks > Save Changes.";
                    document.getElementById("tca-slug-notice").style.borderLeftColor="#00a32a";
                } else { msg.style.color="red"; msg.textContent="Error: "+data.data; btn.disabled=false; }
            });
        });
        </script>';
    }


    /**
     * Adds rewrite rules for /unit/{purpose}/{project}/{postname}/
     * 4-segment rule takes priority over the old 3-segment CPT rule.
     */
    public function add_unit_rewrite_rules() {
        $purposes = get_terms( [ 'taxonomy' => 'tca_purpose', 'hide_empty' => false ] );
        if ( ! is_wp_error( $purposes ) && ! empty( $purposes ) ) {
            foreach ( $purposes as $purpose ) {
                add_rewrite_rule(
                    '^unit/' . preg_quote( $purpose->slug, '/' ) . '/([^/]+)/([^/]+)/?$',
                    'index.php?post_type=tca_unit&name=$matches[2]',
                    'top'
                );
            }
        }
    }

    /**
     * 301-redirect old /unit/{project}/{slug} URLs (3 segments) to new
     * /unit/{purpose}/{project}/{slug} format (4 segments).
     */
    public function redirect_old_unit_urls() {
        if ( ! is_singular( 'tca_unit' ) ) return;

        $path   = isset( $_SERVER['REQUEST_URI'] ) ? parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) : '';
        $parts  = array_values( array_filter( explode( '/', $path ) ) );

        // Old format has exactly 3 parts: ['unit', 'project-slug', 'post-slug']
        // New format has 4: ['unit', 'purpose', 'project-slug', 'post-slug']
        if ( isset( $parts[0] ) && $parts[0] === 'unit' && count( $parts ) === 3 ) {
            wp_redirect( get_permalink(), 301 );
            exit;
        }
    }

    /**
     * Intercept 404 errors for units (deleted/draft/sold) and 301 redirect
     * them to their parent community/developer page to save SEO crawl budget.
     */
    public function redirect_404_units() {
        if ( ! is_404() ) return;

        $path   = isset( $_SERVER['REQUEST_URI'] ) ? parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) : '';
        $parts  = array_values( array_filter( explode( '/', $path ) ) );

        // If it's a 404 and the URL looks like /unit/buy/reem-hills-apartments/capitalave-3-90991-11/
        if ( isset( $parts[0] ) && $parts[0] === 'unit' && count( $parts ) >= 3 ) {
            // $parts[0] = 'unit'
            // $parts[1] = purpose (e.g., 'buy' or 'rent')
            // $parts[2] = project/community slug (e.g., 'reem-hills-apartments')
            
            $purpose_slug = sanitize_title( $parts[1] );
            $project_slug = sanitize_title( $parts[2] );
            
            // Determine the correct base page depending on whether it's for rent or sale
            $base_page = '/abu-dhabi-properties-for-sale';
            if ( $purpose_slug === 'rent' ) {
                $base_page = '/rent-properties-in-abu-dhabi';
            }
            
            // Check if this project taxonomy term actually exists
            $term = get_term_by( 'slug', $project_slug, 'tca_project' );
            if ( ! $term ) {
                // Fallback: If it's not a project, maybe it's a developer?
                $term = get_term_by( 'slug', $project_slug, 'tca_developer' );
            }
            
            if ( $term && ! is_wp_error( $term ) ) {
                // Redirect to the correct pre-filtered search page!
                $keyword = urlencode( $term->name );
                $redirect_url = user_trailingslashit( home_url( $base_page ) ) . '?keyword=' . $keyword . '&purpose=' . $purpose_slug;
                
                wp_redirect( $redirect_url, 301 );
                exit;
            }
            
            // Ultimate Fallback: If project not found, redirect to the general properties page for that purpose
            wp_redirect( user_trailingslashit( home_url( $base_page ) ), 301 );
            exit;
        }
    }

    /**
     * Enqueue WP Media library scripts and custom JS for Developer Logo.
     */
    public function tca_developer_admin_scripts( $hook ) {
        $screen = get_current_screen();
        if ( $screen && $screen->taxonomy === 'tca_developer' ) {
            wp_enqueue_media();
            $js = "
            jQuery(document).ready(function($){
                $('body').on('click', '.tca-tax-upload-logo-btn', function(e){
                    e.preventDefault();
                    var button = $(this);
                    var inputField = $('#tca_developer_logo');
                    var previewImg = $('#tca_developer_logo_preview');
                    var removeBtn = $('.tca-tax-remove-logo-btn');
                    var customUploader = wp.media({
                        title: 'Select Developer Logo',
                        button: { text: 'Use this logo' },
                        multiple: false
                    }).on('select', function() {
                        var attachment = customUploader.state().get('selection').first().toJSON();
                        inputField.val(attachment.url);
                        previewImg.attr('src', attachment.url).show();
                        removeBtn.show();
                    }).open();
                });

                $('body').on('click', '.tca-tax-remove-logo-btn', function(e){
                    e.preventDefault();
                    $('#tca_developer_logo').val('');
                    $('#tca_developer_logo_preview').attr('src', '').hide();
                    $(this).hide();
                });
            });
            ";
            wp_add_inline_script( 'media-upload', $js );
        }
    }

    /**
     * Render logo upload field on "Add New Developer" screen.
     */
    public function tca_developer_add_logo_field() {
        ?>
        <div class="form-field term-group">
            <label for="tca_developer_logo">Developer Logo</label>
            <input type="hidden" id="tca_developer_logo" name="tca_developer_logo" value="">
            <div style="margin-bottom: 10px;">
                <img id="tca_developer_logo_preview" src="" style="max-height:100px; max-width:200px; display:none; border:1px solid #ccc; padding:5px; background:#fff; border-radius:4px; object-fit:contain;">
            </div>
            <button class="button tca-tax-upload-logo-btn" type="button">Upload/Select Logo</button>
            <button class="button tca-tax-remove-logo-btn" type="button" style="display:none; color:#d63638; border-color:#d63638;">Remove Logo</button>
            <p>Select or upload a logo image for this developer. Recommended size: 200x100px (PNG or SVG format preferred).</p>
        </div>
        <div class="form-field term-group">
            <label for="tca_developer_custom_url">Custom Developer Page URL</label>
            <input type="url" id="tca_developer_custom_url" name="tca_developer_custom_url" value="" placeholder="https://thecapitalavenue.com/developers/saas-properties/">
            <p>Optional: Enter custom page URL for this developer (used for "View More Details" button). If left blank, default taxonomy archive is used.</p>
        </div>
        <?php
    }

    /**
     * Render logo upload field on "Edit Developer" screen.
     */
    public function tca_developer_edit_logo_field( $term, $taxonomy ) {
        $logo_url   = get_term_meta( $term->term_id, 'tca_developer_logo', true );
        $custom_url = get_term_meta( $term->term_id, 'tca_developer_custom_url', true );
        ?>
        <tr class="form-field term-group-wrap">
            <th scope="row"><label for="tca_developer_logo">Developer Logo</label></th>
            <td>
                <input type="hidden" id="tca_developer_logo" name="tca_developer_logo" value="<?php echo esc_attr( $logo_url ); ?>">
                <div style="margin-bottom: 10px;">
                    <img id="tca_developer_logo_preview" src="<?php echo esc_url( $logo_url ); ?>" style="max-height:100px; max-width:200px; <?php echo empty( $logo_url ) ? 'display:none;' : ''; ?> border:1px solid #ccc; padding:5px; background:#fff; border-radius:4px; object-fit:contain;">
                </div>
                <button class="button tca-tax-upload-logo-btn" type="button">Upload/Select Logo</button>
                <button class="button tca-tax-remove-logo-btn" type="button" style="<?php echo empty( $logo_url ) ? 'display:none;' : ''; ?> color:#d63638; border-color:#d63638;">Remove Logo</button>
                <p class="description">Select or upload a logo image for this developer. Recommended size: 200x100px (PNG or SVG format preferred).</p>
            </td>
        </tr>
        <tr class="form-field term-group-wrap">
            <th scope="row"><label for="tca_developer_custom_url">Custom Developer Page URL</label></th>
            <td>
                <input type="url" id="tca_developer_custom_url" name="tca_developer_custom_url" value="<?php echo esc_url( $custom_url ); ?>" placeholder="https://thecapitalavenue.com/developers/saas-properties/" style="width:100%;">
                <p class="description">Optional: Enter custom page URL for this developer (used for "View More Details" button). If left blank, default taxonomy archive is used.</p>
            </td>
        </tr>
        <?php
    }

    /**
     * Save the Developer Logo term meta.
     */
    public function tca_developer_save_logo( $term_id ) {
        if ( isset( $_POST['tca_developer_logo'] ) ) {
            update_term_meta( $term_id, 'tca_developer_logo', esc_url_raw( $_POST['tca_developer_logo'] ) );
        }
        if ( isset( $_POST['tca_developer_custom_url'] ) ) {
            update_term_meta( $term_id, 'tca_developer_custom_url', esc_url_raw( $_POST['tca_developer_custom_url'] ) );
        }
    }

    /**
     * Auto-filter all property queries (including Crafto Theme widgets) on Developer and Project pages.
     */
    public function auto_filter_developer_queries( $query ) {
        if ( is_admin() ) {
            return;
        }

        $post_type = $query->get( 'post_type' );
        if ( empty( $post_type ) ) {
            return;
        }

        // Target unit and Crafto property queries
        $is_target_cpt = false;
        if ( is_string( $post_type ) && ( $post_type === 'tca_unit' || $post_type === 'properties' ) ) {
            $is_target_cpt = true;
        } elseif ( is_array( $post_type ) && ( in_array( 'tca_unit', $post_type ) || in_array( 'properties', $post_type ) ) ) {
            $is_target_cpt = true;
        }

        if ( ! $is_target_cpt ) {
            return;
        }

        $queried_obj = get_queried_object();
        $current_id  = get_the_ID();

        // Check Developer Context
        $dev_slug = '';
        if ( $queried_obj && isset( $queried_obj->taxonomy ) && $queried_obj->taxonomy === 'tca_developer' ) {
            $dev_slug = $queried_obj->slug;
        } elseif ( $current_id ) {
            $dev_terms = get_the_terms( $current_id, 'tca_developer' );
            if ( ! empty( $dev_terms ) && ! is_wp_error( $dev_terms ) ) {
                $dev_slug = $dev_terms[0]->slug;
            } else {
                $page_slug  = get_post_field( 'post_name', $current_id );
                $page_title = strtolower( get_the_title( $current_id ) );
                
                // 1. Direct match
                $dev_term = get_term_by( 'slug', sanitize_title( $page_slug ), 'tca_developer' );
                if ( ! $dev_term || is_wp_error( $dev_term ) ) {
                    $dev_term = get_term_by( 'name', get_the_title( $current_id ), 'tca_developer' );
                }
                if ( $dev_term && ! is_wp_error( $dev_term ) ) {
                    $dev_slug = $dev_term->slug;
                } else {
                    // 2. Partial match (e.g. page slug "aldar-properties" or "aldar-developer" matches term "aldar")
                    $all_devs = get_terms( [ 'taxonomy' => 'tca_developer', 'hide_empty' => false ] );
                    if ( ! is_wp_error( $all_devs ) && ! empty( $all_devs ) ) {
                        foreach ( $all_devs as $dev ) {
                            $d_slug = $dev->slug;
                            $d_name = strtolower( $dev->name );
                            if ( ( ! empty( $d_slug ) && strpos( $page_slug, $d_slug ) !== false ) || 
                                 ( ! empty( $d_name ) && strpos( $page_title, $d_name ) !== false ) ) {
                                $dev_slug = $d_slug;
                                break;
                            }
                        }
                    }
                }
            }
        }

        if ( ! empty( $dev_slug ) ) {
            $tax_query = $query->get( 'tax_query' );
            if ( ! is_array( $tax_query ) ) {
                $tax_query = [];
            }
            
            $has_dev_filter = false;
            foreach ( $tax_query as $clause ) {
                if ( is_array( $clause ) && isset( $clause['taxonomy'] ) && $clause['taxonomy'] === 'tca_developer' ) {
                    $has_dev_filter = true;
                    break;
                }
            }

            if ( ! $has_dev_filter ) {
                $tax_query[] = [
                    'taxonomy' => 'tca_developer',
                    'field'    => 'slug',
                    'terms'    => $dev_slug,
                ];
                $query->set( 'tax_query', $tax_query );
            }
        }
    }

}

new TCA_RE_CPT_Taxonomies();
