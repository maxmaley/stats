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
        </div>

        <div class="kpi-card">
            <div class="kpi-content">
                <div class="kpi-label">Conversion Rate</div>
                <div class="kpi-value" id="kpi-conversion">-</div>
                <div class="kpi-change" id="kpi-conversion-change">-</div>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-content">
                <div class="kpi-label">Active Free Users</div>
                <div class="kpi-value" id="kpi-active-free">-</div>
                <div class="kpi-change" id="kpi-active-free-change">-</div>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-content">
                <div class="kpi-label">Active Pro Users</div>
                <div class="kpi-value" id="kpi-active-pro">-</div>
                <div class="kpi-change" id="kpi-active-pro-change">-</div>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-content">
                <div class="kpi-label">Churn Rate</div>
                <div class="kpi-value" id="kpi-churn">-</div>
                <div class="kpi-change negative" id="kpi-churn-change">-</div>
            </div>
        </div>
    </div>

    <!-- Conversion Timeline Section -->
    <div class="aiwu-section">
        <div class="section-header">
            <h2>Conversion Analysis</h2>
            <p class="section-description">График показывает активации FREE версии и конверсии в PRO по дням. Каждый пользователь считается только один раз при его первой конверсии.</p>
        </div>
        <div class="chart-container large" style="height: 500px !important; max-height: 500px !important; overflow: hidden !important;">
            <canvas id="conversion-timeline-chart"></canvas>
        </div>
    </div>

    <!-- Feature Popularity Section -->
    <div class="aiwu-section">
        <div class="section-header">
            <h2>Feature Popularity</h2>
            <p class="section-description">Анализ использования функций плагина. <strong>User Count:</strong> количество уникальных пользователей. <strong>Token Usage:</strong> общее количество использованных токенов. <strong>Conversion Rate:</strong> какой процент пользователей каждой функции в итоге перешли на PRO (показывает корреляцию, не причину).</p>
        </div>
        <div class="aiwu-grid-3">
            <div class="chart-card" style="height: 380px !important; max-height: 380px !important; overflow: hidden !important;">
                <h4>By User Count</h4>
                <canvas id="feature-users-chart"></canvas>
            </div>
            <div class="chart-card" style="height: 380px !important; max-height: 380px !important; overflow: hidden !important;">
                <h4>By Token Usage</h4>
                <canvas id="feature-tokens-chart"></canvas>
            </div>
            <div class="chart-card" style="height: 380px !important; max-height: 380px !important; overflow: hidden !important;">
                <h4>Conversion Rate by Feature</h4>
                <canvas id="feature-conversion-chart"></canvas>
            </div>
        </div>
    </div>

    <!-- Churn Analysis Section -->
    <div class="aiwu-grid-2">
        <div class="aiwu-section">
            <div class="section-header">
                <h3>Deactivation Reasons</h3>
                <p class="section-description">Распределение причин деактивации плагина на основе выбранной причины при удалении.</p>
            </div>
            <div class="chart-container" style="height: 380px !important; max-height: 380px !important; overflow: hidden !important;">
                <canvas id="churn-reasons-chart"></canvas>
            </div>
            <div class="churn-insight">
                <strong>Key Insight:</strong> <span id="churn-insight-text">-</span>
            </div>
        </div>

        <div class="aiwu-section">
            <div class="section-header">
                <h3>Churn Timeline</h3>
                <p class="section-description">Динамика деактиваций по дням. Churn Rate = (деактивации в периоде / активные пользователи на начало периода) × 100%.</p>
            </div>
            <div class="chart-container" style="height: 380px !important; max-height: 380px !important; overflow: hidden !important;">
                <canvas id="churn-timeline-chart"></canvas>
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
            <p class="section-description">Анализ активности пользователей. <strong>User Segments:</strong> группы по использованию токенов (Dead=0, Light=1-10K, Medium=10-100K, Heavy=100K+). <strong>Multi-Feature Usage:</strong> сколько разных функций использует каждый пользователь. <strong>API Providers:</strong> распределение по выбранным API провайдерам.</p>
        </div>
        <div class="aiwu-grid-3">
            <div class="chart-card" style="height: 380px !important; max-height: 380px !important; overflow: hidden !important;">
                <h4>User Segments</h4>
                <canvas id="user-segments-chart"></canvas>
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

            <div class="chart-card" style="height: 380px !important; max-height: 380px !important; overflow: hidden !important;">
                <h4>Multi-Feature Usage</h4>
                <canvas id="multi-feature-chart"></canvas>
            </div>

            <div class="chart-card" style="height: 380px !important; max-height: 380px !important; overflow: hidden !important;">
                <h4>API Provider Distribution</h4>
                <canvas id="api-providers-chart"></canvas>
            </div>
        </div>
    </div>

    <!-- User Activity Table Section -->
    <div class="aiwu-section">
        <div class="section-header">
            <h2>User Activity Details</h2>
            <p class="section-description">Детальная информация по каждому пользователю: дата активации, текущий план (FREE/PRO определяется по последней записи), последняя активность, общее количество токенов и задач.</p>
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
