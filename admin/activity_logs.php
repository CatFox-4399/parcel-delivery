<?php
/**
 * admin/activity_logs.php — System Activity Log Viewer
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth('admin');

$pdo    = db();
$search = get_param('q');
$userId = get_param('user', '');
$page   = max(1, (int) get_param('page', '1'));
$perPage = 30;

$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $like     = "%{$search}%";
    $where[]  = '(al.action LIKE ? OR al.details LIKE ? OR u.name LIKE ?)';
    $params   = array_merge($params, [$like, $like, $like]);
}

if ($userId !== '') {
    $where[]  = 'al.user_id = ?';
    $params[] = (int) $userId;
}

$whereSQL = implode(' AND ', $where);

$total = (function () use ($pdo, $whereSQL, $params) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs al JOIN users u ON u.id = al.user_id WHERE {$whereSQL}");
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
})();

$pg   = paginate($total, $perPage, $page);
$logs = (function () use ($pdo, $whereSQL, $params, $perPage, $pg) {
    $sql = "SELECT al.*, u.name AS user_name, u.role
            FROM activity_logs al
            JOIN users u ON u.id = al.user_id
            WHERE {$whereSQL}
            ORDER BY al.created_at DESC
            LIMIT {$perPage} OFFSET {$pg['offset']}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
})();

$users = $pdo->query('SELECT id, name FROM users ORDER BY name')->fetchAll();

// Map actions to icons
function action_icon(string $action): string {
    if (str_contains($action, 'login'))    return 'log-in';
    if (str_contains($action, 'logout'))   return 'log-out';
    if (str_contains($action, 'create') || str_contains($action, 'created'))  return 'plus-circle';
    if (str_contains($action, 'update') || str_contains($action, 'updated'))  return 'edit-2';
    if (str_contains($action, 'delete') || str_contains($action, 'deleted'))  return 'trash-2';
    if (str_contains($action, 'assign'))   return 'user-plus';
    if (str_contains($action, 'status'))   return 'refresh-cw';
    if (str_contains($action, 'photo'))    return 'camera';
    return 'activity';
}

$pageTitle  = 'Activity Logs';
$activePage = 'logs';
$role       = 'admin';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="section-header">
    <div>
        <div class="section-title">Activity Logs</div>
        <div class="section-subtitle"><?= number_format($total) ?> entries recorded</div>
    </div>
</div>

<div class="card">
    <!-- Filter Bar -->
    <div class="table-toolbar">
        <form method="GET" style="display:flex;gap:var(--space-3);flex-wrap:wrap;flex:1;">
            <div class="search-input-wrap" style="flex:1;min-width:200px;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="q" class="form-control" placeholder="Search action, details, user…" value="<?= e($search) ?>">
            </div>
            <select name="user" class="form-control" style="width:180px;" onchange="this.form.submit()">
                <option value="">All Users</option>
                <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $userId == $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">Search</button>
            <?php if ($search || $userId): ?>
            <a href="<?= BASE_URL ?>/admin/activity_logs.php" class="btn btn-ghost btn-sm">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Log Feed -->
    <div class="log-feed">
        <?php if (empty($logs)): ?>
        <div class="empty-state">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            <h3>No log entries found</h3>
            <p>Activity will appear here as the system is used.</p>
        </div>
        <?php else: ?>
        <?php foreach ($logs as $log): ?>
        <div class="log-item">
            <div class="log-icon">
                <i data-feather="<?= action_icon($log['action']) ?>"></i>
            </div>
            <div class="log-content">
                <div class="log-action">
                    <strong><?= e($log['user_name']) ?></strong>
                    <span class="badge badge-secondary" style="margin-left:6px;font-size:0.65rem;"><?= e($log['role']) ?></span>
                    — <?= e(str_replace('_', ' ', $log['action'])) ?>
                </div>
                <?php if ($log['details']): ?>
                <div class="log-details"><?= e($log['details']) ?></div>
                <?php endif; ?>
                <div style="font-size:var(--font-size-xs);color:var(--color-text-light);margin-top:2px;">
                    IP: <?= e($log['ip_address']) ?>
                </div>
            </div>
            <div class="log-time" title="<?= e($log['created_at']) ?>">
                <?= fmt_date($log['created_at'], 'M d h:i A') ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($pg['totalPages'] > 1): ?>
    <div class="pagination">
        <div class="pagination-info">Showing <?= $pg['offset'] + 1 ?>–<?= min($pg['offset'] + $perPage, $total) ?> of <?= $total ?></div>
        <div class="pagination-links">
            <?php for ($i = 1; $i <= $pg['totalPages']; $i++): ?>
            <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&user=<?= $userId ?>" class="page-link <?= $pg['page'] === $i ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
