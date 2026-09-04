<?php
/**
 * Registers exactly one consolidated Meta Box containing all the single unit details metrics.
 */

if (!defined('ABSPATH')) {
    exit;
}

class TCA_RE_Meta_Boxes
{

    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'add_real_estate_meta_boxes']);
        add_action('save_post', [$this, 'save_real_estate_meta']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_media_uploader']);
        add_action('wp_ajax_tca_get_project_data', [$this, 'ajax_get_project_data']);
    }


    public function enqueue_media_uploader()
    {
        wp_enqueue_media();
        // Inline JS to handle the gallery button
        $js = "
        jQuery(document).ready(function($){
            $('body').on('click', '.tca-gallery-add-btn', function(e){
                e.preventDefault();
                var button = $(this);
                var inputField = button.siblings('.tca-gallery-input');
                var customUploader = wp.media({
                    title: 'Select Images',
                    button: { text: 'Use these images' },
                    multiple: true
                }).on('select', function() {
                    var selection = customUploader.state().get('selection');
                    var attachmentUrls = [];
                    selection.map(function(attachment) {
                        attachmentUrls.push(attachment.toJSON().url);
                    });
                    var existing = inputField.val();
                    if(existing) {
                        inputField.val(existing + ',' + attachmentUrls.join(','));
                    } else {
                        inputField.val(attachmentUrls.join(','));
                    }
                }).open();
            });
        });
        ";
        wp_add_inline_script('media-upload', $js);

        // Inline JS for auto-filling unit details based on Project selection
        $autofill_js = "
        jQuery(document).ready(function($){
            if (typeof ajaxurl === 'undefined') {
                return;
            }

            var isGutenberg = typeof wp !== 'undefined' && typeof wp.data !== 'undefined' && typeof wp.data.subscribe !== 'undefined';
            var lastProjects = [];

            function fetchProjectData(projectTermId) {
                var currentPostId = $('#post_ID').val();
                if (isGutenberg && !currentPostId) {
                    currentPostId = wp.data.select('core/editor').getCurrentPostId();
                }
                currentPostId = currentPostId || 0;
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'tca_get_project_data',
                        project_id: projectTermId,
                        post_id: currentPostId
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            var data = response.data;
                            
                            // Fill Meta Fields (Standard DOM Inputs)
                            if (data.payment_plan && !$('#_tca_payment_plan').val()) {
                                $('#_tca_payment_plan').val(data.payment_plan);
                            }
                            if (data.completion_date && !$('#_tca_completion_date').val()) {
                                $('#_tca_completion_date').val(data.completion_date);
                            }
                            if (data.project_number && !$('#_tca_project_number').val()) {
                                $('#_tca_project_number').val(data.project_number);
                            }

                            if (isGutenberg) {
                                // Check Developer taxonomies via Gutenberg Data Store
                                if (data.developers && data.developers.length > 0) {
                                    var currentDevs = wp.data.select('core/editor').getEditedPostAttribute('tca_developer') || [];
                                    var newDevs = Array.from(new Set(currentDevs.concat(data.developers)));
                                    wp.data.dispatch('core/editor').editPost({ tca_developer: newDevs });
                                }
                                // Check Location taxonomies via Gutenberg Data Store
                                if (data.locations && data.locations.length > 0) {
                                    var currentLocs = wp.data.select('core/editor').getEditedPostAttribute('tca_location') || [];
                                    var newLocs = Array.from(new Set(currentLocs.concat(data.locations)));
                                    wp.data.dispatch('core/editor').editPost({ tca_location: newLocs });
                                }
                            } else {
                                // Classic Editor
                                if (data.developers && data.developers.length > 0) {
                                    $.each(data.developers, function(index, term_id) {
                                        $('#taxonomy-tca_developer input[value=\"' + term_id + '\"]').prop('checked', true);
                                    });
                                }
                                if (data.locations && data.locations.length > 0) {
                                    $.each(data.locations, function(index, term_id) {
                                        $('#taxonomy-tca_location input[value=\"' + term_id + '\"]').prop('checked', true);
                                    });
                                }
                            }
                            console.log('TCA Autofill Success:', data);
                        } else {
                            console.log('TCA Autofill:', response.data || 'Failed to fetch data');
                        }
                    },
                    error: function(err) {
                        console.error('TCA Autofill Error:', err);
                    }
                });
            }

            if (isGutenberg) {
                // Initialize lastProjects once the editor loads
                var initProjectsInterval = setInterval(function() {
                    var currentProjects = wp.data.select('core/editor').getEditedPostAttribute('tca_project');
                    if (typeof currentProjects !== 'undefined') {
                        lastProjects = currentProjects || [];
                        clearInterval(initProjectsInterval);
                        
                        // Subscribe to changes
                        wp.data.subscribe(function() {
                            var newProjects = wp.data.select('core/editor').getEditedPostAttribute('tca_project');
                            if (newProjects && Array.isArray(newProjects)) {
                                var addedProjects = newProjects.filter(function(x) { return lastProjects.indexOf(x) < 0; });
                                if (addedProjects.length > 0) {
                                    // A new project was checked
                                    fetchProjectData(addedProjects[0]);
                                }
                                lastProjects = newProjects;
                            }
                        });
                    }
                }, 1000);
            } else {
                // Classic Editor Fallback
                $('#taxonomy-tca_project').on('change', 'input[type=\"checkbox\"]', function() {
                    var checkbox = $(this);
                    if (checkbox.is(':checked')) {
                        fetchProjectData(checkbox.val());
                    }
                });
            }
        });
        ";
        wp_add_inline_script('media-upload', $autofill_js);
    }

    public function ajax_get_project_data() {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Unauthorized');
        }

        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $post_id_exclude = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$project_id) {
            wp_send_json_error('No project ID');
        }

        // Query the most recently published unit with this project (excluding current)
        $args = [
            'post_type' => 'tca_unit',
            'posts_per_page' => 1,
            'post_status' => 'publish',
            'post__not_in' => $post_id_exclude ? [$post_id_exclude] : [],
            'tax_query' => [
                [
                    'taxonomy' => 'tca_project',
                    'field' => 'term_id',
                    'terms' => $project_id
                ]
            ],
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            $post = $query->posts[0];
            $post_id = $post->ID;

            // Meta fields
            $payment_plan = get_post_meta($post_id, '_tca_payment_plan', true);
            $completion_date = get_post_meta($post_id, '_tca_completion_date', true);
            $project_number = get_post_meta($post_id, '_tca_project_number', true);

            // Taxonomy fields (Developers, Locations)
            $developers = wp_get_post_terms($post_id, 'tca_developer', ['fields' => 'ids']);
            $locations = wp_get_post_terms($post_id, 'tca_location', ['fields' => 'ids']);

            wp_send_json_success([
                'payment_plan' => $payment_plan,
                'completion_date' => $completion_date,
                'project_number' => $project_number,
                'developers' => is_array($developers) ? $developers : [],
                'locations' => is_array($locations) ? $locations : []
            ]);
        } else {
            wp_send_json_error('No units found for this project');
        }
    }

    public function add_real_estate_meta_boxes()
    {
        add_meta_box(
            'tca_unit_details',
            'Unit Details & Specs',
            [$this, 'render_meta_box'],
            'tca_unit',
            'normal',
            'high'
        );
    }

    public function render_meta_box($post)
    {
        // Add a nonce field so we can check for it later.
        wp_nonce_field('tca_unit_meta_save', 'tca_unit_meta_nonce');

        $fields = $this->get_fields();

        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">';
        foreach ($fields as $id => $field) {
            $value = get_post_meta($post->ID, $id, true);
            echo '<div class="tca-meta-field">';
            echo '<label for="' . esc_attr($id) . '"><strong>' . esc_html($field['label']) . '</strong></label><br>';

            if ($field['type'] === 'text' || $field['type'] === 'number' || $field['type'] === 'date') {
                echo '<input type="' . esc_attr($field['type']) . '" id="' . esc_attr($id) . '" name="' . esc_attr($id) . '" value="' . esc_attr($value) . '" style="width: 100%;">';
            } elseif ($field['type'] === 'select') {
                echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($id) . '" style="width: 100%;">';
                echo '<option value="">-- Select --</option>';
                foreach ($field['options'] as $opt_val => $opt_label) {
                    $selected = selected($value, $opt_val, false);
                    echo '<option value="' . esc_attr($opt_val) . '" ' . $selected . '>' . esc_html($opt_label) . '</option>';
                }
                echo '</select>';
            } elseif ($field['type'] === 'checkbox') {
                $checked = checked($value, '1', false);
                echo '<input type="checkbox" id="' . esc_attr($id) . '" name="' . esc_attr($id) . '" value="1" ' . $checked . '>';
                echo ' <label for="' . esc_attr($id) . '">Yes</label>';
            } elseif ($field['type'] === 'textarea') {
                echo '<textarea id="' . esc_attr($id) . '" name="' . esc_attr($id) . '" style="width: 100%;" rows="3">' . esc_textarea($value) . '</textarea>';
            } elseif ($field['type'] === 'gallery') {
                echo '<textarea class="tca-gallery-input" id="' . esc_attr($id) . '" name="' . esc_attr($id) . '" style="width: 100%; margin-bottom:5px;" rows="3" placeholder="Comma-separated image URLs...">' . esc_textarea($value) . '</textarea>';
                echo '<button class="button tca-gallery-add-btn">Add Images from Media Library</button>';
            }

            if (!empty($field['desc'])) {
                echo '<p class="description">' . esc_html($field['desc']) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';
    }

    private function get_fields()
    {
        return [
            '_tca_price' => ['label' => 'Price', 'type' => 'number'],
            '_tca_bedrooms' => ['label' => 'Bedrooms', 'type' => 'number'],
            '_tca_bathrooms' => ['label' => 'Bathrooms', 'type' => 'number'],
            '_tca_parking' => ['label' => 'Parking Spaces', 'type' => 'number'],
            '_tca_area' => ['label' => 'Area / Size (Sq Ft)', 'type' => 'number'],

            '_tca_floor_level' => ['label' => 'Floor Level', 'type' => 'text', 'desc' => 'E.g. Ground, 5th, High Floor'],
            '_tca_total_floors' => ['label' => 'Total Floors in Building', 'type' => 'number'],
            '_tca_reference' => ['label' => 'Unit Reference (e.g., TCA-47266)', 'type' => 'text'],
            '_tca_project_number' => ['label' => 'Project Number', 'type' => 'text'],
            '_tca_permit_number' => ['label' => 'Permit Number (Madhmoun)', 'type' => 'text'],
            '_tca_permit_expiry' => ['label' => 'Permit Expiry Date (Auto-Hide)', 'type' => 'date', 'desc' => 'Property will auto-hide from public site when this date passes.'],
            '_tca_project_status' => [
                'label' => 'Project Status',
                'type' => 'select',
                'options' => ['off_plan' => 'Off-plan', 'ready' => 'Ready']
            ],
            '_tca_completion_date' => ['label' => 'Completion Date', 'type' => 'date'],
            '_tca_payment_plan' => ['label' => 'Payment Plan', 'type' => 'textarea'],
            '_tca_availability' => [
                'label' => 'Availability',
                'type' => 'select',
                'options' => ['available' => 'Available', 'sold' => 'Sold', 'rented' => 'Rented']
            ],
            '_tca_price_sqft' => ['label' => 'Price per Sq Ft', 'type' => 'number'],
            '_tca_service_charges' => ['label' => 'Service Charges', 'type' => 'number'],
            '_tca_mortgage' => ['label' => 'Mortgage Available', 'type' => 'checkbox'],
            '_tca_listed_by' => [
                'label' => 'Listed By',
                'type' => 'select',
                'options' => ['agent' => 'Agent', 'owner' => 'Owner', 'developer' => 'Developer']
            ],
            '_tca_agent_name' => ['label' => 'Agent Name (If Listed By Agent)', 'type' => 'text'],
            '_tca_agent_phone' => ['label' => 'Agent WhatsApp / Call Number (E.g. +971501234567)', 'type' => 'text'],
            '_tca_agent_avatar' => ['label' => 'Agent Avatar Image URL', 'type' => 'text'],
            '_tca_gallery' => ['label' => 'Image Gallery (Comma-separated URLs)', 'type' => 'gallery', 'desc' => 'Use the button to add multiple images'],
            '_tca_category' => [
                'label' => 'Category',
                'type' => 'select',
                'options' => ['residential' => 'Residential', 'commercial' => 'Commercial']
            ],
            '_tca_emirate' => ['label' => 'UAE Emirate', 'type' => 'text'],
            '_tca_verified' => ['label' => 'Verified Listing', 'type' => 'checkbox'],
            '_tca_featured' => ['label' => 'Featured Listing', 'type' => 'checkbox'],
        ];
    }

    public function save_real_estate_meta($post_id)
    {
        // Check if our nonce is set.
        if (!isset($_POST['tca_unit_meta_nonce'])) {
            return;
        }

        // Verify that the nonce is valid.
        if (!wp_verify_nonce($_POST['tca_unit_meta_nonce'], 'tca_unit_meta_save')) {
            return;
        }

        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check the user's permissions.
        if (isset($_POST['post_type']) && 'tca_unit' === $_POST['post_type']) {
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }
        }

        $fields = $this->get_fields();
        foreach ($fields as $id => $field) {
            if ($field['type'] === 'checkbox') {
                $val = isset($_POST[$id]) ? '1' : '0';
                update_post_meta($post_id, $id, $val);
            } else {
                if (isset($_POST[$id])) {
                    $val = sanitize_text_field(wp_unslash($_POST[$id]));
                    if ($field['type'] === 'textarea') {
                        $val = sanitize_textarea_field(wp_unslash($_POST[$id]));
                    }
                    update_post_meta($post_id, $id, $val);
                }
            }
        }

        // --- Custom Slug URL Generation ---
        // Force the URL to start with the reference ID to prevent duplication.
        if (isset($_POST['_tca_reference'])) {
            $reference = sanitize_text_field(wp_unslash($_POST['_tca_reference']));
            if (!empty($reference)) {
                // Temporarily disable save hook to avoid infinite looping
                remove_action('save_post', [$this, 'save_real_estate_meta']);

                $post = get_post($post_id);
                if ($post && $post->post_type === 'tca_unit' && !in_array($post->post_status, ['trash', 'auto-draft', 'draft'])) {
                    $safe_ref = sanitize_title($reference);
                    // Check if current slug already begins with the sanitized reference.
                    // If not, we rebuild it.
                    if (strpos($post->post_name, $safe_ref) !== 0) {
                        $new_slug = sanitize_title($safe_ref . '-' . $post->post_title);

                        wp_update_post([
                            'ID' => $post_id,
                            'post_name' => $new_slug,
                        ]);
                    }
                }

                add_action('save_post', [$this, 'save_real_estate_meta']);
            }
        }
    }
}

new TCA_RE_Meta_Boxes();
