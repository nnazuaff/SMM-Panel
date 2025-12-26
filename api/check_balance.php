<?php
/**
 * Endpoint untuk cek saldo Medanpedia
 * File ini mengembalikan response JSON untuk AJAX request
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include API class
require_once __DIR__ . '/MedanpediaAPI.php';

try {
    // Initialize API
    $api = new MedanpediaAPI();
    
    // Get profile/saldo
    $result = $api->getProfile();
    
    if ($result['status']) {
        // Format response sukses
        $response = [
            'status' => true,
            'message' => 'Saldo berhasil diambil',
            'data' => [
                'username' => $result['data']['username'],
                'full_name' => $result['data']['full_name'],
                'balance' => $result['data']['balance'],
                'balance_formatted' => $api->formatCurrency($result['data']['balance']),
                'last_updated' => date('Y-m-d H:i:s')
            ]
        ];
    } else {
        // Format response error
        $response = [
            'status' => false,
            'message' => $result['msg'],
            'data' => null
        ];
    }
    
} catch (Exception $e) {
    // Handle exception
    $response = [
        'status' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'data' => null
    ];
}

// Return JSON response
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
