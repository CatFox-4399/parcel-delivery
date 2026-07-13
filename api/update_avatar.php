<?php
/**
 * api/update_avatar.php — Upload / Update a Rider's Profile Picture
 *
 * POST params:
 *   csrf_token  string  — CSRF token
 *   rider_id    int     — target rider's user ID (admin only); omit to update own avatar (rider)
 *   avatar      file    — JPEG/PNG/WebP image, max 5 MB
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_csrf();

$pdo        = db();
$currentId  = current_user_id();
$currentRole = current_role();

// Determine which user's avatar we're updating
if ($currentRole === 'admin' && !empty($_POST['user_id'])) {
    $targetUserId = (int) $_POST['user_id'];
} elseif ($currentRole === 'rider' || $currentRole === 'admin') {
    $targetUserId = $currentId;
} else {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

// Admins can edit any user; riders can only edit themselves
if ($currentRole === 'rider' && $targetUserId !== $currentId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

// Validate uploaded file
if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['avatar']['error'] ?? -1;
    echo json_encode(['success' => false, 'message' => "Upload error (code {$errCode}). Please try again."]);
    exit;
}

$file = $_FILES['avatar'];

if ($file['size'] > MAX_FILE_SIZE) {
    echo json_encode(['success' => false, 'message' => 'File exceeds 5 MB limit.']);
    exit;
}

$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
$allowed  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

if (!isset($allowed[$mimeType])) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, and WebP are allowed.']);
    exit;
}

// Create upload directory for avatars
$avatarDir = __DIR__ . '/../assets/uploads/avatars/';
if (!is_dir($avatarDir)) {
    mkdir($avatarDir, 0755, true);
}

// Remove old avatar if it exists
$oldAvatar = $pdo->prepare('SELECT avatar FROM users WHERE id = ?');
$oldAvatar->execute([$targetUserId]);
$oldAvatarRow = $oldAvatar->fetch();
if ($oldAvatarRow && $oldAvatarRow['avatar']) {
    $oldPath = $avatarDir . basename($oldAvatarRow['avatar']);
    if (file_exists($oldPath)) {
        @unlink($oldPath);
    }
}

// Save new file
$ext      = $allowed[$mimeType];
$filename = 'avatar_' . $targetUserId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$dest     = $avatarDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save the uploaded file.']);
    exit;
}

// Update DB
$pdo->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$filename, $targetUserId]);

// Keep session in sync so sidebar updates without re-login
if ($targetUserId === $currentId) {
    $_SESSION['user_avatar'] = $filename;
}

log_activity(
    $currentId,
    'avatar_updated',
    "Updated profile picture for user ID #{$targetUserId}."
);

$avatarUrl = BASE_URL . '/assets/uploads/avatars/' . rawurlencode($filename);
echo json_encode(['success' => true, 'message' => 'Profile picture updated.', 'url' => $avatarUrl]);
