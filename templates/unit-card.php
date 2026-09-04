<?php
/**
 * Template for displaying a single unit card in the frontend grid.
 * This is included inside the loop.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
 
// Retrieve Data
$post_id = get_the_ID();
$price = get_post_meta( $post_id, '_tca_price', true );
$bedrooms = get_post_meta( $post_id, '_tca_bedrooms', true );
$bathrooms = get_post_meta( $post_id, '_tca_bathrooms', true );
$area = get_post_meta( $post_id, '_tca_area', true );
$featured = get_post_meta( $post_id, '_tca_featured', true );

// Taxonomies
$types = get_the_terms( $post_id, 'tca_property_type' );
$type_name = ( $types && ! is_wp_error( $types ) ) ? $types[0]->name : 'Property';

$locations = get_the_terms( $post_id, 'tca_location' );
$location_name = ( $locations && ! is_wp_error( $locations ) ) ? $locations[0]->name : 'Location N/A';

$purposes = get_the_terms( $post_id, 'tca_purpose' );
$purpose_name = ( $purposes && ! is_wp_error( $purposes ) ) ? $purposes[0]->name : '';

$thumbnail_url = get_the_post_thumbnail_url( $post_id, 'large' );
if ( ! $thumbnail_url ) {
    $thumbnail_url = 'https://thecapitalavenue.com/wp-content/uploads/2023/12/thecapitalavenue.png' . $post_id; // Premium placeholder
}
?>

<div class="tca-unit-card">
    <a href="<?php the_permalink(); ?>" class="tca-unit-link">
        <div class="tca-unit-image-wrapper">
            <img src="<?php echo esc_url($thumbnail_url); ?>" alt="<?php the_title_attribute(); ?>" class="tca-unit-img">
            
            <div class="tca-badges">
                <?php if ( $featured == '1' ): ?>
                    <span class="tca-badge tca-badge-featured">★ Featured</span>
                <?php endif; ?>
                <?php if ( $purpose_name ): ?>
                    <span class="tca-badge tca-badge-purpose">For <?php echo esc_html( $purpose_name ); ?></span>
                <?php endif; ?>
            </div>

            <div class="tca-unit-price-overlay">
                <span class="tca-price-label">AED</span> <?php echo number_format_i18n( (float) $price ); ?>
            </div>
        </div>

        <div class="tca-unit-content">
            <div class="tca-unit-meta">
                <span class="tca-property-type"><?php echo esc_html( $type_name ); ?></span>
                <span class="tca-location-pin">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                    <?php echo esc_html( $location_name ); ?>
                </span>
            </div>

            <h3 class="tca-unit-title"><?php the_title(); ?></h3>

            <div class="tca-unit-specs">
                <?php if ( $bedrooms !== '' ): ?>
                <div class="tca-spec">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M20 9.55V3h-2v2H6V3H4v6.55c-1.19.69-2 1.97-2 3.45v5h2v2h2v-2h12v2h2v-2h2v-5c0-1.48-.81-2.76-2-3.45zM11 9H6V7h5v2zm7 0h-5V7h5v2z"/></svg>
                    <span><?php echo ( $bedrooms === '0' || $bedrooms === 0 ) ? 'Studio' : esc_html($bedrooms) . ' Beds'; ?></span>
                </div>
                <?php else: ?>
                <div class="tca-spec">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg>
                    <span><?php echo esc_html( $type_name ); ?></span>
                </div>
                <?php endif; ?>

                <?php if ( $bathrooms !== '' ): ?>
                <div class="tca-spec">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M7 7m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M7 11v8h10v-8H7z"/><path d="M22 13c0-2-2-2-2-4 0-1.66-1.34-3-3-3s-3 1.34-3 3c0 2-2 2-2 4h10z"/></svg>
                    <span><?php echo esc_html( $bathrooms ); ?> Baths</span>
                </div>
                <?php endif; ?>

                <?php if ( $area ): ?>
                <div class="tca-spec">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><path d="M3 9h18M9 21V9"/></svg>
                    <span><?php echo number_format_i18n( (float) $area ); ?> Sq Ft</span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="tca-unit-footer">
                <span class="tca-view-btn">View Details &rarr;</span>
            </div>
        </div>
    </a>
</div>
