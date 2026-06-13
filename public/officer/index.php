<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/layout.php';

Auth::requireLogin();

$userId = Auth::id();

// Find categories this officer is responsible for
$myCats = DB::all(
    'SELECT * FROM categories WHERE officer_user_id=? AND is_active=1',
    [$userId]
);
$catIds = array_column($myCats, 'id');

$filterStatus   = $_GET['status']   ?? '';
$filterPriority = $_GET['priority'] ?? '';
$search         = trim($_GET['q']   ?? '');

$where  = ['1=1'];
$params = [];

if (!empty($catIds)) {
    $in = implode(',', array_fill(0, count($catIds), '?'));
    $where[] = "(d.category_id IN ($in) OR d.submitted_by_user_id = ?)";
    $params  = array_merge($params, $catIds, [$userId]);
} else {
    $where[] = 'd.submitted_by_user_id = ?';
    $params[] = $userId;
}

$where[] = 'd.status != "archived"';

if ($filterStatus && in_array($filterStatus, ['pending','in_progress','completed','paused'])) {
    $where[] = 'd.status=?'; $params[] = $filterStatus;
}
if ($filterPriority && in_array($filterPriority, ['normal','high'])) {
    $where[] = 'd.priority=?'; $params[] = $filterPriority;
}
if ($search) {
    $where[] = '(d.incoming_number LIKE ? OR d.title LIKE ? OR d.submitter_name LIKE ?)';
    $like    = "%$search%";
    $params  = array_merge($params, [$like,$like,$like]);
}

$docs = DB::all(
    'SELECT d.*, c.name cat_name FROM documents d
     LEFT JOIN categories c ON c.id=d.category_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY d.priority DESC, d.submitted_at DESC',
    $params
);

// Counts
$pending    = count(array_filter($docs, fn($d) => $d['status']==='pending'));
$inProgress = count(array_filter($docs, fn($d) => $d['status']==='in_progress'));
$completed  = count(array_filter($docs, fn($d) => $d['status']==='completed'));
$paused     = count(array_filter($docs, fn($d) => $d['status']==='paused'));
$high       = count(array_filter($docs, fn($d) => $d['priority']==='high'));

layoutHead('Моите Документи');
layoutNav('officer');
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
      <span class="stat-value" style="font-size:1.1rem"><?= $inProgress ?></span>
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

  <!-- Filters -->
  <form method="get" class="filters card card-body mb-2" style="padding:.75rem 1rem;align-items:center">
    <input type="text" name="q" class="form-control" placeholder="Търсене…" value="<?= h($search) ?>">
    <select name="status" class="form-control">
      <option value="">Всички статуси</option>
      <option value="pending"     <?= $filterStatus==='pending'?'selected':'' ?>>Чакащ</option>
      <option value="in_progress" <?= $filterStatus==='in_progress'?'selected':'' ?>>В обработка</option>
      <option value="completed"   <?= $filterStatus==='completed'?'selected':'' ?>>Обработен</option>
      <option value="paused"      <?= $filterStatus==='paused'?'selected':'' ?>>Паузиран</option>
    </select>
    <select name="priority" class="form-control">
      <option value="">Всички приоритети</option>
      <option value="normal" <?= $filterPriority==='normal'?'selected':'' ?>>Нормален</option>
      <option value="high"   <?= $filterPriority==='high'?'selected':'' ?>>Висок</option>
    </select>
    <button class="btn btn-primary btn-sm">Филтрирай</button>
    <a href="?" class="btn btn-outline btn-sm">Изчисти</a>
  </form>

  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Входящ №</th><th>Заглавие</th><th>Категория</th>
              <th>Подател</th><th>Статус</th><th>Приоритет</th><th>Дата</th></tr>
        </thead>
        <tbody>
          <?php foreach ($docs as $doc): ?>
          <tr>
            <td class="fw-bold"><?= h($doc['incoming_number']) ?></td>
            <td><?= h(mb_strimwidth($doc['title'],0,38,'…')) ?></td>
            <td class="text-sm"><?= h($doc['cat_name'] ?? '—') ?></td>
            <td class="text-sm"><?= h($doc['submitter_name']) ?></td>
            <td><span class="badge <?= statusClass($doc['status']) ?>"><?= statusLabel($doc['status']) ?></span></td>
            <td><?= $doc['priority']==='high' ? '<span class="badge badge-high">Висок</span>' : '<span class="badge badge-secondary">Нормален</span>' ?></td>
            <td class="text-sm text-gray"><?= h(date('d.m.Y H:i', strtotime($doc['submitted_at']))) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($docs)): ?>
          <tr><td colspan="7" class="text-center text-gray p-2">Няма документи.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php layoutFoot(); ?>
