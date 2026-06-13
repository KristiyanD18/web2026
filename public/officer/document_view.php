<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/layout.php';

Auth::requireLogin();

$id  = (int)($_GET['id'] ?? 0);
$doc = DB::one(
    'SELECT d.*, c.name AS cat_name FROM documents d
     LEFT JOIN categories c ON c.id=d.category_id WHERE d.id=?',
    [$id]
);
if (!$doc) { flash('danger','Документът не е намерен.'); redirect('public/officer/index.php'); }

// Check officer access (must be responsible for this category)
if (!Auth::isAdmin()) {
    $userId = Auth::id();
    $hasAccess = !$doc['category_id'] ? false : (bool) DB::one(
        'SELECT id FROM categories WHERE id=? AND officer_user_id=?',
        [$doc['category_id'], $userId]
    );
    if (!$hasAccess) {
        flash('danger','Нямате достъп до този документ.');
        redirect('public/officer/index.php');
    }
}

$history = DB::all(
    'SELECT h.*, u.full_name FROM document_history h
     LEFT JOIN users u ON u.id=h.changed_by_id
     WHERE document_id=? ORDER BY changed_at ASC',
    [$id]
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'status') {
        $newStatus = $_POST['new_status'] ?? '';
        $notes     = trim($_POST['notes'] ?? '');
        $allowed   = ['pending','in_progress','completed','paused'];
        if (in_array($newStatus, $allowed, true)) {
            DB::query('UPDATE documents SET status=? WHERE id=?', [$newStatus, $id]);
            DB::query(
                'INSERT INTO document_history (document_id,old_status,new_status,changed_by_id,changed_by_name,notes) VALUES (?,?,?,?,?,?)',
                [$id, $doc['status'], $newStatus, Auth::id(), Auth::fullName(), $notes ?: null]
            );
            flash('success', 'Статусът е сменен.');
            redirect('public/officer/document_view.php?id=' . $id);
        }
    }

    if ($action === 'notes') {
        $notes = trim($_POST['officer_notes'] ?? '');
        DB::query('UPDATE documents SET officer_notes=? WHERE id=?', [$notes, $id]);
        flash('success', 'Бележките са запазени.');
        redirect('public/officer/document_view.php?id=' . $id);
    }
}

$logId = logAccess($id, 'view');

layoutHead('Документ ' . $doc['incoming_number']);
layoutNav('officer');
?>
<div class="container" style="max-width:860px">
  <?php layoutFlash(); ?>
  <input type="hidden" id="view-log-id" value="<?= $logId ?>"
         data-close-url="<?= url('public/ajax_log_close.php') ?>">

  <div class="page-header">
    <div>
      <h1><?= h($doc['incoming_number']) ?></h1>
      <p class="text-gray text-sm"><?= h($doc['title']) ?></p>
    </div>
    <a href="<?= url('public/officer/index.php') ?>" class="btn btn-outline btn-sm">Назад</a>
  </div>

  <div style="display:grid;grid-template-columns:3fr 2fr;gap:1.25rem">
    <div>
      <!-- Info -->
      <div class="card mb-2">
        <div class="card-header">Информация</div>
        <div class="card-body">
          <table style="width:100%;font-size:.9rem">
            <tr><td class="text-gray" style="width:35%;padding:.35rem 0">Статус</td>
                <td><span class="badge <?= statusClass($doc['status']) ?>"><?= statusLabel($doc['status']) ?></span></td></tr>
            <tr><td class="text-gray">Категория</td><td><?= h($doc['cat_name'] ?? '—') ?></td></tr>
            <tr><td class="text-gray">Приоритет</td>
                <td><?= $doc['priority']==='high' ? '<span class="badge badge-high">Приоритетен</span>' : 'Нормален' ?></td></tr>
            <tr><td class="text-gray">Подател</td>
                <td><?= h($doc['submitter_name']) ?>
                    <?php if ($doc['submitter_email']): ?><br><span class="text-gray text-sm"><?= h($doc['submitter_email']) ?></span><?php endif; ?>
                </td></tr>
            <tr><td class="text-gray">Файл</td>
                <td><?= h($doc['original_filename']) ?> (<?= formatBytes($doc['file_size']) ?>)<?= $doc['is_encrypted'] ? ' [Крипт.]' : '' ?></td></tr>
            <tr><td class="text-gray">Входирано</td>
                <td><?= h(date('d.m.Y H:i', strtotime($doc['submitted_at']))) ?></td></tr>
          </table>
        </div>
      </div>

      <!-- Change status -->
      <div class="card mb-2">
        <div class="card-header">Смяна на статус</div>
        <div class="card-body">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="status">
            <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:.75rem;align-items:end">
              <div class="form-group" style="margin:0">
                <label>Нов статус</label>
                <select name="new_status" class="form-control">
                  <?php foreach (['pending','in_progress','completed','paused'] as $s): ?>
                  <option value="<?= $s ?>" <?= $doc['status']===$s?'selected':'' ?>><?= statusLabel($s) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group" style="margin:0">
                <label>Бележка</label>
                <input type="text" name="notes" class="form-control">
              </div>
              <button class="btn btn-primary btn-sm">Смени</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Notes -->
      <div class="card mb-2">
        <div class="card-header">Бележки</div>
        <div class="card-body">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="notes">
            <textarea name="officer_notes" class="form-control" rows="3"><?= h($doc['officer_notes'] ?? '') ?></textarea>
            <button class="btn btn-outline btn-sm mt-1">Запази</button>
          </form>
        </div>
      </div>

      <!-- History -->
      <div class="card">
        <div class="card-header">История</div>
        <div class="card-body">
          <ul class="timeline">
            <?php foreach ($history as $h_item): ?>
            <li>
              <strong><?= statusLabel($h_item['new_status']) ?></strong>
              <?php if ($h_item['notes']): ?> — <?= h($h_item['notes']) ?><?php endif; ?>
              <span class="ts">
                <?= h(date('d.m.Y H:i', strtotime($h_item['changed_at']))) ?>
                · <?= h($h_item['full_name'] ?? $h_item['changed_by_name'] ?? '—') ?>
              </span>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>

    <!-- Right: QR + access code -->
    <div>
      <?php if ($doc['qr_filename']): ?>
      <div class="card mb-2">
        <div class="card-header">QR Код</div>
        <div class="card-body">
          <div class="qr-box">
            <img src="<?= url('public/qr_image.php') ?>?f=<?= urlencode($doc['qr_filename']) ?>" alt="QR">
          </div>
          <p class="text-sm text-gray text-center mt-1">
            Код: <strong><?= h($doc['access_code']) ?></strong>
          </p>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($doc['officer_notes']): ?>
      <div class="card">
        <div class="card-header">Текущи бележки</div>
        <div class="card-body text-sm"><?= nl2br(h($doc['officer_notes'])) ?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php layoutFoot(); ?>
