<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

$doc     = null;
$history = [];
$error   = '';
$logId   = null;

$number = trim($_GET['n'] ?? '');
$code   = strtoupper(trim($_GET['c'] ?? ''));

// Form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $number = strtoupper(trim($_POST['number'] ?? ''));
    $code   = strtoupper(trim($_POST['code']   ?? ''));
}

if ($number && $code) {
    $doc = DB::one(
        'SELECT d.*, c.name AS category_name
         FROM documents d
         LEFT JOIN categories c ON c.id = d.category_id
         WHERE d.incoming_number = ? AND d.access_code = ?',
        [$number, $code]
    );
    if ($doc) {
        $history = DB::all(
            'SELECT * FROM document_history WHERE document_id = ? ORDER BY changed_at ASC',
            [$doc['id']]
        );
        // Log the access
        $logId = logAccess($doc['id'], 'status_check');
    } else {
        $error = 'Документ с такъв номер и код за достъп не е намерен.';
    }
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= h(csrf()) ?>">
  <title>Проследяване — <?= h(APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= url('public/assets/css/style.css') ?>">
</head>
<body>
<?php layoutNav('track'); ?>

<div class="hero" style="padding:2.5rem 1.5rem 2rem">
  <h1>🔍 Проследяване на Документ</h1>
  <p>Въведете входящия номер и кода за достъп</p>
</div>

<div class="container" style="max-width:720px">
  <!-- SEARCH FORM -->
  <div class="card mb-3">
    <div class="card-body">
      <?php if ($error): ?>
      <div class="alert alert-danger"><?= h($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
        <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:.75rem;align-items:end">
          <div class="form-group" style="margin:0">
            <label class="required">Входящ номер</label>
            <input type="text" name="number" class="form-control"
                   value="<?= h($number) ?>" placeholder="ВХ-2026-00001" required>
          </div>
          <div class="form-group" style="margin:0">
            <label class="required">Код за достъп</label>
            <input type="text" name="code" class="form-control"
                   value="<?= h($code) ?>" placeholder="AB12CD34"
                   style="text-transform:uppercase" required maxlength="12">
          </div>
          <button type="submit" class="btn btn-primary">Провери</button>
        </div>
      </form>
    </div>
  </div>

  <?php if ($doc): ?>
  <!-- RESULT -->
  <?php if ($logId): ?>
  <input type="hidden" id="view-log-id" value="<?= $logId ?>"
         data-close-url="<?= url('public/ajax_log_close.php') ?>">
  <?php endif; ?>

  <div class="card mb-3">
    <div class="card-header">
      📄 <?= h($doc['title']) ?>
    </div>
    <div class="card-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;flex-wrap:wrap">
        <div>
          <p class="text-sm text-gray">Входящ номер</p>
          <p class="fw-bold" style="font-size:1.1rem;color:var(--clr-primary)"><?= h($doc['incoming_number']) ?></p>
        </div>
        <div>
          <p class="text-sm text-gray">Статус</p>
          <p><span class="badge <?= statusClass($doc['status']) ?>"><?= statusLabel($doc['status']) ?></span></p>
        </div>
        <div>
          <p class="text-sm text-gray">Категория</p>
          <p><?= h($doc['category_name'] ?? 'Без категория') ?></p>
        </div>
        <div>
          <p class="text-sm text-gray">Приоритет</p>
          <p>
            <?php if ($doc['priority'] === 'high'): ?>
              <span class="badge badge-high">🔥 Приоритетен</span>
            <?php else: ?>
              <span class="badge badge-secondary">Нормален</span>
            <?php endif; ?>
          </p>
        </div>
        <div>
          <p class="text-sm text-gray">Подател</p>
          <p><?= h($doc['submitter_name']) ?></p>
        </div>
        <div>
          <p class="text-sm text-gray">Входирано на</p>
          <p><?= h(date('d.m.Y H:i', strtotime($doc['submitted_at']))) ?></p>
        </div>
      </div>

      <?php if ($doc['officer_notes']): ?>
      <div class="alert alert-info mt-2">
        <strong>Бележка от отговорника:</strong> <?= h($doc['officer_notes']) ?>
      </div>
      <?php endif; ?>

      <?php if ($doc['status'] === 'paused'): ?>
      <div class="alert alert-warning mt-2">
        ⏸ Обработката на документа е временно паузирана.
      </div>
      <?php endif; ?>

      <?php if ($doc['qr_filename']): ?>
      <div class="qr-box mt-2" style="max-width:180px">
        <img src="<?= url('public/qr_image.php') ?>?f=<?= urlencode($doc['qr_filename']) ?>" alt="QR">
        <p class="text-sm text-gray mt-1">QR за бърз достъп</p>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- HISTORY TIMELINE -->
  <?php if ($history): ?>
  <div class="card">
    <div class="card-header">📋 История на документа</div>
    <div class="card-body">
      <ul class="timeline">
        <?php foreach ($history as $h_item): ?>
        <li>
          <strong><?= statusLabel($h_item['new_status']) ?></strong>
          <?php if ($h_item['notes']): ?>
            — <?= h($h_item['notes']) ?>
          <?php endif; ?>
          <span class="ts">
            <?= h(date('d.m.Y H:i', strtotime($h_item['changed_at']))) ?>
            <?php if ($h_item['changed_by_name']): ?>
              · <?= h($h_item['changed_by_name']) ?>
            <?php endif; ?>
          </span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <?php endif; ?>

  <?php endif; ?>

  <div class="text-center mt-2">
    <a href="<?= url('public/submit.php') ?>" class="text-sm">← Входири нов документ</a>
  </div>
</div>
<script src="<?= url('public/assets/js/main.js') ?>"></script>
</body>
</html>
