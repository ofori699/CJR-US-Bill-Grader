<?php
/**
 * LegiScan Census API Integration
 * Handles census data retrieval and processing for legislative grading
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class LegiScan_Census_API {

    private $api_key;
    private $base_url = 'https://api.census.gov/data';
    private $cache_duration = 3600; // 1 hour cache

    public function __construct($api_key = null) {
        $this->api_key = $api_key;

        // Add WordPress hooks
        add_action('wp_ajax_get_census_data', array($this, 'ajax_get_census_data'));
        add_action('wp_ajax_nopriv_get_census_data', array($this, 'ajax_get_census_data'));
    }

    /**
     * Get demographic data for a state
     */
    public function get_state_demographics($state_code) {
        $cache_key = 'census_demographics_' . $state_code;
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            return $cached_data;
        }

        // Simulate census data (replace with actual API calls)
        $demographics = $this->fetch_demographics_data($state_code);

        // Cache the result
        set_transient($cache_key, $demographics, $this->cache_duration);

        return $demographics;
    }

    /**
     * Get racial composition data
     */
    public function get_racial_composition($state_code) {
        $demographics = $this->get_state_demographics($state_code);

        return array(
            'white' => $demographics['racial']['white'] ?? 0,
            'black' => $demographics['racial']['black'] ?? 0,
            'hispanic' => $demographics['racial']['hispanic'] ?? 0,
            'asian' => $demographics['racial']['asian'] ?? 0,
            'native_american' => $demographics['racial']['native_american'] ?? 0,
            'other' => $demographics['racial']['other'] ?? 0
        );
    }

    /**
     * Get income distribution data
     */
    public function get_income_distribution($state_code) {
        $demographics = $this->get_state_demographics($state_code);

        return array(
            'under_25k' => $demographics['income']['under_25k'] ?? 0,
            'between_25k_50k' => $demographics['income']['between_25k_50k'] ?? 0,
            'between_50k_75k' => $demographics['income']['between_50k_75k'] ?? 0,
            'between_75k_100k' => $demographics['income']['between_75k_100k'] ?? 0,
            'over_100k' => $demographics['income']['over_100k'] ?? 0,
            'median_income' => $demographics['income']['median_income'] ?? 0
        );
    }

    /**
     * Get population data
     */
    public function get_population_data($state_code) {
        $demographics = $this->get_state_demographics($state_code);

        return array(
            'total_population' => $demographics['population']['total'] ?? 0,
            'urban_population' => $demographics['population']['urban'] ?? 0,
            'rural_population' => $demographics['population']['rural'] ?? 0,
            'population_density' => $demographics['population']['density'] ?? 0
        );
    }

    /**
     * Calculate racial impact score
     */
    public function calculate_racial_impact($state_code, $bill_data) {
        $racial_composition = $this->get_racial_composition($state_code);

        // Base score calculation
        $base_score = 50;

        // Adjust based on bill type and racial composition
        if (isset($bill_data['subjects'])) {
            foreach ($bill_data['subjects'] as $subject) {
                $subject_lower = strtolower($subject);

                // Civil rights and equality bills
                if (strpos($subject_lower, 'civil rights') !== false || 
                    strpos($subject_lower, 'discrimination') !== false ||
                    strpos($subject_lower, 'equality') !== false) {
                    $base_score += 20;
                }

                // Criminal justice reform
                if (strpos($subject_lower, 'criminal') !== false ||
                    strpos($subject_lower, 'justice') !== false ||
                    strpos($subject_lower, 'police') !== false) {
                    // Higher minority population = higher impact
                    $minority_percentage = 100 - ($racial_composition['white'] ?? 70);
                    $base_score += ($minority_percentage / 100) * 15;
                }

                // Education bills
                if (strpos($subject_lower, 'education') !== false ||
                    strpos($subject_lower, 'school') !== false) {
                    $base_score += 10;
                }
            }
        }

        return min(100, max(0, $base_score));
    }

    /**
     * Calculate income impact score
     */
    public function calculate_income_impact($state_code, $bill_data) {
        $income_distribution = $this->get_income_distribution($state_code);

        $base_score = 50;

        if (isset($bill_data['subjects'])) {
            foreach ($bill_data['subjects'] as $subject) {
                $subject_lower = strtolower($subject);

                // Economic and tax bills
                if (strpos($subject_lower, 'tax') !== false ||
                    strpos($subject_lower, 'economic') !== false ||
                    strpos($subject_lower, 'budget') !== false) {
                    // Higher impact for states with more low-income residents
                    $low_income_percentage = ($income_distribution['under_25k'] ?? 20) + 
                                           ($income_distribution['between_25k_50k'] ?? 25);
                    $base_score += ($low_income_percentage / 100) * 20;
                }

                // Healthcare bills
                if (strpos($subject_lower, 'health') !== false ||
                    strpos($subject_lower, 'medical') !== false ||
                    strpos($subject_lower, 'insurance') !== false) {
                    $base_score += 15;
                }

                // Labor and employment
                if (strpos($subject_lower, 'labor') !== false ||
                    strpos($subject_lower, 'employment') !== false ||
                    strpos($subject_lower, 'wage') !== false) {
                    $base_score += 12;
                }
            }
        }

        return min(100, max(0, $base_score));
    }

    /**
     * Calculate state impact score
     */
    public function calculate_state_impact($state_code, $bill_data) {
        $population_data = $this->get_population_data($state_code);

        $base_score = 50;

        // Population-based adjustments
        $total_population = $population_data['total_population'] ?? 1000000;

        // Larger states have higher impact potential
        if ($total_population > 10000000) {
            $base_score += 15;
        } elseif ($total_population > 5000000) {
            $base_score += 10;
        } elseif ($total_population > 1000000) {
            $base_score += 5;
        }

        // Bill scope adjustments
        if (isset($bill_data['subjects'])) {
            foreach ($bill_data['subjects'] as $subject) {
                $subject_lower = strtolower($subject);

                // Statewide impact subjects
                if (strpos($subject_lower, 'constitution') !== false ||
                    strpos($subject_lower, 'government') !== false ||
                    strpos($subject_lower, 'public') !== false) {
                    $base_score += 20;
                }

                // Infrastructure and transportation
                if (strpos($subject_lower, 'transport') !== false ||
                    strpos($subject_lower, 'infrastructure') !== false ||
                    strpos($subject_lower, 'highway') !== false) {
                    $base_score += 15;
                }
            }
        }

        return min(100, max(0, $base_score));
    }

    /**
     * Fetch demographics data (simulated - replace with actual API calls)
     */
    private function fetch_demographics_data($state_code) {
        // This is simulated data - replace with actual Census API calls
        $state_demographics = array(
            'AL' => array(
                'racial' => array('white' => 68.5, 'black' => 26.2, 'hispanic' => 4.2, 'asian' => 1.5, 'native_american' => 0.6, 'other' => 1.0),
                'income' => array('under_25k' => 22.0, 'between_25k_50k' => 25.0, 'between_50k_75k' => 20.0, 'between_75k_100k' => 15.0, 'over_100k' => 18.0, 'median_income' => 51734),
                'population' => array('total' => 5024279, 'urban' => 59.0, 'rural' => 41.0, 'density' => 96.9)
            ),
            'CA' => array(
                'racial' => array('white' => 36.5, 'black' => 5.8, 'hispanic' => 39.4, 'asian' => 15.5, 'native_american' => 1.6, 'other' => 1.2),
                'income' => array('under_25k' => 18.0, 'between_25k_50k' => 20.0, 'between_50k_75k' => 18.0, 'between_75k_100k' => 16.0, 'over_100k' => 28.0, 'median_income' => 80440),
                'population' => array('total' => 39538223, 'urban' => 95.0, 'rural' => 5.0, 'density' => 253.6)
            ),
            'TX' => array(
                'racial' => array('white' => 41.2, 'black' => 11.8, 'hispanic' => 39.7, 'asian' => 5.2, 'native_american' => 1.0, 'other' => 1.1),
                'income' => array('under_25k' => 19.0, 'between_25k_50k' => 23.0, 'between_50k_75k' => 20.0, 'between_75k_100k' => 16.0, 'over_100k' => 22.0, 'median_income' => 64034),
                'population' => array('total' => 29145505, 'urban' => 84.7, 'rural' => 15.3, 'density' => 112.8)
            ),
            'NY' => array(
                'racial' => array('white' => 55.3, 'black' => 13.7, 'hispanic' => 19.3, 'asian' => 9.0, 'native_american' => 1.0, 'other' => 1.7),
                'income' => array('under_25k' => 17.0, 'between_25k_50k' => 20.0, 'between_50k_75k' => 18.0, 'between_75k_100k' => 16.0, 'over_100k' => 29.0, 'median_income' => 70249),
                'population' => array('total' => 20201249, 'urban' => 87.9, 'rural' => 12.1, 'density' => 421.0)
            ),
            'FL' => array(
                'racial' => array('white' => 53.4, 'black' => 15.4, 'hispanic' => 26.5, 'asian' => 2.9, 'native_american' => 0.5, 'other' => 1.3),
                'income' => array('under_25k' => 20.0, 'between_25k_50k' => 24.0, 'between_50k_75k' => 19.0, 'between_75k_100k' => 15.0, 'over_100k' => 22.0, 'median_income' => 59227),
                'population' => array('total' => 21538187, 'urban' => 91.2, 'rural' => 8.8, 'density' => 397.2)
            )
        );

        // Default data for states not in the array
        $default_data = array(
            'racial' => array('white' => 70.0, 'black' => 12.0, 'hispanic' => 10.0, 'asian' => 5.0, 'native_american' => 2.0, 'other' => 1.0),
            'income' => array('under_25k' => 20.0, 'between_25k_50k' => 25.0, 'between_50k_75k' => 20.0, 'between_75k_100k' => 15.0, 'over_100k' => 20.0, 'median_income' => 60000),
            'population' => array('total' => 3000000, 'urban' => 75.0, 'rural' => 25.0, 'density' => 150.0)
        );

        return $state_demographics[$state_code] ?? $default_data;
    }

    /**
     * AJAX handler for census data requests
     */
    public function ajax_get_census_data() {
        check_ajax_referer('census_api_nonce', 'nonce');

        $state_code = sanitize_text_field($_POST['state_code'] ?? '');
        $data_type = sanitize_text_field($_POST['data_type'] ?? 'demographics');

        if (empty($state_code)) {
            wp_die('Invalid state code');
        }

        switch ($data_type) {
            case 'racial':
                $data = $this->get_racial_composition($state_code);
                break;
            case 'income':
                $data = $this->get_income_distribution($state_code);
                break;
            case 'population':
                $data = $this->get_population_data($state_code);
                break;
            default:
                $data = $this->get_state_demographics($state_code);
        }

        wp_send_json_success($data);
    }

    /**
     * Clear cached census data
     */
    public function clear_cache($state_code = null) {
        if ($state_code) {
            delete_transient('census_demographics_' . $state_code);
        } else {
            // Clear all census cache
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_census_demographics_%'");
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_census_demographics_%'");
        }
    }

    /**
     * Get all supported states
     */
    public function get_supported_states() {
        return array(
            'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
            'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
            'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho',
            'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
            'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
            'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
            'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
            'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
            'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
            'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
            'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
            'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
            'WI' => 'Wisconsin', 'WY' => 'Wyoming'
        );
    }
}
?>