<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LegiScan API Handler
 */

class LegiScan_API {

    private $api_key;
    private $base_url = 'https://api.legiscan.com/';

    public function __construct() {
        $this->api_key = get_option('legiscan_api_key', '');
    }

    /**
     * Make API request to LegiScan
     */
    private function make_request($endpoint, $params = []) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'LegiScan API key not configured');
        }

        // Check API usage limit
        if (legiscan_is_api_limit_approaching()) {
            return new WP_Error('api_limit', 'Approaching monthly API limit');
        }

        $params['key'] = $this->api_key;
        $url = $this->base_url . $endpoint . '?' . http_build_query($params);

        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            return $response;
        }

        // Increment API usage counter
        legiscan_increment_api_usage();

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return $data;
    }

    /**
     * Get bill by ID
     */
    public function get_bill($bill_id) {
        return $this->make_request('getBill', ['id' => $bill_id]);
    }

    /**
     * Search bills
     */
    public function search_bills($query, $state = null) {
        $params = ['query' => $query];
        if ($state) {
            $params['state'] = $state;
        }
        return $this->make_request('search', $params);
    }

    /**
     * Get bill text
     */
    public function get_bill_text($doc_id) {
        return $this->make_request('getBillText', ['id' => $doc_id]);
    }
}

// Initialize API class
function legiscan_api() {
    return new LegiScan_API();
}
