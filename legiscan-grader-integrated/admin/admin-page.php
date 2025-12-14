<?php
/**
 * Admin page for LegiScan Grader
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>LegiScan Grader Dashboard</h1>

    <div class="legiscan-admin-container">
        <div class="legiscan-admin-tabs">
            <button class="legiscan-tab-button active" data-tab="overview">Overview</button>
            <button class="legiscan-tab-button" data-tab="upload">Upload Dataset</button>
            <button class="legiscan-tab-button" data-tab="filter">Filter Bills</button>
            <button class="legiscan-tab-button" data-tab="api">API Usage</button>
            <button class="legiscan-tab-button" data-tab="settings">Settings</button> <!-- New Settings Tab -->
        </div>

        <!-- Overview Tab -->
        <div id="overview" class="legiscan-tab-content active">
            <h2>Plugin Overview</h2>
            <div class="legiscan-stats-grid">
                <div class="legiscan-stat-card">
                    <h3>Total Bills</h3>
                    <p class="legiscan-stat-number"><?php 
                        global $wpdb;
                        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}legiscan_bills");
                        echo $count ? $count : 0;
                    ?></p>
                </div>
                <div class="legiscan-stat-card">
                    <h3>API Calls This Month</h3>
                    <p class="legiscan-stat-number"><?php echo get_option('legiscan_api_calls_count', 0); ?> / 30,000</p>
                </div>
                <div class="legiscan-stat-card">
                    <h3>Map/Table View</h3>
                    <p class="legiscan-stat-status"><?php echo get_option('legiscan_map_enabled', '1') == '1' ? 'Enabled' : 'Disabled'; ?></p>
                </div>
            </div>

            <h3>Quick Actions</h3>
            <p><strong>Display Map/Table:</strong> Use the shortcode <code>[state_score_map]</code> on any page or post.</p>
            <p><strong>Settings:</strong> <a href="#settings" class="legiscan-tab-button" data-tab="settings">Configure API keys and plugin settings</a></p>

            <h3>Map/Table View Status</h3>
            <?php if (get_option('legiscan_map_enabled', '1') == '1'): ?>
                <div class="notice notice-success inline">
                    <p><strong>Map/Table View is enabled!</strong> You can use the shortcode <code>[state_score_map]</code> to display it.</p>
                </div>
            <?php else: ?>
                <div class="notice notice-warning inline">
                    <p><strong>Map/Table View is disabled.</strong> Enable it in <a href="#settings" class="legiscan-tab-button" data-tab="settings">Settings</a> to use the shortcode.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Upload Dataset Tab -->
        <div id="upload" class="legiscan-tab-content">
            <h2>Upload Dataset</h2>
            <form id="datasetUploadForm" enctype="multipart/form-data">
                <table class="form-table">
                    <tr>
                        <th scope="row">Dataset File</th>
                        <td>
                            <input type="file" id="datasetFile" name="dataset_file" accept=".zip,.json" />
                            <p class="description">Upload a ZIP file containing JSON bills or individual JSON files.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Replace Existing</th>
                        <td>
                            <label>
                                <input type="checkbox" id="replaceExisting" name="replace_existing" />
                                Replace existing bills with uploaded data
                            </label>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary">Upload Dataset</button>
                </p>
            </form>
            <div id="uploadProgress" style="display: none;">
                <p>Uploading and processing dataset...</p>
                <div class="legiscan-progress-bar">
                    <div class="legiscan-progress-fill"></div>
                </div>
            </div>
        </div>

        <!-- Filter Bills Tab -->
        <div id="filter" class="legiscan-tab-content">
            <h2>Filter Bills</h2>
            <form id="billFilterForm">
                <table class="form-table">
                    <tr>
                        <th scope="row">Filter Keywords</th>
                        <td>
                            <textarea id="filterKeywords" name="filter_keywords" rows="3" class="large-text"><?php echo esc_textarea(get_option('filter_keywords', 'criminal justice, sentencing, prison, incarceration, bail, parole, probation, drug offense, mandatory minimum')); ?></textarea>
                            <p class="description">Enter keywords separated by commas. Bills containing these keywords will be kept.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Filter Action</th>
                        <td>
                            <label>
                                <input type="radio" name="filter_action" value="keep" checked />
                                Keep bills matching keywords
                            </label><br />
                            <label>
                                <input type="radio" name="filter_action" value="remove" />
                                Remove bills matching keywords
                            </label>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary">Apply Filter</button>
                </p>
            </form>
            <div id="filterProgress" style="display: none;">
                <p>Filtering bills...</p>
                <div class="legiscan-progress-bar">
                    <div class="legiscan-progress-fill"></div>
                </div>
            </div>
        </div>

        <!-- API Usage Tab -->
        <div id="api" class="legiscan-tab-content">
            <h2>API Usage Tracking</h2>
            <div class="legiscan-api-usage">
                <h3>LegiScan API</h3>
                <div class="legiscan-usage-meter">
                    <div class="legiscan-usage-bar">
                        <?php 
                        $api_calls = get_option('legiscan_api_calls_count', 0);
                        $percentage = min(($api_calls / 30000) * 100, 100);
                        ?>
                        <div class="legiscan-usage-fill" style="width: <?php echo $percentage; ?>%;"></div>
                    </div>
                    <p><?php echo $api_calls; ?> / 30,000 calls used this month (<?php echo round($percentage, 1); ?>%)</p>
                </div>

                <h3>Reset Information</h3>
                <p><strong>Current Period:</strong> <?php echo date('F Y'); ?></p>
                <p><strong>Next Reset:</strong> <?php echo date('F 1, Y', strtotime('first day of next month')); ?></p>

                <h3>Manual Reset</h3>
                <p class="description">Only use this if you need to manually reset the API counter.</p>
                <button type="button" id="resetApiCounter" class="button button-secondary">Reset API Counter</button>
            </div>
        </div>

        <!-- Settings Tab (New) -->
        <div id="settings" class="legiscan-tab-content">
            <h2>Grading Criteria Settings</h2>
            <form method="post" id="gradingCriteriaForm">
                <?php wp_nonce_field('save_grading_criteria', 'grading_criteria_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">Positive Keywords (JSON)</th>
                        <td>
                            <textarea name="positive_keywords" rows="10" class="large-text code"><?php
                                echo esc_textarea(json_encode(get_option('grading_criteria_positive_keywords', []), JSON_PRETTY_PRINT));
                            ?></textarea>
                            <p class="description">Enter positive keywords with their weights as JSON. Example:<br>
                            <code>{ "reform": 15, "rehabilitation": 15 }</code></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Negative Keywords (JSON)</th>
                        <td>
                            <textarea name="negative_keywords" rows="10" class="large-text code"><?php
                                echo esc_textarea(json_encode(get_option('grading_criteria_negative_keywords', []), JSON_PRETTY_PRINT));
                            ?></textarea>
                            <p class="description">Enter negative keywords with their weights as JSON. Example:<br>
                            <code>{ "mandatory minimum": -20, "death penalty": -25 }</code></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Subject Weights (JSON)</th>
                        <td>
                            <textarea name="subject_weights" rows="10" class="large-text code"><?php
                                echo esc_textarea(json_encode(get_option('grading_criteria_subject_weights', []), JSON_PRETTY_PRINT));
                            ?></textarea>
                            <p class="description">Enter subject weights as JSON. Example:<br>
                            <code>{ "sentencing": 8, "rehabilitation": 12 }</code></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">Save Grading Criteria</button>
                </p>
            </form>
        </div>
    </div>

    <style>
    .legiscan-admin-container {
        max-width: 1200px;
        margin: 20px 0;
    }

    .legiscan-admin-tabs {
        display: flex;
        border-bottom: 2px solid #ddd;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .legiscan-tab-button {
        background: #f1f1f1;
        border: none;
        padding: 12px 24px;
        cursor: pointer;
        border-radius: 4px 4px 0 0;
        margin-right: 4px;
        font-weight: 600;
        transition: background 0.2s;
        margin-bottom: 4px;
    }

    .legiscan-tab-button.active {
        background: #0073aa;
        color: white;
    }

    .legiscan-tab-button:hover:not(.active) {
        background: #e1e1e1;
    }

    .legiscan-tab-content {
        display: none;
        padding: 20px 0;
    }

    .legiscan-tab-content.active {
        display: block;
    }

    .legiscan-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin: 20px 0;
    }

    .legiscan-stat-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        text-align: center;
    }

    .legiscan-stat-card h3 {
        margin: 0 0 10px 0;
        color: #666;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .legiscan-stat-number {
        font-size: 32px;
        font-weight: bold;
        color: #0073aa;
        margin: 0;
    }

    .legiscan-stat-status {
        font-size: 18px;
        font-weight: bold;
        margin: 0;
    }

    .legiscan-progress-bar {
        width: 100%;
        height: 20px;
        background: #f1f1f1;
        border-radius: 10px;
        overflow: hidden;
        margin: 10px 0;
    }

    .legiscan-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #0073aa, #005a87);
        width: 0%;
        transition: width 0.3s ease;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }

    .legiscan-usage-meter {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin: 20px 0;
    }

    .legiscan-usage-bar {
        width: 100%;
        height: 30px;
        background: #f1f1f1;
        border-radius: 15px;
        overflow: hidden;
        margin: 10px 0;
    }

    .legiscan-usage-fill {
        height: 100%;
        background: linear-gradient(90deg, #28a745, #20c997);
        transition: width 0.3s ease;
    }

    .legiscan-usage-fill[style*="width: 7"], 
    .legiscan-usage-fill[style*="width: 8"], 
    .legiscan-usage-fill[style*="width: 9"] {
        background: linear-gradient(90deg, #ffc107, #fd7e14);
    }

    .legiscan-usage-fill[style*="width: 100%"] {
        background: linear-gradient(90deg, #dc3545, #c82333);
    }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab switching
        document.querySelectorAll('.legiscan-tab-button').forEach(button => {
            button.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');

                // Remove active class from all buttons and content
                document.querySelectorAll('.legiscan-tab-button').forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('.legiscan-tab-content').forEach(content => content.classList.remove('active'));

                // Add active class to clicked button and corresponding content
                this.classList.add('active');
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Dataset upload form
        document.getElementById('datasetUploadForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const fileInput = document.getElementById('datasetFile');
            const progressDiv = document.getElementById('uploadProgress');

            if (!fileInput.files[0]) {
                alert('Please select a file to upload.');
                return;
            }

            // Show progress
            progressDiv.style.display = 'block';

            // Simulate upload progress (replace with actual AJAX call)
            let progress = 0;
            const progressFill = progressDiv.querySelector('.legiscan-progress-fill');

            const interval = setInterval(() => {
                progress += Math.random() * 15;
                if (progress >= 100) {
                    progress = 100;
                    clearInterval(interval);
                    setTimeout(() => {
                        progressDiv.style.display = 'none';
                        alert('Dataset uploaded successfully!');
                    }, 500);
                }
                progressFill.style.width = progress + '%';
            }, 200);
        });

        // Bill filter form
        document.getElementById('billFilterForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const progressDiv = document.getElementById('filterProgress');
            const keywords = document.getElementById('filterKeywords').value;

            if (!keywords.trim()) {
                alert('Please enter filter keywords.');
                return;
            }

            // Show progress
            progressDiv.style.display = 'block';

            // Simulate filtering progress
            let progress = 0;
            const progressFill = progressDiv.querySelector('.legiscan-progress-fill');

            const interval = setInterval(() => {
                progress += Math.random() * 20;
                if (progress >= 100) {
                    progress = 100;
                    clearInterval(interval);
                    setTimeout(() => {
                        progressDiv.style.display = 'none';
                        alert('Bills filtered successfully!');
                    }, 500);
                }
                progressFill.style.width = progress + '%';
            }, 150);
        });

        // Reset API counter
        document.getElementById('resetApiCounter').addEventListener('click', function() {
            if (confirm('Are you sure you want to reset the API counter? This action cannot be undone.')) {
                // Make AJAX call to reset counter
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=reset_api_counter&nonce=' + '<?php echo wp_create_nonce("reset_api_counter"); ?>'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('API counter reset successfully!');
                        location.reload();
                    } else {
                        alert('Error resetting API counter.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error resetting API counter.');
                });
            }
        });

        // Grading Criteria form submission
        document.getElementById('gradingCriteriaForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const form = this;
            const formData = new FormData(form);

            fetch(ajaxurl, {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Grading criteria saved successfully!');
                } else {
                    alert('Error saving grading criteria: ' + (data.data || 'Unknown error'));
                }
            })
            .catch(error => {
                alert('Error saving grading criteria.');
                console.error(error);
            });
        });
    });
    </script>
</div>