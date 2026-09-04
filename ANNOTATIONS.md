# TCA Real Estate Plugin - Annotations, Changelog & Deployment Guide

This document operates as the central ledger for all architectural components, updates, and shortcode deployment instructions for the TCA Real Estate WordPress plugin.

## How to Use on the Live Website (Shortcodes)

We can construct our pages effortlessly using these shortcodes in the WordPress Editor, Elementor Shortcode widgets, or directly within standard text blocks.

### 1. `[tca_units]` -> Standard Listing Grid
Displays the primary property feed with built-in pagination.
**Usage Examples:**
- `[tca_units]` — Displays default grid of all recent properties.
- `[tca_units layout="landscape"]` — Displays the wide, horizontal row cards.
- `[tca_units posts_per_page="6"]` — Limits the grid to 6 properties per page.

### 2. `[tca_search_bar]` -> Main Ajax Search Interface
Renders the prominent top-level hero search bar used on the homepage. Includes dynamic autocompleting dropdowns for Locations and Projects.
**Usage Example:**
- `[tca_search_bar]` — Simply drop this into the Homepage hero section.
- `[tca_search_bar hide="purpose,property_type"]` — Selectively hide filters to simplify the UI for specific landing pages. Valid values: `purpose`, `property_type`, `status`, `bedroom`.

### 3. `[tca_project_units]` -> Project-Specific Grid (Dynamic)
An intelligent shortcode meant for individual Project template pages. It automatically detects which Project page you are viewing and exclusively displays units assigned to that specific project name, hiding the page entirely if no units are available.
**Usage Example:**
- `[tca_project_units]` — Place this on your "Fahid Beach Residences" or "Nawayef Village" generic pages. 

### 4. `[tca_compact_filter]` -> Floating Sidebar Filter
A secondary, compact variation of the search box suitable for sidebars alongside the main properties grid. Keeps the search state live alongside your results.
**Usage Example:**
- `[tca_compact_filter]` — Usually placed in a 30% width column next to a `[tca_units]` block in a 70% width column.

### 5. `[tca_ads]` -> Universal Ad Injector
Pulls the customized ads/banners defined inside the **📢 Ad Manager** menu. This is the universal shortcode used for both mobile and desktop placements.
- **On Single Pages:** It is placed inside `.tca-desktop-ad` and `.tca-mobile-ad` containers in the template to ensure the correct version displays based on screen size.
- **Manual Placement:** Simply use `[tca_ads]` anywhere you want the ad slider to appear.

---

## Internal Class Architecture

The plugin is built using a modular, object-oriented structure. Each core responsibility is handled by a dedicated class located in the `includes/` directory:

1.  **`TCA_CPT_Taxonomies` (`class-cpt-taxonomies.php`)**: Handles the registration of the `tca_unit` Post Type and all related taxonomies (Purpose, Location, Project, etc.).
2.  **`TCA_Meta_Boxes` (`class-meta-boxes.php`)**: Manages the custom data entry fields in the WordPress admin (Price, Bedrooms, Maps, etc.).
3.  **`TCA_Shortcodes` (`class-shortcodes.php`)**: The engine that powers all the `[tca_...]` shortcodes and handles the template rendering for grids.
4.  **`TCA_Search_Ajax` (`class-search-ajax.php`)**: Powers the real-time search functionality, processing filters without page refreshes.
5.  **`TCA_Ads_Manager` (`class-ads.php`)**: Manages the shortcode logic for Ad injections and Google AdSense placement.
6.  **`TCA_Elementor_Widget` (`class-elementor-widget.php`)**: Bridges the plugin logic directly into the Elementor Page Builder.
7.  **`TCA_RE_PF_Connector` (`class-pf-connector.php`)**: Handles authentication and secure handshake with the Property Finder Atlas (PFDN) production API.
8.  **`TCA_RE_PF_Sync_Engine` (`class-pf-sync-engine.php`)**: The core logic engine that maps PF listings to WP Units. Handles image sideloading, taxonomy mapping, and batch processing.
9.  **`TCA_RE_PF_Settings` (`class-pf-settings.php`)**: Manages the admin dashboard interface, including the manual Chain Sync UI, 10-second gap logic, and visual progress bar.

---

## Elementor Integration

The **TCA Real Estate Widget** is automatically available within the Elementor sidebar under the "TCA" or "Basic" category.

- **Visual Config:** Instead of typing shortcodes, you can drag the widget onto a page and use the Elementor sidebar to toggle between "Grid" and "Landscape" layouts.
- **Filtering:** You can pre-set the widget to only show specific Projects or Locations directly from the Elementor interface.

---

## Recent Architecture Updates

### 1. Robust PDF Brochure Generation (`html2pdf.js`)
- Completely migrated away from relying on standard CSS print queries. The script generates high-resolution, multi-page PDFs utilizing an advanced Canvas-to-PDF pipeline natively locally.
- **Watermark Flattening Mechanism:** The watermark (`assets/images/watermark.png`) is aggressively embedded beneath a high foreground `z-index` over the HTML layer during rendering. This forces `html2canvas` to aggressively flatten the watermark directly into the text’s pixel raster, inherently preventing PDF editing tools from easily clicking and deleting the layer cleanly.
- **Perfect Spacing & Subtitles:** Handover dates display as "Q[X] [YYYY]". Dynamic layout logic hides Project numbers/permit strings if not filled out.

