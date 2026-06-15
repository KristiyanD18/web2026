<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/qr/QRGenerator.php';
require_once __DIR__ . '/../includes/layout.php';

$categories = DB::all('SELECT * FROM categories WHERE is_active=1 ORDER BY name');

$result = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $title   = trim($_POST['title']   ?? '');
    $desc    = trim($_POST['desc']    ?? '');
    $catId   = (int)($_POST['category_id'] ?? 0) ?: null;
    $name          = trim($_POST['submitter_name']           ?? '');
    $email         = trim($_POST['submitter_email']          ?? '');
    $phone         = trim($_POST['submitter_phone']          ?? '');
    $facultyNumber = trim($_POST['submitter_faculty_number'] ?? '');

    if (!$title) $errors[] = 'Заглавието е задължително.';
    if (!$name)  $errors[] = 'Подателят е задължителен.';
    if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Моля, прикачете файл (PDF или ZIP).';
    }

    if (empty($errors)) {
        try {
            $upload    = handleDocumentUpload($_FILES['document_file']);
            $incomingN = generateIncomingNumber();
            $accessCode = generateAccessCode();

            $docId = DB::insert(
                'INSERT INTO documents
                    (incoming_number, title, description, original_filename, stored_filename,
                     file_type, file_size, category_id, access_code,
                     submitter_name, submitter_email, submitter_phone, submitter_faculty_number,
                     submitted_by_user_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $incomingN, $title, $desc,
                    $upload['original_name'], $upload['stored_name'],
                    $upload['file_type'], $upload['file_size'],
                    $catId, $accessCode,
                    $name, $email ?: null, $phone ?: null, $facultyNumber ?: null,
                    Auth::check() ? Auth::id() : null,
                ]
            );

            // Record initial status history
            DB::query(
                'INSERT INTO document_history (document_id, new_status, changed_by_name, notes)
                 VALUES (?, "pending", ?, "Документът е входиран.")',
                [$docId, $name]
            );

            // Generate QR code
            $qrFile = $docId . '_' . bin2hex(random_bytes(4)) . '.png';
            $qrPath = QR_PATH . '/' . $qrFile;
            $trackUrl = url('public/track.php') . '?n=' . urlencode($incomingN) . '&c=' . $accessCode;
            QRGenerator::generate($trackUrl, $qrPath);

            DB::query('UPDATE documents SET qr_filename=? WHERE id=?', [$qrFile, $docId]);

            $result = [
                'incoming_number' => $incomingN,
                'access_code'     => $accessCode,
                'qr_file'         => $qrFile,
                'title'           => $title,
                'doc_id'          => $docId,
            ];

        } catch (Throwable $e) {
            $errors[] = 'Грешка: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= h(csrf()) ?>">
  <title>Входиране на Документ — <?= h(APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= url('public/assets/css/style.css') ?>">
</head>
<body>
<?php layoutNav('submit'); ?>


<?php if ($result): ?>
<!-- SUCCESS -->
<div class="container submit-section" style="max-width:700px;padding-top:2rem">
  <div class="card">
    <div class="card-header" style="background:#ecfdf5;color:#065f46">
      Документът е успешно входиран!
    </div>
    <div class="card-body">
      <div class="d-flex gap-2" style="flex-wrap:wrap;align-items:flex-start">
        <div style="flex:1;min-width:240px">
          <p class="text-sm text-gray mb-1">Входящ номер</p>
          <p class="fw-bold" style="font-size:1.5rem;color:var(--clr-primary)"><?= h($result['incoming_number']) ?></p>

          <p class="text-sm text-gray mb-1 mt-2">Код за достъп</p>
          <p class="fw-bold" style="font-size:1.3rem;letter-spacing:.15em"><?= h($result['access_code']) ?></p>

          <div class="alert alert-warning mt-2">
            Запазете входящия номер и кода за достъп! Те ви трябват за проследяване на документа.
          </div>

          <a href="<?= url('public/track.php') ?>?n=<?= urlencode($result['incoming_number']) ?>&c=<?= h($result['access_code']) ?>"
             class="btn btn-primary mt-1">Проследи документа</a>

          <a href="<?= url('public/submit.php') ?>" class="btn btn-outline mt-1">Входирай нов документ</a>
        </div>
        <div class="qr-box" style="width:200px">
          <p class="text-sm text-gray mb-1">QR код за проследяване</p>
          <img src="<?= url('public/qr_image.php') ?>?f=<?= urlencode($result['qr_file']) ?>"
               alt="QR Code" style="max-width:160px">
          <p class="text-sm text-gray mt-1">Сканирайте за бърз достъп</p>
        </div>
      </div>
    </div>
  </div>
</div>

<?php else: ?>
<!-- FORM — two-column split -->
<div style="display:flex">

  <!-- LEFT: document details -->
  <div style="flex:1;overflow-y:auto;padding:2rem 2.5rem">
    <h2 style="font-size:1.1rem;font-weight:700;margin-bottom:1.25rem">Детайли на документа</h2>

    <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><?= h($e) ?></div>
    <?php endforeach; ?>

    <form id="submit-form" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf() ?>">

      <div class="form-group">
        <label for="title" class="required">Заглавие на документа</label>
        <input type="text" id="title" name="title" class="form-control"
               value="<?= h($_POST['title'] ?? '') ?>" required maxlength="255">
      </div>

      <div class="form-group">
        <label for="desc">Описание (незадължително)</label>
        <textarea id="desc" name="desc" class="form-control" rows="4"
                  maxlength="2000"><?= h($_POST['desc'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label for="category_id">Категория</label>
        <select id="category_id" name="category_id" class="form-control">
          <option value="">— Без категория —</option>
          <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id'] ?>"
            <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>>
            <?= h($cat['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>

  <!-- RIGHT: submitter + file + submit -->
  <div style="flex:1;overflow-y:auto;padding:2rem 2.5rem">
    <h2 style="font-size:1.1rem;font-weight:700;margin-bottom:1.25rem">Информация за подателя</h2>

    <div>
      <div class="form-group">
        <label for="submitter_name" class="required">Подател</label>
        <input type="text" id="submitter_name" name="submitter_name" form="submit-form"
               class="form-control" value="<?= h($_POST['submitter_name'] ?? '') ?>"
               required maxlength="100">
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
        <div class="form-group">
          <label for="submitter_email">Email</label>
          <input type="email" id="submitter_email" name="submitter_email" form="submit-form"
                 class="form-control" value="<?= h($_POST['submitter_email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="submitter_phone">Телефон</label>
          <input type="tel" id="submitter_phone" name="submitter_phone" form="submit-form"
                 class="form-control" value="<?= h($_POST['submitter_phone'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label for="submitter_faculty_number">Факултетен номер</label>
        <input type="text" id="submitter_faculty_number" name="submitter_faculty_number" form="submit-form"
               class="form-control" value="<?= h($_POST['submitter_faculty_number'] ?? '') ?>" maxlength="20">
      </div>

      <div class="form-group" style="margin-top:.5rem">
        <label class="required">Файл (PDF или ZIP)</label>
        <div style="border:2px dashed var(--clr-border);border-radius:var(--radius);padding:1.5rem;text-align:center;cursor:pointer"
             onclick="document.getElementById('document_file').click()">
          <p id="file-label" class="text-gray text-sm mt-1">Изберете PDF или ZIP файл</p>
          <p class="text-sm text-gray" style="font-size:.75rem">Макс. <?= formatBytes(UPLOAD_MAX) ?></p>
        </div>
        <input type="file" id="document_file" name="document_file" form="submit-form"
               accept=".pdf,.zip" style="display:none" required>
      </div>
    </div>

    </div>
  </div>

</div>
<div style="padding:1rem 2.5rem;text-align:center">
  <button type="submit" form="submit-form" class="btn btn-primary btn-lg" style="min-width:260px">
    Входирай документа
  </button>
</div>
<?php endif; ?>

<script src="<?= url('public/assets/js/main.js') ?>"></script>
</body>
</html>