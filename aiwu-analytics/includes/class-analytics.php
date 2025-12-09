<?php
/**
 * AIWU Analytics Calculator Class
 * 
 * Processes data from database and prepares it for dashboard display
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AIWU_Analytics_Calculator {
    
    /**
     * Database instance
     */
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new AIWU_Analytics_Database();
    }
    
    /**
     * Get all dashboard data
     */
    public function get_dashboard_data($date_from = '', $date_to = '', $plan = 'all', $feature = 'all') {
        // If no dates provided, default to last 30 days
        if (empty($date_from) || empty($date_to)) {
            $date_to = date('Y-m-d');
            $date_from = date('Y-m-d', strtotime('-30 days'));
        }
        
        return array(
            'kpi' => $this->get_kpi_metrics($date_from, $date_to),
            'conversion' => $this->get_conversion_metrics($date_from, $date_to),
            'features' => $this->get_feature_metrics(),
            'churn' => $this->get_churn_metrics($date_from, $date_to),
            'engagement' => $this->get_engagement_metrics($date_from, $date_to),
            'users' => $this->get_user_activity_data(),
            'filters' => array(
                'date_from' => $date_from,
                'date_to' => $date_to,
                'plan' => $plan,
                'feature' => $feature
            )
        );
    }
    
    /**
     * Get KPI metrics for top cards
     */
    private function get_kpi_metrics($date_from, $date_to) {
        // Get current period data
        $current_installations = $this->db->get_total_installations($date_from, $date_to);
        $current_active_free = $this->db->get_active_free_users($date_from, $date_to);
        $current_active_pro = $this->db->get_active_pro_users($date_from, $date_to);
        $current_conversion = $this->db->get_conversion_data($date_from, $date_to);
        $current_churn = $this->db->get_churn_rate($date_from, $date_to);

        // Calculate previous period for comparison
        $days_diff = (strtotime($date_to) - strtotime($date_from)) / 86400;
        $prev_date_to = date('Y-m-d', strtotime($date_from . ' -1 day'));
        $prev_date_from = date('Y-m-d', strtotime($prev_date_to . ' -' . $days_diff . ' days'));

        $prev_installations = $this->db->get_total_installations($prev_date_from, $prev_date_to);
        $prev_active_free = $this->db->get_active_free_users($prev_date_from, $prev_date_to);
        $prev_active_pro = $this->db->get_active_pro_users($prev_date_from, $prev_date_to);
        $prev_conversion = $this->db->get_conversion_data($prev_date_from, $prev_date_to);
        $prev_churn = $this->db->get_churn_rate($prev_date_from, $prev_date_to);

        return array(
            'installations' => array(
                'value' => $current_installations,
                'change' => $this->calculate_percentage_change($prev_installations, $current_installations),
                'trend' => $this->generate_mini_trend($date_from, $date_to, 'installations')
            ),
            'conversion_rate' => array(
                'value' => $current_conversion['conversion_rate'],
                'change' => $this->calculate_percentage_change($prev_conversion['conversion_rate'], $current_conversion['conversion_rate']),
                'trend' => $this->generate_mini_trend($date_from, $date_to, 'conversion')
            ),
            'active_free_users' => array(
                'value' => $current_active_free,
                'change' => $this->calculate_percentage_change($prev_active_free, $current_active_free),
                'trend' => $this->generate_mini_trend($date_from, $date_to, 'active_free')
            ),
            'active_pro_users' => array(
                'value' => $current_active_pro,
                'change' => $this->calculate_percentage_change($prev_active_pro, $current_active_pro),
                'trend' => $this->generate_mini_trend($date_from, $date_to, 'active_pro')
            ),
            'churn_rate' => array(
                'value' => $current_churn['churn_rate'],
                'change' => $this->calculate_percentage_change($prev_churn['churn_rate'], $current_churn['churn_rate']),
                'trend' => $this->generate_mini_trend($date_from, $date_to, 'churn')
            )
        );
    }
    
    /**
     * Calculate percentage change
     */
    private function calculate_percentage_change($old_value, $new_value) {
        if ($old_value == 0) {
            return $new_value > 0 ? 100 : 0;
        }
        
        $change = (($new_value - $old_value) / $old_value) * 100;
        return round($change, 1);
    }
    
    /**
     * Generate mini trend data for sparklines
     */
    private function generate_mini_trend($date_from, $date_to, $type) {
        // Get daily data for the period
        $days = (strtotime($date_to) - strtotime($date_from)) / 86400;
        
        if ($days > 30) {
            // Weekly data if more than 30 days
            $interval = 7;
        } else {
            // Daily data
            $interval = 1;
        }
        
        $trend = array();
        $current_date = $date_from;
        
        while (strtotime($current_date) <= strtotime($date_to)) {
            $next_date = date('Y-m-d', strtotime($current_date . ' +' . $interval . ' days'));
            
            switch ($type) {
                case 'installations':
                    $value = $this->db->get_total_installations($current_date, $next_date);
                    break;
                case 'active':
                    $value = $this->db->get_active_users($current_date, $next_date);
                    break;
                case 'conversion':
                    $data = $this->db->get_conversion_data($current_date, $next_date);
                    $value = $data['conversion_rate'];
                    break;
                case 'churn':
                    $data = $this->db->get_churn_rate($current_date, $next_date);
                    $value = $data['churn_rate'];
                    break;
                default:
                    $value = 0;
            }
            
            $trend[] = $value;
            $current_date = $next_date;
        }
        
        return $trend;
    }
    
    /**
     * Get conversion metrics
     */
    private function get_conversion_metrics($date_from, $date_to) {
        return array(
            'timeline' => array(
                'activations' => $this->db->get_activations_timeline($date_from, $date_to),
                'conversions' => $this->db->get_conversion_timeline($date_from, $date_to)
            ),
            'time_to_convert' => $this->db->get_time_to_conversion(),
            'recent_conversions' => $this->format_recent_conversions($this->db->get_recent_conversions(20)),
            'by_feature' => $this->db->get_feature_conversion_rates()
        );
    }
    
    /**
     * Format recent conversions for display
     */
    private function format_recent_conversions($conversions) {
        $formatted = array();
        
        foreach ($conversions as $conversion) {
            $details = $conversion['details'];
            
            $features_used = array();
            if ($details['tokens_chatbots'] > 0) {
                $features_used[] = array('name' => 'Chatbot', 'tokens' => $details['tokens_chatbots']);
            }
            if ($details['tokens_postscreate'] > 0) {
                $features_used[] = array('name' => 'Bulk Content', 'tokens' => $details['tokens_postscreate']);
            }
            if ($details['tokens_workflow'] > 0) {
                $features_used[] = array('name' => 'Workflow', 'tokens' => $details['tokens_workflow']);
            }
            
            $formatted[] = array(
                'email' => $this->mask_email($conversion['email']),
                'days_to_convert' => (int) $conversion['days_to_convert'],
                'tasks_created' => $details['cnt_tasks'],
                'features' => $features_used,
                'total_tokens' => $details['tokens_total'],
                'pro_date' => date('M d, Y', strtotime($conversion['pro_date']))
            );
        }
        
        return $formatted;
    }
    
    /**
     * Mask email for privacy
     */
    private function mask_email($email) {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }
        
        $name = $parts[0];
        $domain = $parts[1];
        
        if (strlen($name) <= 3) {
            $masked_name = substr($name, 0, 1) . '***';
        } else {
            $masked_name = substr($name, 0, 3) . '***';
        }
        
        return $masked_name . '@' . $domain;
    }
    
    /**
     * Get feature metrics
     */
    private function get_feature_metrics() {
        $usage = $this->db->get_feature_usage();
        $conversion_rates = $this->db->get_feature_conversion_rates();
        
        // Merge data
        $features = array();
        foreach ($usage as $feature) {
            $conv_data = array_filter($conversion_rates, function($item) use ($feature) {
                return $item['feature'] === $feature['feature'];
            });
            
            $conv = !empty($conv_data) ? reset($conv_data) : array('conversion_rate' => 0);
            
            $features[] = array(
                'name' => $feature['feature'],
                'user_count' => $feature['user_count'],
                'total_tokens' => $feature['total_tokens'],
                'conversion_rate' => $conv['conversion_rate']
            );
        }
        
        return $features;
    }
    
    /**
     * Get churn metrics
     */
    private function get_churn_metrics($date_from, $date_to) {
        return array(
            'reasons' => $this->db->get_deactivation_reasons(),
            'timeline' => $this->db->get_churn_timeline($date_from, $date_to),
            'rate_by_plan' => $this->calculate_churn_by_plan($date_from, $date_to)
        );
    }
    
    /**
     * Calculate churn rate by plan
     * FIXED: Proper churn = deactivations in period / active users at start of period (by plan)
     */
    private function calculate_churn_by_plan($date_from, $date_to) {
        global $wpdb;
        $stats_table = $wpdb->prefix . 'lms_stats';

        // Free users active at start of period
        $free_active_at_start = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT email) FROM {$stats_table}
             WHERE email IN (
                 SELECT email FROM {$stats_table} WHERE mode = 1 AND is_pro = 0 AND created < %s
             )
             AND email NOT IN (
                 SELECT email FROM {$stats_table} WHERE mode = 2 AND created < %s
             )
             AND email NOT IN (
                 SELECT email FROM {$stats_table} WHERE is_pro = 1 AND created < %s
             )",
            $date_from, $date_from, $date_from
        ));

        $free_churned = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT email) FROM {$stats_table}
             WHERE mode = 2 AND is_pro = 0
             AND created BETWEEN %s AND %s",
            $date_from, $date_to
        ));

        // Pro users active at start of period
        $pro_active_at_start = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT email) FROM {$stats_table}
             WHERE email IN (
                 SELECT email FROM {$stats_table} WHERE is_pro = 1 AND created < %s
             )
             AND email NOT IN (
                 SELECT email FROM {$stats_table} WHERE mode = 2 AND created < %s
             )",
            $date_from, $date_from
        ));

        $pro_churned = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT email) FROM {$stats_table}
             WHERE mode = 2 AND is_pro = 1
             AND created BETWEEN %s AND %s",
            $date_from, $date_to
        ));

        $free_rate = $free_active_at_start > 0 ? ($free_churned / $free_active_at_start) * 100 : 0;
        $pro_rate = $pro_active_at_start > 0 ? ($pro_churned / $pro_active_at_start) * 100 : 0;

        // Validate rates
        if ($free_rate > 100) {
            error_log('AIWU Analytics: Invalid free churn rate: ' . $free_rate);
            $free_rate = 100;
        }
        if ($pro_rate > 100) {
            error_log('AIWU Analytics: Invalid pro churn rate: ' . $pro_rate);
            $pro_rate = 100;
        }

        return array(
            'free' => array(
                'active_at_start' => (int) $free_active_at_start,
                'churned' => (int) $free_churned,
                'rate' => round($free_rate, 2)
            ),
            'pro' => array(
                'active_at_start' => (int) $pro_active_at_start,
                'churned' => (int) $pro_churned,
                'rate' => round($pro_rate, 2)
            )
        );
    }
    
    /**
     * Get engagement metrics
     */
    private function get_engagement_metrics($date_from, $date_to) {
        return array(
            'api_providers' => $this->db->get_api_provider_distribution(),
            'user_segments' => $this->calculate_user_segments(),
            'multi_feature_usage' => $this->calculate_multi_feature_usage()
        );
    }
    
    /**
     * Calculate user segments by activity
     * FIXED: Group by email (unique users) instead of st_id (records)
     * Uses optimized query with INNER JOIN to get latest stats per user
     */
    private function calculate_user_segments() {
        global $wpdb;
        $stats_table = $wpdb->prefix . 'lms_stats';
        $details_table = $wpdb->prefix . 'lms_stats_details';

        // Get latest stats for each user with their total tokens
        // Using JOIN with subquery to get max id per user
        $query = "SELECT s.email, SUM(d.val_int) as total_tokens
                  FROM {$details_table} d
                  INNER JOIN {$stats_table} s ON d.st_id = s.id
                  INNER JOIN (
                      SELECT email, MAX(id) as max_id
                      FROM {$stats_table}
                      WHERE mode = 0
                      GROUP BY email
                  ) latest ON s.email = latest.email AND s.id = latest.max_id
                  WHERE d.name LIKE 'tokens_%'
                  GROUP BY s.email";

        $results = $wpdb->get_results($query, ARRAY_A);

        $segments = array(
            'dead' => 0,      // 0 tokens
            'light' => 0,     // 1-10K
            'medium' => 0,    // 10-100K
            'heavy' => 0      // 100K+
        );

        foreach ($results as $row) {
            $tokens = (int) $row['total_tokens'];

            if ($tokens === 0) {
                $segments['dead']++;
            } elseif ($tokens <= 10000) {
                $segments['light']++;
            } elseif ($tokens <= 100000) {
                $segments['medium']++;
            } else {
                $segments['heavy']++;
            }
        }

        $total = array_sum($segments);

        return array(
            'dead' => array(
                'count' => $segments['dead'],
                'percentage' => $total > 0 ? round(($segments['dead'] / $total) * 100, 1) : 0
            ),
            'light' => array(
                'count' => $segments['light'],
                'percentage' => $total > 0 ? round(($segments['light'] / $total) * 100, 1) : 0
            ),
            'medium' => array(
                'count' => $segments['medium'],
                'percentage' => $total > 0 ? round(($segments['medium'] / $total) * 100, 1) : 0
            ),
            'heavy' => array(
                'count' => $segments['heavy'],
                'percentage' => $total > 0 ? round(($segments['heavy'] / $total) * 100, 1) : 0
            )
        );
    }
    
    /**
     * Calculate multi-feature usage
     * FIXED: Group by email (unique users) instead of st_id (records)
     * Uses optimized query with INNER JOIN to get latest stats per user
     */
    private function calculate_multi_feature_usage() {
        global $wpdb;
        $stats_table = $wpdb->prefix . 'lms_stats';
        $details_table = $wpdb->prefix . 'lms_stats_details';

        // Count how many features each unique user uses (from their latest stats)
        // Using JOIN with subquery to get max id per user
        $query = "SELECT s.email, COUNT(DISTINCT d.name) as feature_count
                  FROM {$details_table} d
                  INNER JOIN {$stats_table} s ON d.st_id = s.id
                  INNER JOIN (
                      SELECT email, MAX(id) as max_id
                      FROM {$stats_table}
                      WHERE mode = 0
                      GROUP BY email
                  ) latest ON s.email = latest.email AND s.id = latest.max_id
                  WHERE d.name LIKE 'tokens_%' AND d.val_int > 0
                  GROUP BY s.email";

        $results = $wpdb->get_results($query, ARRAY_A);

        $distribution = array(
            '0' => 0,
            '1' => 0,
            '2' => 0,
            '3+' => 0
        );

        foreach ($results as $row) {
            $count = (int) $row['feature_count'];

            if ($count === 0) {
                $distribution['0']++;
            } elseif ($count === 1) {
                $distribution['1']++;
            } elseif ($count === 2) {
                $distribution['2']++;
            } else {
                $distribution['3+']++;
            }
        }

        $total = array_sum($distribution);

        $formatted = array();
        foreach ($distribution as $key => $value) {
            $formatted[] = array(
                'features' => $key,
                'count' => $value,
                'percentage' => $total > 0 ? round(($value / $total) * 100, 1) : 0
            );
        }

        return $formatted;
    }
    
    /**
     * Get user activity data for table
     */
    private function get_user_activity_data($limit = 50, $offset = 0) {
        $users = $this->db->get_user_activity($limit, $offset);
        
        $formatted = array();
        foreach ($users as $user) {
            $formatted[] = array(
                'email' => $this->mask_email($user['email']),
                'activated' => date('M d, Y', strtotime($user['activated'])),
                'plan' => $user['plan'],
                'tasks' => $user['stats']['cnt_tasks'],
                'total_tokens' => $this->format_number($user['stats']['tokens_total']),
                'last_activity' => $this->time_ago($user['last_activity'])
            );
        }
        
        return $formatted;
    }
    
    /**
     * Format large numbers
     */
    private function format_number($number) {
        if ($number >= 1000000000) {
            return round($number / 1000000000, 2) . 'B';
        } elseif ($number >= 1000000) {
            return round($number / 1000000, 2) . 'M';
        } elseif ($number >= 1000) {
            return round($number / 1000, 2) . 'K';
        }
        return $number;
    }
    
    /**
     * Convert timestamp to "time ago" format
     */
    private function time_ago($datetime) {
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return date('M d, Y', $timestamp);
        }
    }
}