### 2. Fix: Taxonomy Tag Priority Engine
- **The Issue:** Properties tagged loosely to multiple top-level projects *(e.g., both Parent project "Fahid" and Child phase "Nawayef")* caused PDF and grid naming conflicts.
- **The Fix:** Created a custom heuristic that overrides WP’s alphabetical parsing. The system now parses all attached `tca_project` tags and locks onto the tag with the **lowest unit count** natively across the database. This inherently selects the most specific phase/sub-community as the primary project title.

### 3. Fix: PHP Scope "Bleeding" from Loop
- **The Issue:** The "Recommended list" carousel grid dynamically leaked its local PHP variables back into the root template. Generating a PDF resulted in the PDF consuming the title and properties of the *last recommended property* instead of the main page!
- **The Fix:** Isolated local variables. Instantiated an explicit variable re-fetch lock right after the `wp_reset_postdata();` block in `single-tca_unit.php`. This securely seals the original `$project_name` and `$project_status` data so it can be passed properly to the PDF build engine.

### 4. Native Mortgage Calculator
- Embedded entirely without external iFrame components. Exists natively at the bottom of standard `single-tca_unit.php` templates, taking an anchor link `/#mortgage` from the floating card action group.

### 5. Property Finder Atlas API Integration (Production)
- **Manual Chain Sync Engine:** Implemented a robust "auto-clicker" style synchronization system. Instead of risky server-side cron jobs, the admin can trigger a "Chain Sync" which downloads properties in batches of 5.
- **Precision Throttling:** Includes a mandatory **10-second rest period** between AJAX batches to ensure server stability and prevent API rate-limiting.
- **Visual Progress Bar:** Real-time feedback showing total listings (e.g., 391) and the exact count processed, with a one-click "Stop/Resume" capability.
- **Smart Data Mapping:**
    - **Dates:** Automatically maps PF's `createdAt` to WordPress `post_date` to preserve original chronological sorting.
    - **Purpose:** Intelligent logic to map PF rental periods to "Rent" taxonomy and sales to "Buy" taxonomy.
    - **Deduplication:** Uses `_tca_reference` meta-search to ensure existing properties are updated instead of duplicated.
    - **Media Sideloading:** Physically downloads up to 10 images into the WP Media Library. Separates Image[0] as the Featured Thumbnail and Image[1-9] as the Gallery to prevent duplication in the frontend slider.

### 6. Custom Developer Logos on Cards
- **Backend Uploader:** Created a custom logo uploader for the `tca_developer` taxonomy screen, saving the logo URL under term meta `tca_developer_logo`.
- **Card Overlays:** Grid and Landscape templates fetch and render the developer logo as a premium badge. Inline CSS constraints (`max-height: 45px`) are applied directly to prevent layout shift.

### 7. Strict Keyword Matching & Mobile Search Optimizations
- **Title-Strict Keyword Search:** Replaced standard description scanning (`'s' => $keyword`) with strict title queries (`LIKE` SQL query on `post_title`), merged with taxonomy matches. Searches like "Saadiyat Island" no longer match properties in other locations just because of description texts.
- **Empty Filter Hiding:** Set `hide_empty => true` in autocomplete suggestions and dropdowns to keep search filters clean and relevant.
- **Mobile Filter Automation:** Enabled immediate auto-submit when changing the `purpose` or `property_type` dropdowns on mobile viewports.

### 8. Custom Button Dropdowns for Beds & Baths (Version 1.9.0)
- **Beds & Baths Refactoring:** Replaced separate min/max select dropdowns with clean, horizontal selectable button groups.
- **Desktop Popup Panels:** Custom interactive dropdowns toggle button selectors for Beds and Baths on click, automatically closing on click-outside and auto-triggering asynchronous searches.
- **Mobile Inline Layout:** Converts Bed and Bath selectors to inline horizontal button groups inside the mobile filter sheet for improved touch accessibility.

---

## Version History

**Version 1.0.0 (Initial Creation)**
- Scaled Custom Post Type `tca_unit` and 6 master Taxonomies.
- Engineered baseline backend Meta Boxes for data capture.

**Version 1.1.0 (Theming & UI)**
- Introduced Grid/Landscape view toggling capabilities.
- Rolled out native Elementor visual block wrappers.

**Version 1.2.0 (Ajax & Filtration Engine)**
- Enabled fully asynchronous Search logic, stripping page refreshes during taxonomy manipulation.

**Version 1.3.0 (PDF Engine & Precision Bug Fixes)**
- Released major PDF generation overhaul and taxonomy heuristic logic.

**Version 1.4.0 (Property Finder Integration)**
- Integrated Property Finder Atlas API for automated listing imports.
- Launched Manual Chain Sync UI with progress tracking and 10s throttling.
- Implemented smart image sideloading and historical date mapping for chronological sorting.

