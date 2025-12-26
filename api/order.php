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

// Include auth and config
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/MedanpediaAPI.php';

// Check authentication
if (!auth_check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Only accept POST requests for creating orders
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
    
    // Validate required fields
    $required_fields = ['service_id', 'link', 'quantity'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            echo json_encode(['success' => false, 'message' => "Field $field is required"]);
            exit();
        }
    }
    
    $service_id = (int)$input['service_id'];
    $link = trim($input['link']);
    $quantity = (int)$input['quantity'];
    
    // Validate quantity
    if ($quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Quantity must be greater than 0']);
        exit();
    }
    
    // Get user info
    $user = auth_user();
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found in session']);
        exit();
    }
    
    // Get service details from API
    $api = new MedanpediaAPI();
    $servicesResult = $api->getServices();
    
    if (!$servicesResult['status']) {
        echo json_encode(['success' => false, 'message' => 'Failed to load services: ' . $servicesResult['msg']]);
        exit();
    }
    
    // Find the specific service
    $service = null;
    foreach ($servicesResult['data'] as $s) {
        if ($s['id'] == $service_id) {
            $service = $s;
            break;
        }
    }
    
    if (!$service) {
        echo json_encode(['success' => false, 'message' => 'Service not found']);
        exit();
    }
    
    // Validate quantity against service min/max
    if (isset($service['min']) && $quantity < $service['min']) {
        echo json_encode(['success' => false, 'message' => "Minimum quantity is {$service['min']}"]);
        exit();
    }
    
    if (isset($service['max']) && $quantity > $service['max']) {
        echo json_encode(['success' => false, 'message' => "Maximum quantity is {$service['max']}"]);
        exit();
    }
    
    // Calculate price with minimal markup (panel price logic: API price is per 1000 units)
    $original_price = floatval($service['price']); // base price per 1000
    $markup_amount = 200; // flat markup 200 rupiah per 1000 units
    $markup_price = $original_price + $markup_amount; // display/charge price per 1000 after markup
    $total_price_raw = ($markup_price / 1000) * $quantity; // charge proportional to quantity
    
    // Round to nearest Rupiah (integer) to avoid decimal precision issues
    $total_price = round($total_price_raw, 0); // Round to nearest integer Rupiah
    
    // Log calculation for debugging
    error_log("Price calculation: original=$original_price, markup=$markup_price, quantity=$quantity, raw_total=$total_price_raw, final_total=$total_price");
    
    // Check user balance (session figure) - enhanced messaging
    if ($user['balance'] < $total_price) {
        $shortfall = max(0, $total_price - $user['balance']);
        echo json_encode([
            'success' => false,
            'code' => 'INSUFFICIENT_BALANCE',
            'message' => 'Saldo tidak cukup.',
            'detail' => 'Saldo Anda kurang untuk melakukan pesanan ini.',
            'required' => (float)$total_price,
            'available' => (float)$user['balance'],
            'shortfall' => (float)$shortfall,
            'formatted' => [
                'required' => 'Rp ' . number_format($total_price, 0, ',', '.'),
                'available' => 'Rp ' . number_format($user['balance'], 0, ',', '.'),
                'shortfall' => 'Rp ' . number_format($shortfall, 0, ',', '.')
            ],
            'suggestion' => 'Top up minimal ' . 'Rp ' . number_format($shortfall, 0, ',', '.') . ' untuk melanjutkan.'
        ]);
        exit();
    }
    
    // Create order via Medanpedia API
    $orderResult = $api->createOrder($service_id, $link, $quantity);

    // Debug: Log the raw response
    error_log("=== MEDANPEDIA API RESPONSE ===");
    error_log("Raw response: " . json_encode($orderResult));
    error_log("Response type: " . gettype($orderResult));
    if (is_array($orderResult)) {
        error_log("Response keys: " . implode(', ', array_keys($orderResult)));
    }
    error_log("===============================");

    // Provider order id fallback parsing (variasi kemungkinan struktur)
    $providerOrderId = null;
    
    // Check various possible response structures berdasarkan dokumentasi Medanpedia
    // Prioritas utama: data.id (format standar Medanpedia)
    if (isset($orderResult['data']['id']) && is_numeric($orderResult['data']['id'])) {
        $providerOrderId = $orderResult['data']['id'];
        error_log("✅ Found Medanpedia order ID in 'data.id': " . $providerOrderId);
    } elseif (isset($orderResult['order']) && is_scalar($orderResult['order'])) {
        $providerOrderId = $orderResult['order'];
        error_log("✅ Found provider ID in 'order': " . $providerOrderId);
    } elseif (isset($orderResult['data']['order'])) {
        $providerOrderId = $orderResult['data']['order'];
        error_log("✅ Found provider ID in 'data.order': " . $providerOrderId);
    } elseif (isset($orderResult['order_id'])) {
        $providerOrderId = $orderResult['order_id'];
        error_log("✅ Found provider ID in 'order_id': " . $providerOrderId);
    } elseif (isset($orderResult['id'])) {
        $providerOrderId = $orderResult['id'];
        error_log("✅ Found provider ID in 'id': " . $providerOrderId);
    } 
    
    // Medanpedia mungkin return response seperti:
    // {"status": true, "msg": "Pesanan ditemukan.", "data": {"id": "1107", "status": "Processing", ...}}
    // Jadi coba ambil dari data.id juga
    if (!$providerOrderId && isset($orderResult['data']) && is_array($orderResult['data'])) {
        error_log("🔍 Searching in data array...");
        error_log("Data keys: " . implode(', ', array_keys($orderResult['data'])));
        
        // Coba semua key yang mungkin berisi order ID
        $possibleKeys = ['id', 'order', 'order_id', 'orderId'];
        foreach ($possibleKeys as $key) {
            if (isset($orderResult['data'][$key]) && is_scalar($orderResult['data'][$key])) {
                $providerOrderId = $orderResult['data'][$key];
                error_log("✅ Found provider ID in 'data.$key': " . $providerOrderId);
                break;
            }
        }
    }
    
    // IMPORTANT: Pastikan ini bukan internal ID
    if ($providerOrderId && is_numeric($providerOrderId) && $providerOrderId > 100000) {
        error_log("⚠️  Provider ID seems too large, might be internal ID: $providerOrderId");
    }
    
    if ($providerOrderId) {
        error_log("🎯 Final provider order ID: " . $providerOrderId);
    } else {
        error_log("❌ No provider order ID found in response");
        if (is_array($orderResult)) {
            error_log("Available keys in response: " . implode(', ', array_keys($orderResult)));
            if (isset($orderResult['data']) && is_array($orderResult['data'])) {
                error_log("Available keys in data: " . implode(', ', array_keys($orderResult['data'])));
            }
        }
    }

    $orderSuccess = (
        (isset($orderResult['status']) && $orderResult['status'] === true) ||
        (isset($providerOrderId) && is_numeric($providerOrderId))
    );

    if ($orderSuccess) {
        // Order successful, deduct balance & record
        $pdo = getDBConnection();
        if ($pdo) {
            try {
                /* ----------------------------------------------
                 * 1. Pastikan schema & baris saldo (DI LUAR TRANSAKSI)
                 * ---------------------------------------------- */
                // Row saldo
                $pdo->prepare('INSERT IGNORE INTO user_balance (user_id, balance, total_spent) VALUES (?,0,0)')->execute([$user['id']]);

                // Tabel orders
                $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    service_id INT NOT NULL,
                    service_name VARCHAR(255) NOT NULL,
                    base_price DECIMAL(10,2) NOT NULL DEFAULT 0,
                    link TEXT NOT NULL,
                    quantity INT NOT NULL,
                    price_per_unit DECIMAL(10,2) NOT NULL,
                    total_price DECIMAL(10,2) NOT NULL,
                    api_order_id VARCHAR(255),
                    status VARCHAR(50) DEFAULT 'pending',
                    start_count INT NULL DEFAULT NULL,
                    remains INT NULL DEFAULT NULL,
                    charge DECIMAL(10,2) NULL DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_user_created (user_id, created_at),
                    INDEX idx_api_order (api_order_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

                // Self-healing kolom hilang
                try {
                    $cols = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
                    if (!in_array('link', $cols, true)) {
                        $pdo->exec("ALTER TABLE orders ADD COLUMN link TEXT NULL AFTER service_name");
                        if (in_array('target', $cols, true)) {
                            $pdo->exec("UPDATE orders SET link = target WHERE (link IS NULL OR link='') AND target IS NOT NULL");
                        }
                        $pdo->exec("UPDATE orders SET link = '' WHERE link IS NULL");
                        $pdo->exec("ALTER TABLE orders MODIFY link TEXT NOT NULL");
                        $cols[] = 'link';
                    }
                    $required = [
                        'base_price' => "ALTER TABLE orders ADD COLUMN base_price DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER service_name",
                        'price_per_unit' => "ALTER TABLE orders ADD COLUMN price_per_unit DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER quantity",
                        'total_price' => "ALTER TABLE orders ADD COLUMN total_price DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER price_per_unit",
                        'api_order_id' => "ALTER TABLE orders ADD COLUMN api_order_id VARCHAR(100) NULL AFTER total_price",
                        'medanpedia_order_id' => "ALTER TABLE orders ADD COLUMN medanpedia_order_id VARCHAR(50) NULL AFTER api_order_id",
                        'status' => "ALTER TABLE orders ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'pending' AFTER medanpedia_order_id",
                        'updated_at' => "ALTER TABLE orders ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at"
                    ];
                    foreach ($required as $col => $ddl) {
                        if (!in_array($col, $cols, true)) {
                            try { $pdo->exec($ddl); } catch (Exception $ie) { /* ignore */ }
                        }
                    }
                } catch (Exception $e) { /* silent */ }

                /* ----------------------------------------------
                 * 2. Transaksi murni untuk pengurangan saldo + insert order
                 * ---------------------------------------------- */
                $pdo->beginTransaction();

                // Atomic balance deduction (hindari race condition)
                // Ensure exact precision by using rounded total_price
                $deductAmount = (int)$total_price; // Force to integer to avoid decimal issues
                error_log("Deducting exact amount from balance: $deductAmount");
                
                $stmt = $pdo->prepare('UPDATE user_balance SET balance = balance - ?, total_spent = total_spent + ? WHERE user_id = ? AND balance >= ?');
                $stmt->execute([$deductAmount, $deductAmount, $user['id'], $deductAmount]);
                if ($stmt->rowCount() === 0) {
                    // Saldo tidak cukup (race condition / perubahan mendadak) - ambil saldo terbaru untuk detail
                    $currentBal = 0.0;
                    try {
                        $bStmt = $pdo->prepare('SELECT balance FROM user_balance WHERE user_id = ? LIMIT 1');
                        $bStmt->execute([$user['id']]);
                        $currentBal = (float)($bStmt->fetchColumn() ?? 0);
                    } catch (Exception $ib) { /* ignore */ }
                    if ($pdo->inTransaction()) { $pdo->rollBack(); }
                    $shortfall = max(0, $total_price - $currentBal);
                    echo json_encode([
                        'success' => false,
                        'code' => 'INSUFFICIENT_BALANCE_CONCURRENT',
                        'message' => 'Saldo tiba-tiba tidak cukup (perubahan bersamaan).',
                        'detail' => 'Terjadi perubahan saldo saat proses order. Coba ulangi setelah top up atau pastikan tidak ada pesanan ganda.',
                        'required' => (float)$total_price,
                        'available' => (float)$currentBal,
                        'shortfall' => (float)$shortfall,
                        'formatted' => [
                            'required' => 'Rp ' . number_format($total_price, 0, ',', '.'),
                            'available' => 'Rp ' . number_format($currentBal, 0, ',', '.'),
                            'shortfall' => 'Rp ' . number_format($shortfall, 0, ',', '.')
                        ],
                        'suggestion' => $shortfall > 0 ? ('Top up minimal ' . 'Rp ' . number_format($shortfall, 0, ',', '.') . ' lalu coba lagi.') : 'Silakan coba lagi.'
                    ]);
                    exit();
                }

    // IMPORTANT: Validate provider order ID before saving
    if ($providerOrderId && is_numeric($providerOrderId)) {
        // Medanpedia order IDs can be large numbers (like 23225869)
        if ($providerOrderId <= 0) {
            error_log("⚠️  WARNING: Provider ID is not positive: $providerOrderId");
            $providerOrderId = null;
        }
    }

                $stmt = $pdo->prepare('INSERT INTO orders (user_id, service_id, service_name, base_price, link, quantity, price_per_unit, total_price, api_order_id, medanpedia_order_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
                $insertResult = $stmt->execute([
                    $user['id'],
                    $service_id,
                    $service['name'],
                    (int)round($original_price, 0), // base_price as integer
                    $link,
                    $quantity,
                    (int)round($markup_price, 0), // price_per_unit as integer  
                    (int)$total_price, // total_price as integer (already rounded)
                    $providerOrderId, // api_order_id (Medanpedia order ID)
                    $providerOrderId, // medanpedia_order_id (same as api_order_id for consistency)
                    'pending'
                ]);

                if (!$insertResult) {
                    error_log("Failed to insert order to database");
                    if ($pdo->inTransaction()) { $pdo->rollBack(); }
                    echo json_encode(['success' => false, 'message' => 'Failed to save order to database']);
                    exit();
                }

                // Internal (panel) order id
                $internalOrderId = (int)$pdo->lastInsertId();
                
                // Log successful insertion with detailed info
                error_log("=== ORDER CREATION DEBUG ===");
                error_log("Internal ID (from database): $internalOrderId");
                error_log("Provider ID (from Medanpedia): " . ($providerOrderId ?? 'NULL'));
                error_log("Raw Medanpedia response: " . json_encode($orderResult));
                error_log("Provider ID type: " . gettype($providerOrderId));
                if ($providerOrderId) {
                    error_log("Provider ID value: '$providerOrderId'");
                }
                error_log("===========================");

                if ($pdo->inTransaction()) { $pdo->commit(); }

                auth_refresh_balance();
                $freshUser = auth_user();
                
                // Double check what's actually in database
                $checkStmt = $pdo->prepare("SELECT api_order_id FROM orders WHERE id = ?");
                $checkStmt->execute([$internalOrderId]);
                $savedApiOrderId = $checkStmt->fetchColumn();
                error_log("Verified from database - api_order_id: " . ($savedApiOrderId ?? 'NULL'));
                // Determine provider order id (different APIs sometimes nest it)
                // providerOrderId sudah disiapkan sebelumnya
                echo json_encode([
                    'success' => true,
                    'message' => 'Order created successfully',
                    // Use internal order id for UI navigation
                    'order_id' => $internalOrderId,
                    'provider_order_id' => $providerOrderId,
                    'total_price' => $total_price,
                    'remaining_balance' => $freshUser['balance'] ?? null,
                    'raw_provider' => $orderResult
                ]);
            } catch (Exception $e) {
                try { if ($pdo->inTransaction()) { $pdo->rollBack(); } } catch (Exception $rbEx) { /* ignore */ }
                error_log('Order DB error user_id=' . ($user['id'] ?? 'n/a') . ' provider_resp=' . json_encode($orderResult) . ' : ' . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Database error: ' . $e->getMessage(),
                    'raw_provider' => $orderResult
                ]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        }
    } else {
        $resp = [
            'success' => false,
            'message' => 'Failed to create order: ' . ($orderResult['msg'] ?? 'Unknown error'),
            'raw_provider' => $orderResult
        ];
        if (is_array($orderResult) && isset($orderResult['debug'])) {
            $resp['debug'] = $orderResult['debug'];
        }
        echo json_encode($resp);
    }
    
} catch (Exception $e) {
    error_log('Order API Error: ' . $e->getMessage());
    $msg = 'Server error occurred';
    if (defined('APP_DEBUG') && APP_DEBUG) {
        $msg .= ': ' . $e->getMessage();
    }
    echo json_encode([
        'success' => false,
        'message' => $msg
    ]);
}
?>
