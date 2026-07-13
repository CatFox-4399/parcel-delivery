/**
 * app.js — ParcelTrack Pro Core JavaScript
 *
 * Provides: AJAX helper, toast notifications, sidebar toggle,
 * live clock, CSRF token, modal helpers, and confirmation dialogs.
 */

'use strict';

/* =============================================================
   CSRF Token
   ============================================================= */
const App = {
    /**
     * CSRF token injected into the page via a meta tag.
     * Usage: <meta name="csrf-token" content="<?= csrf_token() ?>">
     */
    get csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    },

    baseUrl: document.querySelector('meta[name="base-url"]')?.getAttribute('content') ?? '',
};

/* =============================================================
   AJAX Helper
   ============================================================= */

/**
 * Send an AJAX request and return a Promise resolving to the parsed JSON.
 *
 * @param {string} url
 * @param {object} options
 * @param {string} [options.method='GET']
 * @param {object|FormData|null} [options.data=null]   Body payload
 * @param {boolean} [options.withCsrf=true]            Attach X-CSRF-Token header
 * @returns {Promise<{success: boolean, message: string, [key: string]: any}>}
 */
function ajax(url, { method = 'GET', data = null, withCsrf = true } = {}) {
    const headers = {};

    if (withCsrf) {
        headers['X-CSRF-Token'] = App.csrfToken;
    }

    let body = null;

    if (data !== null) {
        if (data instanceof FormData) {
            body = data;
            // Let browser set Content-Type with boundary for FormData
        } else {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(data);
        }
    }

    return fetch(url, { method, headers, body, credentials: 'same-origin' })
        .then(res => {
            if (!res.ok) {
                return res.json().catch(() => ({
                    success: false,
                    message: `HTTP ${res.status} — ${res.statusText}`,
                }));
            }
            return res.json();
        })
        .catch(err => {
            console.error('[ajax] Network error:', err);
            return { success: false, message: 'Network error. Please check your connection.' };
        });
}

/* =============================================================
   Toast Notifications
   ============================================================= */

const TOAST_ICONS = {
    success: 'check-circle',
    error:   'x-circle',
    warning: 'alert-triangle',
    info:    'info',
};

/**
 * Display a toast notification.
 *
 * @param {string} message    Main text
 * @param {'success'|'error'|'warning'|'info'} type
 * @param {string} [title=''] Optional title line
 * @param {number} [duration=4000] Auto-dismiss delay in ms (0 = no auto-dismiss)
 */
function showToast(message, type = 'info', title = '', duration = 4000) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const icon = TOAST_ICONS[type] ?? 'info';

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="toast-icon">
            <i data-feather="${icon}"></i>
        </div>
        <div class="toast-body">
            ${title ? `<div class="toast-title">${escHtml(title)}</div>` : ''}
            <div class="toast-message">${escHtml(message)}</div>
        </div>
    `;

    container.appendChild(toast);

    // Re-render feather icon inside the new toast
    if (typeof feather !== 'undefined') {
        feather.replace({ 'stroke-width': 1.75 });
    }

    if (duration > 0) {
        setTimeout(() => dismissToast(toast), duration);
    }

    return toast;
}

function dismissToast(toast) {
    toast.classList.add('removing');
    toast.addEventListener('animationend', () => toast.remove(), { once: true });
}

/* =============================================================
   HTML Escaping
   ============================================================= */
function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/* =============================================================
   Sidebar Toggle (mobile)
   ============================================================= */
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (!sidebar) return;

    const isOpen = sidebar.classList.toggle('open');
    if (overlay) overlay.classList.toggle('open', isOpen);
    document.body.style.overflow = isOpen ? 'hidden' : '';
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (!sidebar) return;

    sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('open');
    document.body.style.overflow = '';
}

/* =============================================================
   Live Clock in Top Bar
   ============================================================= */
function startClock() {
    const el = document.getElementById('topBarTime');
    if (!el) return;

    function tick() {
        const now = new Date();
        el.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }

    tick();
    setInterval(tick, 1000);
}

/* =============================================================
   Modal Helpers
   ============================================================= */

/**
 * Open a modal by its backdrop element ID.
 * @param {string} id
 */
function openModal(id) {
    const backdrop = document.getElementById(id);
    if (backdrop) backdrop.classList.add('open');
}

/**
 * Close a modal by its backdrop element ID.
 * @param {string} id
 */
function closeModal(id) {
    const backdrop = document.getElementById(id);
    if (backdrop) backdrop.classList.remove('open');
}

// Close modal when clicking the backdrop (outside .modal)
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal-backdrop')) {
        e.target.classList.remove('open');
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-backdrop.open').forEach(m => m.classList.remove('open'));
    }
});

/* =============================================================
   Confirmation Dialog (native-enhanced)
   ============================================================= */

/**
 * Show a lightweight confirmation dialog.
 *
 * @param {string}   message
 * @param {Function} onConfirm  Called when user confirms
 * @param {string}   [confirmLabel='Confirm']
 * @param {'danger'|'primary'} [variant='danger']
 */
function confirmDialog(message, onConfirm, confirmLabel = 'Confirm', variant = 'danger') {
    // Reuse any existing confirm modal, or fall back to native confirm()
    const modal = document.getElementById('confirmModal');

    if (!modal) {
        if (window.confirm(message)) onConfirm();
        return;
    }

    document.getElementById('confirmMessage').textContent = message;
    const confirmBtn = document.getElementById('confirmOkBtn');
    confirmBtn.textContent = confirmLabel;
    confirmBtn.className = `btn btn-${variant}`;

    const handler = function () {
        confirmBtn.removeEventListener('click', handler);
        closeModal('confirmModal');
        onConfirm();
    };

    confirmBtn.addEventListener('click', handler);
    openModal('confirmModal');
}

/* =============================================================
   Table Search Filter (client-side, for small datasets)
   ============================================================= */

/**
 * Wire up a search input to filter table rows client-side.
 *
 * @param {string} inputId    ID of the <input> element
 * @param {string} tableId    ID of the <table> element
 * @param {number[]} [cols]   Column indices to search (default: all)
 */
function initTableSearch(inputId, tableId, cols = []) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;

    input.addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();
        const rows  = table.querySelectorAll('tbody tr');

        rows.forEach(row => {
            const cells = cols.length
                ? cols.map(i => row.cells[i])
                : Array.from(row.cells);

            const text = cells.map(c => (c ? c.textContent : '')).join(' ').toLowerCase();
            row.style.display = !query || text.includes(query) ? '' : 'none';
        });
    });
}

/* =============================================================
   Initialisation
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    startClock();

    // Auto-wire table search inputs with data attributes
    document.querySelectorAll('[data-search-table]').forEach(input => {
        const tableId = input.getAttribute('data-search-table');
        initTableSearch(input.id, tableId);
    });
});
