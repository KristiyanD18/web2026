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
$archived  = DB::one('SELECT COUNT(*) c FROM documents WHERE status="archived"')['c'];

// Filters
$filterStatus   = $_GET['status']   ?? '';
$filterPriority = $_GET['priority'] ?? '';
$filterCategory = (int)($_GET['cat'] ?? 0);
$search         = trim($_GET['q']   ?? '');

$categories = DB::all('SELECT * FROM categories WHERE is_active=1 ORDER BY name');

$params = [];

if ($filterStatus === 'archived') {
    $where = ['d.status = "archived"'];
} else {
    $where = ['d.status != "archived"'];
    if ($filterStatus && in_array($filterStatus, ['pending','in_progress','completed','paused'])) {
        $where[] = 'd.status = ?'; $params[] = $filterStatus;
    }
}
if ($filterCategory) { $where[] = 'd.category_id = ?'; $params[] = $filterCategory; }
if ($filterPriority === 'high') { $where[] = 'd.priority = "high"'; }
if ($search) {
    $where[]  = '(d.incoming_number LIKE ? OR d.title = ? OR d.submitter_name = ? OR d.submitter_faculty_number LIKE ?)';
    $like = "%$search%";
    $params = array_merge($params, [$like, $search, $search, $like]);
}

$docs = DB::all(
    'SELECT d.*, c.name AS cat_name FROM documents d
     LEFT JOIN categories c ON c.id = d.category_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY d.submitted_at DESC LIMIT 50',
    $params
);

layoutHead('Администрация');
layoutNav('admin');
?>
<div class="container">
  <?php layoutFlash(); ?>

  <div class="stats-grid mb-3" style="grid-template-columns:repeat(6,1fr)">
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
    <div class="stat-card" style="padding:.6rem .85rem;flex-direction:row;align-items:center;justify-content:space-between">
      <span class="stat-label" style="font-size:.75rem">Архивирани</span>
      <span class="stat-value" style="font-size:1.1rem"><?= $archived ?></span>
    </div>
  </div>

  <!-- Filters -->
  <form method="get" class="filters card card-body mb-2" style="padding:.75rem 1rem;align-items:center">
    <input type="text" name="q" class="form-control" placeholder="Търсене по име или фак. номер…" value="<?= h($search) ?>" style="flex:1;min-width:200px">
    <select name="status" class="form-control">
      <option value="">Всички статуси</option>
      <option value="pending"     <?= $filterStatus==='pending'?'selected':'' ?>>Чакащ</option>
      <option value="in_progress" <?= $filterStatus==='in_progress'?'selected':'' ?>>В обработка</option>
      <option value="completed"   <?= $filterStatus==='completed'?'selected':'' ?>>Обработен</option>
      <option value="paused"      <?= $filterStatus==='paused'?'selected':'' ?>>Паузиран</option>
      <option value="archived"    <?= $filterStatus==='archived'?'selected':'' ?>>Архивиран</option>
    </select>
    <select name="cat" class="form-control">
      <option value="">Всички категории</option>
      <?php foreach ($categories as $cat): ?>
      <option value="<?= $cat['id'] ?>" <?= $filterCategory==$cat['id']?'selected':'' ?>><?= h($cat['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="priority" class="form-control">
      <option value="">Всички приоритети</option>
      <option value="high" <?= $filterPriority==='high'?'selected':'' ?>>Висок</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Филтрирай</button>
    <a href="?" class="btn btn-outline btn-sm">Изчисти</a>
  </form>

  <div class="card">
    <div class="card-header"><?= count($docs) ?> документа</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Входящ №</th><th>Заглавие</th><th>Категория</th>
            <th>Подател</th><th>Фак. №</th><th>Статус</th><th>Приоритет</th><th>Дата</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($docs as $doc): ?>
          <tr>
            <td class="fw-bold" style="white-space:nowrap"><?= h($doc['incoming_number']) ?></td>
            <td><?= h(mb_strimwidth($doc['title'], 0, 38, '…')) ?></td>
            <td><?= h($doc['cat_name'] ?? '—') ?></td>
            <td class="text-sm"><?= h($doc['submitter_name']) ?></td>
            <td class="text-sm text-gray"><?= h($doc['submitter_faculty_number'] ?? '—') ?></td>
            <td><span class="badge <?= statusClass($doc['status']) ?>"><?= statusLabel($doc['status']) ?></span></td>
            <td>
              <?php if ($doc['priority'] === 'high'): ?>
                <span class="badge badge-high">Висок</span>
              <?php else: ?>
                <span class="badge badge-secondary">Нормален</span>
              <?php endif; ?>
            </td>
            <td class="text-sm text-gray" style="white-space:nowrap"><?= h(date('d.m.Y H:i', strtotime($doc['submitted_at']))) ?></td>
            <td>
              <a href="<?= url('public/admin/document_view.php') ?>?id=<?= $doc['id'] ?>"
                 class="btn btn-outline btn-sm">Преглед</a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($docs)): ?>
          <tr><td colspan="9" class="text-center text-gray">Няма документи.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php layoutFoot(); ?>
