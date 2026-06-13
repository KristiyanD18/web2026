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

  <div class="stats-grid mb-3" style="grid-template-columns:repeat(5,1fr)">
    <div class="stat-card" style="padding:.6rem .85rem;flex-direction:row;align-items:center;justify-content:space-between">
      <span class="stat-label" style="font-size:.75rem">Чакащи</span>
      <span class="stat-value" style="font-size:1.1rem"><?= $pending ?></span>
    </div>
    <div class="stat-card" style="padding:.6rem .85rem;flex-direction:row;align-items:center;justify-content:space-between">
      <span class="stat-label" style="font-size:.75rem">В обработка</span>
      <span class="stat-value" style="font-size:1.1rem"><?= $inProg ?></span>
    </div>
    <div class="stat-card" style="padding:.6rem .85rem;flex-direction:row;align-items:center;justify-content:space-between">
      <span class="stat-label" style="font-size:.75rem">Обработени</span>
      <span class="stat-value" style="font-size:1.1rem"><?= $completed ?></span>
    </div>
    <div class="stat-card" style="padding:.6rem .85rem;flex-direction:row;align-items:center;justify-content:space-between">
      <span class="stat-label" style="font-size:.75rem">Паузирани</span>
      <span class="stat-value" style="font-size:1.1rem"><?= $paused ?></span>
    </div>
    <div class="stat-card" style="padding:.6rem .85rem;flex-direction:row;align-items:center;justify-content:space-between">
      <span class="stat-label" style="font-size:.75rem">Приоритетни</span>
      <span class="stat-value" style="font-size:1.1rem"><?= $high ?></span>
    </div>
  </div>

<div class="card">
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
                <span class="badge badge-high">Висок</span>
              <?php else: ?>
                <span class="badge badge-secondary">Нормален</span>
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
