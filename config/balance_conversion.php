<?php
/**
 * Balance Conversion Configuration & Database Setup
 * Handles conversion requests from AcisPayment to SMM Panel
 */

require_once __DIR__ . '/database.php';

// Create balance conversion requests table
function setupBalanceConversionTable() {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }
    
    try {
        // Table for storing conversion requests
        $pdo->exec("CREATE TABLE IF NOT EXISTS balance_conversions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            acispayment_username VARCHAR(100) NOT NULL,
            phone_number VARCHAR(20) NOT NULL,
            email VARCHAR(255) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            conversion_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
            total_transfer DECIMAL(10,2) NOT NULL DEFAULT 0,
            final_amount DECIMAL(10,2) NOT NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            admin_notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            processed_by INT NULL,
            processed_at TIMESTAMP NULL,
            INDEX idx_user_status (user_id, status),
            INDEX idx_status_created (status, created_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        
        // Add columns if they don't exist (for existing tables)
        try {
            $pdo->exec("ALTER TABLE balance_conversions ADD COLUMN IF NOT EXISTS phone_number VARCHAR(20) NOT NULL AFTER acispayment_username");
            $pdo->exec("ALTER TABLE balance_conversions ADD COLUMN IF NOT EXISTS email VARCHAR(255) NOT NULL AFTER phone_number");
            $pdo->exec("ALTER TABLE balance_conversions ADD COLUMN IF NOT EXISTS conversion_fee DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER amount");
            $pdo->exec("ALTER TABLE balance_conversions ADD COLUMN IF NOT EXISTS total_transfer DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER conversion_fee");
            $pdo->exec("ALTER TABLE balance_conversions ADD COLUMN IF NOT EXISTS final_amount DECIMAL(10,2) NOT NULL AFTER total_transfer");
        } catch (PDOException $e) {
            // Columns might already exist, ignore
        }
        
        return true;
    } catch (PDOException $e) {
        error_log('Failed to create balance_conversions table: ' . $e->getMessage());
        return false;
    }
}

// Initialize table on first load
setupBalanceConversionTable();

/**
 * Submit a balance conversion request with fee calculation
 * @param int $userId User ID
 * @param string $acispaymentUsername Username in AcisPayment app
 * @param string $phoneNumber Phone number
 * @param string $email Email address
 * @param float $amount Amount to convert (yang masuk ke user)
 * @param float $conversionFee Fee charged (0.7% - ditanggung user)
 * @param float $finalAmount Final amount that enters SMM Panel (= amount)
 * @param float $totalTransfer Total amount user must transfer from AcisPayment (amount + fee)
 * @return array Result with success status and message
 */
function submitConversionRequest($userId, $acispaymentUsername, $phoneNumber, $email, $amount, $conversionFee = 0, $finalAmount = 0, $totalTransfer = 0) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    try {
        // Validate amount
        $amount = floatval($amount);
        if ($amount < 1000) {
            return ['success' => false, 'message' => 'Minimal konversi adalah Rp 1.000'];
        }
        
        if ($amount > 10000000) {
            return ['success' => false, 'message' => 'Maksimal konversi adalah Rp 10.000.000'];
        }
        
        // Validate username
        $acispaymentUsername = trim($acispaymentUsername);
        if (empty($acispaymentUsername)) {
            return ['success' => false, 'message' => 'Username AcisPayment harus diisi'];
        }
        
        // Validate phone
        $phoneNumber = trim($phoneNumber);
        if (empty($phoneNumber)) {
            return ['success' => false, 'message' => 'Nomor HP harus diisi'];
        }
        
        if (strlen($phoneNumber) < 10 || strlen($phoneNumber) > 20) {
            return ['success' => false, 'message' => 'Nomor HP harus antara 10-20 digit'];
        }
        
        if (!preg_match('/^[0-9]{10,20}$/', $phoneNumber)) {
            return ['success' => false, 'message' => 'Nomor HP hanya boleh berisi angka'];
        }
        
        // Validate email
        $email = trim($email);
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Email tidak valid'];
        }
        
        // Check for pending requests
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM balance_conversions WHERE user_id = ? AND status = 'pending'");
        $stmt->execute([$userId]);
        $pendingCount = $stmt->fetchColumn();
        
        if ($pendingCount > 0) {
            return ['success' => false, 'message' => 'Anda masih memiliki permintaan konversi yang menunggu persetujuan'];
        }
        
        // Insert conversion request
        $stmt = $pdo->prepare("INSERT INTO balance_conversions (user_id, acispayment_username, phone_number, email, amount, conversion_fee, total_transfer, final_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$userId, $acispaymentUsername, $phoneNumber, $email, $amount, $conversionFee, $totalTransfer, $finalAmount]);
        
        $requestId = $pdo->lastInsertId();
        
        // Also insert into deposits table for unified deposit history
        require_once __DIR__ . '/deposit.php';
        
        // Generate a unique code for tracking (700-800 range for conversion)
        $uniqueCode = ((int)$userId * 17 + time() % 101) + 700;
        
        // Set expiration time (7 days for conversion - longer than QRIS)
        $expiredAt = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        try {
            $stmt = $pdo->prepare("INSERT INTO deposits (user_id, amount, unique_code, final_amount, payment_method, status, expired_at) VALUES (?, ?, ?, ?, 'conversion', 'pending', ?)");
            $stmt->execute([$userId, $amount, $uniqueCode, $finalAmount, $expiredAt]);
        } catch (PDOException $e) {
            // Log but don't fail the conversion request
            error_log('Failed to insert conversion into deposits table: ' . $e->getMessage());
        }
        
        return [
            'success' => true,
            'message' => 'Permintaan konversi berhasil dikirim. Menunggu persetujuan admin.',
            'request_id' => $requestId
        ];
        
    } catch (PDOException $e) {
        error_log('Conversion request error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()];
    }
}

/**
 * Get user's conversion requests
 * @param int $userId User ID
 * @param int $limit Limit results
 * @return array Array of conversion requests
 */
function getUserConversionRequests($userId, $limit = 10) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return [];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                acispayment_username,
                amount,
                status,
                admin_notes,
                created_at,
                updated_at,
                processed_at
            FROM balance_conversions 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get conversion requests error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Approve a conversion request and update balance
 * Also updates the corresponding deposit record
 * @param int $conversionId Conversion request ID
 * @param int $processedBy Admin user ID who processed the request
 * @param string $adminNotes Optional admin notes
 * @return array Result with success status and message
 */
function approveConversionRequest($conversionId, $processedBy = null, $adminNotes = '') {
    $pdo = getDBConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    try {
        $pdo->beginTransaction();
        
        // Get conversion request details
        $stmt = $pdo->prepare("SELECT * FROM balance_conversions WHERE id = ? AND status = 'pending'");
        $stmt->execute([$conversionId]);
        $conversion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$conversion) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Permintaan konversi tidak ditemukan atau sudah diproses'];
        }
        
        // Update balance
        $stmt = $pdo->prepare("UPDATE user_balance SET balance = balance + ? WHERE user_id = ?");
        $stmt->execute([$conversion['final_amount'], $conversion['user_id']]);
        
        // Update conversion status
        $stmt = $pdo->prepare("UPDATE balance_conversions SET status = 'approved', admin_notes = ?, processed_by = ?, processed_at = NOW() WHERE id = ?");
        $stmt->execute([$adminNotes, $processedBy, $conversionId]);
        
        // Update corresponding deposit record
        $stmt = $pdo->prepare("UPDATE deposits SET status = 'success', admin_notes = ?, processed_by = ?, processed_at = NOW() WHERE user_id = ? AND payment_method = 'conversion' AND amount = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$adminNotes, $processedBy, $conversion['user_id'], $conversion['amount']]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Konversi berhasil disetujui. Saldo telah ditambahkan.'
        ];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Approve conversion error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Gagal menyetujui konversi: ' . $e->getMessage()];
    }
}

