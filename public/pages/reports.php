<?php
/**
 * Reports & Analytics Page
 * Sanctum CRM
 */

// Remove any require_once for auth.php and layout.php

$auth = new Auth();
$auth->requireAuth();

// Render the page using the template system
renderHeader('Reports');
ob_start();
?>
<button class="btn btn-outline-success" type="button" onclick="exportData('csv')"><i class="bi bi-download me-1"></i>Export CSV</button>
<button class="btn btn-outline-primary" type="button" onclick="exportData('json')"><i class="bi bi-code-slash me-1"></i>Export JSON</button>
<?php
renderPageHeader('Reports & Analytics', 'Pipeline metrics and charts', ob_get_clean());
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    .reports-card { max-width: 1200px; margin: 0 auto; }
    .metric-card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .metric-value { font-size: 2rem; font-weight: bold; color: #007bff; }
    .metric-label { color: #6c757d; font-size: 0.9rem; }
    .chart-container { position: relative; height: 300px; margin-bottom: 30px; }
    .filter-section { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
    .export-buttons { margin-bottom: 20px; }
</style>

<div class="reports-card">
    <!-- Date Range Filter -->
    <form class="filter-bar" role="search" onsubmit="event.preventDefault(); generateReport();">
        <div class="filter-bar__field">
            <input type="date" class="form-control" id="startDate" aria-label="Start date">
        </div>
        <div class="filter-bar__field">
            <input type="date" class="form-control" id="endDate" aria-label="End date">
        </div>
        <div class="filter-bar__field">
            <select class="form-select" id="reportType" aria-label="Report type">
                <option value="all">All Data</option>
                <option value="deals">Deals Only</option>
                <option value="contacts">Contacts Only</option>
                <option value="users">User Activity</option>
            </select>
        </div>
        <div class="filter-bar__actions">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-arrow-clockwise me-1"></i>Generate Report
            </button>
        </div>
    </form>

    <!-- Alerts -->
    <div id="reportsAlert" class="alert d-none" role="alert"></div>

    <!-- Key Metrics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="metric-card text-center">
                <div class="metric-value" id="totalDeals">0</div>
                <div class="metric-label">Total Deals</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card text-center">
                <div class="metric-value" id="totalValue">$0</div>
                <div class="metric-label">Total Pipeline Value</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card text-center">
                <div class="metric-value" id="winRate">0%</div>
                <div class="metric-label">Win Rate</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card text-center">
                <div class="metric-value" id="avgDealSize">$0</div>
                <div class="metric-label">Average Deal Size</div>
            </div>
        </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="metric-card">
                <h5><i class="bi bi-pie-chart"></i> Deals by Stage</h5>
                <div class="chart-container">
                    <canvas id="dealsByStageChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="metric-card">
                <h5><i class="bi bi-graph-up"></i> Pipeline Value by Stage</h5>
                <div class="chart-container">
                    <canvas id="pipelineValueChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="metric-card">
                <h5><i class="bi bi-people"></i> Contact Sources</h5>
                <div class="chart-container">
                    <canvas id="contactSourcesChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="metric-card">
                <h5><i class="bi bi-calendar3"></i> Deals Over Time</h5>
                <div class="chart-container">
                    <canvas id="dealsOverTimeChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- User Activity Table -->
    <div class="metric-card">
        <h5><i class="bi bi-person-workspace"></i> Recent User Activity</h5>
        <div class="table-responsive">
            <table class="table table-striped" id="activityTable">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Populated by JS -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let reportData = null;
let charts = {};
const STAGE_COLORS = ['#6c757d', '#17a2b8', '#ffc107', '#007bff', '#28a745', '#dc3545'];
const SOURCE_COLORS = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6c757d', '#17a2b8', '#fd7e14'];

async function fetchJsonOrThrow(url) {
    const response = await fetch(url, { credentials: 'include' });
    const payload = await response.json();
    if (!response.ok) {
        throw new Error(payload.error || `HTTP ${response.status}`);
    }
    return payload;
}

function destroyChart(key) {
    if (charts[key]) {
        charts[key].destroy();
        charts[key] = null;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - 30);
    document.getElementById('endDate').value = endDate.toISOString().split('T')[0];
    document.getElementById('startDate').value = startDate.toISOString().split('T')[0];
    generateReport();
});

async function generateReport() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const reportType = document.getElementById('reportType').value;

    if (!startDate || !endDate) {
        showAlert('Please select both start and end dates', 'warning');
        return;
    }

    try {
        showAlert('Generating report...', 'info');
        const qs = new URLSearchParams({
            start_date: startDate,
            end_date: endDate,
            report_type: reportType
        });
        reportData = await fetchJsonOrThrow(crmApiUrl('reports/analytics?' + qs.toString()));
        renderMetrics(reportData.metrics || {});
        renderCharts(reportData.charts || {});
        renderActivity(reportData.activity || []);
        if (reportData.empty) {
            showAlert('Report generated — no deals/contacts in this date range.', 'success');
        } else {
            showAlert('Report generated successfully!', 'success');
        }
    } catch (err) {
        console.error('Failed to generate report:', err);
        showAlert('Network error while generating report', 'danger');
    }
}

function renderMetrics(metrics) {
    document.getElementById('totalDeals').textContent = metrics.total_deals ?? 0;
    const totalValue = Number(metrics.total_pipeline_value || 0);
    document.getElementById('totalValue').textContent = '$' + totalValue.toLocaleString();
    document.getElementById('winRate').textContent = (metrics.win_rate ?? 0) + '%';
    const avg = Number(metrics.avg_deal_size || 0);
    document.getElementById('avgDealSize').textContent = '$' + avg.toLocaleString(undefined, { maximumFractionDigits: 0 });
}

function renderCharts(chartData) {
    renderDoughnut('dealsByStage', 'dealsByStageChart', chartData.deals_by_stage, STAGE_COLORS);
    renderBar('pipelineValue', 'pipelineValueChart', chartData.pipeline_value_by_stage, STAGE_COLORS);
    renderSources('contactSources', 'contactSourcesChart', chartData.contact_sources);
    renderLine('dealsOverTime', 'dealsOverTimeChart', chartData.deals_over_time);
}

function chartSeries(raw) {
    return {
        labels: (raw && raw.labels) ? raw.labels : [],
        values: (raw && raw.values) ? raw.values : []
    };
}

function renderDoughnut(key, canvasId, raw, colors) {
    destroyChart(key);
    const series = chartSeries(raw);
    const ctx = document.getElementById(canvasId).getContext('2d');
    const hasData = series.values.some(v => Number(v) > 0);
    charts[key] = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: hasData ? series.labels : ['No deals in selected range'],
            datasets: [{
                data: hasData ? series.values : [1],
                backgroundColor: hasData ? colors : ['#e9ecef'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: { enabled: hasData }
            }
        }
    });
}

