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
$amount = (int)($_POST['amount'] ?? 0);
$amountWithCode = (int)($_POST['amount_with_code'] ?? 0);
$uniqueCode = (int)($_POST['unique_code'] ?? 0);

// Validation
if ($amount < 1000) {
    echo json_encode(['success' => false, 'message' => 'Nominal minimal Rp 1.000']);
    exit;
}

if ($amount > 200000) {
    echo json_encode(['success' => false, 'message' => 'Nominal maksimal Rp 200.000']);
    exit;
}

// Check if file is uploaded
if (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Bukti transfer diperlukan']);
    exit;
}

$file = $_FILES['proof'];
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
$maxSize = 5 * 1024 * 1024; // 5MB

// Validate file type
if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Format file tidak valid. Gunakan JPG, PNG, atau JPEG']);
    exit;
}

// Validate file size
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'Ukuran file maksimal 5MB']);
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

    // Create upload directory if not exists
    $uploadDir = __DIR__ . '/../storage/uploads/deposit_proofs/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'qris_' . $user['id'] . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode(['success' => false, 'message' => 'Gagal mengupload file']);
        exit;
    }
    
    // Insert deposit record
    $stmt = $pdo->prepare("
        INSERT INTO deposits (user_id, type, amount, amount_with_code, unique_code, proof_image, status, notes)
        VALUES (?, 'qris', ?, ?, ?, ?, 'pending', 'Menunggu verifikasi pembayaran QRIS')
    ");
    $stmt->execute([$user['id'], $amount, $amountWithCode, $uniqueCode, $filename]);
    
    $depositId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Deposit berhasil dikirim untuk verifikasi',
        'deposit_id' => $depositId,
        'details' => [
            'amount' => $amount,
            'amount_with_code' => $amountWithCode,
            'unique_code' => $uniqueCode,
            'formatted_amount' => 'Rp ' . number_format($amount, 0, ',', '.'),
            'formatted_total' => 'Rp ' . number_format($amountWithCode, 0, ',', '.'),
            'status' => 'pending'
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Deposit QRIS error: ' . $e->getMessage());
    
    // Delete uploaded file if database insert failed
    if (isset($filepath) && file_exists($filepath)) {
        unlink($filepath);
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat memproses deposit'
    ]);
}
