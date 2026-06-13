<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

if (Auth::check()) {
    redirect(Auth::isAdmin() ? 'public/admin/index.php' : 'public/officer/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    if (Auth::login($u, $p)) {
        redirect(Auth::isAdmin() ? 'public/admin/index.php' : 'public/officer/index.php');
    }
    $error = 'Невалидно потребителско име или парола.';
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Вход — <?= h(APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= url('public/assets/css/style.css') ?>">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="logo">
      <h2><?= h(APP_NAME) ?></h2>
      <p class="text-gray text-sm mt-1">Вход за служители</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
      <div class="form-group">
        <label for="username" class="required">Потребителско име / Email</label>
        <input type="text" id="username" name="username" class="form-control"
               value="<?= h($_POST['username'] ?? '') ?>" required autofocus>
      </div>
      <div class="form-group">
        <label for="password" class="required">Парола</label>
        <input type="password" id="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-full btn-lg mt-1">Влез</button>
    </form>

    <hr class="separator">
    <p class="text-center text-sm text-gray">
      <a href="<?= url('public/submit.php') ?>">Публична страница</a>
    </p>
  </div>
</div>
<script src="<?= url('public/assets/js/main.js') ?>"></script>
</body>
</html>
