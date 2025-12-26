<?php
/**
 * Deposit Configuration & Database Setup
 * Handles QRIS deposit tracking with unique codes
 */

// Set timezone to WIB (GMT+7)
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/database.php';

// Create deposits table
function setupDepositsTable() {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }
    
    try {
        // Table for storing QRIS deposit transactions
        $pdo->exec("CREATE TABLE IF NOT EXISTS deposits (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            unique_code INT NOT NULL,
            final_amount DECIMAL(10,2) NOT NULL,
            payment_method ENUM('qris', 'conversion') DEFAULT 'qris',
            payment_proof VARCHAR(255) NULL,
            status ENUM('pending', 'success', 'failed', 'expired') DEFAULT 'pending',
            admin_notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            processed_by INT NULL,
            processed_at TIMESTAMP NULL,
            expired_at TIMESTAMP NULL,
            INDEX idx_user_status (user_id, status),
            INDEX idx_final_amount (final_amount, status),
            INDEX idx_created (created_at),
            UNIQUE KEY unique_amount_active (final_amount, status, created_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        
        // Add payment_method column if it doesn't exist (for existing tables)
        try {
            $pdo->exec("ALTER TABLE deposits ADD COLUMN payment_method ENUM('qris', 'conversion') DEFAULT 'qris' AFTER final_amount");
        } catch (PDOException $e) {
            // Column already exists, ignore
        }
        
        return true;
    } catch (PDOException $e) {
        error_log('Failed to create deposits table: ' . $e->getMessage());
        return false;
    }
}

// Initialize table on first load
setupDepositsTable();

/**
 * Submit a deposit request with unique code
 * @param int $userId User ID
 * @param float $amount Base amount
 * @param int $uniqueCode Unique code (100-200)
 * @param float $finalAmount Total amount (base + unique code)
 * @param string $paymentMethod Payment method (qris or conversion)
 * @return array Result with success status and message
 */
function submitDepositRequest($userId, $amount, $uniqueCode, $finalAmount, $paymentMethod = 'qris') {
    $pdo = getDBConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    try {
        // Validate amount
        $amount = floatval($amount);
        if ($amount < 1000) {
            return ['success' => false, 'message' => 'Minimal deposit adalah Rp 1.000'];
        }
        
        if ($amount > 200000) {
            return ['success' => false, 'message' => 'Maksimal deposit adalah Rp 200.000'];
        }
        
        // Validate payment method
        if (!in_array($paymentMethod, ['qris', 'conversion'])) {
            $paymentMethod = 'qris';
        }
        
        // Check if user has pending deposit within last 30 minutes
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM deposits WHERE user_id = ? AND status = 'pending' AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
        $stmt->execute([$userId]);
        $recentPending = $stmt->fetchColumn();
        
        if ($recentPending > 0) {
            return ['success' => false, 'message' => 'Anda masih memiliki deposit pending. Harap selesaikan deposit sebelumnya atau tunggu 30 menit.'];
        }
        
        // Check for duplicate final_amount in last 24 hours (antisipasi duplikasi nominal)
        $stmt = $pdo->prepare("SELECT id, user_id FROM deposits WHERE final_amount = ? AND status IN ('pending', 'success') AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt->execute([$finalAmount]);
        $duplicate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($duplicate) {
            // If duplicate exists, generate new unique code
            return [
                'success' => false, 
                'message' => 'Nominal dengan kode unik ini sudah ada dalam sistem. Silakan klik Lanjutkan lagi untuk mendapat kode unik baru.',
                'duplicate' => true
            ];
        }
        
        // Set expiration time (24 hours from now)
        $expiredAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Insert deposit request
        $stmt = $pdo->prepare("INSERT INTO deposits (user_id, amount, unique_code, final_amount, payment_method, status, expired_at) VALUES (?, ?, ?, ?, ?, 'pending', ?)");
        $stmt->execute([$userId, $amount, $uniqueCode, $finalAmount, $paymentMethod, $expiredAt]);
        
        $depositId = $pdo->lastInsertId();
        
        return [
            'success' => true,
            'message' => 'Deposit berhasil dibuat. Silakan transfer sesuai nominal.',
            'deposit_id' => $depositId,
            'expired_at' => $expiredAt
        ];
        
    } catch (PDOException $e) {
        error_log('Deposit request error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Gagal membuat deposit: ' . $e->getMessage()];
    }
}

/**
 * Update deposit with payment proof
 * @param int $depositId Deposit ID
 * @param string $proofFilename Uploaded proof filename
 * @return array Result
 */
function updateDepositProof($depositId, $proofFilename) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE deposits SET payment_proof = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$proofFilename, $depositId]);
        
        return ['success' => true, 'message' => 'Bukti transfer berhasil diupload'];
    } catch (PDOException $e) {
        error_log('Update proof error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Gagal update bukti transfer'];
    }
}

