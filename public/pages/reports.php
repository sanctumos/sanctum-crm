<?php
/**
 * Reports & Analytics Page
 * FreeOpsDAO CRM
 */

// Remove any require_once for auth.php and layout.php

$auth = new Auth();
$auth->requireAuth();

// Render the page using the template system
renderHeader('Reports');
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
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-chart-bar"></i> Reports & Analytics</h2>
        <div class="export-buttons">
            <button class="btn btn-outline-success" onclick="exportData('csv')">
                <i class="fas fa-download"></i> Export CSV
            </button>
            <button class="btn btn-outline-primary" onclick="exportData('json')">
                <i class="fas fa-code"></i> Export JSON
            </button>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="filter-section shadow-sm">
        <div class="row">
            <div class="col-md-3">
                <label for="startDate" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="startDate">
            </div>
            <div class="col-md-3">
                <label for="endDate" class="form-label">End Date</label>
                <input type="date" class="form-control" id="endDate">
            </div>
            <div class="col-md-3">
                <label for="reportType" class="form-label">Report Type</label>
                <select class="form-select" id="reportType">
                    <option value="all">All Data</option>
                    <option value="deals">Deals Only</option>
                    <option value="contacts">Contacts Only</option>
                    <option value="users">User Activity</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button class="btn btn-primary" onclick="generateReport()">
                        <i class="fas fa-sync-alt"></i> Generate Report
                    </button>
                </div>
            </div>
        </div>
    </div>

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
                <h5><i class="fas fa-chart-pie"></i> Deals by Stage</h5>
                <div class="chart-container">
                    <canvas id="dealsByStageChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="metric-card">
                <h5><i class="fas fa-chart-line"></i> Pipeline Value by Stage</h5>
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
                <h5><i class="fas fa-users"></i> Contact Sources</h5>
                <div class="chart-container">
                    <canvas id="contactSourcesChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="metric-card">
                <h5><i class="fas fa-calendar"></i> Deals Over Time</h5>
                <div class="chart-container">
                    <canvas id="dealsOverTimeChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- User Activity Table -->
    <div class="metric-card">
        <h5><i class="fas fa-user-clock"></i> Recent User Activity</h5>
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
let reportData = {};
let charts = {};

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Set default date range (last 30 days)
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
        
        // Load all data
        const [dealsResponse, contactsResponse, usersResponse] = await Promise.all([
            fetch('/api/v1/deals'),
            fetch('/api/v1/contacts'),
            fetch('/api/v1/users')
        ]);
        
        const dealsData = await dealsResponse.json();
        const contactsData = await contactsResponse.json();
        const usersData = await usersResponse.json();
        
        if (dealsResponse.ok && contactsResponse.ok && usersResponse.ok) {
            reportData = {
                deals: dealsData.deals || [],
                contacts: contactsData.contacts || [],
                users: usersData.users || [],
                startDate: startDate,
                endDate: endDate
            };
            
            // Filter data by date range
            filterDataByDateRange();
            
            // Generate metrics and charts
            generateMetrics();
            generateCharts();
            generateActivityTable();
            
            showAlert('Report generated successfully!', 'success');
        } else {
            showAlert('Failed to load data for report', 'danger');
        }
    } catch (err) {
        showAlert('Network error while generating report', 'danger');
    }
}

function filterDataByDateRange() {
    const startDate = new Date(reportData.startDate);
    const endDate = new Date(reportData.endDate);
    endDate.setHours(23, 59, 59); // End of day
    
    // Filter deals by date range
    reportData.deals = reportData.deals.filter(deal => {
        const dealDate = new Date(deal.created_at);
        return dealDate >= startDate && dealDate <= endDate;
    });
    
    // Filter contacts by date range
    reportData.contacts = reportData.contacts.filter(contact => {
        const contactDate = new Date(contact.created_at);
        return contactDate >= startDate && contactDate <= endDate;
    });
}

function generateMetrics() {
    const deals = reportData.deals;
    const contacts = reportData.contacts;
    
    // Total deals
    document.getElementById('totalDeals').textContent = deals.length;
    
    // Total pipeline value
    const totalValue = deals.reduce((sum, deal) => sum + (parseFloat(deal.amount) || 0), 0);
    document.getElementById('totalValue').textContent = '$' + totalValue.toLocaleString();
    
    // Win rate
    const wonDeals = deals.filter(deal => deal.stage === 'closed_won').length;
    const closedDeals = deals.filter(deal => deal.stage === 'closed_won' || deal.stage === 'closed_lost').length;
    const winRate = closedDeals > 0 ? (wonDeals / closedDeals * 100).toFixed(1) : 0;
    document.getElementById('winRate').textContent = winRate + '%';
    
    // Average deal size
    const avgDealSize = deals.length > 0 ? totalValue / deals.length : 0;
    document.getElementById('avgDealSize').textContent = '$' + avgDealSize.toLocaleString(undefined, {maximumFractionDigits: 0});
}

