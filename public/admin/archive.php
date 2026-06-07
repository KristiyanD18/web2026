<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/layout.php';

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'unarchive') {
        $id = (int)$_POST['doc_id'];
        DB::query('UPDATE documents SET status="pending" WHERE id=? AND status="archived"', [$id]);
        DB::query(
            'INSERT INTO document_history (document_id, old_status, new_status, changed_by_id, changed_by_name, notes) VALUES (?,?,?,?,?,?)',
            [$id, 'archived', 'pending', Auth::id(), Auth::fullName(), 'Извлечен от архив.']
        );
        flash('success', 'Документът е извлечен от архива.');
    }

    if ($action === 'delete_permanent') {
        $id  = (int)$_POST['doc_id'];
        $doc = DB::one('SELECT stored_filename, qr_filename FROM documents WHERE id=? AND status="archived"', [$id]);
        if ($doc) {
            $fp = UPLOAD_PATH . '/' . $doc['stored_filename'];
            if (is_file($fp)) unlink($fp);
            if ($doc['qr_filename']) {
                $qp = QR_PATH . '/' . $doc['qr_filename'];
                if (is_file($qp)) unlink($qp);
            }
            DB::query('DELETE FROM documents WHERE id=?', [$id]);
            flash('success', 'Документът е изтрит окончателно.');
        }
    }

    redirect('public/admin/archive.php');
}

$search = trim($_GET['q'] ?? '');
$where  = ['d.status = "archived"'];
$params = [];
if ($search) {
    $where[]  = '(d.incoming_number LIKE ? OR d.title LIKE ? OR d.submitter_name LIKE ?)';
    $like = "%$search%";
    $params = [$like,$like,$like];
}

$docs = DB::all(
    'SELECT d.*, c.name cat_name FROM documents d
     LEFT JOIN categories c ON c.id=d.category_id
     WHERE ' . implode(' AND ', $where) . ' ORDER BY d.updated_at DESC',
    $params
);

layoutHead('Архив');
layoutNav('admin');
?>
<div class="container">
  <?php layoutFlash(); ?>
  <div class="page-header">
    <h1>🗄 Архив</h1>
    <a href="<?= url('public/admin/index.php') ?>" class="btn btn-outline btn-sm">← Начало</a>
  </div>

  <form method="get" class="d-flex gap-1 mb-2">
    <input type="text" name="q" class="form-control" placeholder="Търсене в архива…" value="<?= h($search) ?>" style="max-width:300px">
    <button class="btn btn-outline btn-sm">Търси</button>
  </form>

  <div class="card">
    <div class="card-header">🗄 Архивирани документи (<?= count($docs) ?>)</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Входящ №</th><th>Заглавие</th><th>Категория</th><th>Подател</th><th>Архивиран</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($docs as $doc): ?>
          <tr>
            <td class="fw-bold"><?= h($doc['incoming_number']) ?></td>
            <td><?= h(mb_strimwidth($doc['title'],0,40,'…')) ?></td>
            <td class="text-sm"><?= h($doc['cat_name'] ?? '—') ?></td>
            <td class="text-sm"><?= h($doc['submitter_name']) ?></td>
            <td class="text-sm text-gray"><?= h(date('d.m.Y H:i', strtotime($doc['updated_at']))) ?></td>
            <td class="d-flex gap-1">
              <a href="<?= url('public/admin/document_view.php') ?>?id=<?= $doc['id'] ?>"
                 class="btn btn-outline btn-sm">Преглед</a>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                <input type="hidden" name="action" value="unarchive">
                <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                <button class="btn btn-warning btn-sm">Извлечи</button>
              </form>
              <form method="post" style="display:inline"
                    onsubmit="return confirm('Окончателно изтриване! Файлът ще бъде изтрит от диска. Продължи?')">
                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                <input type="hidden" name="action" value="delete_permanent">
                <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                <button class="btn btn-danger btn-sm">Изтрий</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($docs)): ?>
          <tr><td colspan="6" class="text-center text-gray p-2">Архивът е празен.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php layoutFoot(); ?>
