<?php
/**
 * rider/dashboard.php — Rider Home Dashboard
 *
 * Hero section with online/offline toggle, GPS status,
 * today's delivery summary, and recent assigned parcels.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth('rider');

$pdo    = db();
$userId = current_user_id();

// Get rider record
$rider = $pdo->prepare('SELECT * FROM riders WHERE user_id = ?');
$rider->execute([$userId]);
$rider = $rider->fetch();

if (!$rider) {
    // Create a minimal rider record if one doesn't exist yet
    $pdo->prepare('INSERT INTO riders (user_id, phone, vehicle_type, plate_number) VALUES (?, \'\', \'\', \'\')')->execute([$userId]);
    $rider = $pdo->prepare('SELECT * FROM riders WHERE user_id = ?');
    $rider->execute([$userId]);
    $rider = $rider->fetch();
}

$riderId = (int) $rider['id'];

// Today's parcel stats
$today = date('Y-m-d');
$todayStats = $pdo->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(status = 'out_for_delivery') AS active,
        SUM(status = 'delivered')        AS delivered,
        SUM(status = 'failed')           AS failed
     FROM parcels
     WHERE rider_id = ? AND DATE(updated_at) = ?"
);
$todayStats->execute([$riderId, $today]);
$todayStats = $todayStats->fetch();

// Active parcels (latest 5)
$activeParcels = $pdo->prepare(
    "SELECT * FROM parcels
     WHERE rider_id = ? AND status NOT IN ('delivered','failed')
     ORDER BY updated_at DESC LIMIT 5"
);
$activeParcels->execute([$riderId]);
$activeParcels = $activeParcels->fetchAll();

$pageTitle    = 'My Dashboard';
$activePage   = 'dashboard';
$role         = 'rider';
$extraScripts = ['/assets/js/tracking.js'];
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="base-url"   content="<?= BASE_URL ?>">

<!-- Hero Section -->
<div class="rider-hero">
    <div class="hero-greeting">
        <h2>Hey, <?= e(explode(' ', current_user_name())[0]) ?> 👋</h2>
        <p><?= date('l, F j, Y') ?></p>

        <!-- GPS Status -->
        <div style="margin-top:var(--space-4);display:flex;align-items:center;gap:var(--space-3);flex-wrap:wrap;">
            <span class="gps-status offline" id="gpsStatus">
                <span class="gps-pulse"></span>Offline
            </span>
            <span style="font-size:var(--font-size-xs);color:rgba(255,255,255,0.5);">
                Last location update: <span id="lastUpdateTime">—</span>
            </span>
        </div>
    </div>

    <div class="hero-actions">
        <!-- Online/Offline Toggle -->
        <button
            class="online-toggle <?= $rider['is_online'] ? 'is-online' : 'is-offline' ?>"
            id="onlineToggleBtn"
            type="button"
        >
            <span class="toggle-switch <?= $rider['is_online'] ? 'active' : '' ?>">
                <span class="toggle-knob"></span>
            </span>
            <span class="toggle-label"><?= $rider['is_online'] ? 'Online' : 'Offline' ?></span>
        </button>

        <!-- Today's Stats -->
        <div class="hero-stats">
            <div class="hero-stat">
                <div class="hero-stat-value"><?= $todayStats['delivered'] ?? 0 ?></div>
                <div class="hero-stat-label">Delivered</div>
            </div>
            <div class="hero-stat">
                <div class="hero-stat-value"><?= $todayStats['active'] ?? 0 ?></div>
                <div class="hero-stat-label">Active</div>
            </div>
            <div class="hero-stat">
                <div class="hero-stat-value"><?= $todayStats['failed'] ?? 0 ?></div>
                <div class="hero-stat-label">Failed</div>
            </div>
        </div>
    </div>
</div>

<!-- Active Parcels -->
<div class="section-header">
    <div class="section-title">Active Parcels</div>
    <a href="<?= BASE_URL ?>/rider/parcels.php" class="btn btn-secondary btn-sm">View All</a>
</div>

<?php if (empty($activeParcels)): ?>
<div class="card">
    <div class="empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
        <h3>No active parcels</h3>
        <p>You have no parcels assigned right now. Check back soon!</p>
    </div>
</div>
<?php else: ?>
<div class="parcel-cards">
    <?php foreach ($activeParcels as $p): ?>
    <div class="parcel-card">
        <div class="parcel-card-header">
            <span class="parcel-tracking"><?= e($p['tracking_number']) ?></span>
            <span class="badge <?= status_class($p['status']) ?>">
                <span class="badge-dot"></span>
                <?= status_label($p['status']) ?>
            </span>
        </div>
        <div class="parcel-card-body">
            <div class="parcel-recipient"><?= e($p['recipient_name']) ?></div>
            <div class="parcel-address">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <?= e($p['recipient_address']) ?>
            </div>
            <div class="parcel-meta">
                <span class="parcel-meta-item">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 2.18h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.27a16 16 0 0 0 5.82 5.82l.89-.89a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7a2 2 0 0 1 1.72 2z"/></svg>
                    <?= e($p['recipient_phone']) ?>
                </span>
                <?php if ($p['weight']): ?>
                <span class="parcel-meta-item">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?= e($p['weight']) ?>kg
                </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="parcel-card-footer">
            <a href="<?= BASE_URL ?>/rider/parcel_update.php?id=<?= $p['id'] ?>" class="btn btn-primary btn-sm" style="flex:1;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Update Status
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    Tracking.init({ currentlyOnline: <?= $rider['is_online'] ? 'true' : 'false' ?> });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
