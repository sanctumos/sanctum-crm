<?php
/**
 * Sanctum CRM
 * 
 * This file is part of Sanctum CRM.
 * 
 * Copyright (C) 2025 Sanctum OS
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Layout Template System
 * Sanctum CRM - Consistent page layout
 */

// Prevent direct access
if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

// Get configuration for dynamic content
$config = ConfigManager::getInstance();
$companyInfo = $config->getCompanyInfo();
$appConfig = $config->getCategory('application');

// Get current user (if auth is available)
$user = null;
if (isset($auth) && $auth instanceof Auth) {
    $user = $auth->getUser();
}

// Get current page for navigation highlighting
$currentPage = $_GET['page'] ?? 'dashboard';

// Get database instance for stats
$db = Database::getInstance();

// Get statistics for dashboard
$stats = [];
$result = $db->fetchOne("SELECT COUNT(*) as count FROM contacts");
$stats['total_contacts'] = $result['count'];

$result = $db->fetchOne("SELECT COUNT(*) as count FROM contacts WHERE contact_type = 'lead'");
$stats['total_leads'] = $result['count'];

$result = $db->fetchOne("SELECT COUNT(*) as count FROM contacts WHERE contact_type = 'customer'");
$stats['total_customers'] = $result['count'];

$result = $db->fetchOne("SELECT COUNT(*) as count FROM deals");
$stats['total_deals'] = $result['count'];

$result = $db->fetchOne("SELECT SUM(amount) as total FROM deals WHERE amount IS NOT NULL");
$stats['total_deal_value'] = $result['total'] ?? 0;

// Recent contacts
$recent_contacts = $db->fetchAll("SELECT * FROM contacts ORDER BY created_at DESC LIMIT 5");

// Recent deals
$recent_deals = $db->fetchAll("SELECT * FROM deals ORDER BY created_at DESC LIMIT 5");

function renderHeader($title = null) {
    global $user, $auth, $currentPage;
    $pageTitle = $title ? $title . ' - ' . getAppName() : getAppName();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $pageTitle; ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
        <style>
            .sidebar {
                min-height: 100vh;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                transition: transform 0.3s ease;
            }
            .sidebar .nav-link {
                color: rgba(255, 255, 255, 0.8);
                border-radius: 10px;
                margin: 2px 0;
                transition: all 0.3s ease;
            }
            .sidebar .nav-link:hover,
            .sidebar .nav-link.active {
                color: white;
                background: rgba(255, 255, 255, 0.1);
                transform: translateX(5px);
            }
            .main-content {
                background: #f8f9fa;
                min-height: 100vh;
            }
            .stat-card {
                background: white;
                border-radius: 15px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
                transition: transform 0.3s ease;
            }
            .stat-card:hover {
                transform: translateY(-5px);
            }
            .stat-icon {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 24px;
                color: white;
            }
            .navbar-brand {
                font-weight: 700;
                font-size: 1.5rem;
            }
            .table {
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            }
            .btn-action {
                border-radius: 20px;
                padding: 8px 16px;
                font-size: 0.875rem;
            }
            
            /* Mobile hamburger menu styles */
            .hamburger-btn {
                display: none;
                background: none;
                border: none;
                color: #333;
                font-size: 1.5rem;
                padding: 0.5rem;
                cursor: pointer;
            }
            
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 1040;
            }
            
            @media (max-width: 767.98px) {
                .hamburger-btn {
                    display: block;
                }
                
                .sidebar {
                    position: fixed;
                    top: 0;
                    left: -100%;
                    width: 280px;
                    height: 100vh;
                    z-index: 1050;
                    transform: translateX(0);
                }
                
                .sidebar.show {
                    left: 0;
                }
                
                .sidebar-overlay.show {
                    display: block;
                }
                
                .main-content {
                    margin-left: 0;
                    width: 100%;
                }
                
                .col-md-9, .col-lg-10 {
                    flex: 0 0 100%;
                    max-width: 100%;
                }
            }
        </style>
    </head>
    <body>
        <!-- Sidebar Overlay for Mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <div class="container-fluid">
            <div class="row">
                <!-- Sidebar -->
                <div class="col-md-3 col-lg-2 px-0">
                    <div class="sidebar p-3" id="sidebar">
                        <div class="text-center mb-4">
                            <h4 class="text-white mb-0">
                                <i class="fas fa-users"></i> <?php echo htmlspecialchars(getAppName()); ?>
                            </h4>
                        </div>
                        
                        <nav class="nav flex-column">
                            <a class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>" href="/index.php">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                            <a class="nav-link <?php echo $currentPage === 'contacts' ? 'active' : ''; ?>" href="/index.php?page=contacts">
                                <i class="fas fa-address-book me-2"></i> Contacts
                            </a>
                            <a class="nav-link <?php echo $currentPage === 'deals' ? 'active' : ''; ?>" href="/index.php?page=deals">
                                <i class="fas fa-handshake me-2"></i> Deals
                            </a>
                            <?php if ($auth->isAdmin()): ?>
                            <a class="nav-link <?php echo $currentPage === 'users' ? 'active' : ''; ?>" href="/index.php?page=users">
                                <i class="fas fa-users-cog me-2"></i> Users
                            </a>
                            <a class="nav-link <?php echo $currentPage === 'settings' ? 'active' : ''; ?>" href="/index.php?page=settings">
                                <i class="fas fa-cog me-2"></i> Settings
                            </a>
                            <?php endif; ?>
                            <a class="nav-link <?php echo $currentPage === 'reports' ? 'active' : ''; ?>" href="/index.php?page=reports">
                                <i class="fas fa-chart-bar me-2"></i> Reports
                            </a>
                            <a class="nav-link <?php echo $currentPage === 'webhooks' ? 'active' : ''; ?>" href="/index.php?page=webhooks">
                                <i class="fas fa-link me-2"></i> Webhooks
                            </a>
                        </nav>
                    </div>
                </div>
                
                <!-- Main Content -->
                <div class="col-md-9 col-lg-10 px-0">
                    <div class="main-content p-4">
                        <!-- Top Navigation -->
                        <nav class="navbar navbar-expand-lg navbar-light bg-white rounded-3 mb-4 shadow-sm">
                            <div class="container-fluid">
                                <button class="hamburger-btn" id="hamburgerBtn">
                                    <i class="fas fa-bars"></i>
                                </button>
                                <span class="navbar-brand mb-0 h1"><?php echo $title ?? 'Dashboard'; ?></span>
                                <div class="navbar-nav ms-auto">
                                    <div class="nav-item dropdown">
                                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-user-circle"></i> 
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                        </a>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="/index.php?page=settings"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item" href="/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </nav>
    <?php
}

