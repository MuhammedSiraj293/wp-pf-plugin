<?php
/**
 * Elementor Custom Widget for TCA Units
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class TCA_Elementor_Units_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'tca_units_grid';
    }

    public function get_title() {
        return 'TCA Real Estate Grid';
    }

    public function get_icon() {
        return 'eicon-posts-grid';
    }

    public function get_categories() {
        return [ 'general' ];
    }

    public function get_style_depends() {
        wp_register_style( 'tca-re-style', TCA_RE_PLUGIN_URL . 'assets/css/style.css', [], TCA_RE_VERSION );
        return [ 'tca-re-style' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => 'Query Settings',
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'layout',
            [
                'label' => 'Layout Style',
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'grid',
                'options' => [
                    'grid'  => 'Vertical Grid (Portrait)',
                    'landscape' => 'Horizontal (Landscape)',
                ],
            ]
        );

        $this->add_control(
            'limit',
            [
                'label' => 'Number of properties',
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 9,
            ]
        );

        $this->add_control(
            'show_pagination',
            [
                'label' => 'Show Pagination Bar',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => 'Yes',
                'label_off' => 'No',
                'return_value' => 'true',
                'default' => '',
            ]
        );

        $this->add_control(
            'location',
            [
                'label' => 'Location Filter (Slug)',
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => 'e.g. yas-island',
            ]
        );

        $this->add_control(
            'purpose',
            [
                'label' => 'Purpose (buy/rent)',
                'type' => \Elementor\Controls_Manager::TEXT,
            ]
        );
        
        $this->add_control(
            'property_type',
            [
                'label' => 'Property Type (Slug)',
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => 'e.g. apartment, villa',
            ]
        );

        $this->add_control(
            'developer',
            [
                'label' => 'Developer (Slug)',
                'type' => \Elementor\Controls_Manager::TEXT,
            ]
        );

        $this->add_control(
            'project',
            [
                'label' => 'Project Name (Slug)',
                'type' => \Elementor\Controls_Manager::TEXT,
            ]
        );

        $this->add_control(
            'amenity',
            [
                'label' => 'Amenity (Slug)',
                'type' => \Elementor\Controls_Manager::TEXT,
            ]
        );

        $this->add_control(
            'bedrooms',
            [
                'label' => 'Bedrooms (Exact Number)',
                'type' => \Elementor\Controls_Manager::NUMBER,
            ]
        );

        $this->add_control(
            'bathrooms',
            [
                'label' => 'Bathrooms (Minimum)',
                'type' => \Elementor\Controls_Manager::NUMBER,
            ]
        );

        $this->add_control(
            'status',
            [
                'label' => 'Project Status',
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    '' => 'Any',
                    'ready' => 'Ready',
                    'off_plan' => 'Off-Plan'
                ],
                'default' => '',
            ]
        );

        $this->add_control(
            'featured',
            [
                'label' => 'Only Featured Properties',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => 'Yes',
                'label_off' => 'No',
                'return_value' => '1',
                'default' => '',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        $shortcode_str = sprintf(
            '[tca_units layout="%s" limit="%d" show_pagination="%s" location="%s" purpose="%s" property_type="%s" developer="%s" project="%s" amenity="%s" bedrooms="%s" bathrooms="%s" status="%s" featured="%s"]',
            esc_attr($settings['layout']),
            intval($settings['limit']),
            esc_attr($settings['show_pagination'] ?? 'false'),
            esc_attr($settings['location'] ?? ''),
            esc_attr($settings['purpose'] ?? ''),
            esc_attr($settings['property_type'] ?? ''),
            esc_attr($settings['developer'] ?? ''),
            esc_attr($settings['project'] ?? ''),
            esc_attr($settings['amenity'] ?? ''),
            esc_attr($settings['bedrooms'] ?? ''),
            esc_attr($settings['bathrooms'] ?? ''),
            esc_attr($settings['status'] ?? ''),
            esc_attr($settings['featured'] ?? '')
        );

        echo do_shortcode( $shortcode_str );
    }
}
