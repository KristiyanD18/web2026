<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/layout.php';

Auth::requireAdmin();

// Filters
$filterStatus   = $_GET['status']   ?? '';
$filterCategory = (int)($_GET['cat'] ?? 0);
$filterPriority = $_GET['priority'] ?? '';
$search         = trim($_GET['q']   ?? '');

$where  = ['d.status != "archived"'];
$params = [];

if ($filterStatus && in_array($filterStatus, ['pending','in_progress','completed','paused'])) {
    $where[] = 'd.status = ?'; $params[] = $filterStatus;
}
if ($filterCategory) { $where[] = 'd.category_id = ?'; $params[] = $filterCategory; }
if ($filterPriority === 'high') { $where[] = 'd.priority = "high"'; }
if ($search) {
    $where[]  = '(d.incoming_number LIKE ? OR d.title LIKE ? OR d.submitter_name LIKE ?)';
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like]);
}

$whereSQL = implode(' AND ', $where);

$docs = DB::all(
    "SELECT d.*, c.name AS cat_name
     FROM documents d LEFT JOIN categories c ON c.id = d.category_id
     WHERE $whereSQL ORDER BY d.priority DESC, d.submitted_at DESC",
    $params
);

$categories = DB::all('SELECT * FROM categories WHERE is_active=1 ORDER BY name');

layoutHead('Документи');
layoutNav('admin');
?>
<div class="container">
  <?php layoutFlash(); ?>
  <div class="page-header">
    <h1>📁 Документи</h1>
    <a href="<?= url('public/admin/index.php') ?>" class="btn btn-outline btn-sm">← Начало</a>
  </div>

  <!-- Filters -->
  <form method="get" class="filters card card-body mb-2" style="padding:.75rem 1rem">
    <input type="text" name="q" class="form-control" placeholder="Търсене…" value="<?= h($search) ?>">
    <select name="status" class="form-control">
      <option value="">Всички статуси</option>
      <option value="pending"     <?= $filterStatus==='pending'?'selected':'' ?>>Чакащ</option>
      <option value="in_progress" <?= $filterStatus==='in_progress'?'selected':'' ?>>В обработка</option>
      <option value="completed"   <?= $filterStatus==='completed'?'selected':'' ?>>Обработен</option>
      <option value="paused"      <?= $filterStatus==='paused'?'selected':'' ?>>Паузиран</option>
    </select>
    <select name="cat" class="form-control">
      <option value="">Всички категории</option>
      <?php foreach ($categories as $cat): ?>
      <option value="<?= $cat['id'] ?>" <?= $filterCategory==$cat['id']?'selected':'' ?>>
        <?= h($cat['name']) ?>
      </option>
      <?php endforeach; ?>
    </select>
    <select name="priority" class="form-control">
      <option value="">Всички приоритети</option>
      <option value="high" <?= $filterPriority==='high'?'selected':'' ?>>Приоритетни</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Филтрирай</button>
    <a href="?" class="btn btn-outline btn-sm">Изчисти</a>
  </form>

  <div class="card">
    <div class="card-header">
      <?= count($docs) ?> документа
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Входящ №</th><th>Заглавие</th><th>Категория</th>
            <th>Подател</th><th>Статус</th><th>Приоритет</th>
            <th>Крипт.</th><th>Дата</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($docs as $doc): ?>
          <tr>
            <td class="fw-bold" style="white-space:nowrap"><?= h($doc['incoming_number']) ?></td>
            <td><?= h(mb_strimwidth($doc['title'], 0, 38, '…')) ?></td>
            <td><?= h($doc['cat_name'] ?? '—') ?></td>
            <td class="text-sm"><?= h($doc['submitter_name']) ?></td>
            <td><span class="badge <?= statusClass($doc['status']) ?>"><?= statusLabel($doc['status']) ?></span></td>
            <td>
              <?php if ($doc['priority']==='high'): ?>
                <span class="badge badge-high">🔥</span>
              <?php else: ?>
                <span class="text-gray text-sm">—</span>
              <?php endif; ?>
            </td>
            <td><?= $doc['is_encrypted'] ? '🔒' : '' ?></td>
            <td class="text-sm text-gray" style="white-space:nowrap"><?= h(date('d.m.Y H:i', strtotime($doc['submitted_at']))) ?></td>
            <td style="white-space:nowrap">
              <a href="<?= url('public/admin/document_view.php') ?>?id=<?= $doc['id'] ?>"
                 class="btn btn-outline btn-sm">Преглед</a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($docs)): ?>
          <tr><td colspan="9" class="text-center text-gray p-2">Няма намерени документи.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php layoutFoot(); ?>
