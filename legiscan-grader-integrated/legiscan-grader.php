<?php
/**
 * Plugin Name: LegiScan Grader - Enhanced with Database Integration
 * Plugin URI: https://yoursite.com/legiscan-grader
 * Description: A comprehensive plugin for grading and analyzing legislative bills using LegiScan API data with interactive maps, tables, and bill folder processing support.
 * Version: 2.4.0
 * Author: FOP Studios
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('LEGISCAN_GRADER_VERSION', '2.4.0');
define('LEGISCAN_GRADER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LEGISCAN_GRADER_PLUGIN_URL', plugin_dir_url(__FILE__));

class LegiScanGrader {

    public $bill_manager;
    public $grading_engine;
    public $api_handler;

    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_ajax_upload_dataset', array($this, 'handle_dataset_upload'));
        add_action('wp_ajax_filter_bills', array($this, 'handle_bill_filtering'));
        add_action('wp_ajax_grade_bills', array($this, 'handle_bill_grading'));
        add_action('wp_ajax_export_data', array($this, 'handle_data_export'));
        add_action('wp_ajax_process_bill_folder', array($this, 'handle_bill_folder_processing'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        

        // UPDATED AJAX handlers for manual grades page with database integration
        add_action('wp_ajax_get_bills_by_state', array($this, 'ajax_get_bills_by_state'));
        add_action('wp_ajax_save_manual_grade', array($this, 'ajax_save_manual_grade'));
        add_action('wp_ajax_delete_bill', array($this, 'ajax_delete_bill'));
        add_action('wp_ajax_process_bills_batch', array($this, 'ajax_process_bills_batch'));

        // Other AJAX handlers
        add_action('wp_ajax_save_state_grades', array($this, 'save_state_grades'));
        add_action('wp_ajax_save_grading_criteria', array($this, 'save_grading_criteria'));

        // NEW: Migration handler
        add_action('wp_ajax_migrate_manual_overrides', array($this, 'ajax_migrate_manual_overrides'));

        // Handlers for grade all bills and export results via admin-post.php
        add_action('admin_post_legiscan_grade_all_bills', array($this, 'handle_grade_all_bills'));
        add_action('admin_post_legiscan_export_results', array($this, 'handle_export_results'));

        // Shortcodes
        add_shortcode('legiscan_scorecard_map', array($this, 'render_scorecard_map'));
        add_shortcode('legiscan_bills_table', array($this, 'render_bills_table'));
        add_shortcode('legiscan_state_summary', array($this, 'render_state_summary'));
        add_shortcode('state_bills', array($this, 'render_state_bills'));
        add_shortcode('overall_grade', array($this, 'shortcode_overall_grade'));
        add_shortcode('racial_impact', array($this, 'shortcode_racial_impact'));
        add_shortcode('income_impact', array($this, 'shortcode_income_impact'));
        add_shortcode('state_impact_score', array($this, 'shortcode_state_impact_score'));

        // Clean up records
        add_action('wp_ajax_cleanup_orphaned_records', array($this, 'ajax_cleanup_orphaned_records'));
    }

    // Helper to get the current state code from the page slug
private function get_current_state_code() {
    global $post;
    if (!$post) return '';
    $slug = $post->post_name;
    $mapping = [
        'alabama' => 'AL', 'alaska' => 'AK', 'arizona' => 'AZ', 'arkansas' => 'AR',
        'california' => 'CA', 'colorado' => 'CO', 'connecticut' => 'CT', 'delaware' => 'DE',
        'florida' => 'FL', 'georgia' => 'GA', 'hawaii' => 'HI', 'idaho' => 'ID',
        'illinois' => 'IL', 'indiana' => 'IN', 'iowa' => 'IA', 'kansas' => 'KS',
        'kentucky' => 'KY', 'louisiana' => 'LA', 'maine' => 'ME', 'maryland' => 'MD',
        'massachusetts' => 'MA', 'michigan' => 'MI', 'minnesota' => 'MN', 'mississippi' => 'MS',
        'missouri' => 'MO', 'montana' => 'MT', 'nebraska' => 'NE', 'nevada' => 'NV',
        'new-hampshire' => 'NH', 'new-jersey' => 'NJ', 'new-mexico' => 'NM', 'new-york' => 'NY',
        'north-carolina' => 'NC', 'north-dakota' => 'ND', 'ohio' => 'OH', 'oklahoma' => 'OK',
        'oregon' => 'OR', 'pennsylvania' => 'PA', 'rhode-island' => 'RI', 'south-carolina' => 'SC',
        'south-dakota' => 'SD', 'tennessee' => 'TN', 'texas' => 'TX', 'utah' => 'UT',
        'vermont' => 'VT', 'virginia' => 'VA', 'washington' => 'WA', 'west-virginia' => 'WV',
        'wisconsin' => 'WI', 'wyoming' => 'WY'
    ];
    return $mapping[strtolower($slug)] ?? strtoupper($slug);
}

// Helper to get the latest grading_details JSON for a state
private function get_latest_grading_details($state_code) {
    global $wpdb;
    $table = $wpdb->prefix . 'legiscan_bill_grades';
    $json = $wpdb->get_var($wpdb->prepare(
        "SELECT grading_details FROM $table WHERE state_code = %s AND grading_details IS NOT NULL AND grading_details != '' ORDER BY processed_date DESC LIMIT 1",
        $state_code
    ));
    return $json ? json_decode($json, true) : null;
}

// [overall_grade]
public function shortcode_overall_grade($atts) {
    $state_code = $this->get_current_state_code();
    if (!$state_code) return '';
    $details = $this->get_latest_grading_details($state_code);
    return isset($details['grade']) ? esc_html($details['grade']) : '';
}

// [racial_impact]
public function shortcode_racial_impact($atts) {
    $state_code = $this->get_current_state_code();
    if (!$state_code) return '';
    $details = $this->get_latest_grading_details($state_code);
    return isset($details['breakdown']['racial_impact']) ? esc_html($details['breakdown']['racial_impact']) : '';
}

// [income_impact]
public function shortcode_income_impact($atts) {
    $state_code = $this->get_current_state_code();
    if (!$state_code) return '';
    $details = $this->get_latest_grading_details($state_code);
    return isset($details['breakdown']['income_impact']) ? esc_html($details['breakdown']['income_impact']) : '';
}

// [state_impact_score]
public function shortcode_state_impact_score($atts) {
    $state_code = $this->get_current_state_code();
    if (!$state_code) return '';
    $details = $this->get_latest_grading_details($state_code);
    return isset($details['breakdown']['state_impact']) ? esc_html($details['breakdown']['state_impact']) : '';
}

    public function init() {
        // Load required classes
        $this->load_dependencies();

        // Initialize components
        if (class_exists('LegiScan_Bill_Manager')) {
            $this->bill_manager = new LegiScan_Bill_Manager();
        }

        if (class_exists('LegiScan_Grading_Engine')) {
            $this->grading_engine = new LegiScan_Grading_Engine();
        }

        // Only initialize API handler if we have the class
        if (class_exists('LegiScan_API')) {
            $this->api_handler = new LegiScan_API();
        }

        // Create database tables if needed
        $this->create_tables();
    }

    private function load_dependencies() {
        $required_files = [
            'includes/bill-manager.php',
            'includes/grading-engine.php'
        ];

        foreach ($required_files as $file) {
            $file_path = LEGISCAN_GRADER_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }

        // Load other files if they exist
        $optional_files = [
            'includes/legiscan-api.php',
            'includes/census-api.php',
            'includes/api-usage.php',
            'includes/scorecard-map.php'
        ];

        foreach ($optional_files as $file) {
            $file_path = LEGISCAN_GRADER_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            'LegiScan Grader',
            'LegiScan Grader',
            'manage_options',
            'legiscan-grader',
            array($this, 'admin_page'),
            'dashicons-analytics',
            30
        );

        add_submenu_page(
            'legiscan-grader',
            'Bill Processing',
            'Bill Processing',
            'manage_options',
            'legiscan-bill-processing',
            array($this, 'bill_processing_page')
        );

        add_submenu_page(
            'legiscan-grader',
            'State Analysis',
            'State Analysis',
            'manage_options',
            'legiscan-state-analysis',
            array($this, 'state_analysis_page')
        );

        add_submenu_page(
            'legiscan-grader',
            'Export Data',
            'Export Data',
            'manage_options',
            'legiscan-export',
            array($this, 'export_page')
        );

        // Manual Grades submenu
        add_submenu_page(
            'legiscan-grader',
            'Manual Grades',
            'Manual Grades',
            'manage_options',
            'legiscan-grader-manual-grades',
            array($this, 'manual_grades_page')
        );

        // Grading Weights submenu
        add_submenu_page(
            'legiscan-grader',
            'Grading Weights',
            'Grading Weights',
            'manage_options',
            'legiscan-grader-weights-settings',
            array($this, 'grading_weights_page')
        );

        // NEW: Database Tools submenu
        add_submenu_page(
            'legiscan-grader',
            'Database Tools',
            'Database Tools',
            'manage_options',
            'legiscan-grader-database-tools',
            array($this, 'database_tools_page')
        );
    }

    public function admin_init() {
        register_setting('legiscan_grader_settings', 'legiscan_api_key');
        register_setting('legiscan_grader_settings', 'census_api_key');
        register_setting('legiscan_grader_settings', 'grading_criteria');
        register_setting('legiscan_grader_settings', 'auto_process_bills');
    }
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>LegiScan Grader Dashboard</h1>

            <div class="legiscan-overview">
                <h2>Welcome to LegiScan Grader</h2>
                <p>This plugin analyzes legislative bills and provides criminal justice reform grades.</p>

                <div class="legiscan-quick-stats">
                    <?php
                    if ($this->bill_manager) {
                        $stats = $this->bill_manager->get_statistics();
                        ?>
                        <div class="stat-box">
                            <h3>Quick Statistics</h3>
                            <p><strong>Total States:</strong> <?php echo esc_html($stats['total_states']); ?></p>
                            <p><strong>Total Bills:</strong> <?php echo esc_html(number_format($stats['total_bills'])); ?></p>
                            <?php if ($this->grading_engine): ?>
                                <?php $grade_stats = $this->grading_engine->get_grade_statistics(); ?>
                                <p><strong>Graded Bills:</strong> <?php echo esc_html(number_format($grade_stats['total_bills'])); ?></p>
                                <p><strong>Average Grade:</strong> <?php echo esc_html($grade_stats['average_grade']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php
                    }
                    ?>
                </div>

                <div class="legiscan-actions">
                    <h3>Quick Actions</h3>
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=legiscan-bill-processing')); ?>" class="button button-primary">
                            Process Bills
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=legiscan-state-analysis')); ?>" class="button button-secondary">
                            View State Analysis
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=legiscan-export')); ?>" class="button">
                            Export Data
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=legiscan-grader-database-tools')); ?>" class="button">
                            Database Tools
                        </a>
                    </p>
                </div>

                <div class="legiscan-settings">
                    <h3>Settings</h3>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('legiscan_grader_settings');
                        do_settings_sections('legiscan_grader_settings');
                        ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row">LegiScan API Key</th>
                                <td>
                                    <input type="text" name="legiscan_api_key" value="<?php echo esc_attr(get_option('legiscan_api_key')); ?>" class="regular-text" />
                                    <p class="description">Enter your LegiScan API key for live data access.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Auto Process Bills</th>
                                <td>
                                    <input type="checkbox" name="auto_process_bills" value="1" <?php checked(1, get_option('auto_process_bills'), true); ?> />
                                    <label>Automatically process new bills when uploaded</label>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button(); ?>
                    </form>
                </div>
            </div>
        </div>

        <style>
            .legiscan-overview {
                background: #fff;
                padding: 20px;
                margin: 20px 0;
                border: 1px solid #ddd;
                border-radius: 5px;
            }
            .stat-box {
                background: #f9f9f9;
                padding: 15px;
                margin: 15px 0;
                border-left: 4px solid #0073aa;
            }
            .legiscan-actions {
                margin: 20px 0;
            }
            .legiscan-settings {
                margin-top: 30px;
                border-top: 1px solid #ddd;
                padding-top: 20px;
            }
        </style>
        <?php
    }

    public function bill_processing_page() {
        if (!$this->bill_manager) {
            echo '<div class="wrap"><h1>Error</h1><p>Bill manager not initialized.</p></div>';
            return;
        }

        $stats = $this->bill_manager->get_statistics();

        // Show success notice after grading
        if (isset($_GET['graded']) && $_GET['graded'] == 1) {
            echo '<div class="notice notice-success is-dismissible"><p>All bills have been graded! <a href="' . esc_url(admin_url('admin.php?page=legiscan-state-analysis')) . '">View State Analysis</a></p></div>';
        }
        ?>
        <div class="wrap">
            <h1>Bill Processing Dashboard</h1>

            <div class="legiscan-stats-grid">
                <div class="stat-card">
                    <h3>Total States</h3>
                    <div class="stat-number"><?php echo esc_html($stats['total_states']); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Bills</h3>
                    <div class="stat-number"><?php echo esc_html(number_format($stats['total_bills'])); ?></div>
                </div>
            </div>

            <div class="legiscan-actions">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block; margin-right:10px;">
                    <?php wp_nonce_field('legiscan_grade_all_bills_nonce'); ?>
                    <input type="hidden" name="action" value="legiscan_grade_all_bills" />
                    <input type="submit" class="button button-primary" value="Grade All Bills" onclick="return confirm('This will grade all bills. Continue?');" />
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
                    <?php wp_nonce_field('legiscan_export_results_nonce'); ?>
                    <input type="hidden" name="action" value="legiscan_export_results" />
                    <input type="submit" class="button" value="Export Results" />
                </form>
            </div>

            <div id="processing-status" style="display:none;">
                <h3>Processing Status</h3>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 0%"></div>
                </div>
                <div class="status-text">Ready to process...</div>
            </div>

            <div class="states-grid">
                <?php if (isset($stats['states']) && is_array($stats['states'])): ?>
                    <?php foreach ($stats['states'] as $state_code => $state_info): ?>
                    <div class="state-card">
                        <h4><?php echo esc_html($state_code); ?></h4>
                        <p><?php echo esc_html(number_format($state_info['bill_count'])); ?> bills</p>
                        <button class="button process-state" data-state="<?php echo esc_attr($state_code); ?>">
                            Process <?php echo esc_html($state_code); ?>
                        </button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <style>
        .legiscan-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .stat-card {
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-align: center;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #0073aa;
        }
        .states-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .state-card {
            background: #f9f9f9;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-align: center;
        }
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: #0073aa;
            transition: width 0.3s ease;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('.process-state').click(function() {
                var state = $(this).data('state');
                $(this).text('Processing...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'process_bill_folder',
                        state: state,
                        _wpnonce: '<?php echo wp_create_nonce("legiscan_nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Processed ' + response.data.processed + ' bills for ' + state);
                        } else {
                            alert('Error processing ' + state);
                        }
                    },
                    complete: function() {
                        $('.process-state[data-state="' + state + '"]').text('Process ' + state);
                    }
                });
            });
        });

        // Batch processing functionality
        if (window.location.search.includes('start_batch=1')) {
            startBatchProcessing();
        }

        function startBatchProcessing() {
            $('#processing-status').show();
            $('.status-text').text('Starting batch processing...');
            processBatch(0);
        }

        function processBatch(offset) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'process_bills_batch',
                    offset: offset,
                    _wpnonce: '<?php echo wp_create_nonce("legiscan_nonce"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        
                        // Update progress
                        $('.progress-fill').css('width', data.progress + '%');
                        $('.status-text').text(data.message);
                        
                        if (data.completed) {
                            // All done!
                            $('.status-text').text('✅ ' + data.message);
                            setTimeout(function() {
                                window.location.href = '<?php echo admin_url('admin.php?page=legiscan-state-analysis'); ?>';
                            }, 2000);
                        } else {
                            // Process next batch
                            setTimeout(function() {
                                processBatch(data.processed);
                            }, 500); // Small delay to prevent overwhelming the server
                        }
                    } else {
                        $('.status-text').text('❌ Error: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function() {
                    $('.status-text').text('❌ Network error occurred');
                }
            });
        }
        </script>
        <?php
    }
    // AJAX HANDLERS - Adding them back
    public function handle_dataset_upload() {
        check_ajax_referer('legiscan_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        // Handle dataset upload logic here
        wp_send_json_success(['message' => 'Dataset upload functionality']);
    }

    public function handle_bill_filtering() {
        check_ajax_referer('legiscan_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        // Handle bill filtering logic here
        wp_send_json_success(['message' => 'Bill filtering functionality']);
    }

    public function handle_bill_grading() {
        check_ajax_referer('legiscan_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        // Handle bill grading logic here
        wp_send_json_success(['message' => 'Bill grading functionality']);
    }

    public function handle_bill_folder_processing() {
        check_ajax_referer('legiscan_nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        if (!$this->bill_manager || !$this->grading_engine) {
            wp_send_json_error('Required components not initialized');
            return;
        }

        $state = sanitize_text_field($_POST['state'] ?? '');

        if ($state) {
            // Process specific state
            $bills = $this->bill_manager->get_bills_by_state($state);
            $results = $this->grading_engine->grade_bills_batch($bills, true); // Store in DB

            wp_send_json_success([
                'state' => $state,
                'processed' => count($bills),
                'results' => $results['statistics']
            ]);
        } else {
            // Process all states
            $states = $this->bill_manager->get_available_states();
            $total_processed = 0;
            $state_results = [];

            foreach ($states as $state_code => $state_info) {
                $bills = $this->bill_manager->get_bills_by_state($state_code);
                $results = $this->grading_engine->grade_bills_batch($bills, true); // Store in DB
                $state_results[$state_code] = $results['statistics'];
                $total_processed += count($bills);
            }

            wp_send_json_success([
                'total_processed' => $total_processed,
                'states_processed' => count($states),
                'state_results' => $state_results
            ]);
        }
    }

public function state_analysis_page() {
    if (!$this->bill_manager || !$this->grading_engine) {
        echo '<div class="wrap"><h1>Error</h1><p>Required components not initialized.</p></div>';
        return;
    }

    $states = $this->bill_manager->get_available_states();
    $state_grades = [];

    foreach ($states as $state_code => $state_info) {
        if ($state_info['bill_count'] > 0) {
            $bills = $this->bill_manager->get_bills_by_state($state_code);
            $grading_result = $this->grading_engine->grade_bills_batch($bills);
            $state_grades[$state_code] = $grading_result['statistics'];
        }
    }

    uasort($state_grades, function($a, $b) {
        return $b['average_score'] <=> $a['average_score'];
    });

    ?>
    <div class="wrap">
        <h1>State Analysis Dashboard</h1>

        <form id="state-grades-form">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>State</th>
                        <th>Bills</th>
                        <th>Avg Score</th>
                        <th>Grade</th>
                        <th>Grade Distribution</th>
                        <th>State Grade</th>
                        <th>State Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rank = 1;
                    foreach ($state_grades as $state_code => $stats): 
                        $existing_grade = get_option('legiscan_state_grade_' . $state_code, '');
                        $existing_details = get_option('legiscan_state_details_' . $state_code, '');

                        $auto_grade = $stats['average_grade'];
                        $manual_grade = $existing_grade;

                        $auto_score = $this->calculate_numeric_score_from_grade($auto_grade);
                        $manual_score = $manual_grade ? $this->calculate_numeric_score_from_grade($manual_grade) : null;

                        if ($manual_score !== null) {
                            $blended_score = ($auto_score * 0.7) + ($manual_score * 0.3);
                        } else {
                            $blended_score = $auto_score;
                        }

                        $blended_grade = $this->convert_numeric_score_to_grade($blended_score);
                    ?>
                    <tr>
                        <td><?php echo esc_html($rank++); ?></td>
                        <td><strong><?php echo esc_html($state_code); ?></strong></td>
                        <td><?php echo esc_html(number_format($stats['total_bills'])); ?></td>
                        <td><?php echo esc_html($stats['average_score']); ?></td>
                        <td>
                            <span class="grade-<?php echo esc_attr(strtolower($blended_grade)); ?>">
                                <?php echo esc_html($blended_grade); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (isset($stats['grade_distribution']) && is_array($stats['grade_distribution'])): ?>
                                <?php foreach ($stats['grade_distribution'] as $grade => $count): ?>
                                <span class="grade-badge grade-<?php echo esc_attr(strtolower($grade)); ?>">
                                    <?php echo esc_html($grade); ?>: <?php echo esc_html($count); ?>
                                </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <select name="state_grade[<?php echo esc_attr($state_code); ?>]">
                                <option value="A" <?php selected($existing_grade, 'A'); ?>>A</option>
                                <option value="B" <?php selected($existing_grade, 'B'); ?>>B</option>
                                <option value="C" <?php selected($existing_grade, 'C'); ?>>C</option>
                                <option value="D" <?php selected($existing_grade, 'D'); ?>>D</option>
                                <option value="F" <?php selected($existing_grade, 'F'); ?>>F</option>
                            </select>
                        </td>
                        <td>
                            <textarea name="state_details[<?php echo esc_attr($state_code); ?>]" rows="3" cols="30"><?php echo esc_textarea($existing_details); ?></textarea>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p>
                <button type="button" class="button button-primary" id="save-state-grades-btn">Save State Grades</button>
            </p>
        </form>
    </div>

    <style>
    .grade-a { color: #28a745; font-weight: bold; }
    .grade-b { color: #17a2b8; font-weight: bold; }
    .grade-c { color: #ffc107; font-weight: bold; }
    .grade-d { color: #fd7e14; font-weight: bold; }
    .grade-f { color: #dc3545; font-weight: bold; }
    .grade-badge {
        display: inline-block;
        padding: 2px 6px;
        margin: 1px;
        border-radius: 3px;
        font-size: 11px;
        background: #f8f9fa;
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        $('#save-state-grades-btn').on('click', function() {
            var data = $('#state-grades-form').serialize();
            data += '&action=save_state_grades&nonce=<?php echo wp_create_nonce('legiscan_nonce'); ?>';

            $.post(ajaxurl, data, function(response) {
                if (response.success) {
                    alert('State grades and details saved successfully!');
                } else {
                    alert('Error saving state grades and details.');
                }
            });
        });
    });
    </script>
    <?php
}
    public function handle_data_export() {
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'legiscan_nonce')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $type = sanitize_text_field($_GET['type'] ?? '');

        switch ($type) {
            case 'state_scorecard':
                $this->export_state_scorecard();
                break;
            case 'all_bills':
                $this->export_all_bills();
                break;
            case 'comprehensive':
                $this->export_comprehensive_report();
                break;
            default:
                wp_die('Invalid export type');
        }
    }

    private function export_state_scorecard() {
        if (!$this->bill_manager || !$this->grading_engine) {
            wp_die('Required components not initialized');
        }

        $states = $this->bill_manager->get_available_states();

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="state_scorecard.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['State', 'Total Bills', 'Average Score', 'Grade', 'A', 'B', 'C', 'D', 'F']);

        foreach ($states as $state_code => $state_info) {
            // UPDATED: Get from database first
            $stats = $this->grading_engine->get_grade_statistics($state_code);
            
            if ($stats['total_bills'] == 0) {
                // Fallback: grade bills if not in database
                $bills = $this->bill_manager->get_bills_by_state($state_code);
                $results = $this->grading_engine->grade_bills_batch($bills, true);
                $stats = $results['statistics'];
            }

            fputcsv($output, [
                $state_code,
                $stats['total_bills'],
                $stats['average_score'],
                $stats['average_grade'],
                $stats['grade_distribution']['A'] ?? 0,
                $stats['grade_distribution']['B'] ?? 0,
                $stats['grade_distribution']['C'] ?? 0,
                $stats['grade_distribution']['D'] ?? 0,
                $stats['grade_distribution']['F'] ?? 0
            ]);
        }

        fclose($output);
        exit;
    }

    private function export_all_bills() {
        if (!$this->bill_manager || !$this->grading_engine) {
            wp_die('Required components not initialized');
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="all_bills_graded.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['State', 'Bill Number', 'Title', 'Status', 'Score', 'Grade', 'Manual Override']);

        // UPDATED: Get from database
        $all_stored_grades = $this->grading_engine->get_all_stored_grades();
        
        foreach ($all_stored_grades as $grade_record) {
            // Check for manual override
            $manual_override = '';
            global $wpdb;
            $table_name = $wpdb->prefix . 'legiscan_bill_grades';
            $manual_grade = $wpdb->get_var($wpdb->prepare(
                "SELECT manual_grade FROM $table_name WHERE bill_id = %s",
                $grade_record['bill_id']
            ));
            
            if ($manual_grade) {
                $manual_override = $manual_grade;
            }

            fputcsv($output, [
                $grade_record['state_code'],
                $grade_record['bill_number'],
                substr($grade_record['title'], 0, 100),
                '', // Status not stored in grades table
                $grade_record['score'],
                $manual_override ?: $grade_record['grade'],
                $manual_override ? 'Yes' : 'No'
            ]);
        }

        fclose($output);
        exit;
    }

    private function export_comprehensive_report() {
        if (!$this->bill_manager || !$this->grading_engine) {
            wp_die('Required components not initialized');
        }

        $states = $this->bill_manager->get_available_states();
        $comprehensive_data = [];

        foreach ($states as $state_code => $state_info) {
            // UPDATED: Get from database
            $stats = $this->grading_engine->get_grade_statistics($state_code);
            $stored_grades = $this->grading_engine->get_all_stored_grades($state_code);
            
            $comprehensive_data[$state_code] = [
                'statistics' => $stats,
                'individual_grades' => $stored_grades
            ];
        }

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="comprehensive_report.json"');

        echo json_encode($comprehensive_data, JSON_PRETTY_PRINT);
        exit;
    }

    // New handlers for admin-post.php form submissions
    public function handle_grade_all_bills() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }
        check_admin_referer('legiscan_grade_all_bills_nonce');

        if (!$this->bill_manager || !$this->grading_engine) {
            wp_die('Required components not initialized');
        }

        // Instead of processing all bills, redirect to batch processing page
        wp_redirect(admin_url('admin.php?page=legiscan-bill-processing&start_batch=1'));
        exit;
    }

    public function handle_export_results() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }
        check_admin_referer('legiscan_export_results_nonce');

        // UPDATED: Export from database
        $all_grades = $this->grading_engine->get_all_stored_grades();
        
        if (empty($all_grades)) {
            wp_redirect(admin_url('admin.php?page=legiscan-export&export=0'));
            exit;
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="legiscan_grading_results.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Bill ID', 'Title', 'Score', 'Grade', 'Manual Grade', 'State', 'Processed Date']);

        foreach ($all_grades as $grade_record) {
            fputcsv($output, [
                $grade_record['bill_id'],
                $grade_record['title'],
                $grade_record['score'],
                $grade_record['grade'],
                $grade_record['manual_grade'] ?? '',
                $grade_record['state_code'],
                $grade_record['processed_date']
            ]);
        }

        fclose($output);
        exit;
    }
    // --- Updated Manual Grades Page ---
    public function manual_grades_page() {
        ?>
        <div class="wrap">
            <h1>Manual Grade Override</h1>
            <p>Select a state to view and manually override grades for bills in that state.</p>

            <div class="legiscan-migration-notice" style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; border-radius: 4px;">
                <h3>🔄 Database Migration Available</h3>
                <p>If you have existing manual grade overrides stored in WordPress options, you can migrate them to the database for better performance.</p>
                <button id="migrate-overrides-btn" class="button button-secondary">Migrate Existing Overrides to Database</button>
                <div id="migration-status" style="margin-top: 10px;"></div>
            </div>

            <div id="legiscan-manual-grades-container" style="display: flex; gap: 20px;">
                <div id="state-list" style="min-width: 150px; max-height: 600px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
                    <h2>States</h2>
                    <ul>
                        <?php
                        $states = $this->bill_manager ? $this->bill_manager->get_available_states() : [];
                        foreach ($states as $state_code => $state_info) {
                            echo '<li><a href="#" class="state-link" data-state="' . esc_attr($state_code) . '">' . esc_html($state_code) . '</a></li>';
                        }
                        ?>
                    </ul>
                </div>

                <div id="bills-table-container" style="flex-grow: 1;">
                    <h2 id="selected-state-title">Select a state to view bills</h2>
                    <div id="bills-table-wrapper">
                        <!-- Bills table will be loaded here via AJAX -->
                    </div>
                </div>
            </div>
        </div>

        <style>
            #state-list ul {
                list-style: none;
                padding-left: 0;
            }
            #state-list li {
                margin-bottom: 8px;
            }
            #state-list a {
                text-decoration: none;
                color: #0073aa;
                cursor: pointer;
            }
            #state-list a:hover {
                text-decoration: underline;
            }
            #bills-table-wrapper table {
                width: 100%;
                border-collapse: collapse;
            }
            #bills-table-wrapper th, #bills-table-wrapper td {
                border: 1px solid #ddd;
                padding: 8px;
            }
            #bills-table-wrapper th {
                background-color: #f1f1f1;
            }
            .manual-grade-input {
                width: 50px;
                text-align: center;
            }
            .delete-bill {
                background-color: #dc3545;
                color: white;
                border: none;
                padding: 5px 10px;
                cursor: pointer;
                border-radius: 3px;
            }
            .delete-bill:hover {
                background-color: #b02a37;
            }
            .grade-override {
                background-color: #fff3cd;
                font-weight: bold;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Migration functionality
            $('#migrate-overrides-btn').on('click', function() {
                $(this).prop('disabled', true).text('Migrating...');
                $('#migration-status').html('<em>Processing migration...</em>');
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'migrate_manual_overrides',
                        nonce: '<?php echo wp_create_nonce('legiscan_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#migration-status').html('<span style="color: green;">✅ ' + response.data.message + '</span>');
                        } else {
                            $('#migration-status').html('<span style="color: red;">❌ Migration failed: ' + response.data + '</span>');
                        }
                    },
                    error: function() {
                        $('#migration-status').html('<span style="color: red;">❌ AJAX error during migration</span>');
                    },
                    complete: function() {
                        $('#migrate-overrides-btn').prop('disabled', false).text('Migrate Existing Overrides to Database');
                    }
                });
            });

            $('.state-link').on('click', function(e) {
                e.preventDefault();
                var state = $(this).data('state');
                $('#selected-state-title').text('Bills for ' + state);
                $('#bills-table-wrapper').html('<p>Loading bills...</p>');

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'get_bills_by_state',
                        state: state,
                        nonce: '<?php echo wp_create_nonce('legiscan_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#bills-table-wrapper').html(response.data.html);
                        } else {
                            $('#bills-table-wrapper').html('<p>Error loading bills.</p>');
                        }
                    },
                    error: function() {
                        $('#bills-table-wrapper').html('<p>AJAX error occurred.</p>');
                    }
                });
            });

            $('#bills-table-wrapper').on('change', '.manual-grade-input', function() {
                var billId = $(this).data('bill-id');
                var newGrade = $(this).val().toUpperCase();
                var $row = $(this).closest('tr');

                if (!['A','B','C','D','F',''].includes(newGrade)) {
                    alert('Invalid grade. Please enter A, B, C, D, F, or leave empty.');
                    $(this).val('');
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'save_manual_grade',
                        bill_id: billId,
                        grade: newGrade,
                        nonce: '<?php echo wp_create_nonce('legiscan_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            // Visual feedback
                            if (newGrade) {
                                $row.addClass('grade-override');
                            } else {
                                $row.removeClass('grade-override');
                            }
                            alert('Manual grade saved.');
                        } else {
                            alert('Error saving manual grade.');
                        }
                    },
                    error: function() {
                        alert('AJAX error saving manual grade.');
                    }
                });
            });

            // Delete bill functionality
            $('#bills-table-wrapper').on('click', '.delete-bill', function() {
                var billId = $(this).data('bill-id');
                if (confirm('Are you sure you want to delete this bill? This will remove both the file and database record.')) {
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'delete_bill',
                            bill_id: billId,
                            nonce: '<?php echo wp_create_nonce('legiscan_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Bill deleted successfully.');
                                // Reload bills for the current state
                                var currentState = $('#selected-state-title').text().replace('Bills for ', '');
                                $('.state-link[data-state="' + currentState + '"]').trigger('click');
                            } else {
                                alert('Error deleting bill.');
                            }
                        },
                        error: function() {
                            alert('AJAX error deleting bill.');
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }

    // AJAX handler: Get bills by state (UPDATED for database integration)
    public function ajax_get_bills_by_state() {
        check_ajax_referer('legiscan_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $state = sanitize_text_field($_POST['state'] ?? '');
        if (empty($state) || !$this->bill_manager || !$this->grading_engine) {
            wp_send_json_error('Invalid state or components not initialized');
        }

        // Get bills from files
        $bills = $this->bill_manager->get_bills_by_state($state);
        if (empty($bills)) {
            wp_send_json_success(['html' => '<p>No bills found for ' . esc_html($state) . '.</p>']);
        }

        ob_start();
        ?>
        <table>
            <thead>
                <tr>
                    <th>Bill ID</th>
                    <th>Title</th>
                    <th>Auto Grade</th>
                    <th>Manual Grade</th>
                    <th>Final Grade</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($bills as $bill_data) {
                    $bill = $bill_data['bill'] ?? $bill_data;
                    $bill_id = $bill['bill_id'] ?? '';
                    $title = $bill['title'] ?? '';
                    
                    // Get stored grade from database
                    $stored_grade = $this->grading_engine->get_stored_grade($bill_id);
                    $auto_grade = $stored_grade ? $stored_grade['grade'] : 'Not Graded';
                    $manual_grade = $stored_grade ? ($stored_grade['manual_grade'] ?? '') : '';
                    $final_grade = $manual_grade ?: $auto_grade;
                    
                    $row_class = $manual_grade ? 'grade-override' : '';
                    ?>
                    <tr class="<?php echo esc_attr($row_class); ?>">
                        <td><?php echo esc_html($bill_id); ?></td>
                        <td><?php echo esc_html(substr($title, 0, 60)); ?>...</td>
                        <td><?php echo esc_html($auto_grade); ?></td>
                        <td>
                            <input type="text" class="manual-grade-input" data-bill-id="<?php echo esc_attr($bill_id); ?>" value="<?php echo esc_attr($manual_grade); ?>" maxlength="1" style="width: 50px; text-align: center;" />
                        </td>
                        <td><strong><?php echo esc_html($final_grade); ?></strong></td>
                        <td>
                            <button class="delete-bill" data-bill-id="<?php echo esc_attr($bill_id); ?>">Delete</button>
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
        <?php
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    // AJAX handler: Save manual grade (UPDATED for database integration)
    public function ajax_save_manual_grade() {
        check_ajax_referer('legiscan_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $bill_id = sanitize_text_field($_POST['bill_id'] ?? '');
        $grade = strtoupper(sanitize_text_field($_POST['grade'] ?? ''));

        if (empty($bill_id) || !in_array($grade, ['A','B','C','D','F',''])) {
            wp_send_json_error('Invalid input');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'legiscan_bill_grades';

        if ($grade === '') {
            // Remove manual override
            $result = $wpdb->update(
                $table_name,
                [
                    'manual_grade' => null,
                    'manual_override_date' => null,
                    'manual_override_user' => null
                ],
                ['bill_id' => $bill_id],
                ['%s', '%s', '%d'],
                ['%s']
            );
        } else {
            // Set manual override
            $result = $wpdb->update(
                $table_name,
                [
                    'manual_grade' => $grade,
                    'manual_override_date' => current_time('mysql'),
                    'manual_override_user' => get_current_user_id()
                ],
                ['bill_id' => $bill_id],
                ['%s', '%s', '%d'],
                ['%s']
            );
        }

        if ($result !== false) {
            wp_send_json_success(['message' => 'Manual grade saved']);
        } else {
            wp_send_json_error('Failed to save manual grade');
        }
    }

    // AJAX handler: Delete bill (UPDATED for database integration)
    public function ajax_delete_bill() {
        check_ajax_referer('legiscan_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $bill_id = sanitize_text_field($_POST['bill_id'] ?? '');
        if (empty($bill_id) || !$this->bill_manager) {
            wp_send_json_error('Invalid bill ID or bill manager not initialized');
        }

        // Delete from database first
        global $wpdb;
        $table_name = $wpdb->prefix . 'legiscan_bill_grades';
        $db_result = $wpdb->delete($table_name, ['bill_id' => $bill_id], ['%s']);

        // Delete file using bill manager
        $file_result = $this->bill_manager->delete_bill($bill_id);

        if ($db_result !== false && $file_result) {
            wp_send_json_success(['message' => 'Bill deleted successfully']);
        } else {
            wp_send_json_error('Failed to delete bill completely');
        }
    }

    // AJAX handler: Process bills batch (NEW for batch processing)
    public function ajax_process_bills_batch() {
        check_ajax_referer('legiscan_nonce', '_wpnonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        if (!$this->bill_manager || !$this->grading_engine) {
            wp_send_json_error('Required components not initialized');
        }

        $offset = intval($_POST['offset'] ?? 0);
        $batch_size = 50; // Process 50 bills at a time

        // Get all bills
        $all_bills = [];
        $states = $this->bill_manager->get_available_states();
        foreach ($states as $state_code => $state_info) {
            $state_bills = $this->bill_manager->get_bills_by_state($state_code);
            foreach ($state_bills as $bill_data) {
                $bill = $bill_data['bill'] ?? $bill_data;
                $bill['state_code'] = $state_code;
                $all_bills[] = $bill;
            }
        }

        $total_bills = count($all_bills);
        $batch_bills = array_slice($all_bills, $offset, $batch_size);
        
        if (empty($batch_bills)) {
            wp_send_json_success([
                'completed' => true,
                'processed' => $total_bills,
                'total' => $total_bills,
                'progress' => 100,
                'message' => 'All bills processed successfully!'
            ]);
        }

        // Process this batch
        $processed_count = 0;
        foreach ($batch_bills as $bill) {
            $result = $this->grading_engine->grade_bill($bill, true); // Store in DB
            if ($result) {
                $processed_count++;
            }
        }

        $total_processed = $offset + $processed_count;
        $progress = ($total_processed / $total_bills) * 100;

        wp_send_json_success([
            'completed' => false,
            'processed' => $total_processed,
            'total' => $total_bills,
            'progress' => round($progress, 1),
            'message' => "Processed {$total_processed} of {$total_bills} bills..."
        ]);
    }

    // AJAX handler: Migrate manual overrides (NEW)
    public function ajax_migrate_manual_overrides() {
        check_ajax_referer('legiscan_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'legiscan_bill_grades';
        
        // Get all options that start with 'legiscan_manual_grade_'
        $manual_grades = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options} 
             WHERE option_name LIKE 'legiscan_manual_grade_%'"
        );

        $migrated_count = 0;
        $errors = [];

        foreach ($manual_grades as $option) {
            $bill_id = str_replace('legiscan_manual_grade_', '', $option->option_name);
            $grade = $option->option_value;

            // Check if bill exists in grades table
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE bill_id = %s",
                $bill_id
            ));

            if ($exists) {
                // Update with manual grade
                $result = $wpdb->update(
                    $table_name,
                    [
                        'manual_grade' => $grade,
                        'manual_override_date' => current_time('mysql'),
                        'manual_override_user' => get_current_user_id()
                    ],
                    ['bill_id' => $bill_id],
                    ['%s', '%s', '%d'],
                    ['%s']
                );

                if ($result !== false) {
                    $migrated_count++;
                    // Delete the old option
                    delete_option($option->option_name);
                } else {
                    $errors[] = "Failed to migrate grade for bill: $bill_id";
                }
            } else {
                $errors[] = "Bill not found in database: $bill_id";
            }
        }

        if ($migrated_count > 0) {
            wp_send_json_success([
                'message' => "Successfully migrated {$migrated_count} manual grade overrides to database.",
                'errors' => $errors
            ]);
        } else {
            wp_send_json_success([
                'message' => 'No manual grade overrides found to migrate.',
                'errors' => $errors
            ]);
        }
    }

public function render_state_bills($atts) {
    global $post;
    
    // Get state from current page slug or custom field
    $state_name = '';
    if ($post) {
        if (strpos($post->post_name, 'state/') === 0) {
            $state_name = str_replace('state/', '', $post->post_name);
        } else {
            $state_name = $post->post_name;
        }
    }
    if (empty($state_name)) {
        return '<p>No state specified.</p>';
    }

    // Map state name to abbreviation
    $state_mapping = [
        'alabama' => 'AL', 'alaska' => 'AK', 'arizona' => 'AZ', 'arkansas' => 'AR',
        'california' => 'CA', 'colorado' => 'CO', 'connecticut' => 'CT', 'delaware' => 'DE',
        'florida' => 'FL', 'georgia' => 'GA', 'hawaii' => 'HI', 'idaho' => 'ID',
        'illinois' => 'IL', 'indiana' => 'IN', 'iowa' => 'IA', 'kansas' => 'KS',
        'kentucky' => 'KY', 'louisiana' => 'LA', 'maine' => 'ME', 'maryland' => 'MD',
        'massachusetts' => 'MA', 'michigan' => 'MI', 'minnesota' => 'MN', 'mississippi' => 'MS',
        'missouri' => 'MO', 'montana' => 'MT', 'nebraska' => 'NE', 'nevada' => 'NV',
        'new-hampshire' => 'NH', 'new-jersey' => 'NJ', 'new-mexico' => 'NM', 'new-york' => 'NY',
        'north-carolina' => 'NC', 'north-dakota' => 'ND', 'ohio' => 'OH', 'oklahoma' => 'OK',
        'oregon' => 'OR', 'pennsylvania' => 'PA', 'rhode-island' => 'RI', 'south-carolina' => 'SC',
        'south-dakota' => 'SD', 'tennessee' => 'TN', 'texas' => 'TX', 'utah' => 'UT',
        'vermont' => 'VT', 'virginia' => 'VA', 'washington' => 'WA', 'west-virginia' => 'WV',
        'wisconsin' => 'WI', 'wyoming' => 'WY'
    ];
    $state_code = $state_mapping[strtolower($state_name)] ?? strtoupper($state_name);

    if (!$this->bill_manager) {
        return '<p>Bill manager not available.</p>';
    }

    $bills = $this->bill_manager->get_bills_by_state($state_code);
    if (empty($bills)) {
        return '<p>No bills found for ' . esc_html($state_name) . '.</p>';
    }

    // Status mapping (define once, outside the loop)
    $status_map = [
        1 => 'Introduced',
        2 => 'In Committee',
        3 => 'Reported',
        4 => 'Passed One Chamber',
        5 => 'Passed Both Chambers',
        6 => 'Enrolled',
        7 => 'Signed by Governor',
        8 => 'Vetoed',
        9 => 'Failed',
        10 => 'Withdrawn',
        11 => 'Other',
    ];

    // Table styling with custom header color and full title display
    $output = '<style>
        .state-bills-table th { background:#940035 !important; color:#fff !important; }
        .state-bills-table { width:100%; border-collapse:collapse; margin:1em 0; }
        .state-bills-table th, .state-bills-table td { border:1px solid #ccc; padding:8px; text-align:left; }
        .state-bills-table td.bill-title { white-space:normal; word-break:break-word; max-width:400px; }
    </style>';
    $output .= '<table class="state-bills-table">';
    $output .= '<tr>
        <th>Bill Number</th>
        <th>Bill Title</th>
        <th>Status</th>
  <th style="display:none;">Racial Grade</th>
    <th style="display:none;">Income Grade</th>
    <th style="display:none;">State Impact Grade</th>
    </tr>';

    foreach ($bills as $bill_data) {
        $bill = $bill_data['bill'] ?? $bill_data;
        $bill_number = esc_html($bill['bill_number'] ?? '');
        $bill_title = esc_html($bill['title'] ?? '');
        
        // Map status number to readable text
        $status_raw = $bill['status'] ?? '';
        $bill_status = isset($status_map[$status_raw]) ? $status_map[$status_raw] : esc_html($status_raw);

        // Try to get grades from the bill array first
        $racial_grade = esc_html($bill['racial_grade'] ?? '');
        $income_grade = esc_html($bill['income_grade'] ?? '');
        $state_impact_grade = esc_html($bill['state_impact_grade'] ?? '');

        // If not found, try to get from grading engine
        if (empty($racial_grade) && $this->grading_engine && !empty($bill['bill_id'])) {
            $grade_record = $this->grading_engine->get_stored_grade($bill['bill_id']);
            $racial_grade = esc_html($grade_record['racial_grade'] ?? '');
            $income_grade = esc_html($grade_record['income_grade'] ?? '');
            $state_impact_grade = esc_html($grade_record['state_impact_grade'] ?? '');
        }

        $output .= "<tr>
            <td>{$bill_number}</td>
            <td class='bill-title'>{$bill_title}</td>
            <td>{$bill_status}</td>
 <td style='display:none;'>{$racial_grade}</td>
    <td style='display:none;'>{$income_grade}</td>
    <td style='display:none;'>{$state_impact_grade}</td>
        </tr>";
    }
    
    $output .= '</table>';
    return $output;
}
    // Grading Weights Page
    public function grading_weights_page() {
        $positive_keywords = get_option('grading_criteria_positive_keywords', []);
        $negative_keywords = get_option('grading_criteria_negative_keywords', []);
        $subject_weights = get_option('grading_criteria_subject_weights', []);

        ?>
        <div class="wrap">
            <h1>Grading Weights & Criteria</h1>
            <p>Configure the keywords and weights used for automatic bill grading.</p>

            <form id="grading-criteria-form">
                <?php wp_nonce_field('save_grading_criteria', 'grading_criteria_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="positive_keywords">Positive Keywords (JSON)</label>
                        </th>
                        <td>
                            <textarea id="positive_keywords" name="positive_keywords" rows="10" cols="80" class="large-text code"><?php echo esc_textarea(json_encode($positive_keywords, JSON_PRETTY_PRINT)); ?></textarea>
                            <p class="description">Keywords that increase the bill's grade (JSON format)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="negative_keywords">Negative Keywords (JSON)</label>
                        </th>
                        <td>
                            <textarea id="negative_keywords" name="negative_keywords" rows="10" cols="80" class="large-text code"><?php echo esc_textarea(json_encode($negative_keywords, JSON_PRETTY_PRINT)); ?></textarea>
                            <p class="description">Keywords that decrease the bill's grade (JSON format)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="subject_weights">Subject Weights (JSON)</label>
                        </th>
                        <td>
                            <textarea id="subject_weights" name="subject_weights" rows="10" cols="80" class="large-text code"><?php echo esc_textarea(json_encode($subject_weights, JSON_PRETTY_PRINT)); ?></textarea>
                            <p class="description">Weights for different bill subjects (JSON format)</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="button" id="save-grading-criteria" class="button button-primary">Save Grading Criteria</button>
                </p>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#save-grading-criteria').on('click', function() {
                var data = $('#grading-criteria-form').serialize();
                data += '&action=save_grading_criteria';

                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        alert('Grading criteria saved successfully!');
                    } else {
                        alert('Error saving grading criteria: ' + response.data);
                    }
                });
            });
        });
        </script>
        <?php
    }

    // NEW: Database Tools Page
    public function database_tools_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'legiscan_bill_grades';
        
        // Get database statistics
        $total_grades = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $manual_overrides = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE manual_grade IS NOT NULL");
        $states_with_grades = $wpdb->get_var("SELECT COUNT(DISTINCT state_code) FROM $table_name");
        
        ?>
        <div class="wrap">
            <h1>Database Tools</h1>
            <p>Manage and debug the grading database.</p>

            <div class="database-stats" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ddd; border-radius: 5px;">
                <h2>Database Statistics</h2>
                <ul>
                    <li><strong>Total Graded Bills:</strong> <?php echo number_format($total_grades); ?></li>
                    <li><strong>Manual Overrides:</strong> <?php echo number_format($manual_overrides); ?></li>
                    <li><strong>States with Grades:</strong> <?php echo $states_with_grades; ?></li>
                </ul>
            </div>

            <div class="database-actions" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ddd; border-radius: 5px;">
                <h2>Database Actions</h2>
                
                <h3>Migration Tools</h3>
                <p>
                    <button id="migrate-overrides-db-btn" class="button button-secondary">
                        Migrate Manual Overrides to Database
                    </button>
                    <span class="description">Move existing manual overrides from WordPress options to database.</span>
                </p>
                <div id="migration-status-db" style="margin-top: 10px;"></div>

                <h3>Maintenance Tools</h3>
                <p>
                    <button id="regrade-all-btn" class="button button-secondary" onclick="return confirm('This will re-grade all bills. Continue?');">
                        Re-grade All Bills
                    </button>
                    <span class="description">Re-process all bills and update grades in database.</span>
                </p>

                <p>
                    <button id="cleanup-orphaned-btn" class="button button-secondary">
                        Clean Up Orphaned Records
                    </button>
                    <span class="description">Remove database records for bills that no longer exist as files.</span>
                </p>
                <div id="cleanup-status" style="margin-top: 10px;"></div>
            </div>

            <div class="recent-grades" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ddd; border-radius: 5px;">
                <h2>Recent Grades (Last 20)</h2>
                <?php
                $recent_grades = $wpdb->get_results(
                    "SELECT bill_id, state_code, bill_number, title, grade, manual_grade, processed_date 
                     FROM $table_name 
                     ORDER BY processed_date DESC 
                     LIMIT 20"
                );
                
                if ($recent_grades) {
                    echo '<table class="wp-list-table widefat fixed striped">';
                    echo '<thead><tr><th>Bill ID</th><th>State</th><th>Bill Number</th><th>Title</th><th>Grade</th><th>Manual</th><th>Date</th></tr></thead>';
                    echo '<tbody>';
                    foreach ($recent_grades as $grade) {
                        echo '<tr>';
                        echo '<td>' . esc_html($grade->bill_id) . '</td>';
                        echo '<td>' . esc_html($grade->state_code) . '</td>';
                        echo '<td>' . esc_html($grade->bill_number) . '</td>';
                        echo '<td>' . esc_html(substr($grade->title, 0, 50)) . '...</td>';
                        echo '<td>' . esc_html($grade->grade) . '</td>';
                        echo '<td>' . esc_html($grade->manual_grade ?: '-') . '</td>';
                        echo '<td>' . esc_html($grade->processed_date) . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                } else {
                    echo '<p>No grades found in database.</p>';
                }
                ?>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#migrate-overrides-db-btn').on('click', function() {
                $(this).prop('disabled', true).text('Migrating...');
                $('#migration-status-db').html('<em>Processing migration...</em>');
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'migrate_manual_overrides',
                        nonce: '<?php echo wp_create_nonce('legiscan_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#migration-status-db').html('<span style="color: green;">✅ ' + response.data.message + '</span>');
                        } else {
                            $('#migration-status-db').html('<span style="color: red;">❌ Migration failed: ' + response.data + '</span>');
                        }
                    },
                    error: function() {
                        $('#migration-status-db').html('<span style="color: red;">❌ AJAX error during migration</span>');
                    },
                    complete: function() {
                        $('#migrate-overrides-db-btn').prop('disabled', false).text('Migrate Manual Overrides to Database');
                    }
                });
            });

            $('#cleanup-orphaned-btn').on('click', function() {
                $(this).prop('disabled', true).text('Cleaning...');
                $('#cleanup-status').html('<em>Processing cleanup...</em>');
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'cleanup_orphaned_records',
                        nonce: '<?php echo wp_create_nonce('legiscan_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#cleanup-status').html('<span style="color: green;">✅ ' + response.data.message + '</span>');
                        } else {
                            $('#cleanup-status').html('<span style="color: red;">❌ Cleanup failed: ' + response.data + '</span>');
                        }
                    },
                    error: function() {
                        $('#cleanup-status').html('<span style="color: red;">❌ AJAX error during cleanup</span>');
                    },
                    complete: function() {
                        $('#cleanup-orphaned-btn').prop('disabled', false).text('Clean Up Orphaned Records');
                        // Reload page to update stats
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    }
                });
            });

            $('#regrade-all-btn').on('click', function() {
                // Redirect to batch processing
                window.location.href = '<?php echo admin_url('admin.php?page=legiscan-bill-processing&start_batch=1'); ?>';
            });
        });
        </script>
        <?php
    }

    public function enqueue_scripts() {
        wp_enqueue_script('jquery');

        // Only enqueue if files exist
        $js_file = LEGISCAN_GRADER_PLUGIN_URL . 'assets/js/legiscan-grader.js';
        $css_file = LEGISCAN_GRADER_PLUGIN_URL . 'assets/css/legiscan-grader.css';

        if (file_exists(LEGISCAN_GRADER_PLUGIN_DIR . 'assets/js/legiscan-grader.js')) {
            wp_enqueue_script('legiscan-grader', $js_file, array('jquery'), LEGISCAN_GRADER_VERSION, true);
        }

        if (file_exists(LEGISCAN_GRADER_PLUGIN_DIR . 'assets/css/legiscan-grader.css')) {
            wp_enqueue_style('legiscan-grader', $css_file, array(), LEGISCAN_GRADER_VERSION);
        }

        wp_localize_script('jquery', 'legiscan_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('legiscan_nonce')
        ));
    }

    public function enqueue_admin_scripts() {
        wp_enqueue_script('jquery');

        // Only enqueue if files exist
        if (file_exists(LEGISCAN_GRADER_PLUGIN_DIR . 'assets/admin-script.js')) {
            wp_enqueue_script('legiscan-admin', LEGISCAN_GRADER_PLUGIN_URL . 'assets/admin-script.js', array('jquery'), LEGISCAN_GRADER_VERSION, true);
        }

        if (file_exists(LEGISCAN_GRADER_PLUGIN_DIR . 'assets/admin-style.css')) {
            wp_enqueue_style('legiscan-admin', LEGISCAN_GRADER_PLUGIN_URL . 'assets/admin-style.css', array(), LEGISCAN_GRADER_VERSION);
        }
    }

    // Shortcode handlers
    public function render_scorecard_map($atts) {
        if (function_exists('render_scorecard_map')) {
            return render_scorecard_map($atts);
        }
        return '<p>Scorecard map functionality not available.</p>';
    }

    public function render_bills_table($atts) {
        if (!$this->bill_manager) {
            return '<p>Bill manager not available.</p>';
        }

        $bills = $this->bill_manager->get_all_bills();

        ob_start();
        ?>
        <div class="legiscan-bills-table">
            <table class="wp-list-table widefat">
                <thead>
                    <tr>
                        <th>State</th>
                        <th>Bill Number</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($bills, 0, 50) as $bill_data): 
                        $bill = isset($bill_data['bill']) ? $bill_data['bill'] : $bill_data;
                        $bill_id = $bill['bill_id'] ?? '';
                        
                        // UPDATED: Get grade from database
                        $stored_grade = '';
                        if ($bill_id && $this->grading_engine) {
                            $grade_record = $this->grading_engine->get_stored_grade($bill_id);
                            $stored_grade = $grade_record ? ($grade_record['manual_grade'] ?: $grade_record['grade']) : '';
                        }
                    ?>
                    <tr>
                        <td><?php echo esc_html($bill['state_code'] ?? 'N/A'); ?></td>
                        <td><?php echo esc_html($bill['bill_number'] ?? 'N/A'); ?></td>
                        <td><?php echo esc_html(substr($bill['title'] ?? '', 0, 100)); ?>...</td>
                        <td><?php echo esc_html($bill['status'] ?? 'N/A'); ?></td>
                        <td><?php echo esc_html($stored_grade ?: 'Not Graded'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_state_summary($atts) {
        $atts = shortcode_atts(array(
            'state' => ''
        ), $atts);

        if (empty($atts['state']) || !$this->bill_manager) {
            return '<p>State not specified or bill manager not available.</p>';
        }

        $state_code = strtoupper($atts['state']);
        $bills = $this->bill_manager->get_bills_by_state($state_code);

        if (empty($bills)) {
            return '<p>No bills found for state: ' . esc_html($state_code) . '</p>';
        }

        // UPDATED: Get grade statistics from database
        $grade_stats = null;
        if ($this->grading_engine) {
            $grade_stats = $this->grading_engine->get_grade_statistics($state_code);
        }

        ob_start();
        ?>
        <div class="legiscan-state-summary">
            <h3>State Summary: <?php echo esc_html($state_code); ?></h3>
            <p><strong>Total Bills:</strong> <?php echo esc_html(count($bills)); ?></p>
            <?php if ($grade_stats && $grade_stats['total_bills'] > 0): ?>
                <p><strong>Graded Bills:</strong> <?php echo esc_html($grade_stats['total_bills']); ?></p>
                <p><strong>Average Grade:</strong> <?php echo esc_html($grade_stats['average_grade']); ?></p>
                <p><strong>Average Score:</strong> <?php echo esc_html($grade_stats['average_score']); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    // Utility methods for grade calculations
    private function calculate_numeric_score_from_grade($grade) {
        switch (strtoupper($grade)) {
            case 'A': return 90;
            case 'B': return 80;
            case 'C': return 70;
            case 'D': return 60;
            case 'F': return 50;
            default: return 0;
        }
    }

    private function convert_numeric_score_to_grade($score) {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'F';
    }

    // Database table creation
    private function create_tables() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'legiscan_bill_grades';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            bill_id varchar(50) NOT NULL,
            state_code varchar(2) NOT NULL,
            bill_number varchar(50) NOT NULL,
            title text,
            score decimal(5,2),
            grade varchar(1),
            manual_grade varchar(1) DEFAULT NULL,
            manual_override_date datetime DEFAULT NULL,
            manual_override_user int DEFAULT NULL,
            grading_details longtext,
            processed_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY bill_id (bill_id),
            KEY state_code (state_code),
            KEY grade (grade),
            KEY manual_grade (manual_grade)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    // NEW: Cleanup orphaned records AJAX handler
    public function ajax_cleanup_orphaned_records() {
        check_ajax_referer('legiscan_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        if (!$this->bill_manager) {
            wp_send_json_error('Bill manager not initialized');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'legiscan_bill_grades';
        
        // Get all bill IDs from database
        $db_bill_ids = $wpdb->get_col("SELECT bill_id FROM $table_name");
        
        $orphaned_count = 0;
        $orphaned_ids = [];

        foreach ($db_bill_ids as $bill_id) {
            // Check if bill file still exists
            $bill_exists = $this->bill_manager->bill_exists($bill_id);
            
            if (!$bill_exists) {
                $orphaned_ids[] = $bill_id;
                $orphaned_count++;
            }
        }

        if ($orphaned_count > 0) {
            // Delete orphaned records
            $placeholders = implode(',', array_fill(0, count($orphaned_ids), '%s'));
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM $table_name WHERE bill_id IN ($placeholders)",
                ...$orphaned_ids
            ));

            wp_send_json_success([
                'message' => "Cleaned up {$deleted} orphaned records from database."
            ]);
        } else {
            wp_send_json_success([
                'message' => 'No orphaned records found.'
            ]);
        }
    }

    // Add the cleanup handler to constructor (this should be added to the constructor in Part 1)
    // add_action('wp_ajax_cleanup_orphaned_records', array($this, 'ajax_cleanup_orphaned_records'));
}

// Initialize the plugin
function legiscan_grader_init() {
    global $legiscan_grader;
    $legiscan_grader = new LegiScanGrader();
}
add_action('plugins_loaded', 'legiscan_grader_init');

// Activation hook (OUTSIDE the class)
register_activation_hook(__FILE__, 'legiscan_grader_activate');
function legiscan_grader_activate() {
    global $legiscan_grader;
    if (!$legiscan_grader) {
        $legiscan_grader = new LegiScanGrader();
    }
    flush_rewrite_rules();
}

// Deactivation hook (OUTSIDE the class)
register_deactivation_hook(__FILE__, 'legiscan_grader_deactivate');
function legiscan_grader_deactivate() {
    flush_rewrite_rules();
}

// Uninstall hook
register_uninstall_hook(__FILE__, 'legiscan_grader_uninstall');
function legiscan_grader_uninstall() {
    // Remove database tables and options on uninstall
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'legiscan_bill_grades';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
    
    // Remove plugin options
    delete_option('legiscan_api_key');
    delete_option('census_api_key');
    delete_option('grading_criteria');
    delete_option('auto_process_bills');
    delete_option('grading_criteria_positive_keywords');
    delete_option('grading_criteria_negative_keywords');
    delete_option('grading_criteria_subject_weights');
    
    // Remove state grades and details
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'legiscan_state_grade_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'legiscan_state_details_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'legiscan_manual_grade_%'");
}

// Helper function to get plugin instance
function legiscan_grader() {
    global $legiscan_grader;
    return $legiscan_grader;
}


