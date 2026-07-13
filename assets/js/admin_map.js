/**
 * admin_map.js — Admin Live Rider Map
 *
 * Renders an interactive Leaflet.js map showing all online riders.
 * Polls the server every 15 seconds for fresh position data.
 * Depends on: Leaflet.js (loaded via CDN in header.php when $usesMap = true)
 */

'use strict';

const AdminMap = (() => {

    const POLL_INTERVAL = 15000; // ms between data refreshes
    const API_URL       = App.baseUrl + '/api/get_riders_map.php';

    let map          = null;
    let markers      = {};   // keyed by rider_id
    let riderList    = null; // sidebar list element
    let lastUpdated  = null;
    let pollTimer    = null;
    let focusedRider = null;

    // Custom marker for online riders — shows profile photo when available, initials otherwise
    function createRiderIcon(rider) {
        const initials  = (rider.name || 'R').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
        const avatarUrl = rider.avatar
            ? App.baseUrl + '/assets/uploads/avatars/' + encodeURIComponent(rider.avatar)
            : null;

        const innerHtml = avatarUrl
            ? `<img src="${avatarUrl}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;" onerror="this.parentElement.innerHTML='<span style=\'color:#fff;font-weight:700;font-size:13px;\'>${escHtml(initials)}</span>';">`
            : `<span style="color:#fff;font-weight:700;font-size:13px;font-family:'Inter',sans-serif;">${escHtml(initials)}</span>`;

        return L.divIcon({
            className: '',
            html: `
                <div style="
                    width:38px;height:38px;border-radius:50%;
                    background:linear-gradient(135deg,#1a2332,#334155);
                    border:3px solid #f97316;
                    display:flex;align-items:center;justify-content:center;
                    overflow:hidden;
                    box-shadow:0 4px 12px rgba(249,115,22,0.4),0 0 0 6px rgba(249,115,22,0.15);
                ">${innerHtml}</div>`,
            iconSize:   [38, 38],
            iconAnchor: [19, 19],
            popupAnchor: [0, -24],
        });
    }

    function buildPopupHtml(rider) {
        const lastSeen = rider.last_update
            ? `<strong>${rider.last_update}</strong>`
            : '—';

        return `
            <div class="rider-popup">
                <h4>${escHtml(rider.name)}</h4>
                <p>📱 ${escHtml(rider.phone || '—')}</p>
                <p>🚗 ${escHtml(rider.vehicle_type || '—')} · ${escHtml(rider.plate_number || '—')}</p>
                <p>🕒 Last update: ${lastSeen}</p>
                <p>📦 Active parcels: <strong>${rider.active_parcels ?? 0}</strong></p>
            </div>`;
    }

    /**
     * Initialise the Leaflet map.
     * @param {string} containerId  ID of the map <div>
     * @param {object} [center]     Initial center { lat, lng }
     */
    function initMap(containerId, center = { lat: 5.3992, lng: 100.3628 }) {
        map = L.map(containerId, {
            zoomControl: true,
            attributionControl: true,
        }).setView([center.lat, center.lng], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);

        riderList = document.getElementById('riderListPanel');

        // Start polling immediately, then on interval
        fetchAndRender();
        pollTimer = setInterval(fetchAndRender, POLL_INTERVAL);

        updateCountdown();
    }

    /**
     * Fetch rider positions from the API and re-render markers.
     */
    function fetchAndRender() {
        ajax(API_URL).then(res => {
            if (!res.success) return;

            const riders  = res.riders ?? [];
            const nowKeys = new Set();

            riders.forEach(rider => {
                const id  = String(rider.rider_id);
                const lat = parseFloat(rider.latitude);
                const lng = parseFloat(rider.longitude);

                if (isNaN(lat) || isNaN(lng)) return;

                nowKeys.add(id);

                const icon  = createRiderIcon(rider);
                const popup = buildPopupHtml(rider);

                if (markers[id]) {
                    // Smoothly move existing marker
                    markers[id].setLatLng([lat, lng]);
                    markers[id].setPopupContent(popup);
                } else {
                    // Create new marker
                    const marker = L.marker([lat, lng], { icon })
                        .bindPopup(popup)
                        .addTo(map);

                    marker.on('click', () => focusRider(id));
                    markers[id] = marker;
                }
            });

            // Remove markers for riders who went offline
            Object.keys(markers).forEach(id => {
                if (!nowKeys.has(id)) {
                    markers[id].remove();
                    delete markers[id];
                }
            });

            // Update sidebar list
            if (riderList) {
                renderRiderList(riders);
            }

            lastUpdated = new Date();
            updateLastUpdatedDisplay();

            // Update counter badge
            const badge = document.getElementById('onlineRiderCount');
            if (badge) badge.textContent = riders.length;

        }).catch(err => {
            console.error('[AdminMap] Poll error:', err);
        });
    }

    function renderRiderList(riders) {
        if (!riderList) return;

        if (riders.length === 0) {
            riderList.innerHTML = `
                <div class="empty-state" style="padding:2rem 1rem;">
                    <i data-feather="wifi-off" style="margin:0 auto 0.75rem;"></i>
                    <p style="font-size:0.8rem;color:var(--color-text-muted);">No online riders right now.</p>
                </div>`;
            if (typeof feather !== 'undefined') feather.replace({ 'stroke-width': 1.75 });
            return;
        }

        riderList.innerHTML = riders.map(rider => {
            const id        = String(rider.rider_id);
            const initials  = (rider.name || 'R').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
            const avatarUrl = rider.avatar
                ? App.baseUrl + '/assets/uploads/avatars/' + encodeURIComponent(rider.avatar)
                : null;
            const active    = focusedRider === id ? 'focused' : '';

            const avatarInner = avatarUrl
                ? `<img src="${avatarUrl}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;" onerror="this.style.display='none';this.parentElement.dataset.fallback='${escHtml(initials)}';"`  + `>`
                : escHtml(initials);

            return `
                <div class="map-rider-item ${active}" data-rider-id="${escHtml(id)}" onclick="AdminMap.focusRider('${escHtml(id)}')">
                    <div class="map-rider-avatar" style="overflow:hidden;">
                        ${avatarInner}
                        <span class="status-pip"></span>
                    </div>
                    <div class="map-rider-info">
                        <div class="map-rider-name">${escHtml(rider.name)}</div>
                        <div class="map-rider-meta">📦 ${rider.active_parcels ?? 0} parcel(s)</div>
                    </div>
                </div>`;
        }).join('');
    }

    /**
     * Pan map to a specific rider and open their popup.
     * @param {string} riderId
     */
    function focusRider(riderId) {
        focusedRider = riderId;
        const marker = markers[riderId];

        if (marker) {
            map.flyTo(marker.getLatLng(), 16, { animate: true, duration: 0.8 });
            marker.openPopup();
        }

        // Highlight in sidebar
        document.querySelectorAll('.map-rider-item').forEach(el => {
            el.classList.toggle('focused', el.getAttribute('data-rider-id') === riderId);
        });
    }

    function updateLastUpdatedDisplay() {
        const el = document.getElementById('mapLastUpdated');
        if (!el || !lastUpdated) return;
        el.textContent = lastUpdated.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }

    // Countdown to next refresh
    function updateCountdown() {
        const el = document.getElementById('mapCountdown');
        if (!el) return;

        let remaining = POLL_INTERVAL / 1000;

        const tick = () => {
            el.textContent = `Next refresh in ${remaining}s`;
            remaining -= 1;
            if (remaining < 0) remaining = POLL_INTERVAL / 1000;
        };

        tick();
        setInterval(tick, 1000);
    }

    /**
     * Stop all polling (e.g., when navigating away).
     */
    function destroy() {
        if (pollTimer) clearInterval(pollTimer);
        if (map) map.remove();
    }

    // Expose public API
    return { initMap, focusRider, destroy };

})();
