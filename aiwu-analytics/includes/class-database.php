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
     */
    public function get_active_users($date_from = '', $date_to = '') {
        $where = "WHERE mode = 0"; // mode=0 is stats ping
        
        if ($date_from && $date_to) {
            $where .= $this->wpdb->prepare(" AND created BETWEEN %s AND %s", $date_from, $date_to);
        }
        
        $query = "SELECT COUNT(DISTINCT email) as total FROM {$this->stats_table} {$where}";
        return (int) $this->wpdb->get_var($query);
    }
    
    /**
     * Get conversion rate Free->Pro
     */
    public function get_conversion_data($date_from = '', $date_to = '') {
        // Get all unique users who activated as Free
        $free_users_query = "SELECT COUNT(DISTINCT email) as total 
                            FROM {$this->stats_table} 
                            WHERE mode = 1 AND is_pro = 0";
        
        if ($date_from && $date_to) {
            $free_users_query .= $this->wpdb->prepare(" AND created BETWEEN %s AND %s", $date_from, $date_to);
        }
        
        $free_users = (int) $this->wpdb->get_var($free_users_query);
        
        // Get users who converted to Pro
        $pro_query = "SELECT COUNT(DISTINCT s1.email) as total
                     FROM {$this->stats_table} s1
                     WHERE s1.is_pro = 1 
                     AND EXISTS (
                         SELECT 1 FROM {$this->stats_table} s2 
                         WHERE s2.email = s1.email 
                         AND s2.is_pro = 0 
                         AND s2.created < s1.created
                     )";
        
        if ($date_from && $date_to) {
            $pro_query .= $this->wpdb->prepare(" AND s1.created BETWEEN %s AND %s", $date_from, $date_to);
        }
        
        $pro_users = (int) $this->wpdb->get_var($pro_query);
        
        $conversion_rate = $free_users > 0 ? ($pro_users / $free_users) * 100 : 0;
        
        return array(
            'free_users' => $free_users,
            'pro_users' => $pro_users,
            'conversion_rate' => round($conversion_rate, 2)
        );
    }
    
    /**
     * Get churn rate
     */
    public function get_churn_rate($date_from = '', $date_to = '') {
        // Total installations
        $total = $this->get_total_installations($date_from, $date_to);
        
        // Deactivations
        $where = "WHERE mode = 2"; // mode=2 is deactivation
        
        if ($date_from && $date_to) {
            $where .= $this->wpdb->prepare(" AND created BETWEEN %s AND %s", $date_from, $date_to);
        }
        
        $deactivations = (int) $this->wpdb->get_var("SELECT COUNT(DISTINCT email) FROM {$this->stats_table} {$where}");
        
        $churn_rate = $total > 0 ? ($deactivations / $total) * 100 : 0;
        
        return array(
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
     */
    public function get_time_to_conversion() {
        $query = "SELECT 
                    s1.email,
                    MIN(s1.created) as free_date,
                    MIN(s2.created) as pro_date,
                    DATEDIFF(MIN(s2.created), MIN(s1.created)) as days_to_convert
                  FROM {$this->stats_table} s1
                  JOIN {$this->stats_table} s2 ON s1.email = s2.email
                  WHERE s1.is_pro = 0 AND s1.mode = 1
                  AND s2.is_pro = 1 AND s2.mode IN (0,1)
                  AND s2.created > s1.created
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
     */
    public function get_recent_conversions($limit = 20) {
        $query = $this->wpdb->prepare(
            "SELECT 
                s1.email,
                s1.created as free_date,
                s2.created as pro_date,
                DATEDIFF(s2.created, s1.created) as days_to_convert,
                s2.id as stats_id
             FROM {$this->stats_table} s1
             JOIN {$this->stats_table} s2 ON s1.email = s2.email
             WHERE s1.is_pro = 0 AND s1.mode = 1
             AND s2.is_pro = 1 AND s2.mode IN (0,1)
             AND s2.created > s1.created
             GROUP BY s1.email
             ORDER BY s2.created DESC
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
                    COUNT(DISTINCT st_id) as user_count,
                    SUM(val_int) as total_tokens
                 FROM {$this->details_table}
                 WHERE name = %s AND val_int > 0",
                $token_name
            );
            
            $data = $this->wpdb->get_row($query, ARRAY_A);
            
            $results[] = array(
                'feature' => $feature_name,
                'token_name' => $token_name,
                'user_count' => (int) $data['user_count'],
                'total_tokens' => (int) $data['total_tokens']
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
     */
    public function get_feature_conversion_rates() {
        $features = array(
            'tokens_chatbots' => 'Chatbot',
            'tokens_postscreate' => 'Bulk Content',
            'tokens_workflow' => 'Workflow Builder'
        );
        
        $results = array();
        
        foreach ($features as $token_name => $feature_name) {
            // Users who used this feature
            $total_users = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(DISTINCT st_id) 
                 FROM {$this->details_table} 
                 WHERE name = %s AND val_int > 0",
                $token_name
            ));
            
            // Users who used this feature AND converted to Pro
            $converted_users = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(DISTINCT d.st_id)
                 FROM {$this->details_table} d
                 JOIN {$this->stats_table} s ON d.st_id = s.id
                 WHERE d.name = %s 
                 AND d.val_int > 0
                 AND s.is_pro = 1",
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
        $query = "SELECT 
                    d.val_int as reason_code,
                    d.val_text as reason_text,
                    COUNT(*) as count
                  FROM {$this->details_table} d
                  WHERE d.name = 'reason'
                  GROUP BY d.val_int, d.val_text
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
                'reason' => isset($reason_map[$code]) ? $reason_map[$code] : $row['reason_text'],
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
                "SELECT COUNT(DISTINCT st_id) 
                 FROM {$this->details_table} 
                 WHERE name = %s AND val_int > 0",
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
     */
    public function get_user_activity($limit = 50, $offset = 0) {
        $query = $this->wpdb->prepare(
            "SELECT 
                s.email,
                MIN(CASE WHEN s.mode = 1 THEN s.created END) as activated,
                MAX(CASE WHEN s.is_pro = 1 THEN 'Pro' ELSE 'Free' END) as plan,
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
