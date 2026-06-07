'use strict';

// ── Flash messages ────────────────────────────────────────────────────────────
document.querySelectorAll('.alert[data-dismiss]').forEach(el => {
    setTimeout(() => el.remove(), 6000);
    el.querySelector('[data-close]')?.addEventListener('click', () => el.remove());
});

// ── CSRF token injection for all fetch() calls ────────────────────────────────
const csrfMeta = document.querySelector('meta[name="csrf-token"]');
const CSRF = csrfMeta ? csrfMeta.content : '';

// ── Confirm dangerous actions ─────────────────────────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
        if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
});

// ── View duration tracking ────────────────────────────────────────────────────
// If a log_id is present on the page, send a heartbeat and close event
const logIdEl = document.getElementById('view-log-id');
if (logIdEl) {
    const logId = logIdEl.value;
    const closeUrl = logIdEl.dataset.closeUrl;

    // Heartbeat every 30s
    const heartbeat = setInterval(() => {
        fetch(closeUrl + '?log_id=' + logId + '&action=ping', {
            method: 'POST',
            headers: { 'X-CSRF': CSRF },
            keepalive: true
        });
    }, 30000);

    // Close on page hide
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            clearInterval(heartbeat);
            navigator.sendBeacon(closeUrl + '?log_id=' + logId + '&action=close');
        }
    });

    window.addEventListener('beforeunload', () => {
        clearInterval(heartbeat);
        navigator.sendBeacon(closeUrl + '?log_id=' + logId + '&action=close');
    });
}

// ── Dynamic key-part rows ─────────────────────────────────────────────────────
const addPartBtn = document.getElementById('add-key-part');
const keyPartsContainer = document.getElementById('key-parts-container');
const partCountInput = document.getElementById('num_parts');

if (addPartBtn && keyPartsContainer) {
    addPartBtn.addEventListener('click', () => {
        const count = keyPartsContainer.querySelectorAll('.key-part-row').length;
        const max   = parseInt(document.getElementById('max-parts')?.value || '5');
        if (count >= max) {
            alert('Достигнат е максималният брой части (' + max + ').');
            return;
        }
        const idx = count + 1;
        const row = document.createElement('div');
        row.className = 'key-part-row';
        row.innerHTML = `
            <div class="form-group" style="margin:0">
                <label>Притежател ${idx}</label>
                <select name="part_user[]" class="form-control" required>
                    ${keyPartsContainer.dataset.userOptions || ''}
                </select>
            </div>
            <div class="form-group" style="margin:0">
                <label>Парола на притежател ${idx}</label>
                <input type="password" name="part_password[]" class="form-control" required>
            </div>
            <button type="button" class="btn btn-danger btn-sm remove-part" style="margin-bottom:.2rem">✕</button>
        `;
        row.querySelector('.remove-part').addEventListener('click', () => {
            row.remove();
            updatePartLabels();
        });
        keyPartsContainer.appendChild(row);
        updatePartLabels();
    });
}

function updatePartLabels() {
    if (!keyPartsContainer) return;
    keyPartsContainer.querySelectorAll('.key-part-row').forEach((row, i) => {
        row.querySelectorAll('label').forEach((lbl, j) => {
            const n = i + 1;
            if (j === 0) lbl.textContent = 'Притежател ' + n;
            else lbl.textContent = 'Парола на притежател ' + n;
        });
    });
    if (partCountInput) {
        partCountInput.value = keyPartsContainer.querySelectorAll('.key-part-row').length;
    }
}

// Remove-part buttons for initial rows
document.querySelectorAll('.remove-part').forEach(btn => {
    btn.addEventListener('click', () => {
        btn.closest('.key-part-row').remove();
        updatePartLabels();
    });
});

// ── Status change inline ──────────────────────────────────────────────────────
document.querySelectorAll('[data-status-change]').forEach(btn => {
    btn.addEventListener('click', async () => {
        const docId  = btn.dataset.docId;
        const status = btn.dataset.status;
        const label  = btn.dataset.label || status;
        if (!confirm(`Сменяте статуса на "${label}"?`)) return;
        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('doc_id', docId);
        fd.append('status', status);
        const res = await fetch('ajax_status.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) location.reload();
        else alert(data.msg || 'Грешка.');
    });
});

// ── File upload preview ───────────────────────────────────────────────────────
const fileInput = document.getElementById('document_file');
const fileLabel = document.getElementById('file-label');
if (fileInput && fileLabel) {
    fileInput.addEventListener('change', () => {
        const f = fileInput.files[0];
        if (f) {
            const mb = (f.size / 1048576).toFixed(2);
            fileLabel.textContent = `${f.name} (${mb} MB)`;
        } else {
            fileLabel.textContent = 'Изберете PDF или ZIP файл';
        }
    });
}

// ── Statistics chart (minimal canvas bar chart) ───────────────────────────────
const chartCanvas = document.getElementById('status-chart');
if (chartCanvas) {
    const data   = JSON.parse(chartCanvas.dataset.values || '[]');
    const labels = JSON.parse(chartCanvas.dataset.labels || '[]');
    if (data.length) drawBarChart(chartCanvas, labels, data);
}

function drawBarChart(canvas, labels, values) {
    const ctx    = canvas.getContext('2d');
    const W      = canvas.width;
    const H      = canvas.height;
    const PAD    = 40;
    const barW   = Math.floor((W - PAD * 2) / values.length) - 8;
    const maxVal = Math.max(...values) || 1;
    const colors = ['#1a56db','#057a55','#c27803','#c81e1e','#374151'];

    ctx.clearRect(0, 0, W, H);
    ctx.fillStyle = '#f9fafb';
    ctx.fillRect(0, 0, W, H);

    // Grid lines
    ctx.strokeStyle = '#e5e7eb';
    ctx.lineWidth   = 1;
    for (let i = 0; i <= 4; i++) {
        const y = PAD + (H - PAD * 2) * i / 4;
        ctx.beginPath(); ctx.moveTo(PAD, y); ctx.lineTo(W - PAD, y); ctx.stroke();
        ctx.fillStyle = '#6b7280';
        ctx.font      = '11px sans-serif';
        ctx.textAlign = 'right';
        ctx.fillText(Math.round(maxVal * (4 - i) / 4), PAD - 4, y + 4);
    }

    // Bars
    values.forEach((v, i) => {
        const bh = ((v / maxVal) * (H - PAD * 2)) || 2;
        const x  = PAD + i * ((W - PAD * 2) / values.length) + 4;
        const y  = H - PAD - bh;
        ctx.fillStyle = colors[i % colors.length];
        ctx.fillRect(x, y, barW, bh);
        ctx.fillStyle = '#374151';
        ctx.font      = '11px sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(labels[i], x + barW / 2, H - PAD + 14);
        ctx.fillStyle = '#111827';
        ctx.fillText(v, x + barW / 2, y - 4);
    });
}
