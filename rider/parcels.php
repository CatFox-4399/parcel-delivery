<?php
/**
 * rider/parcels.php — Rider's Assigned Parcel List
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

$statusFilter = get_param('status', 'active');
$search       = get_param('q', '');

$where  = ['p.rider_id = ?'];
$params = [$riderId];

if ($statusFilter === 'active') {
    $where[] = "p.status NOT IN ('delivered','failed')";
} elseif (in_array($statusFilter, ['pending','out_for_delivery','delivered','failed'])) {
    $where[]  = 'p.status = ?';
    $params[] = $statusFilter;
}

if ($search !== '') {
    $like     = "%{$search}%";
    $where[]  = '(p.tracking_number LIKE ? OR p.recipient_name LIKE ? OR p.recipient_address LIKE ?)';
    $params   = array_merge($params, [$like, $like, $like]);
}

$whereSQL = implode(' AND ', $where);
$parcels  = (function () use ($pdo, $whereSQL, $params) {
    $sql = "SELECT * FROM parcels p WHERE {$whereSQL} ORDER BY
            FIELD(status,'out_for_delivery','pending','delivered','failed'),
            updated_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
})();

$pageTitle  = 'My Parcels';
$activePage = 'parcels';
$role       = 'rider';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="section-header">
    <div>
        <div class="section-title">My Parcels</div>
        <div class="section-subtitle"><?= count($parcels) ?> parcel<?= count($parcels) !== 1 ? 's' : '' ?> found</div>
    </div>
</div>

<!-- Filter Tabs -->
<div style="display:flex;gap:var(--space-2);margin-bottom:var(--space-5);flex-wrap:wrap;">
    <?php
    $tabs = [
        ['key'=>'active',            'label'=>'Active'],
        ['key'=>'pending',           'label'=>'Pending'],
        ['key'=>'out_for_delivery',  'label'=>'Out for Delivery'],
        ['key'=>'delivered',         'label'=>'Delivered'],
        ['key'=>'failed',            'label'=>'Failed'],
    ];
    foreach ($tabs as $tab):
    ?>
    <a
        href="?status=<?= $tab['key'] ?>"
        class="btn btn-sm <?= $statusFilter === $tab['key'] ? 'btn-primary' : 'btn-secondary' ?>"
    >
        <?= $tab['label'] ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Search -->
<form method="GET" style="margin-bottom:var(--space-5);">
    <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
    <div class="search-input-wrap" style="max-width:400px;">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" name="q" class="form-control" placeholder="Search tracking #, recipient, address…" value="<?= e($search) ?>">
    </div>
</form>

<?php if (empty($parcels)): ?>
<div class="card">
    <div class="empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
        <h3>No parcels found</h3>
        <p>Nothing to show in this category right now.</p>
    </div>
</div>
<?php else: ?>
<div class="parcel-cards">
    <?php foreach ($parcels as $p): ?>
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
                <span class="parcel-meta-item text-muted">
                    <?= time_ago($p['updated_at']) ?>
                </span>
            </div>
            <?php if ($p['notes']): ?>
            <div style="margin-top:var(--space-3);padding:var(--space-3);background:var(--color-bg);border-radius:var(--radius-sm);font-size:var(--font-size-xs);color:var(--color-text-muted);">
                <strong>Note:</strong> <?= e($p['notes']) ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="parcel-card-footer">
            <?php if (!in_array($p['status'], ['delivered','failed'])): ?>
            <a href="<?= BASE_URL ?>/rider/parcel_update.php?id=<?= $p['id'] ?>" class="btn btn-primary btn-sm" style="flex:1;">
                Update Status
            </a>
            <?php else: ?>
            <a href="<?= BASE_URL ?>/rider/parcel_update.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm" style="flex:1;">
                View Details
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
