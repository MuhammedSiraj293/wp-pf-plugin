<?php
/**
 * Core Sync Engine for Property Finder API
 */

if (!defined('ABSPATH')) {
    exit;
}

class TCA_RE_PF_Sync_Engine
{

    private $connector;
    private $is_sandbox;

    public function __construct(TCA_RE_PF_Connector $connector)
    {
        $this->connector = $connector;
        $this->is_sandbox = (get_option('tca_pf_sandbox_mode') == '1');
    }

    /**
     * Helper to download images safely without duplicates.
     */
    private function download_image($url, $post_id)
    {
        if (!function_exists('media_sideload_image')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }

        // Fix: Strip query strings (?v=...) to prevent duplicates when PF updates version tokens
        $clean_url = strtok($url, '?');

        // Check if we already downloaded this exact base URL
        $existing = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'any',
            'meta_key' => '_tca_original_pf_url',
            'meta_value' => $clean_url,
            'posts_per_page' => 1,
            'fields' => 'ids'
        ]);

        if (!empty($existing)) {
            return wp_get_attachment_url($existing[0]);
        }

        // Download the image and return attachment ID
        $attach_id = media_sideload_image($url, $post_id, null, 'id');

        if (is_wp_error($attach_id)) {
            return false;
        }

        // Tag it with the CLEAN URL so we don't download it again
        update_post_meta($attach_id, '_tca_original_pf_url', $clean_url);
        return wp_get_attachment_url($attach_id);
    }

    public function get_sample_payload()
    {
        $token = $this->connector->get_access_token();

        if (is_wp_error($token)) {
            return new WP_Error('auth_error', 'Sync failed: ' . $token->get_error_message());
        }

        $base_url = $this->is_sandbox
            ? 'https://sandbox.atlas.propertyfinder.com/v1'
            : 'https://atlas.propertyfinder.com/v1';

        $response = wp_remote_get($base_url . '/listings', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('api_error', 'Failed to reach PF API: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if (empty($json)) {
            return new WP_Error('empty_response', 'Raw response: ' . esc_html($body));
        }

        return $json;
    }

    /**
     * Executes the main sync process.
     * @return array Status and message.
     */
    public function run_manual_sync()
    {
        $token = $this->connector->get_access_token();

        if (is_wp_error($token)) {
            return [
                'success' => false,
                'message' => 'API Connection failed: ' . $token->get_error_message()
            ];
        }

        // Track progress using WP Options
        $state = get_option('tca_pf_manual_sync_state', ['page' => 1, 'offset' => 0]);
        $page = isset($state['page']) ? (int) $state['page'] : 1;
        $offset = isset($state['offset']) ? (int) $state['offset'] : 0;

        $endpoint = $this->is_sandbox
            ? 'https://sandbox.atlas.propertyfinder.com/v1/listings'
            : 'https://atlas.propertyfinder.com/v1/listings';

        $url = add_query_arg([
            'page' => $page,
            'perPage' => 50,
            'sort' => '-createdAt' // Always get newest listings first
        ], $endpoint);

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ],
            'timeout' => 45 // Wait up to 45 seconds for images
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Failed to reach PF API: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if ($status_code !== 200) {
            $error_msg = isset($json['message']) ? $json['message'] : $body;
            return [
                'success' => false,
                'message' => "API Error ({$status_code}): " . esc_html($error_msg)
            ];
        }

        $listings = isset($json['results']) ? $json['results'] : [];
        $total_pages = isset($json['pagination']['totalPages']) ? $json['pagination']['totalPages'] : 1;

        if (empty($listings)) {
            // Reset state if empty
            update_option('tca_pf_manual_sync_state', ['page' => 1, 'offset' => 0]);
            return [
                'success' => true,
                'message' => 'Sync completed. 0 properties found.'
            ];
        }

        // Speed Boost: Process 10 properties per batch (up from 5)
        $batch = array_slice($listings, $offset, 10);

        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0
        ];

        // 2. Process Listings
        foreach ($batch as $data) {
            $status = $this->process_listing($data);
            if ($status === 'created') {
                $stats['created']++;
            } elseif ($status === 'updated') {
                $stats['updated']++;
            }
        }

        // Calculate next run's state
        $next_offset = $offset + 10;
        $next_page = $page;
        $is_finished = false;

        if ($next_offset >= count($listings)) {
            $next_offset = 0;
            $next_page++;
            if ($next_page > $total_pages) {
                $next_page = 1;
                $is_finished = true;
            }
        }

        // Save progress for the next click
        update_option('tca_pf_manual_sync_state', ['page' => $next_page, 'offset' => $next_offset]);

        $msg = "Success! Created: {$stats['created']}, Updated: {$stats['updated']}. ";
        if ($is_finished) {
            $msg .= "✅ ALL PROPERTIES IMPORTED! Restarting from Page 1 next time.";
        } else {
            $msg .= "⌛ Progress saved (Page $page, Item $next_offset/50). Click button again to fetch the next batch!";
        }

        $total_items = isset($json['pagination']['total']) ? (int) $json['pagination']['total'] : 0;
        $current_item = (($page - 1) * 50) + $next_offset;

        // If we are at the end, set current to total
        if ($is_finished) {
            $current_item = $total_items;
        }

        return [
            'success' => true,
            'finished' => $is_finished,
            'total' => $total_items,
            'current' => $current_item,
            'message' => $msg
        ];
    }

    /**
     * Processes a single listing payload from PF.
     */
    private function process_listing($data)
    {
        // Find reference (PF uses various keys, commonly 'reference' or 'propertyId')
        $reference = isset($data['reference']) ? sanitize_text_field($data['reference']) : '';
        if (empty($reference)) {
            return 'skipped'; // Cannot process without a reference
        }

        // Search for existing property by Base Reference (stripping version suffix like -3, -15)
        // CRITICAL FIX: Only strip 1 to 3 digits. This prevents accidentally stripping the main 5-digit ID (e.g. Capitalave-92667)
        $base_ref = preg_replace('/-\d{1,3}$/', '', $reference);

        global $wpdb;

        // High-Performance Indexed Lookup: Generate all possible suffix versions (1 to 99)
        // This avoids full-table scans of wp_postmeta using LIKE, which causes server timeouts.
        $possible_refs = [$base_ref, $reference];
        for ($i = 1; $i <= 99; $i++) {
            $possible_refs[] = $base_ref . '-' . $i;
        }

        // Generate placeholders for the SQL IN statement
        $placeholders = array_fill(0, count($possible_refs), '%s');
        $format = implode(', ', $placeholders);

        // Direct SQL Query: Bypasses all WordPress filters (WPML, Polylang) to guarantee finding drafts and prevent duplicates
        $query = $wpdb->prepare(
            "SELECT {$wpdb->postmeta}.post_id 
             FROM {$wpdb->postmeta} 
             INNER JOIN {$wpdb->posts} ON {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id
             WHERE {$wpdb->postmeta}.meta_key = '_tca_reference' 
             AND {$wpdb->postmeta}.meta_value IN ($format)
             AND {$wpdb->posts}.post_status IN ('publish', 'draft', 'pending', 'private', 'future')
             LIMIT 1",
            ...$possible_refs
        );

        $post_id = (int) $wpdb->get_var($query);
        $is_new = ($post_id === 0);

        // Map PF Title (usually multi-lingual array or string)
        $title = isset($data['title']['en']) ? $data['title']['en'] : (is_string($data['title']) ? $data['title'] : 'Imported Property ' . $reference);
        $desc = isset($data['description']['en']) ? $data['description']['en'] : (is_string($data['description']) ? $data['description'] : '');

        // 3. Post Data
        $post_data = [
            'post_title' => wp_strip_all_tags($title),
            'post_content' => wp_kses_post($desc),
            'post_type' => 'tca_unit',
        ];

        // Parse createdAt for proper sorting on the frontend
        if (isset($data['createdAt'])) {
            $created_time = strtotime($data['createdAt']);
            if ($created_time) {
                $post_data['post_date'] = gmdate('Y-m-d H:i:s', $created_time + (get_option('gmt_offset') * HOUR_IN_SECONDS));
                $post_data['post_date_gmt'] = gmdate('Y-m-d H:i:s', $created_time);
            }
        }

        if ( $is_new ) {
            $post_data['post_status'] = 'draft'; // Always Draft for new imports
            $post_id = wp_insert_post( $post_data );
        } else {
            // EXPRESS SYNC: For existing properties, we ONLY update Price and Verification Status
            // We skip updating title/content/images to make the sync 50x faster and prevent bloat.
            $this->update_express_details($post_id, $data);
            return 'updated'; 
        }

        if (is_wp_error($post_id)) {
            return 'skipped';
        }

        // 4. Meta Data Mapping (Option A: Update fields)
        update_post_meta($post_id, '_tca_reference', $reference);

        // Map Price (Nested in price -> amounts -> sale or yearly/monthly etc.)
        if (isset($data['price']['amounts']['sale'])) {
            update_post_meta($post_id, '_tca_price', sanitize_text_field($data['price']['amounts']['sale']));
        } else {
            $rental_periods = ['yearly', 'monthly', 'weekly', 'daily'];
            foreach ($rental_periods as $period) {
                if (isset($data['price']['amounts'][$period])) {
                    update_post_meta($post_id, '_tca_price', sanitize_text_field($data['price']['amounts'][$period]));
                    break; // Stop after finding the first valid price
                }
            }
        }

        if (isset($data['bedrooms'])) {
            update_post_meta($post_id, '_tca_bedrooms', sanitize_text_field($data['bedrooms']));
        }

        if (isset($data['bathrooms'])) {
            update_post_meta($post_id, '_tca_bathrooms', sanitize_text_field($data['bathrooms']));
        }

        if (isset($data['size'])) {
            update_post_meta($post_id, '_tca_area', sanitize_text_field($data['size']));
        }

        // Map Project Status (off_plan or ready)
        if (isset($data['projectStatus'])) {
            $status = ($data['projectStatus'] === 'off_plan') ? 'off_plan' : 'ready';
            update_post_meta($post_id, '_tca_project_status', $status);
        }

        // Map Verified Status
        $is_verified = (isset($data['qualityScore']['details']['verified']['tag']) && $data['qualityScore']['details']['verified']['tag'] === 'Yes') ? '1' : '0';
        update_post_meta($post_id, '_tca_verified', $is_verified);

        // Map Featured Status (Checking if it has a premium or featured product active)
        $is_featured = (isset($data['products']['premium']) || isset($data['products']['featured'])) ? '1' : '0';
        update_post_meta($post_id, '_tca_featured', $is_featured);

        // Property Category & Emirate
        if (isset($data['category'])) {
            update_post_meta($post_id, '_tca_category', sanitize_text_field($data['category']));
        }
        if (isset($data['uaeEmirate'])) {
            update_post_meta($post_id, '_tca_emirate', sanitize_text_field($data['uaeEmirate']));
        }

        // Agent Data
        if (isset($data['assignedTo']['name'])) {
            update_post_meta($post_id, '_tca_agent_name', sanitize_text_field($data['assignedTo']['name']));
        }
        if (isset($data['assignedTo']['photos']['thumbnail'])) {
            update_post_meta($post_id, '_tca_agent_avatar', esc_url_raw($data['assignedTo']['photos']['thumbnail']));
        }

        // Permit Number (Madhmoun)
        if (isset($data['compliance']['listingAdvertisementNumber'])) {
            update_post_meta($post_id, '_tca_permit_number', sanitize_text_field($data['compliance']['listingAdvertisementNumber']));
        }

        // --- Taxonomies ---

        // Amenities
        if (isset($data['amenities']) && is_array($data['amenities'])) {
            $amenity_names = array_map('sanitize_text_field', $data['amenities']);
            wp_set_object_terms($post_id, $amenity_names, 'tca_amenity', false);
        }

        // Purpose (Sale or Rent) -> Mapping to "Buy" or "Rent"
        if (isset($data['price']['type'])) {
            $raw_type = strtolower(sanitize_text_field($data['price']['type']));
            $purpose_mapped = ($raw_type === 'sale') ? 'Buy' : 'Rent';
            wp_set_object_terms($post_id, $purpose_mapped, 'tca_purpose', false);
        }

        // Type (Villa, Apartment, etc) -> maps to tca_property_type taxonomy
        if ( isset($data['type']) ) {
            wp_set_object_terms( $post_id, sanitize_text_field($data['type']), 'tca_property_type', false );
        }

        // --- Map Media (Download Locally) ---
        // Fix: Image[0] = Featured Thumbnail ONLY. Gallery = images[1..9] (no duplicates).
        if ( isset($data['media']['images']) && is_array($data['media']['images']) ) {
            $local_image_urls = [];
            $img_count = 0;
            $featured_set = false;

            foreach ( $data['media']['images'] as $index => $img ) {
                if ( isset($img['original']['url']) ) {
                    $remote_url = esc_url_raw($img['original']['url']);
                    $local_url = $this->download_image( $remote_url, $post_id );

                    if ( $local_url ) {
                        if ( $index === 0 ) {
                            // First image -> Featured Thumbnail ONLY
                            $attachment_id = attachment_url_to_postid( $local_url );
                            if ( $attachment_id ) {
                                set_post_thumbnail( $post_id, $attachment_id );
                                $featured_set = true;
                            }
                        } else {
                            // All other images -> Gallery only (max 9 = 10 total with featured)
                            $local_image_urls[] = $local_url;
                            $img_count++;
                            if ( $img_count >= 9 ) break;
                        }
                    }
                }
            }
            if ( !empty($local_image_urls) ) {
                update_post_meta( $post_id, '_tca_gallery', implode(',', $local_image_urls) );
            }
        }

        return $is_new ? 'created' : 'updated';
    }

    /**
     * Express Sync: Only updates critical changing fields for existing properties.
     */
    private function update_express_details($post_id, $data) {
        global $wpdb;

        // 0. Update Reference to latest version suffix (e.g. -15) so it matches the portal exactly
        $reference = isset($data['reference']) ? sanitize_text_field($data['reference']) : '';
        if (!empty($reference)) {
            update_post_meta($post_id, '_tca_reference', $reference);
            
            // CRITICAL FIX: Also update the actual post_name (slug) in the database.
            // If the reference changes from -1 to -3, the URL changes. We must update the slug so the new URL doesn't return a 404.
            $new_slug = sanitize_title($reference);
            $wpdb->update(
                $wpdb->posts,
                ['post_name' => $new_slug],
                ['ID' => $post_id]
            );
        }

        // 0b. Update Post Dates to match the new republication/created date
        if (isset($data['createdAt'])) {
            $created_time = strtotime($data['createdAt']);
            if ($created_time) {
                $post_date = gmdate('Y-m-d H:i:s', $created_time + (get_option('gmt_offset') * HOUR_IN_SECONDS));
                $post_date_gmt = gmdate('Y-m-d H:i:s', $created_time);
                
                $wpdb->update(
                    $wpdb->posts,
                    [
                        'post_date' => $post_date,
                        'post_date_gmt' => $post_date_gmt
                    ],
                    ['ID' => $post_id]
                );
                clean_post_cache($post_id);
            }
        }

        // 1. Update Price
        if (isset($data['price']['amounts']['sale'])) {
            update_post_meta($post_id, '_tca_price', sanitize_text_field($data['price']['amounts']['sale']));
        } else {
            $rental_periods = ['yearly', 'monthly', 'weekly', 'daily'];
            foreach ($rental_periods as $period) {
                if (isset($data['price']['amounts'][$period])) {
                    update_post_meta($post_id, '_tca_price', sanitize_text_field($data['price']['amounts'][$period]));
                    break;
                }
            }
        }

        // 2. Update Verification Status
        $is_verified = (isset($data['qualityScore']['details']['verified']['tag']) && $data['qualityScore']['details']['verified']['tag'] === 'Yes') ? '1' : '0';
        update_post_meta($post_id, '_tca_verified', $is_verified);

        // 3. Update Featured Status
        $is_featured = (isset($data['products']['premium']) || isset($data['products']['featured'])) ? '1' : '0';
        update_post_meta($post_id, '_tca_featured', $is_featured);
        
        // 4. Update Permit Number (if changed)
        if (isset($data['compliance']['listingAdvertisementNumber'])) {
            update_post_meta($post_id, '_tca_permit_number', sanitize_text_field($data['compliance']['listingAdvertisementNumber']));
        }
    }
}
