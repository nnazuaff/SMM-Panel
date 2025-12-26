<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

if (!auth_check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Refresh saldo terbaru dari DB agar realtime
auth_refresh_balance();
$user = auth_user();

echo json_encode([
    'success' => true,
    'balance' => (float)($user['balance'] ?? 0),
    'formatted' => 'Rp ' . number_format((float)($user['balance'] ?? 0), 0, ',', '.'),
]);
