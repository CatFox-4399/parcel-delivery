<?php
/**
 * admin/dashboard.php — Admin Overview Dashboard
 *
 * Displays real-time KPI cards, recent activity feed,
 * and quick-action buttons. Stats auto-refresh every 30 seconds.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth('admin');

$pdo = db();

// ── Initial stats (AJAX will keep these fresh) ──────────────────────────────
$stats = [];

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

foreach ($queries as $key => $sql) {
    $stats[$key] = (int) $pdo->query($sql)->fetchColumn();
}

// ── Recent activity logs ─────────────────────────────────────────────────────
$recentLogs = $pdo->query(
    'SELECT al.action, al.details, al.created_at, u.name AS user_name, u.role
     FROM activity_logs al
     JOIN users u ON u.id = al.user_id
     ORDER BY al.created_at DESC
     LIMIT 10'
)->fetchAll();

// ── Unassigned parcels count ─────────────────────────────────────────────────
$unassigned = (int) $pdo->query(
    "SELECT COUNT(*) FROM parcels WHERE rider_id IS NULL AND status = 'pending'"
)->fetchColumn();

// ── Page setup ───────────────────────────────────────────────────────────────
$pageTitle    = 'Dashboard';
$activePage   = 'dashboard';
$role         = 'admin';
$extraScripts = ['/assets/js/dashboard.js'];
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<!-- CSRF token & base-url for JS -->
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="base-url"   content="<?= BASE_URL ?>">

<div class="section-header">
    <div>
        <div class="section-title">Good <?= date('H') < 12 ? 'morning' : (date('H') < 18 ? 'afternoon' : 'evening') ?>, <?= e(current_user_name()) ?> 👋</div>
        <div class="section-subtitle">Here's what's happening with your deliveries today.</div>
    </div>
    <div class="d-flex gap-3">
        <?php if ($unassigned > 0): ?>
        <a href="<?= BASE_URL ?>/admin/parcels.php?filter=unassigned" class="btn btn-secondary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= $unassigned ?> Unassigned
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/admin/parcel_create.php" class="btn btn-primary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Parcel
        </a>
    </div>
</div>

<!-- ── KPI Stat Cards ───────────────────────────────────────────────────────── -->
<div class="stats-grid">
    <div class="stat-card accent">
        <div class="stat-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value" data-stat="total_parcels"><?= $stats['total_parcels'] ?></div>
            <div class="stat-label">Total Parcels</div>
        </div>
    </div>

    <div class="stat-card warning">
        <div class="stat-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value" data-stat="pending"><?= $stats['pending'] ?></div>
            <div class="stat-label">Pending</div>
        </div>
    </div>

    <div class="stat-card info">
        <div class="stat-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value" data-stat="out_delivery"><?= $stats['out_delivery'] ?></div>
            <div class="stat-label">Out for Delivery</div>
        </div>
    </div>

    <div class="stat-card success">
        <div class="stat-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value" data-stat="delivered"><?= $stats['delivered'] ?></div>
            <div class="stat-label">Delivered</div>
        </div>
    </div>

    <div class="stat-card danger">
        <div class="stat-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value" data-stat="failed"><?= $stats['failed'] ?></div>
            <div class="stat-label">Failed</div>
        </div>
    </div>

    <div class="stat-card success">
        <div class="stat-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value" data-stat="total_riders"><?= $stats['total_riders'] ?></div>
            <div class="stat-label">Total Riders</div>
        </div>
    </div>

    <div class="stat-card success">
        <div class="stat-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value" data-stat="riders_online"><?= $stats['riders_online'] ?> <span id="onlineRiderBadge" style="display:none"></span></div>
            <div class="stat-label">Online Riders <span class="online-dot" style="margin-left:4px"></span></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="--stat-icon-bg:#f1f5f9;--stat-accent:#64748b">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="stroke:#64748b"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value" data-stat="riders_offline"><?= $stats['riders_offline'] ?></div>
            <div class="stat-label">Offline Riders</div>
        </div>
    </div>
</div>

<!-- ── Dashboard Grid: Activity + Quick Links ────────────────────────────────── -->
<div class="dashboard-grid">

    <!-- Recent Activity -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Recent Activity
            </div>
            <a href="<?= BASE_URL ?>/admin/activity_logs.php" class="btn btn-ghost btn-sm">View All</a>
        </div>

        <div class="log-feed">
            <?php if (empty($recentLogs)): ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                <h3>No activity yet</h3>
                <p>Actions will appear here as the system is used.</p>
            </div>
            <?php else: ?>
            <?php foreach ($recentLogs as $log): ?>
            <div class="log-item">
                <div class="log-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                </div>
                <div class="log-content">
                    <div class="log-action"><?= e($log['user_name']) ?> — <?= e(str_replace('_', ' ', $log['action'])) ?></div>
                    <?php if ($log['details']): ?>
                    <div class="log-details"><?= e($log['details']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="log-time"><?= time_ago($log['created_at']) ?></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                Quick Actions
            </div>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-3)">
                <a href="<?= BASE_URL ?>/admin/parcel_create.php" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    New Parcel
                </a>
                <a href="<?= BASE_URL ?>/admin/rider_map.php" class="btn btn-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
                    Live Map
                </a>
                <a href="<?= BASE_URL ?>/admin/parcels.php" class="btn btn-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                    All Parcels
                </a>
                <a href="<?= BASE_URL ?>/admin/reports.php" class="btn btn-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    Reports
                </a>
                <a href="<?= BASE_URL ?>/admin/riders.php" class="btn btn-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Riders
                </a>
                <a href="<?= BASE_URL ?>/admin/activity_logs.php" class="btn btn-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                    Activity Logs
                </a>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
