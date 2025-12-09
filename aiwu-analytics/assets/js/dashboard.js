/**
 * AIWU Analytics Dashboard - MVP
 */

(function($) {
    'use strict';

    window.aiwuDashboard = {
        charts: {},
        data: null,

        init: function() {
            this.bindEvents();
            this.loadData();
        },

        bindEvents: function() {
            $('#apply-filters').on('click', () => this.loadData());
        },

        loadData: function() {
            const dateFrom = $('#date-from').val();
            const dateTo = $('#date-to').val();

            if (!dateFrom || !dateTo) {
                alert('Please select date range');
                return;
            }

            $('#loading').show();
            $('#dashboard-content').hide();

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
                        this.render();
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: (xhr, status, error) => {
                    alert('Error: ' + error);
                },
                complete: () => {
                    $('#loading').hide();
                    $('#dashboard-content').show();
                }
            });
        },

        render: function() {
            this.renderKPIs();
            this.renderTrends();
            this.renderFeatureTables();
            this.renderDeactivationReasons();
            this.renderDeactivationTrend();
        },

        renderKPIs: function() {
            const kpi = this.data.kpi;
            $('#kpi-free-installations').text(kpi.new_free_installations.toLocaleString());
            $('#kpi-active-free').text(kpi.active_free_users.toLocaleString());
            $('#kpi-active-pro').text(kpi.active_pro_users.toLocaleString());
        },

        renderTrends: function() {
            const trends = this.data.trends;

            // Free installations chart
            this.renderLineChart(
                'chart-free-installations',
                'freeInstallations',
                trends.free_installations,
                'Free Installations',
                '#3b82f6'
            );

            // Pro installations chart
            this.renderLineChart(
                'chart-pro-installations',
                'proInstallations',
                trends.pro_installations,
                'Pro Installations',
                '#10b981'
            );
        },

        renderLineChart: function(canvasId, chartKey, data, label, color) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return;

            if (this.charts[chartKey]) {
                this.charts[chartKey].destroy();
            }

            this.charts[chartKey] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.date),
                    datasets: [{
                        label: label,
                        data: data.map(d => d.count),
                        borderColor: color,
                        backgroundColor: color + '20',
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
                            grid: { color: '#e5e7eb' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        },

        renderFeatureTables: function() {
            const features = this.data.features;

            // By users
            let usersHtml = '';
            features.by_users.forEach(f => {
                usersHtml += `<tr><td>${f.feature}</td><td>${f.count.toLocaleString()}</td></tr>`;
            });
            $('#table-features-users tbody').html(usersHtml);

            // By tokens
            let tokensHtml = '';
            features.by_tokens.forEach(f => {
                tokensHtml += `<tr><td>${f.feature}</td><td>${this.formatNumber(f.tokens)}</td></tr>`;
            });
            $('#table-features-tokens tbody').html(tokensHtml);

            // By API provider
            let providersHtml = '';
            features.by_api_provider.forEach(p => {
                providersHtml += `<tr><td>${p.provider}</td><td>${p.count.toLocaleString()}</td></tr>`;
            });
            $('#table-api-providers tbody').html(providersHtml);
        },

        renderDeactivationReasons: function() {
            const reasons = this.data.deactivations.reasons;

            let html = '';
            reasons.forEach(r => {
                html += `<tr><td>${r.reason}</td><td>${r.count.toLocaleString()}</td></tr>`;
            });
            $('#table-deactivation-reasons tbody').html(html);
        },

        renderDeactivationTrend: function() {
            const timeline = this.data.deactivations.timeline;
            const ctx = document.getElementById('chart-deactivations');
            if (!ctx) return;

            if (this.charts.deactivations) {
                this.charts.deactivations.destroy();
            }

            this.charts.deactivations = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: timeline.map(d => d.date),
                    datasets: [{
                        label: 'Deactivations',
                        data: timeline.map(d => d.count),
                        borderColor: '#ef4444',
                        backgroundColor: '#ef444420',
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
                            grid: { color: '#e5e7eb' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        },

        formatNumber: function(num) {
            if (num >= 1000000000) {
                return (num / 1000000000).toFixed(1) + 'B';
            } else if (num >= 1000000) {
                return (num / 1000000).toFixed(1) + 'M';
            } else if (num >= 1000) {
                return (num / 1000).toFixed(1) + 'K';
            }
            return num.toString();
        }
    };

    $(document).ready(function() {
        window.aiwuDashboard.init();
    });

})(jQuery);
