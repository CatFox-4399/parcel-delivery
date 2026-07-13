<?php
/**
 * admin/rider_map.php — Live Rider Location Map
 *
 * Shows all online riders on an interactive Leaflet.js map.
 * Default centre: Butterworth, Penang, Malaysia.
 * Map data auto-refreshes every 15 seconds via admin_map.js.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth('admin');

$pdo = db();

// Online rider count for the panel header
$onlineCount = (int) $pdo->query('SELECT COUNT(*) FROM riders WHERE is_online = 1')->fetchColumn();

$pageTitle    = 'Live Rider Map';
$activePage   = 'map';
$role         = 'admin';
$usesMap      = true;
$extraScripts = ['/assets/js/admin_map.js'];
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="base-url"   content="<?= BASE_URL ?>">

<div class="section-header">
    <div>
        <div class="section-title">Live Rider Map</div>
        <div class="section-subtitle">Real-time GPS positions of all online delivery riders.</div>
    </div>
    <div class="d-flex align-center gap-3">
        <span class="badge badge-success">
            <span class="badge-dot"></span>
            <span id="onlineRiderCount"><?= $onlineCount ?></span> Online
        </span>
        <a href="<?= BASE_URL ?>/admin/riders.php" class="btn btn-secondary btn-sm">View All Riders</a>
    </div>
</div>

<div class="map-layout">
    <!-- Sidebar Panel -->
    <div class="map-panel">
        <div class="map-panel-header">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            Online Riders
        </div>
        <div class="map-rider-list" id="riderListPanel">
            <?php if ($onlineCount === 0): ?>
            <div class="empty-state" style="padding:2rem 1rem;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto 0.75rem;stroke:var(--color-border-dark)"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>
                <p style="font-size:0.8rem;color:var(--color-text-muted);">No riders online right now.</p>
            </div>
            <?php endif; ?>
        </div>
        <div class="map-refresh-bar">
            <span><span class="map-refresh-dot"></span> Live tracking</span>
            <span id="mapCountdown">—</span>
        </div>
    </div>

    <!-- Map -->
    <div class="map-container">
        <div id="riderMap" style="width:100%;height:100%;"></div>
    </div>
</div>

<div style="margin-top:var(--space-3);font-size:var(--font-size-xs);color:var(--color-text-muted);text-align:right;">
    Last updated: <span id="mapLastUpdated">—</span>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Centre on Butterworth, Penang, Malaysia.
    AdminMap.initMap('riderMap', { lat: 5.3992, lng: 100.3628 });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
