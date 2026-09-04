<?php
/**
 * Template for displaying a Grid (Portrait) style property card.
 * Designed to explicitly match user's Image 2 screenshot.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Global Variables
$post_id = get_the_ID();
$price = get_post_meta($post_id, '_tca_price', true);
$bedrooms = get_post_meta($post_id, '_tca_bedrooms', true);
$bathrooms = get_post_meta($post_id, '_tca_bathrooms', true);
$area = get_post_meta($post_id, '_tca_area', true);
$price_sqft = get_post_meta($post_id, '_tca_price_sqft', true);
$featured = get_post_meta($post_id, '_tca_featured', true);
$verified = get_post_meta($post_id, '_tca_verified', true);
$listed_by = get_post_meta($post_id, '_tca_listed_by', true);
$completion = get_post_meta($post_id, '_tca_completion_date', true);

// Agent specific
$agent_name = get_post_meta($post_id, '_tca_agent_name', true);
$agent_phone = get_post_meta($post_id, '_tca_agent_phone', true);
$agent_avatar = get_post_meta($post_id, '_tca_agent_avatar', true);
if (!$agent_avatar)
    $agent_avatar = "https://ui-avatars.com/api/?name=" . urlencode($agent_name ? $agent_name : 'Agent') . "&background=1e293b&color=fff";

// Taxonomies
$types = get_the_terms($post_id, 'tca_property_type');
$type_name = ($types && !is_wp_error($types)) ? $types[0]->name : 'Apartment';

$locations = get_the_terms($post_id, 'tca_location');
$location_name = ($locations && !is_wp_error($locations)) ? $locations[0]->name : '';

$purposes = get_the_terms($post_id, 'tca_purpose');
$purpose_name = ($purposes && !is_wp_error($purposes)) ? $purposes[0]->name : '';

$location_display = esc_html($location_name);
if (strtolower($purpose_name) == 'rent') {
    $location_display .= ' | Ready to move';
} elseif ($completion) {
    $time = strtotime($completion);
    $formatted_completion = $time ? 'Q' . ceil(date('n', $time)/3) . ' ' . date('Y', $time) : $completion;
    $location_display .= ' | Handover ' . esc_html($formatted_completion);
}

$thumbnail_url = get_the_post_thumbnail_url($post_id, 'large');
if (!$thumbnail_url) {
    $thumbnail_url = 'https://picsum.photos/800/600?random=' . $post_id;
}

$listing_date = get_the_date('Y-m-d');
$days_ago = round((time() - strtotime($listing_date)) / (60 * 60 * 24));
$listed_text = ($days_ago == 0) ? 'Listed today' : 'Listed ' . $days_ago . ' days ago';

$projects = get_the_terms($post_id, 'tca_project');
$project_name = ($projects && !is_wp_error($projects)) ? $projects[0]->name : $listed_text;

// Fetch developer taxonomy terms and logo
$developers = get_the_terms($post_id, 'tca_developer');
$developer_logo_url = '';
$developer_name = '';
if ($developers && !is_wp_error($developers)) {
    $developer_term = $developers[0];
    $developer_name = $developer_term->name;
    
    $meta_keys = [
        'crafto_custom_meta_developer_logo',
        'crafto_custom_meta_logo',
        'developer_logo',
        'logo',
        'tca_developer_logo',
        'image'
    ];
    foreach ($meta_keys as $key) {
        $meta_val = get_term_meta($developer_term->term_id, $key, true);
        if ($meta_val) {
            if (is_numeric($meta_val)) {
                $img_url = wp_get_attachment_image_url($meta_val, 'full');
                if ($img_url) {
                    $developer_logo_url = $img_url;
                    break;
                }
            } else if (filter_var($meta_val, FILTER_VALIDATE_URL) || strpos($meta_val, '/') === 0) {
                $developer_logo_url = $meta_val;
                break;
            }
        }
    }
}

$gallery_meta = get_post_meta($post_id, '_tca_gallery', true);
$gallery_images = [];

if ($thumbnail_url) {
    $gallery_images[] = $thumbnail_url;
}

if ($gallery_meta) {
    $extra_images = array_filter(array_map('trim', explode(',', $gallery_meta)));
    $gallery_images = array_merge($gallery_images, $extra_images);
}

if (empty($gallery_images)) {
    $gallery_images[] = 'https://picsum.photos/800/600?random=' . $post_id;
}
?>

<div class="tca-card-grid">
    <?php if ( get_post_meta($post_id, '_tca_featured', true) == '1' ): ?>
        <div class="tca-cg-featured-badge">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            Featured
        </div>
    <?php endif; ?>
    <?php if ( $verified == '1' && get_post_meta($post_id, '_tca_featured', true) != '1' ): ?>
        <div class="tca-cg-badge-verified">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
            Verified
        </div>
    <?php endif; ?>
    <?php 
    $status_val = get_post_meta($post_id, '_tca_project_status', true); 
    if ($status_val): 
        $status_label = ($status_val === 'off_plan') ? 'Off-plan' : 'Ready';
    ?>
        <div class="tca-cg-status-badge">
            <?php echo esc_html($status_label); ?>
        </div>
    <?php endif; ?>
    <div class="tca-cg-image-box">
        <div class="tca-gallery-slider">
            <?php foreach ($gallery_images as $img_url): ?>
                <div class="tca-gallery-slide">
                    <a href="<?php the_permalink(); ?>">
                        <img src="<?php echo esc_url($img_url); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="tca-cg-carousel-controls">
            <span class="ctrl-left"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white"
                    stroke-width="2">
                    <path d="M15 18l-6-6 6-6" />
                </svg></span>
            <span class="ctrl-right"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white"
                    stroke-width="2">
                    <path d="M9 18l6-6-6-6" />
                </svg></span>
        </div>

        <div class="tca-cg-carousel-dots">
            <?php foreach ($gallery_images as $idx => $img_url): ?>
                <span class="dot <?php echo ($idx === 0) ? 'active' : ''; ?>"></span>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="tca-cg-content" style="padding: 15px; position: relative; display: flex; flex-direction: column; flex-grow: 1;">
        <div class="tca-cg-header-row" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px; padding-top:10px;">
            <div class="tca-cg-title-price" style="min-width: 0; flex: 1;">
                <div class="tca-cg-project-name" style="font-size:14px; color:#666; font-weight:500; margin-bottom:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    <?php echo esc_html($project_name); ?>
                </div>
                <h2 class="tca-cg-price-small" style="margin: 5px 0 10px 0; font-size: 30px; font-weight: 500; line-height: 1; color: var(--tca-secondary);">
                    <?php echo number_format_i18n((float) $price); ?> AED<?php echo (strtolower($purpose_name) == 'rent') ? '/Year' : ''; ?>
                </h2>
            </div>
            <?php if (!empty($developer_logo_url)): ?>
                <div class="tca-cg-developer-logo" title="<?php echo esc_attr($developer_name); ?>" style="flex-shrink:0; margin-left:12px; background: #fff; padding: 4px; border-radius: 4px; border: 1px solid #e1e8ed; display: flex; align-items: center; justify-content: center;">
                    <img src="<?php echo esc_url($developer_logo_url); ?>" alt="<?php echo esc_attr($developer_name); ?>" style="max-height: 40px; max-width: 60px; object-fit: contain; display: block;">
                </div>
            <?php endif; ?>
        </div>

        <div class="tca-cg-title-desc" style="font-size:14px; color:#444; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-bottom:12px;">
            <a href="<?php the_permalink(); ?>" style="color:inherit; text-decoration:none;"><?php the_title(); ?></a>
        </div>

        <div class="tca-cg-specs" style="display:flex; flex-wrap:wrap; gap:12px; align-items:center; font-size:13px; color:#555; margin-bottom:12px; border-bottom:1px solid #f0f0f0; padding-bottom:12px;">
            <?php if ($bedrooms !== ''): ?>
                <span style="display:flex; align-items:center; gap:4px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M20 9.55V3h-2v2H6V3H4v6.55c-1.19.69-2 1.97-2 3.45v5h2v2h2v-2h12v2h2v-2h2v-5c0-1.48-.81-2.76-2-3.45zM11 9H6V7h5v2zm7 0h-5V7h5v2z" />
                    </svg>
                    <?php echo ($bedrooms === '0' || $bedrooms === 0) ? 'Studio' : esc_html($bedrooms) . ' Beds'; ?>
                </span> <span class="tca-sep">|</span>
            <?php endif; ?>

            <?php if ($bathrooms !== ''): ?>
                <span style="display:flex; align-items:center; gap:4px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M7 7m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                        <path d="M7 11v8h10v-8H7z" />
                        <path d="M22 13c0-2-2-2-2-4 0-1.66-1.34-3-3-3s-3 1.34-3 3c0 2-2 2-2 4h10z" />
                    </svg>
                    <?php echo esc_html($bathrooms); ?>
                </span> <span class="tca-sep">|</span>
            <?php endif; ?>
 
            <?php if ($area): ?>
                <span style="display:flex; align-items:center; gap:4px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                        <path d="M3 9h18M9 21V9" />
                    </svg>
                    <?php echo number_format_i18n((float) $area); ?> sqft
                </span> <span class="tca-sep">|</span>
            <?php endif; ?>

            <span style="display:flex; align-items:center; gap:4px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M3 21h18v-2H3v2zm9-18v14l-4-4h8l-4 4z" />
                </svg>
                <?php echo esc_html($type_name); ?>
            </span>
        </div>

        <div class="tca-cg-location" style="display:flex; align-items:center; gap:6px; font-size:13px; color:#666; margin-bottom:16px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                <circle cx="12" cy="10" r="3"></circle>
            </svg>
            <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo esc_html($location_display); ?></span>
        </div>

        <div class="tca-cg-actions" style="display:flex; gap:8px; margin-top:auto;">
            <?php
            $call_number = '+971505026788';
            $wa_number = '+971527224544';
            $property_url = get_permalink();
            $property_title = get_the_title();
            $reference = get_post_meta($post_id, '_tca_reference', true);
            if (!$reference) $reference = 'MPS-' . str_pad($post_id, 5, '0', STR_PAD_LEFT);
            $wa_text = urlencode("Hi! I'm interested in your listing [Ref: {$reference}] on website {$property_title} | {$property_url}");

            $call_link = 'tel:' . str_replace(' ', '', $call_number);
            $wa_link = 'https://wa.me/' . str_replace([' ', '+'], ['', ''], $wa_number) . '?text=' . $wa_text;
            ?>
            <a href="#enquiry" class="tca-cg-btn tca-cg-btn-enquire tca-enquire-btn" data-property-title="<?php echo esc_attr($property_title); ?>" data-property-url="<?php echo esc_url($property_url); ?>" data-property-ref="<?php echo esc_attr($reference); ?>" style="flex:1;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                </svg>
                Preview
            </a>
            <a href="<?php echo $wa_link; ?>" class="tca-cg-btn tca-cg-btn-wa" target="_blank" style="flex:1;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" />
                </svg>
                WhatsApp
            </a>
        </div>
    </div>
</div>