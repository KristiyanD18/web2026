<?php
// layout helpers — include this after setting $pageTitle and $activePage
function layoutHead(string $title = ''): void {
    $appName = defined('APP_NAME') ? APP_NAME : 'DocReg';
    $full    = $title ? "$title — $appName" : $appName;
    $base    = url('public/assets');
    echo '<!DOCTYPE html><html lang="bg"><head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<meta name="csrf-token" content="' . h(csrf()) . '">';
    echo '<title>' . h($full) . '</title>';
    echo '<link rel="stylesheet" href="' . $base . '/css/style.css">';
    echo '</head><body>';
}

function layoutNav(string $active = ''): void {
    require_once __DIR__ . '/auth.php';
    $isLoggedIn = Auth::check();
    $isAdmin    = Auth::isAdmin();
    $isOfficer  = Auth::isOfficer();
    ?>
    <nav class="navbar">
        <a href="<?= url() ?>" class="brand">Doc<span>Reg</span></a>
        <div class="nav-links">
            <?php if (!$isAdmin): ?>
            <a href="<?= url('public/submit.php') ?>" class="<?= $active === 'submit' ? 'active' : '' ?>">Входиране</a>
            <a href="<?= url('public/track.php') ?>" class="<?= $active === 'track' ? 'active' : '' ?>">Проследяване</a>
            <?php endif; ?>
            <?php if ($isOfficer): ?>
            <a href="<?= url('public/officer/index.php') ?>" class="<?= $active === 'officer' ? 'active' : '' ?>">Моите Документи</a>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
            <a href="<?= url('public/admin/index.php') ?>" class="<?= $active === 'admin' ? 'active' : '' ?>">Администрация</a>
            <a href="<?= url('public/admin/users.php') ?>" class="<?= $active === 'users' ? 'active' : '' ?>">Потребители</a>
            <?php endif; ?>
        </div>
        <div class="nav-user">
            <?php if ($isLoggedIn): ?>
                <?= h(Auth::fullName()) ?> &nbsp;
                <a href="<?= url('public/logout.php') ?>">Изход</a>
            <?php else: ?>
                <a href="<?= url('public/login.php') ?>" style="color:#93c5fd">Вход</a>
            <?php endif; ?>
        </div>
    </nav>
    <?php
}

function layoutFlash(): void {
    require_once __DIR__ . '/functions.php';
    $f = getFlash();
    if (!$f) return;
    $type = match($f['type']) {
        'success' => 'alert-success',
        'danger'  => 'alert-danger',
        'warning' => 'alert-warning',
        default   => 'alert-info',
    };
    echo '<div class="alert ' . $type . '" data-dismiss>
            <span>' . h($f['msg']) . '</span>
            <button data-close style="margin-left:auto;background:none;border:none;cursor:pointer;font-size:1.1rem">✕</button>
          </div>';
}

function layoutFoot(): void {
    $base = url('public/assets');
    echo '<script src="' . $base . '/js/main.js"></script>';
    echo '</body></html>';
}
