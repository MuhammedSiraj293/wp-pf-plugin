<?php
/**
 * Single Unit Template
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

$main_unit_id = get_the_ID();
$price = get_post_meta( $main_unit_id, '_tca_price', true );
$bedrooms = get_post_meta( $main_unit_id, '_tca_bedrooms', true );
$bathrooms = get_post_meta( $main_unit_id, '_tca_bathrooms', true );
$area = get_post_meta( $main_unit_id, '_tca_area', true );
$price_sqft = get_post_meta( $main_unit_id, '_tca_price_sqft', true );
if (!$price_sqft && $price && $area) $price_sqft = round($price / $area);

$main_gallery_meta = get_post_meta( $main_unit_id, '_tca_gallery', true );
$main_gallery_images = [];
$thumbnail_url = get_the_post_thumbnail_url( $main_unit_id, 'large' );
if ( $thumbnail_url ) {
    $main_gallery_images[] = $thumbnail_url;
}
if ( $main_gallery_meta ) {
    $extra_images = array_filter( array_map( 'trim', explode( ',', $main_gallery_meta ) ) );
    $main_gallery_images = array_merge( $main_gallery_images, $extra_images );
}
if ( empty($main_gallery_images) ) $main_gallery_images[] = 'https://thecapitalavenue.com/wp-content/uploads/2026/04/THE-CAPITAL-AVENUE.jpg';

$purposes = get_the_terms( $main_unit_id, 'tca_purpose' );
$purpose_name = ( $purposes && ! is_wp_error( $purposes ) ) ? $purposes[0]->name : 'Sale';
$purpose_term_id = ( $purposes && ! is_wp_error( $purposes ) ) ? $purposes[0]->term_id : 0;

$locations = get_the_terms( $main_unit_id, 'tca_location' );
$location_name = ( $locations && ! is_wp_error( $locations ) ) ? $locations[0]->name : '';
$location_term_id = ( $locations && ! is_wp_error( $locations ) ) ? $locations[0]->term_id : 0;

$projects = get_the_terms( $main_unit_id, 'tca_project' );
$project_name = '';
$project_term_id = 0;
if ( $projects && ! is_wp_error( $projects ) ) {
    // Statistically, the most specific phase/sub-project will have the lowest unit count compared to the macro-project
    usort($projects, function($a, $b) {
        if ($a->count == $b->count) return 0;
        return ($a->count < $b->count) ? -1 : 1;
    });
    $project_name = $projects[0]->name;
    $project_term_id = $projects[0]->term_id;
}

$developers = get_the_terms( $main_unit_id, 'tca_developer' );
$developer_name = ( $developers && ! is_wp_error( $developers ) ) ? $developers[0]->name : '—';

$prop_types = get_the_terms( $main_unit_id, 'tca_property_type' );
$prop_type_val = ( ! empty( $prop_types ) && ! is_wp_error( $prop_types ) ) ? $prop_types[0]->name : '—';

$agent_name = get_post_meta( $main_unit_id, '_tca_agent_name', true );
$agent_phone = get_post_meta( $main_unit_id, '_tca_agent_phone', true );
$agent_avatar = get_post_meta( $main_unit_id, '_tca_agent_avatar', true );
if (!$agent_avatar) $agent_avatar = "https://ui-avatars.com/api/?name=" . urlencode($agent_name ? $agent_name : 'Agent') . "&background=0ea5e9&color=fff";
if (!$agent_name) $agent_name = "TCA Team";

$amenities = get_the_terms( $main_unit_id, 'tca_amenity' );

$reference = get_post_meta( $main_unit_id, '_tca_reference', true );
if (!$reference) $reference = 'MPS-' . str_pad($main_unit_id, 5, '0', STR_PAD_LEFT);
$status = get_post_meta( $main_unit_id, '_tca_status', true );
if (!$status) $status = 'Ready';
$added_on = get_the_date('M d, Y');

$project_number = get_post_meta( $main_unit_id, '_tca_project_number', true );
$permit_number = get_post_meta( $main_unit_id, '_tca_permit_number', true );

$payment_plan = get_post_meta( $main_unit_id, '_tca_payment_plan', true );
$completion = get_post_meta( $main_unit_id, '_tca_completion_date', true );
if ( $completion ) {
    $time = strtotime($completion);
    if ( $time ) {
        $quarter = ceil((date('n', $time)) / 3);
        $completion = "Q{$quarter} " . date('Y', $time);
    }
}
$project_status = get_post_meta( $main_unit_id, '_tca_project_status', true );

$watermark_path = plugin_dir_path(dirname(__FILE__)) . 'assets/images/watermark.png';
$watermark_b64 = '';
if ( file_exists($watermark_path) ) {
    $watermark_b64 = 'data:image/png;base64,' . base64_encode(file_get_contents($watermark_path));
}

// Dynamic Title Generation
$commercial_types = ['Office', 'Retail', 'Shop', 'Warehouse', 'Commercial', 'Plot', 'Land'];
$is_commercial = false;
foreach ($commercial_types as $ct) {
    if (stripos($prop_type_val, $ct) !== false) {
        $is_commercial = true;
        break;
    }
}

$dynamic_title = "";
if ( ! $is_commercial && ! empty( $bedrooms ) ) {
    if ( strtolower($bedrooms) == 'studio' ) {
         $dynamic_title .= "Studio {$prop_type_val}";
    } else {
         $dynamic_title .= "{$bedrooms} Bedroom {$prop_type_val}";
    }
} else {
    $dynamic_title .= "{$prop_type_val}";
}

$display_purpose = ( stripos($purpose_name, 'buy') !== false || stripos($purpose_name, 'sale') !== false ) ? 'Sale' : 'Rent';
$dynamic_title .= " for {$display_purpose}";

if ( ! empty( $project_name ) ) {
    $dynamic_title .= " in {$project_name}";
}
if ( ! empty( $location_name ) ) {
    $dynamic_title .= ", {$location_name}";
}
?>

<div class="tca-sp-container">

    <!-- Header Actions -->
    <div class="tca-sp-header-row">
        <div class="tca-sp-title-area">
            <h1 class="tca-sp-title"><?php echo esc_html($dynamic_title); ?></h1>
        </div>
        <div class="tca-sp-actions">
            <a href="#" id="tca-btn-offer" class="tca-sp-action-btn" style="display:none; background: var(--tca-secondary); color: #fff; border-color: var(--tca-secondary);"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg> Offer</a>
            <a href="#" id="tca-btn-brochure" class="tca-sp-action-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg> Brochure</a>
            <a href="#" id="tca-btn-share" class="tca-sp-action-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg> Share</a>
        </div>
    </div>

    <!-- Full Width Hero Visuals -->
    <div class="tca-sp-hero-wrapper">
        <div class="tca-hero-tags">
            <span class="tca-hero-tag"><?php echo esc_html($purpose_name); ?></span>
            <span class="tca-hero-tag"><?php echo esc_html(strtolower($project_status) === 'off_plan' ? 'Off-plan' : 'Ready'); ?></span>
        </div>
        <div class="tca-sp-hero-gallery" id="overview-gallery">
            <?php foreach($main_gallery_images as $idx => $img): ?>
                <img src="<?php echo esc_url($img); ?>" alt="Property Image" data-index="<?php echo $idx; ?>" class="tca-hero-img-slide">
            <?php endforeach; ?>
        </div>
        
        <button class="tca-sp-hero-nav tca-hero-prev">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
        </button>
        <button class="tca-sp-hero-nav tca-hero-next">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </button>

        <button class="tca-sp-gallery-badge" id="tca-open-lightbox">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;"><polyline points="15 3 21 3 21 9"></polyline><polyline points="9 21 3 21 3 15"></polyline><line x1="21" y1="3" x2="14" y2="10"></line><line x1="3" y1="21" x2="10" y2="14"></line></svg>
            <?php echo count($main_gallery_images); ?> Photo<?php echo count($main_gallery_images)>1?'s':''; ?>
        </button>
    </div>

    <!-- Tabs underneath the Hero -->
    <div class="tca-sp-tabs-wrapper">
        <div class="tca-sp-tabs">
            <a href="#overview" class="tca-sp-tab active">Overview</a>
            <a href="#amenities" class="tca-sp-tab">Amenities</a>
            <a href="#details" class="tca-sp-tab">Property Reference</a>
            <a href="#mortgage" class="tca-sp-tab">Mortgage</a>
            <a href="#enquiry" class="tca-sp-tab tca-enquire-btn">Enquiry</a>
        </div>
    </div>
 
    <!-- Layout Grid -->
    <div class="tca-sp-layout">
        
        <!-- Left Column (Content Details) -->
        <div class="tca-sp-main-col">
            
            <div class="tca-sp-section" id="overview" style="padding-top:20px;">
                <h2 class="tca-sp-section-title">Overview</h2>
                <div class="tca-overview-content tca-sp-text" id="tca-overview-text">
                    <?php 
                    while ( have_posts() ) : the_post();
                        the_content();
                    endwhile; 
                    ?>
                </div>
                <button class="tca-read-more-btn" id="tca-overview-toggle" style="display:none;">Read more</button>
                <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        const contentBox = document.getElementById("tca-overview-text");
                        const toggleBtn = document.getElementById("tca-overview-toggle");
                        
                        // Check if content is actually clamped (taller than what 9 lines allows)
                        if(contentBox.scrollHeight > contentBox.clientHeight) {
                            toggleBtn.style.display = "inline-block";
                        }
                        
                        toggleBtn.addEventListener("click", function() {
                            contentBox.classList.toggle("tca-expanded");
                            if(contentBox.classList.contains("tca-expanded")) {
                                toggleBtn.innerText = "Read less";
                            } else {
                                toggleBtn.innerText = "Read more";
                            }
                        });
                    });
                </script>
            </div>

            <div class="tca-sp-section" id="amenities">
                <h2 class="tca-sp-section-title">Features and Amenities</h2>
                <div class="tca-sp-amenities-grid">
                    <?php if ( $amenities && ! is_wp_error( $amenities ) ) : ?>
                        <?php 
                        $grouped_amenities = [];
                        foreach ( $amenities as $am ) {
                            if ( $am->parent != 0 ) {
                                $parent = get_term( $am->parent, 'tca_amenity' );
                                $parent_name = ( $parent && ! is_wp_error( $parent ) ) ? $parent->name : 'Features';
                                $grouped_amenities[$parent_name][] = $am;
                            } else {
                                // It's a top level term. Check if we have children in the list for it.
                                $has_child = false;
                                foreach ( $amenities as $child ) {
                                    if ( $child->parent == $am->term_id ) { $has_child = true; break; }
                                }
                                if ( ! $has_child ) {
                                    $grouped_amenities['General'][] = $am;
                                }
                            }
                        }
                        
                        // Map SVGs based on keywords
                        $svg_indoor = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>';
                        $svg_outdoor = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>';
                        $svg_default = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>';

                        foreach ( $grouped_amenities as $group_name => $items ) :
                            $icon = $svg_default;
                            if ( stripos($group_name, 'indoor') !== false ) $icon = $svg_indoor;
                            if ( stripos($group_name, 'outdoor') !== false ) $icon = $svg_outdoor;
                        ?>
                        <div class="tca-sp-amenity-group">
                            <h3 class="tca-sp-group-title">
                                <?php echo $icon; ?>
                                <?php echo esc_html( $group_name ); ?>
                            </h3>
                            <?php foreach($items as $item): ?>
                                <span class="tca-sp-amenity-item"><?php echo esc_html($item->name); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No amenities listed.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tca-sp-section" id="details">
                <div class="tca-sp-details-box">
                    <h2 class="tca-sp-section-title">Property Reference</h2>
                    <?php if (!empty($project_name)): ?>
                    <div class="tca-sp-details-row">
                        <span class="tca-sp-details-label">Property Name</span>
                        <span class="tca-sp-details-value"><?php echo esc_html($project_name); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($developer_name) && $developer_name !== '—'): ?>
                    <div class="tca-sp-details-row">
                        <span class="tca-sp-details-label">Developer</span>
                        <span class="tca-sp-details-value"><?php echo esc_html($developer_name); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($reference)): ?>
                    <div class="tca-sp-details-row">
                        <span class="tca-sp-details-label">Unit Reference</span>
                        <span class="tca-sp-details-value"><?php echo esc_html($reference); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($location_name)): ?>
                    <div class="tca-sp-details-row">
                        <span class="tca-sp-details-label">Location</span>
                        <span class="tca-sp-details-value"><?php echo esc_html($location_name); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ( ! empty( $project_number ) ) : ?>
                    <div class="tca-sp-details-row">
                        <span class="tca-sp-details-label">Project Number</span>
                        <span class="tca-sp-details-value"><?php echo esc_html($project_number); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ( ! empty( $permit_number ) ) : ?>
                    <div class="tca-sp-details-row">
                        <span class="tca-sp-details-label">Permit Number (Madhmoun)</span>
                        <span class="tca-sp-details-value"><?php echo esc_html($permit_number); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="tca-sp-details-row">
                        <span class="tca-sp-details-label">Added On</span>
                        <span class="tca-sp-details-value"><?php echo esc_html($added_on); ?></span>
                    </div>
                </div>
            </div>
    <div class="tca-sp-section" id="mortgage">
        <h2 class="tca-sp-section-title">Mortgage Calculator</h2>
        <p class="tca-sp-text" style="color:var(--tca-text-light); margin-top:-20px; margin-bottom:30px;">Estimate your monthly mortgage payments</p>
        
        <div class="tca-sp-mortgage">
            <div class="tca-sp-mortgage-controls">
                <!-- Unit Price -->
                <div class="tca-calc-slider-group">
                    <div class="tca-calc-top">
                        <span class="tca-calc-label">Unit Price (AED)</span>
                        <span class="tca-calc-val" id="val_loan"><?php echo number_format($price); ?></span>
                    </div>
                    <input type="range" class="tca-calc-range" id="rng_loan" min="100000" max="25000000" step="10000" value="<?php echo floatval($price); ?>">
                    <div class="tca-calc-limits"><span>100,000 AED</span><span>25,000,000 AED</span></div>
                </div>

                <!-- Down Payment -->
                <div class="tca-calc-slider-group">
                    <div class="tca-calc-top">
                        <span class="tca-calc-label">Down Payment (AED)</span>
                        <span class="tca-calc-val" id="val_dp"><?php echo number_format($price * 0.25); ?></span>
                    </div>
                    <input type="range" class="tca-calc-range" id="rng_dp" min="50000" max="<?php echo floatval($price); ?>" step="5000" value="<?php echo floatval($price * 0.25); ?>">
                    <div class="tca-calc-limits"><span id="lbl_dp_min">50,000 AED</span><span id="lbl_dp_pct">25%</span></div>
                </div>

                <!-- Loan Period -->
                <div class="tca-calc-slider-group">
                    <div class="tca-calc-top">
                        <span class="tca-calc-label">Loan Period (Years)</span>
                        <span class="tca-calc-val" id="val_years">20</span>
                    </div>
                    <input type="range" class="tca-calc-range" id="rng_years" min="1" max="30" step="1" value="20">
                    <div class="tca-calc-limits"><span>1 year</span><span>30 years</span></div>
                </div>

                <!-- Interest Rate -->
                <div class="tca-calc-slider-group">
                    <div class="tca-calc-top">
                        <span class="tca-calc-label">Interest Rate (%)</span>
                        <span class="tca-calc-val" id="val_rate">4</span>
                    </div>
                    <input type="range" class="tca-calc-range" id="rng_rate" min="2" max="15" step="0.1" value="4">
                    <div class="tca-calc-limits"><span>2%</span><span>15%</span></div>
                </div>
            </div>

            <div class="tca-sp-mortgage-result">
                <div class="tca-mr-row">
                    <span class="tca-mr-label">Total Loan Amount</span>
                    <span class="tca-mr-val" id="res_loan"><?php echo number_format($price * 0.75); ?> AED</span>
                </div>
                <div class="tca-mr-row">
                    <span class="tca-mr-label">Interest</span>
                    <span class="tca-mr-val" id="res_rate">4%</span>
                </div>
                <div class="tca-mr-row" style="border:none;">
                    <span class="tca-mr-label">Loan Period</span>
                    <span class="tca-mr-val" id="res_years">20 years</span>
                </div>
                
                <div class="tca-mr-monthly">
                    <span>Monthly Payment</span>
                    <strong id="res_monthly">0.00 AED</strong>
                    <a href="#enquiry" class="tca-btn-blue tca-enquire-btn">Send Application</a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const elLoan = document.getElementById('rng_loan');
        const elDp = document.getElementById('rng_dp');
        const elYears = document.getElementById('rng_years');
        const elRate = document.getElementById('rng_rate');
        
        const fmt = (num) => new Intl.NumberFormat('en-US').format(Math.round(num));
        
        const calculate = () => {
            let loan = parseFloat(elLoan.value);
            let dp = parseFloat(elDp.value);
            let years = parseInt(elYears.value);
            let rate = parseFloat(elRate.value);
            
            if (dp >= loan) { dp = loan * 0.99; elDp.value = dp; }
            elDp.max = loan;
            
            let principal = loan - dp;
            let monthlyRate = (rate / 100) / 12;
            let totalPayments = years * 12;
            
            let monthly = 0;
            if (monthlyRate === 0) {
                monthly = principal / totalPayments;
            } else {
                monthly = principal * monthlyRate * Math.pow(1 + monthlyRate, totalPayments) / (Math.pow(1 + monthlyRate, totalPayments) - 1);
            }
            
            document.getElementById('val_loan').innerText = fmt(loan);
            document.getElementById('val_dp').innerText = fmt(dp);
            document.getElementById('lbl_dp_pct').innerText = Math.round((dp/loan)*100) + '%';
            document.getElementById('val_years').innerText = years;
            document.getElementById('val_rate').innerText = rate;
            
            document.getElementById('res_loan').innerText = fmt(principal) + ' AED';
            document.getElementById('res_rate').innerText = rate + '%';
            document.getElementById('res_years').innerText = years + ' years';
            document.getElementById('res_monthly').innerText = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(monthly) + ' AED';
        };
        
        elLoan.addEventListener('input', calculate);
        elDp.addEventListener('input', calculate);
        elYears.addEventListener('input', calculate);
        elRate.addEventListener('input', calculate);
        
        calculate();
    });
    </script>
        </div>

        <!-- Right Column / Sidebar -->
        <div class="tca-sp-sidebar-col">
            <div class="tca-sp-sidebar">
                <div class="tca-sp-widget">
                    <div class="tca-sp-widget-price">Price</div>
                    <h2 class="tca-sp-price-title"><?php echo number_format($price); ?> AED</h2>
                    <?php if (!empty($project_name)): ?>
                    <div class="tca-sp-w-row" data-pdf="exclude">
                        <span class="tca-sp-details-label">Property Name</span>
                        <span class="tca-sp-details-value"><?php echo esc_html($project_name); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($prop_type_val) && $prop_type_val !== '—'): ?>
                    <div class="tca-sp-w-row" data-pdf="exclude">
                        <span class="tca-sp-details-label">Property Type</span>
                        <span class="tca-sp-details-value"><?php echo esc_html($prop_type_val); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php
                    // Bedrooms display logic:
                    if ( $bedrooms === '' || $bedrooms === null ) {
                        // For Land, Office, etc., we simply do not show a bedrooms row.
                    } elseif ( (int) $bedrooms === 0 ) {
                        echo '<div class="tca-sp-w-row">
                            <span class="tca-sp-details-label">Bedrooms</span>
                            <span class="tca-sp-details-value">Studio</span>
                        </div>';
                    } else {
                        echo '<div class="tca-sp-w-row">
                            <span class="tca-sp-details-label">Bedrooms</span>
                            <span class="tca-sp-details-value">' . esc_html( $bedrooms ) . '</span>
                        </div>';
                    }
                    ?>
                    <?php if ( $bathrooms !== '' && $bathrooms !== null ) : ?>
                    <div class="tca-sp-w-row">
                        <span class="tca-sp-details-label">Bathroom</span>
                        <span class="tca-sp-details-value"><?php echo esc_html($bathrooms); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($area)): ?>
                    <div class="tca-sp-w-row">
                        <span class="tca-sp-details-label">Area</span>
                        <span class="tca-sp-details-value"><?php echo number_format($area); ?> sq. ft.</span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($price_sqft)): ?>
                    <div class="tca-sp-w-row">
                        <span class="tca-sp-details-label">Price per sq. ft.</span>
                        <span class="tca-sp-details-value"><?php echo number_format($price_sqft); ?> AED per ft²</span>
                    </div>
                    <?php endif; ?>
                    <?php if ( strtolower($project_status) === 'off_plan' ) : ?>
                        <?php if ( !empty($payment_plan) ) : ?>
                        <div class="tca-sp-w-row">
                            <span class="tca-sp-details-label">Payment Plan</span>
                            <span class="tca-sp-details-value"><?php echo esc_html($payment_plan); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ( !empty($completion) ) : ?>
                        <div class="tca-sp-w-row">
                            <span class="tca-sp-details-label">Handover</span>
                            <span class="tca-sp-details-value"><?php echo esc_html($completion); ?></span>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php 
                        $call_number = !empty($agent_phone) ? $agent_phone : '+971505026788';
                        $wa_number = !empty($agent_phone) ? $agent_phone : '+971527224544';
                        $property_url = get_permalink();
                        $property_title = get_the_title();
                        $wa_text = urlencode( "Hi! I'm interested in your listing [Ref: {$reference}] on website {$property_title} | {$property_url}" );
                        $call_link = 'tel:' . str_replace(' ', '', $call_number);
                        $wa_link = 'https://wa.me/' . str_replace([' ', '+'], ['', ''], $wa_number) . '?text=' . $wa_text;
                    ?>
                    <div class="tca-sp-agent-actions">
                        <a href="<?php echo $call_link; ?>" class="tca-sp-btn-call">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
                            </svg> Call Us
                        </a>
                        <a href="<?php echo $wa_link; ?>" target="_blank" class="tca-sp-btn-wa">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" />
                            </svg> WhatsApp
                        </a>
                    </div> <!-- End .tca-sp-agent-actions -->
                    
                    <a href="#enquiry" class="tca-sp-btn-enquire tca-enquire-btn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                        </svg>
                        Schedule callback
                    </a>
                </div> <!-- End .tca-sp-widget -->

                <!-- Desktop Ad Widget -->
                <div class="tca-desktop-ad">
                    <?php echo do_shortcode('[tca_ads]'); ?>
                </div>

            </div> <!-- End .tca-sp-sidebar -->

        </div> <!-- /.tca-sp-sidebar-col -->

    </div><!-- /.tca-sp-layout -->

    <!-- Mobile Ad Widget -->
    <div class="tca-mobile-ad">
        <?php echo do_shortcode('[tca_ads]'); ?>
    </div>

    <!-- Units in same project -->
    <?php if ( $project_term_id ) : ?>
    <div class="tca-sp-section" style="border:none; margin-bottom: 30px;">
        <div class="tca-sp-related-header">
            <h2 class="tca-sp-section-title">Available Units in <?php echo esc_html($project_name); ?></h2>
            <div class="tca-sp-related-nav">
                <span onclick="document.getElementById('tca-carousel-project').scrollBy({left:-300, behavior:'smooth'})"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg></span>
                <span onclick="document.getElementById('tca-carousel-project').scrollBy({left:300, behavior:'smooth'})"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg></span>
            </div>
        </div>
        
        <div class="tca-sp-carousel" id="tca-carousel-project">
<?php
            $args_proj = [
                'post_type' => 'tca_unit',
                'posts_per_page' => 10,
                'post__not_in' => [ $main_unit_id ],
                'tax_query' => [
                    'relation' => 'AND',
                    [
                        'taxonomy' => 'tca_project',
                        'field' => 'term_id',
                        'terms' => $project_term_id,
                    ]
                ]
            ];
            if ( $purpose_term_id ) {
                $args_proj['tax_query'][] = [
                    'taxonomy' => 'tca_purpose',
                    'field' => 'term_id',
                    'terms' => $purpose_term_id,
                ];
            }
            $proj_query = new WP_Query( $args_proj );
            if ( $proj_query->have_posts() ) {
                while ( $proj_query->have_posts() ) {
                    $proj_query->the_post();
                    $loop_id = get_the_ID();
                    set_query_var( 'tca_card_post_id', $loop_id );
                    include TCA_RE_PLUGIN_DIR . 'templates/unit-card-grid.php';
                }
                wp_reset_postdata();
                
                // Re-fetch main unit data to patch loop bleeding
                $reference = get_post_meta( $main_unit_id, '_tca_reference', true );
                if (!$reference) $reference = 'MPS-' . str_pad($main_unit_id, 5, '0', STR_PAD_LEFT);
                $purposes = get_the_terms($main_unit_id, 'tca_purpose');
                $purpose_name = ($purposes && !is_wp_error($purposes)) ? $purposes[0]->name : 'Sale';
                $locations = get_the_terms($main_unit_id, 'tca_location');
                $location_name = ($locations && !is_wp_error($locations)) ? $locations[0]->name : '';
                $project_status = get_post_meta( $main_unit_id, '_tca_project_status', true );
                
                $projects_ref = get_the_terms( $main_unit_id, 'tca_project' );
                $project_name = '';
                if ( $projects_ref && ! is_wp_error( $projects_ref ) ) {
                    usort($projects_ref, function($a, $b) {
                        if ($a->count == $b->count) return 0;
                        return ($a->count < $b->count) ? -1 : 1;
                    });
                    $project_name = $projects_ref[0]->name;
                }
            } else {
                echo '<p>No other units available in this project.</p>';
            }
?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recommended / Same Location -->
    <div class="tca-sp-section" style="border:none;">
        <div class="tca-sp-related-header">
            <h2 class="tca-sp-section-title">Recommended for you</h2>
            <div class="tca-sp-related-nav">
                <span onclick="document.getElementById('tca-carousel-location').scrollBy({left:-300, behavior:'smooth'})"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg></span>
                <span onclick="document.getElementById('tca-carousel-location').scrollBy({left:300, behavior:'smooth'})"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg></span>
            </div>
        </div>
        
        <div class="tca-sp-carousel" id="tca-carousel-location">
<?php
                    if ( $location_term_id ) {
                        $args = [
                            'post_type' => 'tca_unit',
                            'posts_per_page' => 10,
                            'post__not_in' => [ $main_unit_id ],
                            'tax_query' => [
                                'relation' => 'AND',
                                [
                                    'taxonomy' => 'tca_location',
                                    'field' => 'term_id',
                                    'terms' => $location_term_id,
                                ]
                            ]
                        ];
                        if ( $purpose_term_id ) {
                            $args['tax_query'][] = [
                                'taxonomy' => 'tca_purpose',
                                'field' => 'term_id',
                                'terms' => $purpose_term_id,
                            ];
                        }
                        $related_query = new WP_Query( $args );
                        if ( $related_query->have_posts() ) {
                            while ( $related_query->have_posts() ) {
                                $related_query->the_post(); // Loads correct $post_id inside template loop inherently via global scope
                                // Re-fetch post_id so template logic fetches loop unit not parent page unit.
                                $loop_id = get_the_ID();
                                set_query_var( 'tca_card_post_id', $loop_id );
                                include TCA_RE_PLUGIN_DIR . 'templates/unit-card-grid.php';
                            }
                            wp_reset_postdata();
                            
                            // Re-fetch main unit data because it was overwritten in the related units loop!
                            $reference = get_post_meta( $main_unit_id, '_tca_reference', true );
                            if (!$reference) $reference = 'MPS-' . str_pad($main_unit_id, 5, '0', STR_PAD_LEFT);
                            $purposes = get_the_terms($main_unit_id, 'tca_purpose');
                            $purpose_name = ($purposes && !is_wp_error($purposes)) ? $purposes[0]->name : 'Sale';
                            $locations = get_the_terms($main_unit_id, 'tca_location');
                            $location_name = ($locations && !is_wp_error($locations)) ? $locations[0]->name : '';
                            $project_status = get_post_meta( $main_unit_id, '_tca_project_status', true );
                            
                            $projects = get_the_terms( $main_unit_id, 'tca_project' );
                            $project_name = '';
                            if ( $projects && ! is_wp_error( $projects ) ) {
                                usort($projects, function($a, $b) {
                                    if ($a->count == $b->count) return 0;
                                    return ($a->count < $b->count) ? -1 : 1;
                                });
                                $project_name = $projects[0]->name;
                            }
                        } else {
                            echo '<p>No other units available in this location.</p>';
                        }
                    } else {
                        echo '<p>Location unassigned. Discover more on our property map.</p>';
                    }
                    ?>
        </div>
    </div>
</div>

<!-- Fullscreen Lightbox -->
<div id="tca-sp-lightbox" class="tca-sp-lightbox">
    <div class="tca-lb-header">
        <div class="tca-lb-title"><?php echo esc_html(strtoupper($purpose_name) . ' IN ' . strtoupper($location_name) . ' - ' . $reference); ?></div>
        <button class="tca-lb-close" id="tca-lb-close"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
    </div>
    
    <div class="tca-lb-main">
        <img id="tca-lb-main-img" src="" alt="Gallery Image">
    </div>

    <div class="tca-lb-footer">
        <button class="tca-lb-nav" document="prev"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg></button>
        <div class="tca-lb-thumbs">
            <?php foreach($main_gallery_images as $idx => $img): ?>
                <img src="<?php echo esc_url($img); ?>" data-index="<?php echo $idx; ?>" class="tca-lb-thumb <?php echo $idx===0?'active':''; ?>" alt="Thumb">
            <?php endforeach; ?>
        </div>
        <button class="tca-lb-nav" document="next"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg></button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Power the Property Cards (Grid Layouts inside Recommendations)
    document.querySelectorAll('.tca-cg-image-box, .tca-cl-image-box').forEach(box => {
        const slider = box.querySelector('.tca-gallery-slider');
        const left = box.querySelector('.ctrl-left');
        const right = box.querySelector('.ctrl-right');
        if(!slider) return;
        
        const slideNext = () => {
            let slideWidth = slider.clientWidth;
            if (slider.scrollLeft + slideWidth >= slider.scrollWidth - 10) {
                slider.scrollTo({ left: 0, behavior: 'smooth' }); // loop back
            } else {
                slider.scrollBy({ left: slideWidth, behavior: 'smooth' });
            }
        };

        const slidePrev = () => {
            let slideWidth = slider.clientWidth;
            if (slider.scrollLeft <= 0) {
                slider.scrollTo({ left: slider.scrollWidth, behavior: 'smooth' }); // loop end
            } else {
                slider.scrollBy({ left: -slideWidth, behavior: 'smooth' });
            }
        };
        
        if(left) left.addEventListener('click', (e) => { e.preventDefault(); slidePrev(); });
        if(right) right.addEventListener('click', (e) => { e.preventDefault(); slideNext(); });

        setInterval(slideNext, 4000);
    });

    // 2. Main Hero Gallery Logic (Drag to scroll + Arrows)
    const heroSlider = document.getElementById('overview-gallery');
    
    // Arrows
    const heroNextMenu = document.querySelector('.tca-hero-next');
    const heroPrevMenu = document.querySelector('.tca-hero-prev');

    if(heroSlider && heroNextMenu) {
        heroNextMenu.addEventListener('click', () => {
            let slideWidth = heroSlider.clientWidth;
            if (heroSlider.scrollLeft + slideWidth >= heroSlider.scrollWidth - 10) {
                heroSlider.scrollTo({ left: 0, behavior: 'smooth' }); 
            } else {
                heroSlider.scrollBy({ left: slideWidth, behavior: 'smooth' });
            }
        });
        heroPrevMenu.addEventListener('click', () => {
            let slideWidth = heroSlider.clientWidth;
            if (heroSlider.scrollLeft <= 0) {
                heroSlider.scrollTo({ left: heroSlider.scrollWidth, behavior: 'smooth' });
            } else {
                heroSlider.scrollBy({ left: -slideWidth, behavior: 'smooth' });
            }
        });
        
        // Mouse drag logic
        let isDown = false;
        let startX;
        let scrollLeft;

        heroSlider.addEventListener('mousedown', (e) => {
            isDown = true;
            heroSlider.classList.add('active');
            startX = e.pageX - heroSlider.offsetLeft;
            scrollLeft = heroSlider.scrollLeft;
        });
        heroSlider.addEventListener('mouseleave', () => { isDown = false; heroSlider.classList.remove('active'); });
        heroSlider.addEventListener('mouseup', () => { isDown = false; heroSlider.classList.remove('active'); });
        heroSlider.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - heroSlider.offsetLeft;
            const walk = (x - startX) * 2; // scroll-fast multiplier
            heroSlider.scrollLeft = scrollLeft - walk;
        });

        // ── Touch / Swipe support (iOS & Android) ─────────────────────────
        let touchStartX = 0;
        let touchEndX   = 0;

        heroSlider.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });

        heroSlider.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].screenX;
            const delta = touchStartX - touchEndX;
            const slideWidth = heroSlider.clientWidth;

            if (Math.abs(delta) > 50) { // 50px threshold — ignore micro-taps
                if (delta > 0) {
                    // Swipe left → next slide
                    if (heroSlider.scrollLeft + slideWidth >= heroSlider.scrollWidth - 10) {
                        heroSlider.scrollTo({ left: 0, behavior: 'smooth' });
                    } else {
                        heroSlider.scrollBy({ left: slideWidth, behavior: 'smooth' });
                    }
                } else {
                    // Swipe right → prev slide
                    if (heroSlider.scrollLeft <= 0) {
                        heroSlider.scrollTo({ left: heroSlider.scrollWidth, behavior: 'smooth' });
                    } else {
                        heroSlider.scrollBy({ left: -slideWidth, behavior: 'smooth' });
                    }
                }
            }
        }, { passive: true });
    }

    // 3. Lightbox Engine
    const galleryImages = <?php echo json_encode($main_gallery_images); ?>;
    const lightbox = document.getElementById('tca-sp-lightbox');
    const mainImg = document.getElementById('tca-lb-main-img');
    const thumbs = document.querySelectorAll('.tca-lb-thumb');
    let currentIndex = 0;

    // Bind Open Action to Expand Badge
    const expandBtn = document.getElementById('tca-open-lightbox');
    if(expandBtn) {
        expandBtn.addEventListener('click', function(e) {
            e.preventDefault();
            // Automatically open at the active slider index based on scroll position!
            let index = 0;
            if(heroSlider) {
                index = Math.round(heroSlider.scrollLeft / heroSlider.clientWidth);
            }
            openLightbox(index);
        });
    }

    const setLightboxImage = (index) => {
        if (index < 0) index = galleryImages.length - 1;
        if (index >= galleryImages.length) index = 0;
        currentIndex = index;
        mainImg.src = galleryImages[currentIndex];
        
        thumbs.forEach(t => t.classList.remove('active'));
        const activeThumb = document.querySelector(`.tca-lb-thumb[data-index="${currentIndex}"]`);
        if (activeThumb) {
            activeThumb.classList.add('active');
            activeThumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
    };

    const openLightbox = (index) => {
        setLightboxImage(index);
        lightbox.classList.add('open');
        document.body.style.overflow = 'hidden'; 
    };



    // Bind Close Button
    document.getElementById('tca-lb-close').addEventListener('click', () => {
        lightbox.classList.remove('open');
        document.body.style.overflow = '';
    });

    // Thumbnail Clicks
    thumbs.forEach(thumb => {
        thumb.addEventListener('click', function() {
            const idx = parseInt(this.getAttribute('data-index'));
            setLightboxImage(idx);
        });
    });

    // Nav Arrows
    document.querySelectorAll('.tca-lb-nav').forEach(btn => {
        btn.addEventListener('click', function() {
            if (this.getAttribute('document') === 'prev') {
                setLightboxImage(currentIndex - 1);
            } else {
                setLightboxImage(currentIndex + 1);
            }
        });
    });
});
</script>

<!-- Scripts for PDF Download & Web Share -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 0. Staff Mode Detection via URL parameter "?staff=tcaportal"
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('staff') === 'tcaportal') {
        localStorage.setItem('tca_staff_mode', 'true');
        // Clean URL to keep it pretty
        urlParams.delete('staff');
        const cleanUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
        window.history.replaceState({}, document.title, cleanUrl);
    }

    const isStaff = localStorage.getItem('tca_staff_mode') === 'true';
    const offerBtn = document.getElementById('tca-btn-offer');
    const offerModal = document.getElementById('tca-offer-modal');
    
    if (offerBtn && isStaff) {
        offerBtn.style.display = 'inline-flex';
    }

    // Modal Triggers
    if (offerBtn && offerModal) {
        offerBtn.addEventListener('click', function(e) {
            e.preventDefault();
            offerModal.style.display = 'flex';
        });

        const closeBtn = document.getElementById('tca-modal-close-btn');
        const cancelBtn = document.getElementById('tca-btn-cancel-offer');
        
        const closeModal = function() {
            offerModal.style.display = 'none';
        };

        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    }

    // Dynamic Columns & Rows Builder for Payment Plan
    const addRowBtn = document.getElementById('tca-btn-add-payment-row');
    const addColBtn = document.getElementById('tca-btn-add-column');
    const paymentTableBody = document.querySelector('#tca-payment-table tbody');
    const headersRow = document.getElementById('tca-payment-headers-row');

    if (addRowBtn && paymentTableBody && headersRow) {
        // Add Column function
        addColBtn.addEventListener('click', function(e) {
            e.preventDefault();
            // Create header cell
            const th = document.createElement('th');
            th.innerHTML = `
                <div class="tca-payment-table-th-wrapper">
                    <input type="text" class="tca-header-input" value="New Column" style="font-weight:bold; border:none; background:none; width:100%; outline:none; font-size:12px; color:#4b5563; padding-right:15px;">
                    <span class="tca-remove-col-btn">&times;</span>
                </div>
            `;
            
            // Insert before the last th (delete cell th)
            const ths = headersRow.querySelectorAll('th');
            headersRow.insertBefore(th, ths[ths.length - 1]);

            // Append input cell to each existing row
            paymentTableBody.querySelectorAll('tr').forEach(tr => {
                const td = document.createElement('td');
                td.innerHTML = `<input type="text" class="tca-form-control p-custom" placeholder="e.g. Value" required>`;
                const tds = tr.querySelectorAll('td');
                tr.insertBefore(td, tds[tds.length - 1]);
            });
        });

        // Delete Column via Event Delegation
        headersRow.addEventListener('click', function(e) {
            if (e.target.classList.contains('tca-remove-col-btn')) {
                e.preventDefault();
                
                const ths = Array.from(headersRow.querySelectorAll('th'));
                // ths.length - 1 to exclude the last empty helper cell th
                if (ths.length <= 2) {
                    alert('You must have at least one column.');
                    return;
                }

                const th = e.target.closest('th');
                const index = ths.indexOf(th);
                if (index !== -1) {
                    // Remove the th
                    th.remove();
                    // Remove corresponding td in all rows
                    paymentTableBody.querySelectorAll('tr').forEach(tr => {
                        const tds = tr.querySelectorAll('td');
                        if (tds[index]) tds[index].remove();
                    });
                }
            }
        });

        // Add Row function
        addRowBtn.addEventListener('click', function() {
            const numColumns = headersRow.querySelectorAll('th').length - 1;
            const tr = document.createElement('tr');

            for (let i = 0; i < numColumns; i++) {
                const td = document.createElement('td');
                td.innerHTML = `<input type="text" class="tca-form-control p-custom" placeholder="e.g. Value" required>`;
                tr.appendChild(td);
            }

            // Append action cell
            const actionTd = document.createElement('td');
            actionTd.innerHTML = `<button type="button" class="tca-btn-remove-row">&times;</button>`;
            tr.appendChild(actionTd);

            paymentTableBody.appendChild(tr);

            actionTd.querySelector('.tca-btn-remove-row').addEventListener('click', function() {
                tr.remove();
            });
        });

        // Add 1 default row
        addRowBtn.click();
    }

    // 1. Web Share API
    const shareBtn = document.getElementById('tca-btn-share');
    if (shareBtn) {
        shareBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const shareData = {
                title: <?php echo json_encode(get_the_title()); ?>,
                text: 'Check out this property: ' + <?php echo json_encode(get_the_title()); ?>,
                url: window.location.href
            };
            if (navigator.share) {
                navigator.share(shareData).catch(err => console.log('Share failed:', err));
            } else {
                navigator.clipboard.writeText(window.location.href);
                alert('Property link copied to clipboard!');
            }
        });
    }

    // Shared PDF Data Variables
    <?php
    // PHP-side data for the JS PDF
    ?>
    const galleryImages  = <?php echo json_encode($main_gallery_images); ?>;
    const watermarkB64   = <?php echo json_encode($watermark_b64); ?>;
    const pdfHeaderTitle = <?php echo json_encode($project_name ? $project_name : get_the_title()); ?>;
    const pdfTitle       = <?php echo json_encode(get_the_title()); ?>;
    const refNum         = <?php echo json_encode($reference); ?>;
    const pType          = <?php echo json_encode($purpose_name); ?>;
    const pLoc           = <?php echo json_encode($location_name); ?>;
    const isOffPlan      = <?php echo json_encode(strtolower($project_status) === 'off_plan'); ?>;
    const siteUrl        = <?php echo json_encode(get_permalink()); ?>;
    const pdfDescription = <?php
        // Get the raw post content, strip HTML tags, strip shortcodes, trim whitespace
        $raw_content = get_post_field('post_content', $main_unit_id);
        $clean_content = strip_tags(strip_shortcodes($raw_content));
        // Strip everything from "CONTACT US" onwards (boilerplate)
        $cut_markers = [
            'CONTACT US',
            'LIST YOUR PROPERTY',
            'Capital Avenue is a leading',
            'marketing@thecapitalavenue',
            'LIFESTYLE &amp; AMENITIES',
            'LIFESTYLE & AMENITIES',
        ];
        foreach ($cut_markers as $marker) {
            $pos = stripos($clean_content, $marker);
            if ($pos !== false) {
                $clean_content = substr($clean_content, 0, $pos);
            }
        }
        echo json_encode(trim($clean_content));
    ?>;
    const pdfPropDetails = <?php
        $details = [];
        if (!empty($project_name)) {
            $details[] = ['label' => 'Property Name', 'value' => $project_name];
        }
        if (!empty($developer_name) && $developer_name !== '—') {
            $details[] = ['label' => 'Developer', 'value' => $developer_name];
        }
        if (!empty($reference)) {
            $details[] = ['label' => 'Unit Reference', 'value' => $reference];
        }
        if (!empty($location_name)) {
            $details[] = ['label' => 'Location', 'value' => $location_name];
        }
        if (!empty($project_number)) {
            $details[] = ['label' => 'Project Number', 'value' => $project_number];
        }
        if (!empty($permit_number)) {
            $details[] = ['label' => 'Permit Number (Madhmoun)', 'value' => $permit_number];
        }
        $details[] = ['label' => 'Added On', 'value' => get_the_date('M d, Y')];
        echo json_encode($details);
    ?>;
    const pdfSpecs = <?php
        $specs = [];
        if (!empty($bedrooms)) {
            $specs[] = ['label' => 'Bedrooms', 'value' => $bedrooms];
        }
        if (!empty($bathrooms)) {
            $specs[] = ['label' => 'Bathroom', 'value' => $bathrooms];
        }
        if (!empty($area)) {
            $specs[] = ['label' => 'Area', 'value' => number_format($area) . ' Sq. Ft.'];
        }
        if (!empty($price_sqft)) {
            $specs[] = ['label' => 'Price per Sq. Ft.', 'value' => number_format($price_sqft) . ' AED Per Ft²'];
        }
        if (!empty($payment_plan)) {
            $specs[] = ['label' => 'Payment Plan', 'value' => $payment_plan];
        }
        if (!empty($completion)) {
            $specs[] = ['label' => 'Handover', 'value' => $completion];
        }
        echo json_encode($specs);
    ?>;


    // 2. Brochure Button
    const brochureBtn = document.getElementById('tca-btn-brochure');
    if (brochureBtn) {
        const isIOS = /iPhone|iPad|iPod/i.test(navigator.userAgent);

        // iOS → open the server-side print page (auto-prints, then Save as PDF in 1 tap)
        // Desktop / Android → generate PDF directly via JS (no extra steps, same as before)
        const iosBrochureUrl = <?php echo json_encode(
            add_query_arg([
                'tca_brochure' => '1',
                'unit_id'      => $main_unit_id,
                'token'        => hash_hmac('sha256', 'brochure_' . $main_unit_id, wp_salt('auth')),
            ], home_url('/'))
        ); ?>;

        brochureBtn.addEventListener('click', function(e) {
            e.preventDefault();

            if (isIOS) {
                // iPhone/iPad: open the print page → auto print dialog → Save as PDF
                window.open(iosBrochureUrl, '_blank');
                return;
            }

            // ── Desktop / Android: JS PDF generation ──────────────────────
            const originalText = brochureBtn.innerHTML;
            brochureBtn.innerHTML = 'Generating PDF...';
            brochureBtn.style.pointerEvents = 'none';

            setTimeout(function() {
                // Watermark divs (one per A4 page height)
                let watermarkDivs = '';
                const a4h = 1122.9;
                for (let i = 0; i < 6; i++) {
                    watermarkDivs += `<div style="position:absolute;top:${i*a4h}px;left:0;width:100%;height:${a4h}px;display:flex;align-items:center;justify-content:center;opacity:0.22;z-index:0;pointer-events:none;"><img src="${watermarkB64}" style="width:70%;height:auto;margin:0;"></div>`;
                }

                // ── Build HTML string ─────────────────────────────────────
                let htmlStr = `<div id="pdf-wrapper" style="width:794px;padding:40px;box-sizing:border-box;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;background:#fff;color:#333;position:relative;z-index:1;">
                    <style>
                        *{box-sizing:border-box;}
                        h1,h2,h3,h4{font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;font-weight:600;margin:0 0 5px 0;color:#0a3c61;page-break-after:avoid;}
                        h2{font-size:22px!important;} h3{font-size:18px!important;}
                        p{margin:0 0 10px 0;line-height:1.5;color:#444;}
                        .pdf-header{display:flex;justify-content:space-between;align-items:flex-end;border-bottom:3px solid #0a3c61;padding-bottom:8px;margin-bottom:12px;}
                        .pdf-header-left h1{font-size:22px;margin-bottom:4px;}
                        .pdf-header-left span{color:#666;font-size:12px;font-weight:500;text-transform:uppercase;letter-spacing:0.5px;}
                        .pdf-header-right{text-align:right;}
                        .pdf-header-right strong{font-size:12px;color:#222;}
                        .pdf-header-right span{font-size:12px;color:#0a3c61;font-weight:bold;}
                        .pdf-hero{width:100%;height:300px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.1);margin-bottom:10px;background-color:#f4f7f9;}
                        .pdf-price-box{display:flex;justify-content:space-between;background:#f4f7f9;padding:6px 12px;border-radius:8px;margin-bottom:10px;border:1px solid #e1e8ed;align-items:center;}
                        .pdf-price-box h2{font-size:20px;margin:0;color:#0a3c61;}
                        .pdf-price-box .price-label{font-size:10px;color:#666;text-transform:uppercase;font-weight:bold;letter-spacing:0.5px;display:block;}
                        .pdf-gallery-grid{display:flex;gap:8px;margin-bottom:10px;}
                        .pdf-gallery-grid img{width:32.5%;height:120px;object-fit:cover;border-radius:6px;}
                        .pdf-specs-grid{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;}
                        .pdf-spec-item{background:#fff;border:1px solid #e1e8ed;border-radius:6px;padding:5px 4px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;width:calc(33.33% - 6px);}
                        .tca-sp-details-label{font-size:9px;color:#666;text-transform:uppercase;font-weight:bold;letter-spacing:0.5px;margin-bottom:2px;display:block;}
                        .tca-sp-details-value{font-size:12px;color:#0a3c61;font-weight:bold;display:block;}
                        .pdf-section{margin-bottom:24px;}
                        .pdf-section-title{font-size:16px;border-bottom:2px solid #f0f0f0;padding-bottom:8px;margin-bottom:12px;}
                        .pdf-desc{font-size:13px;line-height:1.7;color:#444;}
                        /* Amenities – 2-column checklist with gold tick */
                        .pdf-amenity-checklist{display:block;width:100%;font-size:0;}
                        .pdf-amenity-check{display:inline-block;width:48%;box-sizing:border-box;font-size:12px;color:#0a3c61;vertical-align:top;padding:4px 0;}
                        .pdf-amenity-check::before{content:'✓';color:#c0a24c;font-weight:bold;font-size:13px;margin-right:6px;}
                        .pdf-details-grid{display:block;width:100%;font-size:0;}
                        .pdf-detail-row{display:inline-block;width:48%;box-sizing:border-box;border-bottom:1px dashed #ddd;padding:4px 10px 4px 0;font-size:13px;vertical-align:top;}
                        .pdf-detail-row span{float:left;}
                        .pdf-detail-row strong{float:right;}
                        .pdf-footer{margin-top:auto;padding-top:14px;border-top:2px solid #0a3c61;text-align:center;color:#555;font-size:11px;page-break-inside:avoid;}
                        .pdf-footer a{color:#0a3c61;text-decoration:none;}
                    </style>
                    ${watermarkDivs}
                    <div class="pdf-header">
                        <div class="pdf-header-left">
                            <h1>${pdfHeaderTitle}</h1>
                            <span>${pdfTitle}</span><br>
                            <span style="color:#666;font-size:13px;margin-top:5px;display:inline-block;">${pLoc} &bull; ${pType} ${isOffPlan ? '&bull; Off-plan' : ''}</span>
                        </div>
                        <div class="pdf-header-right">
                            <strong>Reference:</strong><br>
                            <span>${refNum}</span>
                        </div>
                    </div>`;

                // Hero image – embed src directly (same host, useCORS handles it)
                const liveImg = document.querySelector('.tca-hero-img-slide');
                if (liveImg && liveImg.src) {
                    htmlStr += `<div class="pdf-hero" style="background-image:url('${liveImg.src}');background-size:cover;background-position:center;"></div>`;
                }

                // Gallery
                if (galleryImages && galleryImages.length > 1) {
                    let gHTML = '<div class="pdf-gallery-grid">';
                    galleryImages.slice(1, 4).forEach(u => { gHTML += `<img src="${u}">`; });
                    gHTML += '</div>';
                    htmlStr += gHTML;
                }

                // Price
                const livePrice = document.querySelector('.tca-sp-price-title');
                if (livePrice) {
                    const pt = livePrice.innerText.trim();
                    htmlStr += `<div class="pdf-price-box"><div><span class="price-label">Total Price</span><h2>${pt}</h2></div><div style="text-align:right;"><span class="price-label">Generated On</span><span style="font-size:14px;color:#444;font-weight:bold;"><?php echo date('M d, Y'); ?></span></div></div>`;
                }

                // Specs – use clean server-side variable
                let specsHTML = '<div class="pdf-specs-grid">';
                pdfSpecs.forEach(spec => {
                    if (spec.value) {
                        specsHTML += `<div class="pdf-spec-item"><span class="tca-sp-details-label">${spec.label}</span><span class="tca-sp-details-value">${spec.value}</span></div>`;
                    }
                });
                htmlStr += specsHTML + '</div>';

                // Removed hardcoded page break to allow natural flow

                // Description
                if (pdfDescription) {
                    const descFormatted = pdfDescription.replace(/\n\n+/g, '</p><p>').replace(/\n/g, '<br>');
                    htmlStr += `<div class="pdf-section" style="page-break-inside:avoid;"><h3 class="pdf-section-title">Overview</h3><div class="pdf-desc"><p>${descFormatted}</p></div></div>`;
                }

                // Removed hardcoded page break to allow natural flow

                // Amenities – 2-column checklist with gold tick
                const amenityItems = document.querySelectorAll('.tca-sp-amenity-item');
                if (amenityItems.length > 0) {
                    let checkItems = '';
                    amenityItems.forEach(item => {
                        const t = item.innerText.trim();
                        if (t) checkItems += `<div class="pdf-amenity-check">${t}</div>`;
                    });
                    htmlStr += `<div class="pdf-section" style="page-break-inside:avoid;"><h3 class="pdf-section-title">Features &amp; Amenities</h3><div class="pdf-amenity-checklist">${checkItems}</div><div class="clearfix"></div></div>`;
                }

                // Property Reference – use clean server-side variable
                if (pdfPropDetails && pdfPropDetails.length > 0) {
                    htmlStr += `<div class="pdf-section" style="display:block;"><h3 class="pdf-section-title">Property Reference</h3><div class="pdf-details-grid">`;
                    pdfPropDetails.forEach(detail => {
                        htmlStr += `<div class="pdf-detail-row"><span>${detail.label}</span><strong>${detail.value}</strong></div>`;
                    });
                    htmlStr += `</div><div class="clearfix"></div></div>`;
                }

                // Footer – unit URL only (NO absolute watermark divs in HTML)
                htmlStr += `<div class="pdf-footer"><a href="${siteUrl}">${siteUrl}</a></div></div>`;

                // Generate & download
                const opt = {
                    margin:      [10, 0, 10, 0],
                    filename:    <?php echo json_encode(sanitize_title(get_the_title()) . '-brochure.pdf'); ?>,
                    image:       { type: 'jpeg', quality: 0.95 },
                    html2canvas: { scale: 2, useCORS: true, allowTaint: false, logging: false },
                    jsPDF:       { unit: 'mm', format: 'a4', orientation: 'portrait' },
                    pagebreak:   { mode: ['css', 'legacy'] }
                };

                html2pdf().set(opt).from(htmlStr).save().then(function() {
                    brochureBtn.innerHTML = originalText;
                    brochureBtn.style.pointerEvents = 'auto';
                }).catch(function(err) {
                    console.error('PDF Error:', err);
                    brochureBtn.innerHTML = originalText;
                    brochureBtn.style.pointerEvents = 'auto';
                    alert('Error generating PDF.');
                });

            }, 50);
        });
    }

    // 3. Sales Offer PDF Generation Trigger
    const submitOfferBtn = document.getElementById('tca-btn-submit-offer');
    if (submitOfferBtn && offerModal) {
        const isIOS = /iPhone|iPad|iPod/i.test(navigator.userAgent);
        const iosOfferUrl = <?php echo json_encode(
            add_query_arg([
                'tca_offer' => '1',
                'unit_id'   => $main_unit_id,
                'token'     => hash_hmac('sha256', 'offer_' . $main_unit_id, wp_salt('auth')),
            ], home_url('/'))
        ); ?>;

        submitOfferBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('TCA debug: submitOfferBtn clicked.');

            try {
                const layoutFileInput = document.getElementById('tca-offer-layout-file');
                console.log('TCA debug: layout file input:', layoutFileInput);

                // Compile dynamic columns and rows
                const paymentHeaders = Array.from(document.querySelectorAll('#tca-payment-headers-row th .tca-header-input')).map((input, idx) => input.value.trim() || `Column ${idx + 1}`);
                console.log('TCA debug: payment headers:', paymentHeaders);

                const paymentRows = [];
                document.querySelectorAll('#tca-payment-table tbody tr').forEach(tr => {
                    const cells = Array.from(tr.querySelectorAll('td input')).map(input => input.value.trim());
                    if (cells.some(c => c !== '')) {
                        paymentRows.push(cells);
                    }
                });
                console.log('TCA debug: payment rows:', paymentRows);

                const originalText = submitOfferBtn.innerHTML;
                submitOfferBtn.innerHTML = 'Preparing Offer...';
                submitOfferBtn.style.pointerEvents = 'none';

                // Read layout image file if uploaded
                const proceedWithGeneration = function(layoutBase64) {
                    console.log('TCA debug: proceedWithGeneration called. base64 present:', !!layoutBase64);
                    try {
                        // Hide modal immediately to prevent viewport rendering offsets
                        offerModal.style.display = 'none';

                        if (isIOS) {
                            console.log('TCA debug: iOS platform detected. Saving to localStorage and redirecting.');
                            localStorage.setItem('tca_offer_layout', layoutBase64 || '');
                            localStorage.setItem('tca_offer_payment_headers', JSON.stringify(paymentHeaders));
                            localStorage.setItem('tca_offer_payment_plan', JSON.stringify(paymentRows));
                            
                            submitOfferBtn.innerHTML = originalText;
                            submitOfferBtn.style.pointerEvents = 'auto';
                            window.open(iosOfferUrl, '_blank');
                        } else {
                            console.log('TCA debug: Desktop/Android platform detected. Initiating html2pdf rendering.');
                            // Generate PDF directly via html2pdf
                            setTimeout(function() {
                                try {
                                    // Watermark divs
                                    let watermarkDivs = '';
                                    const a4h = 1122.9;
                                    for (let i = 0; i < 8; i++) { // Generate up to 8 pages of watermarks
                                        watermarkDivs += `<div style="position:absolute;top:${i*a4h}px;left:0;width:100%;height:${a4h}px;display:flex;align-items:center;justify-content:center;opacity:0.22;z-index:0;pointer-events:none;"><img src="${watermarkB64}" style="width:70%;height:auto;margin:0;"></div>`;
                                    }

                                    // Inject PDF styles into <head> (style tags inside innerHTML are ignored by browsers)
                                    const pdfStyleTag = document.createElement('style');
                                    pdfStyleTag.id = 'tca-pdf-offer-styles';
                                    pdfStyleTag.textContent = `
                                        #tca-offer-pdf-wrapper *{box-sizing:border-box;}
                                        #tca-offer-pdf-wrapper h1,#tca-offer-pdf-wrapper h2,#tca-offer-pdf-wrapper h3,#tca-offer-pdf-wrapper h4{font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;font-weight:600;margin:0 0 5px 0;color:#0a3c61;page-break-after:avoid;}
                                        #tca-offer-pdf-wrapper h2{font-size:22px!important;} #tca-offer-pdf-wrapper h3{font-size:18px!important;}
                                        #tca-offer-pdf-wrapper p{margin:0 0 10px 0;line-height:1.5;color:#444;}
                                        #tca-offer-pdf-wrapper .pdf-header{display:flex;justify-content:space-between;align-items:flex-end;border-bottom:3px solid #0a3c61;padding-bottom:8px;margin-bottom:12px;}
                                        #tca-offer-pdf-wrapper .pdf-header-left h1{font-size:22px;margin-bottom:4px;}
                                        #tca-offer-pdf-wrapper .pdf-header-left span{color:#666;font-size:12px;font-weight:500;text-transform:uppercase;letter-spacing:0.5px;}
                                        #tca-offer-pdf-wrapper .pdf-header-right{text-align:right;}
                                        #tca-offer-pdf-wrapper .pdf-header-right strong{font-size:12px;color:#222;}
                                        #tca-offer-pdf-wrapper .pdf-header-right span{font-size:12px;color:#0a3c61;font-weight:bold;}
                                        #tca-offer-pdf-wrapper .pdf-hero{width:100%;height:300px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.1);margin-bottom:10px;background-color:#f4f7f9;}
                                        #tca-offer-pdf-wrapper .pdf-price-box{display:flex;justify-content:space-between;background:#f4f7f9;padding:6px 12px;border-radius:8px;margin-bottom:10px;border:1px solid #e1e8ed;align-items:center;}
                                        #tca-offer-pdf-wrapper .pdf-price-box h2{font-size:20px;margin:0;color:#0a3c61;}
                                        #tca-offer-pdf-wrapper .pdf-price-box .price-label{font-size:10px;color:#666;text-transform:uppercase;font-weight:bold;letter-spacing:0.5px;display:block;}
                                        #tca-offer-pdf-wrapper .pdf-gallery-grid{display:flex;gap:8px;margin-bottom:10px;}
                                        #tca-offer-pdf-wrapper .pdf-gallery-grid img{width:32.5%;height:120px;object-fit:cover;border-radius:6px;}
                                        #tca-offer-pdf-wrapper .pdf-specs-grid{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;}
                                        #tca-offer-pdf-wrapper .pdf-spec-item{background:#fff;border:1px solid #e1e8ed;border-radius:6px;padding:5px 4px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;width:calc(33.33% - 6px);}
                                        #tca-offer-pdf-wrapper .tca-sp-details-label{font-size:9px;color:#666;text-transform:uppercase;font-weight:bold;letter-spacing:0.5px;margin-bottom:2px;display:block;}
                                        #tca-offer-pdf-wrapper .tca-sp-details-value{font-size:12px;color:#0a3c61;font-weight:bold;display:block;}
                                        #tca-offer-pdf-wrapper .pdf-section{margin-bottom:14px;}
                                        #tca-offer-pdf-wrapper .pdf-section-title{font-size:16px;border-bottom:2px solid #f0f0f0;padding-bottom:8px;margin-bottom:12px;text-transform:none!important;}
                                        #tca-offer-pdf-wrapper .pdf-desc{font-size:13px;line-height:1.7;color:#444;}
                                        #tca-offer-pdf-wrapper .pdf-amenity-checklist{display:block;width:100%;font-size:0;}
                                        #tca-offer-pdf-wrapper .pdf-amenity-check{display:inline-block;width:48%;box-sizing:border-box;font-size:12px;color:#0a3c61;vertical-align:top;padding:4px 0;}
                                        #tca-offer-pdf-wrapper .pdf-amenity-check::before{content:'✓';color:#c0a24c;font-weight:bold;font-size:13px;margin-right:6px;}
                                        #tca-offer-pdf-wrapper .pdf-details-grid{display:flex;flex-wrap:wrap;width:100%;gap:10px 4%;}
                                        #tca-offer-pdf-wrapper .pdf-detail-row{display:flex;justify-content:space-between;width:48%;box-sizing:border-box;border-bottom:1px dashed #ddd;padding:4px 0;font-size:13px;}
                                        #tca-offer-pdf-wrapper .pdf-detail-row span{color:#444;}
                                        #tca-offer-pdf-wrapper .pdf-detail-row strong{color:#0a3c61;}
                                        #tca-offer-pdf-wrapper tr{page-break-inside:avoid;}
                                        #tca-offer-pdf-wrapper .pdf-footer{margin-top:auto;padding-top:14px;border-top:2px solid #0a3c61;text-align:center;color:#555;font-size:11px;page-break-inside:avoid;}
                                        #tca-offer-pdf-wrapper .pdf-footer a{color:#0a3c61;text-decoration:none;}
                                    `;
                                    document.head.appendChild(pdfStyleTag);

                                    // Build HTML template string (NO inline <style> needed — CSS is in <head>)
                                    let htmlStr = `<div id="tca-offer-pdf-wrapper" style="width:794px;padding:40px;box-sizing:border-box;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;background:#fff;color:#333;position:relative;z-index:1;">
                                        ${watermarkDivs}
                                        <div class="pdf-header">
                                            <div class="pdf-header-left">
                                                <h1>${pdfHeaderTitle}</h1>
                                                <span>${pdfTitle}</span><br>
                                                <span style="color:#666;font-size:13px;margin-top:5px;display:inline-block;">${pLoc} &bull; ${pType} ${isOffPlan ? '&bull; Off-plan' : ''}</span>
                                            </div>
                                            <div class="pdf-header-right">
                                                <strong>Reference:</strong><br>
                                                <span>${refNum}</span>
                                            </div>
                                        </div>`;

                                    // Hero image – embed src directly (same host, useCORS handles it)
                                    const liveImg = document.querySelector('.tca-hero-img-slide');
                                    if (liveImg && liveImg.src) {
                                        htmlStr += `<div class="pdf-hero" style="background-image:url('${liveImg.src}');background-size:cover;background-position:center;"></div>`;
                                    }

                                    // Gallery
                                    if (galleryImages && galleryImages.length > 1) {
                                        let gHTML = '<div class="pdf-gallery-grid">';
                                        galleryImages.slice(1, 4).forEach(u => { gHTML += `<img src="${u}">`; });
                                        gHTML += '</div>';
                                        htmlStr += gHTML;
                                    }

                                    // Price
                                    const livePrice = document.querySelector('.tca-sp-price-title');
                                    if (livePrice) {
                                        const pt = livePrice.innerText.trim();
                                        htmlStr += `<div class="pdf-price-box"><div><span class="price-label">Total Price</span><h2>${pt}</h2></div><div style="text-align:right;"><span class="price-label">Generated On</span><span style="font-size:14px;color:#444;font-weight:bold;"><?php echo date('M d, Y'); ?></span></div></div>`;
                                    }

                                    // Specs – use clean server-side variable
                                    let specsHTML = '<div class="pdf-specs-grid">';
                                    pdfSpecs.forEach(spec => {
                                        if (spec.value) {
                                            specsHTML += `<div class="pdf-spec-item"><span class="tca-sp-details-label">${spec.label}</span><span class="tca-sp-details-value">${spec.value}</span></div>`;
                                        }
                                    });
                                    htmlStr += specsHTML + '</div>';

                                    // Removed hardcoded page break to allow natural flow

                                    // Description – Shortened to first paragraph
                                    if (pdfDescription) {
                                        const shortDesc = pdfDescription.trim().split(/\n\n+/)[0];
                                        const descFormatted = shortDesc.replace(/\n/g, '<br>');
                                        htmlStr += `<div class="pdf-section" style="page-break-inside:avoid;"><h3 class="pdf-section-title">Overview</h3><div class="pdf-desc"><p>${descFormatted}</p></div></div>`;
                                    }

                                    // Unit Layout Plan
                                    if (layoutBase64) {
                                        htmlStr += `
                                            <div class="pdf-section" style="page-break-before:always;page-break-inside:avoid;text-align:center;">
                                                <h3 class="pdf-section-title" style="text-transform:none!important;text-align:left;">Unit Layout</h3>
                                                <div style="text-align:center;margin-top:10px;">
                                                    <img src="${layoutBase64}" style="max-width:100%;max-height:400px;object-fit:contain;border:1px solid #e5e7eb;border-radius:6px;padding:10px;">
                                                </div>
                                            </div>
                                        `;
                                    }

                                    // Removed hardcoded page break to allow natural flowing and prevent blank pages

                                    // Amenities – 2-column checklist with gold tick
                                    const amenityItems = document.querySelectorAll('.tca-sp-amenity-item');
                                    if (amenityItems.length > 0) {
                                        let checkItems = '';
                                        amenityItems.forEach(item => {
                                            const t = item.innerText.trim();
                                            if (t) checkItems += `<div class="pdf-amenity-check">${t}</div>`;
                                        });
                                        htmlStr += `<div class="pdf-section"><h3 class="pdf-section-title">Features &amp; Amenities</h3><div class="pdf-amenity-checklist">${checkItems}</div><div class="clearfix"></div></div>`;
                                    }

                                    // Remaining Payment (inline after Amenities)
                                    if (paymentRows.length > 0) {
                                        htmlStr += `
                                            <div class="pdf-section" style="display:block;">
                                                <h3 class="pdf-section-title" style="text-transform:none!important;">Remaining Payment</h3>
                                                <table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:20px;">
                                                    <thead>
                                                        <tr style="background:#0a3c61;color:#fff;page-break-inside:avoid;">
                                        `;
                                        paymentHeaders.forEach((headerText, index) => {
                                            let style = 'text-align:left;padding:6px 10px;color:#fff!important;';
                                            if (index === 0) style += 'border-radius:4px 0 0 4px;';
                                            else if (index === paymentHeaders.length - 1) style += 'border-radius:0 4px 4px 0;text-align:right;';
                                            else style += 'text-align:center;';
                                            htmlStr += `<th style="${style}">${headerText}</th>`;
                                        });
                                        htmlStr += `
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                        `;
                                        paymentRows.forEach((row, rowIndex) => {
                                            const bg = rowIndex % 2 === 0 ? '#f9fafb' : '#ffffff';
                                            htmlStr += `<tr style="background:${bg};border-bottom:1px solid #e5e7eb;page-break-inside:avoid;">`;
                                            row.forEach((cell, cellIndex) => {
                                                let style = 'padding:8px 10px;color:#111827;';
                                                if (cellIndex === 0) style += 'font-weight:500;';
                                                else if (cellIndex === row.length - 1) style += 'text-align:right;';
                                                else style += 'text-align:center;font-weight:bold;color:#0a3c61;';
                                                htmlStr += `<td style="${style}">${cell}</td>`;
                                            });
                                            htmlStr += `</tr>`;
                                        });
                                        htmlStr += `</tbody></table></div>`;
                                    }

                                    // Property Reference – use clean server-side variable
                                    if (pdfPropDetails && pdfPropDetails.length > 0) {
                                        htmlStr += `<div class="pdf-section" style="display:block;"><h3 class="pdf-section-title">Property Reference</h3><div class="pdf-details-grid">`;
                                        pdfPropDetails.forEach(detail => {
                                            htmlStr += `<div class="pdf-detail-row"><span>${detail.label}</span><strong>${detail.value}</strong></div>`;
                                        });
                                        htmlStr += `</div><div class="clearfix"></div></div>`;
                                    }

                                    // Footer – unit URL only (NO absolute watermark divs in HTML)
                                    htmlStr += `<div class="pdf-footer"><a href="${siteUrl}">${siteUrl}</a></div></div>`;

                                    // Generate PDF
                                    const opt = {
                                        margin:      [10, 0, 10, 0],
                                        filename:    `Offer-${refNum}.pdf`,
                                        image:       { type: 'jpeg', quality: 0.95 },
                                        html2canvas: { scale: 2, useCORS: true, allowTaint: false, logging: false },
                                        jsPDF:       { unit: 'mm', format: 'a4', orientation: 'portrait' },
                                        pagebreak:   { mode: ['css', 'legacy'], avoid: ['tr', 'table', '.pdf-section-avoid'] }
                                    };

                                    console.log('TCA debug: calling html2pdf bundles.');
                                    const _cleanupStyle = () => { const s = document.getElementById('tca-pdf-offer-styles'); if(s) s.remove(); };
                                    
                                    html2pdf().set(opt).from(htmlStr).save().then(function() {
                                        console.log('TCA debug: html2pdf save promise resolved.');
                                        submitOfferBtn.innerHTML = originalText;
                                        submitOfferBtn.style.pointerEvents = 'auto';
                                        _cleanupStyle();
                                    }).catch(function(err) {
                                        console.error('TCA debug: html2pdf internal save error:', err);
                                        submitOfferBtn.innerHTML = originalText;
                                        submitOfferBtn.style.pointerEvents = 'auto';
                                        _cleanupStyle();
                                        alert('Error saving PDF: ' + err.message);
                                    });
                                } catch(innerErr) {
                                    console.error('TCA debug: error inside setTimeout block:', innerErr);
                                    submitOfferBtn.innerHTML = originalText;
                                    submitOfferBtn.style.pointerEvents = 'auto';
                                    alert('Error compiling template: ' + innerErr.message);
                                }
                            }, 150);
                        }
                    } catch(procErr) {
                        console.error('TCA debug: error in proceedWithGeneration execution:', procErr);
                        submitOfferBtn.innerHTML = originalText;
                        submitOfferBtn.style.pointerEvents = 'auto';
                        alert('Error initiating generator: ' + procErr.message);
                    }
                };

                // Read & compress layout image file if uploaded
                if (layoutFileInput.files && layoutFileInput.files[0]) {
                    console.log('TCA debug: Reading layout image file.');
                    const file = layoutFileInput.files[0];
                    const reader = new FileReader();
                    reader.onload = function(readerEvt) {
                        console.log('TCA debug: layout image loaded. Compressing...');
                        const img = new Image();
                        img.onload = function() {
                            try {
                                const canvas = document.createElement('canvas');
                                let width = img.width;
                                let height = img.height;
                                const maxDim = 1000;
                                if (width > maxDim || height > maxDim) {
                                    if (width > height) {
                                        height = Math.round((height * maxDim) / width);
                                        width = maxDim;
                                    } else {
                                        width = Math.round((width * maxDim) / height);
                                        height = maxDim;
                                    }
                                }
                                canvas.width = width;
                                canvas.height = height;
                                const ctx = canvas.getContext('2d');
                                ctx.drawImage(img, 0, 0, width, height);
                                const compressedBase64 = canvas.toDataURL('image/jpeg', 0.8);
                                console.log('TCA debug: Compression complete.');
                                proceedWithGeneration(compressedBase64);
                            } catch(compErr) {
                                console.error('TCA debug: Compression failed, using fallback original.', compErr);
                                proceedWithGeneration(readerEvt.target.result);
                            }
                        };
                        img.onerror = function() {
                            console.warn('TCA debug: Image parse failed, using fallback.');
                            proceedWithGeneration(readerEvt.target.result);
                        };
                        img.src = readerEvt.target.result;
                    };
                    reader.onerror = function(readErr) {
                        console.error('TCA debug: FileReader error:', readErr);
                        alert('Failed to read layout image file.');
                        submitOfferBtn.innerHTML = originalText;
                        submitOfferBtn.style.pointerEvents = 'auto';
                    };
                    reader.readAsDataURL(file);
                } else {
                    console.log('TCA debug: No layout image file selected.');
                    proceedWithGeneration(null);
                }
            } catch(e) {
                console.error('TCA debug: click handler crashed.', e);
                submitOfferBtn.innerHTML = originalText;
                submitOfferBtn.style.pointerEvents = 'auto';
                alert('A runtime error occurred: ' + e.message);
            }
        });
    }

    function sanitizeTitle(str) {
        return str.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
    }

});
</script>

<!-- Sales Offer Dynamic Modal Form -->
<div id="tca-offer-modal" class="tca-modal">
    <div class="tca-modal-content">
        <div class="tca-modal-header">
            <h3>Generate Sales Offer</h3>
            <button class="tca-modal-close" id="tca-modal-close-btn">&times;</button>
        </div>
        <div class="tca-modal-body">
            <div class="tca-form-group">
                <label>Unit Layout Image</label>
                <input type="file" id="tca-offer-layout-file" class="tca-form-control" accept="image/*">
            </div>
            <div class="tca-form-group">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <label style="margin-bottom:0;">Remaining Payment Milestones</label>
                    <button type="button" class="tca-btn-secondary" id="tca-btn-add-column" style="padding:4px 8px; font-size:11px; margin: 0;">+ Add Column</button>
                </div>
                <table class="tca-table-builder" id="tca-payment-table">
                    <thead>
                        <tr id="tca-payment-headers-row">
                            <th>
                                <div class="tca-payment-table-th-wrapper">
                                    <input type="text" class="tca-header-input" value="Milestone / Installment" style="font-weight:bold; border:none; background:none; width:100%; outline:none; font-size:12px; color:#4b5563; padding-right:15px;">
                                    <span class="tca-remove-col-btn">&times;</span>
                                </div>
                            </th>
                            <th>
                                <div class="tca-payment-table-th-wrapper">
                                    <input type="text" class="tca-header-input" value="Percentage" style="font-weight:bold; border:none; background:none; width:100%; outline:none; font-size:12px; color:#4b5563; padding-right:15px;">
                                    <span class="tca-remove-col-btn">&times;</span>
                                </div>
                            </th>
                            <th>
                                <div class="tca-payment-table-th-wrapper">
                                    <input type="text" class="tca-header-input" value="Period / Date" style="font-weight:bold; border:none; background:none; width:100%; outline:none; font-size:12px; color:#4b5563; padding-right:15px;">
                                    <span class="tca-remove-col-btn">&times;</span>
                                </div>
                            </th>
                            <th style="width:24px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dynamically added rows -->
                    </tbody>
                </table>
                <button type="button" class="tca-btn-add-row" id="tca-btn-add-payment-row">+ Add Installment Row</button>
            </div>
        </div>
        <div class="tca-modal-footer">
            <button class="tca-btn-secondary" id="tca-btn-cancel-offer">Cancel</button>
            <button class="tca-btn-primary" id="tca-btn-submit-offer">Generate Offer PDF</button>
        </div>
    </div>
</div>

<?php 
get_footer(); 
?>
