<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/layout.php';

Auth::requireAdmin();

// Summary stats
$totalDocs    = DB::one('SELECT COUNT(*) c FROM documents')['c'];
$totalActive  = DB::one('SELECT COUNT(*) c FROM documents WHERE status != "archived"')['c'];
$totalArchived= DB::one('SELECT COUNT(*) c FROM documents WHERE status = "archived"')['c'];
$totalAccess  = DB::one('SELECT COUNT(*) c FROM access_log')['c'];
$totalDecrypt = DB::one('SELECT COUNT(*) c FROM access_log WHERE access_type="decrypt_attempt"')['c'];
$failedDecrypt= DB::one('SELECT COUNT(*) c FROM access_log WHERE access_type="decrypt_attempt" AND success=0')['c'];

// By status
$byStatus = DB::all(
    'SELECT status, COUNT(*) cnt FROM documents WHERE status != "archived" GROUP BY status'
);

// By category
$byCat = DB::all(
    'SELECT COALESCE(c.name,"Без категория") AS cat_name, COUNT(*) cnt
     FROM documents d
     LEFT JOIN categories c ON c.id=d.category_id
     WHERE d.status != "archived"
     GROUP BY d.category_id ORDER BY cnt DESC'
);

// Documents per day (last 14 days)
$perDay = DB::all(
    'SELECT DATE(submitted_at) AS day, COUNT(*) cnt
     FROM documents
     WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
     GROUP BY day ORDER BY day'
);

// Average processing time for completed docs
$avgTime = DB::one(
    'SELECT AVG(TIMESTAMPDIFF(HOUR, submitted_at, updated_at)) avg_h
     FROM documents WHERE status = "completed"'
)['avg_h'];

// Most accessed documents
$mostAccessed = DB::all(
    'SELECT d.incoming_number, d.title, COUNT(*) cnt
     FROM access_log l JOIN documents d ON d.id=l.document_id
     WHERE l.access_type IN ("view","download")
     GROUP BY d.id ORDER BY cnt DESC LIMIT 10'
);

// Average view duration
$avgDuration = DB::one(
    'SELECT AVG(duration_seconds) avg_s FROM access_log WHERE duration_seconds > 0'
)['avg_s'];

// Prepare chart data
$statusLabels = array_column($byStatus, 'status');
$statusCounts = array_column($byStatus, 'cnt');
$statusDisplayLabels = array_map('statusLabel', $statusLabels);

$catLabels  = array_column($byCat, 'cat_name');
$catCounts  = array_column($byCat, 'cnt');

