<?php
/**
 * Handles AJAX requests for Advanced Live Search.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TCA_RE_Search_Ajax {

    public function __construct() {
        add_action( 'wp_ajax_tca_filter_units', [ $this, 'process_search' ] );
        add_action( 'wp_ajax_nopriv_tca_filter_units', [ $this, 'process_search' ] );

        add_action( 'wp_ajax_tca_autocomplete_search', [ $this, 'process_autocomplete' ] );
        add_action( 'wp_ajax_nopriv_tca_autocomplete_search', [ $this, 'process_autocomplete' ] );
    }
 
    public function process_autocomplete() {
        check_ajax_referer( 'tca_search_nonce', 'security' );

        $query = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
        $is_compact = isset( $_POST['is_compact'] ) && $_POST['is_compact'] == '1';
        $results = [];

        $taxonomies = $is_compact ? [ 'tca_project', 'tca_developer' ] : [ 'tca_location', 'tca_project', 'tca_developer' ];

        // Check if there are active developer or location filters on the page
        $active_developer = isset( $_POST['developer'] ) ? sanitize_text_field( wp_unslash( $_POST['developer'] ) ) : '';
        $active_location  = isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : '';

        // Eliminate "Developer" suggestion if we are already on a developer page
        if ( ! empty( $active_developer ) ) {
            $taxonomies = array_diff( $taxonomies, [ 'tca_developer' ] );
        }
        // Eliminate "Location" suggestion if we are already on a location page
        if ( ! empty( $active_location ) ) {
            $taxonomies = array_diff( $taxonomies, [ 'tca_location' ] );
        }

        // Find matching tca_unit posts under active location/developer filters
        $matching_post_ids = [];
        if ( ! empty( $active_developer ) || ! empty( $active_location ) ) {
            $post_args = [
                'post_type'      => 'tca_unit',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'tax_query'      => [ 'relation' => 'AND' ],
            ];
            if ( ! empty( $active_developer ) ) {
                $post_args['tax_query'][] = [
                    'taxonomy' => 'tca_developer',
                    'field'    => 'slug',
                    'terms'    => $active_developer,
                ];
            }
            if ( ! empty( $active_location ) ) {
                $post_args['tax_query'][] = [
                    'taxonomy' => 'tca_location',
                    'field'    => 'slug',
                    'terms'    => $active_location,
                ];
            }
            $matching_post_ids = get_posts( $post_args );
        }

        if ( empty( $query ) ) {
            // Default: popular/top locations (skip for compact filter as it doesn't search locations)
            if ( ! $is_compact && in_array( 'tca_location', $taxonomies ) ) {
                $terms_args = [
                    'taxonomy'   => 'tca_location',
                    'hide_empty' => true,
                    'number'     => 6,
                    'orderby'    => 'count',
                    'order'      => 'DESC'
                ];
                if ( ! empty( $active_developer ) || ! empty( $active_location ) ) {
                    if ( ! empty( $matching_post_ids ) ) {
                        $allowed_terms = wp_get_object_terms( $matching_post_ids, 'tca_location', [ 'fields' => 'ids' ] );
                        if ( ! empty( $allowed_terms ) && ! is_wp_error( $allowed_terms ) ) {
                            $terms_args['include'] = $allowed_terms;
                        } else {
                            $terms_args['include'] = [0]; // Force no results
                        }
                    } else {
                        $terms_args['include'] = [0];
                    }
                }
                $terms = get_terms( $terms_args );
                if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                    foreach ( $terms as $term ) {
                        $results[] = [
                            'label' => $term->name,
                            'value' => $term->name,
                            'type'  => 'Location'
                        ];
                    }
                }
            }
        } else {
            // Search all target taxonomies
            foreach ( $taxonomies as $tax ) {
                $terms_args = [
                    'taxonomy'   => $tax,
                    'hide_empty' => true,
                    'name__like' => $query,
                    'number'     => 5
                ];

                // Restrict suggestions to only terms associated with matching units
                if ( ! empty( $active_developer ) || ! empty( $active_location ) ) {
                    if ( ! empty( $matching_post_ids ) ) {
                        $allowed_terms = wp_get_object_terms( $matching_post_ids, $tax, [ 'fields' => 'ids' ] );
                        if ( ! empty( $allowed_terms ) && ! is_wp_error( $allowed_terms ) ) {
                            $terms_args['include'] = $allowed_terms;
                        } else {
                            $terms_args['include'] = [0]; // Force no results
                        }
                    } else {
                        continue;
                    }
                }

                $terms = get_terms( $terms_args );
                if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                    $type_label = '';
                    if ( $tax === 'tca_location' ) $type_label = 'Location';
                    if ( $tax === 'tca_project' ) $type_label = 'Project';
                    if ( $tax === 'tca_developer' ) $type_label = 'Developer';

                    foreach ( $terms as $term ) {
                        $results[] = [
                            'label' => $term->name,
                            'value' => $term->name,
                            'type'  => $type_label
                        ];
                    }
                }
            }
        }

        wp_send_json_success( $results );
    }

    public function process_search() {
        // Basic Security Check
        check_ajax_referer( 'tca_search_nonce', 'security' );

        $args = [
            'post_type'      => 'tca_unit',
            'posts_per_page' => isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 10,
            'paged'          => isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1,
            'post_status'    => 'publish',
            'tax_query'      => ['relation' => 'AND'],
            'meta_query'     => ['relation' => 'AND']
        ];

        // 1. Keyword search (Universal Match: Title, Location, Project, Developer)
        if ( ! empty( $_POST['keyword'] ) ) {
            $keyword = sanitize_text_field( wp_unslash( $_POST['keyword'] ) );
            
            // a. Find posts by Title matching keyword (exclude post description)
            global $wpdb;
            $keyword_like = '%' . $wpdb->esc_like( $keyword ) . '%';
            $ids_by_title = $wpdb->get_col( $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'tca_unit' AND post_status = 'publish' AND post_title LIKE %s",
                $keyword_like
            ) );
            if ( ! is_array( $ids_by_title ) ) {
                $ids_by_title = [];
            }

            // b. Find matching terms in key taxonomies
            $taxonomies = ['tca_location', 'tca_project', 'tca_developer', 'tca_property_type'];
            $term_ids = [];
            foreach ( $taxonomies as $tax ) {
                $found_terms = get_terms([
                    'taxonomy'   => $tax,
                    'name__like' => $keyword,
                    'fields'     => 'ids'
                ]);
                if ( ! is_wp_error( $found_terms ) && ! empty( $found_terms ) ) {
                    $term_ids[$tax] = $found_terms;
                }
            }

            // c. Find posts that have these matching terms
            $ids_by_tax = [];
            if ( ! empty( $term_ids ) ) {
                $tax_query = ['relation' => 'OR'];
                foreach ( $term_ids as $tax => $ids ) {
                    $tax_query[] = [
                        'taxonomy' => $tax,
                        'field'    => 'term_id',
                        'terms'    => $ids,
                    ];
                }
                $ids_by_tax = get_posts([
                    'post_type'      => 'tca_unit',
                    'tax_query'      => $tax_query,
                    'fields'         => 'ids',
                    'posts_per_page' => -1,
                    'post_status'    => 'publish'
                ]);
            }

            // d. Merge all found IDs
            $all_match_ids = array_unique( array_merge( $ids_by_title, $ids_by_tax ) );
            
            if ( ! empty( $all_match_ids ) ) {
                $args['post__in'] = $all_match_ids;
            } else {
                $args['post__in'] = [0]; // Force no results if absolutely nothing matches
            }
        }

        // 2. Taxonomy Filtering
        $tax_map = [
            'purpose'       => 'tca_purpose',
            'property_type' => 'tca_property_type',
            'location'      => 'tca_location',
            'project'       => 'tca_project',
            'developer'     => 'tca_developer',
            'amenity'       => 'tca_amenity',
        ];

        foreach ( $tax_map as $post_key => $tax_name ) {
            if ( ! empty( $_POST[ $post_key ] ) ) {
                $args['tax_query'][] = [
                    'taxonomy' => $tax_name,
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) ),
                ];
            }
        }

        // Status is always stored as post meta, NOT a taxonomy
        if ( ! empty( $_POST['status'] ) ) {
            $args['meta_query'][] = [
                'key'     => '_tca_project_status',
                'value'   => sanitize_text_field( wp_unslash( $_POST['status'] ) ),
                'compare' => '='
            ];
        }
 
        // 3. Mathematical Meta Queries
        // Area: unified range dropdown splits "min-max"
        if ( ! empty( $_POST['area'] ) ) {
            $area_parts = explode('-', sanitize_text_field($_POST['area']));
            if ( count($area_parts) === 2 ) {
                $area_min = intval($area_parts[0]);
                $area_max = intval($area_parts[1]);
                if ( $area_min > 0 ) {
                    $args['meta_query'][] = [ 'key' => '_tca_area', 'value' => $area_min, 'compare' => '>=', 'type' => 'NUMERIC' ];
                }
                if ( $area_max > 0 ) {
                    $args['meta_query'][] = [ 'key' => '_tca_area', 'value' => $area_max, 'compare' => '<=', 'type' => 'NUMERIC' ];
                }
            }
        }
        // Legacy sqft support (fallback if both still sent)
        if ( ! empty( $_POST['min_sqft'] ) ) {
            $args['meta_query'][] = [ 'key' => '_tca_area', 'value' => intval( $_POST['min_sqft'] ), 'compare' => '>=' ];
        }
        // Bedrooms
        if ( isset($_POST['bedrooms']) && $_POST['bedrooms'] !== '' ) {
            $beds_val = sanitize_text_field( wp_unslash( $_POST['bedrooms'] ) );
            if ( $beds_val === '0' ) {
                $args['meta_query'][] = [ 'key' => '_tca_bedrooms', 'value' => 0, 'compare' => '=' ];
            } elseif ( $beds_val === '7+' ) {
                $args['meta_query'][] = [ 'key' => '_tca_bedrooms', 'value' => 7, 'compare' => '>=' ];
            } else {
                $args['meta_query'][] = [ 'key' => '_tca_bedrooms', 'value' => intval($beds_val), 'compare' => '=' ];
            }
        } elseif ( ! empty( $_POST['min_beds'] ) || ! empty( $_POST['max_beds'] ) ) {
            if ( ! empty( $_POST['min_beds'] ) ) {
                $args['meta_query'][] = [ 'key' => '_tca_bedrooms', 'value' => intval( $_POST['min_beds'] ), 'compare' => '>=' ];
            }
            if ( ! empty( $_POST['max_beds'] ) ) {
                $args['meta_query'][] = [ 'key' => '_tca_bedrooms', 'value' => intval( $_POST['max_beds'] ), 'compare' => '<=' ];
            }
        }

        // Bathrooms
        if ( isset($_POST['bathrooms']) && $_POST['bathrooms'] !== '' ) {
            $baths_val = sanitize_text_field( wp_unslash( $_POST['bathrooms'] ) );
            if ( strpos($baths_val, '+') !== false ) {
                $args['meta_query'][] = [ 'key' => '_tca_bathrooms', 'value' => intval($baths_val), 'compare' => '>=' ];
            } else {
                $args['meta_query'][] = [ 'key' => '_tca_bathrooms', 'value' => intval($baths_val), 'compare' => '=' ];
            }
        }

        if ( ! empty( $_POST['min_price'] ) ) {
            $args['meta_query'][] = [ 'key' => '_tca_price', 'value' => intval( $_POST['min_price'] ), 'compare' => '>=', 'type' => 'NUMERIC' ];
        }
        if ( ! empty( $_POST['max_price'] ) ) {
            $args['meta_query'][] = [ 'key' => '_tca_price', 'value' => intval( $_POST['max_price'] ), 'compare' => '<=', 'type' => 'NUMERIC' ];
        }

        // Sort By (Featured always first, then user-chosen sort)
        $sort_by = ! empty( $_POST['sort_by'] ) ? sanitize_text_field( $_POST['sort_by'] ) : 'date_desc';
        switch ( $sort_by ) {
            case 'date_asc':   $args['orderby'] = 'date'; $args['order'] = 'ASC'; break;
            case 'price_asc':  $args['orderby'] = 'meta_value_num'; $args['meta_key'] = '_tca_price'; $args['order'] = 'ASC'; break;
            case 'price_desc': $args['orderby'] = 'meta_value_num'; $args['meta_key'] = '_tca_price'; $args['order'] = 'DESC'; break;
            default:           $args['orderby'] = 'date'; $args['order'] = 'DESC'; break;
        }
        // Inject featured-first via SQL hook ONLY if the user is not searching/filtering
        $is_searching = ! empty( $_POST['keyword'] ) || 
                        ! empty( $_POST['property_type'] ) || 
                        ! empty( $_POST['status'] ) || 
                        ! empty( $_POST['min_beds'] ) || 
                        ! empty( $_POST['max_beds'] ) || 
                        ! empty( $_POST['bedrooms'] ) || 
                        ! empty( $_POST['bathrooms'] ) || 
                        ! empty( $_POST['min_price'] ) || 
                        ! empty( $_POST['max_price'] ) || 
                        ! empty( $_POST['area'] ) || 
                        ! empty( $_POST['sort_by'] );

        if ( ! $is_searching ) {
            add_filter( 'posts_orderby', function( $orderby, $query ) {
                global $wpdb;
                $featured_clause = "( SELECT COALESCE(meta_value,'0') FROM {$wpdb->postmeta} pm WHERE pm.post_id = {$wpdb->posts}.ID AND pm.meta_key = '_tca_featured' LIMIT 1 ) DESC";
                return $featured_clause . ', ' . $orderby;
            }, 10, 2 );
        }
        // Lazy Enforcement: Hide expired Madhmoun Permitted properties unconditionally
        $args['meta_query'][] = [
            'relation' => 'OR',
            [ 'key' => '_tca_permit_expiry', 'compare' => 'NOT EXISTS' ],
            [ 'key' => '_tca_permit_expiry', 'value'   => '', 'compare' => '=' ],
            [ 'key' => '_tca_permit_expiry', 'value'   => current_time('Y-m-d'), 'compare' => '>=', 'type' => 'DATE' ]
        ];

        $query = new WP_Query( $args );

        ob_start();

        if ( $query->have_posts() ) {
            $layout = isset($_POST['layout']) ? sanitize_text_field($_POST['layout']) : 'grid';
            $layout_class = ( $layout === 'landscape' ) ? 'tca-layout-landscape' : 'tca-layout-grid';
            
            // Output grid container logic matching class-shortcodes
            $returned_limit = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : $args['posts_per_page'];
            
            // Keep context attributes so subsequent AJAX searches don't lose the "base" filters
            $base_data = '';
            $context_keys = ['purpose', 'location', 'developer', 'project', 'property_type', 'amenity', 'status', 'bedrooms', 'bathrooms'];
            foreach ( $context_keys as $ckey ) {
                if ( ! empty( $_POST[ $ckey ] ) ) {
                    $base_data .= ' data-base-' . $ckey . '="' . esc_attr( $_POST[ $ckey ] ) . '"';
                }
            }

            echo '<div id="tca-results-wrapper" data-limit="' . $returned_limit . '"' . $base_data . '>';
            echo '<div class="tca-re-grid ' . esc_attr($layout_class) . '">';
            while ( $query->have_posts() ) {
                $query->the_post();
                $template_name = ($layout === 'landscape') ? 'unit-card-landscape.php' : 'unit-card-grid.php';
                $template_path = locate_template( 'tca-real-estate/' . $template_name );
                if ( ! $template_path ) {
                    $template_path = TCA_RE_PLUGIN_DIR . 'templates/' . $template_name;
                }
                if ( file_exists( $template_path ) ) {
                    include $template_path;
                }
            }
            echo '</div>'; // End grid

            // Pagination Logic
            if ( $query->max_num_pages > 1 ) {
                echo '<div class="tca-pagination">';
                
                $current_page = max( 1, $args['paged'] );
                $total_pages = $query->max_num_pages;
                
                if ( $current_page > 1 ) {
                    echo '<a href="#" class="tca-page-link tca-page-prev" data-page="' . ($current_page - 1) . '"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg></a>';
                }

                // Intelligent Page Numbers
                $range = 1; 
                $show_pages = [];
                for ($i = 1; $i <= $total_pages; $i++) {
                    if ($i == 1 || $i == $total_pages || ($i >= $current_page - $range && $i <= $current_page + $range)) {
                        $show_pages[] = $i;
                    }
                }

                $last_p = 0;
                foreach ($show_pages as $p) {
                    if ($last_p > 0 && $p - $last_p > 1) {
                        echo '<span class="tca-pagination-dots">...</span>';
                    }
                    $is_active = ( $p == $current_page ) ? 'active' : '';
                    $display_num = str_pad($p, 2, '0', STR_PAD_LEFT);
                    echo '<a href="#" class="tca-page-link ' . $is_active . '" data-page="' . $p . '">' . $display_num . '</a>';
                    $last_p = $p;
                }

                if ( $current_page < $total_pages ) {
                    echo '<a href="#" class="tca-page-link tca-page-next" data-page="' . ($current_page + 1) . '"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></a>';
                }
                
                echo '</div>';
            }

            echo '</div>'; // End #tca-results-wrapper
            wp_reset_postdata();
        } else {
            $returned_limit = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : $args['posts_per_page'];
            $base_data = '';
            $context_keys = ['purpose', 'location', 'developer', 'project', 'property_type', 'amenity', 'status', 'bedrooms', 'bathrooms'];
            foreach ( $context_keys as $ckey ) {
                if ( ! empty( $_POST[ $ckey ] ) ) {
                    $base_data .= ' data-base-' . $ckey . '="' . esc_attr( $_POST[ $ckey ] ) . '"';
                }
            }
            echo '<div id="tca-results-wrapper" data-limit="' . $returned_limit . '"' . $base_data . '>';
            echo '<div class="tca-re-no-results"><h3>No Properties Found</h3><p>Try adjusting your search criteria to find what you are looking for.</p><button type="button" class="tca-reset-search-btn tca-search-btn" style="margin-top: 15px; width: auto; padding: 10px 24px; display: inline-block;">Reset Search</button></div>';
            echo '</div>';
        }

        $html = ob_get_clean();

        wp_send_json_success( [ 'html' => $html ] );
    }
}

new TCA_RE_Search_Ajax();
