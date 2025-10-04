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
 * Dashboard Page
 * Sanctum CRM - Main Dashboard
 */

// Get database instance and auth
$db = Database::getInstance();
$auth = new Auth();
$auth->requireAuth();

// Calculate dashboard statistics
$stats = [];

// Total contacts
$total_contacts = $db->fetchOne("SELECT COUNT(*) as count FROM contacts")['count'];
$stats['total_contacts'] = $total_contacts;

// Total leads
$total_leads = $db->fetchOne("SELECT COUNT(*) as count FROM contacts WHERE contact_type = 'lead'")['count'];
$stats['total_leads'] = $total_leads;

// Total customers
$total_customers = $db->fetchOne("SELECT COUNT(*) as count FROM contacts WHERE contact_type = 'customer'")['count'];
$stats['total_customers'] = $total_customers;

// Total deal value
$total_deal_value = $db->fetchOne("SELECT SUM(amount) as total FROM deals WHERE stage IN ('won', 'closed')")['total'] ?? 0;
$stats['total_deal_value'] = $total_deal_value;

// Enriched contacts
$enriched_contacts = $db->fetchOne("SELECT COUNT(*) as count FROM contacts WHERE enrichment_status = 'enriched'")['count'];
$stats['enriched_contacts'] = $enriched_contacts;

// Render the page using the template system
renderHeader('Dashboard');
renderDashboardStats();
renderRecentActivity();
renderFooter();
?> 