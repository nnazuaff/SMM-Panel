<?php
/**
 * Cron Job untuk Auto Update Status Transaksi
 * Jalankan setiap 5-10 menit untuk mengecek status terbaru dari Medanpedia API
 */

// Prevent direct browser access
if (php_sapi_name() !== 'cli' && !isset($_GET['cron_key']) || (isset($_GET['cron_key']) && $_GET['cron_key'] !== 'AcisPedia2024')) {
    die('Access denied');
}

// Include configuration
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/MedanpediaAPI.php';

// Log function
function logMessage($message) {
    $logFile = __DIR__ . '/status_update.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

try {
    logMessage("Starting status update process...");
    
    // Get database connection
    $db = getDBConnection();
    if (!$db) {
        throw new Exception('Database connection failed: ' . implode(', ', db_last_errors()));
    }
    
    // Initialize API
    $api = new MedanpediaAPI();
    
    // Get orders that need status check
    // Only check orders that are not in final status (Success, Canceled, Partial, Error)
    $query = "SELECT id, medanpedia_order_id, status, last_status_check, status_check_attempts 
              FROM orders 
              WHERE medanpedia_order_id IS NOT NULL 
              AND medanpedia_order_id != '' 
              AND status NOT IN ('Success', 'Canceled', 'Partial', 'Error')
              AND (last_status_check IS NULL OR last_status_check < DATE_SUB(NOW(), INTERVAL 5 MINUTE))
              ORDER BY created_at DESC 
              LIMIT 20";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updateCount = 0;
    $errorCount = 0;
    
    foreach ($orders as $order) {
        try {
            logMessage("Checking order ID: {$order['id']}, Medanpedia ID: {$order['medanpedia_order_id']}");
            
            // Get status from Medanpedia API
            $response = $api->getOrderStatus($order['medanpedia_order_id']);
            
            if ($response && isset($response['status']) && $response['status'] === true && isset($response['data']['status'])) {
                $newStatus = $response['data']['status'];
                $currentStatus = $order['status'];
                
                // Update last check time and attempts
                $updateQuery = "UPDATE orders SET 
                               last_status_check = NOW(), 
                               status_check_attempts = COALESCE(status_check_attempts, 0) + 1";
                
                // If status changed, update it
                if ($newStatus !== $currentStatus) {
                    $updateQuery .= ", status = :status";
                    
                    // If it's a final status, mark it
                    if (in_array($newStatus, ['Success', 'Canceled', 'Partial', 'Error'])) {
                        $updateQuery .= ", is_final_status = 1";
                    }
                    
                    logMessage("Status changed for order {$order['id']}: $currentStatus -> $newStatus");
                }
                
                $updateQuery .= " WHERE id = :id";
                
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindParam(':id', $order['id']);
                
                if ($newStatus !== $currentStatus) {
                    $updateStmt->bindParam(':status', $newStatus);
                }
                
                $updateStmt->execute();
                $updateCount++;
                
            } else {
                $errorMsg = isset($response['msg']) ? $response['msg'] : 'Invalid API response';
                logMessage("Failed to get status for order {$order['id']}: $errorMsg");
                $errorCount++;
                
                // Update attempts count even if failed
                $updateQuery = "UPDATE orders SET 
                               last_status_check = NOW(), 
                               status_check_attempts = COALESCE(status_check_attempts, 0) + 1 
                               WHERE id = :id";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindParam(':id', $order['id']);
                $updateStmt->execute();
            }
            
            // Small delay to avoid overwhelming the API
            usleep(500000); // 0.5 second delay
            
        } catch (Exception $e) {
            logMessage("Error processing order {$order['id']}: " . $e->getMessage());
            $errorCount++;
        }
    }
    
    logMessage("Status update completed. Updated: $updateCount, Errors: $errorCount, Total checked: " . count($orders));
    
    // Clean old log entries (keep last 100 lines)
    $logFile = __DIR__ . '/status_update.log';
    if (file_exists($logFile)) {
        $lines = file($logFile);
        if (count($lines) > 100) {
            $lines = array_slice($lines, -100);
            file_put_contents($logFile, implode('', $lines));
        }
    }
    
} catch (Exception $e) {
    logMessage("Critical error in cron job: " . $e->getMessage());
    error_log("AcisPedia Cron Error: " . $e->getMessage());
}
?>
