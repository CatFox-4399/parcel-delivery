<?php
/**
 * api/get_dashboard_stats.php — Admin Dashboard Live Stats
 *
 * GET: Returns KPI counts for the admin dashboard stat cards.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Method not allowed.');
}

require_auth('admin');

$pdo = db();

$queries = [
    'total_riders'   => 'SELECT COUNT(*) FROM riders',
    'riders_online'  => 'SELECT COUNT(*) FROM riders WHERE is_online = 1',
    'riders_offline' => 'SELECT COUNT(*) FROM riders WHERE is_online = 0',
    'total_parcels'  => 'SELECT COUNT(*) FROM parcels',
    'pending'        => "SELECT COUNT(*) FROM parcels WHERE status = 'pending'",
    'out_delivery'   => "SELECT COUNT(*) FROM parcels WHERE status = 'out_for_delivery'",
    'delivered'      => "SELECT COUNT(*) FROM parcels WHERE status = 'delivered'",
    'failed'         => "SELECT COUNT(*) FROM parcels WHERE status = 'failed'",
];

$stats = [];
foreach ($queries as $key => $sql) {
    $stats[$key] = (int) $pdo->query($sql)->fetchColumn();
}

json_response(true, '', ['stats' => $stats]);
