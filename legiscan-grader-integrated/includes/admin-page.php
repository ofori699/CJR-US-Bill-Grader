<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Add admin menu
add_action('admin_menu', function() {
    add_menu_page(
        'LegiScan Grader',
        'LegiScan Grader',
        'manage_options',
        'legiscan-grader-admin',
        'legiscan_grader_admin_page',
        'dashicons-analytics',
        20
    );

    // Add submenu pages
    add_submenu_page(
        'legiscan-grader-admin',
        'Settings',
        'Settings',
        'manage_options',
        'legiscan-grader-admin',
        'legiscan_grader_admin_page'
    );

    add_submenu_page(
        'legiscan-grader-admin',
        'Grade Bills',
        'Grade Bills',
        'manage_options',
        'legiscan-grader-grade',
        'legiscan_grader_grade_page'
    );

    add_submenu_page(
        'legiscan-grader-admin',
        'View Results',
        'View Results',
        'manage_options',
        'legiscan-grader-results',
        'legiscan_grader_results_page'
    );

    add_submenu_page(
        'legiscan-grader-admin',
        'Manual Grades',
        'Manual Grades',
        'manage_options',
        'legiscan-grader-manual-grades', // Changed slug
        'legiscan_grader_manual_page'
    );

    add_submenu_page(
        'legiscan-grader-admin',
        'Grading Weights',
        'Grading Weights',
        'manage_options',
        'legiscan-grader-weights-settings', // Changed slug
        'legiscan_grader_weights_page'
    );
});