**Version 1.5.0 (Sticky Filters & Mobile UX)**
- **Sticky Search Context:** Re-engineered the AJAX engine to preserve "base" filters (like Location or Project) across multiple consecutive searches. 
- **Single Page Mobile Overhaul:** 
    - Added native touch/swipe support to the hero gallery (50px threshold).
    - Reduced visual crowding with a cleaner, simplified mobile layout and optimized font sizes.
    - Hidden scrollbars on the horizontal tab-bar for a more premium look.
- **Landscape UI Refinements:** 
    - Standardized landscape images to a fixed 440x300px aspect ratio for consistency.
    - Unified badge styling (Verified/Off-plan) with 50% glassmorphism opacity and corrected positioning.
    - Restored hover navigation arrows for the landscape gallery.

**Version 1.6.0 (Premium Search & Intelligent Logic)**
- **Branded AJAX Autocomplete:**
    - Implemented real-time, debounced search suggestions for Locations, Projects, and Developers.
    - Custom premium dropdown UI with Navy/Gold accents and "Category Pills" for easy navigation.
    - Added "Popular Locations" quick-start menu that appears instantly when the search box is focused.
- **Intelligent Related Content:**
    - **Context-Aware Recommendations:** The "Recommended for you" and "More in Project" sections now automatically filter results by the current unit's Purpose (Buy vs. Rent).
    - **Smart Placeholder Logic:** The compact filter sidebar now exclusively searches Projects and Developers to match its UI intent.
- **UI/UX Polishing:**
    - **Reset Search Engine:** Added a one-click "Reset Search" button to empty result states to improve user recovery.
    - **Variable Isolation (Gallery Fix):** Re-engineered variable naming in `single-tca_unit.php` to prevent related property cards from overwriting the main unit's gallery data.
    - **Text Truncation:** Implemented flex-box locks and `text-overflow: ellipsis` on autocomplete items to ensure long project names don't break the layout.
    - **Z-Index Layering:** Standardized z-index layers so search dropdowns confidently overlap grid content.
- **Data Display Logic:**
    - Standardized "Studio" labeling for 0-bedroom units.
    - Implemented "Property Type" fallback for Land and Office units where bedroom counts are irrelevant.
    - Auto-hiding empty bathroom fields to keep the UI clean.

**Version 1.7.0 (Express Sync & Intelligent Pagination)**
- **Express Sync Engine (Performance):**
    - Dramatically reduced manual sync time (by approx 50x).
    - Existing properties now bypass expensive image and description downloads, intelligently updating only critical fields (`Price`, `Verification Status`, `Featured Status`, `Permit Number`).
- **Smart Image Deduplication:**
    - Engineered URL query-stripping (`strtok`) to clean Property Finder version tokens (`?v=...`) before checking the Media Library, preventing massive duplicate image bloat.
- **Sync Speed & Reliability:**
    - Increased batch processing size from 5 to 10 properties per API hit.
    - Reduced UI throttle delay from 10 seconds to 5 seconds.
    - Enforced `-createdAt` API sorting to guarantee newest properties are processed immediately on Page 1.
- **Intelligent Sliding Pagination:**
    - Replaced standard sequential loop pagination with an advanced sliding algorithm (`1 ... 4 5 6 ... 20`).
    - Ensures the pagination UI remains compact and professional, regardless of property volume.
    - Added specific CSS for `.tca-pagination-dots` to match the brand's circular button aesthetic.

**Version 1.7.6 (Developer Logos & Card Alignment)**
- Added backend uploader for Developer logos (`includes/class-cpt-taxonomies.php`).
- Embedded developer logos onto grid and landscape cards with inline layout styling.
- Standardized vertical card alignment, clamped titles to a maximum of 2 lines, and set `margin-top: auto` on CTA buttons.

**Version 1.8.0 (Search, Badge & Mobile Filter Overhaul)**
- **Strict Keyword Matching:** Avoided description text matching for keywords by using a strict SQL title match merged with taxonomy lookups.
- **Badge Priority:** Hid the `Verified` badge if the `Featured` badge is active to prevent visual clutter.
- **Conditional Featured Sorting:** Skip forcing featured listings to the top when search parameters are active, preserving price/relevance sort orders.
- **Mobile Select Auto-Submit:** Enabled immediate auto-submission when changing primary filters on mobile viewports.
- **Reset Option Fix:** Resolved a bug in JS where selecting the default placeholder (e.g. resetting back to "Property Type") was ignored.
- **Empty Option Hiding:** Filtered out empty/unavailable categories and projects from dropdowns and suggestions.

**Version 1.9.0 (Custom Bed & Bath Selector Panels)**
- Replaced legacy desktop min_beds/max_beds select elements with modular `Beds` and `Baths` popup dropdown button panels.
- Converted mobile sheet bed/bath selects into inline horizontal finger-friendly button selectors.
- Re-architected PHP shortcode and AJAX backend query processors to map `bedrooms` and `bathrooms` parameters to exact/range meta filters.
- Enforced immediate auto-submission on desktop selections.
