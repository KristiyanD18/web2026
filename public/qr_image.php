<?php
// Serve QR code images safely (prevents direct path traversal to uploads/docs)
require_once __DIR__ . '/../config/config.php';

$file = basename($_GET['f'] ?? '');
if (!$file || !preg_match('/^[a-f0-9_]+\.png$/', $file)) {
    http_response_code(400);
    exit('Invalid request.');
}

$path = QR_PATH . '/' . $file;
if (!is_file($path)) {
    http_response_code(404);
    exit('Not found.');
}

header('Content-Type: image/png');
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=86400');
readfile($path);