/**
 * Reject a conversion request
 * Also updates the corresponding deposit record
 * @param int $conversionId Conversion request ID
 * @param int $processedBy Admin user ID who processed the request
 * @param string $adminNotes Reason for rejection
 * @return array Result with success status and message
 */
function rejectConversionRequest($conversionId, $processedBy = null, $adminNotes = 'Ditolak oleh admin') {
    $pdo = getDBConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    try {
        $pdo->beginTransaction();
        
        // Get conversion request details
        $stmt = $pdo->prepare("SELECT * FROM balance_conversions WHERE id = ? AND status = 'pending'");
        $stmt->execute([$conversionId]);
        $conversion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$conversion) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Permintaan konversi tidak ditemukan atau sudah diproses'];
        }
        
        // Update conversion status
        $stmt = $pdo->prepare("UPDATE balance_conversions SET status = 'rejected', admin_notes = ?, processed_by = ?, processed_at = NOW() WHERE id = ?");
        $stmt->execute([$adminNotes, $processedBy, $conversionId]);
        
        // Update corresponding deposit record
        $stmt = $pdo->prepare("UPDATE deposits SET status = 'failed', admin_notes = ?, processed_by = ?, processed_at = NOW() WHERE user_id = ? AND payment_method = 'conversion' AND amount = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$adminNotes, $processedBy, $conversion['user_id'], $conversion['amount']]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Konversi berhasil ditolak.'
        ];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Reject conversion error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Gagal menolak konversi: ' . $e->getMessage()];
    }
}
