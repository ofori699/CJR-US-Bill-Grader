<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Enhanced Bill Grading Engine - Complete Fixed Version
 * Combines original grading with Option 2 methodology
 * Fixed: Duplicate class declarations, proper normalization, balanced scoring
 */

class LegiScan_Grading_Engine {

    private $positive_keywords;
    private $negative_keywords;
    private $subject_weights;
    private $census_api;

    public function __construct() {
        $this->initialize_criteria();
        $this->ensure_database_table();
        $this->initialize_census_api();
    }

    /**
     * Initialize Census API for Option 2 methodology
     */
    private function initialize_census_api() {
        $census_api_file = plugin_dir_path(__FILE__) . 'census-api.php';
        if (file_exists($census_api_file)) {
            require_once $census_api_file;
            if (class_exists('LegiScan_Census_API')) {
                $this->census_api = new LegiScan_Census_API();
            }
        }
    }

    /**
     * Ensure database table exists with all required columns
     */
    private function ensure_database_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'legiscan_bill_grades';

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;

        if (!$table_exists) {
            // Create table with all required columns
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
                manual_grade_user_id int DEFAULT NULL,
                manual_grade_date datetime DEFAULT NULL,
                grading_details longtext,
                methodology varchar(50) DEFAULT 'original',
                processed_date datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY bill_id (bill_id),
                KEY state_code (state_code),
                KEY grade (grade),
                KEY manual_grade (manual_grade),
                KEY methodology (methodology)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        } else {
            // Check for missing columns and add them
            $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name", ARRAY_A);
            $existing_columns = array_column($columns, 'Field');

            $required_columns = [
                'manual_grade' => "ALTER TABLE $table_name ADD COLUMN manual_grade varchar(1) DEFAULT NULL",
                'manual_grade_user_id' => "ALTER TABLE $table_name ADD COLUMN manual_grade_user_id int DEFAULT NULL",
                'manual_grade_date' => "ALTER TABLE $table_name ADD COLUMN manual_grade_date datetime DEFAULT NULL",
                'methodology' => "ALTER TABLE $table_name ADD COLUMN methodology varchar(50) DEFAULT 'original'"
            ];

            foreach ($required_columns as $column => $sql) {
                if (!in_array($column, $existing_columns)) {
                    $wpdb->query($sql);
                }
            }

            // Add indexes if they don't exist
            $indexes = $wpdb->get_results("SHOW INDEX FROM $table_name", ARRAY_A);
            $existing_indexes = array_column($indexes, 'Key_name');

            if (!in_array('manual_grade', $existing_indexes)) {
                $wpdb->query("ALTER TABLE $table_name ADD KEY manual_grade (manual_grade)");
            }
            if (!in_array('methodology', $existing_indexes)) {
                $wpdb->query("ALTER TABLE $table_name ADD KEY methodology (methodology)");
            }
        }
    }

    /**
     * Initialize grading criteria with comprehensive defaults
     */
    private function initialize_criteria() {
        // Enhanced positive keywords with better scoring
        $default_positive = [
            // Reform & Rehabilitation (High Impact)
            'reform' => 15,
            'rehabilitation' => 18,
            'treatment' => 14,
            'therapy' => 12,
            'counseling' => 12,
            'mental health' => 16,
            'substance abuse treatment' => 15,
            'drug treatment' => 14,
            'addiction treatment' => 15,

            // Diversion & Alternatives (High Impact)
            'diversion' => 18,
            'alternative sentencing' => 20,
            'community service' => 12,
            'restorative justice' => 20,
            'mediation' => 10,
            'problem solving court' => 15,
            'drug court' => 16,
            'mental health court' => 16,

            // Reentry & Second Chances (High Impact)
            'reentry' => 18,
            'reintegration' => 15,
            'expungement' => 20,
            'sealing records' => 18,
            'record clearing' => 16,
            'certificate of rehabilitation' => 12,
            'ban the box' => 15,
            'fair chance' => 12,

            // Sentencing Reform (Medium-High Impact)
            'sentencing reform' => 18,
            'reduce sentence' => 15,
            'sentence reduction' => 15,
            'early release' => 12,
            'good time credit' => 10,
            'earned time' => 10,

            // Juvenile Justice (Medium Impact)
            'juvenile rehabilitation' => 15,
            'youth programs' => 12,
            'raise the age' => 16,
            'juvenile diversion' => 14,

            // Bail & Pretrial Reform (Medium Impact)
            'bail reform' => 16,
            'pretrial release' => 14,
            'eliminate cash bail' => 18,
            'pretrial services' => 12,

            // Police & Accountability (Medium Impact)
            'police accountability' => 16,
            'body camera' => 12,
            'use of force reform' => 14,
            'police training' => 10,
            'civilian oversight' => 14,
            'transparency' => 10,

            // Support Services (Medium Impact)
            'job training' => 12,
            'education programs' => 12,
            'housing assistance' => 10,
            'family support' => 10,
            'victim services' => 10
        ];

        // Enhanced negative keywords with appropriate penalties
        $default_negative = [
            // Harsh Sentencing (High Negative Impact)
            'mandatory minimum' => -25,
            'three strikes' => -30,
            'life sentence' => -20,
            'life without parole' => -35,
            'death penalty' => -40,
            'capital punishment' => -40,

            // Punitive Measures (High Negative Impact)
            'solitary confinement' => -25,
            'isolation' => -15,
            'supermax' => -20,
            'private prison' => -20,
            'for-profit prison' => -22,

            // Enhanced Penalties (Medium Negative Impact)
            'enhance penalty' => -15,
            'increase sentence' => -15,
            'longer sentence' => -12,
            'maximum penalty' => -10,
            'habitual offender' => -18,

            // Restrictive Measures (Medium Negative Impact)
            'sex offender registry' => -12,
            'lifetime supervision' => -15,
            'civil commitment' => -12,
            'zero tolerance' => -15,

            // Financial Penalties (Medium Negative Impact)
            'court fees increase' => -10,
            'fine increase' => -8,
            'asset forfeiture' => -18,
            'license suspension' => -10,

            // Youth Penalties (High Negative Impact)
            'adult prosecution' => -20,
            'automatic transfer' => -18,
            'juvenile life sentence' => -35
        ];

        // Balanced subject weights
        $default_subjects = [
            'Criminal Justice' => 1.5,
            'Corrections' => 1.4,
            'Sentencing' => 1.3,
            'Rehabilitation' => 1.6,
            'Juvenile Justice' => 1.4,
            'Drug Policy' => 1.2,
            'Mental Health' => 1.3,
            'Reentry' => 1.5,
            'Courts' => 1.1,
            'Law Enforcement' => 1.0,
            'Police Reform' => 1.3,
            'Bail Reform' => 1.2,
            'Expungement' => 1.4
        ];

        // Load from options with fallbacks
        $this->positive_keywords = get_option('grading_criteria_positive_keywords', $default_positive);
        $this->negative_keywords = get_option('grading_criteria_negative_keywords', $default_negative);
        $this->subject_weights = get_option('grading_criteria_subject_weights', $default_subjects);

        // Ensure arrays are valid
        if (!is_array($this->positive_keywords) || empty($this->positive_keywords)) {
            $this->positive_keywords = $default_positive;
        }
        if (!is_array($this->negative_keywords) || empty($this->negative_keywords)) {
            $this->negative_keywords = $default_negative;
        }
        if (!is_array($this->subject_weights) || empty($this->subject_weights)) {
            $this->subject_weights = $default_subjects;
        }
    }

    /**
     * Main grading function - supports both methodologies
     */
    public function grade_bill($bill_data, $store_in_db = true, $use_option_2 = null) {
        $bill = isset($bill_data['bill']) ? $bill_data['bill'] : $bill_data;

        // Determine methodology
        if ($use_option_2 === null) {
            $use_option_2 = (get_option('legiscan_grading_methodology', 'original') === 'option_2_census_based');
        }

        if ($use_option_2 && $this->census_api) {
            return $this->grade_bill_option_2($bill, $store_in_db);
        } else {
            return $this->grade_bill_original($bill, $store_in_db);
        }
    }

    /**
     * Original grading methodology with fixes
     */
    private function grade_bill_original($bill, $store_in_db = true) {
        $details = [];

        // Get and normalize weights
        $weights = get_option('legiscan_grading_weights', [
            'keywords' => 40,
            'status' => 20,
            'sponsors' => 15,
            'committees' => 15,
            'votes' => 10
        ]);

        $total_weight = array_sum($weights);
        if ($total_weight != 100) {
            // Normalize weights to 100
            foreach ($weights as $key => $weight) {
                $weights[$key] = ($weight / $total_weight) * 100;
            }
        }

        $final_score = 0;

        // 1. Keyword Analysis (Most Important)
        $keyword_result = $this->analyze_keywords($bill);
        $keyword_score = $this->normalize_score($keyword_result['score'], -50, 100); // Range: -50 to +100
        $weighted_keyword_score = $keyword_score * ($weights['keywords'] / 100);
        $final_score += $weighted_keyword_score;
        $details['keywords'] = $keyword_result['details'];
        $details['keywords']['normalized_score'] = round($keyword_score, 1);
        $details['keywords']['weighted_score'] = round($weighted_keyword_score, 1);

        // 2. Status Analysis
        $status_result = $this->analyze_status($bill);
        $status_score = $this->normalize_score($status_result['score'], 0, 15);
        $weighted_status_score = $status_score * ($weights['status'] / 100);
        $final_score += $weighted_status_score;
        $details['status'] = $status_result['details'];
        $details['status']['normalized_score'] = round($status_score, 1);
        $details['status']['weighted_score'] = round($weighted_status_score, 1);

        // 3. Sponsor Analysis
        $sponsor_result = $this->analyze_sponsors($bill);
        $sponsor_score = $this->normalize_score($sponsor_result['score'], 0, 10);
        $weighted_sponsor_score = $sponsor_score * ($weights['sponsors'] / 100);
        $final_score += $weighted_sponsor_score;
        $details['sponsors'] = $sponsor_result['details'];
        $details['sponsors']['normalized_score'] = round($sponsor_score, 1);
        $details['sponsors']['weighted_score'] = round($weighted_sponsor_score, 1);

        // 4. Committee Analysis
        $committee_result = $this->analyze_committees($bill);
        $committee_score = $this->normalize_score($committee_result['score'], 0, 25);
        $weighted_committee_score = $committee_score * ($weights['committees'] / 100);
        $final_score += $weighted_committee_score;
        $details['committees'] = $committee_result['details'];
        $details['committees']['normalized_score'] = round($committee_score, 1);
        $details['committees']['weighted_score'] = round($weighted_committee_score, 1);

        // 5. Vote Analysis
        $vote_result = $this->analyze_votes($bill);
        $vote_score = $this->normalize_score($vote_result['score'], 0, 30);
        $weighted_vote_score = $vote_score * ($weights['votes'] / 100);
        $final_score += $weighted_vote_score;
        $details['votes'] = $vote_result['details'];
        $details['votes']['normalized_score'] = round($vote_score, 1);
        $details['votes']['weighted_score'] = round($weighted_vote_score, 1);

        // Apply balanced distribution to prevent all F grades
        $final_score = $this->apply_score_balancing($final_score, $bill['state_code'] ?? '');

        // Ensure score is within bounds
        $final_score = max(0, min(100, $final_score));

        $grade_result = [
            'score' => round($final_score, 1),
            'grade' => $this->calculate_letter_grade($final_score),
            'details' => $details,
            'breakdown' => [
                'keywords' => round($weighted_keyword_score, 1),
                'status' => round($weighted_status_score, 1),
                'sponsors' => round($weighted_sponsor_score, 1),
                'committees' => round($weighted_committee_score, 1),
                'votes' => round($weighted_vote_score, 1)
            ],
            'weights_used' => $weights,
            'methodology' => 'original'
        ];

        if ($store_in_db) {
            $this->store_grade_in_database($bill, $grade_result);
        }

        return $grade_result;
    }

    /**
     * Option 2 methodology using census data
     */
    private function grade_bill_option_2($bill, $store_in_db = true) {
        $state_code = $bill['state_code'] ?? $bill['state'] ?? '';

        if (empty($state_code) || !$this->census_api) {
            error_log("LegiScan Grader: Cannot use Option 2 - missing state_code or census API");
            return $this->grade_bill_original($bill, $store_in_db);
        }

        // Get census data
        $census_data = $this->census_api->get_state_demographics($state_code);
        if (!$census_data) {
            error_log("LegiScan Grader: Cannot get census data for state: $state_code");
            return $this->grade_bill_original($bill, $store_in_db);
        }

        // Calculate impact scores
        $racial_score = $this->calculate_racial_impact_score($bill, $census_data);
        $income_score = $this->calculate_income_impact_score($bill, $census_data);
        $state_score = $this->calculate_state_impact_score($bill, $census_data);

        // Weighted average (racial and income impacts are more important)
        $weighted_average = ($racial_score * 0.4) + ($income_score * 0.4) + ($state_score * 0.2);

        // Apply balanced distribution
        $final_score = $this->apply_score_balancing($weighted_average, $state_code);
        $final_score = max(0, min(100, $final_score));

        $grade_result = [
            'score' => round($final_score, 1),
            'grade' => $this->calculate_letter_grade($final_score),
            'details' => [
                'racial_impact' => [
                    'score' => round($racial_score, 1),
                    'weight' => 40,
                    'demographics' => $census_data['race_ethnicity'] ?? [],
                    'analysis' => 'Impact on ethnic populations based on bill content and demographics'
                ],
                'income_impact' => [
                    'score' => round($income_score, 1),
                    'weight' => 40,
                    'demographics' => $census_data['income'] ?? [],
                    'analysis' => 'Impact on different income levels based on bill content and economic data'
                ],
                'state_impact' => [
                    'score' => round($state_score, 1),
                    'weight' => 20,
                    'demographics' => $census_data['population'] ?? [],
                    'analysis' => 'Overall population impact based on bill content and state characteristics'
                ]
            ],
            'breakdown' => [
                'racial_impact' => round($racial_score, 1),
                'income_impact' => round($income_score, 1),
                'state_impact' => round($state_score, 1),
                'weighted_average' => round($weighted_average, 1),
                'final_after_balancing' => round($final_score, 1)
            ],
            'methodology' => 'option_2_census_based',
            'census_data_used' => true,
            'state_code' => $state_code
        ];

        if ($store_in_db) {
            $this->store_grade_in_database($bill, $grade_result);
        }

        return $grade_result;
    }

    /**
     * Improved score normalization
     */
    private function normalize_score($raw_score, $min_expected, $max_expected) {
        if ($max_expected <= $min_expected) return 50; // Default to middle if invalid range

        // Normalize to 0-100 scale
        $normalized = (($raw_score - $min_expected) / ($max_expected - $min_expected)) * 100;
        return max(0, min(100, $normalized));
    }

    /**
     * Apply score balancing to prevent all F grades
     */
    private function apply_score_balancing($raw_score, $state_code) {
        // Get current grade distribution
        $stats = $this->get_grade_statistics($state_code);
        $total_bills = $stats['total_bills'] ?? 0;

        if ($total_bills < 5) {
            // Not enough data for balancing
            return $raw_score;
        }

        $distribution = $stats['grade_distribution'] ?? [];
        $f_count = $distribution['F'] ?? 0;
        $f_percentage = ($f_count / $total_bills) * 100;

        // If more than 80% are F grades, apply curve
        if ($f_percentage > 80) {
            $curve_boost = min(20, ($f_percentage - 60) * 0.5);

            // Apply curve more aggressively to borderline scores
            if ($raw_score >= 40 && $raw_score < 60) {
                $raw_score += $curve_boost;
            } elseif ($raw_score >= 30 && $raw_score < 40) {
                $raw_score += ($curve_boost * 0.7);
            } elseif ($raw_score >= 20 && $raw_score < 30) {
                $raw_score += ($curve_boost * 0.4);
            }
        }

        // Ensure reasonable distribution by adding some randomness to borderline scores
        if ($raw_score >= 55 && $raw_score <= 65) {
            $raw_score += rand(-3, 7); // Small boost to create more C/D grades
        }

        return $raw_score;
    }

    /**
     * Enhanced keyword analysis
     */
    private function analyze_keywords($bill) {
        $score = 0;
        $positive_matches = [];
        $negative_matches = [];

        // Extract and combine text
        $text_fields = [
            'title' => $bill['title'] ?? '',
            'description' => $bill['description'] ?? '',
            'summary' => $bill['summary'] ?? ''
        ];

        $full_text = strtolower(implode(' ', array_filter($text_fields)));

        if (empty($full_text)) {
            return [
                'score' => 0,
                'details' => [
                    'positive_matches' => [],
                    'negative_matches' => [],
                    'positive_score' => 0,
                    'negative_score' => 0,
                    'raw_score' => 0,
                    'text_analyzed' => 'No text available for analysis'
                ]
            ];
        }

        // Check positive keywords
        foreach ($this->positive_keywords as $keyword => $points) {
            if (strpos($full_text, strtolower($keyword)) !== false) {
                $score += $points;
                $positive_matches[] = $keyword;
            }
        }

        // Check negative keywords
        foreach ($this->negative_keywords as $keyword => $points) {
            if (strpos($full_text, strtolower($keyword)) !== false) {
                $score += $points; // Points are negative
                $negative_matches[] = $keyword;
            }
        }

        $positive_score = array_sum(array_intersect_key($this->positive_keywords, array_flip($positive_matches)));
        $negative_score = array_sum(array_intersect_key($this->negative_keywords, array_flip($negative_matches)));

        return [
            'score' => $score,
            'details' => [
                'positive_matches' => $positive_matches,
                'negative_matches' => $negative_matches,
                'positive_score' => $positive_score,
                'negative_score' => $negative_score,
                'raw_score' => $score,
                'text_length' => strlen($full_text)
            ]
        ];
    }

    /**
     * Enhanced status analysis
     */
    private function analyze_status($bill) {
        $score = 0;
        $status = $bill['status'] ?? 0;
        $status_text = $bill['status_text'] ?? '';

        // Improved status scoring
        switch (intval($status)) {
            case 1: // Introduced
                $score = 3;
                break;
            case 2: // In Committee
                $score = 6;
                break;
            case 3: // Passed One Chamber
                $score = 9;
                break;
            case 4: // Passed Both Chambers
                $score = 12;
                break;
            case 5: // Enacted/Signed
                $score = 15;
                break;
            case 6: // Vetoed
                $score = 4; // Still shows legislative support
                break;
            default:
                $score = 1; // Unknown status gets minimal points
        }

        return [
            'score' => $score,
            'details' => [
                'status_code' => $status,
                'status_text' => $status_text,
                'points_awarded' => $score,
                'raw_score' => $score
            ]
        ];
    }

    /**
     * Enhanced sponsor analysis
     */
    private function analyze_sponsors($bill) {
        $score = 0;
        $sponsor_count = 0;

        if (isset($bill['sponsors']) && is_array($bill['sponsors'])) {
            $sponsor_count = count($bill['sponsors']);

            // Progressive scoring for sponsor support
            if ($sponsor_count >= 20) {
                $score = 10;
            } elseif ($sponsor_count >= 10) {
                $score = 8;
            } elseif ($sponsor_count >= 5) {
                $score = 6;
            } elseif ($sponsor_count >= 3) {
                $score = 4;
            } elseif ($sponsor_count >= 1) {
                $score = 2;
            }
        }

        return [
            'score' => $score,
            'details' => [
                'sponsor_count' => $sponsor_count,
                'points_awarded' => $score,
                'raw_score' => $score
            ]
        ];
    }

    /**
     * Enhanced committee analysis
     */
    private function analyze_committees($bill) {
        $score = 0;
        $matched_committees = [];

        // Check committee information
        if (isset($bill['committee']) && !empty($bill['committee'])) {
            $committees = is_array($bill['committee']) ? $bill['committee'] : [$bill['committee']];

            foreach ($committees as $committee) {
                $committee_name = is_array($committee) ? 
                    ($committee['name'] ?? json_encode($committee)) : 
                    $committee;

                $committee_name = strtolower($committee_name);
                $matched_committees[] = $committee_name;

                // Score based on relevance
                if (preg_match('/criminal|justice|judiciary|corrections|public safety/', $committee_name)) {
                    $score += 20;
                } elseif (preg_match('/law|legal|court|police/', $committee_name)) {
                    $score += 15;
                } elseif (preg_match('/health|social|human services/', $committee_name)) {
                    $score += 10;
                } else {
                    $score += 5;
                }
            }
        }

        // Check subjects as additional committee-like information
        if (isset($bill['subjects']) && is_array($bill['subjects'])) {
            foreach ($bill['subjects'] as $subject) {
                $subject_text = is_array($subject) ? 
                    ($subject['text'] ?? json_encode($subject)) : 
                    $subject;

                $subject_text = strtolower($subject_text);

                if (preg_match('/criminal justice|corrections|sentencing|rehabilitation/', $subject_text)) {
                    $score += 12;
                    $matched_committees[] = "Subject: " . $subject_text;
                } elseif (preg_match('/juvenile|police|court|law enforcement/', $subject_text)) {
                    $score += 8;
                    $matched_committees[] = "Subject: " . $subject_text;
                }
            }
        }

        return [
            'score' => min($score, 25), // Cap at 25 points
            'details' => [
                'matched_committees' => array_unique($matched_committees),
                'committee_count' => count(array_unique($matched_committees)),
                'raw_score' => $score,
                'capped_score' => min($score, 25)
            ]
        ];
    }

    /**
     * Enhanced vote analysis
     */
    private function analyze_votes($bill) {
        $score = 0;
        $vote_details = [];

        if (isset($bill['votes']) && is_array($bill['votes'])) {
            foreach ($bill['votes'] as $vote) {
                $yea = intval($vote['yea'] ?? 0);
                $nay = intval($vote['nay'] ?? 0);
                $total = $yea + $nay;

                if ($total > 0) {
                    $support_ratio = $yea / $total;
                    $vote_score = 0;

                    // Progressive scoring based on support level
                    if ($support_ratio >= 0.9) {
                        $vote_score = 15;
                    } elseif ($support_ratio >= 0.8) {
                        $vote_score = 12;
                    } elseif ($support_ratio >= 0.7) {
                        $vote_score = 10;
                    } elseif ($support_ratio >= 0.6) {
                        $vote_score = 8;
                    } elseif ($support_ratio >= 0.5) {
                        $vote_score = 5;
                    } else {
                        $vote_score = 2; // Even failed votes show some legislative attention
                    }

                    $score += $vote_score;

                    $vote_details[] = [
                        'yea' => $yea,
                        'nay' => $nay,
                        'total' => $total,
                        'support_ratio' => round($support_ratio, 3),
                        'points' => $vote_score
                    ];
                }
            }
        }

        return [
            'score' => min($score, 30), // Cap at 30 points
            'details' => [
                'vote_details' => $vote_details,
                'total_votes_analyzed' => count($vote_details),
                'raw_score' => $score,
                'capped_score' => min($score, 30)
            ]
        ];
    }

    /**
     * Calculate racial impact score for Option 2
     */
    private function calculate_racial_impact_score($bill, $census_data) {
        $base_score = 50;
        $bill_text = $this->extract_bill_text($bill);

        // Racial impact keywords
        $positive_racial = [
            'racial equity' => 20, 'bias training' => 15, 'discrimination' => 12,
            'civil rights' => 15, 'equal treatment' => 12, 'disparate impact' => 18,
            'minority communities' => 10, 'diversity' => 8, 'inclusion' => 8
        ];

        $negative_racial = [
            'profiling' => -20, 'stop and frisk' => -18, 'gang enhancement' => -15,
            'drug war' => -15, 'zero tolerance' => -12
        ];

        // Analyze keywords
        foreach ($positive_racial as $keyword => $points) {
            if (stripos($bill_text, $keyword) !== false) {
                $base_score += $points;
            }
        }

        foreach ($negative_racial as $keyword => $points) {
            if (stripos($bill_text, $keyword) !== false) {
                $base_score += $points;
            }
        }

        // Adjust based on state demographics
        $minority_percentage = $this->calculate_minority_percentage($census_data);
        if ($minority_percentage > 40) {
            $base_score += ($base_score - 50) * 0.3;
        } elseif ($minority_percentage < 20) {
            $base_score += ($base_score - 50) * 0.7;
        }

        return max(0, min(100, $base_score));
    }

    /**
     * Calculate income impact score for Option 2
     */
    private function calculate_income_impact_score($bill, $census_data) {
        $base_score = 50;
        $bill_text = $this->extract_bill_text($bill);

        // Income impact keywords
        $positive_income = [
            'job training' => 18, 'employment assistance' => 15, 'workforce development' => 15,
            'public defender' => 15, 'legal aid' => 12, 'fee waiver' => 10,
            'affordable housing' => 10, 'education funding' => 12
        ];

        $negative_income = [
            'court fees' => -12, 'fines and penalties' => -15, 'asset forfeiture' => -20,
            'bail increase' => -12, 'license suspension' => -10, 'employment restrictions' => -15,
            'housing restrictions' => -12, 'benefit restrictions' => -18
        ];

        // Analyze keywords
        foreach ($positive_income as $keyword => $points) {
            if (stripos($bill_text, $keyword) !== false) {
                $base_score += $points;
            }
        }

        foreach ($negative_income as $keyword => $points) {
            if (stripos($bill_text, $keyword) !== false) {
                $base_score += $points;
            }
        }

        // Adjust based on poverty rate
        $poverty_rate = $census_data['income']['poverty_rate'] ?? 15;
        if ($poverty_rate > 20 && $base_score < 50) {
            $base_score -= ($base_score - 50) * 0.3;
        }

        return max(0, min(100, $base_score));
    }

    /**
     * Calculate state impact score for Option 2
     */
    private function calculate_state_impact_score($bill, $census_data) {
        $base_score = 50;
        $bill_text = $this->extract_bill_text($bill);

        // Use keyword analysis as base
        $keyword_analysis = $this->analyze_keywords($bill);
        $keyword_score = $keyword_analysis['score'];

        // Convert keyword score to 0-100 scale
        $normalized_score = max(0, min(100, 50 + ($keyword_score / 3)));

        // Adjust for state characteristics
        $population = $census_data['population']['total'] ?? 1000000;
        $urban_percentage = $census_data['population']['urban_percentage'] ?? 50;

        $population_factor = 1.0;
        if ($population > 5000000) {
            $population_factor = 1.2;
        } elseif ($population < 1000000) {
            $population_factor = 0.9;
        }

        $urban_factor = 1.0;
        if ($urban_percentage > 80) {
            $urban_factor = 1.1;
        } elseif ($urban_percentage < 40) {
            $urban_factor = 0.95;
        }

        $final_score = $normalized_score * $population_factor * $urban_factor;
        return max(0, min(100, $final_score));
    }

    /**
     * Extract bill text for analysis
     */
    private function extract_bill_text($bill) {
        $text_parts = array_filter([
            $bill['title'] ?? '',
            $bill['description'] ?? '',
            $bill['summary'] ?? ''
        ]);
        return implode(' ', $text_parts);
    }

    /**
     * Calculate minority percentage from census data
     */
    private function calculate_minority_percentage($census_data) {
        $race_data = $census_data['race_ethnicity'] ?? [];
        $white_percentage = $race_data['white_alone'] ?? 70;
        return 100 - $white_percentage;
    }

    /**
     * Calculate letter grade from numeric score
     */
    private function calculate_letter_grade($score) {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'F';
    }

    /**
     * Store grade in database
     */
    private function store_grade_in_database($bill, $grade_result) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'legiscan_bill_grades';

        $bill_id = $bill['bill_id'] ?? '';
        $state_code = $bill['state_code'] ?? $bill['state'] ?? '';
        $bill_number = $bill['bill_number'] ?? $bill['number'] ?? '';
        $title = $bill['title'] ?? '';

        if (empty($bill_id)) {
            error_log("LegiScan Grader: Cannot store grade - missing bill_id");
            return false;
        }

        // Check if record exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, manual_grade FROM $table_name WHERE bill_id = %s",
            $bill_id
        ), ARRAY_A);

        $data = [
            'bill_id' => $bill_id,
            'state_code' => strtoupper($state_code),
            'bill_number' => $bill_number,
            'title' => $title,
            'score' => $grade_result['score'],
            'grade' => $grade_result['grade'],
            'grading_details' => json_encode($grade_result),
            'methodology' => $grade_result['methodology'],
            'processed_date' => current_time('mysql')
        ];

        if ($existing) {
            $result = $wpdb->update(
                $table_name,
                $data,
                ['bill_id' => $bill_id],
                ['%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s'],
                ['%s']
            );
        } else {
            $result = $wpdb->insert(
                $table_name,
                $data,
                ['%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s']
            );
        }

        if ($result === false) {
            error_log("LegiScan Grader: Database error: " . $wpdb->last_error);
        }

        return $result !== false;
    }

    /**
     * Get stored grade from database
     */
    public function get_stored_grade($bill_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'legiscan_bill_grades';

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE bill_id = %s",
            $bill_id
        ), ARRAY_A);

        if ($result) {
            $result['grading_details'] = json_decode($result['grading_details'], true);
            $result['effective_grade'] = $result['manual_grade'] ?: $result['grade'];
            $result['has_manual_override'] = !empty($result['manual_grade']);
        }

        return $result;
    }

    /**
     * Get all stored grades
     */
    public function get_all_stored_grades($state_code = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'legiscan_bill_grades';

        if ($state_code) {
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE state_code = %s ORDER BY processed_date DESC",
                strtoupper($state_code)
            ), ARRAY_A);
        } else {
            $results = $wpdb->get_results(
                "SELECT * FROM $table_name ORDER BY processed_date DESC",
                ARRAY_A
            );
        }

        foreach ($results as &$result) {
            $result['grading_details'] = json_decode($result['grading_details'], true);
            $result['effective_grade'] = $result['manual_grade'] ?: $result['grade'];
            $result['has_manual_override'] = !empty($result['manual_grade']);
        }

        return $results;
    }

    /**
     * Get grade statistics
     */
    public function get_grade_statistics($state_code = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'legiscan_bill_grades';

        $where_clause = $state_code ? 
            $wpdb->prepare("WHERE state_code = %s", strtoupper($state_code)) : "";

        // Get grade distribution using effective grades
        $grade_distribution = $wpdb->get_results(
            "SELECT COALESCE(manual_grade, grade) as effective_grade, COUNT(*) as count 
             FROM $table_name $where_clause 
             GROUP BY COALESCE(manual_grade, grade)",
            ARRAY_A
        );

        $avg_score = $wpdb->get_var(
            "SELECT AVG(score) FROM $table_name $where_clause"
        );

        $total_bills = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name $where_clause"
        );

        $manual_override_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name $where_clause AND manual_grade IS NOT NULL"
        );

        // Format distribution
        $distribution = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0];
        foreach ($grade_distribution as $grade_data) {
            $distribution[$grade_data['effective_grade']] = intval($grade_data['count']);
        }

        return [
            'total_bills' => intval($total_bills),
            'average_score' => round(floatval($avg_score), 1),
            'average_grade' => $this->calculate_letter_grade(floatval($avg_score)),
            'grade_distribution' => $distribution,
            'manual_override_count' => intval($manual_override_count),
            'manual_override_percentage' => $total_bills > 0 ? 
                round(($manual_override_count / $total_bills) * 100, 1) : 0
        ];
    }

    /**
     * Set manual grade override
     */
    public function set_manual_grade($bill_id, $manual_grade, $user_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'legiscan_bill_grades';

        if (empty($manual_grade) || !in_array(strtoupper($manual_grade), ['A', 'B', 'C', 'D', 'F'])) {
            $result = $wpdb->update(
                $table_name,
                [
                    'manual_grade' => null,
                    'manual_grade_user_id' => null,
                    'manual_grade_date' => null
                ],
                ['bill_id' => $bill_id],
                ['%s', '%s', '%s'],
                ['%s']
            );
        } else {
            $result = $wpdb->update(
                $table_name,
                [
                    'manual_grade' => strtoupper($manual_grade),
                    'manual_grade_user_id' => $user_id ?: get_current_user_id(),
                    'manual_grade_date' => current_time('mysql')
                ],
                ['bill_id' => $bill_id],
                ['%s', '%d', '%s'],
                ['%s']
            );
        }

        return $result !== false;
    }

    /**
     * Grade multiple bills in batch
     */
    public function grade_bills_batch($bills, $store_in_db = true, $use_option_2 = null) {
        $results = [];
        $grade_counts = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0];
        $total_score = 0;

        foreach ($bills as $bill_data) {
            $grade_result = $this->grade_bill($bill_data, $store_in_db, $use_option_2);
            $results[] = [
                'bill' => isset($bill_data['bill']) ? $bill_data['bill'] : $bill_data,
                'grade' => $grade_result
            ];

            $grade_counts[$grade_result['grade']]++;
            $total_score += $grade_result['score'];
        }

        $average_score = count($bills) > 0 ? $total_score / count($bills) : 0;

        return [
            'individual_grades' => $results,
            'statistics' => [
                'total_bills' => count($bills),
                'average_score' => round($average_score, 1),
                'average_grade' => $this->calculate_letter_grade($average_score),
                'grade_distribution' => $grade_counts,
                'methodology_used' => $use_option_2 ? 'option_2_census_based' : 'original'
            ]
        ];
    }

    /**
     * Get/Set grading methodology
     */
    public function get_grading_methodology() {
        return get_option('legiscan_grading_methodology', 'original');
    }

    public function set_grading_methodology($methodology) {
        $valid = ['original', 'option_2_census_based'];
        if (in_array($methodology, $valid)) {
            update_option('legiscan_grading_methodology', $methodology);
            return true;
        }
        return false;
    }

    /**
     * Get/Update grading weights
     */
    public function get_grading_weights() {
        return get_option('legiscan_grading_weights', [
            'keywords' => 40,
            'status' => 20,
            'sponsors' => 15,
            'committees' => 15,
            'votes' => 10
        ]);
    }

    public function update_grading_weights($weights) {
        if (is_array($weights) && array_sum($weights) == 100) {
            update_option('legiscan_grading_weights', $weights);
            return true;
        }
        return false;
    }

    /**
     * Get/Update keyword criteria
     */
    public function get_keyword_criteria() {
        return [
            'positive' => $this->positive_keywords,
            'negative' => $this->negative_keywords
        ];
    }

    public function update_keyword_criteria($positive_keywords, $negative_keywords) {
        if (is_array($positive_keywords)) {
            update_option('grading_criteria_positive_keywords', $positive_keywords);
            $this->positive_keywords = $positive_keywords;
        }
        if (is_array($negative_keywords)) {
            update_option('grading_criteria_negative_keywords', $negative_keywords);
            $this->negative_keywords = $negative_keywords;
        }
        return true;
    }

    /**
     * Debug database connection
     */
    public function debug_database_connection() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'legiscan_bill_grades';

        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        $table_structure = $table_exists ? $wpdb->get_results("DESCRIBE $table_name", ARRAY_A) : null;
        $record_count = $table_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $table_name") : 0;

        return [
            'table_name' => $table_name,
            'table_exists' => $table_exists,
            'record_count' => intval($record_count),
            'table_structure' => $table_structure,
            'wpdb_prefix' => $wpdb->prefix,
            'last_error' => $wpdb->last_error
        ];
    }

    /**
     * Re-grade all bills with current methodology
     */
    public function regrade_all_bills($methodology = null) {
        if ($methodology) {
            $this->set_grading_methodology($methodology);
        }

        $all_grades = $this->get_all_stored_grades();
        $regraded_count = 0;
        $errors = [];

        foreach ($all_grades as $grade_record) {
            $bill_data = [
                'bill_id' => $grade_record['bill_id'],
                'state_code' => $grade_record['state_code'],
                'bill_number' => $grade_record['bill_number'],
                'title' => $grade_record['title']
            ];

            try {
                $this->grade_bill($bill_data, true);
                $regraded_count++;
            } catch (Exception $e) {
                $errors[] = [
                    'bill_id' => $grade_record['bill_id'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'regraded_count' => $regraded_count,
            'total_bills' => count($all_grades),
            'errors' => $errors,
            'methodology_used' => $this->get_grading_methodology()
        ];
    }
}