/**
 * Get user's deposit history
 * @param int $userId User ID
 * @param int $limit Number of records to retrieve
 * @return array List of deposits
 */
function getUserDeposits($userId, $limit = 10) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return [];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM deposits WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get deposits error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Auto-expire old pending deposits (called by cron)
 */
function expireOldDeposits() {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE deposits SET status = 'expired', updated_at = NOW() WHERE status = 'pending' AND expired_at < NOW()");
        $stmt->execute();
        
        return ['success' => true, 'expired_count' => $stmt->rowCount()];
    } catch (PDOException $e) {
        error_log('Expire deposits error: ' . $e->getMessage());
        return ['success' => false];
    }
}

/**
 * Approve deposit and update user balance
 * @param int $depositId Deposit ID
 * @param int $processedBy Admin user ID (optional)
 * @param string $adminNotes Admin notes (optional)
 * @return array Result with success status and message
 */
function approveDeposit($depositId, $processedBy = null, $adminNotes = '') {
    $pdo = getDBConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get deposit info
        $stmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ? AND status = 'pending' FOR UPDATE");
        $stmt->execute([$depositId]);
        $deposit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$deposit) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Deposit tidak ditemukan atau sudah diproses'];
        }
        
        // Check if expired
        if (strtotime($deposit['expired_at']) < time()) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Deposit sudah kadaluarsa'];
        }
        
        // Update deposit status
        $stmt = $pdo->prepare("UPDATE deposits SET status = 'success', processed_by = ?, processed_at = NOW(), admin_notes = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$processedBy, $adminNotes, $depositId]);
        
        // Update user balance
        $amount = floatval($deposit['amount']);
        $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->execute([$amount, $deposit['user_id']]);
        
        // Update user_balance table if exists
        try {
            $stmt = $pdo->prepare("INSERT INTO user_balance (user_id, balance, total_spent) VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE balance = balance + ?");
            $stmt->execute([$deposit['user_id'], $amount, $amount]);
        } catch (PDOException $e) {
            // Table might not exist, ignore
        }
        
        // Commit transaction
        $pdo->commit();
        
        return [
            'success' => true, 
            'message' => 'Deposit berhasil disetujui dan saldo telah ditambahkan',
            'amount_added' => $amount
        ];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Approve deposit error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Gagal approve deposit: ' . $e->getMessage()];
    }
}

/**
 * Reject deposit
 * @param int $depositId Deposit ID
 * @param int $processedBy Admin user ID (optional)
 * @param string $adminNotes Rejection reason
 * @return array Result
 */
function rejectDeposit($depositId, $processedBy = null, $adminNotes = '') {
    $pdo = getDBConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE deposits SET status = 'failed', processed_by = ?, processed_at = NOW(), admin_notes = ?, updated_at = NOW() WHERE id = ? AND status = 'pending'");
        $stmt->execute([$processedBy, $adminNotes, $depositId]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Deposit ditolak'];
        } else {
            return ['success' => false, 'message' => 'Deposit tidak ditemukan atau sudah diproses'];
        }
    } catch (PDOException $e) {
        error_log('Reject deposit error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Gagal reject deposit'];
    }
}

/**
 * Get deposit by ID
 * @param int $depositId Deposit ID
 * @return array|null Deposit data or null
 */
function getDepositById($depositId) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ?");
        $stmt->execute([$depositId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get deposit error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Auto-approve deposit based on payment proof (optional - untuk otomatis)
 * This can be called after successful payment verification
 * @param int $depositId Deposit ID
 * @return array Result
 */
function autoApproveDeposit($depositId) {
    return approveDeposit($depositId, null, 'Auto-approved by system');
}
