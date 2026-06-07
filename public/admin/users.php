<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/layout.php';

Auth::requireAdmin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username  = trim($_POST['username'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $fullName  = trim($_POST['full_name'] ?? '');
        $role      = in_array($_POST['role'] ?? '', ['admin','officer']) ? $_POST['role'] : 'officer';
        $password  = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';

        if (!$username || !$email || !$fullName || !$password) $errors[] = 'Всички полета са задължителни.';
        if ($password !== $password2) $errors[] = 'Паролите не съвпадат.';
        if (strlen($password) < 8) $errors[] = 'Паролата трябва да е поне 8 символа.';
        if (DB::one('SELECT id FROM users WHERE username=?', [$username])) $errors[] = 'Потребителското име е заето.';
        if (DB::one('SELECT id FROM users WHERE email=?', [$email])) $errors[] = 'Email адресът е зает.';

        if (empty($errors)) {
            DB::insert(
                'INSERT INTO users (username, email, password_hash, full_name, role) VALUES (?,?,?,?,?)',
                [$username, $email, password_hash($password, PASSWORD_BCRYPT), $fullName, $role]
            );
            flash('success', 'Потребителят е добавен.');
            redirect('public/admin/users.php');
        }
    }

    if ($action === 'toggle') {
        $uid = (int)$_POST['user_id'];
        if ($uid !== Auth::id()) {
            DB::query('UPDATE users SET is_active = NOT is_active WHERE id=?', [$uid]);
        }
        redirect('public/admin/users.php');
    }

    if ($action === 'reset_password') {
        $uid  = (int)$_POST['user_id'];
        $pass = $_POST['new_password'] ?? '';
        if (strlen($pass) >= 8) {
            DB::query('UPDATE users SET password_hash=? WHERE id=?', [password_hash($pass, PASSWORD_BCRYPT), $uid]);
            flash('success', 'Паролата е сменена.');
        } else {
            flash('danger', 'Паролата трябва да е поне 8 символа.');
        }
        redirect('public/admin/users.php');
    }
}

$users = DB::all('SELECT * FROM users ORDER BY role, full_name');

layoutHead('Потребители');
layoutNav('admin');
?>
<div class="container" style="max-width:900px">
  <?php layoutFlash(); ?>
  <?php foreach ($errors as $e): ?>
  <div class="alert alert-danger"><?= h($e) ?></div>
  <?php endforeach; ?>

  <div class="page-header">
    <h1>👤 Потребители</h1>
    <a href="<?= url('public/admin/index.php') ?>" class="btn btn-outline btn-sm">← Начало</a>
  </div>

  <!-- Add user -->
  <div class="card mb-3">
    <div class="card-header">Нов потребител</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="create">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
          <div class="form-group">
            <label class="required">Потребителско име</label>
            <input type="text" name="username" class="form-control" required
                   value="<?= h($_POST['username'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="required">Email</label>
            <input type="email" name="email" class="form-control" required
                   value="<?= h($_POST['email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="required">Пълно Пространство</label>
            <input type="text" name="full_name" class="form-control" required
                   value="<?= h($_POST['full_name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Роля</label>
            <select name="role" class="form-control">
              <option value="officer">Отговорник</option>
              <option value="admin">Администратор</option>
            </select>
          </div>
          <div class="form-group">
            <label class="required">Парола</label>
            <input type="password" name="password" class="form-control" required minlength="8">
          </div>
          <div class="form-group">
            <label class="required">Повторете паролата</label>
            <input type="password" name="password2" class="form-control" required minlength="8">
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Добави потребител</button>
      </form>
    </div>
  </div>

  <!-- Users list -->
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Пространство</th><th>Username</th><th>Email</th><th>Роля</th><th>Активен</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td class="fw-bold"><?= h($u['full_name']) ?></td>
            <td class="text-sm"><?= h($u['username']) ?></td>
            <td class="text-sm"><?= h($u['email']) ?></td>
            <td>
              <span class="badge <?= $u['role']==='admin'?'badge-danger':'badge-info' ?>">
                <?= $u['role'] === 'admin' ? 'Администратор' : 'Отговорник' ?>
              </span>
            </td>
            <td><?= $u['is_active'] ? '<span class="badge badge-success">Да</span>' : '<span class="badge badge-secondary">Не</span>' ?></td>
            <td class="d-flex gap-1" style="white-space:nowrap">
              <?php if ($u['id'] !== Auth::id()): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button class="btn btn-outline btn-sm"><?= $u['is_active'] ? 'Деактивирай' : 'Активирай' ?></button>
              </form>
              <?php endif; ?>
              <button onclick="togglePwReset(<?= $u['id'] ?>)" class="btn btn-outline btn-sm">Нова парола</button>
              <div id="pw-<?= $u['id'] ?>" style="display:none">
                <form method="post" class="d-flex gap-1 align-center mt-1">
                  <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                  <input type="hidden" name="action" value="reset_password">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <input type="password" name="new_password" class="form-control" placeholder="Нова парола (мин. 8)" minlength="8" style="width:200px">
                  <button class="btn btn-warning btn-sm">Смени</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function togglePwReset(id) {
  const el = document.getElementById('pw-' + id);
  el.style.display = el.style.display === 'none' ? '' : 'none';
}
</script>
<?php layoutFoot(); ?>
