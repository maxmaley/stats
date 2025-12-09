/**
 * AIWU Analytics Dashboard JavaScript
 */

(function($) {
    'use strict';

    // Main dashboard object
    window.aiwuDashboard = {

        charts: {},
        data: null,

        /**
         * Helper: Get canvas element with null check
         */
        getCanvas: function(id) {
            const element = document.getElementById(id);
            if (!element) {
                console.error('Canvas element not found:', id);
                return null;
            }
            return element;
        },

        /**
         * Initialize dashboard
         */
        init: function() {
            this.bindEvents();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            $('#apply_filters').on('click', () => this.loadData());
            $('#reset_filters').on('click', () => this.resetFilters());
            $('#export_csv').on('click', () => this.exportCSV());
            $('#period_selector').on('change', () => this.handlePeriodChange());
        },

        /**
         * Handle period selector change
         */
        handlePeriodChange: function() {
            const period = $('#period_selector').val();
            if (period === 'custom') {
                $('#custom_date_range').show();
            } else {
                $('#custom_date_range').hide();
                this.loadData(); // Auto-apply when selecting preset period
            }
        },

        /**
         * Reset filters to default
         */
        resetFilters: function() {
            $('#period_selector').val('30');
            $('#custom_date_range').hide();
            this.loadData();
        },
        
        /**
         * Format date to YYYY-MM-DD
         */
        formatDate: function(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        },

        /**
         * Calculate date range based on period
         */
        calculatePeriodDates: function(period) {
            const today = new Date();
            let dateFrom, dateTo;

            dateTo = this.formatDate(today);

            switch(period) {
                case '7':
                    dateFrom = this.formatDate(new Date(today.getTime() - (7 * 24 * 60 * 60 * 1000)));
                    break;
                case '30':
                    dateFrom = this.formatDate(new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000)));
                    break;
                case '90':
                    dateFrom = this.formatDate(new Date(today.getTime() - (90 * 24 * 60 * 60 * 1000)));
                    break;
                case 'this_month':
                    dateFrom = this.formatDate(new Date(today.getFullYear(), today.getMonth(), 1));
                    break;
                case 'last_month':
                    const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    dateFrom = this.formatDate(lastMonth);
                    dateTo = this.formatDate(new Date(today.getFullYear(), today.getMonth(), 0));
                    break;
                case 'this_year':
                    dateFrom = this.formatDate(new Date(today.getFullYear(), 0, 1));
                    break;
                default:
                    dateFrom = this.formatDate(new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000)));
            }

            return { from: dateFrom, to: dateTo };
        },
        
        /**
         * Load analytics data via AJAX
         */
        loadData: function() {
            const period = $('#period_selector').val();
            let dateFrom, dateTo;

            if (period === 'custom') {
                dateFrom = $('#date_from').val();
                dateTo = $('#date_to').val();

                if (!dateFrom || !dateTo) {
                    alert('Please select both start and end dates');
                    return;
                }
            } else {
                const dates = this.calculatePeriodDates(period);
                dateFrom = dates.from;
                dateTo = dates.to;
            }

            // Show loading
            $('#aiwu-loading').show();

            $.ajax({
                url: aiwuAnalytics.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiwu_get_analytics_data',
                    nonce: aiwuAnalytics.nonce,
                    date_from: dateFrom,
                    date_to: dateTo
                },
                success: (response) => {
                    if (response.success) {
                        this.data = response.data;
                        this.renderDashboard();
                        $('#last-updated').text(new Date().toLocaleString());
                    } else {
                        alert('Error loading data: ' + response.data);
                    }
                },
                error: (xhr, status, error) => {
                    alert('Error loading data: ' + error);
                },
                complete: () => {
                    $('#aiwu-loading').hide();
                }
            });
        },
        
        /**
         * Render entire dashboard
         */
        renderDashboard: function() {
            this.renderKPICards();
            this.renderConversionCharts();
            this.renderFeatureCharts();
            this.renderChurnCharts();
            this.renderEngagementCharts();
            this.renderTables();
        },
        
        /**
         * Render KPI cards
         */
        renderKPICards: function() {
            const kpi = this.data.kpi;

            // Installations
            $('#kpi-installations').text(kpi.installations.value.toLocaleString());
            this.updateKPIChange('#kpi-installations-change', kpi.installations.change);

            // Conversion Rate
            $('#kpi-conversion').text(kpi.conversion_rate.value + '%');
            this.updateKPIChange('#kpi-conversion-change', kpi.conversion_rate.change);

            // Active Free Users
            if (kpi.active_free_users) {
                $('#kpi-active-free').text(kpi.active_free_users.value.toLocaleString());
                this.updateKPIChange('#kpi-active-free-change', kpi.active_free_users.change);
            } else {
                console.error('Missing active_free_users in KPI data');
                $('#kpi-active-free').text('0');
            }

            // Active Pro Users
            if (kpi.active_pro_users) {
                $('#kpi-active-pro').text(kpi.active_pro_users.value.toLocaleString());
                this.updateKPIChange('#kpi-active-pro-change', kpi.active_pro_users.change);
            } else {
                console.error('Missing active_pro_users in KPI data');
                $('#kpi-active-pro').text('0');
            }

            // Churn Rate
            $('#kpi-churn').text(kpi.churn_rate.value + '%');
            this.updateKPIChange('#kpi-churn-change', kpi.churn_rate.change, true);
        },
        
        /**
         * Update KPI change indicator
         */
        updateKPIChange: function(selector, change, inverse = false) {
            const $elem = $(selector);
            const isPositive = inverse ? change < 0 : change > 0;
            
            $elem.removeClass('positive negative');
            $elem.addClass(isPositive ? 'positive' : 'negative');
            $elem.text(Math.abs(change) + '% vs previous period');
        },
        
        /**
         * Render mini trend sparkline
         */
        renderMiniTrend: function(canvasId, data) {
            const ctx = document.getElementById(canvasId);
            
            if (this.charts[canvasId]) {
                this.charts[canvasId].destroy();
            }
            
            this.charts[canvasId] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map((_, i) => i),
                    datasets: [{
                        data: data,
                        borderColor: '#3b82f6',
                        borderWidth: 2,
                        fill: false,
                        pointRadius: 0,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: false }
                    },
                    scales: {
                        x: { display: false },
                        y: { display: false }
                    }
                }
            });
        },
        
        /**
         * Render conversion charts
         */
        renderConversionCharts: function() {
            const conv = this.data.conversion;

            // Conversion Timeline
            this.renderConversionTimeline(conv.timeline);
        },
        
        /**
         * Render conversion timeline chart
         */
        renderConversionTimeline: function(timeline) {
            const ctx = this.getCanvas('conversion-timeline-chart');
            if (!ctx) return;

            if (!timeline || !timeline.activations || !timeline.conversions) {
                console.error('Invalid timeline data', timeline);
                return;
            }

            if (this.charts.conversionTimeline) {
                this.charts.conversionTimeline.destroy();
            }

            // Merge activations and conversions data
            const dates = [...new Set([
                ...timeline.activations.map(a => a.date),
                ...timeline.conversions.map(c => c.date)
            ])].sort();
            
            const activationsData = dates.map(date => {
                const item = timeline.activations.find(a => a.date === date);
                return item ? parseInt(item.free_activations) : 0;
            });
            
            const conversionsData = dates.map(date => {
                const item = timeline.conversions.find(c => c.date === date);
                return item ? parseInt(item.conversions) : 0;
            });
            
            this.charts.conversionTimeline = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [
                        {
                            label: 'Free Activations',
                            data: activationsData,
                            borderColor: '#94a3b8',
                            backgroundColor: 'rgba(148, 163, 184, 0.1)',
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Conversions to Pro',
                            data: conversionsData,
                            borderColor: '#48bb78',
                            backgroundColor: 'rgba(72, 187, 120, 0.1)',
                            fill: true,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f1f5f9'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        },

        /**
         * Render time to convert histogram
         */
        renderTimeToConvert: function(data) {
            const ctx = this.getCanvas('time-to-convert-chart');
            if (!ctx || !data) return;

            if (this.charts.timeToConvert) {
                this.charts.timeToConvert.destroy();
            }

            const labels = Object.keys(data);
            const values = Object.values(data);
            
            this.charts.timeToConvert = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels.map(l => l + ' days'),
                    datasets: [{
                        label: 'Users',
                        data: values,
                        backgroundColor: [
                            '#3b82f6',
                            '#6366f1',
                            '#8b5cf6',
                            '#06b6d4',
                            '#10b981',
                            '#f59e0b'
                        ],
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f1f5f9'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        },

        /**
         * Render recent conversions table
         */
        renderRecentConversions: function(conversions) {
            let html = '<table class="aiwu-table">';
            html += '<thead><tr>';
            html += '<th>Email</th>';
            html += '<th>Days to Convert</th>';
            html += '<th>Tasks</th>';
            html += '<th>Features Used</th>';
            html += '<th>Total Tokens</th>';
            html += '<th>Date</th>';
            html += '</tr></thead><tbody>';
            
            conversions.forEach(conv => {
                html += '<tr>';
                html += `<td>${conv.email}</td>`;
                html += `<td><strong>${conv.days_to_convert}</strong></td>`;
                html += `<td>${conv.tasks_created}</td>`;
                html += '<td><div class="feature-tokens">';
                
                if (conv.features.length === 0) {
                    html += '<span class="text-muted">None</span>';
                } else {
                    conv.features.forEach(f => {
                        html += `<div class="feature-token">`;
                        html += `<span class="name">${f.name}</span>`;
                        html += `<span class="tokens">${this.formatNumber(f.tokens)}</span>`;
                        html += `</div>`;
                    });
                }
                
                html += '</div></td>';
                html += `<td>${this.formatNumber(conv.total_tokens)}</td>`;
                html += `<td>${conv.pro_date}</td>`;
                html += '</tr>';
            });
            
            html += '</tbody></table>';
            $('#recent-conversions-table').html(html);
        },
        
        /**
         * Render feature charts
         */
        renderFeatureCharts: function() {
            const features = this.data.features;
            
            // By user count
            this.renderFeatureUserChart(features);
            
            // By tokens
            this.renderFeatureTokensChart(features);
            
            // By conversion rate
            this.renderFeatureConversionChart(features);
        },
        
        /**
         * Render feature users chart
         */
        renderFeatureUserChart: function(features) {
            const ctx = this.getCanvas('feature-users-chart');
            if (!ctx || !features) return;

            if (this.charts.featureUsers) {
                this.charts.featureUsers.destroy();
            }

            // Take top 8 features
            const top = features.slice(0, 8);
            
            this.charts.featureUsers = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: top.map(f => f.name),
                    datasets: [{
                        label: 'Users',
                        data: top.map(f => f.user_count),
                        backgroundColor: '#3b82f6',
                        borderRadius: 8
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: {
                                color: '#f1f5f9'
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        },

        /**
         * Render feature tokens chart
         */
        renderFeatureTokensChart: function(features) {
            const ctx = this.getCanvas('feature-tokens-chart');
            if (!ctx || !features) return;

            if (this.charts.featureTokens) {
                this.charts.featureTokens.destroy();
            }

            // Take top 8 features
            const top = features.slice(0, 8);
            
            this.charts.featureTokens = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: top.map(f => f.name),
                    datasets: [{
                        label: 'Tokens',
                        data: top.map(f => f.total_tokens),
                        backgroundColor: '#6366f1',
                        borderRadius: 8
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    return this.formatNumber(context.parsed.x) + ' tokens';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            type: 'logarithmic',
                            grid: {
                                color: '#f1f5f9'
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        },

        /**
         * Render feature conversion chart
         */
        renderFeatureConversionChart: function(features) {
            const ctx = this.getCanvas('feature-conversion-chart');
            if (!ctx || !features) return;

            if (this.charts.featureConversion) {
                this.charts.featureConversion.destroy();
            }
            
            const convData = this.data.conversion.by_feature;
            
            this.charts.featureConversion = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: convData.map(f => f.feature),
                    datasets: [{
                        label: 'Conversion Rate (%)',
                        data: convData.map(f => f.conversion_rate),
                        backgroundColor: '#48bb78',
                        borderRadius: 8
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const item = convData[context.dataIndex];
                                    return `${context.parsed.x}% (${item.converted_users}/${item.total_users})`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            max: 100,
                            grid: {
                                color: '#f1f5f9'
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        },

        /**
         * Render churn charts
         */
        renderChurnCharts: function() {
            const churn = this.data.churn;
            
            // Deactivation reasons
            this.renderChurnReasons(churn.reasons);
            
            // Churn timeline
            this.renderChurnTimeline(churn.timeline);
            
            // Update comparison
            $('#free-churn-rate').text(churn.rate_by_plan.free.rate + '%');
            $('#pro-churn-rate').text(churn.rate_by_plan.pro.rate + '%');
            
            // Update insight
            const topReason = churn.reasons[0];
            $('#churn-insight-text').text(
                `${topReason.reason} is the #1 reason (${topReason.count} users, ${Math.round((topReason.count / churn.reasons.reduce((sum, r) => sum + r.count, 0)) * 100)}%)`
            );
        },
        
        /**
         * Render churn reasons chart
         */
        renderChurnReasons: function(reasons) {
            const ctx = this.getCanvas('churn-reasons-chart');
            if (!ctx || !reasons) return;

            if (this.charts.churnReasons) {
                this.charts.churnReasons.destroy();
            }
            
            this.charts.churnReasons = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: reasons.map(r => r.reason),
                    datasets: [{
                        data: reasons.map(r => r.count),
                        backgroundColor: [
                            '#ef4444',
                            '#f97316',
                            '#f59e0b',
                            '#10b981',
                            '#3b82f6',
                            '#8b5cf6',
                            '#d1d5db'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
        },

        /**
         * Render churn timeline chart
         */
        renderChurnTimeline: function(timeline) {
            const ctx = this.getCanvas('churn-timeline-chart');
            if (!ctx || !timeline) return;

            if (this.charts.churnTimeline) {
                this.charts.churnTimeline.destroy();
            }
            
            this.charts.churnTimeline = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: timeline.map(t => t.date),
                    datasets: [{
                        label: 'Deactivations',
                        data: timeline.map(t => t.deactivations),
                        borderColor: '#f56565',
                        backgroundColor: 'rgba(245, 101, 101, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f1f5f9'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        },

        /**
         * Render engagement charts
         */
        renderEngagementCharts: function() {
            const engagement = this.data.engagement;
            
            // User segments
            this.renderUserSegments(engagement.user_segments);
            
            // Multi-feature usage
            this.renderMultiFeature(engagement.multi_feature_usage);
            
            // API providers
            this.renderAPIProviders(engagement.api_providers);
        },
        
        /**
         * Render user segments chart
         */
        renderUserSegments: function(segments) {
            const ctx = this.getCanvas('user-segments-chart');
            if (!ctx || !segments) return;

            if (this.charts.userSegments) {
                this.charts.userSegments.destroy();
            }
            
            this.charts.userSegments = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: ['Dead', 'Light', 'Medium', 'Heavy'],
                    datasets: [{
                        data: [
                            segments.dead.count,
                            segments.light.count,
                            segments.medium.count,
                            segments.heavy.count
                        ],
                        backgroundColor: [
                            '#9ca3af',
                            '#34d399',
                            '#fbbf24',
                            '#ef4444'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const label = context.label;
                                    const value = context.parsed;
                                    const percentage = context.dataset.data.reduce((a, b) => a + b, 0);
                                    return `${label}: ${value} (${Math.round((value / percentage) * 100)}%)`;
                                }
                            }
                        }
                    }
                }
            });
        },

        /**
         * Render multi-feature usage chart
         */
        renderMultiFeature: function(data) {
            const ctx = this.getCanvas('multi-feature-chart');
            if (!ctx || !data) return;

            if (this.charts.multiFeature) {
                this.charts.multiFeature.destroy();
            }
            
            this.charts.multiFeature = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.features + ' features'),
                    datasets: [{
                        label: 'Users',
                        data: data.map(d => d.count),
                        backgroundColor: '#06b6d4',
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f1f5f9'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        },

        /**
         * Render API providers chart
         */
        renderAPIProviders: function(providers) {
            const ctx = this.getCanvas('api-providers-chart');
            if (!ctx || !providers) return;

            if (this.charts.apiProviders) {
                this.charts.apiProviders.destroy();
            }
            
            this.charts.apiProviders = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: providers.map(p => p.provider),
                    datasets: [{
                        label: 'Users',
                        data: providers.map(p => p.count),
                        backgroundColor: [
                            '#3b82f6',
                            '#6366f1',
                            '#8b5cf6'
                        ],
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f1f5f9'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        },

        /**
         * Render tables
         */
        renderTables: function() {
            this.renderUserActivityTable(this.data.users);
        },
        
        /**
         * Render user activity table
         */
        renderUserActivityTable: function(users) {
            let html = '<table class="aiwu-table">';
            html += '<thead><tr>';
            html += '<th>Email</th>';
            html += '<th>Activated</th>';
            html += '<th>Plan</th>';
            html += '<th>Tasks</th>';
            html += '<th>Total Tokens</th>';
            html += '<th>Last Activity</th>';
            html += '</tr></thead><tbody>';
            
            users.forEach(user => {
                html += '<tr>';
                html += `<td>${user.email}</td>`;
                html += `<td>${user.activated}</td>`;
                html += `<td><span class="badge ${user.plan.toLowerCase()}">${user.plan}</span></td>`;
                html += `<td>${user.tasks}</td>`;
                html += `<td>${user.total_tokens}</td>`;
                html += `<td>${user.last_activity}</td>`;
                html += '</tr>';
            });
            
            html += '</tbody></table>';
            $('#user-activity-table').html(html);
        },
        
        /**
         * Format number (1.36B, 230M, 45K)
         */
        formatNumber: function(num) {
            if (num >= 1000000000) {
                return (num / 1000000000).toFixed(2) + 'B';
            } else if (num >= 1000000) {
                return (num / 1000000).toFixed(2) + 'M';
            } else if (num >= 1000) {
                return (num / 1000).toFixed(2) + 'K';
            }
            return num.toString();
        },
        
        /**
         * Export data to CSV
         */
        exportCSV: function() {
            if (!this.data || !this.data.users) {
                alert('No data to export');
                return;
            }
            
            let csv = 'Email,Activated,Plan,Tasks,Total Tokens,Last Activity\n';
            
            this.data.users.forEach(user => {
                csv += `${user.email},${user.activated},${user.plan},${user.tasks},${user.total_tokens},${user.last_activity}\n`;
            });
            
            // Download CSV
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'aiwu-analytics-' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        window.aiwuDashboard.init();
    });
    
})(jQuery);
