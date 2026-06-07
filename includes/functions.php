<?php
require_once __DIR__ . '/db.php';

// ─── Incoming number ──────────────────────────────────────────────────────────

function generateIncomingNumber(): string {
    $pdo = DB::get();
    $pdo->beginTransaction();
    try {
        $row = DB::one("SELECT setting_value FROM settings WHERE setting_key='doc_counter' FOR UPDATE");
        $counter = (int)($row['setting_value'] ?? 0) + 1;
        $year    = (int) DB::setting('doc_year', date('Y'));

        if ((int)date('Y') > $year) {
            $counter = 1;
            DB::setSetting('doc_year', date('Y'));
        }

        DB::setSetting('doc_counter', $counter);
        $pdo->commit();

        return DOC_PREFIX . '-' . date('Y') . '-' . str_pad($counter, 5, '0', STR_PAD_LEFT);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ─── Access code ──────────────────────────────────────────────────────────────

function generateAccessCode(): string {
    return strtoupper(bin2hex(random_bytes(4)));
}

// ─── File upload ──────────────────────────────────────────────────────────────

function handleDocumentUpload(array $file): array {
    $allowedMime  = ['application/pdf', 'application/zip', 'application/x-zip-compressed'];
    $allowedExt   = ['pdf', 'zip'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Грешка при качване на файла: код ' . $file['error']);
    }
    if ($file['size'] > UPLOAD_MAX) {
        throw new RuntimeException('Файлът надвишава максималния размер от ' . formatBytes(UPLOAD_MAX));
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('Позволени са само PDF и ZIP файлове.');
    }

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, $allowedMime, true)) {
        throw new RuntimeException('Невалиден тип файл.');
    }

    $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
    $destPath   = UPLOAD_PATH . '/' . $storedName;

    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new RuntimeException('Не може да се запише файлът на диска.');
    }

    return [
        'original_name' => basename($file['name']),
        'stored_name'   => $storedName,
        'file_type'     => $ext,
        'file_size'     => $file['size'],
    ];
}

// ─── Status labels ────────────────────────────────────────────────────────────

function statusLabel(string $status): string {
    return match($status) {
        'pending'     => 'Чакащ',
        'in_progress' => 'В обработка',
        'completed'   => 'Обработен',
        'paused'      => 'Паузиран',
        'archived'    => 'Архивиран',
        default       => $status,
    };
}

function statusClass(string $status): string {
    return match($status) {
        'pending'     => 'badge-warning',
        'in_progress' => 'badge-info',
        'completed'   => 'badge-success',
        'paused'      => 'badge-secondary',
        'archived'    => 'badge-dark',
        default       => 'badge-light',
    };
}

function priorityLabel(string $p): string {
    return $p === 'high' ? 'Приоритетен' : 'Нормален';
}

// ─── Statistics helper ────────────────────────────────────────────────────────

function logAccess(int $docId, string $type, bool $success = true): int {
    return DB::insert(
        'INSERT INTO access_log (document_id, user_id, ip_address, access_type, session_token, success)
         VALUES (?, ?, ?, ?, ?, ?)',
        [
            $docId,
            Auth::id() ?: null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $type,
            session_id(),
            (int)$success,
        ]
    );
}

function closeAccessLog(int $logId): void {
    DB::query(
        'UPDATE access_log
         SET closed_at = NOW(),
             duration_seconds = TIMESTAMPDIFF(SECOND, accessed_at, NOW())
         WHERE id = ?',
        [$logId]
    );
}

// ─── Utilities ────────────────────────────────────────────────────────────────

function formatBytes(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

function h(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf(), $token)) {
        http_response_code(403);
        die('Невалиден CSRF токен.');
    }
}

function redirect(string $path): never {
    header('Location: ' . url($path));
    exit;
}

function flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}
