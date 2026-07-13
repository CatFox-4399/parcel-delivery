<?php
/**
 * api/update_location.php — GPS Location Update Endpoint
 *
 * POST: latitude, longitude, accuracy (optional)
 * Rider only. Stores a new location row and updates last_seen.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Must be a POST from an authenticated rider
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.');
}

require_auth('rider');
require_csrf();

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$lat      = filter_var($input['latitude']  ?? '', FILTER_VALIDATE_FLOAT);
$lng      = filter_var($input['longitude'] ?? '', FILTER_VALIDATE_FLOAT);
$accuracy = isset($input['accuracy']) ? filter_var($input['accuracy'], FILTER_VALIDATE_FLOAT) : null;

if ($lat === false || $lng === false) {
    json_response(false, 'Invalid coordinates provided.');
}

// Bounds check (rough global latitude/longitude limits)
if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    json_response(false, 'Coordinates out of valid range.');
}

$pdo = db();

// Resolve rider ID from the session user
$stmt = $pdo->prepare('SELECT id FROM riders WHERE user_id = ?');
$stmt->execute([current_user_id()]);
$rider = $stmt->fetch();

if (!$rider) {
    json_response(false, 'Rider profile not found.');
}

$riderId = (int) $rider['id'];

// Insert new location record
$pdo->prepare(
    'INSERT INTO rider_locations (rider_id, latitude, longitude, accuracy) VALUES (?, ?, ?, ?)'
)->execute([$riderId, $lat, $lng, $accuracy > 0 ? $accuracy : null]);

// Keep last_seen updated on the rider record
$pdo->prepare(
    'UPDATE riders SET last_seen = NOW() WHERE id = ?'
)->execute([$riderId]);

json_response(true, 'Location recorded.', [
    'latitude'    => $lat,
    'longitude'   => $lng,
    'recorded_at' => date('Y-m-d H:i:s'),
]);
