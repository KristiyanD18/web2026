<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/crypto/CryptoHelper.php';

Auth::requireAdmin();

$id  = (int)($_GET['id'] ?? 0);
$doc = DB::one(
    'SELECT d.*, c.name AS cat_name FROM documents d
     LEFT JOIN categories c ON c.id=d.category_id WHERE d.id=?',
    [$id]
);
if (!$doc) { flash('danger','Документът не е намерен.'); redirect('public/admin/index.php'); }

$history    = DB::all('SELECT h.*, u.full_name FROM document_history h LEFT JOIN users u ON u.id=h.changed_by_id WHERE document_id=? ORDER BY changed_at ASC', [$id]);
$accessLogs = DB::all('SELECT l.*, u.full_name FROM access_log l LEFT JOIN users u ON u.id=l.user_id WHERE document_id=? ORDER BY accessed_at DESC LIMIT 30', [$id]);
$categories = DB::all('SELECT * FROM categories WHERE is_active=1 ORDER BY name');
$users      = DB::all('SELECT * FROM users WHERE is_active=1 ORDER BY full_name');
$encInfo    = DB::one('SELECT * FROM document_encryptions WHERE document_id=?', [$id]);
$keyParts   = $encInfo ? DB::all('SELECT kp.*, u.full_name FROM encryption_key_parts kp LEFT JOIN users u ON u.id=kp.holder_user_id WHERE encryption_id=? ORDER BY part_index', [$encInfo['id']]) : [];

$errors = [];
$decryptTempPath = null;

