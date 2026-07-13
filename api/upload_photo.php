<?php
/**
 * api/upload_photo.php — Delivery Proof Photo Upload Endpoint
 *
 * POST (multipart/form-data): photo (file), parcel_id
 * Rider only. Validates, stores, and records the photo in the DB.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.');
}

require_auth('rider');
require_csrf();

$parcelId = (int) ($_POST['parcel_id'] ?? 0);

if ($parcelId <= 0) {
    json_response(false, 'Invalid parcel ID.');
}

if (empty($_FILES['photo']) || $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE) {
    json_response(false, 'No photo was uploaded.');
}

$pdo = db();

// Verify rider owns the parcel
$stmt = $pdo->prepare(
    'SELECT r.id AS rider_id FROM parcels p
     JOIN riders r ON r.id = p.rider_id
     WHERE p.id = ? AND r.user_id = ?'
);
$stmt->execute([$parcelId, current_user_id()]);
$result = $stmt->fetch();

if (!$result) {
    json_response(false, 'Parcel not found or access denied.');
}

$riderId   = (int) $result['rider_id'];
$uploadDir = UPLOAD_DIR;

// Ensure upload directory exists
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        json_response(false, 'Could not create upload directory. Check server permissions.');
    }
}

try {
    $filename = handle_photo_upload($_FILES['photo'], $uploadDir);
} catch (RuntimeException $e) {
    json_response(false, $e->getMessage());
}

// Record in database
$pdo->prepare(
    'INSERT INTO delivery_photos (parcel_id, rider_id, photo_path) VALUES (?, ?, ?)'
)->execute([$parcelId, $riderId, $filename]);

log_activity(
    current_user_id(),
    'photo_uploaded',
    "Proof photo uploaded for parcel #{$parcelId}: {$filename}"
);

json_response(true, 'Photo uploaded successfully.', [
    'filename' => $filename,
    'url'      => UPLOAD_URL . $filename,
]);
