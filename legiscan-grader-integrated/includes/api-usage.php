<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * API Usage Tracking Functions
 */

// Get current month's API usage
function legiscan_get_api_usage() {
    $usage = get_option('legiscan_api_usage', []);
    $month = date('Y-m');
    if (!isset($usage[$month])) {
        $usage[$month] = 0;
        update_option('legiscan_api_usage', $usage);
    }
    return $usage[$month];
}

// Increment API usage
function legiscan_increment_api_usage($count = 1) {
    $usage = get_option('legiscan_api_usage', []);
    $month = date('Y-m');
    if (!isset($usage[$month])) {
        $usage[$month] = 0;
    }
    $usage[$month] += $count;
    update_option('legiscan_api_usage', $usage);

    // Log the API call
    error_log("LegiScan API call made. Total this month: " . $usage[$month]);
}

// Reset API usage for current month
function legiscan_reset_api_usage() {
    $usage = get_option('legiscan_api_usage', []);
    $month = date('Y-m');
    $usage[$month] = 0;
    update_option('legiscan_api_usage', $usage);
}

// Get API usage history
function legiscan_get_api_usage_history() {
    return get_option('legiscan_api_usage', []);
}

// Check if API limit is approaching
function legiscan_is_api_limit_approaching($threshold = 0.9) {
    $usage = legiscan_get_api_usage();
    $limit = 30000; // LegiScan monthly limit
    return ($usage / $limit) >= $threshold;
}
