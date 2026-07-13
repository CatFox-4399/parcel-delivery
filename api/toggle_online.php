<?php
/**
 * api/toggle_online.php — Rider Online/Offline Status Toggle
 *
 * POST: online (1 or 0)
 * Rider only.
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

$input    = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$goOnline = isset($input['online']) ? (int) $input['online'] : -1;

if ($goOnline !== 0 && $goOnline !== 1) {
    json_response(false, 'Invalid online value. Must be 0 or 1.');
}

$pdo = db();

$stmt = $pdo->prepare('SELECT id FROM riders WHERE user_id = ?');
$stmt->execute([current_user_id()]);
$rider = $stmt->fetch();

if (!$rider) {
    json_response(false, 'Rider profile not found.');
}

$riderId = (int) $rider['id'];

$pdo->prepare(
    'UPDATE riders SET is_online = ?, last_seen = NOW() WHERE id = ?'
)->execute([$goOnline, $riderId]);

$action = $goOnline ? 'rider_online' : 'rider_offline';
log_activity(current_user_id(), $action, "Rider set status to " . ($goOnline ? 'online' : 'offline') . ".");

json_response(true, $goOnline ? 'You are now online.' : 'You are now offline.', [
    'is_online' => (bool) $goOnline,
]);
