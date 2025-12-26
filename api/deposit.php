<?php
header('Content-Type: application/json');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include required files
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/deposit.php';

// Check authentication
if (!auth_check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit();
    }
    
    $action = $input['action'] ?? '';
    
    // Get user info
    $user = auth_user();
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    switch ($action) {
        case 'create_deposit':
            // Validate required fields
            if (!isset($input['amount']) || empty($input['amount'])) {
                echo json_encode(['success' => false, 'message' => 'Nominal deposit harus diisi']);
                exit();
            }
            
            if (!isset($input['unique_code']) || empty($input['unique_code'])) {
                echo json_encode(['success' => false, 'message' => 'Kode unik tidak valid']);
                exit();
            }
            
            if (!isset($input['final_amount']) || empty($input['final_amount'])) {
                echo json_encode(['success' => false, 'message' => 'Total nominal tidak valid']);
                exit();
            }
            
            $amount = floatval($input['amount']);
            $uniqueCode = intval($input['unique_code']);
            $finalAmount = floatval($input['final_amount']);
            
            // Validate amount range
            if ($amount < 1000) {
                echo json_encode(['success' => false, 'message' => 'Minimal deposit adalah Rp 1.000']);
                exit();
            }
            
            if ($amount > 200000) {
                echo json_encode(['success' => false, 'message' => 'Maksimal deposit adalah Rp 200.000']);
                exit();
            }
            
            // Validate unique code range
            if ($uniqueCode < 100 || $uniqueCode > 200) {
                echo json_encode(['success' => false, 'message' => 'Kode unik tidak valid']);
                exit();
            }
            
            // Validate calculation
            if (abs(($amount + $uniqueCode) - $finalAmount) > 0.01) {
                echo json_encode(['success' => false, 'message' => 'Perhitungan nominal tidak sesuai']);
                exit();
            }
            
            // Submit deposit request
            $result = submitDepositRequest($user['id'], $amount, $uniqueCode, $finalAmount);
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => $result['message'],
                    'deposit_id' => $result['deposit_id'],
                    'expired_at' => $result['expired_at'],
                    'data' => [
                        'amount' => $amount,
                        'unique_code' => $uniqueCode,
                        'final_amount' => $finalAmount,
                        'formatted_final_amount' => 'Rp ' . number_format($finalAmount, 0, ',', '.')
                    ]
                ]);
            } else {
                echo json_encode($result);
            }
            break;
            
        case 'upload_proof':
            // This will be handled separately via form upload
            // For now, just return success
            echo json_encode(['success' => true, 'message' => 'Upload proof endpoint']);
            break;
            
        case 'get_deposits':
            // Get user's deposit history
            $limit = isset($input['limit']) ? intval($input['limit']) : 10;
            $deposits = getUserDeposits($user['id'], $limit);
            
            // Format deposits for display
            $formattedDeposits = array_map(function($dep) {
                return [
                    'id' => $dep['id'],
                    'amount' => floatval($dep['amount']),
                    'unique_code' => intval($dep['unique_code']),
                    'final_amount' => floatval($dep['final_amount']),
                    'formatted_final_amount' => 'Rp ' . number_format($dep['final_amount'], 0, ',', '.'),
                    'status' => $dep['status'],
                    'status_label' => ucfirst($dep['status']),
                    'payment_proof' => $dep['payment_proof'],
                    'admin_notes' => $dep['admin_notes'],
                    'created_at' => $dep['created_at'],
                    'expired_at' => $dep['expired_at'],
                    'processed_at' => $dep['processed_at']
                ];
            }, $deposits);
            
            echo json_encode([
                'success' => true,
                'deposits' => $formattedDeposits,
                'count' => count($formattedDeposits)
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log('Deposit API Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred: ' . $e->getMessage()
    ]);
}
