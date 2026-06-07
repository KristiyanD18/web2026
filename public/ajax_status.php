<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Method not allowed']);
    exit;
}

verifyCsrf();

$docId  = (int)($_POST['doc_id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$allowed = ['pending','in_progress','completed','paused','archived'];

if (!$docId || !in_array($status, $allowed, true)) {
    echo json_encode(['ok' => false, 'msg' => 'Невалидни данни.']);
    exit;
}

$doc = DB::one('SELECT id, status FROM documents WHERE id=?', [$docId]);
if (!$doc) {
    echo json_encode(['ok' => false, 'msg' => 'Документът не е намерен.']);
    exit;
}

// Officers can only change docs in their category
if (!Auth::isAdmin()) {
    $userId = Auth::id();
    $catDoc = DB::one(
        'SELECT d.id FROM documents d
         JOIN categories c ON c.id = d.category_id
         WHERE d.id=? AND c.officer_user_id=?',
        [$docId, $userId]
    );
    if (!$catDoc) {
        echo json_encode(['ok' => false, 'msg' => 'Нямате права.']);
        exit;
    }
}

DB::query('UPDATE documents SET status=? WHERE id=?', [$status, $docId]);
DB::query(
    'INSERT INTO document_history (document_id, old_status, new_status, changed_by_id, changed_by_name)
     VALUES (?,?,?,?,?)',
    [$docId, $doc['status'], $status, Auth::id(), Auth::fullName()]
);

logAccess($docId, 'view');
echo json_encode(['ok' => true]);
