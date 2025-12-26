<?php
/**
 * Balance Log Helper Functions
 * Fungsi untuk mencatat semua transaksi saldo user
 */

require_once __DIR__ . '/database.php';

/**
 * Log transaksi saldo
 * 
 * @param int $userId ID user
 * @param string $type Tipe transaksi: deposit, order, refund, convert, admin_adjustment
 * @param float $amount Jumlah transaksi (positif untuk masuk, negatif untuk keluar)
 * @param float $balanceBefore Saldo sebelum transaksi
 * @param float $balanceAfter Saldo setelah transaksi
 * @param string $description Deskripsi transaksi
 * @param int|null $orderId ID order terkait (opsional)
 * @return bool Success status
 */
function log_balance_transaction($userId, $type, $amount, $balanceBefore, $balanceAfter, $description, $orderId = null) {
    try {
        $pdo = getDBConnection();
        if (!$pdo) {
            error_log('[Balance Log] Database connection failed');
            return false;
        }
        
        // Create table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS balance_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            type ENUM('deposit', 'order', 'refund', 'convert', 'admin_adjustment') NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            balance_before DECIMAL(10,2) NOT NULL,
            balance_after DECIMAL(10,2) NOT NULL,
            description TEXT,
            order_id INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_created_at (created_at),
            INDEX idx_type (type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Insert log
        $stmt = $pdo->prepare("
            INSERT INTO balance_logs (user_id, type, amount, balance_before, balance_after, description, order_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $userId,
            $type,
            $amount,
            $balanceBefore,
            $balanceAfter,
            $description,
            $orderId
        ]);
        
        if (!$result) {
            error_log('[Balance Log] Failed to insert log for user ' . $userId);
            return false;
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log('[Balance Log] Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update saldo user dan catat log
 * 
 * @param int $userId ID user
 * @param float $amount Jumlah perubahan (positif untuk tambah, negatif untuk kurang)
 * @param string $type Tipe transaksi
 * @param string $description Deskripsi transaksi
 * @param int|null $orderId ID order terkait
 * @return array ['success' => bool, 'balance_before' => float, 'balance_after' => float]
 */
function update_balance_with_log($userId, $amount, $type, $description, $orderId = null) {
    try {
        $pdo = getDBConnection();
        if (!$pdo) {
            return ['success' => false, 'message' => 'Database connection failed'];
        }
        
        // Ensure user_balance row exists
        $pdo->prepare('INSERT IGNORE INTO user_balance (user_id, balance, total_spent) VALUES (?,0,0)')->execute([$userId]);
        
        // Get current balance
        $stmt = $pdo->prepare('SELECT balance FROM user_balance WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $balanceBefore = (float)($stmt->fetchColumn() ?? 0);
        
        // Calculate new balance
        $balanceAfter = $balanceBefore + $amount;
        
        // Prevent negative balance
        if ($balanceAfter < 0) {
            return [
                'success' => false, 
                'message' => 'Saldo tidak cukup',
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceBefore,
                'shortfall' => abs($balanceAfter)
            ];
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Update balance
            $stmt = $pdo->prepare('UPDATE user_balance SET balance = ? WHERE user_id = ?');
            $stmt->execute([$balanceAfter, $userId]);
            
            // Update total_spent if it's an order (negative amount)
            if ($type === 'order' && $amount < 0) {
                $stmt = $pdo->prepare('UPDATE user_balance SET total_spent = total_spent + ? WHERE user_id = ?');
                $stmt->execute([abs($amount), $userId]);
            }
            
            // Log transaction
            log_balance_transaction($userId, $type, $amount, $balanceBefore, $balanceAfter, $description, $orderId);
            
            $pdo->commit();
            
            return [
                'success' => true,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter
            ];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('[Update Balance] Transaction failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Transaction failed'];
        }
        
    } catch (Exception $e) {
        error_log('[Update Balance] Error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'System error'];
    }
}

/**
 * Get balance logs for user
 * 
 * @param int $userId ID user
 * @param int $limit Jumlah record maksimal
 * @param int $offset Offset untuk pagination
 * @param string|null $type Filter by type (optional)
 * @return array Array of balance logs
 */
function get_balance_logs($userId, $limit = 20, $offset = 0, $type = null) {
    try {
        $pdo = getDBConnection();
        if (!$pdo) {
            return [];
        }
        
        $whereClause = 'user_id = ?';
        $params = [$userId];
        
        if ($type && in_array($type, ['deposit', 'order', 'refund', 'convert', 'admin_adjustment'])) {
            $whereClause .= ' AND type = ?';
            $params[] = $type;
        }
        
        $stmt = $pdo->prepare("
            SELECT * FROM balance_logs 
            WHERE {$whereClause}
            ORDER BY created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log('[Get Balance Logs] Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get balance summary for user
 * 
 * @param int $userId ID user
 * @return array ['total_deposit' => float, 'total_spent' => float, 'total_refund' => float]
 */
function get_balance_summary($userId) {
    try {
        $pdo = getDBConnection();
        if (!$pdo) {
            return ['total_deposit' => 0, 'total_spent' => 0, 'total_refund' => 0];
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN type IN ('deposit', 'convert') AND amount > 0 THEN amount ELSE 0 END) as total_deposit,
                SUM(CASE WHEN type = 'order' AND amount < 0 THEN ABS(amount) ELSE 0 END) as total_spent,
                SUM(CASE WHEN type = 'refund' AND amount > 0 THEN amount ELSE 0 END) as total_refund
            FROM balance_logs 
            WHERE user_id = ?
        ");
        
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_deposit' => (float)($result['total_deposit'] ?? 0),
            'total_spent' => (float)($result['total_spent'] ?? 0),
            'total_refund' => (float)($result['total_refund'] ?? 0)
        ];
        
    } catch (Exception $e) {
        error_log('[Get Balance Summary] Error: ' . $e->getMessage());
        return ['total_deposit' => 0, 'total_spent' => 0, 'total_refund' => 0];
    }
}