// Main admin page content
function legiscan_grader_admin_page() {
    // Handle API key save
    if (isset($_POST['save_api_keys'])) {
        update_option('legiscan_api_key', sanitize_text_field($_POST['legiscan_api_key']));
        update_option('census_api_key', sanitize_text_field($_POST['census_api_key']));
        echo '<div class="notice notice-success"><p>API keys saved!</p></div>';
    }

    // Handle dataset upload
    if (isset($_POST['upload_dataset'])) {
        $upload_result = legiscan_handle_dataset_upload();
    }

    // Handle filter bills
    if (isset($_POST['filter_bills'])) {
        $filter_result = legiscan_handle_filter_bills();
    }

    // Handle API usage reset
    if (isset($_POST['reset_api_usage'])) {
        legiscan_reset_api_usage();
        echo '<div class="notice notice-success"><p>API usage counter reset for this month.</p></div>';
    }

    // Get current keys and usage
    $legiscan_api_key = esc_attr(get_option('legiscan_api_key', ''));
    $census_api_key = esc_attr(get_option('census_api_key', ''));
    $api_usage = legiscan_get_api_usage();
    $api_limit = 30000;
    $usage_percent = min(100, round(($api_usage / $api_limit) * 100));
    $usage_color = $usage_percent > 90 ? 'red' : ($usage_percent > 75 ? 'orange' : 'green');
    $keywords = esc_attr(get_option('legiscan_filter_keywords', 'crime,criminal,prison,jail,police,court'));

    // Get bill count
    $bill_manager = legiscan_bill_manager();
    $bills = $bill_manager->get_all_bills();
    $bill_count = count($bills);

    ?>
    <div class="wrap">
        <h1>LegiScan Grader Settings</h1>

        <!-- Dashboard Overview -->
        <div class="legiscan-dashboard-overview">
            <div class="legiscan-stat-box">
                <h3>Bills Loaded</h3>
                <p class="legiscan-stat-number"><?php echo $bill_count; ?></p>
            </div>
            <div class="legiscan-stat-box">
                <h3>API Usage</h3>
                <p class="legiscan-stat-number" style="color:<?php echo $usage_color; ?>;"><?php echo $usage_percent; ?>%</p>
            </div>
        </div>

        <h2>API Keys</h2>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th><label for="legiscan_api_key">LegiScan API Key</label></th>
                    <td>
                        <input type="text" name="legiscan_api_key" id="legiscan_api_key" value="<?php echo $legiscan_api_key; ?>" style="width: 400px;">
                        <p class="description">Get your API key from <a href="https://legiscan.com/legiscan" target="_blank">LegiScan</a></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="census_api_key">Census API Key</label></th>
                    <td>
                        <input type="text" name="census_api_key" id="census_api_key" value="<?php echo $census_api_key; ?>" style="width: 400px;">
                        <p class="description">Get your API key from <a href="https://api.census.gov/data/key_signup.html" target="_blank">Census Bureau</a></p>
                    </td>
                </tr>
            </table>
            <input type="submit" name="save_api_keys" class="button button-primary" value="Save API Keys">
        </form>

        <h2>LegiScan API Usage</h2>
        <div style="margin-bottom:10px;">
            <strong>API Calls This Month:</strong>
            <span style="color:<?php echo $usage_color; ?>;font-weight:bold;">
                <?php echo $api_usage . ' / ' . $api_limit; ?> (<?php echo $usage_percent; ?>%)
            </span>
            <?php if ($usage_percent > 90): ?>
                <span style="color:red;">&nbsp;⚠️ Approaching monthly limit!</span>
            <?php endif; ?>
        </div>
        <div class="legiscan-progress-bar">
            <div class="legiscan-progress-fill" style="width: <?php echo $usage_percent; ?>%; background-color: <?php echo $usage_color; ?>;"></div>
        </div>
        <form method="post" style="margin-bottom:30px;">
            <input type="submit" name="reset_api_usage" class="button" value="Reset Counter for This Month" onclick="return confirm('Are you sure you want to reset the API usage counter for this month?');">
        </form>

        <h2>Upload Bill Dataset</h2>
        <form method="post" enctype="multipart/form-data">
            <label for="bills_file">Choose a JSON or ZIP file:</label>
            <input type="file" name="bills_file" id="bills_file" required accept=".json,.zip">
            <input type="submit" name="upload_dataset" class="button button-primary" value="Upload Dataset">
            <p class="description">Upload LegiScan dataset files (JSON or ZIP format)</p>
        </form>
        <?php if (isset($upload_result)) echo $upload_result; ?>

        <h2>Filter Criminal Justice Bills</h2>
        <form method="post">
            <label for="filter_keywords">Filter Keywords (comma-separated):</label>
            <textarea name="filter_keywords" id="filter_keywords" rows="3" style="width: 100%; max-width: 600px;"><?php echo $keywords; ?></textarea>
            <p class="description">Bills containing these keywords will be kept. Others will be deleted.</p>
            <input type="submit" name="filter_bills" class="button button-secondary" value="Filter Bills">
        </form>
        <?php if (isset($filter_result)) echo $filter_result; ?>
    </div>

    <style>
        .legiscan-dashboard-overview {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        .legiscan-stat-box {
            background: #fff;
            border: 1px solid #ccd0d4;
            padding: 20px;
            border-radius: 4px;
            text-align: center;
            min-width: 150px;
        }
        .legiscan-stat-number {
            font-size: 2em;
            font-weight: bold;
            margin: 10px 0;
        }
        .legiscan-progress-bar {
            width: 100%;
            max-width: 400px;
            height: 20px;
            background-color: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        .legiscan-progress-fill {
            height: 100%;
            transition: width 0.3s ease;
        }
    </style>
    <?php
}

// Grade Bills page
function legiscan_grader_grade_page() {
    $bill_manager = legiscan_bill_manager();
    $bills = $bill_manager->get_all_bills();
    $bill_count = count($bills);

    // Get current weights for display
    $weights = get_option('legiscan_grading_weights', [
        'keywords' => 20,
        'status' => 20,
        'sponsors' => 20,
        'committees' => 20,
        'votes' => 20
    ]);

    // Show success notice after grading
    if (isset($_GET['graded']) && $_GET['graded'] == 1) {
        echo '<div class="notice notice-success is-dismissible"><p>All bills have been graded! <a href="' . admin_url('admin.php?page=legiscan-grader-results') . '">View Results</a></p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Grade Bills</h1>

        <div class="legiscan-grade-overview">
            <p>You have <strong><?php echo $bill_count; ?></strong> bills ready for grading.</p>

            <?php if ($bill_count > 0): ?>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('legiscan_grade_all_bills_nonce'); ?>
                    <input type="hidden" name="action" value="legiscan_grade_all_bills" />
                    <input type="submit" class="button button-primary" value="Grade All Bills" onclick="return confirm('This will grade all <?php echo $bill_count; ?> bills. Continue?');">
                </form>

                <h3>Current Grading Criteria</h3>
                <ul>
                    <li><strong>Keywords (<?php echo $weights['keywords']; ?> points):</strong> Presence of criminal justice keywords</li>
                    <li><strong>Status (<?php echo $weights['status']; ?> points):</strong> Bill progress and current status</li>
                    <li><strong>Sponsors (<?php echo $weights['sponsors']; ?> points):</strong> Sponsor information and credibility</li>
                    <li><strong>Committees (<?php echo $weights['committees']; ?> points):</strong> Committee assignments and relevance</li>
                    <li><strong>Votes (<?php echo $weights['votes']; ?> points):</strong> Voting patterns and support</li>
                </ul>
                <p><a href="<?php echo admin_url('admin.php?page=legiscan-grader-weights-settings'); ?>">Adjust grading weights</a></p>
            <?php else: ?>
                <p>No bills found. Please <a href="<?php echo admin_url('admin.php?page=legiscan-grader-admin'); ?>">upload a dataset</a> first.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// Results page
function legiscan_grader_results_page() {
    $results = get_option('legiscan_grading_results', []);

    // Show error notice if export failed due to no results
    if (isset($_GET['export']) && $_GET['export'] == 0) {
        echo '<div class="notice notice-error is-dismissible"><p>No grading results to export.</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Grading Results</h1>

        <?php if (empty($results)): ?>
            <p>No grading results found. <a href="<?php echo admin_url('admin.php?page=legiscan-grader-grade'); ?>">Grade bills first</a>.</p>
        <?php else: ?>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin-bottom: 20px;">
                <?php wp_nonce_field('legiscan_export_results_nonce'); ?>
                <input type="hidden" name="action" value="legiscan_export_results" />
                <input type="submit" class="button button-secondary" value="Export Results as CSV">
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Bill ID</th>
                        <th>Title</th>
                        <th>Score</th>
                        <th>Grade</th>
                        <th>State</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $manual_overrides = get_option('legiscan_manual_grade_overrides', []);
                    foreach ($results as $result): 
                        $bill_id = $result['bill']['bill_id'] ?? 'N/A';
                        $has_override = isset($manual_overrides[$bill_id]);
                        $display_score = $has_override ? $manual_overrides[$bill_id]['manual_score'] : $result['grade']['score'];
                        $display_grade = $has_override ? $manual_overrides[$bill_id]['manual_grade'] : $result['grade']['grade'];
                    ?>
                    <tr>
                        <td><?php echo esc_html($bill_id); ?></td>
                        <td><?php echo esc_html(substr($result['bill']['title'] ?? 'N/A', 0, 50)) . '...'; ?></td>
                        <td>
                            <?php echo esc_html($display_score); ?>
                            <?php if ($has_override): ?>
                                <small style="color: #d63638;">(Manual)</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo esc_html($display_grade); ?></strong>
                            <?php if ($has_override): ?>
                                <small style="color: #d63638;">*</small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($result['bill']['state'] ?? 'N/A'); ?></td>
                        <td><?php echo esc_html($result['bill']['status'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php 
            $override_count = count($manual_overrides);
            if ($override_count > 0): ?>
                <p><small style="color: #d63638;">* <?php echo $override_count; ?> grade(s) have been manually overridden. <a href="<?php echo admin_url('admin.php?page=legiscan-grader-manual-grades'); ?>">Manage manual grades</a></small></p>
            <?php endif; ?>

            <p><a href="<?php echo admin_url('admin.php?page=legiscan-grader-grade'); ?>" class="button">Grade Bills Again</a></p>
        <?php endif; ?>
    </div>
    <?php
}

// Manual Grade Management page
function legiscan_grader_manual_page() {
    // Handle manual grade updates
    if (isset($_POST['update_manual_grades'])) {
        legiscan_handle_manual_grade_updates();
    }

    $results = get_option('legiscan_grading_results', []);
    $states = [];

    // Group results by state
    foreach ($results as $index => $result) {
        $state = $result['bill']['state'] ?? $result['bill']['state_code'] ?? 'Unknown';
        if (!isset($states[$state])) {
            $states[$state] = [];
        }
        $states[$state][] = ['index' => $index, 'result' => $result];
    }

    ?>
    <div class="wrap">
        <h1>Manual Grade Override</h1>
        <p>Use this page to manually override the calculated grades for individual bills. Manual overrides will be displayed in the results with a special indicator.</p>

        <?php if (empty($results)): ?>
            <p>No graded bills found. <a href="<?php echo admin_url('admin.php?page=legiscan-grader-grade'); ?>">Grade bills first</a>.</p>
        <?php else: ?>

            <div class="legiscan-manual-grades-container">
                <form method="post" id="manual-grades-form">
                    <?php wp_nonce_field('legiscan_manual_grades_nonce'); ?>

                    <!-- State Filter -->
                    <div class="legiscan-state-filter">
                        <label for="state-filter"><strong>Filter by State:</strong></label>
                        <select id="state-filter" onchange="filterByState(this.value)">
                            <option value="">All States (<?php echo count($results); ?> bills)</option>
                            <?php foreach ($states as $state => $state_bills): ?>
                                <option value="<?php echo esc_attr($state); ?>"><?php echo esc_html($state); ?> (<?php echo count($state_bills); ?> bills)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Bills Table -->
                    <table class="wp-list-table widefat fixed striped" id="manual-grades-table">
                        <thead>
                            <tr>
                                <th style="width: 8%;">State</th>
                                <th style="width: 12%;">Bill ID</th>
                                <th style="width: 35%;">Title</th>
                                <th style="width: 10%;">Current Score</th>
                                <th style="width: 10%;">Current Grade</th>
                                <th style="width: 12%;">Manual Override</th>
                                <th style="width: 13%;">New Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($states as $state => $state_bills): ?>
                                <?php foreach ($state_bills as $bill_data): ?>
                                    <?php 
                                    $index = $bill_data['index'];
                                    $result = $bill_data['result'];
                                    $bill = $result['bill'];
                                    $grade = $result['grade'];

                                    // Check for existing manual override
                                    $manual_overrides = get_option('legiscan_manual_grade_overrides', []);
                                    $bill_id = $bill['bill_id'] ?? 'unknown';
                                    $has_override = isset($manual_overrides[$bill_id]);
                                    $override_data = $has_override ? $manual_overrides[$bill_id] : null;
                                    ?>
                                    <tr class="bill-row" data-state="<?php echo esc_attr($state); ?>">
                                        <td><?php echo esc_html($state); ?></td>
                                        <td><?php echo esc_html($bill_id); ?></td>
                                        <td title="<?php echo esc_attr($bill['title'] ?? ''); ?>">
                                            <?php echo esc_html(substr($bill['title'] ?? 'N/A', 0, 60)) . (strlen($bill['title'] ?? '') > 60 ? '...' : ''); ?>
                                        </td>
                                        <td><?php echo esc_html($grade['score']); ?></td>
                                        <td>
                                            <strong class="<?php echo $has_override ? 'overridden' : ''; ?>">
                                                <?php echo esc_html($has_override ? $override_data['original_grade'] : $grade['grade']); ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <input type="number" 
                                                   name="manual_scores[<?php echo esc_attr($bill_id); ?>]" 
                                                   min="0" max="100" step="0.1"
                                                   value="<?php echo $has_override ? esc_attr($override_data['manual_score']) : ''; ?>"
                                                   placeholder="0-100"
                                                   onchange="updateGradePreview(this, '<?php echo esc_js($bill_id); ?>')">
                                        </td>
                                        <td>
                                            <span id="grade-preview-<?php echo esc_attr($bill_id); ?>" class="grade-preview">
                                                <?php if ($has_override): ?>
                                                    <strong style="color: #d63638;"><?php echo esc_html($override_data['manual_grade']); ?></strong>
                                                    <small>(Override Active)</small>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p class="submit">
                        <input type="submit" name="update_manual_grades" class="button button-primary" value="Update Manual Grades">
                        <input type="submit" name="clear_all_overrides" class="button button-secondary" value="Clear All Overrides" 
                               onclick="return confirm('This will remove all manual grade overrides and restore original calculated grades. Continue?');">
                    </p>
                </form>
            </div>

        <?php endif; ?>
    </div>

    <style>
        .legiscan-manual-grades-container {
            margin-top: 20px;
        }
        .legiscan-state-filter {
            margin-bottom: 20px;
            padding: 15px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .legiscan-state-filter select {
            margin-left: 10px;
            padding: 5px 10px;
            min-width: 200px;
        }
        .overridden {
            text-decoration: line-through;
            color: #666;
        }
        .grade-preview {
            font-weight: bold;
        }
        .bill-row.hidden {
            display: none;
        }
        #manual-grades-table input[type="number"] {
            width: 80px;
            padding: 4px;
        }
        #manual-grades-table th {
            background: #f1f1f1;
        }
        .submit {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
    </style>

    <script>
        function updateGradePreview(input, billId) {
            const score = parseFloat(input.value);
            const previewSpan = document.getElementById('grade-preview-' + billId);

            if (isNaN(score) || input.value === '') {
                previewSpan.innerHTML = '';
                return;
            }

            let grade = 'F';
            if (score >= 90) grade = 'A';
            else if (score >= 80) grade = 'B';
            else if (score >= 70) grade = 'C';
            else if (score >= 60) grade = 'D';

            previewSpan.innerHTML = '<strong style="color: #d63638;">' + grade + '</strong> <small>(New Override)</small>';
        }

        function filterByState(selectedState) {
            const rows = document.querySelectorAll('.bill-row');
            let visibleCount = 0;

            rows.forEach(row => {
                if (selectedState === '' || row.dataset.state === selectedState) {
                    row.classList.remove('hidden');
                    visibleCount++;
                } else {
                    row.classList.add('hidden');
                }
            });

            // Update table message if no results
            const tbody = document.querySelector('#manual-grades-table tbody');
            const existingMessage = document.querySelector('.no-results-message');
            if (existingMessage) {
                existingMessage.remove();
            }

            if (visibleCount === 0 && selectedState !== '') {
                const messageRow = document.createElement('tr');
                messageRow.className = 'no-results-message';
                messageRow.innerHTML = '<td colspan="7" style="text-align: center; padding: 20px; color: #666;">No bills found for the selected state.</td>';
                tbody.appendChild(messageRow);
            }
        }
    </script>
    <?php
}

// Grading Weights Management page
function legiscan_grader_weights_page() {
    // Handle weight updates
    if (isset($_POST['update_weights'])) {
        legiscan_handle_weight_updates();
    }

    // Get current weights
    $weights = get_option('legiscan_grading_weights', [
        'keywords' => 20,
        'status' => 20,
        'sponsors' => 20,
        'committees' => 20,
        'votes' => 20
    ]);

    $total_weight = array_sum($weights);
    ?>
    <div class="wrap">
        <h1>Grading Weights Configuration</h1>
        <p>Adjust the weight (importance) of each grading factor. The system will automatically normalize these weights to ensure they total 100 points.</p>

        <div class="legiscan-weights-container">
            <form method="post" id="weights-form">
                <?php wp_nonce_field('legiscan_weights_nonce'); ?>

                <table class="form-table">
                    <thead>
                        <tr>
                            <th>Grading Factor</th>
                            <th>Weight</th>
                            <th>Normalized Points</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Keywords</strong></td>
                            <td>
                                <input type="number" name="weights[keywords]" value="<?php echo esc_attr($weights['keywords']); ?>" 
                                       min="0" max="100" step="1" onchange="updateNormalizedWeights()">
                            </td>
                            <td><span id="normalized-keywords"><?php echo round(($weights['keywords'] / $total_weight) * 100, 1); ?></span></td>
                            <td>Presence and relevance of criminal justice keywords in bill text</td>
                        </tr>
                        <tr>
                            <td><strong>Status</strong></td>
                            <td>
                                <input type="number" name="weights[status]" value="<?php echo esc_attr($weights['status']); ?>" 
                                       min="0" max="100" step="1" onchange="updateNormalizedWeights()">
                            </td>
                            <td><span id="normalized-status"><?php echo round(($weights['status'] / $total_weight) * 100, 1); ?></span></td>
                            <td>Bill progress through legislative process and current status</td>
                        </tr>
                        <tr>
                            <td><strong>Sponsors</strong></td>
                            <td>
                                <input type="number" name="weights[sponsors]" value="<?php echo esc_attr($weights['sponsors']); ?>" 
                                       min="0" max="100" step="1" onchange="updateNormalizedWeights()">
                            </td>
                            <td><span id="normalized-sponsors"><?php echo round(($weights['sponsors'] / $total_weight) * 100, 1); ?></span></td>
                            <td>Number and credibility of bill sponsors</td>
                        </tr>
                        <tr>
                            <td><strong>Committees</strong></td>
                            <td>
                                <input type="number" name="weights[committees]" value="<?php echo esc_attr($weights['committees']); ?>" 
                                       min="0" max="100" step="1" onchange="updateNormalizedWeights()">
                            </td>
                            <td><span id="normalized-committees"><?php echo round(($weights['committees'] / $total_weight) * 100, 1); ?></span></td>
                            <td>Committee assignments and relevance to criminal justice</td>
                        </tr>
                        <tr>
                            <td><strong>Votes</strong></td>
                            <td>
                                <input type="number" name="weights[votes]" value="<?php echo esc_attr($weights['votes']); ?>" 
                                       min="0" max="100" step="1" onchange="updateNormalizedWeights()">
                            </td>
                            <td><span id="normalized-votes"><?php echo round(($weights['votes'] / $total_weight) * 100, 1); ?></span></td>
                            <td>Voting patterns, support levels, and vote outcomes</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr style="border-top: 2px solid #ddd; font-weight: bold;">
                            <td>Total</td>
                            <td><span id="total-weight"><?php echo $total_weight; ?></span></td>
                            <td><span id="total-normalized">100.0</span></td>
                            <td>All factors combined</td>
                        </tr>
                    </tfoot>
                </table>

                <div class="legiscan-weights-info">
                    <h3>How Weight Normalization Works</h3>
                    <p>The system automatically normalizes your weights to ensure the total always equals 100 points:</p>
                    <ul>
                        <li>Enter any positive numbers for each factor based on their relative importance</li>
                        <li>The system calculates each factor's percentage of the total</li>
                        <li>Final scores are calculated using these normalized percentages</li>
                        <li>Example: If you set Keywords=30, Status=20, others=10 each, Keywords gets 30/70 = 42.9% of total points</li>
                    </ul>
                </div>

                <p class="submit">
                    <input type="submit" name="update_weights" class="button button-primary" value="Update Grading Weights">
                    <input type="submit" name="reset_weights" class="button button-secondary" value="Reset to Default (Equal Weights)" 
                           onclick="return confirm('This will reset all weights to 20 (equal importance). Continue?');">
                </p>
            </form>
        </div>
    </div>

    <style>
        .legiscan-weights-container {
            margin-top: 20px;
        }
        .form-table {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-table th, .form-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        .form-table th {
            background: #f9f9f9;
            font-weight: bold;
        }
        .form-table input[type="number"] {
            width: 80px;
            padding: 5px;
            text-align: center;
        }
        .legiscan-weights-info {
            margin: 30px 0;
            padding: 20px;
            background: #f0f8ff;
            border: 1px solid #b3d9ff;
            border-radius: 4px;
        }
        .legiscan-weights-info h3 {
            margin-top: 0;
            color: #0073aa;
        }
        .legiscan-weights-info ul {
            margin-bottom: 0;
        }
        #total-weight, #total-normalized {
            font-weight: bold;
            color: #0073aa;
        }
    </style>

    <script>
        function updateNormalizedWeights() {
            const weights = {
                keywords: parseInt(document.querySelector('input[name="weights[keywords]"]').value) || 0,
                status: parseInt(document.querySelector('input[name="weights[status]"]').value) || 0,
                sponsors: parseInt(document.querySelector('input[name="weights[sponsors]"]').value) || 0,
                committees: parseInt(document.querySelector('input[name="weights[committees]"]').value) || 0,
                votes: parseInt(document.querySelector('input[name="weights[votes]"]').value) || 0
            };

            const total = weights.keywords + weights.status + weights.sponsors + weights.committees + weights.votes;
            
            document.getElementById('total-weight').textContent = total;

            if (total > 0) {
                document.getElementById('normalized-keywords').textContent = Math.round((weights.keywords / total) * 1000) / 10;
                document.getElementById('normalized-status').textContent = Math.round((weights.status / total) * 1000) / 10;
                document.getElementById('normalized-sponsors').textContent = Math.round((weights.sponsors / total) * 1000) / 10;
                document.getElementById('normalized-committees').textContent = Math.round((weights.committees / total) * 1000) / 10;
                document.getElementById('normalized-votes').textContent = Math.round((weights.votes / total) * 1000) / 10;
            } else {
                document.getElementById('normalized-keywords').textContent = '0.0';
                document.getElementById('normalized-status').textContent = '0.0';
                document.getElementById('normalized-sponsors').textContent = '0.0';
                document.getElementById('normalized-committees').textContent = '0.0';
                document.getElementById('normalized-votes').textContent = '0.0';
            }
        }
    </script>
    <?php
}

// Handle manual grade updates
function legiscan_handle_manual_grade_updates() {
    if (!wp_verify_nonce($_POST['_wpnonce'], 'legiscan_manual_grades_nonce')) {
        wp_die('Security check failed');
    }

    if (isset($_POST['clear_all_overrides'])) {
        delete_option('legiscan_manual_grade_overrides');
        echo '<div class="notice notice-success"><p>All manual grade overrides cleared! Original calculated grades have been restored.</p></div>';
        return;
    }

    if (!isset($_POST['manual_scores']) || !is_array($_POST['manual_scores'])) {
        echo '<div class="notice notice-warning"><p>No manual scores provided.</p></div>';
        return;
    }

    $manual_overrides = get_option('legiscan_manual_grade_overrides', []);
    $results = get_option('legiscan_grading_results', []);
    $updated_count = 0;
    $removed_count = 0;

    foreach ($_POST['manual_scores'] as $bill_id => $manual_score) {
        $bill_id = sanitize_text_field($bill_id);

        // If score is empty, remove the override
        if (empty($manual_score)) {
            if (isset($manual_overrides[$bill_id])) {
                unset($manual_overrides[$bill_id]);
                $removed_count++;
            }
            continue;
        }

        $manual_score = floatval($manual_score);

        if ($manual_score < 0 || $manual_score > 100) {
            continue; // Skip invalid scores
        }

        // Find the original result
        $original_result = null;
        foreach ($results as $result) {
            if (($result['bill']['bill_id'] ?? '') === $bill_id) {
                $original_result = $result;
                break;
            }
        }

        if (!$original_result) {
            continue;
        }

        // Calculate new grade
        $manual_grade = 'F';
        if ($manual_score >= 90) $manual_grade = 'A';
        else if ($manual_score >= 80) $manual_grade = 'B';
        else if ($manual_score >= 70) $manual_grade = 'C';
        else if ($manual_score >= 60) $manual_grade = 'D';

        // Store the override
        $manual_overrides[$bill_id] = [
            'manual_score' => $manual_score,
            'manual_grade' => $manual_grade,
            'original_score' => $original_result['grade']['score'],
            'original_grade' => $original_result['grade']['grade'],
            'override_date' => current_time('mysql'),
            'override_user' => get_current_user_id()
        ];

        $updated_count++;
    }

    update_option('legiscan_manual_grade_overrides', $manual_overrides);

    $message = '';
    if ($updated_count > 0) {
        $message .= $updated_count . ' manual grade override(s) updated. ';
    }
    if ($removed_count > 0) {
        $message .= $removed_count . ' override(s) removed. ';
    }
    if (empty($message)) {
        $message = 'No changes made.';
    }

    echo '<div class="notice notice-success"><p>' . $message . '</p></div>';
}

// Handle weight updates
function legiscan_handle_weight_updates() {
    if (!wp_verify_nonce($_POST['_wpnonce'], 'legiscan_weights_nonce')) {
        wp_die('Security check failed');
    }

    if (isset($_POST['reset_weights'])) {
        $default_weights = [
            'keywords' => 20,
            'status' => 20,
            'sponsors' => 20,
            'committees' => 20,
            'votes' => 20
        ];
        update_option('legiscan_grading_weights', $default_weights);
        echo '<div class="notice notice-success"><p>Grading weights reset to default (equal weights)!</p></div>';
        return;
    }

    if (!isset($_POST['weights']) || !is_array($_POST['weights'])) {
        echo '<div class="notice notice-error"><p>Invalid weight data provided.</p></div>';
        return;
    }

    $new_weights = [];
    $valid_factors = ['keywords', 'status', 'sponsors', 'committees', 'votes'];

    foreach ($valid_factors as $factor) {
        if (isset($_POST['weights'][$factor])) {
            $weight = intval($_POST['weights'][$factor]);
            if ($weight < 0) $weight = 0;
            if ($weight > 100) $weight = 100;
            $new_weights[$factor] = $weight;
        } else {
            $new_weights[$factor] = 0;
        }
    }

    $total_weight = array_sum($new_weights);
    if ($total_weight == 0) {
        echo '<div class="notice notice-error"><p>Total weight cannot be zero. Please enter at least one non-zero weight.</p></div>';
        return;
    }

    update_option('legiscan_grading_weights', $new_weights);
    echo '<div class="notice notice-success"><p>Grading weights updated successfully! Total weight: ' . $total_weight . '</p></div>';
}

// Dataset upload handler
function legiscan_handle_dataset_upload() {
    if (empty($_FILES['bills_file']['tmp_name'])) {
        return '<div class="notice notice-error"><p>Please select a file to upload.</p></div>';
    }

    $file = $_FILES['bills_file'];
    $upload_dir = wp_upload_dir()['basedir'] . '/legiscan-bills/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($ext === 'json') {
        $target_file = $upload_dir . basename($file['name']);
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            return '<div class="notice notice-success"><p>JSON file uploaded successfully!</p></div>';
        } else {
            return '<div class="notice notice-error"><p>Failed to upload JSON file.</p></div>';
        }
    } elseif ($ext === 'zip') {
        $zip = new ZipArchive;
        if ($zip->open($file['tmp_name']) === TRUE) {
            $zip->extractTo($upload_dir);
            $zip->close();
            return '<div class="notice notice-success"><p>ZIP file extracted successfully!</p></div>';
        } else {
            return '<div class="notice notice-error"><p>Failed to extract ZIP file.</p></div>';
        }
    } else {
        return '<div class="notice notice-error"><p>Only JSON or ZIP files are allowed.</p></div>';
    }
}

// Bill filter handler
function legiscan_handle_filter_bills() {
    $keywords_str = isset($_POST['filter_keywords']) ? sanitize_text_field($_POST['filter_keywords']) : '';
    update_option('legiscan_filter_keywords', $keywords_str);
    $keywords = array_map('trim', explode(',', $keywords_str));

    $upload_dir = wp_upload_dir()['basedir'] . '/legiscan-bills/';
    $files_deleted = 0;
    $files_kept = 0;

    foreach (glob($upload_dir . '*.json') as $file) {
        $content = file_get_contents($file);
        $found = false;
        foreach ($keywords as $kw) {
            if (stripos($content, trim($kw)) !== false) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            unlink($file);
            $files_deleted++;
        } else {
            $files_kept++;
        }
    }

    return '<div class="notice notice-success"><p>Filter complete! ' . $files_kept . ' files kept, ' . $files_deleted . ' files deleted.</p></div>';
}