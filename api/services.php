<?php
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

// Include auth and config
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/MedanpediaAPI.php';

// Check authentication
if (!auth_check()) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized - Please login first',
        'debug' => [
            'session_status' => session_status(),
            'session_id' => session_id(),
            'user_session' => isset($_SESSION['user']) ? 'exists' : 'missing'
        ]
    ]);
    exit();
}

try {
    // Handle both GET and POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
    } else {
        $action = $_GET['action'] ?? 'get_services';
    }

    switch ($action) {
        case 'get_services':
            $api = new MedanpediaAPI();
            $result = $api->getServices();
            
            // Debug log untuk melihat response asli
            error_log('Medanpedia API Response: ' . json_encode($result));
            
            if (isset($result['status']) && $result['status'] === true) {
                // Pastikan data adalah array
                if (!isset($result['data']) || !is_array($result['data'])) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Data layanan tidak valid atau kosong',
                        'debug' => [
                            'api_response' => $result,
                            'data_type' => gettype($result['data'] ?? null),
                            'data_isset' => isset($result['data']),
                            'data_is_array' => is_array($result['data'] ?? null)
                        ]
                    ]);
                    break;
                }

                // Ambil parameter filter & sort (kompatibel dengan frontend) dari GET atau body (POST)
                $q = isset($_GET['q']) ? trim($_GET['q']) : (isset($input['q']) ? trim($input['q']) : '');
                $categoryFilter = isset($_GET['category']) ? trim($_GET['category']) : (isset($input['category']) ? trim($input['category']) : '');
                $sort = isset($_GET['sort']) ? trim($_GET['sort']) : (isset($input['sort']) ? trim($input['sort']) : '');

                $qLower = $q !== '' ? mb_strtolower($q) : '';
                
                // Kumpulan sementara (flatten) untuk proses filter & sort
                $flat = [];
                $skippedServices = 0;
                foreach ($result['data'] as $index => $service) {
                    if (!is_array($service)) { $skippedServices++; continue; }
                    if (!isset($service['id'])) { $skippedServices++; continue; }
                    if (!isset($service['name']) || trim($service['name'])==='') { $skippedServices++; continue; }

                    $category = isset($service['category']) && !empty($service['category']) ? $service['category'] : 'Other';
                    $name = (string)$service['name'];

                    // Filter pencarian (q) - cocok di nama atau kategori
                    if ($qLower !== '') {
                        $nameLower = mb_strtolower($name);
                        $catLower = mb_strtolower($category);
                        if (strpos($nameLower, $qLower) === false && strpos($catLower, $qLower) === false) {
                            continue; // tidak match search
                        }
                    }

                    // Filter kategori (exact match)
                    if ($categoryFilter !== '' && $category !== $categoryFilter) {
                        continue;
                    }

                    $flat[] = [
                        'id' => (int)$service['id'],
                        'name' => $name,
                        'description' => isset($service['description']) ? (string)$service['description'] : 'Tidak ada deskripsi tersedia',
                        'price' => isset($service['price']) ? (float)$service['price'] : 0,
                        'min' => isset($service['min']) ? (int)$service['min'] : 1,
                        'max' => isset($service['max']) ? (int)$service['max'] : 10000,
                        'average_time' => isset($service['average_time']) ? (string)$service['average_time'] : null,
                        'category' => $category
                    ];
                }

                // Sorting (price_asc, price_desc, name_asc, name_desc)
                switch ($sort) {
                    case 'price_asc':
                        usort($flat, function($a,$b){ return $a['price'] <=> $b['price']; });
                        break;
                    case 'price_desc':
                        usort($flat, function($a,$b){ return $b['price'] <=> $a['price']; });
                        break;
                    case 'name_asc':
                        usort($flat, function($a,$b){ return strcasecmp($a['name'],$b['name']); });
                        break;
                    case 'name_desc':
                        usort($flat, function($a,$b){ return strcasecmp($b['name'],$a['name']); });
                        break;
                }

                // Tentukan apakah request ini "legacy" (order form) yang mengharapkan SEMUA layanan sekaligus.
                // Ciri legacy: POST hanya kirim {action:"get_services"} tanpa param paging.
                $isLegacyFull = (
                    $_SERVER['REQUEST_METHOD'] === 'POST'
                    && (empty($_GET))
                    && (
                        empty($input) 
                        || (is_array($input) && count($input) === 1 && isset($input['action']))
                        || (isset($input['all']) && $input['all'])
                    )
                );

                // Pagination & limit (kecuali legacy full)
                if ($isLegacyFull) {
                    $perPage = count($flat) ?: 1;
                    $page = 1;
                    $totalAfterFilter = count($flat);
                    $totalPages = 1;
                    $paged = $flat; // semua data
                } else {
                    // Prioritas parameter: body kemudian GET (agar order form bisa kirim per_page bila diinginkan ke depan)
                    $perPage = isset($input['per_page']) ? (int)$input['per_page'] : (isset($_GET['per_page']) ? (int)$_GET['per_page'] : (isset($_GET['limit']) ? (int)$_GET['limit'] : 25));
                    if ($perPage < 1) $perPage = 25;
                    if ($perPage > 100) $perPage = 100; // hard cap
                    $page = isset($input['page']) ? (int)$input['page'] : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
                    if ($page < 1) $page = 1;
                    $totalAfterFilter = count($flat);
                    $totalPages = $totalAfterFilter > 0 ? (int)ceil($totalAfterFilter / $perPage) : 0;
                    if ($totalPages > 0 && $page > $totalPages) $page = $totalPages;
                    $offset = ($page - 1) * $perPage;
                    $paged = $totalAfterFilter ? array_slice($flat, $offset, $perPage) : [];
                }

                // Siapkan daftar kategori dari seluruh hasil filter (bukan hanya halaman ini) agar dropdown lengkap
                $categories = [];
                foreach ($flat as $sCat) {
                    $key = strtolower(str_replace([' ', '-', '_'], '', $sCat['category']));
                    $categories[$key] = $sCat['category'];
                }

                // Regroup hanya data halaman saat ini
                $groupedServices = [];
                foreach ($paged as $service) {
                    $categoryKey = strtolower(str_replace([' ', '-', '_'], '', $service['category']));
                    if (!isset($groupedServices[$categoryKey])) {
                        $groupedServices[$categoryKey] = [];
                    }
                    $groupedServices[$categoryKey][] = $service;
                }
                
                echo json_encode([
                    'success' => true,
                    'services' => $groupedServices,
                    'categories' => $categories,
                    'total_services' => count($result['data']), // total asal dari API (sebelum filter)
                    'valid_services' => $totalAfterFilter, // total setelah filter (sebelum paging)
                    'shown_services' => array_sum(array_map('count', $groupedServices)), // jumlah yang ditampilkan (halaman ini)
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => $totalPages,
                    'legacy_full' => $isLegacyFull,
                    'filtered' => [
                        'search' => $q,
                        'category' => $categoryFilter,
                        'sort' => $sort
                    ],
                    'skipped_services' => $skippedServices,
                    'total_categories' => count($categories),
                    'debug' => [
                        'raw_data_count' => count($result['data']),
                        'category_keys' => array_keys($categories),
                        'services_per_category' => array_map('count', $groupedServices),
                        'after_filter_before_paging' => $totalAfterFilter,
                        'paging' => true,
                        'applied_filters' => $q !== '' || $categoryFilter !== '' || $sort !== ''
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => isset($result['msg']) ? $result['msg'] : 'Gagal memuat layanan dari API',
                    'debug' => $result
                ]);
            }
            break;
            
        case 'get_service_detail':
            $serviceId = isset($_GET['service_id']) ? (int)$_GET['service_id'] : (isset($input['service_id']) ? (int)$input['service_id'] : 0);
            
            if (!$serviceId || $serviceId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Service ID required dan harus berupa angka valid']);
                break;
            }
            
            $api = new MedanpediaAPI();
            $result = $api->getServices();
            
            if (isset($result['status']) && $result['status'] === true) {
                if (!isset($result['data']) || !is_array($result['data'])) {
                    echo json_encode(['success' => false, 'message' => 'Data layanan tidak valid']);
                    break;
                }
                
                $service = null;
                foreach ($result['data'] as $s) {
                    if (!is_array($s) || !isset($s['id'])) continue;
                    
                    if ((int)$s['id'] === $serviceId) {
                        $service = $s;
                        break;
                    }
                }
                
                if ($service) {
                    echo json_encode([
                        'success' => true,
                        'service' => [
                            'id' => (int)$service['id'],
                            'name' => (string)$service['name'],
                            'description' => isset($service['description']) ? (string)$service['description'] : 'Tidak ada deskripsi tersedia',
                            'price' => isset($service['price']) ? (float)$service['price'] : 0,
                            'min' => isset($service['min']) ? (int)$service['min'] : 1,
                            'max' => isset($service['max']) ? (int)$service['max'] : 10000,
                            'category' => isset($service['category']) ? (string)$service['category'] : 'Other'
                        ]
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Service dengan ID ' . $serviceId . ' tidak ditemukan']);
                }
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => isset($result['msg']) ? $result['msg'] : 'Gagal memuat detail layanan dari API',
                    'debug' => $result
                ]);
            }
            break;
            
        default:
            // Backward compatibility for old format
            $api = new MedanpediaAPI();
            $q = isset($_GET['q']) ? trim($_GET['q']) : '';
            $category = isset($_GET['category']) ? trim($_GET['category']) : '';
            $sort = isset($_GET['sort']) ? trim($_GET['sort']) : '';
            $favOnly = isset($_GET['fav']) && $_GET['fav'] === '1';
            $force = isset($_GET['refresh']) && $_GET['refresh'] === '1';

            $result = $api->getServices(300, $force);
            
            if (!isset($result['status']) || $result['status'] !== true) {
                http_response_code(500);
                echo json_encode([
                    'status' => false,
                    'msg' => isset($result['msg']) ? $result['msg'] : 'Gagal memuat layanan dari API',
                    'debug' => isset($result['debug']) ? $result['debug'] : null
                ]);
                exit;
            }

            // Validasi data
            if (!isset($result['data']) || !is_array($result['data'])) {
                http_response_code(500);
                echo json_encode([
                    'status' => false,
                    'msg' => 'Data layanan tidak valid atau kosong',
                    'debug' => $result
                ]);
                exit;
            }

            $services = $result['data'];

            // Normalisasi kolom harga dan validasi data
            $validServices = [];
            foreach ($services as $s) {
                // Skip invalid service data
                if (!is_array($s) || !isset($s['id'], $s['name'])) {
                    continue;
                }
                
                // Normalisasi price
                $s['_price_num'] = isset($s['price']) ? (float)$s['price'] : 0;
                
                // Ensure all required fields exist
                $s['category'] = isset($s['category']) && !empty($s['category']) ? $s['category'] : 'Other';
                $s['min'] = isset($s['min']) ? (int)$s['min'] : 1;
                $s['max'] = isset($s['max']) ? (int)$s['max'] : 10000;
                $s['refill'] = isset($s['refill']) ? $s['refill'] : null;
                $s['average_time'] = isset($s['average_time']) ? $s['average_time'] : null;
                
                $validServices[] = $s;
            }
            
            $services = $validServices;

            // Filter search
            if ($q !== '') {
                $qLower = mb_strtolower($q);
                $services = array_filter($services, function($s) use ($qLower){
                    $nameMatch = strpos(mb_strtolower($s['name']), $qLower) !== false;
                    $categoryMatch = isset($s['category']) && strpos(mb_strtolower($s['category']), $qLower) !== false;
                    return $nameMatch || $categoryMatch;
                });
            }

            // Filter category
            if ($category !== '') {
                $services = array_filter($services, function($s) use ($category){
                    return isset($s['category']) && $s['category'] === $category;
                });
            }

            // Sort
            switch ($sort) {
                case 'price_asc':
                    usort($services, function($a,$b){return $a['_price_num'] <=> $b['_price_num'];});
                    break;
                case 'price_desc':
                    usort($services, function($a,$b){return $b['_price_num'] <=> $a['_price_num'];});
                    break;
                case 'name_asc':
                    usort($services, function($a,$b){return strcasecmp($a['name'],$b['name']);});
                    break;
                case 'name_desc':
                    usort($services, function($a,$b){return strcasecmp($b['name'],$a['name']);});
                    break;
            }

            // Kumpulkan kategori unik
            $categories = [];
            foreach ($services as $s) {
                if (!empty($s['category'])) {
                    $categories[$s['category']] = true;
                }
            }
            $categories = array_keys($categories);

            // Format harga
            function format_rp($n){ return 'Rp ' . number_format($n,0,',','.'); }

            // Pagination
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            if ($page < 1) $page = 1;
            $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
            if ($perPage < 1) $perPage = 20;
            if ($perPage > 20) $perPage = 20;

            // Transform dengan markup flat 200 rupiah
            $transformed = array_map(function($s){
                $displayPrice = $s['_price_num'] + 200; // flat 200 rupiah markup
                return [
                    'id' => (int)$s['id'],
                    'name' => (string)$s['name'],
                    'category' => (string)$s['category'],
                    'price' => (string)$s['price'],
                    'price_formatted' => format_rp($displayPrice),
                    'min' => $s['min'],
                    'max' => $s['max'],
                    'refill' => $s['refill'],
                    'average_time' => $s['average_time'],
                ];
            }, $services);

            $totalAll = count($transformed);
            $totalPages = $totalAll > 0 ? (int)ceil($totalAll / $perPage) : 0;
            if ($totalPages > 0 && $page > $totalPages) $page = $totalPages;
            $offset = ($page - 1) * $perPage;
            $pagedData = $totalAll ? array_slice($transformed, $offset, $perPage) : [];

            echo json_encode([
                'status' => true,
                'msg' => 'OK',
                'total' => $totalAll,
                'count' => count($pagedData),
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
                'cached' => isset($result['cached']) ? $result['cached'] : false,
                'categories' => $categories,
                'services' => $pagedData
            ]);
            break;
    }

} catch (Exception $e) {
    error_log('Services API Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan pada server'
    ]);
}
?>