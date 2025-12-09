<?php
/**
 * AIWU Analytics Calculator - MVP
 *
 * Simple data aggregation for dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIWU_Analytics_Calculator {

    private $db;

    public function __construct() {
        $this->db = new AIWU_Analytics_Database();
    }

    /**
     * Get all dashboard data - MVP version
     */
    public function get_dashboard_data($date_from = '', $date_to = '') {
        // Default to last 30 days
        if (empty($date_from) || empty($date_to)) {
            $date_to = date('Y-m-d 23:59:59');
            $date_from = date('Y-m-d 00:00:00', strtotime('-30 days'));
        } else {
            $date_from = $date_from . ' 00:00:00';
            $date_to = $date_to . ' 23:59:59';
        }

        return array(
            'kpi' => array(
                'new_free_installations' => $this->db->get_new_free_installations($date_from, $date_to),
                'active_free_users' => $this->db->get_active_free_only_users($date_from, $date_to),
                'active_pro_users' => $this->db->get_active_pro_users($date_from, $date_to)
            ),
            'trends' => array(
                'free_installations' => $this->db->get_free_installations_timeline($date_from, $date_to),
                'pro_installations' => $this->db->get_pro_installations_timeline($date_from, $date_to)
            ),
            'features' => array(
                'by_users' => $this->db->get_features_by_users(),
                'by_tokens' => $this->db->get_features_by_tokens(),
                'by_api_provider' => $this->db->get_api_providers()
            ),
            'deactivations' => array(
                'reasons' => $this->db->get_deactivation_reasons(),
                'timeline' => $this->db->get_deactivations_timeline($date_from, $date_to)
            ),
            'filters' => array(
                'date_from' => substr($date_from, 0, 10),
                'date_to' => substr($date_to, 0, 10)
            )
        );
    }
}
