<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!auth_check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$acispayId = trim($input['acispay_id'] ?? '');
$amount = (int)($input['amount'] ?? 0);

// Validation
if (empty($acispayId)) {
    echo json_encode(['success' => false, 'message' => 'ID AcisPay diperlukan']);
    exit;
}

if ($amount < 1000) {
    echo json_encode(['success' => false, 'message' => 'Nominal minimal Rp 1.000']);
    exit;
}

if ($amount > 10000000) {
    echo json_encode(['success' => false, 'message' => 'Nominal maksimal Rp 10.000.000']);
    exit;
}

$user = auth_user();
$pdo = getDBConnection();

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    // Create deposits table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS deposits (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        type ENUM('manual', 'convert', 'qris') NOT NULL DEFAULT 'manual',
        amount DECIMAL(10,2) NOT NULL,
        amount_with_code DECIMAL(10,2) DEFAULT NULL,
        unique_code INT DEFAULT NULL,
        acispay_id VARCHAR(100) DEFAULT NULL,
        proof_image VARCHAR(255) DEFAULT NULL,
        status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_status (status),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Insert deposit record
    $stmt = $pdo->prepare("
        INSERT INTO deposits (user_id, type, amount, acispay_id, status, notes)
        VALUES (?, 'convert', ?, ?, 'pending', 'Menunggu verifikasi konversi saldo dari AcisPay')
    ");
    $stmt->execute([$user['id'], $amount, $acispayId]);
    
    $depositId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Permintaan konversi saldo berhasil dikirim',
        'deposit_id' => $depositId,
        'details' => [
            'amount' => $amount,
            'formatted_amount' => 'Rp ' . number_format($amount, 0, ',', '.'),
            'acispay_id' => $acispayId,
            'status' => 'pending'
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Deposit convert error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat memproses permintaan'
    ]);
}
