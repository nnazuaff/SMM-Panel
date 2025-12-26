<?php
/**
 * HTTP Cron Job untuk Auto Update Status Transaksi
 * Akses melalui URL dengan cron key untuk keamanan
 * URL: https://yourdomain.com/cron/http_cron.php?key=AcisPedia2024
 */

// Security check
if (!isset($_GET['key']) || $_GET['key'] !== 'AcisPedia2024') {
    http_response_code(403);
    die('Access denied');
}

// Set time limit
set_time_limit(300); // 5 minutes max

// Include configuration
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/MedanpediaAPI.php';

// Log function
function logMessage($message) {
    $logFile = __DIR__ . '/status_update.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] HTTP CRON: $message\n", FILE_APPEND | LOCK_EX);
}

// Output function for HTTP
function outputMessage($message) {
    echo "<p>" . htmlspecialchars($message) . "</p>\n";
    flush();
}

// Start output
echo "<!DOCTYPE html><html><head><title>Cron Job Status Update</title></head><body>";
echo "<h2>AcisPedia - Status Update Cron Job</h2>";
echo "<p>Started at: " . date('Y-m-d H:i:s') . "</p>";

try {
    logMessage("Starting HTTP cron status update process...");
    outputMessage("Starting status update process...");
    
    // Get database connection
    $db = getDBConnection();
    if (!$db) {
        throw new Exception('Database connection failed: ' . implode(', ', db_last_errors()));
    }
    
    outputMessage("Database connected successfully");
    
    // Initialize API
    $api = new MedanpediaAPI();
    outputMessage("API initialized");
    
    // Get orders that need status check
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
    
    $totalOrders = count($orders);
    outputMessage("Found $totalOrders orders to check");
    
    $updateCount = 0;
    $errorCount = 0;
    
    foreach ($orders as $order) {
        try {
            logMessage("Checking order ID: {$order['id']}, Medanpedia ID: {$order['medanpedia_order_id']}");
            outputMessage("Checking order #{$order['id']} (Medanpedia ID: {$order['medanpedia_order_id']})");
            
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
                    outputMessage("✅ Status updated: $currentStatus → $newStatus");
                } else {
                    outputMessage("ℹ️ Status unchanged: $currentStatus");
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
                outputMessage("❌ Error: $errorMsg");
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
            outputMessage("❌ Error processing order #{$order['id']}: " . $e->getMessage());
            $errorCount++;
        }
    }
    
    $summary = "Status update completed. Updated: $updateCount, Errors: $errorCount, Total checked: $totalOrders";
    logMessage($summary);
    outputMessage("📊 " . $summary);
    
    // Clean old log entries (keep last 100 lines)
    $logFile = __DIR__ . '/status_update.log';
    if (file_exists($logFile)) {
        $lines = file($logFile);
        if (count($lines) > 100) {
            $lines = array_slice($lines, -100);
            file_put_contents($logFile, implode('', $lines));
        }
    }
    
    outputMessage("✅ Cron job completed successfully at " . date('Y-m-d H:i:s'));
    
} catch (Exception $e) {
    $errorMsg = "Critical error in HTTP cron job: " . $e->getMessage();
    logMessage($errorMsg);
    outputMessage("❌ " . $errorMsg);
    error_log("AcisPedia HTTP Cron Error: " . $e->getMessage());
}

echo "</body></html>";
?>
