<?php
/**
 * api/get_riders_map.php — Active Riders Data for Admin Map
 *
 * GET: Returns JSON array of all online riders with their latest GPS position.
 * Admin only.
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

// Fetch all online riders with their most recent location
$riders = $pdo->query(
    "SELECT
        r.id        AS rider_id,
        u.name,
        u.avatar,
        r.phone,
        r.vehicle_type,
        r.plate_number,
        r.last_seen,
        rl.latitude,
        rl.longitude,
        rl.accuracy,
        rl.recorded_at AS last_update,
        (SELECT COUNT(*) FROM parcels
         WHERE rider_id = r.id AND status = 'out_for_delivery') AS active_parcels
     FROM riders r
     JOIN users u ON u.id = r.user_id
     -- Latest location via correlated subquery
     LEFT JOIN rider_locations rl ON rl.id = (
         SELECT id FROM rider_locations
         WHERE rider_id = r.id
         ORDER BY recorded_at DESC
         LIMIT 1
     )
     WHERE r.is_online = 1
       AND rl.latitude IS NOT NULL
     ORDER BY u.name"
)->fetchAll();

// Format timestamps for display
$riders = array_map(function ($r) {
    $r['last_update'] = $r['last_update']
        ? (new DateTimeImmutable($r['last_update']))->format('h:i:s A')
        : null;
    return $r;
}, $riders);

json_response(true, '', ['riders' => $riders]);
