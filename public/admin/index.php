<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/layout.php';

Auth::requireAdmin();

// Stats
$total     = DB::one('SELECT COUNT(*) c FROM documents WHERE status != "archived"')['c'];
$pending   = DB::one('SELECT COUNT(*) c FROM documents WHERE status="pending"')['c'];
$inProg    = DB::one('SELECT COUNT(*) c FROM documents WHERE status="in_progress"')['c'];
$completed = DB::one('SELECT COUNT(*) c FROM documents WHERE status="completed"')['c'];
$paused    = DB::one('SELECT COUNT(*) c FROM documents WHERE status="paused"')['c'];
$high      = DB::one('SELECT COUNT(*) c FROM documents WHERE priority="high" AND status NOT IN ("completed","archived")')['c'];

// Recent docs
$recent = DB::all(
    'SELECT d.*, c.name AS cat_name FROM documents d
     LEFT JOIN categories c ON c.id = d.category_id
     WHERE d.status != "archived"
     ORDER BY d.submitted_at DESC LIMIT 10'
);

layoutHead('Администрация');
layoutNav('admin');
?>
<div class="container">
  <?php layoutFlash(); ?>
  <div class="page-header">
    <h1>📊 Административен панел</h1>
    <div class="d-flex gap-1">
      <a href="<?= url('public/admin/documents.php') ?>" class="btn btn-primary">Всички документи</a>
      <a href="<?= url('public/admin/categories.php') ?>" class="btn btn-outline">Категории</a>
      <a href="<?= url('public/admin/users.php') ?>" class="btn btn-outline">Потребители</a>
    </div>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <span class="stat-value"><?= $total ?></span>
      <span class="stat-label">Общо активни</span>
    </div>
    <div class="stat-card">
      <span class="stat-value" style="color:var(--clr-warning)"><?= $pending ?></span>
      <span class="stat-label">Чакащи</span>
    </div>
    <div class="stat-card">
      <span class="stat-value" style="color:#1d4ed8"><?= $inProg ?></span>
      <span class="stat-label">В обработка</span>
    </div>
    <div class="stat-card">
      <span class="stat-value" style="color:var(--clr-success)"><?= $completed ?></span>
      <span class="stat-label">Обработени</span>
    </div>
    <div class="stat-card">
      <span class="stat-value" style="color:var(--clr-gray)"><?= $paused ?></span>
      <span class="stat-label">Паузирани</span>
    </div>
    <div class="stat-card">
      <span class="stat-value" style="color:var(--clr-danger)"><?= $high ?></span>
      <span class="stat-label">Приоритетни</span>
    </div>
  </div>

  <!-- Quick actions -->
  <div class="d-flex gap-1 mb-3" style="flex-wrap:wrap">
    <a href="<?= url('public/admin/documents.php') ?>?status=pending" class="btn btn-warning btn-sm">Чакащи →</a>
    <a href="<?= url('public/admin/documents.php') ?>?priority=high" class="btn btn-danger btn-sm">Приоритетни →</a>
    <a href="<?= url('public/admin/archive.php') ?>" class="btn btn-outline btn-sm">🗄 Архив</a>
    <a href="<?= url('public/admin/statistics.php') ?>" class="btn btn-outline btn-sm">📈 Статистика</a>
  </div>

  <div class="card">
    <div class="card-header">📋 Последни документи</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Входящ №</th><th>Заглавие</th><th>Категория</th>
            <th>Статус</th><th>Приоритет</th><th>Дата</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $doc): ?>
          <tr>
            <td class="fw-bold"><?= h($doc['incoming_number']) ?></td>
            <td><?= h(mb_strimwidth($doc['title'], 0, 40, '…')) ?></td>
            <td><?= h($doc['cat_name'] ?? '—') ?></td>
            <td><span class="badge <?= statusClass($doc['status']) ?>"><?= statusLabel($doc['status']) ?></span></td>
            <td>
              <?php if ($doc['priority'] === 'high'): ?>
                <span class="badge badge-high">🔥 Приор.</span>
              <?php else: ?>
                <span class="badge badge-secondary">Норм.</span>
              <?php endif; ?>
            </td>
            <td class="text-sm text-gray"><?= h(date('d.m.Y H:i', strtotime($doc['submitted_at']))) ?></td>
            <td>
              <a href="<?= url('public/admin/document_view.php') ?>?id=<?= $doc['id'] ?>"
                 class="btn btn-outline btn-sm">Преглед</a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($recent)): ?>
          <tr><td colspan="7" class="text-center text-gray">Няма документи.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php layoutFoot(); ?>
