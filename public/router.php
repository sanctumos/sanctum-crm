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

// public/router.php
file_put_contents(__DIR__ . '/router.log', date('c') . ' ' . $_SERVER['REQUEST_URI'] . "\n", FILE_APPEND);
if (preg_match('/^\/api\/v1\//', $_SERVER['REQUEST_URI'])) {
    require __DIR__ . '/api/v1/index.php';
    exit;
}
$path = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (file_exists($path) && !is_dir($path)) {
    return false; // serve the requested static file
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Not found', 'uri' => $_SERVER['REQUEST_URI']]);
    exit;
} 