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
 * Submit a balance conversion request
 * @param int $userId User ID
 * @param string $acispaymentUsername Username in AcisPayment app
 * @param string $phoneNumber Phone number
 * @param string $email Email address
 * @param float $amount Amount to convert
 * @return array Result with success status and message
 */
function submitConversionRequest($userId, $acispaymentUsername, $phoneNumber, $email, $amount) {
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
        $stmt = $pdo->prepare("INSERT INTO balance_conversions (user_id, acispayment_username, phone_number, email, amount, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$userId, $acispaymentUsername, $phoneNumber, $email, $amount]);
        
        $requestId = $pdo->lastInsertId();
        
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
