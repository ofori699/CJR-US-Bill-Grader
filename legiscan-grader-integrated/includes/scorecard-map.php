<?php
/**
 * State Scorecard Map & Table functionality (Complete Version)
 * Includes all missing methods and enhanced functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('LegiScan_Scorecard_Map')) {
    class LegiScan_Scorecard_Map {

        private $grading_engine;

        public function __construct() {
            // Register REST API endpoints when class is instantiated
            add_action('rest_api_init', array($this, 'register_state_scorecard_api'));

            // Initialize grading engine for data access
            if (class_exists('LegiScan_Grading_Engine')) {
                $this->grading_engine = new LegiScan_Grading_Engine();
            }
        }

        public function render_map($atts = []) {
            // Check if map is enabled in settings
            $enabled = get_option('legiscan_map_enabled', '1');
            if ($enabled != '1') {
                return '<div class="notice notice-info"><p><strong>Map/Table View is disabled.</strong> Enable it in <a href="' . admin_url('admin.php?page=legiscan-grader-settings') . '">LegiScan Grader Settings</a> to display the interactive map.</p></div>';
            }

            ob_start();
            ?>
            <div class="scorecard-container">
                <!-- Toggle Label -->
                <div class="toggle-label" style="text-align:center; font-size:1.1em; font-weight:700; color:#940036; margin-bottom:6px;">
                    Map/Table View
                </div>
                <!-- Toggle Switch -->
                <div class="scorecard-toggle-switch">
                    <input type="checkbox" id="toggleSwitch" class="toggle-switch-input" />
                    <label for="toggleSwitch" class="toggle-switch-label">
                        <span class="toggle-switch-inner"></span>
                        <span class="toggle-switch-switch"></span>
                    </label>
                </div>

                <!-- Map & Controls Flex Layout -->
                <div id="mapViewFlex" class="map-flex-container" style="visibility: visible; position: relative; height: auto; display:flex; gap:24px; align-items:flex-start;">
                    <button id="mobileControlsToggle" style="display:none;">
                      Show Filters & Legend
                    </button>
                    <!-- Map Controls: Only visible in map view -->
                    <div id="mapControls" style="display:block; min-width:180px;">
                        <div class="scorecard-controls-title" style="font-size:1.15em; font-weight:800; color:#940036; margin-bottom:12px;">
                            Sort and Filter States
                        </div>
                        <div class="grade-filter-pills" id="gradeFilterPills" style="flex-direction:column; gap:10px;">
                            <button data-grade="" class="pill active">All</button>
                            <button data-grade="A" class="pill" style="background:#940036; color:#fff;">A</button>
                            <button data-grade="B" class="pill" style="background:#c94a6a; color:#fff;">B</button>
                            <button data-grade="C" class="pill" style="background:#e98ca7; color:#fff;">C</button>
                            <button data-grade="D" class="pill" style="background:#f3c1d7; color:#940036;">D</button>
                            <button data-grade="F" class="pill" style="background:#f8e6ee; color:#940036;">F</button>
                        </div>

                        <!-- Impact Dropdown -->
                        <div class="impact-dropdown-container" id="impactDropdownContainer" style="margin-top:18px;">
                            <label for="impactDropdown" class="impact-dropdown-label">Color by:</label>
                            <select id="impactDropdown" class="impact-dropdown">
                                <option value="grade">Overall Grade</option>
                                <option value="racial">Racial Impact</option>
                                <option value="income">Income Impact</option>
                                <option value="stateImpact">State Impact</option>
                            </select>
                        </div>

                        <!-- Legend -->
                        <div id="impactLegend" style="display:none; margin-top:16px;">
                            <div style="display:flex; align-items:center;">
                                <span id="legendMin" style="font-size:0.95em; color:#940036; min-width:32px; text-align:right;">0</span>
                                <div style="position:relative; height:18px; width:140px; margin:0 8px;">
                                    <div style="height:100%; width:100%; background:linear-gradient(to right, #f8e6ee, #940036); border-radius:8px;"></div>
                                    <!-- Tick marks -->
                                    <div style="position:absolute; left:0; top:100%; width:100%; height:10px; display:flex; justify-content:space-between;">
                                        <span style="width:1px; height:8px; background:#940036; display:inline-block;"></span>
                                        <span style="width:1px; height:8px; background:#940036; display:inline-block;"></span>
                                        <span style="width:1px; height:8px; background:#940036; display:inline-block;"></span>
                                        <span style="width:1px; height:8px; background:#940036; display:inline-block;"></span>
                                        <span style="width:1px; height:8px; background:#940036; display:inline-block;"></span>
                                    </div>
                                    <!-- Tick labels -->
                                    <div style="position:absolute; left:0; top:100%; width:100%; height:20px; display:flex; justify-content:space-between; margin-top:8px;">
                                        <span style="font-size:0.85em; color:#940036;">0</span>
                                        <span style="font-size:0.85em; color:#940036;">25</span>
                                        <span style="font-size:0.85em; color:#940036;">50</span>
                                        <span style="font-size:0.85em; color:#940036;">75</span>
                                        <span style="font-size:0.85em; color:#940036;">100</span>
                                    </div>
                                </div>
                                <span id="legendMax" style="font-size:0.95em; color:#940036; min-width:32px; text-align:left;">100</span>
                            </div>
                            <div style="font-size:0.9em; color:#666; margin-top:4px;">
                                <span id="legendLabel"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Map Container -->
                    <div style="flex:1; min-width:0;">
                        <div id="scorecardMapContainer" style="width: 100% !important; height: 600px !important; display: block; min-height: 600px;"></div>
                    </div>
                </div>
                                <!-- Table Container -->
                <div id="scorecardTableContainer" style="visibility: hidden; position: absolute; height: 0;">
                    <table id="stateScoreTable" class="state-score-table">
                        <thead>
                            <tr>
                                <th data-sort="rank" class="sortable">Rank <span class="sort-arrow"></span></th>
                                <th data-sort="state" class="sortable">State <span class="sort-arrow"></span></th>
                                <th data-sort="grade" class="sortable">Grade <span class="sort-arrow"></span></th>
                                <th data-sort="bills" class="sortable">Bills Graded <span class="sort-arrow"></span></th>
                                <th data-sort="score" class="sortable">Avg Score <span class="sort-arrow"></span></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <!-- Popup Panel -->
                <div id="scorecardPanel" class="scorecard-panel">
                    <div class="panel-header" id="panelHeader">
                        <div>
                            <span id="panelGradeBadge" class="grade-badge">A</span>
                        </div>
                        <h2 id="panelStateName">State Name</h2>
                        <button id="closePanel" class="close-button">&times;</button>
                    </div>
                    <div class="panel-body">
                        <div id="panelBreakdown" class="impact-table"></div>
                        <div class="panel-section">
                            <h3>What Changed This Year</h3>
                            <div id="panelChanged"><em>Loading recent bills...</em></div>
                        </div>
                        <div class="panel-section">
                            <h3>How To Improve</h3>
                            <div id="panelImprove"><em>Loading suggestions...</em></div>
                        </div>
                        <div class="panel-section">
                            <h3>State Statistics</h3>
                            <div id="panelStats"><em>Loading statistics...</em></div>
                        </div>
                        <a href="#" id="panelLink" class="scorecard-link" target="_blank">View State Page</a>
                    </div>
                </div>

               <?php
            // Inject real state data here
            $states = $this->get_state_score_map_data();
            echo '<script>const stateData = ' . json_encode($states) . ';</script>';
            ?>

                <!-- Libraries -->
                <script src="https://cdn.amcharts.com/lib/5/index.js"></script>
                <script src="https://cdn.amcharts.com/lib/5/map.js"></script>
                <script src="https://cdn.amcharts.com/lib/5/geodata/usaLow.js"></script>
                <script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>
              
            <style>
                .scorecard-container {
                    font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
                    max-width: 1200px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .scorecard-controls-title {
                    font-size: 1.15em;
                    font-weight: 800;
                    color: #940036;
                    margin-bottom: 12px;
                }
                .grade-filter-pills {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                }
                .pill {
                    border: none;
                    border-radius: 20px;
                    padding: 8px 22px;
                    font-weight: 600;
                    font-size: 16px;
                    cursor: pointer;
                    transition: background 0.2s, color 0.2s;
                }

                /* Responsive layout for map and controls */
                 @media (max-width: 900px) {
                  .map-flex-container {
                    flex-direction: column;
                    gap: 0;
                  }
                  #mapControls {
                    min-width: 100% !important;
                    margin-bottom: 18px;
                  }
                  #scorecardMapContainer {
                    min-height: 320px;
                    max-height: 480px;
                    aspect-ratio: 1.4 / 1;
                    padding: 0 !important;
                    margin: 0 !important;
                  }
                  .grade-filter-pills {
                    flex-direction: row !important;
                    flex-wrap: wrap;
                    gap: 8px !important;
                  }
                  .pill {
                    padding: 6px 12px !important;
                    font-size: 14px !important;
                  }
                  .impact-dropdown {
                    width: 100% !important;
                    margin-top: 8px;
                  }
                  .scorecard-panel {
                    width: 100vw;
                    right: -100vw;
                  }
                  .scorecard-panel.open {
                    right: 0;
                  }
                  .panel-header,
                  .panel-body {
                    padding: 16px 8px;
                  }
                  .impact-table th,
                  .impact-table td {
                    font-size: 1em;
                  }
                  .grade-badge {
                    font-size: 2.2em;
                  }
                  .stats-grid {
                    grid-template-columns: 1fr;
                  }
                }
             @media (max-width: 600px) {
              .scorecard-container {
                padding: 0 !important;
                margin: 0 !important;
                background: #fff;
              }
              .map-flex-container,
              #mapViewFlex {
                flex-direction: column;
                gap: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
              }

            #scorecardMapContainer {
                aspect-ratio: 1.6 / 1; /* or 4 / 3, or 3 / 2, experiment for best fit */
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                height: auto !important;
                min-height: 225px !important;
                margin: 0 auto !important;
                background: #fff;
                border-radius: 0;
                box-shadow: none;
                position: relative;
                z-index: 1;
                display: block;
                overflow: visible;
            }

              #impactLegend {
                position: fixed !important;
                bottom: 16px;
                left: 50%;
                transform: translateX(-50%);
                background: rgba(255,255,255,0.97);
                border-radius: 12px;
                box-shadow: 0 2px 12px rgba(148,0,54,0.10);
                padding: 10px 18px;
                z-index: 1001;
                min-width: 220px;
                max-width: 90vw;
                font-size: 1em;
                margin: 0 !important;
                display: none;
              }
              #mapControls {
                min-width: 100% !important;
                margin-bottom: 0;
                order: 2;
                background: #fff;
                border-radius: 0 0 12px 12px;
                box-shadow: 0 2px 12px rgba(148,0,54,0.08);
                padding: 0 0 8px 0;
                position: relative;
                z-index: 2;
                display: none;
              }
              #mobileControlsToggle {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 100vw;
                background: #940036;
                color: #fff;
                font-weight: 700;
                font-size: 1.1em;
                padding: 14px 0 12px 0;
                border: none;
                border-radius: 0 0 12px 12px;
                cursor: pointer;
                z-index: 3;
                position: relative;
                margin: 0;
              }
              .am5-layer text {
                font-size: 22px !important;
              }
              .impact-table th, .impact-table td {
                font-size: 0.95em;
                padding-top: 4px !important;
                padding-bottom: 4px !important;
              }
              .state-score-table th, .state-score-table td {
                padding: 8px 4px !important;
              }
              .panel-header h2 {
                font-size: 1.2em;
                margin-top: 8px !important;
                margin-bottom: 4px !important;
              }
              .grade-badge {
                font-size: 1.5em;
              }
              .panel-body, .panel-section {
                padding: 0 !important;
                margin: 0 !important;
              }
              .panel-section h3 {
                margin-top: 10px !important;
                margin-bottom: 4px !important;
              }
              .panel-section {
                margin-bottom: 8px !important;
              }
            }

                /* If using flexbox for layout, ensure children can shrink */
                    .map-flex-container > div {
                      min-width: 0;
                    }
                  /* Make table horizontally scrollable */
                  #scorecardTableContainer {
                    overflow-x: auto;
                    width: 100%;
                  }
                  .state-score-table {
                    min-width: 600px; /* adjust as needed */
                    width: 100%;
                  }
                  .state-score-table th, 
                  .state-score-table td {
                    font-size: 0.9em !important;
                    padding: 8px 6px !important;
                  }
                  .state-score-table th {
                    position: sticky;
                    top: 0;
                    background: #fff;
                    z-index: 2;
                  }
                }

                /* Default "All" button */
                .pill[data-grade=""] {
                    background: #f3c1d7;
                    color: #940036;
                }

                /* Grade A button */
                .pill[data-grade="A"] {
                    background: #940036;
                    color: #fff;
                }

                /* Grade B button */
                .pill[data-grade="B"] {
                    background: #c94a6a;
                    color: #fff;
                }

                /* Grade C button */
                .pill[data-grade="C"] {
                    background: #e98ca7;
                    color: #fff;
                }

                /* Grade D button */
                .pill[data-grade="D"] {
                    background: #f3c1d7;
                    color: #940036;
                }

                /* Grade F button */
                .pill[data-grade="F"] {
                    background: #f8e6ee;
                    color: #940036;
                }

                .pill.active, .pill:focus {
                    opacity: 0.8;
                    transform: scale(1.05);
                }
                .scorecard-toggle-switch {
                    display: flex;
                    justify-content: center;
                    margin-bottom: 20px;
                }
                .toggle-switch-label {
                    display: flex;
                    align-items: center;
                    cursor: pointer;
                    position: relative;
                    width: 120px;
                    height: 40px;
                    background: #f3c1d7;
                    border-radius: 20px;
                    transition: background 0.3s;
                }
                .toggle-switch-input {
                    display: none;
                }
                .toggle-switch-inner {
                    position: absolute;
                    left: 0; right: 0; top: 0; bottom: 0;
                    border-radius: 20px;
                    transition: background 0.3s;
                }
                .toggle-switch-switch {
                    position: absolute;
                    top: 4px;
                    left: 6px;
                    width: 32px;
                    height: 32px;
                    background: #940036;
                    border-radius: 50%;
                    transition: left 0.3s;
                }
                .toggle-switch-input:checked + .toggle-switch-label .toggle-switch-switch {
                    left: 82px;
                }
                .impact-dropdown-container {
                    display: flex;
                    justify-content: flex-start;
                    align-items: center;
                    margin-bottom: 12px;
                }
                .impact-dropdown-label {
                    font-weight: 600;
                    color: #940036;
                    margin-right: 8px;
                }
                .impact-dropdown {
                    padding: 6px 16px;
                    border-radius: 6px;
                    border: 1px solid #e98ca7;
                    font-size: 1em;
                }
                
                /* Better map container sizing */
                .map-flex-container {
                    display: flex;
                    gap: 24px;
                    align-items: flex-start;
                    width: 100%;
                }
                
               /* Default: Desktop and up */
                #scorecardMapContainer {
                  width: 100%;
                  max-width: 100%;
                  min-width: 0;
                  height: 400px; /* or whatever your original was */
                  margin: 0 auto;
                  background: #fff;
                  border-radius: 0;
                  box-shadow: none;
                  position: relative;
                  z-index: 1;
                  margin-top: 0;
                  margin-bottom: 0;
                  float: none;
                  display: block;
                  overflow: visible;
                }
                
                /* Remove blue underlines from all links in the map container */
                #scorecardMapContainer a,
                .scorecard-container a {
                    text-decoration: none !important;
                }
                #scorecardMapContainer text {
                    fill: #940036 !important; /* Maroon */
                    opacity: 1 !important; /* Make sure they are visible */
                }

                .scorecard-panel {
                    position: fixed;
                    top: 0;
                    right: -420px;
                    width: 400px;
                    height: 100%;
                    background: #f8e6ee;
                    border-left: 8px solid #940036;
                    box-shadow: -2px 0 10px rgba(0,0,0,0.12);
                    transition: right 0.3s;
                    padding: 0;
                    z-index: 9999;
                    overflow-y: auto;
                }
                .scorecard-panel.open {
                    right: 0;
                }
                .panel-header {
                    background: #940036;
                    color: #fff;
                    padding: 32px 20px 20px 20px;
                    border-radius: 0 0 16px 16px;
                    text-align: center;
                    position: relative;
                }
                .panel-header h2 {
                    font-size: 2.1em;
                    margin: 0 0 10px 0;
                    font-weight: 700;
                    letter-spacing: 1px;
                }
                .grade-badge {
                    font-size: 70px;
                    font-weight: bold;
                    border-radius: 50%;
                    padding: 0 24px;
                    color: #fff;
                    background: none;
                    display: block;
                    margin: 0 auto 10px auto;
                    line-height: 1.1;
                }
                .close-button {
                    background: none;
                    border: none;
                    font-size: 32px;
                    cursor: pointer;
                    color: #fff;
                    position: absolute;
                    top: 10px;
                    right: 18px;
                }
                .scorecard-link {
                    background: #940036;
                    color: white !important;
                    padding: 10px 24px;
                    text-decoration: none !important;
                    border-radius: 4px;
                    display: inline-block;
                    margin-top: 18px;
                    font-size: 1.1em;
                    font-weight: 600;
                    transition: background-color 0.3s ease;
                }
                .scorecard-link:hover {
                    background: #7c002c;
                    color: white !important;
                    text-decoration: none !important;
                }
                .panel-body {
                    padding: 24px 20px 20px 20px;
                }
                .panel-section h3 {
                    color: #940036;
                    margin-top: 22px;
                    margin-bottom: 8px;
                    font-size: 1.35em;
                    font-weight: 900;
                    letter-spacing: 0.5px;
                }
                .impact-table {
                    width: 100%;
                    margin: 18px 0 24px 0;
                    border-collapse: separate;
                    border-spacing: 0 8px;
                    text-align: center;
                }
                .impact-table th {
                    font-size: 1.1em;
                    color: #940036;
                    font-weight: 700;
                    background: none;
                    border: none;
                    padding-bottom: 6px;
                }
                .impact-grade {
                    font-size: 2.2em;
                    font-weight: 800;
                    color: #940036;
                    background: #f3c1d7;
                    border-radius: 12px;
                    padding: 10px 0;
                    letter-spacing: 1px;
                }
                #panelBreakdown ul {
                    list-style: none;
                    padding: 0;
                    margin: 0 0 20px 0;
                }
                #panelBreakdown li {
                    padding: 8px 0;
                    border-bottom: 1px solid #eee;
                    font-size: 1.05em;
                }
                #panelBreakdown li:last-child {
                    border-bottom: none;
                }

                .state-score-table {
                    width: 100%;
                    border-collapse: separate;
                    border-spacing: 0 6px;
                    margin-top: 20px;
                    background: #fff;
                }
                .state-score-table th,
                .state-score-table td {
                    padding: 14px 12px;
                    border: none;
                    text-align: left;
                }
                .state-score-table th {
                    background: #fff;
                    color: #940036;
                    font-weight: 800;
                    border-bottom: 2px solid #e98ca7;
                    position: relative;
                    cursor: pointer;
                    user-select: none;
                }
                
                /* Better sort arrow styling */
                .sort-arrow {
                    font-size: 0.9em;
                    margin-left: 4px;
                    color: #c94a6a;
                    vertical-align: middle;
                    font-family: monospace;
                }
                .sort-arrow::before {
                    content: "↕";
                }
                .sort-arrow.asc::before {
                    content: "↑";
                }
                .sort-arrow.desc::before {
                    content: "↓";
                }
                .state-score-table tr {
                    background: #fff;
                    border-radius: 8px;
                    box-shadow: 0 1px 4px rgba(148,0,54,0.04);
                    transition: box-shadow 0.2s;
                }
                .state-score-table tr:hover {
                    box-shadow: 0 2px 8px rgba(148,0,54,0.10);
                }
                .state-score-table .grade-badge {
                    font-size: 1.3em;
                    padding: 4px 16px;
                    border-radius: 20px;
                    font-weight: 700;
                    color: #fff;
                    background: #940036;
                    display: inline-block;
                }
                .state-score-table .grade-badge.B { background: #c94a6a; }
                .state-score-table .grade-badge.C { background: #e98ca7; }
                .state-score-table .grade-badge.D { background: #f3c1d7; color: #940036; }
                .state-score-table .grade-badge.F { background: #f8e6ee; color: #940036; }
                .state-score-table .scorecard-link {
                    margin: 0;
                    padding: 7px 18px;
                    font-size: 1em;
                    display: inline-block;
                    text-align: center;
                    vertical-align: middle;
                }
                .state-score-table td:last-child {
                    text-align: center;
                }
                .bill-item {
                    display: flex;
                    align-items: center;
                    margin-bottom: 12px;
                    padding-bottom: 12px;
                    border-bottom: 1px solid rgba(148,0,54,0.1);
                }
                .bill-item:last-child {
                    border-bottom: none;
                    margin-bottom: 0;
                }
                .bill-icon {
                    width: 32px;
                    height: 32px;
                    background: #940036;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-right: 12px;
                    flex-shrink: 0;
                }
                .bill-icon svg {
                    width: 16px;
                    height: 16px;
                    fill: white;
                }
                .bill-content {
                    flex: 1;
                }
                .bill-title {
                    font-weight: 600;
                    color: #333;
                    margin-bottom: 4px;
                }
                .bill-date {
                    font-size: 0.9em;
                    color: #666;
                }
                .improve-item {
                    display: flex;
                    align-items: flex-start;
                    margin-bottom: 12px;
                }
                .improve-icon {
                    width: 24px;
                    height: 24px;
                    background: #940036;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-right: 12px;
                    flex-shrink: 0;
                }
                .improve-icon svg {
                    width: 14px;
                    height: 14px;
                    fill: white;
                }
                .improve-text {
                    flex: 1;
                    padding-top: 2px;
                }
                
                .stats-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 12px;
                    margin-top: 12px;
                }
                
                .stat-item {
                    background: rgba(148, 0, 54, 0.05);
                    padding: 12px;
                    border-radius: 8px;
                    text-align: center;
                }
                
                .stat-value {
                    font-size: 1.5em;
                    font-weight: 700;
                    color: #940036;
                    display: block;
                }
                
                .stat-label {
                    font-size: 0.9em;
                    color: #666;
                    margin-top: 4px;
                }

                </style>
<script>
let root, chart, polygonSeries, labelSeries;
let currentGradeFilter = "";
let tableSortCol = "rank";
let tableSortDir = "asc";

// --- STRICT COLOR PALETTE ---
function getGradeColor(grade) {
    const shades = {
        A: am5.color(0x940036),
        B: am5.color(0xc94a6a),
        C: am5.color(0xe98ca7),
        D: am5.color(0xf3c1d7),
        F: am5.color(0xf8e6ee)
    };
    return shades[grade] || am5.color(0xf8e6ee);
}

function getGradeTextColor(grade) {
    return (grade === "D" || grade === "F") ? "#940036" : "#fff";
}

function grade_to_number(g) {
    return { A:5, B:4, C:3, D:2, F:1 }[g] || 0;
}

function getFilteredStates() {
    return stateData.filter(s => !currentGradeFilter || s.grade === currentGradeFilter);
}

// --- RANK GROUP COLORING ---
const rankShades = [
    am5.color(0x940036), // 1st–10th (darkest)
    am5.color(0xc94a6a), // 11th–20th
    am5.color(0xe98ca7), // 21st–30th
    am5.color(0xf3c1d7), // 31st–40th
    am5.color(0xf8e6ee)  // 41st–50th (lightest)
];

function getRankGroupIndex(rank) {
    if (rank <= 10) return 0;
    if (rank <= 20) return 1;
    if (rank <= 30) return 2;
    if (rank <= 40) return 3;
    return 4;
}

function calculateRanks(states, impactType) {
    let validStates = states
        .filter(s => !isNaN(parseFloat(s[impactType])))
        .sort((a, b) => parseFloat(b[impactType]) - parseFloat(a[impactType]));
    validStates.forEach((s, i) => s._rank = i + 1);
    states.forEach(s => {
        if (isNaN(parseFloat(s[impactType]))) s._rank = 51;
    });
}

function getRankColor(rank) {
    return rankShades[getRankGroupIndex(rank)];
}

// Better map update function
function updateMap(states) {
    let colorBy = document.getElementById("impactDropdown") ? document.getElementById("impactDropdown").value : "grade";

    // If using a rank-based impact type, calculate ranks first
    if (["racial", "income", "stateImpact"].includes(colorBy)) {
        calculateRanks(states, colorBy);
    }

    polygonSeries.data.setAll(states.map(s => {
        let fill;
        if (colorBy === "grade") {
            fill = getGradeColor(s.grade);
        } else if (["racial", "income", "stateImpact"].includes(colorBy)) {
            fill = getRankColor(s._rank);
        } else {
            fill = getGradeColor(s.grade); // fallback
        }
        return {
            id: "US-" + s.abbr,
            name: s.name,
            abbr: s.abbr,
            grade: s.grade,
            value: 1,
            racial: s.racial,
            income: s.income,
            stateImpact: s.stateImpact,
            link: s.link,
            url: s.link,
            fill: fill,
            changed: s.changed,
            improve: s.improve,
            billCount: s.billCount,
            avgScore: s.avgScore
        };
    }));

    // Update the abbreviation labels after polygons are set
    setTimeout(function() {
        updateStateLabels(states);
    }, 100);

    // Force map to maintain size
    setTimeout(function() {
        if (chart && chart.root) {
            chart.root.resize();
        }
    }, 200);
}

function updateTable(states) {
    let tbody = document.querySelector('#stateScoreTable tbody');
    tbody.innerHTML = '';

    // Sort
    let sorted = [...states];
    if (tableSortCol === 'rank') {
        sorted.sort((a, b) => grade_to_number(b.grade) - grade_to_number(a.grade));
        if (tableSortDir === 'desc') sorted.reverse();
    } else if (tableSortCol === 'state') {
        sorted.sort((a, b) => a.name.localeCompare(b.name));
        if (tableSortDir === 'desc') sorted.reverse();
    } else if (tableSortCol === 'grade') {
        sorted.sort((a, b) => grade_to_number(b.grade) - grade_to_number(a.grade));
        if (tableSortDir === 'desc') sorted.reverse();
    } else if (tableSortCol === 'bills') {
        sorted.sort((a, b) => (b.billCount || 0) - (a.billCount || 0));
        if (tableSortDir === 'desc') sorted.reverse();
    } else if (tableSortCol === 'score') {
        sorted.sort((a, b) => (b.avgScore || 0) - (a.avgScore || 0));
        if (tableSortDir === 'desc') sorted.reverse();
    }

    sorted.forEach((s, i) => {
        let tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${i + 1}</td>
            <td>${s.name}</td>
            <td><span class="grade-badge ${s.grade}">${s.grade || 'N/A'}</span></td>
            <td>${s.billCount || 0}</td>
            <td>${s.avgScore || 'N/A'}</td>
            <td><a href="${s.link}" class="scorecard-link" target="_blank">View State Page</a></td>
        `;
        tbody.appendChild(tr);
    });
}

function updateStateLabels(states) {
    let points = [];
    polygonSeries.mapPolygons.each(function(polygon) {
        let abbr = polygon.dataItem.dataContext.abbr;
        if (!abbr) return;
        let geoCentroid = polygon.geoCentroid();
        if (geoCentroid) {
            points.push({
                geometry: { type: "Point", coordinates: geoCentroid },
                abbr: abbr
            });
        }
    });
    labelSeries.data.setAll(points);
}

// Better table update with improved sorting
function showPanel(d) {
    document.getElementById("panelStateName").textContent = d.name;
    document.getElementById("panelGradeBadge").textContent = d.grade || "N/A";
    document.getElementById("panelGradeBadge").style.color = getGradeTextColor(d.grade);
    document.getElementById("panelHeader").style.background = getGradeColor(d.grade).toCSSHex();
    document.getElementById("panelLink").href = d.link || "#";
    document.getElementById("panelBreakdown").innerHTML = `
        <table class="impact-table">
            <tr>
                <th>Racial Impact</th>
                <th>Income Impact</th>
                <th>State Impact</th>
            </tr>
            <tr>
                <td class="impact-grade">${d.racial ?? "N/A"}</td>
                <td class="impact-grade">${d.income ?? "N/A"}</td>
                <td class="impact-grade">${d.stateImpact ?? "N/A"}</td>
            </tr>
        </table>
    `;

    // State Statistics
    document.getElementById("panelStats").innerHTML = `
        <div class="stats-grid">
            <div class="stat-item">
                <span class="stat-value">${d.billCount || 0}</span>
                <div class="stat-label">Bills Graded</div>
            </div>
            <div class="stat-item">
                <span class="stat-value">${d.avgScore || 'N/A'}</span>
                <div class="stat-label">Average Score</div>
            </div>
            <div class="stat-item">
                <span class="stat-value">${d.grade || 'N/A'}</span>
                <div class="stat-label">Overall Grade</div>
            </div>
            <div class="stat-item">
                <span class="stat-value">${d.region || 'N/A'}</span>
                <div class="stat-label">Region</div>
            </div>
        </div>
    `;

    // What Changed This Year
    document.getElementById("panelChanged").innerHTML = "<em>Loading recent bills...</em>";
    fetch(`/wp-json/legiscan-grader/v1/passed_bills?state=${d.abbr}`)
        .then(response => response.json())
        .then(data => {
            if (data.bills && data.bills.length > 0) {
                let html = '';
                data.bills.forEach(bill => {
                    html += `
                        <div class="bill-item">
                            <div class="bill-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 2l5 5h-5V4zM6 20V4h5v7h7v9H6z"/></svg>
                            </div>
                            <div class="bill-content">
                                <div class="bill-title">${bill.title}</div>
                                <div class="bill-date">Passed: ${new Date(bill.date).toLocaleDateString()}</div>
                            </div>
                        </div>
                    `;
                });
                document.getElementById("panelChanged").innerHTML = html;
            } else {
                if (d.changed && d.changed.trim()) {
                    document.getElementById("panelChanged").innerHTML = d.changed;
                } else {
                    document.getElementById("panelChanged").innerHTML = "<em>No recent legislation found</em>";
                }
            }
        })
        .catch(error => {
            if (d.changed && d.changed.trim()) {
                document.getElementById("panelChanged").innerHTML = d.changed;
            } else {
                document.getElementById("panelChanged").innerHTML = "<em>No recent legislation found</em>";
            }
        });

    // How To Improve
    document.getElementById("panelImprove").innerHTML = "<em>Loading suggestions...</em>";
    fetch(`/wp-json/legiscan-grader/v1/ai_improve?state=${d.abbr}&grade=${d.grade}&racial=${d.racial}&income=${d.income}&stateImpact=${d.stateImpact}`)
        .then(response => response.json())
        .then(data => {
            if (data.suggestions && data.suggestions.length > 0) {
                let html = '';
                data.suggestions.forEach(suggestion => {
                    html += `
                        <div class="improve-item">
                            <div class="improve-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                            </div>
                            <div class="improve-text">${suggestion}</div>
                        </div>
                    `;
                });
                document.getElementById("panelImprove").innerHTML = html;
            } else {
                if (d.improve && d.improve.trim()) {
                    document.getElementById("panelImprove").innerHTML = d.improve;
                } else {
                    document.getElementById("panelImprove").innerHTML = "<em>No improvement suggestions available</em>";
                }
            }
        })
        .catch(error => {
            if (d.improve && d.improve.trim()) {
                document.getElementById("panelImprove").innerHTML = d.improve;
            } else {
                document.getElementById("panelImprove").innerHTML = "<em>No improvement suggestions available</em>";
            }
        });

    document.getElementById("scorecardPanel").classList.add("open");
}

document.addEventListener('DOMContentLoaded', function() {
    // Toggle Switch
    document.getElementById("toggleSwitch").onchange = function() {
      if (this.checked) {
        // Show table, hide map
        const mapViewFlex = document.getElementById("mapViewFlex");
        const scorecardTableContainer = document.getElementById("scorecardTableContainer");

        mapViewFlex.style.visibility = "hidden";
        mapViewFlex.style.position = "absolute";
        mapViewFlex.style.height = 0;

        scorecardTableContainer.style.visibility = "visible";
        scorecardTableContainer.style.position = "relative";
        scorecardTableContainer.style.height = "auto";
      } else {
        // Show map, hide table
        const mapViewFlex = document.getElementById("mapViewFlex");
        const scorecardTableContainer = document.getElementById("scorecardTableContainer");

        mapViewFlex.style.visibility = "visible";
        mapViewFlex.style.position = "relative";
        mapViewFlex.style.height = "auto";

        scorecardTableContainer.style.visibility = "hidden";
        scorecardTableContainer.style.position = "absolute";
        scorecardTableContainer.style.height = 0;

        setTimeout(function() {
          if (chart && chart.root) {
            chart.root.resize();
          }
        }, 100);
      }
    };

    // Grade filter pills
    document.querySelectorAll('#gradeFilterPills .pill').forEach(btn => {
        btn.onclick = function() {
          document.querySelectorAll('#gradeFilterPills .pill').forEach(b => b.classList.remove('active'));
          this.classList.add('active');
          currentGradeFilter = this.getAttribute('data-grade');
          updateMap(getFilteredStates());
          updateTable(getFilteredStates());
        };
    });

    // Impact dropdown
    const impactDropdown = document.getElementById("impactDropdown");
    impactDropdown.onchange = function() {
        const value = this.value;
        const pills = document.getElementById("gradeFilterPills");
        const legend = document.getElementById("impactLegend");

        if (value === "grade") {
          pills.style.display = "flex";
          legend.style.display = "none";
          currentGradeFilter = "";
          document.querySelectorAll('#gradeFilterPills .pill').forEach(b => b.classList.remove('active'));
          document.querySelector('#gradeFilterPills .pill[data-grade=""]').classList.add('active');
        } else {
          pills.style.display = "none";
          legend.style.display = "block";
          let label = "";
          if (value === "racial") label = "Racial Impact";
          else if (value === "income") label = "Income Impact";
          else if (value === "stateImpact") label = "State Impact";

          legend.innerHTML = `
            <div style="display:flex;flex-direction:column;gap:4px;">
              <div><span style="display:inline-block;width:24px;height:16px;background:${rankShades[0].toCSSHex()};margin-right:8px;"></span>1st–10th ${label} rank</div>
              <div><span style="display:inline-block;width:24px;height:16px;background:${rankShades[1].toCSSHex()};margin-right:8px;"></span>11th–20th ${label} rank</div>
              <div><span style="display:inline-block;width:24px;height:16px;background:${rankShades[2].toCSSHex()};margin-right:8px;"></span>21st–30th ${label} rank</div>
              <div><span style="display:inline-block;width:24px;height:16px;background:${rankShades[3].toCSSHex()};margin-right:8px;"></span>31st–40th ${label} rank</div>
              <div><span style="display:inline-block;width:24px;height:16px;background:${rankShades[4].toCSSHex()};margin-right:8px;"></span>41st–50th ${label} rank</div>
            </div>
          `;
        }
        updateMap(getFilteredStates());
    };

    // Table sorting
    document.querySelectorAll('.state-score-table th.sortable').forEach(th => {
        th.onclick = function() {
          let col = th.getAttribute('data-sort');
          if (tableSortCol === col) {
            tableSortDir = tableSortDir === "asc" ? "desc" : "asc";
          } else {
            tableSortCol = col;
            tableSortDir = "asc";
          }

          document.querySelectorAll('.state-score-table th .sort-arrow').forEach(a => {
            a.className = 'sort-arrow';
          });

          const arrow = th.querySelector('.sort-arrow');
          arrow.className = `sort-arrow ${tableSortDir}`;

          updateTable(getFilteredStates());
        };
    });

    // Close popup
    document.getElementById("closePanel").onclick = () => {
        document.getElementById("scorecardPanel").classList.remove("open");
    };
});

// amCharts initialization stays outside DOMContentLoaded
am5.ready(function () {
    try {
      root = am5.Root.new("scorecardMapContainer");
      root.setThemes([am5themes_Animated.new(root)]);

      chart = root.container.children.push(
          am5map.MapChart.new(root, {
            panX: "none",
            panY: "none",
            wheelX: "none",
            wheelY: "none",
            pinchZoom: false,
            projection: am5map.geoAlbersUsa(),
            paddingTop: 10,
            paddingBottom: 10,
            paddingLeft: 10,
            paddingRight: 10
          })
        );


      polygonSeries = chart.series.push(
        am5map.MapPolygonSeries.new(root, {
          geoJSON: am5geodata_usaLow,
          valueField: "value"
        })
      );

      polygonSeries.mapPolygons.template.setAll({
        tooltipHTML: `<div style="text-align:center;">
          <strong>{name}</strong><br>
          <span style="font-size:22px;font-weight:bold;color:#940036;">{grade}</span><br>
          <div style="font-size:0.9em;margin:4px 0;">Bills: {billCount} | Score: {avgScore}</div>
          <a href="{url}" target="_blank" style="display:inline-block;margin-top:8px;padding:7px 18px;background:#940036;color:#fff;border-radius:4px;font-weight:600;text-decoration:none;">View Scorecard</a>
        </div>`,
        interactive: true,
        stroke: am5.color(0x940036),
        strokeWidth: 1
      });

      polygonSeries.mapPolygons.template.adapters.add("fill", function(fill, target) {
        const d = target.dataItem.dataContext;
        let colorBy = document.getElementById("impactDropdown") ? document.getElementById("impactDropdown").value : "grade";
        if (colorBy === "grade") {
          return getGradeColor(d.grade);
        } else if (["racial", "income", "stateImpact"].includes(colorBy)) {
          return getRankColor(d._rank);
        } else {
          return getGradeColor(d.grade);
        }
      });

      labelSeries = chart.series.push(
        am5map.MapPointSeries.new(root, {
          valueField: "value",
          calculateAggregates: true
        })
      );

      labelSeries.bullets.push(function() {
        return am5.Bullet.new(root, {
          sprite: am5.Label.new(root, {
            text: "{abbr}",
            centerX: am5.p50,
            centerY: am5.p50,
            fontSize: 18,
            fontWeight: "bold",
            fill: am5.color(0x940036),
            populateText: true
          })
        });
      });

      chart.series.removeValue(labelSeries);
      chart.series.push(labelSeries);

      polygonSeries.mapPolygons.template.events.on("click", function (ev) {
        const d = ev.target.dataItem.dataContext;
        if (!d || !d.name) return;
        showPanel(d);
      });

      // Initial data population
      updateMap(getFilteredStates());
      updateTable(getFilteredStates());

    } catch (error) {
      console.error("Error initializing map:", error);
      document.getElementById("scorecardMapContainer").innerHTML = '<div style="padding:20px;text-align:center;color:#940036;">Error loading map. Please refresh the page.</div>';
    }
});

// --- Only ONE set of resize/orientation handlers! ---
function forceMapResize() {
  if (chart && chart.root) {
    chart.root.resize();
  }
}
window.addEventListener("resize", function() {
  setTimeout(forceMapResize, 200);
});
window.addEventListener("orientationchange", function() {
  setTimeout(forceMapResize, 300);
});


document.addEventListener('DOMContentLoaded', function() {
  // Mobile controls toggle
  const controls = document.getElementById('mapControls');
  const toggleBtn = document.getElementById('mobileControlsToggle');
  const legend = document.getElementById('impactLegend');

  function isMobile() {
    return window.innerWidth <= 600;
  }

  function showControls(show) {
    if (show) {
      controls.style.display = 'block';
      toggleBtn.textContent = 'Hide Filters & Legend';
    } else {
      controls.style.display = 'none';
      toggleBtn.textContent = 'Show Filters & Legend';
    }
  }

  if (isMobile()) {
    toggleBtn.style.display = 'flex';
    showControls(false);
    // Show floating legend if needed
    if (legend && legend.innerHTML.trim() !== '') {
      legend.style.display = 'block';
    }
  }

  toggleBtn.addEventListener('click', function() {
    if (controls.style.display === 'block') {
      showControls(false);
    } else {
      showControls(true);
      // Optionally scroll to controls
      setTimeout(() => {
        controls.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }, 100);
    }
  });

  // Hide legend when controls are open
  if (legend) {
    toggleBtn.addEventListener('click', function() {
      if (controls.style.display === 'block') {
        legend.style.display = 'none';
      } else {
        legend.style.display = 'block';
      }
    });
  }

  // Hide controls/legend on resize if not mobile
  window.addEventListener('resize', function() {
    if (!isMobile()) {
      controls.style.display = '';
      toggleBtn.style.display = 'none';
      if (legend) legend.style.display = '';
    } else {
      toggleBtn.style.display = 'flex';
      showControls(false);
      if (legend && legend.innerHTML.trim() !== '') {
        legend.style.display = 'block';
      }
    }
  });
});

        function adjustMapHeightForMobile() {
          var mapContainer = document.getElementById('scorecardMapContainer');
          if (window.innerWidth <= 600) {
            mapContainer.style.height = 'auto';
            mapContainer.style.minHeight = '220px';
          } else {
            mapContainer.style.height = '600px';
            mapContainer.style.minHeight = '600px';
          }
        }

        // Run on load and on resize
        window.addEventListener('DOMContentLoaded', adjustMapHeightForMobile);
        window.addEventListener('resize', adjustMapHeightForMobile);
</script>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * Get comprehensive state score map data from database
         * ENHANCED: Now includes bill counts, average scores, and better grade calculation
         */
        private function get_state_score_map_data() {
            global $wpdb;

            $region_map = [
                'ME'=>'Northeast','NH'=>'Northeast','VT'=>'Northeast','MA'=>'Northeast',
                'RI'=>'Northeast','CT'=>'Northeast','NY'=>'Northeast','NJ'=>'Northeast',
                'PA'=>'Northeast','OH'=>'Midwest','MI'=>'Midwest','IN'=>'Midwest',
                'IL'=>'Midwest','WI'=>'Midwest','MN'=>'Midwest','IA'=>'Midwest',
                'MO'=>'Midwest','ND'=>'Midwest','SD'=>'Midwest','NE'=>'Midwest',
                'KS'=>'Midwest','DE'=>'South','MD'=>'South','DC'=>'South','VA'=>'South',
                'WV'=>'South','NC'=>'South','SC'=>'South','GA'=>'South','FL'=>'South',
                'KY'=>'South','TN'=>'South','MS'=>'South','AL'=>'South','OK'=>'South',
                'TX'=>'South','AR'=>'South','LA'=>'South','MT'=>'West','WY'=>'West',
                'CO'=>'West','NM'=>'West','ID'=>'West','UT'=>'West','AZ'=>'West',
                'NV'=>'West','WA'=>'West','OR'=>'West','CA'=>'West','AK'=>'West','HI'=>'West'
            ];

            // List of all state abbreviations and names
            $states = [
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
            ];

            $table_name = $wpdb->prefix . 'legiscan_bill_grades';
            $data = [];

            foreach ($states as $abbr => $name) {
                // Get comprehensive statistics for this state
                $stats = $this->get_state_statistics($abbr);

                // Get all grades for this state to determine overall grade
                $bills = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT COALESCE(manual_grade, grade) as effective_grade, score, grading_details
                         FROM $table_name
                         WHERE state_code = %s AND grade IS NOT NULL", $abbr
                    ), ARRAY_A
                );

                $grade_counts = ['A'=>0,'B'=>0,'C'=>0,'D'=>0,'F'=>0];
                $total_score = 0;
                $graded_bills = 0;
                $racial_scores = [];
                $income_scores = [];
                $state_scores = [];

                foreach ($bills as $bill) {
                    $g = $bill['effective_grade'];
                    if ($g && in_array($g, ['A','B','C','D','F'])) {
                        $grade_counts[$g]++;
                        $graded_bills++;
                        $total_score += (float)$bill['score'];

                        // Extract impact scores from grading details if available
                        $details = json_decode($bill['grading_details'], true);
                        if ($details && isset($details['methodology']) && $details['methodology'] === 'option_2_census_based') {
                            if (isset($details['breakdown']['racial_impact'])) {
                                $racial_scores[] = $details['breakdown']['racial_impact'];
                            }
                            if (isset($details['breakdown']['income_impact'])) {
                                $income_scores[] = $details['breakdown']['income_impact'];
                            }
                            if (isset($details['breakdown']['state_impact'])) {
                                $state_scores[] = $details['breakdown']['state_impact'];
                            }
                        }
                    }
                }

                // Determine overall grade using weighted approach
                if ($graded_bills > 0) {
                    // Use most common grade, but weight by bill count
                    $max_count = max($grade_counts);
                    $grade = array_search($max_count, $grade_counts);

                    // If tie, use average score to break tie
                    if (array_count_values($grade_counts)[$max_count] > 1) {
                        $avg_score = $total_score / $graded_bills;
                        $grade = $this->score_to_grade($avg_score);
                    }
                } else {
                    $grade = 'F'; // Default for states with no graded bills
                }

                $avg_score = $graded_bills > 0 ? round($total_score / $graded_bills, 1) : 0;

                // Calculate impact scores
                $racial_impact = !empty($racial_scores) ? 
                    $this->score_to_grade(array_sum($racial_scores) / count($racial_scores)) : 
                    $this->score_to_grade($avg_score * 0.9);

                $income_impact = !empty($income_scores) ? 
                    $this->score_to_grade(array_sum($income_scores) / count($income_scores)) : 
                    $this->score_to_grade($avg_score * 0.8);

                $state_impact = !empty($state_scores) ? 
                    $this->score_to_grade(array_sum($state_scores) / count($state_scores)) : 
                    $this->score_to_grade($avg_score);

                $data[] = [
                    'abbr' => $abbr,
                    'name' => $name,
                    'grade' => $grade,
                    'racial' => $racial_impact,
                    'income' => $income_impact,
                    'stateImpact' => $state_impact,
                    'billCount' => $graded_bills,
                    'avgScore' => $avg_score,
                    'link' => home_url("/state/" . strtolower(str_replace(' ', '-', $name)) . "/"),
                    'region' => $region_map[$abbr] ?? 'Unknown',
                    'changed' => $this->get_state_recent_changes($abbr),
                    'improve' => $this->get_state_improvement_summary($abbr),
                    'gradeDistribution' => $grade_counts,
                    'totalBills' => $stats['total_bills'] ?? 0,
                    'manualOverrides' => $stats['manual_override_count'] ?? 0
                ];
            }

            return $data;
        }

        /**
         * Get state statistics using grading engine
         */
        private function get_state_statistics($state_code) {
            if ($this->grading_engine) {
                return $this->grading_engine->get_grade_statistics($state_code);
            }
            return ['total_bills' => 0, 'manual_override_count' => 0];
        }

        /**
         * Get recent changes summary for a state
         */
        private function get_state_recent_changes($state_code) {
            global $wpdb;

            $table_name = $wpdb->prefix . 'legiscan_bill_grades';
            $current_year = date('Y');

            $recent_bills = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name 
                 WHERE state_code = %s 
                 AND YEAR(processed_date) = %d",
                $state_code, $current_year
            ));

            if ($recent_bills > 0) {
                return "Analyzed $recent_bills criminal justice bills in $current_year";
            }

            return "No recent criminal justice legislation analyzed";
        }

        /**
         * Get improvement summary for a state
         */
        private function get_state_improvement_summary($state_code) {
            // This could be enhanced to pull from a dedicated improvements table
            return "Focus on sentencing reform, rehabilitation programs, and reducing recidivism";
        }

        /**
         * Helper function to convert numeric score to letter grade
         */
        private function score_to_grade($score) {
            if ($score >= 80) return 'A';
            elseif ($score >= 65) return 'B';
            elseif ($score >= 50) return 'C';
            elseif ($score >= 35) return 'D';
            else return 'F';
        }
        
        /**
         * Register REST API endpoints for dynamic content
         */
        public function register_state_scorecard_api() {
            // Endpoint for recent passed bills
            register_rest_route('legiscan-grader/v1', '/passed_bills', [
                'methods' => 'GET',
                'callback' => array($this, 'get_recent_passed_bills'),
                'permission_callback' => '__return_true',
            ]);

            // Endpoint for AI improvement suggestions
            register_rest_route('legiscan-grader/v1', '/ai_improve', [
                'methods' => 'GET',
                'callback' => array($this, 'get_ai_improvement_suggestions'),
                'permission_callback' => '__return_true',
            ]);

            // Endpoint for state statistics
            register_rest_route('legiscan-grader/v1', '/state_stats', [
                'methods' => 'GET',
                'callback' => array($this, 'get_state_statistics_api'),
                'permission_callback' => '__return_true',
            ]);
        }

        /**
         * Get recent passed bills for a state (API endpoint)
         */
        public function get_recent_passed_bills($request) {
            $state = $request->get_param('state');
            if (!$state) {
                return new WP_Error('missing_state', 'State parameter is required', ['status' => 400]);
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'legiscan_bill_grades';

            // Get recent bills from current year
            $current_year = date('Y');
            $bills = $wpdb->get_results($wpdb->prepare(
                "SELECT bill_number, bill_title, processed_date, grade, score
                 FROM $table_name 
                 WHERE state_code = %s 
                 AND YEAR(processed_date) = %d
                 ORDER BY processed_date DESC
                 LIMIT 10",
                $state, $current_year
            ), ARRAY_A);

            $formatted_bills = [];
            foreach ($bills as $bill) {
                $formatted_bills[] = [
                    'title' => $bill['bill_title'] ?: $bill['bill_number'],
                    'number' => $bill['bill_number'],
                    'date' => $bill['processed_date'],
                    'grade' => $bill['grade'],
                    'score' => $bill['score']
                ];
            }

            return [
                'state' => $state,
                'year' => $current_year,
                'bills' => $formatted_bills,
                'count' => count($formatted_bills)
            ];
        }

        /**
         * Get AI improvement suggestions for a state (API endpoint)
         */
        public function get_ai_improvement_suggestions($request) {
            $state = $request->get_param('state');
            $grade = $request->get_param('grade');
            $racial = $request->get_param('racial');
            $income = $request->get_param('income');
            $state_impact = $request->get_param('stateImpact');

            if (!$state) {
                return new WP_Error('missing_state', 'State parameter is required', ['status' => 400]);
            }

            // Generate contextual suggestions based on grades
            $suggestions = [];

            if ($grade === 'F' || $grade === 'D') {
                $suggestions[] = "Implement comprehensive criminal justice reform focusing on rehabilitation over punishment";
                $suggestions[] = "Establish drug courts and mental health courts to address root causes of crime";
                $suggestions[] = "Expand expungement and record sealing programs for non-violent offenses";
            }

            if ($racial === 'F' || $racial === 'D') {
                $suggestions[] = "Address racial disparities in sentencing through bias training and policy reform";
                $suggestions[] = "Implement data collection and reporting requirements for racial impact analysis";
            }

            if ($income === 'F' || $income === 'D') {
                $suggestions[] = "Reform cash bail system to reduce wealth-based detention";
                $suggestions[] = "Expand public defender funding and resources";
                $suggestions[] = "Create alternatives to fines and fees that disproportionately impact low-income individuals";
            }

            if ($state_impact === 'F' || $state_impact === 'D') {
                $suggestions[] = "Invest in community-based alternatives to incarceration";
                $suggestions[] = "Expand reentry programs and support services";
                $suggestions[] = "Focus on evidence-based practices that reduce recidivism";
            }

            // Add general suggestions if no specific ones
            if (empty($suggestions)) {
                $suggestions[] = "Continue monitoring and evaluating criminal justice policies for effectiveness";
                $suggestions[] = "Engage stakeholders in ongoing reform efforts";
                $suggestions[] = "Maintain focus on evidence-based practices";
            }

            return [
                'state' => $state,
                'suggestions' => $suggestions,
                'generated_at' => current_time('mysql')
            ];
        }

        /**
         * Get state statistics (API endpoint)
         */
        public function get_state_statistics_api($request) {
            $state = $request->get_param('state');
            if (!$state) {
                return new WP_Error('missing_state', 'State parameter is required', ['status' => 400]);
            }

            $stats = $this->get_state_statistics($state);

            return [
                'state' => $state,
                'statistics' => $stats,
                'generated_at' => current_time('mysql')
            ];
        }

        /**
         * Initialize the scorecard map functionality
         */
        public static function init() {
            return new self();
        }
    }
}

// Initialize the class
LegiScan_Scorecard_Map::init();

// Instantiate the class once and reuse it for the shortcode
function legiscan_scorecard_map_shortcode($atts = []) {
    $scorecard_map = LegiScan_Scorecard_Map::init();
    return $scorecard_map->render_map($atts);
}
add_shortcode('legiscan_scorecard_map', 'legiscan_scorecard_map_shortcode');

function render_legiscan_scorecard_map($atts = []) {
    return legiscan_scorecard_map_shortcode($atts);
}
?>