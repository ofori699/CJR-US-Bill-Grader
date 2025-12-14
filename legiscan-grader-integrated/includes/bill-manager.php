<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Enhanced Bill Manager - Handle bill data operations with folder structure support
 * UPDATED: Now integrates with database for grade retrieval
 */

class LegiScan_Bill_Manager {

    private $upload_dir;
    private $bills_folder;
    private $grading_engine;

    public function __construct() {
        $this->upload_dir = wp_upload_dir()['basedir'] . '/legiscan-bills/';
        $this->bills_folder = $this->upload_dir . 'bill/'; // Support for bill folder structure
        $this->ensure_directories();

        // Initialize grading engine for database lookups
        if (class_exists('LegiScan_Grading_Engine')) {
            $this->grading_engine = new LegiScan_Grading_Engine();
        }
    }

    /**
     * Ensure required directories exist
     */
    private function ensure_directories() {
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
        }
        if (!file_exists($this->bills_folder)) {
            wp_mkdir_p($this->bills_folder);
        }
    }

    /**
     * Get all bills from upload directory with database grades
     * UPDATED: Now includes database grade information
     */
    public function get_all_bills($include_grades = true) {
        $bills = [];

        // Check for folder structure first (bill/STATE/*.json)
        if (is_dir($this->bills_folder)) {
            $bills = array_merge($bills, $this->get_bills_from_folder_structure($include_grades));
        }

        // Also check for flat structure (*.json in root)
        $flat_files = glob($this->upload_dir . '*.json');
        foreach ($flat_files as $file) {
            $content = file_get_contents($file);
            $bill_data = json_decode($content, true);
            if ($bill_data) {
                $bill_data['source_file'] = basename($file);

                // Add grade information from database
                if ($include_grades && $this->grading_engine) {
                    $bill_id = $bill_data['bill']['bill_id'] ?? '';
                    if ($bill_id) {
                        $grade_data = $this->grading_engine->get_stored_grade($bill_id);
                        if ($grade_data) {
                            $bill_data['grade'] = [
                                'score' => $grade_data['score'],
                                'grade' => $grade_data['grade'],
                                'details' => $grade_data['grading_details'],
                                'processed_date' => $grade_data['processed_date']
                            ];

                            // Check for manual override
                            $manual_overrides = get_option('legiscan_manual_grade_overrides', []);
                            if (isset($manual_overrides[$bill_id])) {
                                $bill_data['grade']['manual_override'] = $manual_overrides[$bill_id];
                            }
                        }
                    }
                }

                $bills[] = $bill_data;
            }
        }

        return $bills;
    }

    /**
     * Get bills from folder structure (bill/STATE/*.json) with database grades
     * UPDATED: Now includes database grade information
     */
    private function get_bills_from_folder_structure($include_grades = true) {
        $bills = [];

        if (!is_dir($this->bills_folder)) {
            return $bills;
        }

        // Scan state directories
        $state_dirs = glob($this->bills_folder . '*', GLOB_ONLYDIR);

        foreach ($state_dirs as $state_dir) {
            $state_code = basename($state_dir);
            $bill_files = glob($state_dir . '/*.json');

            foreach ($bill_files as $file) {
                $content = file_get_contents($file);
                $bill_data = json_decode($content, true);

                if ($bill_data && isset($bill_data['bill'])) {
                    // Add metadata
                    $bill_data['bill']['state_code'] = $state_code;
                    $bill_data['bill']['source_file'] = basename($file);
                    $bill_data['bill']['file_path'] = $file;

                    // Add grade information from database
                    if ($include_grades && $this->grading_engine) {
                        $bill_id = $bill_data['bill']['bill_id'] ?? '';
                        if ($bill_id) {
                            $grade_data = $this->grading_engine->get_stored_grade($bill_id);
                            if ($grade_data) {
                                $bill_data['grade'] = [
                                    'score' => $grade_data['score'],
                                    'grade' => $grade_data['grade'],
                                    'details' => $grade_data['grading_details'],
                                    'processed_date' => $grade_data['processed_date']
                                ];

                                // Check for manual override
                                $manual_overrides = get_option('legiscan_manual_grade_overrides', []);
                                if (isset($manual_overrides[$bill_id])) {
                                    $bill_data['grade']['manual_override'] = $manual_overrides[$bill_id];
                                }
                            }
                        }
                    }

                    $bills[] = $bill_data;
                }
            }
        }

        return $bills;
    }

    /**
     * Get bills by state with database grades
     * UPDATED: Now includes database grade information
     */
    public function get_bills_by_state($state_code, $include_grades = true) {
        $state_dir = $this->bills_folder . strtoupper($state_code);
        $bills = [];

        if (is_dir($state_dir)) {
            $bill_files = glob($state_dir . '/*.json');

            foreach ($bill_files as $file) {
                $content = file_get_contents($file);
                $bill_data = json_decode($content, true);

                if ($bill_data && isset($bill_data['bill'])) {
                    $bill_data['bill']['state_code'] = $state_code;
                    $bill_data['bill']['source_file'] = basename($file);
                    $bill_data['bill']['file_path'] = $file;

                    // Add grade information from database
                    if ($include_grades && $this->grading_engine) {
                        $bill_id = $bill_data['bill']['bill_id'] ?? '';
                        if ($bill_id) {
                            $grade_data = $this->grading_engine->get_stored_grade($bill_id);
                            if ($grade_data) {
                                $bill_data['grade'] = [
                                    'score' => $grade_data['score'],
                                    'grade' => $grade_data['grade'],
                                    'details' => $grade_data['grading_details'],
                                    'processed_date' => $grade_data['processed_date']
                                ];

                                // Check for manual override
                                $manual_overrides = get_option('legiscan_manual_grade_overrides', []);
                                if (isset($manual_overrides[$bill_id])) {
                                    $bill_data['grade']['manual_override'] = $manual_overrides[$bill_id];
                                }
                            }
                        }
                    }

                    $bills[] = $bill_data;
                }
            }
        }

        return $bills;
    }

    /**
     * Get available states
     */
    public function get_available_states() {
        $states = [];

        if (is_dir($this->bills_folder)) {
            $state_dirs = glob($this->bills_folder . '*', GLOB_ONLYDIR);

            foreach ($state_dirs as $state_dir) {
                $state_code = basename($state_dir);
                $bill_count = count(glob($state_dir . '/*.json'));

                $states[$state_code] = [
                    'code' => $state_code,
                    'bill_count' => $bill_count,
                    'path' => $state_dir
                ];
            }
        }

        return $states;
    }

    /**
     * Get bill by ID (enhanced to search in folder structure) with database grade
     * UPDATED: Now includes database grade information
     */
    public function get_bill_by_id($bill_id, $include_grades = true) {
        // First try flat structure
        $file_path = $this->upload_dir . $bill_id . '.json';
        if (file_exists($file_path)) {
            $content = file_get_contents($file_path);
            $bill_data = json_decode($content, true);

            if ($bill_data && $include_grades && $this->grading_engine) {
                $grade_data = $this->grading_engine->get_stored_grade($bill_id);
                if ($grade_data) {
                    $bill_data['grade'] = [
                        'score' => $grade_data['score'],
                        'grade' => $grade_data['grade'],
                        'details' => $grade_data['grading_details'],
                        'processed_date' => $grade_data['processed_date']
                    ];

                    // Check for manual override
                    $manual_overrides = get_option('legiscan_manual_grade_overrides', []);
                    if (isset($manual_overrides[$bill_id])) {
                        $bill_data['grade']['manual_override'] = $manual_overrides[$bill_id];
                    }
                }
            }

            return $bill_data;
        }

        // Then search in folder structure
        $states = $this->get_available_states();
        foreach ($states as $state_code => $state_info) {
            $state_bills = $this->get_bills_by_state($state_code, $include_grades);
            foreach ($state_bills as $bill_data) {
                if (isset($bill_data['bill']['bill_id']) && $bill_data['bill']['bill_id'] == $bill_id) {
                    return $bill_data;
                }
            }
        }

        return null;
    }

    /**
     * Save bill data and optionally re-grade
     * UPDATED: Now triggers re-grading when bill is saved
     */
    public function save_bill($bill_data, $filename = null, $regrade = true) {
        if (!$filename && isset($bill_data['bill']['bill_id'])) {
            $filename = $bill_data['bill']['bill_id'] . '.json';
        }

        if (!$filename) {
            return false;
        }

        $file_path = $this->upload_dir . $filename;
        $result = file_put_contents($file_path, json_encode($bill_data, JSON_PRETTY_PRINT));

        // Re-grade the bill to update database
        if ($result && $regrade && $this->grading_engine) {
            $this->grading_engine->grade_bill($bill_data, true);
        }

        return $result;
    }

    /**
     * Delete bill from both file system and database
     * UPDATED: Now removes both file and database record
     */
    public function delete_bill($bill_id) {
        $success = true;

        // Get bill data first to find file path
        $bill_data = $this->get_bill_by_id($bill_id, false);

        // Delete from database
        if ($this->grading_engine) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'legiscan_bill_grades';
            $db_result = $wpdb->delete(
                $table_name,
                array('bill_id' => $bill_id),
                array('%s')
            );

            if ($db_result === false) {
                $success = false;
                error_log("Failed to delete bill from database: " . $bill_id);
            }
        }

        // Delete file
        if ($bill_data && isset($bill_data['bill']['file_path'])) {
            $file_path = $bill_data['bill']['file_path'];
            if (file_exists($file_path)) {
                if (!@unlink($file_path)) {
                    $success = false;
                    error_log("Failed to delete bill file: " . $file_path);
                }
            }
        } else {
            // Try to find and delete by bill_id
            $possible_paths = [
                $this->upload_dir . $bill_id . '.json'
            ];

            // Also check in state folders
            $states = $this->get_available_states();
            foreach ($states as $state_code => $state_info) {
                $possible_paths[] = $state_info['path'] . '/' . $bill_id . '.json';
            }

            foreach ($possible_paths as $path) {
                if (file_exists($path)) {
                    if (!@unlink($path)) {
                        $success = false;
                        error_log("Failed to delete bill file: " . $path);
                    }
                    break;
                }
            }
        }

        // Remove manual override if exists
        $manual_overrides = get_option('legiscan_manual_grade_overrides', []);
        if (isset($manual_overrides[$bill_id])) {
            unset($manual_overrides[$bill_id]);
            update_option('legiscan_manual_grade_overrides', $manual_overrides);
        }

        return $success;
    }

    /**
     * Get statistics with database integration
     * UPDATED: Now includes grade statistics from database
     */
    public function get_statistics() {
        $states = $this->get_available_states();
        $total_bills = 0;
        $total_states = count($states);

        foreach ($states as $state_info) {
            $total_bills += $state_info['bill_count'];
        }

        $stats = [
            'total_states' => $total_states,
            'total_bills' => $total_bills,
            'states' => $states
        ];

        // Add grade statistics if grading engine is available
        if ($this->grading_engine) {
            $grade_stats = $this->grading_engine->get_grade_statistics();
            $stats['grade_statistics'] = $grade_stats;
        }

        return $stats;
    }

    /**
     * Search bills by criteria with database grades
     * UPDATED: Now includes database grade information
     */
    public function search_bills($criteria = [], $include_grades = true) {
        $all_bills = $this->get_all_bills($include_grades);
        $filtered_bills = [];

        foreach ($all_bills as $bill_data) {
            $bill = isset($bill_data['bill']) ? $bill_data['bill'] : $bill_data;
            $matches = true;

            // Filter by state
            if (!empty($criteria['state']) && isset($bill['state_code'])) {
                if (strtoupper($criteria['state']) !== strtoupper($bill['state_code'])) {
                    $matches = false;
                }
            }

            // Filter by keyword in title
            if (!empty($criteria['keyword']) && isset($bill['title'])) {
                if (stripos($bill['title'], $criteria['keyword']) === false) {
                    $matches = false;
                }
            }

            // Filter by status
            if (!empty($criteria['status']) && isset($bill['status'])) {
                if ($criteria['status'] != $bill['status']) {
                    $matches = false;
                }
            }

            // Filter by grade
            if (!empty($criteria['grade']) && isset($bill_data['grade']['grade'])) {
                if (strtoupper($criteria['grade']) !== strtoupper($bill_data['grade']['grade'])) {
                    $matches = false;
                }
            }

            if ($matches) {
                $filtered_bills[] = $bill_data;
            }
        }

        return $filtered_bills;
    }
}