layoutHead('Статистика');
layoutNav('admin');
?>
<div class="container">
  <?php layoutFlash(); ?>
  <div class="page-header">
    <h1>Статистика</h1>
    <a href="<?= url('public/admin/index.php') ?>" class="btn btn-outline btn-sm">Начало</a>
  </div>

  <!-- Summary stats -->
  <div class="stats-grid mb-3">
    <div class="stat-card">
      <span class="stat-value"><?= $totalDocs ?></span>
      <span class="stat-label">Всички документи</span>
    </div>
    <div class="stat-card">
      <span class="stat-value"><?= $totalActive ?></span>
      <span class="stat-label">Активни</span>
    </div>
    <div class="stat-card">
      <span class="stat-value"><?= $totalArchived ?></span>
      <span class="stat-label">Архивирани</span>
    </div>
    <div class="stat-card">
      <span class="stat-value"><?= $totalAccess ?></span>
      <span class="stat-label">Достъпи</span>
    </div>
    <div class="stat-card">
      <span class="stat-value" style="color:var(--clr-danger)"><?= $failedDecrypt ?></span>
      <span class="stat-label">Неусп. декрипт.</span>
    </div>
    <div class="stat-card">
      <span class="stat-value"><?= $avgTime ? round($avgTime, 1) . 'ч' : '—' ?></span>
      <span class="stat-label">Сред. обработка</span>
    </div>
    <div class="stat-card">
      <span class="stat-value"><?= $avgDuration ? round($avgDuration) . 'с' : '—' ?></span>
      <span class="stat-label">Сред. преглед</span>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem">

    <!-- Status chart -->
    <div class="card">
      <div class="card-header">Документи по статус</div>
      <div class="card-body">
        <canvas id="status-chart" width="380" height="220"
                data-labels='<?= json_encode($statusDisplayLabels) ?>'
                data-values='<?= json_encode($statusCounts) ?>'></canvas>
      </div>
    </div>

    <!-- Category chart -->
    <div class="card">
      <div class="card-header">Документи по категория</div>
      <div class="card-body">
        <canvas id="cat-chart" width="380" height="220"
                data-labels='<?= json_encode($catLabels) ?>'
                data-values='<?= json_encode($catCounts) ?>'></canvas>
      </div>
    </div>

    <!-- Daily submissions -->
    <div class="card">
      <div class="card-header">Входирания (последни 14 дни)</div>
      <div class="card-body">
        <?php
        $dayLabels = array_map(fn($r) => date('d.m', strtotime($r['day'])), $perDay);
        $dayCounts = array_column($perDay, 'cnt');
        ?>
        <canvas id="day-chart" width="380" height="220"
                data-labels='<?= json_encode($dayLabels) ?>'
                data-values='<?= json_encode($dayCounts) ?>'></canvas>
      </div>
    </div>

    <!-- Most accessed -->
    <div class="card">
      <div class="card-header">Най-достъпвани документи</div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Входящ №</th><th>Заглавие</th><th>Достъпи</th></tr></thead>
          <tbody>
            <?php foreach ($mostAccessed as $row): ?>
            <tr>
              <td class="fw-bold text-sm"><?= h($row['incoming_number']) ?></td>
              <td class="text-sm"><?= h(mb_strimwidth($row['title'],0,35,'…')) ?></td>
              <td><?= $row['cnt'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($mostAccessed)): ?>
            <tr><td colspan="3" class="text-center text-gray">Няма данни.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Decrypt attempts table -->
  <div class="card mt-2">
    <div class="card-header">Опити за декриптиране</div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Тип</th><th>Документ</th><th>Потребител</th><th>IP</th><th>Дата</th><th>Резултат</th></tr></thead>
        <tbody>
          <?php
          $decLogs = DB::all(
            'SELECT l.*, d.incoming_number, u.full_name
             FROM access_log l
             JOIN documents d ON d.id=l.document_id
             LEFT JOIN users u ON u.id=l.user_id
             WHERE l.access_type IN ("decrypt_attempt","decrypt_success")
             ORDER BY l.accessed_at DESC LIMIT 20'
          );
          foreach ($decLogs as $log):
          ?>
          <tr>
            <td class="text-sm"><?= h($log['access_type']) ?></td>
            <td class="fw-bold text-sm"><?= h($log['incoming_number']) ?></td>
            <td class="text-sm"><?= h($log['full_name'] ?? 'Неизвестен') ?></td>
            <td class="text-sm text-gray"><?= h($log['ip_address']) ?></td>
            <td class="text-sm text-gray"><?= h(date('d.m.Y H:i', strtotime($log['accessed_at']))) ?></td>
            <td><?= $log['success'] ? '<span class="badge badge-success">Успех</span>' : '<span class="badge badge-danger">Неуспех</span>' ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($decLogs)): ?>
          <tr><td colspan="6" class="text-center text-gray">Няма записи.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="<?= url('public/assets/js/main.js') ?>"></script>
<script>
// Extra charts
const catCanvas = document.getElementById('cat-chart');
if (catCanvas) {
    const data   = JSON.parse(catCanvas.dataset.values || '[]');
    const labels = JSON.parse(catCanvas.dataset.labels || '[]');
    if (data.length) drawBarChart(catCanvas, labels, data);
}
const dayCanvas = document.getElementById('day-chart');
if (dayCanvas) {
    const data   = JSON.parse(dayCanvas.dataset.values || '[]');
    const labels = JSON.parse(dayCanvas.dataset.labels || '[]');
    if (data.length) drawBarChart(dayCanvas, labels, data);
}
</script>
</body></html>
