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
require_once __DIR__ . '/../config/balance_conversion.php';

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
        case 'submit_conversion':
            // Validate required fields
            if (!isset($input['acispayment_username']) || empty(trim($input['acispayment_username']))) {
                echo json_encode(['success' => false, 'message' => 'Username AcisPayment harus diisi']);
                exit();
            }
            
            if (!isset($input['amount']) || empty($input['amount'])) {
                echo json_encode(['success' => false, 'message' => 'Nominal konversi harus diisi']);
                exit();
            }
            
            $acispaymentUsername = trim($input['acispayment_username']);
            $amount = floatval($input['amount']);
            
            // Validate amount
            if ($amount < 1000) {
                echo json_encode(['success' => false, 'message' => 'Minimal konversi adalah Rp 1.000']);
                exit();
            }
            
            if ($amount > 10000000) {
                echo json_encode(['success' => false, 'message' => 'Maksimal konversi adalah Rp 10.000.000']);
                exit();
            }
            
            // Submit conversion request
            $result = submitConversionRequest($user['id'], $acispaymentUsername, $amount);
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => $result['message'],
                    'request_id' => $result['request_id'],
                    'data' => [
                        'username' => $acispaymentUsername,
                        'amount' => $amount,
                        'formatted_amount' => 'Rp ' . number_format($amount, 0, ',', '.')
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => $result['message']
                ]);
            }
            break;
            
        case 'get_requests':
            // Get user's conversion requests
            $limit = isset($input['limit']) ? intval($input['limit']) : 10;
            $requests = getUserConversionRequests($user['id'], $limit);
            
            // Format requests for display
            $formattedRequests = array_map(function($req) {
                return [
                    'id' => $req['id'],
                    'username' => $req['acispayment_username'],
                    'amount' => floatval($req['amount']),
                    'formatted_amount' => 'Rp ' . number_format($req['amount'], 0, ',', '.'),
                    'status' => $req['status'],
                    'status_label' => ucfirst($req['status']),
                    'admin_notes' => $req['admin_notes'],
                    'created_at' => $req['created_at'],
                    'updated_at' => $req['updated_at'],
                    'processed_at' => $req['processed_at']
                ];
            }, $requests);
            
            echo json_encode([
                'success' => true,
                'requests' => $formattedRequests,
                'count' => count($formattedRequests)
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log('Balance Conversion API Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred: ' . $e->getMessage()
    ]);
}
