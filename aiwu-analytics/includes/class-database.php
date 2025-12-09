<?php
/**
 * AIWU Analytics Database Class
 * 
 * Handles all database queries for analytics
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AIWU_Analytics_Database {
    
    /**
     * WordPress database object
     */
    private $wpdb;
    
    /**
     * Table names
     */
    private $stats_table;
    private $details_table;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->stats_table = $wpdb->prefix . 'lms_stats';
        $this->details_table = $wpdb->prefix . 'lms_stats_details';
    }
    
    /**
     * Get total installations (unique activations)
     */
    public function get_total_installations($date_from = '', $date_to = '') {
        $where = "WHERE mode = 1"; // mode=1 is activation
        
        if ($date_from && $date_to) {
            $where .= $this->wpdb->prepare(" AND created BETWEEN %s AND %s", $date_from, $date_to);
        }
        
        $query = "SELECT COUNT(DISTINCT email) as total FROM {$this->stats_table} {$where}";
        return (int) $this->wpdb->get_var($query);
    }
    
    /**
     * Get active users (users with mode=0 stats in period)
     * FIXED: Active = sent stats in period AND activated AND not deactivated after last activation
     */
    public function get_active_users($date_from = '', $date_to = '') {
        if (empty($date_from) || empty($date_to)) {
            // If no date range, count all users who ever sent stats and are still active
            $query = "SELECT COUNT(DISTINCT s.email)
                      FROM {$this->stats_table} s
                      WHERE s.mode = 0
                      AND EXISTS (
                          SELECT 1 FROM {$this->stats_table} s2
                          WHERE s2.email = s.email AND s2.mode = 1
                      )
                      AND NOT EXISTS (
                          SELECT 1 FROM {$this->stats_table} s3
                          WHERE s3.email = s.email
                          AND s3.mode = 2
                          AND s3.created > (
                              SELECT MAX(created) FROM {$this->stats_table} s4
                              WHERE s4.email = s.email AND s4.mode = 1
                          )
                      )";
            return (int) $this->wpdb->get_var($query);
        }

        // Active users in period = sent stats in period AND not deactivated
        $query = $this->wpdb->prepare(
            "SELECT COUNT(DISTINCT s.email) as total
             FROM {$this->stats_table} s
             WHERE s.mode = 0
             AND s.created BETWEEN %s AND %s
             AND EXISTS (
                 SELECT 1 FROM {$this->stats_table} s2
                 WHERE s2.email = s.email AND s2.mode = 1
             )
             AND NOT EXISTS (
                 SELECT 1 FROM {$this->stats_table} s3
                 WHERE s3.email = s.email
                 AND s3.mode = 2
                 AND s3.created > (
                     SELECT MAX(created) FROM {$this->stats_table} s4
                     WHERE s4.email = s.email AND s4.mode = 1
                 )
             )",
            $date_from, $date_to
        );

        return (int) $this->wpdb->get_var($query);
    }
    
    /**
     * Get conversion rate Free->Pro
     * FIXED: Proper conversion = conversions in period / free users at start of period
     */
    public function get_conversion_data($date_from = '', $date_to = '') {
        if (empty($date_from) || empty($date_to)) {
            return array(
                'free_users_at_start' => 0,
                'conversions' => 0,
                'conversion_rate' => 0
            );
        }

        // Free users at start of period = activated as Free before period AND never converted to Pro before period
        $free_users_at_start = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT email) FROM {$this->stats_table}
             WHERE email IN (
                 SELECT email FROM {$this->stats_table} WHERE mode = 1 AND is_pro = 0 AND created < %s
             )
             AND email NOT IN (
                 SELECT email FROM {$this->stats_table} WHERE is_pro = 1 AND created < %s
             )",
            $date_from, $date_from
        ));

        // Conversions in period = first appearance of is_pro=1 in period (after having is_pro=0 before)
        $conversions = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT s1.email)
             FROM {$this->stats_table} s1
             WHERE s1.is_pro = 1
             AND s1.created BETWEEN %s AND %s
             AND EXISTS (
                 SELECT 1 FROM {$this->stats_table} s2
                 WHERE s2.email = s1.email
                 AND s2.is_pro = 0
                 AND s2.created < s1.created
             )
             AND NOT EXISTS (
                 SELECT 1 FROM {$this->stats_table} s3
                 WHERE s3.email = s1.email
                 AND s3.is_pro = 1
                 AND s3.created < %s
             )",
            $date_from, $date_to, $date_from
        ));

        $conversion_rate = $free_users_at_start > 0 ? ($conversions / $free_users_at_start) * 100 : 0;

        // Validate: conversion rate should never exceed 100%
        if ($conversion_rate > 100) {
            error_log('AIWU Analytics: Invalid conversion rate calculated: ' . $conversion_rate);
            $conversion_rate = 100;
        }

        return array(
            'free_users_at_start' => $free_users_at_start,
            'conversions' => $conversions,
            'conversion_rate' => round($conversion_rate, 2)
        );
    }
    
    /**
     * Get churn rate
     * FIXED: Proper churn calculation = deactivations in period / active users at start of period
     */
    public function get_churn_rate($date_from = '', $date_to = '') {
        if (empty($date_from) || empty($date_to)) {
            return array(
                'active_at_start' => 0,
                'deactivations' => 0,
                'churn_rate' => 0
            );
        }

        // Active users at start of period = activated before period start AND not deactivated before period start
        $active_at_start = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT email) FROM {$this->stats_table}
             WHERE email IN (
                 SELECT email FROM {$this->stats_table} WHERE mode = 1 AND created < %s
             )
             AND email NOT IN (
                 SELECT email FROM {$this->stats_table} WHERE mode = 2 AND created < %s
             )",
            $date_from, $date_from
        ));

        // Deactivations in period
        $deactivations = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT email) FROM {$this->stats_table}
             WHERE mode = 2 AND created BETWEEN %s AND %s",
            $date_from, $date_to
        ));

        $churn_rate = $active_at_start > 0 ? ($deactivations / $active_at_start) * 100 : 0;

        // Validate: churn rate should never exceed 100%
        if ($churn_rate > 100) {
            error_log('AIWU Analytics: Invalid churn rate calculated: ' . $churn_rate);
            $churn_rate = 100;
        }

        return array(
            'active_at_start' => $active_at_start,
            'deactivations' => $deactivations,
            'churn_rate' => round($churn_rate, 2)
        );
    }
    
    /**
     * Get activations timeline
     */
    public function get_activations_timeline($date_from, $date_to) {
        $query = $this->wpdb->prepare(
            "SELECT DATE(created) as date, 
                    SUM(CASE WHEN is_pro = 0 THEN 1 ELSE 0 END) as free_activations,
                    SUM(CASE WHEN is_pro = 1 THEN 1 ELSE 0 END) as pro_activations,
                    COUNT(*) as total
             FROM {$this->stats_table}
             WHERE mode = 1 
             AND created BETWEEN %s AND %s
             GROUP BY DATE(created)
             ORDER BY date ASC",
            $date_from,
            $date_to
        );
        
        return $this->wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get conversion timeline
     * NOTE: This counts ALL is_pro=1 records in period (not just first conversion)
     * Known limitation: May count same user multiple times if they send multiple stats
     */
    public function get_conversion_timeline($date_from, $date_to) {
        $query = $this->wpdb->prepare(
            "SELECT DATE(s1.created) as date, COUNT(DISTINCT s1.email) as conversions
             FROM {$this->stats_table} s1
             WHERE s1.is_pro = 1
             AND s1.created BETWEEN %s AND %s
             AND EXISTS (
                 SELECT 1 FROM {$this->stats_table} s2
                 WHERE s2.email = s1.email
                 AND s2.is_pro = 0
                 AND s2.created < s1.created
             )
             GROUP BY DATE(s1.created)
             ORDER BY date ASC",
            $date_from,
            $date_to
        );

        return $this->wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get time to conversion distribution
     * FIXED: Find FIRST is_pro=1 record, not any mode=0 or mode=1 record
     */
    public function get_time_to_conversion() {
        $query = "SELECT
                    s1.email,
                    MIN(s1.created) as free_date,
                    (SELECT MIN(created) FROM {$this->stats_table}
                     WHERE email = s1.email AND is_pro = 1) as pro_date,
                    DATEDIFF(
                        (SELECT MIN(created) FROM {$this->stats_table}
                         WHERE email = s1.email AND is_pro = 1),
                        MIN(s1.created)
                    ) as days_to_convert
                  FROM {$this->stats_table} s1
                  WHERE s1.is_pro = 0 AND s1.mode = 1
                  AND EXISTS (
                      SELECT 1 FROM {$this->stats_table}
                      WHERE email = s1.email AND is_pro = 1
                  )
                  GROUP BY s1.email
                  HAVING days_to_convert >= 0";

        $results = $this->wpdb->get_results($query, ARRAY_A);

        // Group into buckets
        $buckets = array(
            '0-1' => 0,
            '1-3' => 0,
            '3-7' => 0,
            '7-14' => 0,
            '14-30' => 0,
            '30+' => 0
        );

        foreach ($results as $row) {
            $days = (int) $row['days_to_convert'];

            if ($days <= 1) {
                $buckets['0-1']++;
            } elseif ($days <= 3) {
                $buckets['1-3']++;
            } elseif ($days <= 7) {
                $buckets['3-7']++;
            } elseif ($days <= 14) {
                $buckets['7-14']++;
            } elseif ($days <= 30) {
                $buckets['14-30']++;
            } else {
                $buckets['30+']++;
            }
        }

        return $buckets;
    }
    
    /**
     * Get recent conversions with details
     * FIXED: Find FIRST is_pro=1 record, not any mode=0 or mode=1 record
     */
    public function get_recent_conversions($limit = 20) {
        $query = $this->wpdb->prepare(
            "SELECT
                s1.email,
                MIN(s1.created) as free_date,
                (SELECT MIN(created) FROM {$this->stats_table}
                 WHERE email = s1.email AND is_pro = 1) as pro_date,
                DATEDIFF(
                    (SELECT MIN(created) FROM {$this->stats_table}
                     WHERE email = s1.email AND is_pro = 1),
                    MIN(s1.created)
                ) as days_to_convert,
                (SELECT id FROM {$this->stats_table}
                 WHERE email = s1.email AND is_pro = 1
                 ORDER BY created ASC LIMIT 1) as stats_id
             FROM {$this->stats_table} s1
             WHERE s1.is_pro = 0 AND s1.mode = 1
             AND EXISTS (
                 SELECT 1 FROM {$this->stats_table}
                 WHERE email = s1.email AND is_pro = 1
             )
             GROUP BY s1.email
             ORDER BY pro_date DESC
             LIMIT %d",
            $limit
        );

        $conversions = $this->wpdb->get_results($query, ARRAY_A);

        // Get details for each conversion
        foreach ($conversions as &$conversion) {
            $conversion['details'] = $this->get_user_stats_at_conversion($conversion['stats_id']);
        }

        return $conversions;
    }
    
    /**
     * Get user stats at specific stats_id
     */
    private function get_user_stats_at_conversion($stats_id) {
        $query = $this->wpdb->prepare(
            "SELECT name, val_int 
             FROM {$this->details_table} 
             WHERE st_id = %d 
             AND (name LIKE 'tokens_%' OR name = 'cnt_tasks')",
            $stats_id
        );
        
        $results = $this->wpdb->get_results($query, ARRAY_A);
        
        $data = array(
            'cnt_tasks' => 0,
            'tokens_chatbots' => 0,
            'tokens_postscreate' => 0,
            'tokens_workflow' => 0,
            'tokens_total' => 0
        );
        
        foreach ($results as $row) {
            if ($row['name'] === 'cnt_tasks') {
                $data['cnt_tasks'] = (int) $row['val_int'];
            } elseif (strpos($row['name'], 'tokens_') === 0) {
                $data[$row['name']] = (int) $row['val_int'];
                $data['tokens_total'] += (int) $row['val_int'];
            }
        }
        
        return $data;
    }
    
    /**
     * Get feature usage statistics
     * FIXED: Count unique emails instead of st_id (records)
     */
    public function get_feature_usage() {
        $features = array(
            'tokens_postscreate' => 'Bulk Content',
            'tokens_chatbots' => 'Chatbot',
            'tokens_workflow' => 'Workflow Builder',
            'tokens_magictext' => 'Magic Text',
            'tokens_postsrss' => 'Posts RSS',
            'tokens_training' => 'Training',
            'tokens_postsfields' => 'Post Fields',
            'tokens_postslinks' => 'Posts Links',
            'tokens_forms' => 'Forms',
            'tokens_postsaskai' => 'Ask AI',
            'tokens_productsfields' => 'Product Fields'
        );

        $results = array();

        foreach ($features as $token_name => $feature_name) {
            $query = $this->wpdb->prepare(
                "SELECT
                    COUNT(DISTINCT s.email) as user_count,
                    SUM(d.val_int) as total_tokens
                 FROM {$this->details_table} d
                 JOIN {$this->stats_table} s ON d.st_id = s.id
                 WHERE d.name = %s AND d.val_int > 0",
                $token_name
            );

            $data = $this->wpdb->get_row($query, ARRAY_A);

            // Handle NULL result (no data for this feature)
            $user_count = $data ? (int) $data['user_count'] : 0;
            $total_tokens = $data ? (int) $data['total_tokens'] : 0;

            $results[] = array(
                'feature' => $feature_name,
                'token_name' => $token_name,
                'user_count' => $user_count,
                'total_tokens' => $total_tokens
            );
        }

        // Sort by user count descending
        usort($results, function($a, $b) {
            return $b['user_count'] - $a['user_count'];
        });

        return $results;
    }
    
    /**
     * Get feature conversion rates
     * FIXED: Count unique emails instead of st_id (records)
     */
    public function get_feature_conversion_rates() {
        $features = array(
            'tokens_chatbots' => 'Chatbot',
            'tokens_postscreate' => 'Bulk Content',
            'tokens_workflow' => 'Workflow Builder'
        );

        $results = array();

        foreach ($features as $token_name => $feature_name) {
            // Unique users who used this feature
            $total_users = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(DISTINCT s.email)
                 FROM {$this->details_table} d
                 JOIN {$this->stats_table} s ON d.st_id = s.id
                 WHERE d.name = %s AND d.val_int > 0",
                $token_name
            ));

            // Unique users who used this feature AND converted to Pro
            $converted_users = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(DISTINCT s.email)
                 FROM {$this->details_table} d
                 JOIN {$this->stats_table} s ON d.st_id = s.id
                 WHERE d.name = %s
                 AND d.val_int > 0
                 AND EXISTS (
                     SELECT 1 FROM {$this->stats_table} s2
                     WHERE s2.email = s.email AND s2.is_pro = 1
                 )",
                $token_name
            ));

            $conversion_rate = $total_users > 0 ? ($converted_users / $total_users) * 100 : 0;

            $results[] = array(
                'feature' => $feature_name,
                'total_users' => (int) $total_users,
                'converted_users' => (int) $converted_users,
                'conversion_rate' => round($conversion_rate, 2)
            );
        }

        return $results;
    }
    
    /**
     * Get deactivation reasons
     */
    public function get_deactivation_reasons() {
        // Group only by reason code to avoid duplicates
        $query = "SELECT
                    d.val_int as reason_code,
                    COUNT(*) as count
                  FROM {$this->details_table} d
                  WHERE d.name = 'reason'
                  GROUP BY d.val_int
                  ORDER BY count DESC";

        $results = $this->wpdb->get_results($query, ARRAY_A);

        // Map reason codes to text
        $reason_map = array(
            -1 => 'Not specified',
            0 => 'Requires third-party APIs',
            1 => 'Difficult to use',
            2 => 'Lacking necessary features',
            3 => 'Current features are not good enough',
            4 => 'Missing features in the free version',
            5 => 'Other'
        );

        $formatted = array();
        foreach ($results as $row) {
            $code = (int) $row['reason_code'];
            $formatted[] = array(
                'reason' => isset($reason_map[$code]) ? $reason_map[$code] : 'Unknown',
                'count' => (int) $row['count']
            );
        }

        return $formatted;
    }
    
    /**
     * Get churn timeline
     */
    public function get_churn_timeline($date_from, $date_to) {
        $query = $this->wpdb->prepare(
            "SELECT DATE(created) as date, COUNT(*) as deactivations
             FROM {$this->stats_table}
             WHERE mode = 2
             AND created BETWEEN %s AND %s
             GROUP BY DATE(created)
             ORDER BY date ASC",
            $date_from,
            $date_to
        );
        
        return $this->wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get API provider distribution
     * FIXED: Count unique emails instead of st_id (records)
     */
    public function get_api_provider_distribution() {
        $providers = array(
            'apikey' => 'OpenAI',
            'gemini_api_key' => 'Gemini',
            'deep_seek_apikey' => 'DeepSeek'
        );

        $results = array();

        foreach ($providers as $key_name => $provider_name) {
            $count = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(DISTINCT s.email)
                 FROM {$this->details_table} d
                 JOIN {$this->stats_table} s ON d.st_id = s.id
                 WHERE d.name = %s AND d.val_int > 0",
                $key_name
            ));

            $results[] = array(
                'provider' => $provider_name,
                'count' => (int) $count
            );
        }

        return $results;
    }
    
    /**
     * Get detailed user activity
     * FIXED: Get plan from latest record, not MAX which always returns 'Pro' if user ever had Pro
     */
    public function get_user_activity($limit = 50, $offset = 0) {
        $query = $this->wpdb->prepare(
            "SELECT
                s.email,
                MIN(CASE WHEN s.mode = 1 THEN s.created END) as activated,
                (SELECT CASE WHEN is_pro = 1 THEN 'Pro' ELSE 'Free' END
                 FROM {$this->stats_table}
                 WHERE email = s.email
                 ORDER BY created DESC LIMIT 1) as plan,
                MAX(s.created) as last_activity
             FROM {$this->stats_table} s
             GROUP BY s.email
             ORDER BY last_activity DESC
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        );

        $users = $this->wpdb->get_results($query, ARRAY_A);

        // Get details for each user
        foreach ($users as &$user) {
            // Get latest stats
            $latest_stats = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT id FROM {$this->stats_table}
                 WHERE email = %s AND mode = 0
                 ORDER BY created DESC LIMIT 1",
                $user['email']
            ));

            if ($latest_stats) {
                $user['stats'] = $this->get_user_stats_at_conversion($latest_stats);
            } else {
                $user['stats'] = array(
                    'cnt_tasks' => 0,
                    'tokens_total' => 0
                );
            }
        }

        return $users;
    }
}