function renderFooter() {
    ?>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script>
            // Mobile hamburger menu functionality
            document.addEventListener('DOMContentLoaded', function() {
                const hamburgerBtn = document.getElementById('hamburgerBtn');
                const sidebar = document.getElementById('sidebar');
                const sidebarOverlay = document.getElementById('sidebarOverlay');
                
                function toggleSidebar() {
                    sidebar.classList.toggle('show');
                    sidebarOverlay.classList.toggle('show');
                }
                
                function closeSidebar() {
                    sidebar.classList.remove('show');
                    sidebarOverlay.classList.remove('show');
                }
                
                // Hamburger button click
                hamburgerBtn.addEventListener('click', toggleSidebar);
                
                // Overlay click to close
                sidebarOverlay.addEventListener('click', closeSidebar);
                
                // Close sidebar when clicking on nav links (mobile)
                const navLinks = sidebar.querySelectorAll('.nav-link');
                navLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        if (window.innerWidth <= 767.98) {
                            closeSidebar();
                        }
                    });
                });
                
                // Close sidebar on window resize if screen becomes larger
                window.addEventListener('resize', function() {
                    if (window.innerWidth > 767.98) {
                        closeSidebar();
                    }
                });
            });
        </script>
    </body>
    </html>
    <?php
}

function renderDashboardStats() {
    global $stats;
    ?>
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card p-4">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <h3 class="mb-0"><?php echo number_format($stats['total_contacts']); ?></h3>
                        <p class="text-muted mb-0">Total Contacts</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stat-card p-4">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div>
                        <h3 class="mb-0"><?php echo number_format($stats['total_leads']); ?></h3>
                        <p class="text-muted mb-0">Leads</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stat-card p-4">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div>
                        <h3 class="mb-0"><?php echo number_format($stats['total_customers']); ?></h3>
                        <p class="text-muted mb-0">Customers</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stat-card p-4">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <div>
                        <h3 class="mb-0">$<?php echo number_format($stats['total_deal_value'], 2); ?></h3>
                        <p class="text-muted mb-0">Deal Value</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function renderRecentActivity() {
    global $recent_contacts, $recent_deals;
    ?>
    <!-- Recent Activity -->
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Recent Contacts</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_contacts as $contact): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($contact['email']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $contact['contact_type'] === 'lead' ? 'warning' : 'success'; ?>">
                                            <?php echo ucfirst($contact['contact_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo ucfirst($contact['contact_status']); ?></span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('M j, Y', strtotime($contact['created_at'])); ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="/index.php?page=contacts" class="btn btn-sm btn-outline-primary">View All Contacts</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Recent Deals</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Deal</th>
                                    <th>Amount</th>
                                    <th>Stage</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_deals as $deal): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($deal['title']); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($deal['amount']): ?>
                                            <strong>$<?php echo number_format($deal['amount'], 2); ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo ucfirst(str_replace('_', ' ', $deal['stage'])); ?></span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('M j, Y', strtotime($deal['created_at'])); ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="/index.php?page=deals" class="btn btn-sm btn-outline-primary">View All Deals</a>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?> 