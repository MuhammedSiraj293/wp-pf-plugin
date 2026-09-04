<?php
/**
 * TCA Unit Brochure – iPhone / iPad print page
 * Design mirrors the JS-generated PDF exactly (same colours, layout, sections).
 * window.print() fires automatically on iOS → user taps Save as PDF in 1 tap.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ── Auth: permanent HMAC token (no expiry) ─────────────────────────────────
$unit_id  = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : 0;
$token    = isset($_GET['token'])   ? sanitize_text_field($_GET['token']) : '';
$expected = hash_hmac('sha256', 'brochure_' . $unit_id, wp_salt('auth'));

if ( ! $unit_id || ! hash_equals($expected, $token) ) {
    wp_die( 'Invalid link.', 'Error', ['response' => 403] );
}

$post = get_post( $unit_id );
if ( ! $post || $post->post_type !== 'tca_unit' ) {
    wp_die( 'Unit not found.', 'Error', ['response' => 404] );
}

// ── Meta ───────────────────────────────────────────────────────────────────
$price      = get_post_meta( $unit_id, '_tca_price',      true );
$bedrooms   = get_post_meta( $unit_id, '_tca_bedrooms',   true );
$bathrooms  = get_post_meta( $unit_id, '_tca_bathrooms',  true );
$area       = get_post_meta( $unit_id, '_tca_area',       true );
$price_sqft = get_post_meta( $unit_id, '_tca_price_sqft', true );
if ( ! $price_sqft && $price && $area ) $price_sqft = round($price / $area);

$reference    = get_post_meta($unit_id,'_tca_reference',       true);
if (!$reference) $reference = 'MPS-' . str_pad($unit_id,5,'0',STR_PAD_LEFT);
$status       = get_post_meta($unit_id,'_tca_status',          true) ?: 'Ready';
$payment_plan = get_post_meta($unit_id,'_tca_payment_plan',    true);
$completion   = get_post_meta($unit_id,'_tca_completion_date', true);
if ($completion) {
    $t = strtotime($completion);
    if ($t) $completion = 'Q'.ceil(date('n',$t)/3).' '.date('Y',$t);
}
$project_number = get_post_meta($unit_id,'_tca_project_number', true);
$permit_number  = get_post_meta($unit_id,'_tca_permit_number',  true);
$project_status = get_post_meta($unit_id,'_tca_project_status', true);

$purposes   = get_the_terms($unit_id,'tca_purpose');
$purpose    = ($purposes && !is_wp_error($purposes)) ? $purposes[0]->name : 'Sale';
$locations  = get_the_terms($unit_id,'tca_location');
$location   = ($locations && !is_wp_error($locations)) ? $locations[0]->name : '';
$projects   = get_the_terms($unit_id,'tca_project');
$project    = '';
if ($projects && !is_wp_error($projects)) {
    usort($projects, fn($a,$b) => $a->count <=> $b->count);
    $project = $projects[0]->name;
}
$developers = get_the_terms($unit_id,'tca_developer');
$developer  = ($developers && !is_wp_error($developers)) ? $developers[0]->name : '—';
$prop_types = get_the_terms($unit_id,'tca_property_type');
$prop_type  = ($prop_types && !is_wp_error($prop_types)) ? $prop_types[0]->name : '—';

// Flat amenity list (all items, no grouping — matches checklist style)
$amenities_raw = get_the_terms($unit_id,'tca_amenity');
$all_amenity_names = [];
if ($amenities_raw && !is_wp_error($amenities_raw)) {
    foreach ($amenities_raw as $am) {
        // Only add leaf items (items that are themselves children, or top-level with no children used)
        $all_amenity_names[] = $am->name;
    }
    // De-duplicate parent names that appear as children (keep unique)
    $all_amenity_names = array_values(array_unique($all_amenity_names));
    sort($all_amenity_names);
}

// Gallery → Base64 (avoid CORS issues in print rendering)
$gallery_meta   = get_post_meta($unit_id,'_tca_gallery',true);
$gallery_images = [];
$thumb          = get_the_post_thumbnail_url($unit_id,'large');
if ($thumb) $gallery_images[] = $thumb;
if ($gallery_meta) {
    foreach (array_filter(array_map('trim',explode(',',$gallery_meta))) as $u) {
        $gallery_images[] = $u;
    }
}
if (empty($gallery_images)) {
    $gallery_images[] = 'https://thecapitalavenue.com/wp-content/uploads/2026/04/THE-CAPITAL-AVENUE.jpg';
}
$gallery_images = array_slice($gallery_images, 0, 4);

function tca_bprint_img($url) {
    $upload = wp_upload_dir();
    $local  = str_replace($upload['baseurl'], $upload['basedir'], $url);
    if (file_exists($local)) {
        $data = file_get_contents($local);
        $mime = mime_content_type($local) ?: 'image/jpeg';
        return 'data:'.$mime.';base64,'.base64_encode($data);
    }
    $resp = wp_remote_get($url,['timeout'=>8,'sslverify'=>false]);
    if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
        $ct = wp_remote_retrieve_header($resp,'content-type') ?: 'image/jpeg';
        return 'data:'.$ct.';base64,'.base64_encode(wp_remote_retrieve_body($resp));
    }
    return $url;
}
$gallery_b64 = array_map('tca_bprint_img', $gallery_images);

// Watermark
$watermark_path = plugin_dir_path(dirname(__FILE__)) . 'assets/images/watermark.png';
$watermark_b64  = '';
if (file_exists($watermark_path)) {
    $watermark_b64 = 'data:image/png;base64,'.base64_encode(file_get_contents($watermark_path));
}

// Description — unit's own post_content only, with proper paragraph formatting
setup_postdata($post);
$description = get_the_content(null, false, $post);
wp_reset_postdata();
$description = wpautop($description);                                          // converts \n\n → <p> tags
$description = strip_tags($description, '<p><br><strong><em><ul><ol><li>');
$description = trim($description);

$price_fmt = $price ? 'AED '.number_format($price) : 'Price on Request';
$title     = get_the_title($unit_id);
$unit_url  = get_permalink($unit_id);

// Header meta line
$meta_parts = [];
if ($location) $meta_parts[] = $location;
if ($prop_type && $prop_type !== '—') $meta_parts[] = $prop_type;
if (strtolower($project_status) === 'off_plan') $meta_parts[] = 'Off-plan';
$header_meta = implode(' • ', $meta_parts);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html($title); ?> – Brochure</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  html, body {
    background: #e8eaed;
    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    color: #333;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  /* ── Page ───────────────────────────────────────────────── */
  .page {
    width: 210mm;
    min-height: 297mm;
    background: #fff;
    margin: 0 auto;
    padding: 40px 40px 30px;
    position: relative;
    overflow: hidden;
  }

  /* Watermark */
  .watermark {
    position: absolute; top: 0; left: 0;
    width: 100%; height: 100%;
    background-image: url('<?php echo $watermark_b64; ?>');
    background-position: center; background-repeat: no-repeat;
    background-size: 80%; opacity: 0.22;
    z-index: 0; pointer-events: none;
  }
  .content { position: relative; z-index: 1; }

  /* ── Shared heading colours ──────────────────────────────── */
  h1, h2, h3, h4 {
    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    font-weight: 600; color: #0a3c61;
  }

  /* ── Header (matches JS PDF pdf-header) ─────────────────── */
  .pdf-header {
    display: flex; justify-content: space-between; align-items: flex-end;
    border-bottom: 3px solid #0a3c61;
    padding-bottom: 8px; margin-bottom: 12px;
  }
  .pdf-header-left h1 { font-size: 22px; margin-bottom: 4px; }
  .pdf-header-left .hl-sub {
    color: #666; font-size: 12px; font-weight: 500;
    text-transform: uppercase; letter-spacing: 0.5px; display: block;
  }
  .pdf-header-left .hl-meta {
    color: #666; font-size: 13px; margin-top: 5px; display: block;
  }
  .pdf-header-right { text-align: right; }
  .pdf-header-right strong { font-size: 12px; color: #222; }
  .pdf-header-right span   { font-size: 12px; color: #0a3c61; font-weight: bold; }

  /* ── Hero image (matches pdf-hero) ──────────────────────── */
  .pdf-hero { margin-bottom: 10px; }
  .pdf-hero img {
    width: 100%; height: 300px; object-fit: cover;
    border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    display: block;
  }

  /* ── Gallery (matches pdf-gallery-grid) ─────────────────── */
  .pdf-gallery-grid { display: flex; gap: 8px; margin-bottom: 10px; }
  .pdf-gallery-grid img { flex: 1; height: 120px; object-fit: cover; border-radius: 6px; }

  /* ── Price box (matches pdf-price-box) ──────────────────── */
  .pdf-price-box {
    display: flex; justify-content: space-between; align-items: center;
    background: #f4f7f9; padding: 6px 12px; border-radius: 8px;
    margin-bottom: 10px; border: 1px solid #e1e8ed;
  }
  .pdf-price-box h2 { font-size: 20px; margin: 0; color: #0a3c61; }
  .price-label { font-size: 10px; color: #666; text-transform: uppercase; font-weight: bold; letter-spacing: 0.5px; display: block; }
  .price-date  { font-size: 14px; color: #444; font-weight: bold; }

  /* ── Specs (matches pdf-specs-grid) ─────────────────────── */
  .pdf-specs-grid {
    display: grid; grid-template-columns: repeat(3, 1fr);
    gap: 8px; margin-bottom: 10px;
  }
  .pdf-spec-item {
    background: #fff; border: 1px solid #e1e8ed; border-radius: 6px;
    padding: 5px 4px; text-align: center;
  }
  .spec-label { font-size: 9px; color: #666; text-transform: uppercase; font-weight: bold; letter-spacing: 0.5px; margin-bottom: 2px; display: block; }
  .spec-value { font-size: 12px; color: #0a3c61; font-weight: bold; display: block; }

  /* Page break after specs */
  .page-break { page-break-after: always; }

  /* ── Sections ────────────────────────────────────────────── */
  .pdf-section { margin-bottom: 24px; page-break-inside: avoid; }
  .pdf-section-title {
    font-size: 16px; font-weight: 600; color: #0a3c61;
    border-bottom: 2px solid #f0f0f0;
    padding-bottom: 8px; margin-bottom: 12px;
  }

  /* ── Description ─────────────────────────────────────────── */
  .pdf-desc { font-size: 13px; line-height: 1.7; color: #444; }
  .pdf-desc p { margin-bottom: 8px; }

  /* ── Amenities – 2-column checklist with gold tick ────────
     Exactly the same classes used in JS PDF so rendering matches. */
  .pdf-amenity-checklist {
    display: grid; grid-template-columns: 1fr 1fr; gap: 5px 30px;
  }
  .pdf-amenity-check {
    font-size: 12px; color: #0a3c61;
    display: flex; align-items: center; gap: 6px; padding: 2px 0;
  }
  .pdf-amenity-check::before {
    content: '✓'; color: #c0a24c; font-weight: bold; font-size: 13px; flex-shrink: 0;
  }

  /* ── Property Reference ──────────────────────────────────── */
  .pdf-details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 28px; }
  .pdf-detail-row {
    display: flex; justify-content: space-between;
    border-bottom: 1px dashed #ddd; padding-bottom: 5px; font-size: 13px;
  }
  .pdf-detail-row span   { color: #666; }
  .pdf-detail-row strong { color: #222; text-align: right; }

  /* ── Footer – unit URL only ──────────────────────────────── */
  .pdf-footer {
    margin-top: auto; padding-top: 14px;
    border-top: 2px solid #0a3c61;
    text-align: center; color: #555; font-size: 11px;
    page-break-inside: avoid;
  }
  .pdf-footer a { color: #0a3c61; text-decoration: none; }

  /* ── iOS tip banner (screen only) ───────────────────────── */
  .ios-tip {
    background: #0a3c61; color: #fff;
    padding: 11px 20px; text-align: center; font-size: 13px;
  }
  .ios-tip strong { color: #c0a24c; }

  /* ── Print overrides ────────────────────────────────────── */
  @media print {
    html, body { background: #fff; }
    .page { width: 100%; margin: 0; padding: 28px 32px; box-shadow: none; }
    .ios-tip { display: none !important; }
  }
  @media screen {
    body { padding: 14px 0 40px; }
    .page { box-shadow: 0 4px 28px rgba(0,0,0,0.13); }
  }
</style>
</head>
<body>

<div class="ios-tip">
  📄 To save as PDF: tap <strong>Share ⎋</strong> → <strong>Print</strong>, then pinch-zoom outward on the preview — or tap <strong>Save to Files</strong>.
</div>

<div class="page">
  <?php if ($watermark_b64): ?><div class="watermark"></div><?php endif; ?>
  <div class="content">

    <!-- Header — same structure as JS PDF pdf-header -->
    <div class="pdf-header">
      <div class="pdf-header-left">
        <h1><?php echo esc_html($project ?: $title); ?></h1>
        <span class="hl-sub"><?php echo esc_html($title); ?></span>
        <span class="hl-meta"><?php echo esc_html($header_meta); ?></span>
      </div>
      <div class="pdf-header-right">
        <strong>Reference:</strong><br>
        <span><?php echo esc_html($reference); ?></span>
      </div>
    </div>

    <!-- Hero Image -->
    <?php if (!empty($gallery_b64[0])): ?>
    <div class="pdf-hero">
      <img src="<?php echo $gallery_b64[0]; ?>" alt="Property">
    </div>
    <?php endif; ?>

    <!-- Gallery Row -->
    <?php if (count($gallery_b64) > 1): ?>
    <div class="pdf-gallery-grid">
      <?php foreach (array_slice($gallery_b64, 1, 3) as $img): ?>
        <img src="<?php echo $img; ?>" alt="Property">
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Price Box -->
    <div class="pdf-price-box">
      <div>
        <span class="price-label">Total Price</span>
        <h2><?php echo esc_html($price_fmt); ?></h2>
      </div>
      <div style="text-align:right;">
        <span class="price-label">Generated On</span>
        <span class="price-date"><?php echo date('M d, Y'); ?></span>
      </div>
    </div>

    <!-- Specs Grid -->
    <div class="pdf-specs-grid">
      <?php if ($bedrooms !== '' && $bedrooms !== null): ?>
      <div class="pdf-spec-item">
        <span class="spec-label">Bedrooms</span>
        <span class="spec-value"><?php echo (intval($bedrooms) === 0) ? 'Studio' : esc_html($bedrooms); ?></span>
      </div>
      <?php endif; ?>
      <?php if ($bathrooms !== '' && $bathrooms !== null): ?>
      <div class="pdf-spec-item">
        <span class="spec-label">Bathrooms</span>
        <span class="spec-value"><?php echo esc_html($bathrooms); ?></span>
      </div>
      <?php endif; ?>
      <?php if ($area): ?>
      <div class="pdf-spec-item">
        <span class="spec-label">Area</span>
        <span class="spec-value"><?php echo number_format($area); ?> sqft</span>
      </div>
      <?php endif; ?>
      <?php if ($price_sqft): ?>
      <div class="pdf-spec-item">
        <span class="spec-label">Price/sqft</span>
        <span class="spec-value">AED <?php echo number_format($price_sqft); ?></span>
      </div>
      <?php endif; ?>
      <?php if ($status): ?>
      <div class="pdf-spec-item">
        <span class="spec-label">Status</span>
        <span class="spec-value"><?php echo esc_html($status); ?></span>
      </div>
      <?php endif; ?>
      <?php if ($completion): ?>
      <div class="pdf-spec-item">
        <span class="spec-label">Completion</span>
        <span class="spec-value"><?php echo esc_html($completion); ?></span>
      </div>
      <?php endif; ?>
      <?php if ($payment_plan): ?>
      <div class="pdf-spec-item">
        <span class="spec-label">Payment Plan</span>
        <span class="spec-value"><?php echo esc_html($payment_plan); ?></span>
      </div>
      <?php endif; ?>
    </div>

    <!-- Page break after specs (matches JS PDF) -->
    <div class="page-break"></div>

    <!-- Description -->
    <?php if ($description): ?>
    <div class="pdf-section">
      <div class="pdf-section-title">Overview</div>
      <div class="pdf-desc"><?php echo $description; ?></div>
    </div>
    <?php endif; ?>

    <!-- Features & Amenities — 2-column checklist, gold tick — matches JS PDF exactly -->
    <?php if (!empty($all_amenity_names)): ?>
    <div class="pdf-section">
      <div class="pdf-section-title">Features &amp; Amenities</div>
      <div class="pdf-amenity-checklist">
        <?php foreach ($all_amenity_names as $aname): ?>
          <div class="pdf-amenity-check"><?php echo esc_html($aname); ?></div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Property Reference -->
    <div class="pdf-section">
      <div class="pdf-section-title">Property Reference</div>
      <div class="pdf-details-grid">
        <div class="pdf-detail-row"><span>Reference No.</span><strong><?php echo esc_html($reference); ?></strong></div>
        <div class="pdf-detail-row"><span>Purpose</span><strong><?php echo esc_html($purpose); ?></strong></div>
        <?php if ($developer && $developer !== '—'): ?>
        <div class="pdf-detail-row"><span>Developer</span><strong><?php echo esc_html($developer); ?></strong></div>
        <?php endif; ?>
        <?php if ($prop_type && $prop_type !== '—'): ?>
        <div class="pdf-detail-row"><span>Property Type</span><strong><?php echo esc_html($prop_type); ?></strong></div>
        <?php endif; ?>
        <?php if ($location): ?>
        <div class="pdf-detail-row"><span>Location</span><strong><?php echo esc_html($location); ?></strong></div>
        <?php endif; ?>
        <?php if ($project_number): ?>
        <div class="pdf-detail-row"><span>Project No.</span><strong><?php echo esc_html($project_number); ?></strong></div>
        <?php endif; ?>
        <?php if ($permit_number): ?>
        <div class="pdf-detail-row"><span>Permit No.</span><strong><?php echo esc_html($permit_number); ?></strong></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Footer — unit URL only, no extra links -->
    <div class="pdf-footer">
      <a href="<?php echo esc_url($unit_url); ?>"><?php echo esc_url($unit_url); ?></a>
    </div>

  </div><!-- /.content -->
</div><!-- /.page -->

<script>
// Auto-trigger print on iOS so user can save as PDF in 1 tap
(function(){
    if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
        window.onload = function(){ setTimeout(window.print, 700); };
    }
})();
</script>
</body>
</html>
<?php exit; ?>