// ── Handle POST actions ───────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // Change status + priority combined
    if ($action === 'status_priority') {
        $newStatus = $_POST['new_status'] ?? '';
        $allowed   = ['pending','in_progress','completed','paused','archived'];
        $p         = ($_POST['priority'] ?? '') === 'high' ? 'high' : 'normal';
        if (in_array($newStatus, $allowed, true)) {
            DB::query('UPDATE documents SET status=?, priority=? WHERE id=?', [$newStatus, $p, $id]);
            DB::query(
                'INSERT INTO document_history (document_id,old_status,new_status,changed_by_id,changed_by_name,notes) VALUES (?,?,?,?,?,?)',
                [$id, $doc['status'], $newStatus, Auth::id(), Auth::fullName(), null]
            );
            $doc['status']   = $newStatus;
            $doc['priority'] = $p;
            flash('success', 'Документът е обновен.');
            redirect('public/admin/document_view.php?id=' . $id);
        }
    }

    // Change status
    if ($action === 'status') {
        $newStatus = $_POST['new_status'] ?? '';
        $notes     = trim($_POST['notes'] ?? '');
        $allowed   = ['pending','in_progress','completed','paused','archived'];
        if (in_array($newStatus, $allowed, true)) {
            DB::query('UPDATE documents SET status=? WHERE id=?', [$newStatus, $id]);
            DB::query(
                'INSERT INTO document_history (document_id,old_status,new_status,changed_by_id,changed_by_name,notes) VALUES (?,?,?,?,?,?)',
                [$id, $doc['status'], $newStatus, Auth::id(), Auth::fullName(), $notes ?: null]
            );
            $doc['status'] = $newStatus;
            flash('success', 'Статусът е сменен на: ' . statusLabel($newStatus));
            redirect('public/admin/document_view.php?id=' . $id);
        }
    }

    // Change category
    if ($action === 'category') {
        $catId = (int)($_POST['category_id'] ?? 0) ?: null;
        DB::query('UPDATE documents SET category_id=? WHERE id=?', [$catId, $id]);
        flash('success', 'Категорията е обновена.');
        redirect('public/admin/document_view.php?id=' . $id);
    }

    // Change priority
    if ($action === 'priority') {
        $p = $_POST['priority'] === 'high' ? 'high' : 'normal';
        DB::query('UPDATE documents SET priority=? WHERE id=?', [$p, $id]);
        DB::query(
            'INSERT INTO document_history (document_id,old_status,new_status,changed_by_id,changed_by_name,notes) VALUES (?,?,?,?,?,?)',
            [$id, $doc['status'], $doc['status'], Auth::id(), Auth::fullName(), 'Приоритет сменен на: ' . priorityLabel($p)]
        );
        flash('success', 'Приоритетът е обновен.');
        redirect('public/admin/document_view.php?id=' . $id);
    }

    // Save notes
    if ($action === 'notes') {
        $notes = trim($_POST['officer_notes'] ?? '');
        DB::query('UPDATE documents SET officer_notes=? WHERE id=?', [$notes, $id]);
        flash('success', 'Бележките са запазени.');
        redirect('public/admin/document_view.php?id=' . $id);
    }

    // Encrypt document
    if ($action === 'encrypt' && !$doc['is_encrypted']) {
        $numParts  = max(2, min(5, (int)($_POST['num_parts'] ?? 2)));
        $partUsers = $_POST['part_user']     ?? [];
        $partPasses= $_POST['part_password'] ?? [];

        if (count($partUsers) !== $numParts || count($partPasses) !== $numParts) {
            $errors[] = 'Посочете всички притежатели и техните пароли.';
        } else {
            $filePath = UPLOAD_PATH . '/' . $doc['stored_filename'];
            if (!is_file($filePath)) {
                $errors[] = 'Файлът не е намерен на диска.';
            } else {
                $key   = CryptoHelper::generateKey();
                $parts = CryptoHelper::splitKey($key, $numParts);
                CryptoHelper::encryptFile($filePath, $key);

                $encId = DB::insert(
                    'INSERT INTO document_encryptions (document_id, num_parts) VALUES (?,?)',
                    [$id, $numParts]
                );

                for ($i = 0; $i < $numParts; $i++) {
                    $userId   = (int)$partUsers[$i];
                    $password = $partPasses[$i];
                    $encPart  = CryptoHelper::encryptPart($parts[$i], $password);
                    $holderRow = DB::one('SELECT full_name FROM users WHERE id=?', [$userId]);
                    DB::insert(
                        'INSERT INTO encryption_key_parts (encryption_id, part_index, holder_user_id, holder_name, encrypted_part) VALUES (?,?,?,?,?)',
                        [$encId, $i + 1, $userId, $holderRow['full_name'] ?? 'Неизвестен', $encPart]
                    );
                }

                DB::query('UPDATE documents SET is_encrypted=1 WHERE id=?', [$id]);
                logAccess($id, 'view');
                flash('success', 'Документът е криптиран успешно. Всеки притежател трябва да запомни паролата си.');
                redirect('public/admin/document_view.php?id=' . $id);
            }
        }
    }

    // Decrypt + download
    if ($action === 'decrypt' && $doc['is_encrypted'] && $encInfo) {
        $passwords = $_POST['decrypt_password'] ?? [];
        $collectedParts = [];
        $decryptError = false;

        foreach ($keyParts as $idx => $kp) {
            $pass = $passwords[$idx] ?? '';
            $plain = CryptoHelper::decryptPart($kp['encrypted_part'], $pass);
            if ($plain === null) {
                $decryptError = true;
                $errors[] = 'Неправилна парола за притежател: ' . h($kp['holder_name']);
            } else {
                $collectedParts[] = $plain;
            }
        }

        if (!$decryptError) {
            try {
                $key     = CryptoHelper::joinParts($collectedParts);
                $encPath = UPLOAD_PATH . '/' . $doc['stored_filename'];
                $tmpPath = CryptoHelper::decryptFileToTemp($encPath, $key);
                logAccess($id, 'decrypt_success');
                // Stream the file
                $ext      = pathinfo($doc['original_filename'], PATHINFO_EXTENSION);
                $mime     = $ext === 'pdf' ? 'application/pdf' : 'application/zip';
                $safeFile = preg_replace('/[^a-zA-Z0-9._-]/', '_', $doc['original_filename']);
                header('Content-Type: ' . $mime);
                header('Content-Disposition: attachment; filename="' . $safeFile . '"');
                header('Content-Length: ' . filesize($tmpPath));
                readfile($tmpPath);
                unlink($tmpPath);
                exit;
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
                logAccess($id, 'decrypt_attempt', false);
            }
        } else {
            logAccess($id, 'decrypt_attempt', false);
        }
    }

    // Download (unencrypted)
    if ($action === 'download' && !$doc['is_encrypted']) {
        $filePath = UPLOAD_PATH . '/' . $doc['stored_filename'];
        if (is_file($filePath)) {
            $ext      = pathinfo($doc['original_filename'], PATHINFO_EXTENSION);
            $mime     = $ext === 'pdf' ? 'application/pdf' : 'application/zip';
            $safeFile = preg_replace('/[^a-zA-Z0-9._-]/', '_', $doc['original_filename']);
            logAccess($id, 'download');
            header('Content-Type: ' . $mime);
            header('Content-Disposition: attachment; filename="' . $safeFile . '"');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        }
        flash('danger','Файлът не е намерен.');
        redirect('public/admin/document_view.php?id=' . $id);
    }
}

$logId = logAccess($id, 'view');

