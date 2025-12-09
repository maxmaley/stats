<?php
/**
 * AIWU Analytics Database Class - MVP
 *
 * Simple and accurate queries for dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIWU_Analytics_Database {

    private $wpdb;
    private $stats_table;
    private $details_table;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->stats_table = $wpdb->prefix . 'lms_stats';
        $this->details_table = $wpdb->prefix . 'lms_stats_details';
    }

    /**
     * KPI 1: Количество новых установок FREE
     * mode=1 (activation) AND is_pro=0
     */
    public function get_new_free_installations($date_from, $date_to) {
        $query = $this->wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$this->stats_table}
             WHERE mode = 1
             AND is_pro = 0
             AND created BETWEEN %s AND %s",
            $date_from,
            $date_to
        );
        return (int) $this->wpdb->get_var($query);
    }

    /**
     * KPI 2: Активные уникальные Free (у которых НЕТ Pro)
     * Юзеры которые отправили stats И никогда не имели is_pro=1
     */
    public function get_active_free_only_users($date_from, $date_to) {
        $query = $this->wpdb->prepare(
            "SELECT COUNT(DISTINCT email)
             FROM {$this->stats_table}
             WHERE mode = 0
             AND created BETWEEN %s AND %s
             AND email NOT IN (
                 SELECT DISTINCT email
                 FROM {$this->stats_table}
                 WHERE is_pro = 1
             )",
            $date_from,
            $date_to
        );
        return (int) $this->wpdb->get_var($query);
    }

    /**
     * KPI 3: Активные уникальные Pro (у которых есть и Free и Pro)
     * Юзеры которые отправили stats И имели is_pro=1
     */
    public function get_active_pro_users($date_from, $date_to) {
        $query = $this->wpdb->prepare(
            "SELECT COUNT(DISTINCT email)
             FROM {$this->stats_table}
             WHERE mode = 0
             AND created BETWEEN %s AND %s
             AND email IN (
                 SELECT DISTINCT email
                 FROM {$this->stats_table}
                 WHERE is_pro = 1
             )",
            $date_from,
            $date_to
        );
        return (int) $this->wpdb->get_var($query);
    }

    /**
     * Тренд: Free установки по дням
     */
    public function get_free_installations_timeline($date_from, $date_to) {
        $query = $this->wpdb->prepare(
            "SELECT DATE(created) as date, COUNT(*) as count
             FROM {$this->stats_table}
             WHERE mode = 1
             AND is_pro = 0
             AND created BETWEEN %s AND %s
             GROUP BY DATE(created)
             ORDER BY date ASC",
            $date_from,
            $date_to
        );
        return $this->wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Тренд: Pro установки по дням
     */
    public function get_pro_installations_timeline($date_from, $date_to) {
        $query = $this->wpdb->prepare(
            "SELECT DATE(created) as date, COUNT(*) as count
             FROM {$this->stats_table}
             WHERE mode = 1
             AND is_pro = 1
             AND created BETWEEN %s AND %s
             GROUP BY DATE(created)
             ORDER BY date ASC",
            $date_from,
            $date_to
        );
        return $this->wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Фичи: по уникальным юзерам
     */
    public function get_features_by_users() {
        $features = array(
            'tokens_chatbots' => 'Chatbot',
            'tokens_postscreate' => 'Bulk Content',
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
            $count = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(DISTINCT s.email)
                 FROM {$this->details_table} d
                 JOIN {$this->stats_table} s ON d.st_id = s.id
                 WHERE d.name = %s AND d.val_int > 0",
                $token_name
            ));

            $results[] = array(
                'feature' => $feature_name,
                'count' => (int) $count
            );
        }

        usort($results, function($a, $b) {
            return $b['count'] - $a['count'];
        });

        return $results;
    }

    /**
     * Фичи: по токенам
     */
    public function get_features_by_tokens() {
        $features = array(
            'tokens_chatbots' => 'Chatbot',
            'tokens_postscreate' => 'Bulk Content',
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
            $tokens = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT SUM(val_int)
                 FROM {$this->details_table}
                 WHERE name = %s AND val_int > 0",
                $token_name
            ));

            $results[] = array(
                'feature' => $feature_name,
                'tokens' => (int) $tokens
            );
        }

        usort($results, function($a, $b) {
            return $b['tokens'] - $a['tokens'];
        });

        return $results;
    }

    /**
     * API провайдеры: по уникальным юзерам
     */
    public function get_api_providers() {
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

        usort($results, function($a, $b) {
            return $b['count'] - $a['count'];
        });

        return $results;
    }

    /**
     * Причины деактивации
     */
    public function get_deactivation_reasons() {
        $reason_map = array(
            -1 => 'Not specified',
            0 => 'Requires third-party APIs',
            1 => 'Difficult to use',
            2 => 'Lacking necessary features',
            3 => 'Current features not good enough',
            4 => 'Missing features in free version',
            5 => 'Other'
        );

        $query = "SELECT d.val_int as reason_code, COUNT(*) as count
                  FROM {$this->details_table} d
                  WHERE d.name = 'reason'
                  GROUP BY d.val_int
                  ORDER BY count DESC";

        $raw_results = $this->wpdb->get_results($query, ARRAY_A);

        $results = array();
        foreach ($raw_results as $row) {
            $code = (int) $row['reason_code'];
            $results[] = array(
                'reason' => isset($reason_map[$code]) ? $reason_map[$code] : 'Unknown',
                'count' => (int) $row['count']
            );
        }

        return $results;
    }

    /**
     * Тренд: деактивации по дням
     */
    public function get_deactivations_timeline($date_from, $date_to) {
        $query = $this->wpdb->prepare(
            "SELECT DATE(created) as date, COUNT(*) as count
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
}
