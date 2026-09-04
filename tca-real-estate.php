<?php
/**
 * Plugin Name: TCA Real Estate Units
 * Plugin URI: https://tca.ae
 * Description: Custom plugin to fully dynamically manage real estate units, filters, taxonomies, and shortcodes.
 * Version: 3.0.0
 * Author: TCA
 * Author URI: https://tca.ae
 * Text Domain: tca-real-estate
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define plugin constants safely
if (!defined('TCA_RE_VERSION')) {
    define('TCA_RE_VERSION', '3.0.0');
}
if (!defined('TCA_RE_PLUGIN_DIR')) {
    define('TCA_RE_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('TCA_RE_PLUGIN_URL')) {
    define('TCA_RE_PLUGIN_URL', plugin_dir_url(__FILE__));
}

/**
 * Main Class for TCA Real Estate Plugin
 */
if (!class_exists('TCA_Real_Estate')) {
    class TCA_Real_Estate
    {

        public function __construct()
        {
            $this->load_dependencies();
            $this->register_hooks();
        }

        private function load_dependencies()
        {
            require_once TCA_RE_PLUGIN_DIR . 'includes/class-cpt-taxonomies.php';
            require_once TCA_RE_PLUGIN_DIR . 'includes/class-meta-boxes.php';
            require_once TCA_RE_PLUGIN_DIR . 'includes/class-shortcodes.php';
            require_once TCA_RE_PLUGIN_DIR . 'includes/class-search-ajax.php';
            require_once TCA_RE_PLUGIN_DIR . 'includes/class-ads.php';
            require_once TCA_RE_PLUGIN_DIR . 'includes/class-pf-settings.php';
            new TCA_RE_PF_Settings();
            require_once TCA_RE_PLUGIN_DIR . 'includes/class-pf-connector.php';
            new TCA_RE_PF_Connector();
            require_once TCA_RE_PLUGIN_DIR . 'includes/class-pf-sync-engine.php';
            // Elementor requires a specific timing for widgets
            add_action('elementor/widgets/register', [$this, 'register_elementor_widgets']);
        }

        public function register_elementor_widgets($widgets_manager)
        {
            require_once TCA_RE_PLUGIN_DIR . 'includes/class-elementor-widget.php';
            $widgets_manager->register(new \TCA_Elementor_Units_Widget());
        }

        private function register_hooks()
        {
            add_action('wp_enqueue_scripts', [$this, 'register_frontend_scripts']);
            add_action('elementor/frontend/after_enqueue_styles', [$this, 'register_frontend_scripts']);
            add_filter('template_include', [$this, 'load_single_template']);
        }

        public function load_single_template($template)
        {
            if (is_singular('tca_unit')) {
                $plugin_template = TCA_RE_PLUGIN_DIR . 'templates/single-tca_unit.php';
                if (file_exists($plugin_template)) {
                    return $plugin_template;
                }
            }
            return $template;
        }

        public function register_frontend_scripts()
        {
            wp_register_style('tca-re-style', TCA_RE_PLUGIN_URL . 'assets/css/style.css', [], TCA_RE_VERSION);
            wp_register_style('tca-re-single', TCA_RE_PLUGIN_URL . 'assets/css/single.css', [], TCA_RE_VERSION);

            global $post;
            $has_re_shortcode = false;
            if (is_a($post, 'WP_Post')) {
                $has_re_shortcode = has_shortcode($post->post_content, 'tca_search') ||
                    has_shortcode($post->post_content, 'tca_units_grid') ||
                    has_shortcode($post->post_content, 'tca_featured_units');
            }

            // Load general styles only if needed
            if (is_singular('tca_unit') || is_post_type_archive('tca_unit') || $has_re_shortcode || is_tax(['tca_location', 'tca_property_type', 'tca_purpose', 'tca_amenity', 'tca_developer', 'tca_project'])) {
                wp_enqueue_style('tca-re-style');
            }

            if (is_singular('tca_unit')) {
                wp_enqueue_style('tca-re-single');
            }
        }
    } // End TCA_Real_Estate class
} // End class_exists check

// Initialize the plugin safely
if (!function_exists('tca_real_estate_init')) {
    function tca_real_estate_init()
    {
        if (class_exists('TCA_Real_Estate')) {
            new TCA_Real_Estate();
        }
    }
}
add_action('plugins_loaded', 'tca_real_estate_init');

/**
 * Helper: Format bedrooms count for display.
 * Returns 'Studio' when bedrooms = 0, otherwise returns the number.
 * Usage: tca_format_bedrooms( $bedrooms )
 */
if (!function_exists('tca_format_bedrooms')) {
    function tca_format_bedrooms($bedrooms)
    {
        if ($bedrooms === '' || $bedrooms === null)
            return ''; // Not set — hide or handle upstream
        return ($bedrooms === '0' || $bedrooms === 0) ? 'Studio' : esc_html($bedrooms);
    }
}

/**
 * Auto-Clean Feature: Delete attached media when a TCA Unit is permanently deleted.
 * This prevents the Media Library from filling up with ghost images from old properties.
 */
if (!function_exists('tca_delete_unit_images')) {
    function tca_delete_unit_images($post_id)
    {
        if (get_post_type($post_id) !== 'tca_unit') {
            return;
        }

        // Get all attachments where this post is the parent
        $attachments = get_posts([
            'post_type' => 'attachment',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'post_parent' => $post_id,
            'fields' => 'ids'
        ]);

        if (!empty($attachments)) {
            foreach ($attachments as $attachment_id) {
                wp_delete_attachment($attachment_id, true); // true = force delete
            }
        }
    }
}
add_action('before_delete_post', 'tca_delete_unit_images');

// ── Brochure Endpoint ─────────────────────────────────────────────────────────
// Registers /tca-brochure/ as a query-var route so the template can be loaded
// without needing a physical file outside the plugin directory.
if ( ! function_exists('tca_register_brochure_endpoint') ) {
    function tca_register_brochure_endpoint() {
        add_rewrite_rule( '^tca-brochure/?$', 'index.php?tca_brochure=1', 'top' );
        add_rewrite_tag( '%tca_brochure%', '([0-9]+)' );
    }
}
add_action( 'init', 'tca_register_brochure_endpoint' );

if ( ! function_exists('tca_handle_brochure_request') ) {
    function tca_handle_brochure_request() {
        if ( isset($_GET['tca_brochure']) && isset($_GET['unit_id']) && isset($_GET['token']) ) {
            require_once plugin_dir_path(__FILE__) . 'templates/brochure-print.php';
            exit;
        }
        if ( isset($_GET['tca_offer']) && isset($_GET['unit_id']) && isset($_GET['token']) ) {
            require_once plugin_dir_path(__FILE__) . 'templates/offer-print.php';
            exit;
        }
    }
}
add_action( 'template_redirect', 'tca_handle_brochure_request', 1 );

// ── SEO Breadcrumb Fixes ─────────────────────────────────────────────────────────
add_filter( 'rank_math/frontend/breadcrumb/items', function( $crumbs, $class ) {
    if ( is_singular( 'tca_unit' ) ) {
        foreach ( $crumbs as &$crumb ) {
            if ( strpos( $crumb[0], '%tca_project%' ) !== false ) {
                $projects = get_the_terms( get_the_ID(), 'tca_project' );
                if ( $projects && ! is_wp_error( $projects ) ) {
                    $crumb[0] = str_replace( '%tca_project%', esc_html( $projects[0]->name ), $crumb[0] );
                } else {
                    $crumb[0] = str_replace( '%tca_project%', 'Property', $crumb[0] );
                }
            }
        }
    }
    return $crumbs;
}, 10, 2);

