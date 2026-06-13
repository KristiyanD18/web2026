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

    if ($action === 'create') {
        $name   = trim($_POST['name'] ?? '');
        $desc   = trim($_POST['desc'] ?? '');
        $officer= (int)($_POST['officer_user_id'] ?? 0) ?: null;
        if ($name) {
            DB::query('INSERT INTO categories (name, description, officer_user_id) VALUES (?,?,?)', [$name,$desc,$officer]);
            flash('success','Категорията е добавена.');
        } else {
            flash('danger','Името е задължително.');
        }
    }

    if ($action === 'update') {
        $catId  = (int)$_POST['cat_id'];
        $name   = trim($_POST['name'] ?? '');
        $desc   = trim($_POST['desc'] ?? '');
        $officer= (int)($_POST['officer_user_id'] ?? 0) ?: null;
        $active = isset($_POST['is_active']) ? 1 : 0;
        DB::query(
            'UPDATE categories SET name=?,description=?,officer_user_id=?,is_active=? WHERE id=?',
            [$name,$desc,$officer,$active,$catId]
        );
        flash('success','Категорията е обновена.');
    }

    if ($action === 'delete') {
        $catId = (int)$_POST['cat_id'];
        DB::query('UPDATE documents SET category_id=NULL WHERE category_id=?', [$catId]);
        DB::query('DELETE FROM categories WHERE id=?', [$catId]);
        flash('success','Категорията е изтрита.');
    }

    redirect('public/admin/categories.php');
}

$cats  = DB::all('SELECT c.*, u.full_name FROM categories c LEFT JOIN users u ON u.id=c.officer_user_id ORDER BY c.name');
$users = DB::all("SELECT * FROM users WHERE is_active=1 AND role='officer' ORDER BY full_name");

layoutHead('Категории');
layoutNav('admin');
?>
<div class="container" style="max-width:900px">
  <?php layoutFlash(); ?>
  <div class="page-header">
    <h1>Категории</h1>
    <a href="<?= url('public/admin/index.php') ?>" class="btn btn-outline btn-sm">Начало</a>
  </div>

  <!-- Add category -->
  <div class="card mb-3">
    <div class="card-header">Нова категория</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="create">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:.75rem;align-items:end">
          <div class="form-group" style="margin:0">
            <label class="required">Наименование</label>
            <input type="text" name="name" class="form-control" required maxlength="100">
          </div>
          <div class="form-group" style="margin:0">
            <label>Описание</label>
            <input type="text" name="desc" class="form-control" maxlength="255">
          </div>
          <div class="form-group" style="margin:0">
            <label>Отговорник</label>
            <select name="officer_user_id" class="form-control">
              <option value="">— Без отговорник —</option>
              <?php foreach ($users as $u): ?>
              <option value="<?= $u['id'] ?>"><?= h($u['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button class="btn btn-primary btn-sm" style="margin-bottom:.15rem">Добави</button>
        </div>
      </form>
    </div>
  </div>

  <!-- List -->
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Категория</th><th>Описание</th><th>Отговорник</th><th>Активна</th><th>Документи</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($cats as $cat): ?>
          <?php $cnt = DB::one('SELECT COUNT(*) c FROM documents WHERE category_id=? AND status!="archived"', [$cat['id']])['c']; ?>
          <tr>
            <td class="fw-bold"><?= h($cat['name']) ?></td>
            <td class="text-sm text-gray"><?= h(mb_strimwidth($cat['description']??'',0,40,'…')) ?></td>
            <td class="text-sm"><?= h($cat['full_name'] ?? '—') ?></td>
            <td><?= $cat['is_active'] ? '<span class="badge badge-success">Да</span>' : '<span class="badge badge-secondary">Не</span>' ?></td>
            <td><?= $cnt ?></td>
            <td>
              <!-- Inline edit via small form modal-like -->
              <button onclick="toggleEdit(<?= $cat['id'] ?>)" class="btn btn-outline btn-sm">Редактирай</button>
              <form method="post" style="display:inline"
                    onsubmit="return confirm('Изтрий категорията? Документите ще загубят категорията.')">
                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
                <button class="btn btn-danger btn-sm">Изтрий</button>
              </form>
            </td>
          </tr>
          <!-- Edit row -->
          <tr id="edit-<?= $cat['id'] ?>" style="display:none;background:#f8faff">
            <td colspan="6">
              <form method="post" style="padding:.5rem 0">
                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto auto;gap:.5rem;align-items:end">
                  <div>
                    <label class="text-sm">Наименование</label>
                    <input type="text" name="name" class="form-control" value="<?= h($cat['name']) ?>" required>
                  </div>
                  <div>
                    <label class="text-sm">Описание</label>
                    <input type="text" name="desc" class="form-control" value="<?= h($cat['description']??'') ?>">
                  </div>
                  <div>
                    <label class="text-sm">Отговорник</label>
                    <select name="officer_user_id" class="form-control">
                      <option value="">— —</option>
                      <?php foreach ($users as $u): ?>
                      <option value="<?= $u['id'] ?>" <?= $cat['officer_user_id']==$u['id']?'selected':'' ?>><?= h($u['full_name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div style="margin-top:1.4rem">
                    <label class="d-flex align-center gap-1">
                      <input type="checkbox" name="is_active" <?= $cat['is_active']?'checked':'' ?>> Активна
                    </label>
                  </div>
                  <div style="margin-bottom:.15rem">
                    <button class="btn btn-primary btn-sm">Запази</button>
                  </div>
                </div>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function toggleEdit(id) {
  const row = document.getElementById('edit-' + id);
  row.style.display = row.style.display === 'none' ? '' : 'none';
}
</script>
<?php layoutFoot(); ?>
