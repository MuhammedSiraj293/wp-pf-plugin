<?php
/**
 * Registers the [tca_units] shortcode and handles the WP_Query.
 */

if (!defined('ABSPATH')) {
    exit;
}

class TCA_RE_Shortcodes
{

    public function __construct()
    {
        add_shortcode('tca_units', [$this, 'render_units_shortcode']);
        add_shortcode('tca_search_bar', [$this, 'render_search_bar_shortcode']);
        add_shortcode('tca_compact_filter', [$this, 'render_compact_filter_shortcode']);
        add_shortcode('tca_project_units', [$this, 'render_project_units_shortcode']);
        add_shortcode('tca_about_developer', [$this, 'render_about_developer_shortcode']);
        add_shortcode('tca_mortgage_calculator', [$this, 'render_mortgage_calculator_shortcode']);
        add_shortcode('tca_mortgage_summary', [$this, 'render_mortgage_summary_shortcode']);
        add_shortcode('tca_developers', [$this, 'render_developers_shortcode']);
    }

    public function render_search_bar_shortcode($atts)
    {
        $atts = shortcode_atts([
            'hide' => ''
        ], $atts);
        $hide_fields = array_map('trim', explode(',', strtolower($atts['hide'])));

        // Helper check flags for hiding fields (supports synonyms and multi-word keys)
        $hide_purpose = in_array('purpose', $hide_fields);
        $hide_type = in_array('property_type', $hide_fields) || in_array('property-type', $hide_fields) || in_array('type', $hide_fields);
        $hide_status = in_array('status', $hide_fields) || in_array('project_status', $hide_fields) || in_array('project-status', $hide_fields);
        $hide_bedroom = in_array('bedroom', $hide_fields) || in_array('bedrooms', $hide_fields) || in_array('beds', $hide_fields);
        $hide_area = in_array('area', $hide_fields) || in_array('sqft', $hide_fields);
        $hide_price = in_array('price', $hide_fields) || in_array('prices', $hide_fields);
        $hide_sort = in_array('sort', $hide_fields) || in_array('sort_by', $hide_fields) || in_array('orderby', $hide_fields);
        $hide_keyword = in_array('keyword', $hide_fields) || in_array('search', $hide_fields);
        $hide_bathroom = in_array('bathroom', $hide_fields) || in_array('bathrooms', $hide_fields) || in_array('baths', $hide_fields);

        // Enqueue styles
        if (!wp_style_is('tca-re-style', 'registered')) {
            wp_register_style('tca-re-style', TCA_RE_PLUGIN_URL . 'assets/css/style.css', [], TCA_RE_VERSION);
        }
        wp_enqueue_style('tca-re-style');

        // Register and enqueue the JS filtering logic
        wp_register_script('tca-search-script', TCA_RE_PLUGIN_URL . 'assets/js/search-filter.js', [], TCA_RE_VERSION, true);
        wp_localize_script('tca-search-script', 'tcaSearchAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tca_search_nonce')
        ]);
        wp_enqueue_script('tca-search-script');

        ob_start();

        $purposes = $this->get_active_taxonomy_terms('tca_purpose');
        $types = $this->get_active_taxonomy_terms('tca_property_type');

        ?>
        <div class="tca-advanced-search">
            <form id="tca-search-form" class="tca-search-form">

                <!-- Primary row: always visible -->
                <div class="tca-search-primary-row">
                    <?php if (!$hide_purpose): ?>
                        <select name="purpose" class="tca-search-select">
                            <option value="">Buy / Rent</option>
                            <?php foreach ($purposes as $p) {
                                echo '<option value="' . esc_attr($p->slug) . '">' . esc_html($p->name) . '</option>';
                            } ?>
                        </select>
                    <?php endif; ?>

                    <?php if (!$hide_type): ?>
                        <select name="property_type" class="tca-search-select">
                            <option value="">Property Type</option>
                            <?php foreach ($types as $t) {
                                echo '<option value="' . esc_attr($t->slug) . '">' . esc_html($t->name) . '</option>';
                            } ?>
                        </select>
                    <?php endif; ?>

                    <?php if (!$hide_keyword): ?>
                        <div class="tca-search-input-wrapper">
                            <svg class="tca-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8" />
                                <path d="m21 21-4.3-4.3" />
                            </svg>
                            <input type="text" name="keyword" class="tca-search-input"
                                placeholder="Project, Developer, Location...">
                        </div>
                    <?php endif; ?>

                    <!-- Mobile: Filter Trigger -->
                    <button type="button" id="tca-filter-toggle" class="tca-filter-toggle-btn" aria-label="Filters">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="4" y1="6" x2="20" y2="6" />
                            <line x1="8" y1="12" x2="16" y2="12" />
                            <line x1="11" y1="18" x2="13" y2="18" />
                        </svg>
                        Filters
                    </button>

                    <button type="submit" class="tca-search-btn">SEARCH</button>
                </div>

                <!-- Desktop secondary row (hidden on mobile via CSS) -->
                <div class="tca-search-secondary-desktop">
                    <?php if (!$hide_status): ?>
                        <select name="status" class="tca-search-select tca-desktop-field">
                            <option value="">Project Status</option>
                            <option value="off_plan">Off-plan</option>
                            <option value="ready">Ready</option>
                        </select>
                    <?php endif; ?>

                    <?php
                    // Fetch available beds and baths from the inventory to show only active configurations in filter
                    global $wpdb;
                    $available_beds = $wpdb->get_col("
                        SELECT DISTINCT CAST(pm.meta_value AS SIGNED) 
                        FROM {$wpdb->postmeta} pm
                        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                        WHERE p.post_type = 'tca_unit' 
                          AND p.post_status = 'publish' 
                          AND pm.meta_key = '_tca_bedrooms'
                        ORDER BY CAST(pm.meta_value AS SIGNED) ASC
                    ");
                    $available_baths = $wpdb->get_col("
                        SELECT DISTINCT CAST(pm.meta_value AS SIGNED) 
                        FROM {$wpdb->postmeta} pm
                        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                        WHERE p.post_type = 'tca_unit' 
                          AND p.post_status = 'publish' 
                          AND pm.meta_key = '_tca_bathrooms'
                        ORDER BY CAST(pm.meta_value AS SIGNED) ASC
                    ");

                    $show_beds_buttons = [];
                    if (is_array($available_beds)) {
                        foreach ($available_beds as $bed_count) {
                            $bed_count = intval($bed_count);
                            if ($bed_count === 0) {
                                $show_beds_buttons['0'] = true;
                            } elseif ($bed_count >= 1 && $bed_count <= 6) {
                                $show_beds_buttons[(string) $bed_count] = true;
                            } elseif ($bed_count >= 7) {
                                $show_beds_buttons['7+'] = true;
                            }
                        }
                    }

                    $show_baths_buttons = [];
                    if (is_array($available_baths)) {
                        foreach ($available_baths as $bath_count) {
                            $bath_count = intval($bath_count);
                            if ($bath_count >= 1 && $bath_count <= 4) {
                                $show_baths_buttons[(string) $bath_count] = true;
                            } elseif ($bath_count >= 5) {
                                $show_baths_buttons['5+'] = true;
                            }
                        }
                    }

                    $current_beds = isset($_GET['bedrooms']) ? sanitize_text_field($_GET['bedrooms']) : '';
                    $current_baths = isset($_GET['bathrooms']) ? sanitize_text_field($_GET['bathrooms']) : '';

                    if (!$hide_bedroom):
                        $beds_label = 'Bedrooms';
                        if ($current_beds !== '') {
                            if ($current_beds === '0') {
                                $beds_label = 'Studio';
                            } elseif ($current_beds === '1') {
                                $beds_label = '1 Bedroom';
                            } else {
                                $beds_label = $current_beds . ' Bedrooms';
                            }
                        }
                        ?>
                        <!-- Custom Beds Dropdown -->
                        <div class="tca-dropdown-filter" id="tca-beds-dropdown">
                            <button type="button" class="tca-dropdown-trigger">
                                <span class="tca-trigger-label"><?php echo esc_html($beds_label); ?></span>
                                <svg class="tca-trigger-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2">
                                    <path d="M6 9l6 6 6-6" />
                                </svg>
                            </button>
                            <div class="tca-dropdown-panel">
                                <div class="tca-panel-title">Bedrooms</div>
                                <div class="tca-btn-group" data-name="bedrooms">
                                    <button type="button"
                                        class="tca-filter-btn <?php echo ($current_beds === '') ? 'active' : ''; ?>"
                                        data-value="">All</button>
                                    <?php if (isset($show_beds_buttons['0'])): ?>
                                        <button type="button"
                                            class="tca-filter-btn <?php echo ($current_beds === '0') ? 'active' : ''; ?>"
                                            data-value="0">Studio</button>
                                    <?php endif; ?>
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                        <?php if (isset($show_beds_buttons[(string) $i])): ?>
                                            <button type="button"
                                                class="tca-filter-btn <?php echo ($current_beds === (string) $i) ? 'active' : ''; ?>"
                                                data-value="<?php echo $i; ?>"><?php echo $i; ?></button>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    <?php if (isset($show_beds_buttons['7+'])): ?>
                                        <button type="button"
                                            class="tca-filter-btn <?php echo ($current_beds === '7+') ? 'active' : ''; ?>"
                                            data-value="7+">7+</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <input type="hidden" name="bedrooms" value="<?php echo esc_attr($current_beds); ?>"
                                class="tca-dropdown-hidden-input tca-desktop-field">
                        </div>
                    <?php endif; ?>

                    <?php
                    if (!$hide_bathroom):
                        $baths_label = 'Bathrooms';
                        if ($current_baths !== '') {
                            $baths_label = ($current_baths === '1') ? '1 Bathroom' : $current_baths . ' Bathrooms';
                        }
                        ?>
                        <!-- Custom Baths Dropdown -->
                        <div class="tca-dropdown-filter" id="tca-baths-dropdown">
                            <button type="button" class="tca-dropdown-trigger">
                                <span class="tca-trigger-label"><?php echo esc_html($baths_label); ?></span>
                                <svg class="tca-trigger-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2">
                                    <path d="M6 9l6 6 6-6" />
                                </svg>
                            </button>
                            <div class="tca-dropdown-panel">
                                <div class="tca-panel-title">Bathrooms</div>
                                <div class="tca-btn-group" data-name="bathrooms">
                                    <button type="button"
                                        class="tca-filter-btn <?php echo ($current_baths === '') ? 'active' : ''; ?>"
                                        data-value="">All</button>
                                    <?php for ($i = 1; $i <= 4; $i++): ?>
                                        <?php if (isset($show_baths_buttons[(string) $i])): ?>
                                            <button type="button"
                                                class="tca-filter-btn <?php echo ($current_baths === (string) $i) ? 'active' : ''; ?>"
                                                data-value="<?php echo $i; ?>"><?php echo $i; ?></button>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    <?php if (isset($show_baths_buttons['5+'])): ?>
                                        <button type="button"
                                            class="tca-filter-btn <?php echo ($current_baths === '5+') ? 'active' : ''; ?>"
                                            data-value="5+">5+</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <input type="hidden" name="bathrooms" value="<?php echo esc_attr($current_baths); ?>"
                                class="tca-dropdown-hidden-input tca-desktop-field">
                        </div>
                    <?php endif; ?>

                    <?php if (!$hide_area): ?>
                        <select name="area" class="tca-search-select tca-desktop-field">
                            <option value="">Area (Sqft)</option>
                            <option value="0-1000">Up to 1,000</option>
                            <option value="1000-2500">1,000 &ndash; 2,500</option>
                            <option value="2500-5000">2,500 &ndash; 5,000</option>
                            <option value="5000-10000">5,000 &ndash; 10,000</option>
                            <option value="10000-99999">10,000+</option>
                        </select>
                    <?php endif; ?>

                    <?php if (!$hide_price): ?>
                        <select name="min_price" class="tca-search-select tca-desktop-field">
                            <option value="">Min. Price</option>
                            <option value="300000">300K AED</option>
                            <option value="500000">500K AED</option>
                            <option value="1000000">1M AED</option>
                            <option value="2000000">2M AED</option>
                            <option value="5000000">5M AED</option>
                        </select>
                        <select name="max_price" class="tca-search-select tca-desktop-field">
                            <option value="">Max. Price</option>
                            <option value="1000000">1M AED</option>
                            <option value="3000000">3M AED</option>
                            <option value="5000000">5M AED</option>
                            <option value="10000000">10M AED</option>
                            <option value="25000000">25M+ AED</option>
                        </select>
                    <?php endif; ?>

                    <?php if (!$hide_sort): ?>
                        <select name="sort_by" class="tca-search-select tca-desktop-field">
                            <option value="">Sort By</option>
                            <option value="date_desc">Newest First</option>
                            <option value="date_asc">Oldest First</option>
                            <option value="price_asc">Price: Low to High</option>
                            <option value="price_desc">Price: High to Low</option>
                        </select>
                    <?php endif; ?>
                </div>

            </form>
        </div>

        <!--
            MOBILE BOTTOM SHEET — placed OUTSIDE .tca-advanced-search intentionally.
            backdrop-filter on the parent creates a stacking context that would trap
            position:fixed children. Being a sibling lets it render above everything.
            All inputs use form="tca-search-form" to participate in form submission.
        -->
        <div id="tca-filter-overlay" class="tca-filter-overlay"></div>
        <div id="tca-filter-panel" class="tca-filter-sheet" role="dialog" aria-modal="true" aria-label="Search Filters">
            <div class="tca-filter-sheet-handle"></div>
            <div class="tca-filter-sheet-header">
                <span>Filters</span>
                <button type="button" id="tca-filter-close" class="tca-filter-close" aria-label="Close">&times;</button>
            </div>
            <div class="tca-filter-sheet-body">
                <?php if (!$hide_status): ?>
                    <label class="tca-filter-label">Project Status</label>
                    <select name="status" form="tca-search-form" class="tca-search-select tca-mobile-field">
                        <option value="">Any</option>
                        <option value="off_plan">Off-plan</option>
                        <option value="ready">Ready</option>
                    </select>
                <?php endif; ?>

                <?php if (!$hide_bedroom): ?>
                    <label class="tca-filter-label">Bedrooms</label>
                    <div class="tca-inline-filter">
                        <div class="tca-btn-group" data-name="bedrooms">
                            <button type="button" class="tca-filter-btn <?php echo ($current_beds === '') ? 'active' : ''; ?>"
                                data-value="">All</button>
                            <?php if (isset($show_beds_buttons['0'])): ?>
                                <button type="button" class="tca-filter-btn <?php echo ($current_beds === '0') ? 'active' : ''; ?>"
                                    data-value="0">Studio</button>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                                <?php if (isset($show_beds_buttons[(string) $i])): ?>
                                    <button type="button"
                                        class="tca-filter-btn <?php echo ($current_beds === (string) $i) ? 'active' : ''; ?>"
                                        data-value="<?php echo $i; ?>"><?php echo $i; ?></button>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <?php if (isset($show_beds_buttons['7+'])): ?>
                                <button type="button" class="tca-filter-btn <?php echo ($current_beds === '7+') ? 'active' : ''; ?>"
                                    data-value="7+">7+</button>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="bedrooms" value="<?php echo esc_attr($current_beds); ?>"
                            class="tca-dropdown-hidden-input tca-mobile-field" form="tca-search-form">
                    </div>
                <?php endif; ?>

                <?php if (!$hide_bathroom): ?>
                    <label class="tca-filter-label">Bathrooms</label>
                    <div class="tca-inline-filter">
                        <div class="tca-btn-group" data-name="bathrooms">
                            <button type="button" class="tca-filter-btn <?php echo ($current_baths === '') ? 'active' : ''; ?>"
                                data-value="">All</button>
                            <?php for ($i = 1; $i <= 4; $i++): ?>
                                <?php if (isset($show_baths_buttons[(string) $i])): ?>
                                    <button type="button"
                                        class="tca-filter-btn <?php echo ($current_baths === (string) $i) ? 'active' : ''; ?>"
                                        data-value="<?php echo $i; ?>"><?php echo $i; ?></button>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <?php if (isset($show_baths_buttons['5+'])): ?>
                                <button type="button" class="tca-filter-btn <?php echo ($current_baths === '5+') ? 'active' : ''; ?>"
                                    data-value="5+">5+</button>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="bathrooms" value="<?php echo esc_attr($current_baths); ?>"
                            class="tca-dropdown-hidden-input tca-mobile-field" form="tca-search-form">
                    </div>
                <?php endif; ?>

                <?php if (!$hide_area): ?>
                    <label class="tca-filter-label">Area (Sqft)</label>
                    <select name="area" form="tca-search-form" class="tca-search-select tca-mobile-field">
                        <option value="">Any</option>
                        <option value="0-1000">Up to 1,000</option>
                        <option value="1000-2500">1,000 &ndash; 2,500</option>
                        <option value="2500-5000">2,500 &ndash; 5,000</option>
                        <option value="5000-10000">5,000 &ndash; 10,000</option>
                        <option value="10000-99999">10,000+</option>
                    </select>
                <?php endif; ?>

                <?php if (!$hide_price): ?>
                    <label class="tca-filter-label">Min. Price (AED)</label>
                    <select name="min_price" form="tca-search-form" class="tca-search-select tca-mobile-field">
                        <option value="">Any</option>
                        <option value="300000">300,000</option>
                        <option value="500000">500,000</option>
                        <option value="1000000">1,000,000</option>
                        <option value="2000000">2,000,000</option>
                        <option value="5000000">5,000,000</option>
                    </select>

                    <label class="tca-filter-label">Max. Price (AED)</label>
                    <select name="max_price" form="tca-search-form" class="tca-search-select tca-mobile-field">
                        <option value="">Any</option>
                        <option value="1000000">1,000,000</option>
                        <option value="3000000">3,000,000</option>
                        <option value="5000000">5,000,000</option>
                        <option value="10000000">10,000,000</option>
                        <option value="25000000">25,000,000+</option>
                    </select>
                <?php endif; ?>

                <?php if (!$hide_sort): ?>
                    <label class="tca-filter-label">Sort By</label>
                    <select name="sort_by" form="tca-search-form" class="tca-search-select tca-mobile-field">
                        <option value="">Default</option>
                        <option value="date_desc">Newest First</option>
                        <option value="date_asc">Oldest First</option>
                        <option value="price_asc">Price: Low to High</option>
                        <option value="price_desc">Price: High to Low</option>
                    </select>
                <?php endif; ?>
            </div>
            <div class="tca-filter-sheet-footer">
                <button id="tca-filter-apply" type="button" class="tca-filter-apply-btn">Apply Filters</button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Compact Filter Shortcode
     * [tca_compact_filter]
     */
    public function render_compact_filter_shortcode($atts)
    {
        // Enqueue styles
        if (!wp_style_is('tca-re-style', 'registered')) {
            wp_register_style('tca-re-style', TCA_RE_PLUGIN_URL . 'assets/css/style.css', [], TCA_RE_VERSION);
        }
        wp_enqueue_style('tca-re-style');

        // Enqueue JS
        wp_register_script('tca-search-script', TCA_RE_PLUGIN_URL . 'assets/js/search-filter.js', [], TCA_RE_VERSION, true);
        wp_localize_script('tca-search-script', 'tcaSearchAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tca_search_nonce')
        ]);
        wp_enqueue_script('tca-search-script');

        ob_start();
        $types = $this->get_active_taxonomy_terms('tca_property_type');
        ?>
        <div class="tca-advanced-search tca-compact-filter">
            <form id="tca-search-form" class="tca-search-form">
                <div class="tca-search-primary-row">
                    <div class="tca-search-input-wrapper">
                        <svg class="tca-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8" />
                            <path d="m21 21-4.3-4.3" />
                        </svg>
                        <input type="text" name="keyword" class="tca-search-input" placeholder="Project, Developer...">
                    </div>

                    <select name="property_type" class="tca-search-select">
                        <option value="">Property Type</option>
                        <?php foreach ($types as $t) {
                            echo '<option value="' . esc_attr($t->slug) . '">' . esc_html($t->name) . '</option>';
                        } ?>
                    </select>

                    <select name="max_price" class="tca-search-select">
                        <option value="">Price (Max)</option>
                        <option value="1000000">1M AED</option>
                        <option value="3000000">3M AED</option>
                        <option value="5000000">5M AED</option>
                        <option value="10000000">10M AED</option>
                        <option value="25000000">25M+ AED</option>
                    </select>

                    <select name="sort_by" class="tca-search-select">
                        <option value="">Sort By</option>
                        <option value="date_desc">Newest</option>
                        <option value="date_asc">Oldest</option>
                        <option value="price_asc">Price Low</option>
                        <option value="price_desc">Price High</option>
                    </select>

                    <button type="submit" class="tca-search-btn">SEARCH</button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_units_shortcode($atts)
    {
        // Force Styles
        if (!wp_style_is('tca-re-style', 'registered')) {
            wp_register_style('tca-re-style', TCA_RE_PLUGIN_URL . 'assets/css/style.css', [], TCA_RE_VERSION);
        }
        wp_enqueue_style('tca-re-style');

        // Parse attributes and provide defaults
        $atts = shortcode_atts([
            'layout' => 'grid', // 'grid' or 'landscape'
            'purpose' => isset($_GET['purpose']) ? sanitize_text_field($_GET['purpose']) : '',
            'location' => isset($_GET['location']) ? sanitize_text_field($_GET['location']) : '',
            'developer' => isset($_GET['developer']) ? sanitize_text_field($_GET['developer']) : '',
            'project' => isset($_GET['project']) ? sanitize_text_field($_GET['project']) : '',
            'property_type' => isset($_GET['property_type']) ? sanitize_text_field($_GET['property_type']) : '',
            'amenity' => isset($_GET['amenity']) ? sanitize_text_field($_GET['amenity']) : '',
            'bedrooms' => isset($_GET['bedrooms']) ? sanitize_text_field($_GET['bedrooms']) : '',
            'bathrooms' => isset($_GET['bathrooms']) ? sanitize_text_field($_GET['bathrooms']) : '',
            'status' => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '', // Project Status (ready, off_plan)
            'featured' => '', // true/false or 1/0
            'limit' => 10,
            'show_pagination' => 'false',
            'paged' => isset($_GET['page']) ? intval($_GET['page']) : (get_query_var('paged') ? get_query_var('paged') : 1),
        ], $atts, 'tca_units');

        // Automatic Context Detection if taxonomy attribute is not explicitly passed
        $current_id  = get_the_ID();
        $queried_obj = get_queried_object();

        // 1. Automatic Developer Context Detection
        if (empty($atts['developer'])) {
            if ($queried_obj && isset($queried_obj->taxonomy) && $queried_obj->taxonomy === 'tca_developer') {
                $atts['developer'] = $queried_obj->slug;
            } elseif ($current_id) {
                $dev_terms = get_the_terms($current_id, 'tca_developer');
                if (!empty($dev_terms) && !is_wp_error($dev_terms)) {
                    $atts['developer'] = $dev_terms[0]->slug;
                } else {
                    $page_slug  = get_post_field('post_name', $current_id);
                    $page_title = get_the_title($current_id);
                    $dev_term   = get_term_by('slug', sanitize_title($page_slug), 'tca_developer');
                    if (!$dev_term || is_wp_error($dev_term)) {
                        $dev_term = get_term_by('name', $page_title, 'tca_developer');
                    }
                    if ($dev_term && !is_wp_error($dev_term)) {
                        $atts['developer'] = $dev_term->slug;
                    }
                }
            }
        }

        // 2. Automatic Project Context Detection
        if (empty($atts['project'])) {
            if ($queried_obj && isset($queried_obj->taxonomy) && $queried_obj->taxonomy === 'tca_project') {
                $atts['project'] = $queried_obj->slug;
            } elseif ($current_id) {
                $proj_terms = get_the_terms($current_id, 'tca_project');
                if (!empty($proj_terms) && !is_wp_error($proj_terms)) {
                    $atts['project'] = $proj_terms[0]->slug;
                } else {
                    $page_slug  = get_post_field('post_name', $current_id);
                    $page_title = get_the_title($current_id);
                    $proj_term  = get_term_by('slug', sanitize_title($page_slug), 'tca_project');
                    if (!$proj_term || is_wp_error($proj_term)) {
                        $proj_term = get_term_by('name', $page_title, 'tca_project');
                    }
                    if ($proj_term && !is_wp_error($proj_term)) {
                        $atts['project'] = $proj_term->slug;
                    }
                }
            }
        }

        // 3. Automatic Location Context Detection
        if (empty($atts['location'])) {
            if ($queried_obj && isset($queried_obj->taxonomy) && $queried_obj->taxonomy === 'tca_location') {
                $atts['location'] = $queried_obj->slug;
            } elseif ($current_id) {
                $loc_terms = get_the_terms($current_id, 'tca_location');
                if (!empty($loc_terms) && !is_wp_error($loc_terms)) {
                    $atts['location'] = $loc_terms[0]->slug;
                }
            }
        }

        // Setup the Query args
        $args = [
            'post_type' => 'tca_unit',
            'posts_per_page' => intval($atts['limit']),
            'paged' => intval($atts['paged']),
            'post_status' => 'publish',
            'tax_query' => ['relation' => 'AND'],
            'meta_query' => ['relation' => 'AND']
        ];

        // 1. Taxonomies mapping
        $tax_map = [
            'purpose' => 'tca_purpose',
            'location' => 'tca_location',
            'developer' => 'tca_developer',
            'project' => 'tca_project',
            'property_type' => 'tca_property_type',
            'amenity' => 'tca_amenity',
        ];

        foreach ($tax_map as $att_key => $tax_name) {
            if (!empty($atts[$att_key])) {
                $args['tax_query'][] = [
                    'taxonomy' => $tax_name,
                    'field' => 'slug',
                    'terms' => array_map('trim', explode(',', $atts[$att_key])),
                ];
            }
        }

        // 2. Meta Variables
        if ($atts['bedrooms'] !== '') {
            $args['meta_query'][] = [
                'key' => '_tca_bedrooms',
                'value' => intval($atts['bedrooms']),
                'compare' => '='
            ];
        }
        if ($atts['bathrooms'] !== '') {
            $args['meta_query'][] = [
                'key' => '_tca_bathrooms',
                'value' => intval($atts['bathrooms']),
                'compare' => '>='
            ];
        }
        if ($atts['status'] !== '') {
            $args['meta_query'][] = [
                'key' => '_tca_project_status',
                'value' => sanitize_text_field($atts['status']),
                'compare' => '='
            ];
        }
        if ($atts['featured'] === '1' || strtolower($atts['featured']) === 'true') {
            $args['meta_query'][] = [
                'key' => '_tca_featured',
                'value' => '1',
                'compare' => '='
            ];
        }

        // Dynamically apply URL GET meta filters
        if (!empty($_GET['keyword'])) {
            $keyword = sanitize_text_field(wp_unslash($_GET['keyword']));

            // a. Find posts by Title matching keyword (exclude post description)
            global $wpdb;
            $keyword_like = '%' . $wpdb->esc_like($keyword) . '%';
            $ids_by_title = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'tca_unit' AND post_status = 'publish' AND post_title LIKE %s",
                $keyword_like
            ));
            if (!is_array($ids_by_title)) {
                $ids_by_title = [];
            }

            // b. Find matching terms in key taxonomies
            $taxonomies = ['tca_location', 'tca_project', 'tca_developer', 'tca_property_type'];
            $term_ids = [];
            foreach ($taxonomies as $tax) {
                $found_terms = get_terms([
                    'taxonomy' => $tax,
                    'name__like' => $keyword,
                    'fields' => 'ids'
                ]);
                if (!is_wp_error($found_terms) && !empty($found_terms)) {
                    $term_ids[$tax] = $found_terms;
                }
            }

            // c. Find posts that have these matching terms
            $ids_by_tax = [];
            if (!empty($term_ids)) {
                $tax_query = ['relation' => 'OR'];
                foreach ($term_ids as $tax => $ids) {
                    $tax_query[] = [
                        'taxonomy' => $tax,
                        'field' => 'term_id',
                        'terms' => $ids,
                    ];
                }
                $ids_by_tax = get_posts([
                    'post_type' => 'tca_unit',
                    'tax_query' => $tax_query,
                    'fields' => 'ids',
                    'posts_per_page' => -1,
                    'post_status' => 'publish'
                ]);
            }

            // d. Merge all found IDs
            $all_match_ids = array_unique(array_merge($ids_by_title, $ids_by_tax));

            if (!empty($all_match_ids)) {
                $args['post__in'] = $all_match_ids;
            } else {
                $args['post__in'] = [0]; // Force no results if absolutely nothing matches
            }
        }
        // Unified area range
        if (!empty($_GET['area'])) {
            $area_parts = explode('-', sanitize_text_field($_GET['area']));
            if (count($area_parts) === 2) {
                $area_min = intval($area_parts[0]);
                $area_max = intval($area_parts[1]);
                if ($area_min > 0)
                    $args['meta_query'][] = ['key' => '_tca_area', 'value' => $area_min, 'compare' => '>=', 'type' => 'NUMERIC'];
                if ($area_max > 0)
                    $args['meta_query'][] = ['key' => '_tca_area', 'value' => $area_max, 'compare' => '<=', 'type' => 'NUMERIC'];
            }
        }
        // Bedrooms
        if (isset($_GET['bedrooms']) && $_GET['bedrooms'] !== '') {
            $beds_val = sanitize_text_field($_GET['bedrooms']);
            if ($beds_val === '0') {
                $args['meta_query'][] = ['key' => '_tca_bedrooms', 'value' => 0, 'compare' => '='];
            } elseif ($beds_val === '7+') {
                $args['meta_query'][] = ['key' => '_tca_bedrooms', 'value' => 7, 'compare' => '>='];
            } else {
                $args['meta_query'][] = ['key' => '_tca_bedrooms', 'value' => intval($beds_val), 'compare' => '='];
            }
        } elseif (!empty($_GET['min_beds']) || !empty($_GET['max_beds'])) {
            if (!empty($_GET['min_beds'])) {
                $args['meta_query'][] = ['key' => '_tca_bedrooms', 'value' => intval($_GET['min_beds']), 'compare' => '>='];
            }
            if (!empty($_GET['max_beds'])) {
                $args['meta_query'][] = ['key' => '_tca_bedrooms', 'value' => intval($_GET['max_beds']), 'compare' => '<='];
            }
        }

        // Bathrooms
        if (isset($_GET['bathrooms']) && $_GET['bathrooms'] !== '') {
            $baths_val = sanitize_text_field($_GET['bathrooms']);
            if (strpos($baths_val, '+') !== false) {
                $args['meta_query'][] = ['key' => '_tca_bathrooms', 'value' => intval($baths_val), 'compare' => '>='];
            } else {
                $args['meta_query'][] = ['key' => '_tca_bathrooms', 'value' => intval($baths_val), 'compare' => '='];
            }
        }

        if (!empty($_GET['min_price'])) {
            $args['meta_query'][] = ['key' => '_tca_price', 'value' => intval($_GET['min_price']), 'compare' => '>=', 'type' => 'NUMERIC'];
        }
        if (!empty($_GET['max_price'])) {
            $args['meta_query'][] = ['key' => '_tca_price', 'value' => intval($_GET['max_price']), 'compare' => '<=', 'type' => 'NUMERIC'];
        }
        // Sort By (Featured always first, then user-chosen sort)
        $sort_by = !empty($_GET['sort_by']) ? sanitize_text_field($_GET['sort_by']) : 'date_desc';
        switch ($sort_by) {
            case 'date_asc':
                $args['orderby'] = 'date';
                $args['order'] = 'ASC';
                break;
            case 'price_asc':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = '_tca_price';
                $args['order'] = 'ASC';
                break;
            case 'price_desc':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = '_tca_price';
                $args['order'] = 'DESC';
                break;
            default:
                $args['orderby'] = 'date';
                $args['order'] = 'DESC';
                break;
        }
        // Inject featured-first via SQL hook ONLY if the user is not searching/filtering
        $is_searching = !empty($_GET['keyword']) ||
            !empty($_GET['property_type']) ||
            !empty($_GET['status']) ||
            !empty($_GET['min_beds']) ||
            !empty($_GET['max_beds']) ||
            !empty($_GET['bedrooms']) ||
            !empty($_GET['bathrooms']) ||
            !empty($_GET['min_price']) ||
            !empty($_GET['max_price']) ||
            !empty($_GET['area']) ||
            !empty($_GET['sort_by']);

        if (!$is_searching) {
            add_filter('posts_orderby', function ($orderby, $query) {
                global $wpdb;
                $featured_clause = "( SELECT COALESCE(meta_value,'0') FROM {$wpdb->postmeta} pm WHERE pm.post_id = {$wpdb->posts}.ID AND pm.meta_key = '_tca_featured' LIMIT 1 ) DESC";
                return $featured_clause . ', ' . $orderby;
            }, 10, 2);
        }
        // Lazy Enforcement: Hide expired Madhmoun Permitted properties unconditionally
        $args['meta_query'][] = [
            'relation' => 'OR',
            ['key' => '_tca_permit_expiry', 'compare' => 'NOT EXISTS'],
            ['key' => '_tca_permit_expiry', 'value' => '', 'compare' => '='],
            ['key' => '_tca_permit_expiry', 'value' => current_time('Y-m-d'), 'compare' => '>=', 'type' => 'DATE']
        ];

        // Execute Query
        $query = new WP_Query($args);

        ob_start();

        if ($query->have_posts()) {
            $layout_class = ($atts['layout'] === 'landscape') ? 'tca-layout-landscape' : 'tca-layout-grid';

            // Build data-base attributes to keep context during AJAX search
            $base_data = '';
            $context_keys = ['purpose', 'location', 'developer', 'project', 'property_type', 'amenity', 'status', 'bedrooms', 'bathrooms'];
            foreach ($context_keys as $ckey) {
                if (!empty($atts[$ckey])) {
                    $base_data .= ' data-base-' . $ckey . '="' . esc_attr($atts[$ckey]) . '"';
                }
            }

            echo '<div id="tca-results-wrapper" data-limit="' . intval($atts['limit']) . '"' . $base_data . '>';
            echo '<div class="tca-re-grid ' . esc_attr($layout_class) . '">';
            while ($query->have_posts()) {
                $query->the_post();

                $template_name = ($atts['layout'] === 'landscape') ? 'unit-card-landscape.php' : 'unit-card-grid.php';

                // Allow template overrides
                $template_path = locate_template('tca-real-estate/' . $template_name);
                if (!$template_path) {
                    $template_path = TCA_RE_PLUGIN_DIR . 'templates/' . $template_name;
                }

                if (file_exists($template_path)) {
                    include $template_path;
                }
            }
            echo '</div>'; // End grid

            // Pagination Logic
            if ($query->max_num_pages > 1 && ($atts['show_pagination'] === 'true' || $atts['show_pagination'] === '1')) {
                echo '<div class="tca-pagination">';

                $current_page = max(1, $atts['paged']);
                $total_pages = $query->max_num_pages;

                // Prev button
                if ($current_page > 1) {
                    echo '<a href="#" class="tca-page-link tca-page-prev" data-page="' . ($current_page - 1) . '"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg></a>';
                }

                // Intelligent Page Numbers
                $range = 1; // Number of pages around current
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
                    $is_active = ($p == $current_page) ? 'active' : '';
                    $display_num = str_pad($p, 2, '0', STR_PAD_LEFT);
                    echo '<a href="#" class="tca-page-link ' . $is_active . '" data-page="' . $p . '">' . $display_num . '</a>';
                    $last_p = $p;
                }

                // Next button
                if ($current_page < $total_pages) {
                    echo '<a href="#" class="tca-page-link tca-page-next" data-page="' . ($current_page + 1) . '"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></a>';
                }

                echo '</div>';
            }

            echo '</div>'; // End #tca-results-wrapper
            wp_reset_postdata();
        } else {
            $base_data = '';
            $context_keys = ['purpose', 'location', 'developer', 'project', 'property_type', 'amenity', 'status', 'bedrooms', 'bathrooms'];
            foreach ($context_keys as $ckey) {
                if (!empty($atts[$ckey])) {
                    $base_data .= ' data-base-' . $ckey . '="' . esc_attr($atts[$ckey]) . '"';
                }
            }
            echo '<div id="tca-results-wrapper" data-limit="' . intval($atts['limit']) . '"' . $base_data . '>';
            echo '<p class="tca-re-no-results">Unit Sold or Removed. The unit you are looking for has been either sold or removed from our listings. Please take a look at some of our featured units below.</p>';
            echo '</div>';
        }

        $output = ob_get_clean();

        // Inject tiny script for slider controls if not added already
        $output .= $this->get_card_slider_js();
        return $output;
    }

    /**
     * Shared Card Slider JS logic to prevent duplication and fix conflicts.
     */
    private function get_card_slider_js()
    {
        static $tca_slider_script_printed = false;
        if ($tca_slider_script_printed)
            return '';
        $tca_slider_script_printed = true;

        return "
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            function initTcaSliders() {
                document.querySelectorAll('.tca-cg-image-box:not(.slider-init), .tca-cl-image-box:not(.slider-init)').forEach(box => {
                    box.classList.add('slider-init');
                    const slider = box.querySelector('.tca-gallery-slider');
                    const left  = box.querySelector('.ctrl-left');
                    const right = box.querySelector('.ctrl-right');
                    const dots  = box.querySelectorAll('.tca-cg-carousel-dots .dot');

                    if (!slider) return;

                    const totalSlides = slider.querySelectorAll('.tca-gallery-slide').length;
                    if (totalSlides <= 1) return; // nothing to slide

                    let currentIndex = 0;
                    let autoTimer = null;

                    const goTo = (idx) => {
                        // Clamp
                        currentIndex = (idx + totalSlides) % totalSlides;
                        const targetLeft = currentIndex * slider.clientWidth;
                        slider.scrollTo({ left: targetLeft, behavior: 'smooth' });
                        // Update dots
                        dots.forEach((dot, i) => dot.classList.toggle('active', i === currentIndex));
                    };

                    const startAuto = () => {
                        clearInterval(autoTimer);
                        autoTimer = setInterval(() => goTo(currentIndex + 1), 5000);
                    };

                    if (left)  left.addEventListener('click',  (e) => { e.preventDefault(); e.stopPropagation(); goTo(currentIndex - 1); startAuto(); });
                    if (right) right.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); goTo(currentIndex + 1); startAuto(); });

                    // Keep currentIndex in sync when the user swipes manually
                    slider.addEventListener('scrollend', () => {
                        const w = slider.clientWidth;
                        if (w > 0) {
                            currentIndex = Math.round(slider.scrollLeft / w) % totalSlides;
                            dots.forEach((dot, i) => dot.classList.toggle('active', i === currentIndex));
                        }
                    });

                    startAuto();
                });
            }
            initTcaSliders();

            // Re-init for AJAX-loaded cards
            document.addEventListener('tca_results_updated', initTcaSliders);
        });
        </script>
        ";
    }

    /**
     * Helper: Fetch active terms for a taxonomy that have active published units.
     */
    private function get_active_taxonomy_terms($taxonomy)
    {
        global $wpdb;
        $current_date = current_time('Y-m-d');

        $query = $wpdb->prepare("
            SELECT DISTINCT t.term_id
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
            WHERE p.post_type = 'tca_unit'
              AND p.post_status = 'publish'
              AND tt.taxonomy = %s
              AND (
                  NOT EXISTS (
                      SELECT 1 FROM {$wpdb->postmeta} pm 
                      WHERE pm.post_id = p.ID 
                        AND pm.meta_key = '_tca_permit_expiry'
                  )
                  OR EXISTS (
                      SELECT 1 FROM {$wpdb->postmeta} pm 
                      WHERE pm.post_id = p.ID 
                        AND pm.meta_key = '_tca_permit_expiry' 
                        AND (pm.meta_value = '' OR pm.meta_value >= %s)
                  )
              )
        ", $taxonomy, $current_date);

        $active_term_ids = $wpdb->get_col($query);

        if (empty($active_term_ids)) {
            return [];
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'include' => $active_term_ids,
            'hide_empty' => false
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        usort($terms, function ($a, $b) {
            $oa = (int) get_term_meta($a->term_id, 'tca_menu_order', true);
            $ob = (int) get_term_meta($b->term_id, 'tca_menu_order', true);
            return $oa === $ob ? strcmp($a->name, $b->name) : $oa - $ob;
        });

        return $terms;
    }

    /**
     * Renders units for the current project automatically based on Project Name matching the page title.
     * Use: [tca_project_units]
     */
    public function render_project_units_shortcode($atts)
    {
        $atts = shortcode_atts([
            'project' => '',
            'slug'    => '',
            'purpose' => '',
        ], $atts);

        // Force Styles
        if (!wp_style_is('tca-re-style', 'registered')) {
            wp_register_style('tca-re-style', TCA_RE_PLUGIN_URL . 'assets/css/style.css', [], TCA_RE_VERSION);
        }
        wp_enqueue_style('tca-re-style');

        $current_id = get_the_ID();
        $term       = null;

        // 1. Explicit shortcode attribute (project="..." or slug="...")
        $explicit_target = !empty($atts['project']) ? $atts['project'] : $atts['slug'];
        if (!empty($explicit_target)) {
            $term = get_term_by('slug', sanitize_title($explicit_target), 'tca_project');
            if (!$term || is_wp_error($term)) {
                $term = get_term_by('name', sanitize_text_field($explicit_target), 'tca_project');
            }
        }

        // 2. Standard WordPress Taxonomy Checkbox on current Page/Post
        if ((!$term || is_wp_error($term)) && $current_id) {
            $page_terms = get_the_terms($current_id, 'tca_project');
            if (!empty($page_terms) && !is_wp_error($page_terms)) {
                $term = $page_terms[0];
            }
        }

        // 3. Automatic Fallbacks (Queried Object / Page Slug / Page Title)
        if (!$term || is_wp_error($term)) {
            $queried_obj = get_queried_object();
            if ($queried_obj && isset($queried_obj->taxonomy) && $queried_obj->taxonomy === 'tca_project') {
                $term = $queried_obj;
            } else {
                $page_slug = get_post_field('post_name', $current_id);
                if (!empty($page_slug)) {
                    $term = get_term_by('slug', sanitize_title($page_slug), 'tca_project');
                }
                if (!$term || is_wp_error($term)) {
                    $page_title = get_the_title($current_id);
                    if (!empty($page_title)) {
                        $term = get_term_by('name', $page_title, 'tca_project');
                    }
                }
                // Partial Fallback: e.g. Page Title "Ramhan Island Villas" matching term "Ramhan Island"
                if (!$term || is_wp_error($term)) {
                    $all_projects = get_terms(['taxonomy' => 'tca_project', 'hide_empty' => false]);
                    if (!is_wp_error($all_projects) && !empty($all_projects)) {
                        $target_slug  = sanitize_title($page_slug ? $page_slug : $page_title);
                        $target_title = strtolower($page_title);
                        foreach ($all_projects as $p_term) {
                            $p_slug = $p_term->slug;
                            $p_name = strtolower($p_term->name);
                            if (strpos($target_slug, $p_slug) !== false || strpos($target_title, $p_name) !== false || strpos($p_slug, $target_slug) !== false) {
                                $term = $p_term;
                                break;
                            }
                        }
                    }
                }
            }
        }

        if (!$term || is_wp_error($term)) {
            return '';
        }

        $project_display_title = $term->name;

        $tax_query = [
            [
                'taxonomy' => 'tca_project',
                'field'    => 'term_id',
                'terms'    => $term->term_id,
            ]
        ];

        // Optional Purpose filtering
        if (!empty($atts['purpose'])) {
            $purpose_term = get_term_by('slug', sanitize_title($atts['purpose']), 'tca_purpose');
            if (!$purpose_term) {
                $purpose_term = get_term_by('name', sanitize_text_field($atts['purpose']), 'tca_purpose');
            }
            if ($purpose_term && !is_wp_error($purpose_term)) {
                $tax_query[] = [
                    'taxonomy' => 'tca_purpose',
                    'field'    => 'term_id',
                    'terms'    => $purpose_term->term_id,
                ];
            }
        }

        $args = [
            'post_type'      => 'tca_unit',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'tax_query'      => $tax_query,
        ];
        // Inject featured-first via SQL hook
        add_filter('posts_orderby', function ($orderby, $query) {
            global $wpdb;
            $featured_clause = "( SELECT COALESCE(meta_value,'0') FROM {$wpdb->postmeta} pm WHERE pm.post_id = {$wpdb->posts}.ID AND pm.meta_key = '_tca_featured' LIMIT 1 ) DESC";
            return $featured_clause . ', ' . $orderby;
        }, 10, 2);

        $query = new WP_Query($args);
        ob_start();

        if ($query->have_posts()) {
            echo '<div class="tca-project-units-wrapper" data-page-size="3" data-current-page="0">';
            echo '  <div class="tca-project-units-header">';
            echo '      <h3 class="tca-project-units-title">' . esc_html__('Available Units in ', 'tca-real-estate') . esc_html($project_display_title) . '</h3>';
            echo '      <div class="tca-project-nav">';
            echo '          <button class="tca-nav-btn tca-prev" aria-label="Previous">&lt;</button>';
            echo '          <button class="tca-nav-btn tca-next" aria-label="Next" ' . ($query->post_count <= 3 ? 'style="display:none;"' : '') . '>&gt;</button>';
            echo '      </div>';
            echo '  </div>';

            echo '  <div class="tca-project-paged-grid">';

            $idx = 0;
            while ($query->have_posts()) {
                $query->the_post();
                $template_path = TCA_RE_PLUGIN_DIR . 'templates/unit-card-grid.php';
                if (file_exists($template_path)) {
                    echo '<div class="tca-project-item" data-index="' . $idx . '">';
                    include $template_path;
                    echo '</div>';
                    $idx++;
                }
            }

            echo '  </div>'; // End grid
            echo '</div>'; // End wrapper
            wp_reset_postdata();

            // Inject Paging JS
            echo "
            <style>
                .tca-project-item.tca-hidden { display: none !important; }
                .tca-nav-btn:disabled { opacity: 0.3; cursor: not-allowed; }
            </style>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const wrappers = document.querySelectorAll('.tca-project-units-wrapper');
                wrappers.forEach(wrap => {
                    const items = wrap.querySelectorAll('.tca-project-item');
                    const prev = wrap.querySelector('.tca-prev');
                    const next = wrap.querySelector('.tca-next');
                    const pageSize = 3;
                    let currentPage = 0;
                    const totalPages = Math.ceil(items.length / pageSize);

                    const updateView = () => {
                        items.forEach((item, idx) => {
                            const start = currentPage * pageSize;
                            const end = start + pageSize;
                            if (idx >= start && idx < end) {
                                item.classList.remove('tca-hidden');
                            } else {
                                item.classList.add('tca-hidden');
                            }
                        });
                        
                        if(prev) prev.disabled = (currentPage === 0);
                        if(next) next.disabled = (currentPage >= totalPages - 1);
                        
                        // Show buttons only if we have multiple pages
                        if(prev && next && totalPages <= 1) {
                            prev.style.display = 'none';
                            next.style.display = 'none';
                        }
                    };

                    if (next) next.addEventListener('click', () => {
                        if (currentPage < totalPages - 1) {
                            currentPage++;
                            updateView();
                            wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    });
                    if (prev) prev.addEventListener('click', () => {
                        if (currentPage > 0) {
                            currentPage--;
                            updateView();
                            wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    });

                    // Initial call
                    updateView();
                });
            });
            </script>
            ";
        }

        $output = ob_get_clean();
        $output .= $this->get_card_slider_js();

        return $output;
    }

    /**
     * Renders "About the Developer" section matching user screenshot design.
     * Use: [tca_about_developer] or [tca_about_developer slug="aldar"] or [tca_about_developer name="SAAS Properties"]
     */
    public function render_about_developer_shortcode($atts)
    {
        $atts = shortcode_atts([
            'name' => '',
            'slug' => '',
        ], $atts);

        // Enqueue plugin styles
        if (!wp_style_is('tca-re-style', 'registered')) {
            wp_register_style('tca-re-style', TCA_RE_PLUGIN_URL . 'assets/css/style.css', [], TCA_RE_VERSION);
        }
        wp_enqueue_style('tca-re-style');

        $current_id     = get_the_ID();
        $developer_term = null;

        // 1. Explicit shortcode attribute (name="..." or slug="...")
        $explicit_target = !empty($atts['slug']) ? $atts['slug'] : $atts['name'];
        if (!empty($explicit_target)) {
            $developer_term = get_term_by('slug', sanitize_title($explicit_target), 'tca_developer');
            if (!$developer_term || is_wp_error($developer_term)) {
                $developer_term = get_term_by('name', sanitize_text_field($explicit_target), 'tca_developer');
            }
        }

        // 2. Standard WordPress Developer Checkbox checked on current Page/Post
        if ((!$developer_term || is_wp_error($developer_term)) && $current_id) {
            $page_dev_terms = get_the_terms($current_id, 'tca_developer');
            if (!empty($page_dev_terms) && !is_wp_error($page_dev_terms)) {
                $developer_term = $page_dev_terms[0];
            }
        }

        // 3. Single Unit page (tca_unit)
        if ((!$developer_term || is_wp_error($developer_term)) && is_singular('tca_unit')) {
            $terms = get_the_terms($current_id, 'tca_developer');
            if (!empty($terms) && !is_wp_error($terms)) {
                $developer_term = $terms[0];
            }
        }

        // 4. Automatic Fallbacks (Page Slug / Page Title / Associated Project)
        if (!$developer_term || is_wp_error($developer_term)) {
            $page_slug  = get_post_field('post_name', $current_id);
            $page_title = get_the_title($current_id);

            // Direct developer slug or name match
            $dev_by_slug = get_term_by('slug', sanitize_title($page_slug), 'tca_developer');
            if ($dev_by_slug && !is_wp_error($dev_by_slug)) {
                $developer_term = $dev_by_slug;
            } else {
                $dev_by_title = get_term_by('name', $page_title, 'tca_developer');
                if ($dev_by_title && !is_wp_error($dev_by_title)) {
                    $developer_term = $dev_by_title;
                } else {
                    // Try getting project's developer across any unit status (publish, draft, etc.)
                    $proj_term = get_term_by('slug', sanitize_title($page_slug), 'tca_project');
                    if (!$proj_term || is_wp_error($proj_term)) {
                        $proj_term = get_term_by('name', $page_title, 'tca_project');
                    }
                    if ($proj_term && !is_wp_error($proj_term)) {
                        $sample_units = get_posts([
                            'post_type'      => 'tca_unit',
                            'posts_per_page' => 1,
                            'post_status'    => 'any', // Ensures developer shows even if 0 published units available!
                            'tax_query'      => [
                                [
                                    'taxonomy' => 'tca_project',
                                    'field'    => 'term_id',
                                    'terms'    => $proj_term->term_id,
                                ]
                            ]
                        ]);
                        if (!empty($sample_units)) {
                            $dev_terms = get_the_terms($sample_units[0]->ID, 'tca_developer');
                            if (!empty($dev_terms) && !is_wp_error($dev_terms)) {
                                $developer_term = $dev_terms[0];
                            }
                        }
                    }
                }
            }
        }

        if (!$developer_term || is_wp_error($developer_term)) {
            return '';
        }

        $term_id = $developer_term->term_id;
        $name = $developer_term->name;
        $description = term_description($term_id, 'tca_developer');
        if (empty(trim(strip_tags($description)))) {
            $description = $developer_term->description;
        }

        $logo_url = get_term_meta($term_id, 'tca_developer_logo', true);
        $custom_url = get_term_meta($term_id, 'tca_developer_custom_url', true);

        // Fallback to term link if custom URL is not set
        $details_url = !empty($custom_url) ? $custom_url : get_term_link($developer_term);
        if (is_wp_error($details_url)) {
            $details_url = home_url('/developers/');
        }

        ob_start();
        ?>
        <div class="tca-about-dev-box">
            <div class="tca-about-dev-content">
                <h3 class="tca-about-dev-title">About the Developer</h3>
                <?php if (!empty($description)): ?>
                    <div class="tca-about-dev-desc">
                        <?php echo wp_kses_post(wpautop($description)); ?>
                    </div>
                <?php endif; ?>
                <div class="tca-about-dev-btn-wrap">
                    <a href="<?php echo esc_url($details_url); ?>" class="tca-about-dev-btn">More About Developer</a>
                </div>
            </div>
            <?php if (!empty($logo_url)): ?>
                <div class="tca-about-dev-logo-wrap">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($name); ?> Logo"
                        class="tca-about-dev-logo">
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renders a grid of Developer Cards: [tca_developers]
     */
    public function render_developers_shortcode($atts)
    {
        $atts = shortcode_atts([
            'limit' => -1,
            'slugs' => '',
        ], $atts);

        $query_args = [
            'taxonomy'   => 'tca_developer',
            'hide_empty' => false,
            'number'     => $atts['limit'] > 0 ? $atts['limit'] : 0,
        ];

        if (!empty($atts['slugs'])) {
            $slugs = array_map('trim', explode(',', $atts['slugs']));
            $query_args['slug'] = $slugs;
        }

        $terms = get_terms($query_args);

        if (is_wp_error($terms) || empty($terms)) {
            return '<p>No developers found.</p>';
        }

        ob_start();
        ?>
        <style>
        .tca-developers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .tca-dev-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            text-decoration: none !important;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #f0f0f0;
        }
        .tca-dev-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .tca-dev-logo-wrap {
            height: 150px;
            background: #fdfdfd;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .tca-dev-logo-wrap img {
            max-width: 200px;
            max-height: 100px;
            object-fit: contain;
        }
        .tca-dev-info {
            padding: 20px;
            flex: 1;
            background: #fff;
        }
        .tca-dev-info h3 {
            font-size: 22px !important;
            margin: 0 0 10px 0;
            color: var(--tca-secondary) !important;
            font-weight: 500;
        }
        @media (min-width: 768px) {
            .tca-dev-info h3 {
                font-size: 30px !important;
            }
        }
        .tca-dev-info p {
            font-size: 14px;
            color: #666;
            line-height: 1.5;
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        </style>
        <div class="tca-developers-grid">
            <?php foreach ($terms as $dev): 
                $logo_url = get_term_meta($dev->term_id, 'tca_developer_logo', true);
                $custom_url = get_term_meta($dev->term_id, 'tca_developer_custom_url', true);
                
                // Fallback URL if custom url is missing
                $link = !empty($custom_url) ? $custom_url : get_term_link($dev);
                
                // Truncate description dynamically using CSS -webkit-line-clamp but strip tags first
                $desc = wp_strip_all_tags(term_description($dev->term_id, 'tca_developer'));
            ?>
                <a href="<?php echo esc_url($link); ?>" class="tca-dev-card">
                    <div class="tca-dev-logo-wrap">
                        <?php if (!empty($logo_url)): ?>
                            <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($dev->name); ?> Logo">
                        <?php else: ?>
                            <span style="color:#999; font-size:14px; font-weight:600;"><?php echo esc_html($dev->name); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="tca-dev-info">
                        <h3><?php echo esc_html($dev->name); ?></h3>
                        <?php if (!empty($desc)): ?>
                            <p><?php echo esc_html($desc); ?></p>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renders Interactive Mortgage Calculator: [tca_mortgage_calculator]
     */
    public function render_mortgage_calculator_shortcode($atts)
    {
        $current_unit_price = '';
        if (is_singular('tca_unit')) {
            $current_unit_price = get_post_meta(get_the_ID(), '_tca_price', true);
        }

        $atts = shortcode_atts([
            'price'        => !empty($current_unit_price) ? $current_unit_price : '2500000',
            'down_payment' => '20',
            'interest'     => '4.25',
            'years'        => '12',
            'link'         => '#mortage-tca',
        ], $atts);

        $btn_link = !empty($atts['link']) ? $atts['link'] : '#mortage-tca';

        // Enqueue styles and JS
        if (!wp_style_is('tca-re-style', 'registered')) {
            wp_register_style('tca-re-style', TCA_RE_PLUGIN_URL . 'assets/css/style.css', [], TCA_RE_VERSION);
        }
        wp_enqueue_style('tca-re-style');

        wp_register_script('tca-mortgage-script', TCA_RE_PLUGIN_URL . 'assets/js/mortgage-calculator.js', ['jquery'], TCA_RE_VERSION, true);
        wp_enqueue_script('tca-mortgage-script');

        $price        = floatval($atts['price']);
        $down_percent = floatval($atts['down_payment']);
        $interest     = floatval($atts['interest']);
        $years        = intval($atts['years']);

        ob_start();
        ?>
        <div class="tca-mortgage-calc-box">
            <div class="tca-mc-header">
                <h3 class="tca-mc-title">Mortgage Calculator</h3>
                <p class="tca-mc-subtitle">Estimate your monthly mortgage payments</p>
            </div>
            
            <div class="tca-mc-grid">
                <div class="tca-mc-inputs-col">
                    <!-- 1. Unit Price Slider -->
                    <div class="tca-mc-slider-field">
                        <div class="tca-mc-label-row">
                            <span class="tca-mc-field-label">Unit Price (AED)</span>
                            <span class="tca-mc-field-val tca-val-price">2,500,000</span>
                            <input type="hidden" class="tca-mc-price-input" value="<?php echo esc_attr($price); ?>">
                        </div>
                        <input type="range" class="tca-mc-slider tca-slider-price" min="100000" max="25000000" step="50000" value="<?php echo esc_attr($price); ?>">
                        <div class="tca-mc-minmax-row">
                            <span>100,000 AED</span>
                            <span>25,000,000 AED</span>
                        </div>
                    </div>

                    <!-- 2. Down Payment Slider -->
                    <div class="tca-mc-slider-field">
                        <div class="tca-mc-label-row">
                            <span class="tca-mc-field-label">Down Payment (AED)</span>
                            <span class="tca-mc-field-val tca-val-down">500,000</span>
                            <input type="hidden" class="tca-mc-down-percent" value="<?php echo esc_attr($down_percent); ?>">
                        </div>
                        <input type="range" class="tca-mc-slider tca-slider-down" min="5" max="80" step="1" value="<?php echo esc_attr($down_percent); ?>">
                        <div class="tca-mc-minmax-row">
                            <span>50,000 AED</span>
                            <span class="tca-val-down-pct">20%</span>
                        </div>
                    </div>

                    <!-- 3. Loan Period Slider -->
                    <div class="tca-mc-slider-field">
                        <div class="tca-mc-label-row">
                            <span class="tca-mc-field-label">Loan Period (Years)</span>
                            <span class="tca-mc-field-val tca-val-years">12</span>
                            <input type="hidden" class="tca-mc-years-select" value="<?php echo esc_attr($years); ?>">
                        </div>
                        <input type="range" class="tca-mc-slider tca-slider-years" min="1" max="30" step="1" value="<?php echo esc_attr($years); ?>">
                        <div class="tca-mc-minmax-row">
                            <span>1 year</span>
                            <span>30 years</span>
                        </div>
                    </div>

                    <!-- 4. Interest Rate Slider -->
                    <div class="tca-mc-slider-field">
                        <div class="tca-mc-label-row">
                            <span class="tca-mc-field-label">Interest Rate (%)</span>
                            <span class="tca-mc-field-val tca-val-rate">4</span>
                            <input type="hidden" class="tca-mc-rate-input" value="<?php echo esc_attr($interest); ?>">
                        </div>
                        <input type="range" class="tca-mc-slider tca-slider-rate" min="2" max="15" step="0.5" value="<?php echo esc_attr($interest); ?>">
                        <div class="tca-mc-minmax-row">
                            <span>2%</span>
                            <span>15%</span>
                        </div>
                    </div>
                </div>

                <div class="tca-mc-results-col">
                    <div class="tca-mc-card-row">
                        <span class="tca-mc-card-label">Total Loan Amount</span>
                        <span class="tca-mc-card-val tca-mc-res-loan">2,000,000 AED</span>
                    </div>
                    <div class="tca-mc-card-divider"></div>

                    <div class="tca-mc-card-row">
                        <span class="tca-mc-card-label">Interest</span>
                        <span class="tca-mc-card-val tca-mc-res-rate">4%</span>
                    </div>
                    <div class="tca-mc-card-divider"></div>

                    <div class="tca-mc-card-row">
                        <span class="tca-mc-card-label">Loan Period</span>
                        <span class="tca-mc-card-val tca-mc-res-period">12 years</span>
                    </div>
                    <div class="tca-mc-card-divider"></div>

                    <div class="tca-mc-card-monthly-block">
                        <span class="tca-mc-monthly-label">Monthly Payment</span>
                        <span class="tca-mc-monthly-val tca-mc-res-monthly">18,000.16 AED</span>
                    </div>

                    <div class="tca-mc-action-wrap">
                        <a href="<?php echo esc_url($btn_link); ?>" class="tca-mc-apply-btn mortage-tca">Send Application</a>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renders Mortgage Loan Summary Box for Elementor Popup: [tca_mortgage_summary]
     */
    public function render_mortgage_summary_shortcode($atts)
    {
        // Enqueue styles and JS
        if (!wp_style_is('tca-re-style', 'registered')) {
            wp_register_style('tca-re-style', TCA_RE_PLUGIN_URL . 'assets/css/style.css', [], TCA_RE_VERSION);
        }
        wp_enqueue_style('tca-re-style');

        wp_register_script('tca-mortgage-script', TCA_RE_PLUGIN_URL . 'assets/js/mortgage-calculator.js', ['jquery'], TCA_RE_VERSION, true);
        wp_enqueue_script('tca-mortgage-script');

        ob_start();
        ?>
        <div class="tca-mortgage-summary-card">
            <div class="tca-ms-banner">
                <span class="tca-ms-banner-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                </span>
                <span class="tca-ms-banner-text">Verify the details of your future loan</span>
            </div>

            <div class="tca-ms-rows">
                <div class="tca-ms-row">
                    <span class="tca-ms-label">Monthly Payment</span>
                    <span class="tca-ms-value tca-ms-monthly-val">0.00 AED</span>
                </div>
                <div class="tca-ms-row">
                    <span class="tca-ms-label">Loan Amount</span>
                    <span class="tca-ms-value tca-ms-loan-val">0.00 AED</span>
                </div>
                <div class="tca-ms-row">
                    <span class="tca-ms-label">Down Payment</span>
                    <span class="tca-ms-value tca-ms-down-val">0.00 AED</span>
                </div>
                <div class="tca-ms-row">
                    <span class="tca-ms-label">Interest Rate</span>
                    <span class="tca-ms-value tca-ms-rate-val">4%</span>
                </div>
                <div class="tca-ms-row">
                    <span class="tca-ms-label">Loan Period</span>
                    <span class="tca-ms-value tca-ms-period-val">12 years</span>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

new TCA_RE_Shortcodes();
