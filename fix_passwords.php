<?php
/**
 * fix_passwords.php — Reset Demo Account Passwords
 *
 * Run this ONCE if login fails after importing schema.sql directly.
 * The schema.sql contains placeholder hashes; this script regenerates
 * them correctly using PHP's password_hash().
 *
 * URL: http://localhost/parcel_delivery_system/fix_passwords.php
 *
 * DELETE THIS FILE after running it.
 */

// Allow only localhost
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    http_response_code(403);
    die('Access denied.');
}

require_once __DIR__ . '/config/db.php';

$accounts = [
    ['admin@parcel.local',  'Admin@1234', 'admin'],
    ['rider@parcel.local',  'Rider@1234', 'rider'],
    ['rider2@parcel.local', 'Rider@1234', 'rider'],
];

$pdo    = db();
$log    = [];
$errors = [];

foreach ($accounts as [$email, $plainPw, $role]) {
    // Check if user exists
    $stmt = $pdo->prepare('SELECT id, name FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // User doesn't exist — create them
        $hash = password_hash($plainPw, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare(
            'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)'
        )->execute([ucfirst($role) . ' Account', $email, $hash, $role]);

        $userId = (int) $pdo->lastInsertId();

        // If rider, create rider profile too
        if ($role === 'rider') {
            $pdo->prepare(
                'INSERT IGNORE INTO riders (user_id, phone, vehicle_type, plate_number) VALUES (?, ?, ?, ?)'
            )->execute([$userId, '', 'Motorcycle', '']);
        }

        $log[] = [
            'email'  => $email,
            'status' => 'created',
            'name'   => $user['name'] ?? ($role . ' Account'),
        ];
    } else {
        // User exists — update password
        $hash = password_hash($plainPw, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare('UPDATE users SET password = ? WHERE email = ?')->execute([$hash, $email]);

        // Ensure rider profile exists
        if ($role === 'rider') {
            $check = $pdo->prepare('SELECT id FROM riders WHERE user_id = ?');
            $check->execute([(int)$user['id']]);
            if (!$check->fetch()) {
                $pdo->prepare(
                    'INSERT INTO riders (user_id, phone, vehicle_type, plate_number) VALUES (?, ?, ?, ?)'
                )->execute([(int)$user['id'], '', 'Motorcycle', '']);
            }
        }

        $log[] = [
            'email'  => $email,
            'status' => 'updated',
            'name'   => $user['name'],
        ];
    }

    // Verify the hash works
    $verify = $pdo->prepare('SELECT password FROM users WHERE email = ?');
    $verify->execute([$email]);
    $stored = $verify->fetchColumn();

    if (!password_verify($plainPw, $stored)) {
        $errors[] = "❌ Verification FAILED for {$email}";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Passwords — ParcelTrack Pro</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 640px; margin: 40px auto; padding: 0 20px; background: #f0f2f5; color: #1e293b; }
        h1  { font-size: 1.5rem; font-weight: 800; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 16px; }
        .ok   { color: #15803d; font-weight: 600; }
        .err  { color: #dc2626; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; font-weight: 700; color: #64748b; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.06em; }
        code { background: #f1f5f9; padding: 2px 8px; border-radius: 4px; font-family: monospace; font-size: 0.9em; }
        .btn { display: inline-block; background: #f97316; color: #fff; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 700; margin-top: 12px; }
        .warn { background: #fef2f2; border: 1px solid #fca5a5; border-radius: 8px; padding: 12px 16px; color: #991b1b; font-size: 0.875rem; margin-top: 16px; }
    </style>
</head>
<body>
<h1>🔧 Password Fix — ParcelTrack Pro</h1>

<?php if (!empty($errors)): ?>
<div class="card">
    <?php foreach ($errors as $err): ?>
    <div class="err"><?= htmlspecialchars($err) ?></div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card">
    <div class="ok" style="margin-bottom:12px;">✅ All passwords updated and verified successfully.</div>
    <table>
        <tr><th>Email</th><th>Password</th><th>Action</th></tr>
        <?php foreach ($log as $entry): ?>
        <tr>
            <td><?= htmlspecialchars($entry['email']) ?></td>
            <td>
                <?php if (str_contains($entry['email'], 'admin')): ?>
                <code>Admin@1234</code>
                <?php else: ?>
                <code>Rider@1234</code>
                <?php endif; ?>
            </td>
            <td class="ok"><?= $entry['status'] === 'created' ? '✅ Created' : '🔄 Updated' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <a class="btn" href="/parcel_delivery_system/login.php">→ Go to Login</a>

    <div class="warn">
        ⚠️ <strong>Delete this file</strong> now: <code>fix_passwords.php</code>
    </div>
</div>
<?php endif; ?>

</body>
</html>
