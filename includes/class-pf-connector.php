<?php
/**
 * Connector for Property Finder PFDN (Atlas) API.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TCA_RE_PF_Connector {

    private $api_key;
    private $api_secret;
    private $is_sandbox;
    private $base_url;

    public function __construct() {
        $this->api_key    = get_option('tca_pf_api_key');
        $this->api_secret = get_option('tca_pf_api_secret');
        $this->is_sandbox = (get_option('tca_pf_sandbox_mode') == '1');
        
        // PFDN Base URLs (Atlas)
        $this->base_url = $this->is_sandbox 
            ? 'https://sandbox.atlas.propertyfinder.com/v1' 
            : 'https://atlas.propertyfinder.com/v1';

        add_action( 'wp_ajax_tca_pf_test_connection', [ $this, 'ajax_test_connection' ] );
    }

    /**
     * Get the Access Token from Property Finder.
     */
    public function get_access_token() {
        if ( empty($this->api_key) || empty($this->api_secret) ) {
            return new WP_Error( 'missing_keys', 'API Key or Secret is missing.' );
        }

        $response = wp_remote_post( $this->base_url . '/auth/token', [
            'body' => json_encode([
                'apiKey'    => $this->api_key,
                'apiSecret' => $this->api_secret
            ]),
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json'
            ]
        ]);

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset($body['accessToken']) ) {
            return $body['accessToken'];
        }

        $raw_body = wp_remote_retrieve_body( $response );
        $error_msg = 'Failed to retrieve access token. Raw response: ' . esc_html($raw_body);
        return new WP_Error( 'auth_failed', $error_msg );
    }

    /**
     * AJAX handler to test the connection.
     */
    public function ajax_test_connection() {
        check_ajax_referer( 'tca_pf_test_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $token = $this->get_access_token();

        if ( is_wp_error( $token ) ) {
            wp_send_json_error( $token->get_error_message() );
        }

        wp_send_json_success( 'Connected successfully to ' . ($this->is_sandbox ? 'Sandbox' : 'Production') );
    }
}
