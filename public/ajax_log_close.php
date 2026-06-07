<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$logId = (int)($_GET['log_id'] ?? 0);
if ($logId > 0) {
    closeAccessLog($logId);
}
http_response_code(204);