function renderBar(key, canvasId, raw, colors) {
    destroyChart(key);
    const series = chartSeries(raw);
    const ctx = document.getElementById(canvasId).getContext('2d');
    charts[key] = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: series.labels,
            datasets: [{
                label: 'Pipeline Value ($)',
                data: series.values,
                backgroundColor: colors,
                borderColor: colors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString();
                        }
                    }
                }
            },
            plugins: { legend: { display: false } }
        }
    });
}

function renderSources(key, canvasId, raw) {
    destroyChart(key);
    const series = chartSeries(raw);
    const ctx = document.getElementById(canvasId).getContext('2d');
    if (series.labels.length === 0) {
        charts[key] = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['No contact data in selected range'],
                datasets: [{
                    data: [1],
                    backgroundColor: ['#e9ecef'],
                    borderWidth: 1,
                    borderColor: '#dee2e6'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: { enabled: false }
                }
            }
        });
        return;
    }
    charts[key] = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: series.labels,
            datasets: [{
                data: series.values,
                backgroundColor: SOURCE_COLORS.slice(0, series.labels.length),
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });
}

function renderLine(key, canvasId, raw) {
    destroyChart(key);
    const series = chartSeries(raw);
    const ctx = document.getElementById(canvasId).getContext('2d');
    charts[key] = new Chart(ctx, {
        type: 'line',
        data: {
            labels: series.labels.length ? series.labels : ['No data'],
            datasets: [{
                label: 'Deals Created',
                data: series.labels.length ? series.values : [0],
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            },
            plugins: { legend: { display: false } }
        }
    });
}

function renderActivity(rows) {
    const tbody = document.querySelector('#activityTable tbody');
    tbody.innerHTML = '';
    rows.forEach(row => {
        const tr = document.createElement('tr');
        const dateRaw = row.date || '';
        const dateLabel = dateRaw ? new Date(String(dateRaw).replace(' ', 'T')).toLocaleDateString() : '';
        tr.innerHTML = `
            <td>${escapeHtml(row.user || 'System')}</td>
            <td>${escapeHtml(row.action || '')}</td>
            <td>${escapeHtml(row.details || '')}</td>
            <td>${escapeHtml(dateLabel)}</td>
        `;
        tbody.appendChild(tr);
    });
}

function exportData(format) {
    if (!reportData) {
        showAlert('No data to export. Please generate a report first.', 'warning');
        return;
    }

    let data, filename, mimeType;
    if (format === 'csv') {
        const metrics = reportData.metrics || {};
        const lines = [
            'Metric,Value',
            `total_deals,${metrics.total_deals ?? 0}`,
            `total_pipeline_value,${metrics.total_pipeline_value ?? 0}`,
            `win_rate,${metrics.win_rate ?? 0}`,
            `avg_deal_size,${metrics.avg_deal_size ?? 0}`
        ];
        const stage = (reportData.charts && reportData.charts.deals_by_stage) || { labels: [], values: [] };
        lines.push('');
        lines.push('Stage,Deal Count');
        (stage.labels || []).forEach((label, i) => {
            lines.push(`"${label}",${stage.values[i] ?? 0}`);
        });
        data = lines.join('\n');
        filename = `analytics_report_${new Date().toISOString().split('T')[0]}.csv`;
        mimeType = 'text/csv';
    } else {
        data = JSON.stringify(reportData, null, 2);
        filename = `analytics_report_${new Date().toISOString().split('T')[0]}.json`;
        mimeType = 'application/json';
    }

    const blob = new Blob([data], { type: mimeType });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    showAlert(`Data exported as ${format.toUpperCase()} successfully!`, 'success');
}

function showAlert(message, type) {
    const alertBox = document.getElementById('reportsAlert');
    alertBox.textContent = message;
    alertBox.className = `alert alert-${type}`;
    alertBox.classList.remove('d-none');
    setTimeout(() => {
        alertBox.classList.add('d-none');
    }, 5000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
}
</script>

<?php
renderFooter();
?> 