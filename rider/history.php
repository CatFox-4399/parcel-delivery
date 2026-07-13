<?php
/**
 * rider/history.php — Delivery History (Completed & Failed Parcels)
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth('rider');

$pdo    = db();
$userId = current_user_id();

$rider = $pdo->prepare('SELECT id FROM riders WHERE user_id = ?');
$rider->execute([$userId]);
$rider = $rider->fetch();
if (!$rider) redirect('/rider/dashboard.php');
$riderId = (int) $rider['id'];

$filter  = get_param('filter', 'all');  // all | delivered | failed
$page    = max(1, (int) get_param('page', '1'));
$perPage = 20;

$where  = ["p.rider_id = ?", "p.status IN ('delivered','failed')"];
$params = [$riderId];

if ($filter === 'delivered') {
    $where[]  = "p.status = 'delivered'";
} elseif ($filter === 'failed') {
    $where[]  = "p.status = 'failed'";
}

$whereSQL = implode(' AND ', $where);

$total = (function () use ($pdo, $whereSQL, $params) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM parcels p WHERE {$whereSQL}");
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
})();

$pg      = paginate($total, $perPage, $page);
$parcels = (function () use ($pdo, $whereSQL, $params, $perPage, $pg) {
    $sql = "SELECT * FROM parcels p WHERE {$whereSQL} ORDER BY updated_at DESC LIMIT {$perPage} OFFSET {$pg['offset']}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
})();

// Summary counts
$counts = $pdo->prepare(
    "SELECT
        SUM(status='delivered') AS delivered,
        SUM(status='failed')    AS failed
     FROM parcels WHERE rider_id = ? AND status IN ('delivered','failed')"
);
$counts->execute([$riderId]);
$counts = $counts->fetch();

$pageTitle  = 'Delivery History';
$activePage = 'history';
$role       = 'rider';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="section-header">
    <div class="section-title">Delivery History</div>
</div>

<!-- Summary Stats -->
<div class="stats-grid mb-6" style="grid-template-columns:repeat(2,1fr);max-width:400px;">
    <div class="stat-card success">
        <div class="stat-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
        <div class="stat-info">
            <div class="stat-value"><?= $counts['delivered'] ?? 0 ?></div>
            <div class="stat-label">Delivered</div>
        </div>
    </div>
    <div class="stat-card danger">
        <div class="stat-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
        <div class="stat-info">
            <div class="stat-value"><?= $counts['failed'] ?? 0 ?></div>
            <div class="stat-label">Failed</div>
        </div>
    </div>
</div>

<!-- Filter -->
<div style="display:flex;gap:var(--space-2);margin-bottom:var(--space-5);">
    <a href="?filter=all"       class="btn btn-sm <?= $filter === 'all'       ? 'btn-primary' : 'btn-secondary' ?>">All</a>
    <a href="?filter=delivered" class="btn btn-sm <?= $filter === 'delivered' ? 'btn-primary' : 'btn-secondary' ?>">Delivered</a>
    <a href="?filter=failed"    class="btn btn-sm <?= $filter === 'failed'    ? 'btn-primary' : 'btn-secondary' ?>">Failed</a>
</div>

<div class="card">
    <div class="log-feed">
        <?php if (empty($parcels)): ?>
        <div class="empty-state">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <h3>No history yet</h3>
            <p>Completed and failed deliveries will appear here.</p>
        </div>
        <?php else: ?>
        <?php foreach ($parcels as $p): ?>
        <div class="history-item">
            <div class="history-icon <?= $p['status'] ?>">
                <?php if ($p['status'] === 'delivered'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                <?php else: ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                <?php endif; ?>
            </div>
            <div class="history-info">
                <div class="history-tracking"><?= e($p['tracking_number']) ?></div>
                <div class="history-recipient"><?= e($p['recipient_name']) ?></div>
                <div class="text-xs text-muted" style="margin-top:2px;"><?= e($p['recipient_address']) ?></div>
            </div>
            <div class="history-time"><?= fmt_date($p['updated_at'], 'M d, Y') ?></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($pg['totalPages'] > 1): ?>
    <div class="pagination">
        <div class="pagination-info">Showing <?= $pg['offset'] + 1 ?>–<?= min($pg['offset'] + $perPage, $total) ?> of <?= $total ?></div>
        <div class="pagination-links">
            <?php for ($i = 1; $i <= $pg['totalPages']; $i++): ?>
            <a href="?page=<?= $i ?>&filter=<?= $filter ?>" class="page-link <?= $pg['page'] === $i ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
