<?php
/**
 * AIWU Analytics Dashboard Template
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap aiwu-analytics-dashboard">
    
    <!-- Header -->
    <div class="aiwu-header">
        <h1>AIWU Analytics Dashboard</h1>
        <p class="aiwu-subtitle">Comprehensive analytics for plugin usage, conversions, and user behavior</p>
    </div>

    <!-- Filters Section -->
    <div class="aiwu-filters-panel">
        <div class="filter-group">
            <label>Period</label>
            <div class="date-inputs">
                <input type="date" id="date_from" class="filter-input" />
                <span class="date-separator">to</span>
                <input type="date" id="date_to" class="filter-input" />
            </div>
        </div>

        <div class="filter-group">
            <label>Quick Select</label>
            <select id="quick_period" class="filter-input">
                <option value="7">Last 7 days</option>
                <option value="30" selected>Last 30 days</option>
                <option value="90">Last 90 days</option>
                <option value="this_month">This month</option>
                <option value="last_month">Last month</option>
            </select>
        </div>

        <div class="filter-group">
            <label>Plan</label>
            <select id="plan_filter" class="filter-input">
                <option value="all">All Plans</option>
                <option value="free">Free Only</option>
                <option value="pro">Pro Only</option>
            </select>
        </div>

        <div class="filter-group">
            <label>Feature</label>
            <select id="feature_filter" class="filter-input">
                <option value="all">All Features</option>
                <option value="chatbot">Chatbot</option>
                <option value="bulk">Bulk Content</option>
                <option value="workflow">Workflow Builder</option>
            </select>
        </div>
        
        <div class="filter-actions">
            <button id="apply_filters" class="button button-primary">Apply Filters</button>
            <button id="reset_filters" class="button">Reset</button>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="aiwu-loading" class="aiwu-loading" style="display: none;">
        <div class="loading-spinner"></div>
        <p>Loading analytics data...</p>
    </div>

    <!-- KPI Cards Section -->
    <div class="aiwu-kpi-section">
        <div class="kpi-card">
            <div class="kpi-content">
                <div class="kpi-label">Total Installations</div>
                <div class="kpi-value" id="kpi-installations">-</div>
                <div class="kpi-change" id="kpi-installations-change">-</div>
            </div>
            <canvas id="kpi-installations-trend" class="kpi-trend"></canvas>
        </div>

        <div class="kpi-card">
            <div class="kpi-content">
                <div class="kpi-label">Conversion Rate</div>
                <div class="kpi-value" id="kpi-conversion">-</div>
                <div class="kpi-change" id="kpi-conversion-change">-</div>
            </div>
            <canvas id="kpi-conversion-trend" class="kpi-trend"></canvas>
        </div>

        <div class="kpi-card">
            <div class="kpi-content">
                <div class="kpi-label">Active Users</div>
                <div class="kpi-value" id="kpi-active">-</div>
                <div class="kpi-change" id="kpi-active-change">-</div>
            </div>
            <canvas id="kpi-active-trend" class="kpi-trend"></canvas>
        </div>

        <div class="kpi-card">
            <div class="kpi-content">
                <div class="kpi-label">Churn Rate</div>
                <div class="kpi-value" id="kpi-churn">-</div>
                <div class="kpi-change negative" id="kpi-churn-change">-</div>
            </div>
            <canvas id="kpi-churn-trend" class="kpi-trend"></canvas>
        </div>
    </div>

    <!-- Conversion Timeline Section -->
    <div class="aiwu-section">
        <div class="section-header">
            <h2>Conversion Analysis</h2>
        </div>
        <div class="chart-container large" style="height: 400px !important; max-height: 400px !important; overflow: hidden !important;">
            <canvas id="conversion-timeline-chart" width="800" height="400"></canvas>
        </div>
    </div>

    <!-- Time to Convert & Recent Conversions -->
    <div class="aiwu-grid-2">
        <div class="aiwu-section">
            <div class="section-header">
                <h3>Time to Conversion</h3>
            </div>
            <div class="chart-container" style="height: 300px !important; max-height: 300px !important; overflow: hidden !important;">
                <canvas id="time-to-convert-chart" width="500" height="300"></canvas>
            </div>
        </div>

        <div class="aiwu-section">
            <div class="section-header">
                <h3>Conversion Triggers</h3>
                <p class="section-subtitle">What users did before upgrading to Pro</p>
            </div>
            <div class="table-container" id="recent-conversions-table">
                <!-- Table will be populated by JS -->
            </div>
        </div>
    </div>

    <!-- Feature Popularity Section -->
    <div class="aiwu-section">
        <div class="section-header">
            <h2>Feature Popularity</h2>
        </div>
        <div class="aiwu-grid-3">
            <div class="chart-card" style="height: 320px !important; max-height: 320px !important; overflow: hidden !important;">
                <h4>By User Count</h4>
                <canvas id="feature-users-chart" width="400" height="280"></canvas>
            </div>
            <div class="chart-card" style="height: 320px !important; max-height: 320px !important; overflow: hidden !important;">
                <h4>By Token Usage</h4>
                <canvas id="feature-tokens-chart" width="400" height="280"></canvas>
            </div>
            <div class="chart-card" style="height: 320px !important; max-height: 320px !important; overflow: hidden !important;">
                <h4>Conversion Rate by Feature</h4>
                <canvas id="feature-conversion-chart" width="400" height="280"></canvas>
            </div>
        </div>
    </div>

    <!-- Churn Analysis Section -->
    <div class="aiwu-grid-2">
        <div class="aiwu-section">
            <div class="section-header">
                <h3>Deactivation Reasons</h3>
            </div>
            <div class="chart-container" style="height: 300px !important; max-height: 300px !important; overflow: hidden !important;">
                <canvas id="churn-reasons-chart" width="500" height="300"></canvas>
            </div>
            <div class="churn-insight">
                <strong>Key Insight:</strong> <span id="churn-insight-text">-</span>
            </div>
        </div>

        <div class="aiwu-section">
            <div class="section-header">
                <h3>Churn Timeline</h3>
            </div>
            <div class="chart-container" style="height: 300px !important; max-height: 300px !important; overflow: hidden !important;">
                <canvas id="churn-timeline-chart" width="500" height="300"></canvas>
            </div>
            <div class="churn-comparison">
                <div class="comparison-item">
                    <span class="label">Free Churn Rate:</span>
                    <span class="value" id="free-churn-rate">-</span>
                </div>
                <div class="comparison-item">
                    <span class="label">Pro Churn Rate:</span>
                    <span class="value" id="pro-churn-rate">-</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Engagement Section -->
    <div class="aiwu-section">
        <div class="section-header">
            <h2>User Engagement</h2>
        </div>
        <div class="aiwu-grid-3">
            <div class="chart-card" style="height: 320px !important; max-height: 320px !important; overflow: hidden !important;">
                <h4>User Segments</h4>
                <canvas id="user-segments-chart" width="400" height="240"></canvas>
                <div class="segment-legend">
                    <div class="legend-item">
                        <span class="color-box dead"></span>
                        <span>Dead (0 tokens)</span>
                    </div>
                    <div class="legend-item">
                        <span class="color-box light"></span>
                        <span>Light (1-10K)</span>
                    </div>
                    <div class="legend-item">
                        <span class="color-box medium"></span>
                        <span>Medium (10-100K)</span>
                    </div>
                    <div class="legend-item">
                        <span class="color-box heavy"></span>
                        <span>Heavy (100K+)</span>
                    </div>
                </div>
            </div>

            <div class="chart-card" style="height: 320px !important; max-height: 320px !important; overflow: hidden !important;">
                <h4>Multi-Feature Usage</h4>
                <canvas id="multi-feature-chart" width="400" height="280"></canvas>
            </div>

            <div class="chart-card" style="height: 320px !important; max-height: 320px !important; overflow: hidden !important;">
                <h4>API Provider Distribution</h4>
                <canvas id="api-providers-chart" width="400" height="280"></canvas>
            </div>
        </div>
    </div>

    <!-- User Activity Table Section -->
    <div class="aiwu-section">
        <div class="section-header">
            <h2>User Activity Details</h2>
            <div class="table-actions">
                <input type="search" id="user-search" placeholder="Search by email..." class="table-search" />
                <button id="export-csv" class="button">Export CSV</button>
            </div>
        </div>
        <div class="table-container scrollable" id="user-activity-table">
            <!-- Table will be populated by JS -->
        </div>
    </div>

    <!-- Footer -->
    <div class="aiwu-footer">
        <p>AIWU Analytics Dashboard v<?php echo AIWU_ANALYTICS_VERSION; ?> | 
           Data refreshed: <span id="last-updated">-</span> | 
           <a href="https://aiwuplugin.com" target="_blank">Visit AIWU Plugin</a>
        </p>
    </div>

</div>

<script>
// Initialize date inputs with default values
document.addEventListener('DOMContentLoaded', function() {
    // Set default dates (last 30 days)
    const today = new Date();
    const thirtyDaysAgo = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000));
    
    document.getElementById('date_to').valueAsDate = today;
    document.getElementById('date_from').valueAsDate = thirtyDaysAgo;
    
    // Quick period selector
    document.getElementById('quick_period').addEventListener('change', function() {
        const value = this.value;
        const today = new Date();
        let fromDate;
        
        switch(value) {
            case '7':
                fromDate = new Date(today.getTime() - (7 * 24 * 60 * 60 * 1000));
                break;
            case '30':
                fromDate = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000));
                break;
            case '90':
                fromDate = new Date(today.getTime() - (90 * 24 * 60 * 60 * 1000));
                break;
            case 'this_month':
                fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
                break;
            case 'last_month':
                fromDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                const toDate = new Date(today.getFullYear(), today.getMonth(), 0);
                document.getElementById('date_to').valueAsDate = toDate;
                break;
        }
        
        if (fromDate) {
            document.getElementById('date_from').valueAsDate = fromDate;
        }
        
        if (value !== 'last_month') {
            document.getElementById('date_to').valueAsDate = today;
        }
    });
    
    // Load initial data
    window.aiwuDashboard.loadData();
});
</script>
