<?php
/**
 * AIWU Analytics Dashboard Template - MVP
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap aiwu-dashboard">
    <h1>AIWU Analytics - MVP Dashboard</h1>

    <!-- Filters -->
    <div class="aiwu-filters">
        <div class="filter-group">
            <label for="date-from">From:</label>
            <input type="date" id="date-from" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
        </div>
        <div class="filter-group">
            <label for="date-to">To:</label>
            <input type="date" id="date-to" value="<?php echo date('Y-m-d'); ?>">
        </div>
        <button id="apply-filters" class="button button-primary">Apply</button>
    </div>

    <!-- Loading -->
    <div id="loading" class="aiwu-loading">Loading...</div>

    <!-- Dashboard Content -->
    <div id="dashboard-content" style="display: none;">

        <!-- KPI Cards -->
        <div class="aiwu-section">
            <h2>Summary</h2>
            <div class="kpi-cards">
                <div class="kpi-card">
                    <div class="kpi-label">New FREE Installations</div>
                    <div class="kpi-value" id="kpi-free-installations">-</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Active Free Users (no Pro)</div>
                    <div class="kpi-value" id="kpi-active-free">-</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Active Pro Users</div>
                    <div class="kpi-value" id="kpi-active-pro">-</div>
                </div>
            </div>
        </div>

        <!-- Installation Trends -->
        <div class="aiwu-section">
            <h2>Installation Trends</h2>
            <div class="charts-row">
                <div class="chart-container">
                    <h3>Free Installations</h3>
                    <canvas id="chart-free-installations"></canvas>
                </div>
                <div class="chart-container">
                    <h3>Pro Installations</h3>
                    <canvas id="chart-pro-installations"></canvas>
                </div>
            </div>
        </div>

        <!-- Feature Popularity -->
        <div class="aiwu-section">
            <h2>Feature Popularity</h2>
            <div class="tables-row">
                <div class="table-container">
                    <h3>By Unique Users</h3>
                    <table class="wp-list-table widefat fixed striped" id="table-features-users">
                        <thead>
                            <tr>
                                <th>Feature</th>
                                <th>Users</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="table-container">
                    <h3>By Tokens</h3>
                    <table class="wp-list-table widefat fixed striped" id="table-features-tokens">
                        <thead>
                            <tr>
                                <th>Feature</th>
                                <th>Tokens</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="table-container">
                    <h3>By API Provider</h3>
                    <table class="wp-list-table widefat fixed striped" id="table-api-providers">
                        <thead>
                            <tr>
                                <th>Provider</th>
                                <th>Users</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Deactivation Reasons -->
        <div class="aiwu-section">
            <h2>Deactivation Reasons</h2>
            <table class="wp-list-table widefat fixed striped" id="table-deactivation-reasons">
                <thead>
                    <tr>
                        <th>Reason</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <!-- Deactivation Trend -->
        <div class="aiwu-section">
            <h2>Deactivation Trend</h2>
            <div class="chart-container-full">
                <canvas id="chart-deactivations"></canvas>
            </div>
        </div>

    </div>
</div>
