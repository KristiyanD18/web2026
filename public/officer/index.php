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

$filterStatus = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');

$where  = ['1=1'];
$params = [];

if (!Auth::isAdmin()) {
    if (empty($catIds)) {
        $where[] = '0';  // officer with no categories sees nothing
    } else {
        $in = implode(',', array_fill(0, count($catIds), '?'));
        $where[] = "d.category_id IN ($in)";
        $params  = array_merge($params, $catIds);
    }
}

$where[] = 'd.status != "archived"';

if ($filterStatus && in_array($filterStatus, ['pending','in_progress','completed','paused'])) {
    $where[] = 'd.status=?'; $params[] = $filterStatus;
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
$high       = count(array_filter($docs, fn($d) => $d['priority']==='high'));

layoutHead('Моите Документи');
layoutNav('officer');
?>
<div class="container">
  <?php layoutFlash(); ?>
  <div class="page-header">
    <h1>📋 Моите Документи</h1>
    <?php if (!empty($myCats)): ?>
    <div class="text-sm text-gray">
      Отговарям за: <?= implode(', ', array_map(fn($c) => h($c['name']), $myCats)) ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="stats-grid mb-3" style="grid-template-columns:repeat(3,1fr)">
    <div class="stat-card">
      <span class="stat-value" style="color:var(--clr-warning)"><?= $pending ?></span>
      <span class="stat-label">Чакащи</span>
    </div>
    <div class="stat-card">
      <span class="stat-value" style="color:var(--clr-primary)"><?= $inProgress ?></span>
      <span class="stat-label">В обработка</span>
    </div>
    <div class="stat-card">
      <span class="stat-value" style="color:var(--clr-danger)"><?= $high ?></span>
      <span class="stat-label">Приоритетни</span>
    </div>
  </div>

  <!-- Filters -->
  <form method="get" class="filters card card-body mb-2" style="padding:.75rem 1rem">
    <input type="text" name="q" class="form-control" placeholder="Търсене…" value="<?= h($search) ?>">
    <select name="status" class="form-control">
      <option value="">Всички</option>
      <option value="pending"     <?= $filterStatus==='pending'?'selected':'' ?>>Чакащ</option>
      <option value="in_progress" <?= $filterStatus==='in_progress'?'selected':'' ?>>В обработка</option>
      <option value="completed"   <?= $filterStatus==='completed'?'selected':'' ?>>Обработен</option>
      <option value="paused"      <?= $filterStatus==='paused'?'selected':'' ?>>Паузиран</option>
    </select>
    <button class="btn btn-primary btn-sm">Филтрирай</button>
    <a href="?" class="btn btn-outline btn-sm">Изчисти</a>
  </form>

  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Входящ №</th><th>Заглавие</th><th>Категория</th>
              <th>Подател</th><th>Статус</th><th>Приоритет</th><th>Дата</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($docs as $doc): ?>
          <tr>
            <td class="fw-bold"><?= h($doc['incoming_number']) ?></td>
            <td><?= h(mb_strimwidth($doc['title'],0,38,'…')) ?></td>
            <td class="text-sm"><?= h($doc['cat_name'] ?? '—') ?></td>
            <td class="text-sm"><?= h($doc['submitter_name']) ?></td>
            <td><span class="badge <?= statusClass($doc['status']) ?>"><?= statusLabel($doc['status']) ?></span></td>
            <td><?= $doc['priority']==='high' ? '<span class="badge badge-high">🔥</span>' : '—' ?></td>
            <td class="text-sm text-gray"><?= h(date('d.m.Y H:i', strtotime($doc['submitted_at']))) ?></td>
            <td>
              <a href="<?= url('public/officer/document_view.php') ?>?id=<?= $doc['id'] ?>"
                 class="btn btn-outline btn-sm">Преглед</a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($docs)): ?>
          <tr><td colspan="8" class="text-center text-gray p-2">Няма документи.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php layoutFoot(); ?>
