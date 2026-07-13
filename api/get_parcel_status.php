<?php
/**
 * api/get_parcel_status.php — Get Parcel Status (Polling)
 *
 * GET: parcel_id
 * Returns current status and latest history entry.
 * Accessible by the owning rider or any admin.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Method not allowed.');
}

require_auth();  // Any authenticated user

$parcelId = (int) get_param('parcel_id');

if ($parcelId <= 0) {
    json_response(false, 'Invalid parcel ID.');
}

$pdo = db();

// Enforce ownership: riders can only poll their own parcels
if (current_role() === 'rider') {
    $check = $pdo->prepare(
        'SELECT p.id FROM parcels p
         JOIN riders r ON r.id = p.rider_id
         WHERE p.id = ? AND r.user_id = ?'
    );
    $check->execute([$parcelId, current_user_id()]);

    if (!$check->fetch()) {
        json_response(false, 'Parcel not found or access denied.');
    }
}

$parcel = $pdo->prepare('SELECT id, tracking_number, status, updated_at FROM parcels WHERE id = ?');
$parcel->execute([$parcelId]);
$parcel = $parcel->fetch();

if (!$parcel) {
    json_response(false, 'Parcel not found.');
}

// Latest history entry
$latest = $pdo->prepare(
    'SELECT status, remarks, created_at FROM parcel_status_history WHERE parcel_id = ? ORDER BY created_at DESC LIMIT 1'
);
$latest->execute([$parcelId]);
$latest = $latest->fetch();

json_response(true, '', [
    'parcel_id'      => $parcel['id'],
    'tracking'       => $parcel['tracking_number'],
    'status'         => $parcel['status'],
    'status_label'   => status_label($parcel['status']),
    'updated_at'     => $parcel['updated_at'],
    'latest_remarks' => $latest['remarks'] ?? null,
]);
