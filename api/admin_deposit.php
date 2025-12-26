<?php
/**
 * Admin API for managing deposits
 * This endpoint allows admin to approve/reject deposits
 */

header('Content-Type: application/json');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include required files
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/deposit.php';
require_once __DIR__ . '/../config/database.php';

// Check authentication
if (!auth_check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get user info
$user = auth_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

// Check if user is admin (you can implement your own admin check)
// For now, we'll use a simple check - you can modify this
$isAdmin = false;
try {
    $pdo = getDBConnection();
    if ($pdo) {
        // Check if user has admin role (assumes you have a role column)
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $userRole = $stmt->fetchColumn();
        $isAdmin = ($userRole === 'admin' || $userRole === 'superadmin');
    }
} catch (Exception $e) {
    // If role column doesn't exist, check by email/username
    // You can add specific admin emails here
    $adminEmails = ['admin@acispedia.com', 'acispedia@gmail.com'];
    $isAdmin = in_array($user['email'] ?? '', $adminEmails);
}

if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin only.']);
    exit();
}

try {
    // Handle both GET and POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
    } else {
        $input = $_GET;
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'get_pending_deposits':
            // Get all pending deposits
            $pdo = getDBConnection();
            if (!$pdo) {
                echo json_encode(['success' => false, 'message' => 'Database connection failed']);
                exit();
            }
            
            $stmt = $pdo->prepare("
                SELECT d.*, u.username, u.email, u.full_name
                FROM deposits d
                JOIN users u ON d.user_id = u.id
                WHERE d.status = 'pending'
                ORDER BY d.created_at DESC
            ");
            $stmt->execute();
            $deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format deposits
            $formattedDeposits = array_map(function($dep) {
                return [
                    'id' => $dep['id'],
                    'user_id' => $dep['user_id'],
                    'username' => $dep['username'],
                    'email' => $dep['email'],
                    'full_name' => $dep['full_name'],
                    'amount' => floatval($dep['amount']),
                    'unique_code' => intval($dep['unique_code']),
                    'final_amount' => floatval($dep['final_amount']),
                    'formatted_final_amount' => 'Rp ' . number_format($dep['final_amount'], 0, ',', '.'),
                    'payment_proof' => $dep['payment_proof'],
                    'status' => $dep['status'],
                    'created_at' => $dep['created_at'],
                    'expired_at' => $dep['expired_at']
                ];
            }, $deposits);
            
            echo json_encode([
                'success' => true,
                'deposits' => $formattedDeposits,
                'count' => count($formattedDeposits)
            ]);
            break;
            
        case 'get_all_deposits':
            // Get all deposits with optional filter
            $pdo = getDBConnection();
            if (!$pdo) {
                echo json_encode(['success' => false, 'message' => 'Database connection failed']);
                exit();
            }
            
            $status = $input['status'] ?? '';
            $limit = isset($input['limit']) ? intval($input['limit']) : 50;
            $offset = isset($input['offset']) ? intval($input['offset']) : 0;
            
            $sql = "
                SELECT d.*, u.username, u.email, u.full_name
                FROM deposits d
                JOIN users u ON d.user_id = u.id
            ";
            
            $params = [];
            if ($status && in_array($status, ['pending', 'success', 'failed', 'expired'])) {
                $sql .= " WHERE d.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY d.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count
            $countSql = "SELECT COUNT(*) FROM deposits d";
            if ($status && in_array($status, ['pending', 'success', 'failed', 'expired'])) {
                $countSql .= " WHERE d.status = ?";
                $stmt = $pdo->prepare($countSql);
                $stmt->execute([$status]);
            } else {
                $stmt = $pdo->query($countSql);
            }
            $totalCount = $stmt->fetchColumn();
            
            // Format deposits
            $formattedDeposits = array_map(function($dep) {
                return [
                    'id' => $dep['id'],
                    'user_id' => $dep['user_id'],
                    'username' => $dep['username'],
                    'email' => $dep['email'],
                    'full_name' => $dep['full_name'],
                    'amount' => floatval($dep['amount']),
                    'unique_code' => intval($dep['unique_code']),
                    'final_amount' => floatval($dep['final_amount']),
                    'formatted_final_amount' => 'Rp ' . number_format($dep['final_amount'], 0, ',', '.'),
                    'payment_proof' => $dep['payment_proof'],
                    'status' => $dep['status'],
                    'admin_notes' => $dep['admin_notes'],
                    'created_at' => $dep['created_at'],
                    'updated_at' => $dep['updated_at'],
                    'expired_at' => $dep['expired_at'],
                    'processed_at' => $dep['processed_at']
                ];
            }, $deposits);
            
            echo json_encode([
                'success' => true,
                'deposits' => $formattedDeposits,
                'count' => count($formattedDeposits),
                'total' => $totalCount,
                'limit' => $limit,
                'offset' => $offset
            ]);
            break;
            
        case 'approve_deposit':
            // Approve a deposit
            if (!isset($input['deposit_id']) || empty($input['deposit_id'])) {
                echo json_encode(['success' => false, 'message' => 'Deposit ID harus diisi']);
                exit();
            }
            
            $depositId = intval($input['deposit_id']);
            $adminNotes = $input['admin_notes'] ?? '';
            
            $result = approveDeposit($depositId, $user['id'], $adminNotes);
            echo json_encode($result);
            break;
            
        case 'reject_deposit':
            // Reject a deposit
            if (!isset($input['deposit_id']) || empty($input['deposit_id'])) {
                echo json_encode(['success' => false, 'message' => 'Deposit ID harus diisi']);
                exit();
            }
            
            $depositId = intval($input['deposit_id']);
            $adminNotes = $input['admin_notes'] ?? 'Ditolak oleh admin';
            
            $result = rejectDeposit($depositId, $user['id'], $adminNotes);
            echo json_encode($result);
            break;
            
        case 'get_deposit_stats':
            // Get deposit statistics
            $pdo = getDBConnection();
            if (!$pdo) {
                echo json_encode(['success' => false, 'message' => 'Database connection failed']);
                exit();
            }
            
            // Get counts by status
            $stmt = $pdo->query("
                SELECT 
                    status,
                    COUNT(*) as count,
                    SUM(amount) as total_amount
                FROM deposits
                GROUP BY status
            ");
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get today's stats
            $stmt = $pdo->query("
                SELECT 
                    COUNT(*) as count,
                    SUM(amount) as total_amount
                FROM deposits
                WHERE DATE(created_at) = CURDATE()
            ");
            $todayStats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'stats' => $stats,
                'today' => $todayStats
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log('Admin Deposit API Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred: ' . $e->getMessage()
    ]);
}