function generateCharts() {
    generateDealsByStageChart();
    generatePipelineValueChart();
    generateContactSourcesChart();
    generateDealsOverTimeChart();
}

function generateDealsByStageChart() {
    const ctx = document.getElementById('dealsByStageChart').getContext('2d');
    
    // Destroy existing chart
    if (charts.dealsByStage) {
        charts.dealsByStage.destroy();
    }
    
    const stages = ['prospecting', 'qualification', 'proposal', 'negotiation', 'closed_won', 'closed_lost'];
    const stageCounts = stages.map(stage => 
        reportData.deals.filter(deal => deal.stage === stage).length
    );
    
    const colors = ['#6c757d', '#17a2b8', '#ffc107', '#007bff', '#28a745', '#dc3545'];
    
    charts.dealsByStage = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: stages.map(s => s.replace('_', ' ').toUpperCase()),
            datasets: [{
                data: stageCounts,
                backgroundColor: colors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

function generatePipelineValueChart() {
    const ctx = document.getElementById('pipelineValueChart').getContext('2d');
    
    if (charts.pipelineValue) {
        charts.pipelineValue.destroy();
    }
    
    const stages = ['prospecting', 'qualification', 'proposal', 'negotiation', 'closed_won', 'closed_lost'];
    const stageValues = stages.map(stage => {
        const stageDeals = reportData.deals.filter(deal => deal.stage === stage);
        return stageDeals.reduce((sum, deal) => sum + (parseFloat(deal.amount) || 0), 0);
    });
    
    const colors = ['#6c757d', '#17a2b8', '#ffc107', '#007bff', '#28a745', '#dc3545'];
    
    charts.pipelineValue = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: stages.map(s => s.replace('_', ' ').toUpperCase()),
            datasets: [{
                label: 'Pipeline Value ($)',
                data: stageValues,
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
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

function generateContactSourcesChart() {
    const ctx = document.getElementById('contactSourcesChart').getContext('2d');
    
    if (charts.contactSources) {
        charts.contactSources.destroy();
    }
    
    const sources = {};
    reportData.contacts.forEach(contact => {
        const source = contact.source || 'Unknown';
        sources[source] = (sources[source] || 0) + 1;
    });
    
    const labels = Object.keys(sources);
    const data = Object.values(sources);
    const colors = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6c757d', '#17a2b8', '#fd7e14'];
    
    charts.contactSources = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors.slice(0, labels.length),
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

function generateDealsOverTimeChart() {
    const ctx = document.getElementById('dealsOverTimeChart').getContext('2d');
    
    if (charts.dealsOverTime) {
        charts.dealsOverTime.destroy();
    }
    
    // Group deals by month
    const monthlyData = {};
    reportData.deals.forEach(deal => {
        const date = new Date(deal.created_at);
        const monthKey = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
        monthlyData[monthKey] = (monthlyData[monthKey] || 0) + 1;
    });
    
    const labels = Object.keys(monthlyData).sort();
    const data = labels.map(label => monthlyData[label]);
    
    charts.dealsOverTime = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Deals Created',
                data: data,
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
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

function generateActivityTable() {
    const tbody = document.querySelector('#activityTable tbody');
    tbody.innerHTML = '';
    
    // For now, we'll show recent deals as activity
    // In a real implementation, you'd have an activity log table
    const recentDeals = reportData.deals
        .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
        .slice(0, 10);
    
    recentDeals.forEach(deal => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>System</td>
            <td>Deal Created</td>
            <td>${escapeHtml(deal.title)}</td>
            <td>${new Date(deal.created_at).toLocaleDateString()}</td>
        `;
        tbody.appendChild(row);
    });
}

function exportData(format) {
    if (!reportData.deals || reportData.deals.length === 0) {
        showAlert('No data to export. Please generate a report first.', 'warning');
        return;
    }
    
    let data, filename, mimeType;
    
    if (format === 'csv') {
        // Convert deals to CSV
        const headers = ['ID', 'Title', 'Contact ID', 'Amount', 'Stage', 'Probability', 'Expected Close Date', 'Created At'];
        const csvContent = [
            headers.join(','),
            ...reportData.deals.map(deal => [
                deal.id,
                `"${deal.title}"`,
                deal.contact_id,
                deal.amount || '',
                deal.stage,
                deal.probability,
                deal.expected_close_date || '',
                deal.created_at
            ].join(','))
        ].join('\n');
        
        data = csvContent;
        filename = `deals_report_${new Date().toISOString().split('T')[0]}.csv`;
        mimeType = 'text/csv';
    } else {
        // JSON export
        data = JSON.stringify(reportData, null, 2);
        filename = `deals_report_${new Date().toISOString().split('T')[0]}.json`;
        mimeType = 'application/json';
    }
    
    // Create download link
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
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php
renderFooter();
?> 