/**
 * dashboard.js — Admin Dashboard Live Stats
 *
 * Polls /api/get_dashboard_stats.php every 30 seconds and updates
 * the stat card counters with a smooth count-up animation.
 */

'use strict';

const Dashboard = (() => {

    const API_URL      = App.baseUrl + '/api/get_dashboard_stats.php';
    const POLL_MS      = 30000;
    let   pollTimer    = null;

    /**
     * Animate a number counter from its current displayed value to the target.
     * @param {HTMLElement} el
     * @param {number}      target
     * @param {number}      [duration=600]
     */
    function animateCounter(el, target, duration = 600) {
        const start     = parseInt(el.textContent.replace(/\D/g, ''), 10) || 0;
        const delta     = target - start;
        const startTime = performance.now();

        if (delta === 0) return;

        function step(now) {
            const elapsed  = now - startTime;
            const progress = Math.min(elapsed / duration, 1);
            // Ease out cubic
            const eased    = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.round(start + delta * eased);

            if (progress < 1) requestAnimationFrame(step);
        }

        requestAnimationFrame(step);
    }

    /**
     * Fetch stats from the API and update card elements.
     */
    function fetchStats() {
        ajax(API_URL).then(res => {
            if (!res.success || !res.stats) return;

            const stats = res.stats;

            // Update each stat card that has a matching [data-stat] attribute
            document.querySelectorAll('[data-stat]').forEach(el => {
                const key   = el.getAttribute('data-stat');
                const value = stats[key];
                if (value !== undefined) {
                    animateCounter(el, parseInt(value, 10) || 0);
                }
            });

            // Update page-title suffix with online count
            const onlineEl = document.getElementById('onlineRiderBadge');
            if (onlineEl && stats.riders_online !== undefined) {
                onlineEl.textContent = stats.riders_online;
            }

        }).catch(err => {
            console.error('[Dashboard] Stats poll error:', err);
        });
    }

    /**
     * Start the dashboard polling loop.
     */
    function init() {
        fetchStats();
        pollTimer = setInterval(fetchStats, POLL_MS);
    }

    function destroy() {
        if (pollTimer) clearInterval(pollTimer);
    }

    return { init, destroy, fetchStats };

})();

// Auto-start on pages that include this script
document.addEventListener('DOMContentLoaded', () => Dashboard.init());