layoutHead('Документ ' . $doc['incoming_number']);
layoutNav('admin');
?>
<div class="container">
  <?php layoutFlash(); ?>
  <?php foreach ($errors as $e): ?>
  <div class="alert alert-danger"><?= h($e) ?></div>
  <?php endforeach; ?>

  <input type="hidden" id="view-log-id" value="<?= $logId ?>"
         data-close-url="<?= url('public/ajax_log_close.php') ?>">

  <div class="page-header">
    <div>
      <h1><?= h($doc['incoming_number']) ?></h1>
      <p class="text-gray text-sm"><?= h($doc['title']) ?></p>
    </div>
    <div class="d-flex gap-1">
      <a href="<?= url('public/admin/index.php') ?>" class="btn btn-outline btn-sm">Назад</a>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem">

    <!-- LEFT COLUMN -->
    <div>
      <!-- Document info -->
      <div class="card mb-2">
        <div class="card-header">Информация</div>
        <div class="card-body">
          <table style="width:100%;font-size:.9rem">
            <tr><td class="text-gray" style="width:35%;padding:.35rem 0">Входящ №</td>
                <td class="fw-bold" style="color:var(--clr-primary)"><?= h($doc['incoming_number']) ?></td></tr>
            <tr><td class="text-gray">Статус</td>
                <td><span class="badge <?= statusClass($doc['status']) ?>"><?= statusLabel($doc['status']) ?></span></td></tr>
            <tr><td class="text-gray">Приоритет</td>
                <td><?= $doc['priority']==='high' ? '<span class="badge badge-high">Приоритетен</span>' : 'Нормален' ?></td></tr>
            <tr><td class="text-gray">Категория</td>
                <td><?= h($doc['cat_name'] ?? '—') ?></td></tr>
            <tr><td class="text-gray">Подател</td>
                <td><?= h($doc['submitter_name']) ?>
                  <?php if ($doc['submitter_email']): ?>
                    <br><span class="text-gray text-sm"><?= h($doc['submitter_email']) ?></span>
                  <?php endif; ?>
                  <?php if ($doc['submitter_phone']): ?>
                    <br><span class="text-gray text-sm"><?= h($doc['submitter_phone']) ?></span>
                  <?php endif; ?>
                  <?php if ($doc['submitter_faculty_number']): ?>
                    <br><span class="text-gray text-sm">Фак. №: <?= h($doc['submitter_faculty_number']) ?></span>
                  <?php endif; ?>
                </td></tr>
            <tr><td class="text-gray">Файл</td>
                <td><?= h($doc['original_filename']) ?> (<?= formatBytes($doc['file_size']) ?>)
                  <?php if ($doc['is_encrypted']): ?> [Крипт.]<?php endif; ?>
                </td></tr>
            <tr><td class="text-gray">Входирано</td>
                <td><?= h(date('d.m.Y H:i', strtotime($doc['submitted_at']))) ?></td></tr>
          </table>
        </div>
      </div>

      <!-- Change status + priority -->
      <div class="card mb-2">
        <div class="card-header">Статус и приоритет</div>
        <div class="card-body">
          <form method="post" class="d-flex gap-1 align-center">
            <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="status_priority">
            <select name="new_status" class="form-control">
              <?php foreach (['pending','in_progress','completed','paused','archived'] as $s): ?>
              <option value="<?= $s ?>" <?= $doc['status']===$s?'selected':'' ?>><?= statusLabel($s) ?></option>
              <?php endforeach; ?>
            </select>
            <select name="priority" class="form-control">
              <option value="normal" <?= $doc['priority']==='normal'?'selected':'' ?>>Нормален</option>
              <option value="high"   <?= $doc['priority']==='high'?'selected':'' ?>>Висок</option>
            </select>
            <button class="btn btn-primary btn-sm">Запази</button>
          </form>
        </div>
      </div>

      <!-- Officer notes -->
      <div class="card mb-2">
        <div class="card-header">Бележки от отговорника</div>
        <div class="card-body">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="notes">
            <textarea name="officer_notes" class="form-control" rows="3"
                      maxlength="2000"><?= h($doc['officer_notes'] ?? '') ?></textarea>
            <button class="btn btn-outline btn-sm mt-1">Запази бележките</button>
          </form>
        </div>
      </div>

      <!-- History -->
      <div class="card mb-2">
        <div class="card-header">История</div>
        <div class="card-body">
          <ul class="timeline">
            <?php foreach ($history as $h_item): ?>
            <li>
              <strong><?= statusLabel($h_item['new_status']) ?></strong>
              <?php if ($h_item['notes']): ?> — <?= h($h_item['notes']) ?><?php endif; ?>
              <span class="ts">
                <?= h(date('d.m.Y H:i', strtotime($h_item['changed_at']))) ?>
                · <?= h($h_item['full_name'] ?? $h_item['changed_by_name'] ?? 'Подател') ?>
              </span>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>

      <!-- Access log -->
      <div class="card">
        <div class="card-header">Лог за достъп</div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Тип</th><th>От</th><th>IP</th><th>Дата</th><th>Продъл.</th><th>Успех</th></tr></thead>
            <tbody>
              <?php foreach ($accessLogs as $log): ?>
              <tr>
                <td class="text-sm"><?= h($log['access_type']) ?></td>
                <td class="text-sm"><?= h($log['full_name'] ?? 'Публичен') ?></td>
                <td class="text-sm text-gray"><?= h($log['ip_address']) ?></td>
                <td class="text-sm text-gray"><?= h(date('d.m.Y H:i', strtotime($log['accessed_at']))) ?></td>
                <td class="text-sm"><?= $log['duration_seconds'] ? $log['duration_seconds'].'с' : '—' ?></td>
                <td><?= $log['success'] ? 'Да' : 'Не' ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($accessLogs)): ?>
              <tr><td colspan="6" class="text-center text-gray">Няма записи.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- RIGHT COLUMN -->
    <div>
      <!-- Category -->
      <div class="card mb-2">
        <div class="card-header">Категория</div>
        <div class="card-body">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="category">
            <select name="category_id" class="form-control mb-1">
              <option value="">— Без категория —</option>
              <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>" <?= $doc['category_id']==$cat['id']?'selected':'' ?>><?= h($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-outline btn-sm w-full">Запази</button>
          </form>
        </div>
      </div>

      <!-- QR code -->
      <?php if ($doc['qr_filename']): ?>
      <div class="card mb-2">
        <div class="card-header">QR Код</div>
        <div class="card-body">
          <div class="qr-box">
            <img src="<?= url('public/qr_image.php') ?>?f=<?= urlencode($doc['qr_filename']) ?>" alt="QR">
          </div>
          <p class="text-sm text-gray text-center mt-1">Код за достъп: <strong><?= h($doc['access_code']) ?></strong></p>
        </div>
      </div>
      <?php endif; ?>

      <!-- Download -->
      <?php if (!$doc['is_encrypted']): ?>
      <div class="card mb-2">
        <div class="card-header">Изтегляне</div>
        <div class="card-body">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="download">
            <button class="btn btn-primary w-full">Изтегли файла</button>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <!-- Encryption -->
      <?php if (!$doc['is_encrypted']): ?>
      <div class="card mb-2">
        <div class="card-header">Криптиране</div>
        <div class="card-body">
          <p class="text-sm text-gray mb-2">Криптирайте документа с разделен ключ.</p>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="encrypt">
            <input type="hidden" name="num_parts" id="num_parts" value="2">

            <div id="key-parts-container"
                 data-user-options="<?php
                   foreach ($users as $u) {
                       echo "<option value='" . h($u['id']) . "'>" . h($u['full_name']) . "</option>";
                   }
                 ?>">
              <!-- Initial 2 rows -->
              <?php for ($i = 1; $i <= 2; $i++): ?>
              <div class="key-part-row">
                <div class="form-group" style="margin:0">
                  <label>Притежател <?= $i ?></label>
                  <select name="part_user[]" class="form-control" required>
                    <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= h($u['full_name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group" style="margin:0">
                  <label>Парола на притежател <?= $i ?></label>
                  <input type="password" name="part_password[]" class="form-control" required>
                </div>
                <?php if ($i > 2): ?>
                <button type="button" class="btn btn-danger btn-sm remove-part">X</button>
                <?php else: ?>
                <div></div>
                <?php endif; ?>
              </div>
              <?php endfor; ?>
            </div>

            <input type="hidden" id="max-parts" value="5">
            <button type="button" id="add-key-part" class="btn btn-outline btn-sm mb-2">+ Добави притежател</button>
            <button type="submit" class="btn btn-warning w-full"
                    data-confirm="Криптирането е необратимо! Продължи?">Криптирай</button>
          </form>
        </div>
      </div>
      <?php else: ?>
      <!-- Decrypt -->
      <div class="card mb-2">
        <div class="card-header">Декриптиране и Изтегляне</div>
        <div class="card-body">
          <p class="text-sm text-gray mb-2">
            Изисква пароли от <strong><?= $encInfo['num_parts'] ?></strong> притежател(и).
          </p>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="decrypt">
            <?php foreach ($keyParts as $idx => $kp): ?>
            <div class="form-group">
              <label>Парола на: <strong><?= h($kp['holder_name']) ?></strong></label>
              <input type="password" name="decrypt_password[<?= $idx ?>]"
                     class="form-control" required>
            </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-primary w-full">Декриптирай и изтегли</button>
          </form>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>
<?php layoutFoot(); ?>
