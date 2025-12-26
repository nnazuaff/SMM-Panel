<?php
require_once __DIR__ . '/includes/auth.php';
auth_require();
auth_refresh_balance();

// Set timezone to WIB (Asia/Jakarta)
date_default_timezone_set('Asia/Jakarta');

$user = auth_user();
$activePage = 'transactions';
$sectionTitle = 'Riwayat Pesanan';

// Helper function to build URL with current filters
function buildFilterUrl($page, $excludeFilters = []) {
    $params = [];
    $params['page'] = $page;
    
    // Get current filter values
    $filterStatus = $_GET['status'] ?? '';
    $filterSearch = $_GET['search'] ?? '';
    $filterSearchType = $_GET['search_type'] ?? 'id';
    $filterYear = $_GET['year'] ?? '';
    
    // Add filters to params if they exist and not excluded
    if (!empty($filterStatus) && $filterStatus !== 'all' && !in_array('status', $excludeFilters)) {
        $params['status'] = $filterStatus;
    }
    if (!empty($filterSearch) && !in_array('search', $excludeFilters)) {
        $params['search'] = $filterSearch;
        $params['search_type'] = $filterSearchType;
    }
    if (!empty($filterYear) && !in_array('year', $excludeFilters)) {
        $params['year'] = $filterYear;
    }
    
    return '?' . http_build_query($params);
}

// Get user's orders from database
require_once __DIR__ . '/config/database.php';
$orders = [];
$paginationInfo = [
    'total' => 0,
    'current_page' => 1,
    'total_pages' => 1,
    'per_page' => 20
];

// Get filter parameters
$filterStatus = $_GET['status'] ?? '';
$filterSearch = $_GET['search'] ?? '';
$filterSearchType = $_GET['search_type'] ?? 'id'; // 'id' or 'target'
$filterYear = $_GET['year'] ?? '';

try {
    $pdo = getDBConnection();
    if ($pdo) {
        // Get pagination parameters
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        
        // Check if orders table exists and get column info
        $columns = [];
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM orders");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        } catch (Exception $e) {
            $columns = [];
        }
        
        if (!empty($columns)) {
            // Build WHERE clause with filters
            $whereConditions = ['user_id = ?'];
            $whereParams = [$user['id']];
            
            // Status filter
            if (!empty($filterStatus) && $filterStatus !== 'all') {
                $whereConditions[] = 'status = ?';
                $whereParams[] = $filterStatus;
            }
            
            // Search filter
            if (!empty($filterSearch)) {
                if ($filterSearchType === 'id') {
                    // Very simple ID search - just check the main id column
                    $searchTerm = trim($filterSearch);
                    
                    if (is_numeric($searchTerm)) {
                        // For numeric search, try both exact and partial match
                        $whereConditions[] = '(id = ? OR id LIKE ?)';
                        $whereParams[] = (int)$searchTerm;
                        $whereParams[] = '%' . $searchTerm . '%';
                    } else {
                        // For non-numeric search, convert id to string and search
                        $whereConditions[] = 'CAST(id AS CHAR) LIKE ?';
                        $whereParams[] = '%' . $searchTerm . '%';
                    }
                } else { // target
                    if (in_array('link', $columns)) {
                        $whereConditions[] = 'link LIKE ?';
                        $whereParams[] = '%' . $filterSearch . '%';
                    }
                }
            }
            
            // Date filter
            if (!empty($filterYear) && in_array('created_at', $columns)) {
                $whereConditions[] = 'YEAR(created_at) = ?';
                $whereParams[] = $filterYear;
            }
            
            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            
            // Temporary debug - remove this later
            if (!empty($filterSearch)) {
                echo "<!-- DEBUG: Search Term: " . htmlspecialchars($filterSearch) . " -->";
                echo "<!-- DEBUG: Search Type: " . htmlspecialchars($filterSearchType) . " -->";
                echo "<!-- DEBUG: Where Clause: " . htmlspecialchars($whereClause) . " -->";
                echo "<!-- DEBUG: Parameters: " . htmlspecialchars(print_r($whereParams, true)) . " -->";
            }
            
            // Get total count with filters
            $countSql = "SELECT COUNT(*) FROM orders {$whereClause}";
            $stmt = $pdo->prepare($countSql);
            $stmt->execute($whereParams);
            $total = (int)$stmt->fetchColumn();
            
            // Calculate pagination
            $totalPages = max(1, ceil($total / $perPage));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * $perPage;
            
            $paginationInfo = [
                'total' => $total,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'per_page' => $perPage
            ];
            
            // Build SELECT query based on available columns
            $selectFields = ['id'];
            $optionalFields = [
                'provider_order_id' => 'provider_order_id',
                'medanpedia_order_id' => 'medanpedia_order_id',
                'service_id' => 'service_id',
                'service_name' => 'service_name',
                'link' => 'link',
                'quantity' => 'quantity',
                'price' => 'price',
                'total_price' => 'total_price',
                'status' => 'status',
                'created_at' => 'created_at'
            ];
            
            foreach ($optionalFields as $field => $column) {
                if (in_array($column, $columns)) {
                    $selectFields[] = $column;
                }
            }
            
            $selectClause = implode(', ', $selectFields);
            $orderByClause = in_array('created_at', $columns) ? 'ORDER BY created_at DESC' : 'ORDER BY id DESC';
            
            // Execute main query with filters
            $mainSql = "SELECT {$selectClause} FROM orders {$whereClause} {$orderByClause} LIMIT ? OFFSET ?";
            $mainParams = array_merge($whereParams, [$perPage, $offset]);
            
            $stmt = $pdo->prepare($mainSql);
            $stmt->execute($mainParams);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Exception $e) {
    // Silent fail, orders remain empty array
    error_log("Error fetching orders: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="format-detection" content="telephone=no">
    <title>Riwayat Pesanan - AcisPedia</title>
    <link rel="icon" href="storage/assets/img/logo/logo_trans.png">
    
    <!-- Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="css/style-v2.css">
    
    <style>
        /* Global responsive improvements */
        * {
            box-sizing: border-box;
        }
        
        html {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        
        body {
            overflow-x: hidden;
            -webkit-tap-highlight-color: transparent;
        }
        
        /* Dashboard Layout - SAMA PERSIS DENGAN DASHBOARD.PHP */
        .dashboard-layout {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Enhanced - SAMA PERSIS */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, var(--navy-800) 0%, var(--navy-700) 100%);
            color: white;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            transform: translateX(0);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            overflow: hidden;
            box-shadow: 2px 0 20px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar.collapsed {
            width: 80px;
            background: linear-gradient(135deg, var(--teal-500) 0%, var(--teal-600) 100%);
        }
        
        .sidebar.hidden {
            transform: translateX(-100%);
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--navy-600);
            display: flex;
            align-items: center;
            gap: 12px;
            white-space: nowrap;
            min-height: 72px;
        }
        
        .sidebar.collapsed .sidebar-header {
            padding: 20px 15px;
            justify-content: center;
        }
        
        .sidebar-logo {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            flex-shrink: 0;
        }
        
        .sidebar-title {
            font-size: 18px;
            font-weight: 600;
            opacity: 1;
            transition: opacity 0.3s ease;
        }
        
        .sidebar.collapsed .sidebar-title {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: var(--slate-200);
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
            white-space: nowrap;
            position: relative;
        }
        
        .sidebar.collapsed .menu-item {
            padding: 12px 15px;
            justify-content: center;
        }
        
        .menu-item:hover, .menu-item.active {
            background: var(--navy-600);
            color: white;
            border-left-color: var(--teal-500);
        }
        
        .menu-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: var(--slate-200);
        }

        .menu-icon svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .menu-item:hover .menu-icon,
        .menu-item.active .menu-icon {
            color: var(--teal-300);
        }
        
        .menu-text {
            opacity: 1;
            transition: opacity 0.3s ease;
        }
        
        .sidebar.collapsed .menu-text {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }
        
        /* Tooltip for collapsed sidebar */
        .menu-tooltip {
            position: absolute;
            left: 70px;
            top: 50%;
            transform: translateY(-50%);
            background: var(--navy-600);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            z-index: 1001;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        
        .menu-tooltip::before {
            content: '';
            position: absolute;
            left: -4px;
            top: 50%;
            transform: translateY(-50%);
            border: 4px solid transparent;
            border-right-color: var(--navy-600);
        }
        
        .sidebar.collapsed .menu-item:hover .menu-tooltip {
            opacity: 1;
            visibility: visible;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 100vh;
            background: var(--navy-900);
        }
        
        .main-content.collapsed {
            margin-left: 80px;
        }
        
        .main-content.expanded {
            margin-left: 0;
        }
        
        /* Top Header - SAMA PERSIS */
        .top-header {
            background: rgba(255,255,255,.05);
            border-bottom: 1px solid rgba(255,255,255,.08);
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 4500;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .hamburger-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .hamburger-btn:hover {
            background: rgba(255,255,255,.06);
            transform: scale(1.05);
        }
        
        .hamburger-btn:hover .hamburger span {
            background: var(--teal-500);
        }
        
        .hamburger {
            width: 20px;
            height: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.3s ease;
        }
        
        .hamburger span {
            width: 100%;
            height: 2px;
            background: var(--slate-100);
            border-radius: 1px;
            transition: all 0.3s ease;
        }
        
        .hamburger.active span {
            background: var(--teal-500);
        }
        
        .hamburger.active span:nth-child(1) {
            transform: rotate(45deg) translate(6px, 6px);
        }
        
        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }
        
        .hamburger.active span:nth-child(3) {
            transform: rotate(-45deg) translate(6px, -6px);
        }
        
        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--slate-100);
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        /* Profile Dropdown - SAMA PERSIS */
        .profile-dropdown {
            position: relative;
            z-index: 2500;
        }
        
        .profile-btn {
            background: none;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border-radius: 50%;
            transition: background 0.2s;
        }
        
        .profile-btn:hover {
            background: rgba(255,255,255,.06);
        }
        
        .profile-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--teal-500), var(--teal-600));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }
        
        .profile-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: rgba(15,25,40,.85);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 12px;
            box-shadow: 0 10px 32px -4px rgba(0,0,0,.55), 0 0 0 1px rgba(255,255,255,.05);
            backdrop-filter: blur(16px) saturate(140%);
            min-width: 280px;
            z-index: 6000;
            margin-top: 8px;
            display: none;
        }
        
        .profile-menu.show {
            display: block;
        }
        
        /* Mobile specific styles for profile menu */
        @media (max-width: 768px) {
            .profile-menu {
                right: -10px;
                min-width: 260px;
                max-width: calc(100vw - 32px);
                border-radius: 8px;
            }
        }
        
        @media (max-width: 480px) {
            .profile-menu {
                right: -15px;
                min-width: 240px;
                max-width: calc(100vw - 24px);
            }
            
            .profile-menu-header {
                padding: 12px 16px;
            }
            
            .profile-details h4 {
                font-size: 13px;
            }
            
            .profile-details p {
                font-size: 11px;
            }
            
            .balance-info {
                font-size: 11px;
                padding: 6px 10px;
            }
            
            .profile-menu-item {
                padding: 8px 10px;
                font-size: 12px;
            }
        }
        
        .profile-menu-header {
            padding: 16px 20px;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }
        
        .profile-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .profile-details h4 {
            font-weight: 600;
            font-size: 14px;
            color: var(--slate-100);
            margin: 0;
        }
        
        .profile-details p {
            font-size: 12px;
            color: var(--slate-300);
            margin: 0;
        }
        
        .balance-info {
            margin-top: 12px;
            padding: 8px 12px;
            background: rgba(20,184,166,.12);
            border: 1px solid rgba(20,184,166,.25);
            border-radius: 8px;
            font-size: 12px;
            color: var(--teal-300);
        }
        
        .balance-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
            color: var(--teal-300);
            margin-right: 4px;
        }

        .balance-icon svg {
            width: 14px;
            height: 14px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        
        .profile-menu-items {
            padding: 8px;
        }
        
        .profile-menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            color: var(--slate-200);
            font-size: 13px;
            text-decoration: none;
            transition: background 0.2s;
        }
        
        .profile-menu-item:hover {
            background: rgba(255,255,255,.06);
        }
        
        .profile-menu-item.danger {
            color: #f87171;
        }
        
        .profile-menu-item.danger:hover {
            background: rgba(220,38,38,.12);
        }
        
        .profile-menu-icon {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        
        /* Content Area */
        .content-area {
            padding: 24px;
            max-width: 1800px; /* Increased from 1200px */
            margin: 0 auto;
            width: 100%;
            box-sizing: border-box;
        }
        
        /* TRANSACTIONS SPECIFIC STYLES */
        .transactions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .transactions-title {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--slate-100);
        }
        
        .transactions-title h2 {
            font-size: 28px;
            font-weight: 600;
            margin: 0;
        }
        
        .transactions-icon {
            width: 32px;
            height: 32px;
            padding: 6px;
            background: linear-gradient(135deg, var(--teal-500), var(--teal-600));
            border-radius: 8px;
            color: white;
        }
        
        .transactions-icon svg {
            width: 100%;
            height: 100%;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        
        .transactions-stats {
            display: flex;
            gap: 16px;
            align-items: center;
            font-size: 14px;
            color: var(--slate-300);
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            background: rgba(255,255,255,.05);
            border-radius: 6px;
            border: 1px solid rgba(255,255,255,.08);
        }
        
        .stat-number {
            font-weight: 600;
            color: var(--teal-300);
        }
        
        /* Filter Form Styles */
        .filter-section {
            background: rgba(255,255,255,.03);
            border: 1px solid rgba(255,255,255,.06);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            backdrop-filter: blur(8px);
        }
        
        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .filter-title {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--slate-100);
            font-size: 16px;
            font-weight: 600;
        }
        
        .filter-icon {
            width: 20px;
            height: 20px;
            color: var(--teal-400);
        }
        
        .filter-icon svg {
            width: 100%;
            height: 100%;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        
        .filter-toggle {
            background: none;
            border: none;
            color: var(--slate-300);
            cursor: pointer;
            padding: 6px;
            border-radius: 4px;
            transition: all 0.2s;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .filter-toggle:hover {
            background: rgba(255,255,255,.05);
            color: var(--teal-300);
        }
        
        .filter-toggle svg {
            width: 14px;
            height: 14px;
            transition: transform 0.2s;
        }
        
        .filter-toggle.collapsed svg {
            transform: rotate(-90deg);
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            transition: all 0.3s ease;
        }
        
        .filter-form.collapsed {
            display: none;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .filter-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--slate-300);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-input,
        .filter-select {
            background: rgba(15,41,66,.6);
            border: 1px solid rgba(255,255,255,.14);
            border-radius: 6px;
            padding: 10px 12px;
            color: var(--slate-100);
            font-size: 14px;
            transition: all 0.2s;
            width: 100%;
            box-sizing: border-box;
        }
        
        .filter-input:focus,
        .filter-select:focus {
            outline: none;
            border-color: var(--teal-500);
            background: rgba(15,41,66,.75);
            box-shadow: 0 0 0 3px rgba(20,184,166,.1);
        }
        
        .filter-input:hover,
        .filter-select:hover {
            border-color: var(--teal-500);
        }
        
        .filter-input::placeholder {
            color: var(--slate-400);
        }
        
        .filter-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 8px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 32px;
        }
        
        .filter-select option {
            background: var(--navy-800);
            color: var(--slate-100);
        }
        
        .filter-search-group {
            display: flex;
            gap: 8px;
            align-items: end;
        }
        
        .filter-search-type {
            min-width: 100px;
            flex-shrink: 0;
        }
        
        .filter-search-input {
            flex: 1;
        }
        
        .filter-date-group {
            display: flex;
            gap: 8px;
            align-items: end;
        }
        
        .filter-date-input {
            flex: 1;
        }
        
        .filter-actions {
            display: flex;
            gap: 8px;
            align-items: end;
            justify-content: flex-end;
        }
        
        .filter-btn {
            padding: 10px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        
        .filter-btn-primary {
            background: linear-gradient(135deg, var(--teal-500), var(--teal-600));
            color: white;
        }
        
        .filter-btn-primary:hover {
            background: linear-gradient(135deg, var(--teal-600), var(--teal-700));
            transform: translateY(-1px);
        }
        
        .filter-btn-secondary {
            background: rgba(255,255,255,.05);
            color: var(--slate-300);
            border: 1px solid rgba(255,255,255,.1);
        }
        
        .filter-btn-secondary:hover {
            background: rgba(255,255,255,.08);
            color: var(--slate-100);
        }
        
        .filter-btn svg {
            width: 14px;
            height: 14px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        
        .active-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid rgba(255,255,255,.05);
        }
        
        .filter-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(20,184,166,.15);
            color: var(--teal-300);
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            border: 1px solid rgba(20,184,166,.25);
        }
        
        .filter-tag-remove {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            padding: 0;
            margin-left: 2px;
            border-radius: 50%;
            width: 14px;
            height: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        
        .filter-tag-remove:hover {
            background: rgba(20,184,166,.2);
        }
        
        .filter-tag-remove svg {
            width: 10px;
            height: 10px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
        }
        
        /* Filter responsive */
        @media (max-width: 768px) {
            .filter-section {
                padding: 16px;
                margin-bottom: 20px;
                border-radius: 8px;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .filter-search-group,
            .filter-date-group {
                flex-direction: column;
                align-items: stretch;
                gap: 6px;
            }
            
            .filter-search-type,
            .filter-search-input,
            .filter-date-input {
                flex: none;
            }
            
            .filter-actions {
                justify-content: stretch;
                margin-top: 8px;
                grid-column: 1;
            }
            
            .filter-btn {
                flex: 1;
                justify-content: center;
            }
            
            .filter-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .filter-toggle {
                align-self: flex-end;
            }
        }
        
        @media (max-width: 480px) {
            .filter-section {
                padding: 12px;
                margin-bottom: 16px;
            }
            
            .filter-header {
                margin-bottom: 12px;
            }
            
            .filter-title {
                font-size: 14px;
            }
            
            .filter-input,
            .filter-select {
                padding: 8px 10px;
                font-size: 13px;
            }
            
            .filter-btn {
                padding: 8px 12px;
                font-size: 12px;
            }
            
            .active-filters {
                margin-top: 8px;
                padding-top: 8px;
                gap: 6px;
            }
            
            .filter-tag {
                font-size: 10px;
                padding: 3px 6px;
            }
        }
        
        @media (max-width: 360px) {
            .filter-section {
                padding: 10px;
            }
            
            .filter-form {
                gap: 10px;
            }
            
            .filter-input,
            .filter-select {
                padding: 6px 8px;
                font-size: 12px;
            }
            
            .filter-btn {
                padding: 6px 10px;
                font-size: 11px;
            }
            
            .filter-actions {
                flex-direction: column;
                gap: 6px;
            }
        }
        
        .transactions-container {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }
        
        .transactions-table-wrapper {
            overflow-x: auto;
        }
        
        .transactions-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .transactions-table th,
        .transactions-table td {
            padding: 16px 24px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .transactions-table th {
            background: rgba(255, 255, 255, 0.03);
            color: var(--slate-300);
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .transactions-table tbody tr {
            transition: all 0.2s ease;
        }
        
        .transactions-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.03);
        }
        
        .order-id {
            font-family: 'Courier New', monospace; /* Monospace untuk ID */
            font-weight: 600;
            color: var(--teal-400);
            font-size: 13px;
            letter-spacing: 0.5px;
        }
        
        .service-name {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-weight: 500;
            max-width: 100%;
            color: var(--slate-50); /* Brighter text */
            line-height: 1.4;
            font-size: 14px;
        }
        
        .order-link {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: var(--slate-400);
            font-size: 12px;
            max-width: 100%;
            font-family: 'Courier New', monospace;
            background: rgba(255,255,255,.02);
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid rgba(255,255,255,.05);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border: 1px solid transparent;
            min-width: 80px;
            text-align: center;
            white-space: nowrap;
            box-sizing: border-box;
        }
        
        .status-pending {
            background: rgba(251,191,36,.15);
            color: #fbbf24;
            border: 1px solid rgba(251,191,36,.3);
        }
        
        .status-processing {
            background: rgba(59,130,246,.15);
            color: #3b82f6;
            border: 1px solid rgba(59,130,246,.3);
        }
        
        .status-completed {
            background: rgba(34,197,94,.15);
            color: #22c55e;
            border: 1px solid rgba(34,197,94,.3);
        }
        
        .status-failed {
            background: rgba(239,68,68,.15);
            color: #ef4444;
            border: 1px solid rgba(239,68,68,.3);
        }
        
        .status-partial {
            background: rgba(168,85,247,.15);
            color: #a855f7;
            border: 1px solid rgba(168,85,247,.3);
        }
        
        .status-cancelled {
            background: rgba(156,163,175,.15);
            color: #9ca3af;
            border: 1px solid rgba(156,163,175,.3);
        }
        
        .price-cell {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            font-weight: 600;
            color: var(--teal-300);
            font-size: 14px;
            text-align: center;
            letter-spacing: 0.3px;
            min-width: 100px;
            box-sizing: border-box;
        }
        
        .quantity-cell {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            font-weight: 600;
            color: var(--slate-100);
            font-size: 14px;
            text-align: center;
            letter-spacing: 0.3px;
        }
        
        /* Fixed column widths untuk tampilan yang rapi dan profesional */
        .transactions-table th:nth-child(1) { width: 12%; text-align: left; }    /* ID Pesanan */
        .transactions-table th:nth-child(2) { width: 28%; text-align: left; }    /* Layanan */
        .transactions-table th:nth-child(3) { width: 22%; text-align: left; }    /* Target */
        .transactions-table th:nth-child(4) { width: 10%; text-align: center; }  /* Jumlah */
        .transactions-table th:nth-child(5) { width: 12%; text-align: center; }  /* Harga */
        .transactions-table th:nth-child(6) { width: 12%; text-align: center; }  /* Status */
        .transactions-table th:nth-child(7) { width: 15%; text-align: left; }    /* Tanggal */
        .transactions-table th:nth-child(8) { width: 9%; text-align: center; }   /* Aksi */
        
        .transactions-table td:nth-child(1) { text-align: left; }
        .transactions-table td:nth-child(2) { text-align: left; }
        .transactions-table td:nth-child(3) { text-align: left; }
        .transactions-table td:nth-child(4) { text-align: center; }
        .transactions-table td:nth-child(5) { text-align: center; }
        .transactions-table td:nth-child(6) { text-align: center; }
        .transactions-table td:nth-child(7) { text-align: left; }
        .transactions-table td:nth-child(8) { text-align: center; }
        
        .price-cell {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            font-weight: 600;
            color: var(--teal-300);
            font-size: 14px;
            text-align: center;
            letter-spacing: 0.3px;
            min-width: 100px;
            box-sizing: border-box;
        }
        
        .quantity-cell {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            font-weight: 600;
            color: var(--slate-100);
            font-size: 14px;
            text-align: center;
            letter-spacing: 0.3px;
        }
        
        .date-cell {
            color: var(--slate-300);
            font-size: 13px;
            font-weight: 500;
            letter-spacing: 0.2px;
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            white-space: nowrap;
        }
        
        .action-cell {
            text-align: center;
            width: 80px;
            min-width: 80px;
            padding: 20px 16px !important;
        }
        
        .btn-detail {
            background: var(--teal-500);
            border: none;
            border-radius: 4px; /* Square-ish design */
            padding: 8px;
            cursor: pointer;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            flex-shrink: 0;
            /* Removed all animations and transitions */
        }
        
        .btn-detail:hover {
            background: var(--teal-600);
            /* No transform or shadow animations */
        }
        
        .btn-detail svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        
        /* Modal Detail Popup */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 15000; /* Higher than sidebar z-index */
            display: none;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
            padding: 20px;
            box-sizing: border-box;
            overflow-y: auto;
        }
        
        .modal-overlay.show {
            display: flex;
        }
        
        .modal-content {
            background: rgba(15, 25, 40, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 0;
            max-width: 600px;
            width: 100%;
            max-height: 95vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(16px);
            animation: modalSlideIn 0.3s ease-out;
            margin: auto;
            position: relative;
            display: flex;
            flex-direction: column;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255, 255, 255, 0.02);
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--slate-100);
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            color: var(--slate-400);
            transition: all 0.2s;
        }
        
        .modal-close:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--slate-200);
        }
        
        .modal-close svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        
        .modal-body {
            padding: 24px;
            flex: 1;
            overflow-y: auto;
            min-height: 0;
        }
        
        /* Modal responsive */
        @media (max-width: 768px) {
            .modal-overlay {
                padding: 16px;
                align-items: flex-start;
                justify-content: center;
                padding-top: 5vh;
            }
            
            .modal-content {
                max-width: none;
                width: calc(100% - 32px);
                max-height: 90vh;
                border-radius: 12px;
                margin: 0;
            }
            
            .modal-header {
                padding: 16px 20px;
            }
            
            .modal-title {
                font-size: 18px;
            }
            
            .modal-body {
                padding: 20px;
            }
            
            .detail-item {
                gap: 4px;
            }
            
            .detail-label {
                font-size: 12px;
            }
            
            .detail-value {
                font-size: 14px;
            }
            
            .copy-btn {
                padding: 4px 8px;
                font-size: 11px;
                margin-left: 6px;
            }
        }
        
        @media (max-width: 480px) {
            .modal-overlay {
                padding: 12px;
                align-items: flex-start;
                justify-content: center;
                padding-top: 3vh;
            }
            
            .modal-content {
                max-height: 92vh;
                border-radius: 10px;
                width: calc(100% - 24px);
                margin: 0;
            }
            
            .modal-header {
                padding: 12px 16px;
            }
            
            .modal-title {
                font-size: 16px;
            }
            
            .modal-body {
                padding: 16px;
            }
            
            .detail-item {
                gap: 2px;
            }
            
            .detail-label {
                font-size: 11px;
            }
            
            .detail-value {
                font-size: 13px;
                word-break: break-word;
                flex-wrap: wrap;
                gap: 6px;
            }
            
            .detail-value.highlight {
                font-size: 12px;
            }
            
            .copy-btn {
                padding: 5px 6px;
                min-width: 28px;
                height: 28px;
            }
            
            .copy-btn svg {
                width: 14px;
                height: 14px;
            }
            
            .detail-status {
                padding: 4px 8px;
                font-size: 10px;
            }
        }
        
        @media (max-width: 360px) {
            .modal-overlay {
                padding: 12px;
                align-items: center;
                justify-content: center;
            }
            
            .modal-content {
                max-height: 75vh;
                width: calc(100% - 24px);
                border-radius: 8px;
                margin: auto;
            }
            
            .modal-header {
                padding: 10px 12px;
            }
            
            .modal-title {
                font-size: 14px;
            }
            
            .modal-body {
                padding: 12px;
                max-height: calc(80vh - 60px);
            }
            
            .detail-label {
                font-size: 10px;
            }
            
            .detail-value {
                font-size: 12px;
            }
            
            .detail-value.highlight {
                font-size: 11px;
            }
            
            .copy-btn {
                padding: 4px 5px;
                min-width: 26px;
                height: 26px;
            }
            
            .copy-btn svg {
                width: 12px;
                height: 12px;
            }
        }
        
        .detail-grid {
            display: grid;
            gap: 20px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .detail-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--slate-400);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-value {
            font-size: 15px;
            color: var(--slate-100);
            font-weight: 500;
            word-break: break-all;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .detail-value.highlight {
            color: var(--teal-300);
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }
        
        .detail-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            width: fit-content;
        }
        
        .copy-btn {
            background: rgba(20, 184, 166, 0.1);
            border: 1px solid rgba(20, 184, 166, 0.2);
            border-radius: 6px;
            padding: 6px 8px;
            color: var(--teal-400);
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            min-width: 32px;
            height: 32px;
        }
        
        .copy-btn:hover {
            background: rgba(20, 184, 166, 0.2);
            border-color: rgba(20, 184, 166, 0.4);
            color: var(--teal-300);
            transform: translateY(-1px);
        }
        
        .copy-btn:active {
            transform: translateY(0);
        }
        
        .copy-btn svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
        }
        
        /* Toast animations */
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideOutRight {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }
        
        .progress-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .progress-bar {
            width: 80px;
            height: 4px;
            background: rgba(255,255,255,.1);
            border-radius: 2px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--teal-500), var(--teal-400));
            border-radius: 2px;
            transition: width 0.3s ease;
        }
        
        .progress-text {
            font-size: 11px;
            color: var(--slate-400);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--slate-400);
        }
        
        .empty-state-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            opacity: 0.5;
        }
        
        .empty-state-icon svg {
            width: 100%;
            height: 100%;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.5;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        
        .empty-state h3 {
            font-size: 18px;
            font-weight: 600;
            margin: 0 0 8px 0;
            color: var(--slate-300);
        }
        
        .empty-state p {
            font-size: 14px;
            margin: 0 0 20px 0;
            max-width: 300px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.5;
        }
        
        .btn-create-order {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--teal-500), var(--teal-600));
            color: white;
            padding: 12px 20px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        
        .btn-create-order::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
            transition: left 0.6s ease;
        }
        
        .btn-create-order:hover {
            background: linear-gradient(135deg, var(--teal-600), var(--teal-700));
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(20, 184, 166, 0.3);
        }
        
        .btn-create-order:hover::before {
            left: 100%;
        }
        
        .btn-create-order:active {
            transform: translateY(0);
            transition: transform 0.1s ease;
        }
        
        .btn-create-order svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,.06);
            background: rgba(255,255,255,.02);
        }
        
        .pagination-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border: 1px solid rgba(255,255,255,.1);
            background: rgba(255,255,255,.03);
            color: var(--slate-200);
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s;
            min-width: 40px;
        }
        
        .pagination-btn:hover:not(.disabled) {
            background: rgba(255,255,255,.08);
            border-color: var(--teal-500);
            color: var(--teal-300);
        }
        
        .pagination-btn.active {
            background: linear-gradient(135deg, var(--teal-500), var(--teal-600));
            border-color: var(--teal-500);
            color: white;
            font-weight: 600;
        }
        
        .pagination-btn.disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        
        .pagination-info {
            color: var(--slate-300);
            font-size: 13px;
            margin: 0 16px;
        }
        
        /* Mobile Responsive - ENHANCED FOR ALL DEVICES */
        @media (max-width: 1200px) {
            .content-area {
                padding: 20px;
            }
            
            .transactions-header {
                margin-bottom: 20px;
            }
            
            .transactions-title h2 {
                font-size: 24px;
            }
            
            .transactions-table {
                font-size: 12px;
                min-width: 800px;
            }
            
            .transactions-table th,
            .transactions-table td {
                padding: 16px 12px;
            }
            
            /* Sesuaikan lebar kolom untuk laptop kecil */
            .transactions-table th:nth-child(1) { width: 12%; }  /* ID */
            .transactions-table th:nth-child(2) { width: 30%; }  /* Layanan */
            .transactions-table th:nth-child(3) { width: 20%; }  /* Target */
            .transactions-table th:nth-child(4) { width: 9%; }   /* Jumlah */
            .transactions-table th:nth-child(5) { width: 12%; }  /* Harga */
            .transactions-table th:nth-child(6) { width: 12%; }  /* Status */
            .transactions-table th:nth-child(7) { width: 14%; }  /* Tanggal */
            .transactions-table th:nth-child(8) { width: 9%; }   /* Aksi */
        }
        
        @media (max-width: 992px) {
            .content-area {
                padding: 18px;
            }
            
            .top-header {
                padding: 14px 18px;
            }
            
            .page-title {
                font-size: 20px;
            }
            
            .transactions-title h2 {
                font-size: 22px;
            }
            
            .transactions-stats {
                flex-wrap: wrap;
                gap: 12px;
            }
            
            .stat-item {
                padding: 6px 10px;
                font-size: 13px;
            }
            
            .transactions-table {
                font-size: 11px;
                min-width: 700px;
            }
            
            .transactions-table th,
            .transactions-table td {
                padding: 14px 10px;
            }
            
            /* Sesuaikan lebar kolom untuk tablet */
            .transactions-table th:nth-child(1) { width: 13%; }  /* ID */
            .transactions-table th:nth-child(2) { width: 28%; }  /* Layanan */
            .transactions-table th:nth-child(3) { width: 20%; }  /* Target */
            .transactions-table th:nth-child(4) { width: 9%; }   /* Jumlah */
            .transactions-table th:nth-child(5) { width: 12%; }  /* Harga */
            .transactions-table th:nth-child(6) { width: 12%; }  /* Status */
            .transactions-table th:nth-child(7) { width: 14%; }  /* Tanggal */
            .transactions-table th:nth-child(8) { width: 10%; }  /* Aksi */
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px; /* Maintain width for smooth animation */
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .top-header {
                padding: 12px 16px;
                position: sticky;
                top: 0;
                z-index: 9999;
            }
            
            .page-title {
                font-size: 18px;
            }
            
            .content-area {
                padding: 16px;
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
            }
            
            .transactions-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
                margin-bottom: 16px;
            }
            
            .transactions-title {
                width: 100%;
            }
            
            .transactions-title h2 {
                font-size: 20px;
            }
            
            .transactions-stats {
                width: 100%;
                justify-content: flex-start;
                flex-wrap: wrap;
                gap: 8px;
            }
            
            .stat-item {
                flex: 1;
                min-width: 120px;
                justify-content: center;
                padding: 8px 12px;
                font-size: 12px;
            }
            
            .transactions-container {
                border-radius: 8px;
                margin: 0;
                width: 100%;
                overflow: hidden;
            }
            
            .transactions-table-wrapper {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                border-radius: 8px;
                width: 100%;
                max-width: 100%;
            }
            
            .transactions-table {
                font-size: 13px;
                min-width: 700px;
                width: 700px; /* Fixed width for consistent scrolling */
            }
            
            .transactions-table th,
            .transactions-table td {
                padding: 14px 10px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            
            .transactions-table th {
                font-size: 11px;
                letter-spacing: 0.5px;
                padding: 16px 10px;
                font-weight: 600;
            }
            
            /* Sesuaikan lebar kolom untuk mobile */
            .transactions-table th:nth-child(1) { width: 14%; text-align: left; }   /* ID */
            .transactions-table th:nth-child(2) { width: 26%; text-align: left; }   /* Layanan */
            .transactions-table th:nth-child(3) { width: 18%; text-align: left; }   /* Target */
            .transactions-table th:nth-child(4) { width: 9%; text-align: center; }  /* Jumlah */
            .transactions-table th:nth-child(5) { width: 12%; text-align: right; }  /* Harga */
            .transactions-table th:nth-child(6) { width: 12%; text-align: center; } /* Status */
            .transactions-table th:nth-child(7) { width: 14%; text-align: left; }   /* Tanggal */
            .transactions-table th:nth-child(8) { width: 11%; text-align: center; } /* Aksi */
            
            .transactions-table td:nth-child(1) { text-align: left; }
            .transactions-table td:nth-child(2) { text-align: left; }
            .transactions-table td:nth-child(3) { text-align: left; }
            .transactions-table td:nth-child(4) { text-align: center; }
            .transactions-table td:nth-child(5) { text-align: center; }
            .transactions-table td:nth-child(6) { text-align: center; }
            .transactions-table td:nth-child(7) { text-align: left; }
            .transactions-table td:nth-child(8) { text-align: center; }
            
            .order-id {
                font-size: 12px;
                line-height: 1.3;
                font-weight: 500;
            }
            
            .service-name {
                font-size: 13px;
                max-width: 150px;
                line-height: 1.4;
            }
            
            .order-link {
                font-size: 12px;
                max-width: 120px;
                padding: 3px 8px;
            }
            
            .status-badge {
                padding: 6px 10px;
                font-size: 11px;
                letter-spacing: 0.5px;
                min-width: 70px;
                font-weight: 500;
            }
            
            .price-cell {
                font-size: 12px;
                text-align: center;
                font-weight: 500;
            }
            
            .quantity-cell {
                font-size: 12px;
                font-weight: 500;
            }
            
            .date-cell {
                font-size: 11px;
                line-height: 1.3;
            }
            
            .action-cell {
                width: 70px;
                min-width: 70px;
                padding: 14px 10px !important;
            }
            
            .btn-detail {
                width: 36px;
                height: 36px;
                padding: 8px;
            }
            
            .btn-detail svg {
                width: 18px;
                height: 18px;
            }
            
            .pagination {
                flex-wrap: wrap;
                gap: 6px;
                padding: 18px;
            }
            
            .pagination-btn {
                padding: 8px 12px;
                font-size: 14px;
                min-width: 38px;
                min-height: 38px;
            }
            
            .pagination-info {
                width: 100%;
                text-align: center;
                margin: 10px 0 0 0;
                font-size: 13px;
            }
            
            .empty-state {
                padding: 50px 20px;
            }
            
            .empty-state h3 {
                font-size: 18px;
            }
            
            .empty-state p {
                font-size: 14px;
                max-width: 300px;
            }
        }
        
        @media (max-width: 480px) {
            .top-header {
                padding: 10px 12px;
            }
            
            .page-title {
                font-size: 17px;
            }
            
            .content-area {
                padding: 12px;
            }
            
            .transactions-title h2 {
                font-size: 19px;
            }
            
            .transactions-icon {
                width: 30px;
                height: 30px;
            }
            
            .stat-item {
                font-size: 12px;
                padding: 8px 10px;
                min-width: 110px;
            }
            
            .transactions-table {
                font-size: 12px;
                min-width: 650px;
                width: 650px;
            }
            
            .transactions-table th,
            .transactions-table td {
                padding: 12px 8px;
            }
            
            .transactions-table th {
                font-size: 10px;
                padding: 14px 8px;
                font-weight: 600;
            }
            
            /* Untuk layar sangat kecil, sembunyikan kolom target */
            .transactions-table th:nth-child(3), /* Target */
            .transactions-table td:nth-child(3) {
                display: none;
            }
            
            /* Adjust remaining columns untuk very small screen */
            .transactions-table th:nth-child(1) { width: 16%; text-align: left; }   /* ID */
            .transactions-table th:nth-child(2) { width: 32%; text-align: left; }   /* Layanan */
            .transactions-table th:nth-child(4) { width: 11%; text-align: center; } /* Jumlah */
            .transactions-table th:nth-child(5) { width: 14%; text-align: center; } /* Harga */
            .transactions-table th:nth-child(6) { width: 15%; text-align: center; } /* Status */
            .transactions-table th:nth-child(7) { width: 16%; text-align: left; }   /* Tanggal */
            .transactions-table th:nth-child(8) { width: 12%; text-align: center; } /* Aksi */
            
            .transactions-table td:nth-child(1) { text-align: left; }
            .transactions-table td:nth-child(2) { text-align: left; }
            .transactions-table td:nth-child(4) { text-align: center; }
            .transactions-table td:nth-child(5) { text-align: center; }
            .transactions-table td:nth-child(6) { text-align: center; }
            .transactions-table td:nth-child(7) { text-align: left; }
            .transactions-table td:nth-child(8) { text-align: center; }
            
            .order-id {
                font-size: 11px;
                font-weight: 500;
            }
            
            .service-name {
                font-size: 12px;
                max-width: 130px;
                line-height: 1.3;
            }
            
            .status-badge {
                padding: 5px 8px;
                font-size: 10px;
                letter-spacing: 0.4px;
                min-width: 65px;
                font-weight: 500;
            }
            
            .price-cell {
                font-size: 11px;
                text-align: center;
                font-weight: 500;
            }
            
            .quantity-cell {
                font-size: 11px;
                font-weight: 500;
            }
            
            .date-cell {
                font-size: 10px;
                line-height: 1.3;
            }
            
            .action-cell {
                width: 65px;
                min-width: 65px;
                padding: 12px 8px !important;
            }
            
            .btn-detail {
                width: 34px;
                height: 34px;
                padding: 7px;
            }
            
            .btn-detail svg {
                width: 17px;
                height: 17px;
            }
            
            .pagination {
                padding: 16px;
            }
            
            .pagination-btn {
                padding: 7px 10px;
                font-size: 13px;
                min-width: 36px;
                min-height: 36px;
            }
            
            .pagination-info {
                font-size: 12px;
            }
            
            .empty-state {
                padding: 40px 16px;
            }
            
            .empty-state-icon {
                width: 56px;
                height: 56px;
            }
            
            .empty-state h3 {
                font-size: 17px;
            }
            
            .empty-state p {
                font-size: 14px;
                max-width: 280px;
            }
            
            .btn-create-order {
                padding: 10px 16px;
                font-size: 13px;
                transform: none;
            }
            
            .btn-create-order:hover {
                transform: translateY(-1px);
            }
            
            .btn-create-order:active {
                transform: translateY(0);
            }
        }
        
        @media (max-width: 360px) {
            .content-area {
                padding: 10px;
            }
            
            .top-header {
                padding: 10px 12px;
            }
            
            .page-title {
                font-size: 16px;
            }
            
            .transactions-title h2 {
                font-size: 18px;
            }
            
            .transactions-table {
                font-size: 11px;
                min-width: 600px;
                width: 600px;
            }
            
            .transactions-table th,
            .transactions-table td {
                padding: 12px 7px;
            }
            
            .transactions-table th {
                font-size: 10px;
                padding: 14px 7px;
                font-weight: 600;
            }
            
            .stat-item {
                font-size: 11px;
                padding: 7px 9px;
                min-width: 100px;
            }
            
            .order-id {
                font-size: 10px;
                font-weight: 500;
            }
            
            .service-name {
                font-size: 11px;
                max-width: 110px;
                line-height: 1.3;
            }
            
            .status-badge {
                padding: 4px 7px;
                font-size: 9px;
                min-width: 60px;
                font-weight: 500;
            }
            
            .price-cell, .quantity-cell, .date-cell {
                font-size: 10px;
                font-weight: 500;
            }
            
            .price-cell {
                text-align: center;
            }
            
            .action-cell {
                width: 60px;
                min-width: 60px;
                padding: 12px 7px !important;
            }
            
            .btn-detail {
                width: 32px;
                height: 32px;
                padding: 6px;
            }
            
            .btn-detail svg {
                width: 16px;
                height: 16px;
            }
            
            .pagination-btn {
                padding: 6px 9px;
                font-size: 12px;
                min-width: 34px;
                min-height: 34px;
            }
            
            .pagination-info {
                font-size: 11px;
            }
            
            .empty-state {
                padding: 35px 14px;
            }
            
            .empty-state h3 {
                font-size: 16px;
            }
            
            .empty-state p {
                font-size: 13px;
                max-width: 260px;
            }
        }
        
        /* Overlay for mobile */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
            transition: opacity 0.3s ease;
        }
        
        .sidebar-overlay.show {
            display: block;
            opacity: 1;
        }
        
        /* Ensure proper layering and touch handling */
        @media (max-width: 768px) {
            .sidebar-overlay {
                -webkit-tap-highlight-color: transparent;
                user-select: none;
            }
            
            .sidebar {
                -webkit-transform: translateX(-100%);
                transform: translateX(-100%);
                will-change: transform;
            }
            
            .sidebar.show {
                -webkit-transform: translateX(0);
                transform: translateX(0);
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar - SAMA PERSIS DENGAN DASHBOARD.PHP -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="storage/assets/img/logo/logo_trans.png" alt="AcisPedia" class="sidebar-logo">
                <span class="sidebar-title">AcisPedia</span>
            </div>
            
            <nav class="sidebar-menu">
                <a href="dashboard.php" class="menu-item">
                    <div class="menu-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            <polyline points="9,22 9,12 15,12 15,22"/>
                        </svg>
                    </div>
                    <span class="menu-text">Dashboard</span>
                    <div class="menu-tooltip">Dashboard</div>
                </a>
                
                <a href="order.php" class="menu-item">
                    <div class="menu-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                            <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
                        </svg>
                    </div>
                    <span class="menu-text">Buat Pesanan</span>
                    <div class="menu-tooltip">Buat Pesanan</div>
                </a>
                
                <a href="services.php" class="menu-item">
                    <div class="menu-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                            <path d="M2 17l10 5 10-5"/>
                            <path d="M2 12l10 5 10-5"/>
                        </svg>
                    </div>
                    <span class="menu-text">Layanan</span>
                    <div class="menu-tooltip">Layanan</div>
                </a>
                
                <a href="transactions.php" class="menu-item active">
                    <div class="menu-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M9 11H5a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h4v-9zM15 11h4a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-4v-9z"/>
                            <path d="M9 7V2a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v5"/>
                        </svg>
                    </div>
                    <span class="menu-text">Riwayat Pesanan</span>
                    <div class="menu-tooltip">Riwayat Pesanan</div>
                </a>
                
                <a href="deposit.php" class="menu-item">
                    <div class="menu-icon">
                        <svg viewBox="0 0 24 24">
                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                            <line x1="8" y1="21" x2="16" y2="21"/>
                            <line x1="12" y1="17" x2="12" y2="21"/>
                        </svg>
                    </div>
                    <span class="menu-text">Top Up Saldo</span>
                    <div class="menu-tooltip">Top Up Saldo</div>
                </a>
                
                <a href="deposit_history.php" class="menu-item">
                    <div class="menu-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14,2 14,8 20,8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                            <polyline points="10,9 9,9 8,9"/>
                        </svg>
                    </div>
                    <span class="menu-text">Riwayat Deposit</span>
                    <div class="menu-tooltip">Riwayat Deposit</div>
                </a>
        </aside>
        
        <!-- Sidebar Overlay for Mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <!-- Top Header - SAMA PERSIS -->
            <header class="top-header">
                <div class="header-left">
                    <button class="hamburger-btn" id="hamburgerBtn">
                        <div class="hamburger" id="hamburger">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </button>
                    <h1 class="page-title">Riwayat Pesanan</h1>
                </div>
                
                <div class="header-right">
                    <div class="profile-dropdown">
                        <button class="profile-btn" id="profileBtn">
                            <div class="profile-avatar">
                                <?= strtoupper(substr($user['full_name'] ?? $user['username'] ?? 'U', 0, 1)); ?>
                            </div>
                        </button>
                        
                        <div class="profile-menu" id="profileMenu">
                            <div class="profile-menu-header">
                                <div class="profile-info">
                                    <div class="profile-avatar">
                                        <?= strtoupper(substr($user['full_name'] ?? $user['username'] ?? 'U', 0, 1)); ?>
                                    </div>
                                    <div class="profile-details">
                                        <h4><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'Pengguna'); ?></h4>
                                        <p><?= htmlspecialchars($user['email'] ?? 'user@example.com'); ?></p>
                                    </div>
                                </div>
                                <div class="balance-info">
                                    <span class="balance-icon" style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;background:rgba(20,184,166,.18);border:1px solid rgba(20,184,166,.35);border-radius:6px;font-size:10px;font-weight:600;letter-spacing:.5px;color:var(--teal-300);">RP</span>
                                    &nbsp;Saldo: Rp <?= number_format((int)($user['balance'] ?? 0), 0, ',', '.'); ?>
                                </div>
                            </div>
                            
                            <div class="profile-menu-items">
                                <a href="dashboard.php" class="profile-menu-item">
                                    <svg class="profile-menu-icon" viewBox="0 0 24 24">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                        <circle cx="12" cy="7" r="4"/>
                                    </svg>
                                    Profil Saya
                                </a>
                                <a href="deposit.php" class="profile-menu-item">
                                    <svg class="profile-menu-icon" viewBox="0 0 24 24">
                                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                                        <line x1="8" y1="21" x2="16" y2="21"/>
                                        <line x1="12" y1="17" x2="12" y2="21"/>
                                    </svg>
                                    Top Up Saldo
                                </a>
                                <a href="auth/logout.php" class="profile-menu-item danger">
                                    <svg class="profile-menu-icon" viewBox="0 0 24 24">
                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                        <polyline points="16,17 21,12 16,7"/>
                                        <line x1="21" y1="12" x2="9" y2="12"/>
                                    </svg>
                                    Keluar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Content Area - TRANSACTIONS CONTENT -->
            <div class="content-area">
                <!-- Filter Section -->
                <div class="filter-section">
                    <div class="filter-header">
                        <div class="filter-title">
                            <div class="filter-icon">
                                <svg viewBox="0 0 24 24">
                                    <polygon points="22,3 2,3 10,12.46 10,19 14,21 14,12.46"/>
                                </svg>
                            </div>
                            Filter & Pencarian
                        </div>
                        <button type="button" class="filter-toggle" id="filterToggle">
                            <svg viewBox="0 0 24 24">
                                <polyline points="6,9 12,15 18,9"/>
                            </svg>
                            Sembunyikan
                        </button>
                    </div>
                    
                    <form method="GET" class="filter-form" id="filterForm">
                        <!-- Preserve pagination -->
                        <input type="hidden" name="page" value="1">
                        
                        <!-- Status Filter -->
                        <div class="filter-group">
                            <label class="filter-label">Status</label>
                            <select name="status" class="filter-select">
                                <option value="all" <?= $filterStatus === 'all' || empty($filterStatus) ? 'selected' : ''; ?>>Semua Status</option>
                                <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?= $filterStatus === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="success" <?= $filterStatus === 'success' ? 'selected' : ''; ?>>Selesai</option>
                                <option value="failed" <?= $filterStatus === 'failed' ? 'selected' : ''; ?>>Gagal</option>
                                <option value="partial" <?= $filterStatus === 'partial' ? 'selected' : ''; ?>>Partial</option>
                            </select>
                        </div>
                        
                        <!-- Search Filter -->
                        <div class="filter-group">
                            <label class="filter-label">Pencarian</label>
                            <div class="filter-search-group">
                                <div class="filter-search-type">
                                    <select name="search_type" class="filter-select">
                                        <option value="id" <?= $filterSearchType === 'id' ? 'selected' : ''; ?>>ID Pesanan</option>
                                        <option value="target" <?= $filterSearchType === 'target' ? 'selected' : ''; ?>>Target/Link</option>
                                    </select>
                                </div>
                                <div class="filter-search-input">
                                    <input type="text" name="search" value="<?= htmlspecialchars($filterSearch); ?>" 
                                           placeholder="Masukkan kata kunci..." class="filter-input">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Year Filter -->
                        <div class="filter-group">
                            <label class="filter-label">Tahun</label>
                            <div class="filter-date-group">
                                <select name="year" class="filter-select">
                                    <option value="">Semua Tahun</option>
                                    <option value="2025" <?= ($filterYear == '2025') ? 'selected' : ''; ?>>2025</option>
                                    <option value="2026" <?= ($filterYear == '2026') ? 'selected' : ''; ?>>2026</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="filter-actions">
                            <button type="submit" class="filter-btn filter-btn-primary">
                                <svg viewBox="0 0 24 24">
                                    <circle cx="11" cy="11" r="8"/>
                                    <path d="m21 21-4.35-4.35"/>
                                </svg>
                                Filter
                            </button>
                            <button type="button" class="filter-btn filter-btn-secondary" onclick="clearFilters()">
                                <svg viewBox="0 0 24 24">
                                    <path d="M3 6h18"/>
                                    <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/>
                                    <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                                </svg>
                                Reset
                            </button>
                        </div>
                    </form>
                    
                    <!-- Active Filters -->
                    <?php if (!empty($filterStatus) || !empty($filterSearch) || !empty($filterYear)): ?>
                    <div class="active-filters">
                        <?php if (!empty($filterStatus) && $filterStatus !== 'all'): ?>
                        <span class="filter-tag">
                            Status: <?php 
                                $statusLabels = [
                                    'pending' => 'Pending',
                                    'processing' => 'Processing',
                                    'success' => 'Selesai',
                                    'failed' => 'Gagal',
                                    'partial' => 'Partial'
                                ];
                                echo $statusLabels[$filterStatus] ?? ucfirst($filterStatus);
                            ?>
                            <button type="button" class="filter-tag-remove" onclick="removeFilter('status')">
                                <svg viewBox="0 0 24 24">
                                    <line x1="18" y1="6" x2="6" y2="18"/>
                                    <line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                            </button>
                        </span>
                        <?php endif; ?>
                        
                        <?php if (!empty($filterSearch)): ?>
                        <span class="filter-tag">
                            <?= $filterSearchType === 'id' ? 'ID' : 'Target'; ?>: <?= htmlspecialchars($filterSearch); ?>
                            <button type="button" class="filter-tag-remove" onclick="removeFilter('search')">
                                <svg viewBox="0 0 24 24">
                                    <line x1="18" y1="6" x2="6" y2="18"/>
                                    <line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                            </button>
                        </span>
                        <?php endif; ?>
                        
                        <?php if (!empty($filterYear)): ?>
                        <span class="filter-tag">
                            Tahun: <?= htmlspecialchars($filterYear); ?>
                            <button type="button" class="filter-tag-remove" onclick="removeFilter('year')">
                                <svg viewBox="0 0 24 24">
                                    <line x1="18" y1="6" x2="6" y2="18"/>
                                    <line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                            </button>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Transactions Container -->
                <div class="transactions-container">
                    <?php if (empty($orders)): ?>
                    <!-- Empty State -->
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <svg viewBox="0 0 24 24">
                                <path d="M9 11H5a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h4v-9zM15 11h4a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-4v-9z"/>
                                <path d="M9 7V2a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v5"/>
                            </svg>
                        </div>
                        <h3>Belum Ada Pesanan</h3>
                        <p>Anda belum memiliki riwayat pesanan. Mulai buat pesanan pertama Anda untuk layanan SMM Panel.</p>
                        <a href="order.php" class="btn-create-order">
                            <svg viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="8" x2="12" y2="16"/>
                                <line x1="8" y1="12" x2="16" y2="12"/>
                            </svg>
                            Buat Pesanan Sekarang
                        </a>
                    </div>
                    <?php else: ?>
                    <!-- Transactions Table -->
                    <div class="transactions-table-wrapper">
                        <table class="transactions-table">
                            <thead>
                                <tr>
                                    <th>ID Pesanan</th>
                                    <th>Layanan</th>
                                    <th>Target</th>
                                    <th style="text-align: center;">Jumlah</th>
                                    <th style="text-align: center;">Harga</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                    <th style="text-align: center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <div class="order-id">
                                            #<?= htmlspecialchars($order['id'] ?? '???'); ?>
                                            <?php if (!empty($order['provider_order_id']) && $order['provider_order_id'] != $order['id']): ?>
                                            <br><small style="color:var(--slate-400);font-size:10px;">Provider: <?= htmlspecialchars($order['provider_order_id']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="service-name" title="<?= htmlspecialchars($order['service_name'] ?? 'Layanan ID: ' . ($order['service_id'] ?? 'N/A')); ?>">
                                            <?= htmlspecialchars($order['service_name'] ?? 'Layanan ID: ' . ($order['service_id'] ?? 'N/A')); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="order-link" title="<?= htmlspecialchars($order['link'] ?? 'N/A'); ?>">
                                            <?= htmlspecialchars($order['link'] ?? 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="quantity-cell">
                                            <?php 
                                            $quantity = $order['quantity'] ?? 0;
                                            if (is_string($quantity)) {
                                                $quantity = (int)str_replace(',', '', $quantity);
                                            }
                                            $quantity = (int)$quantity;
                                            ?>
                                            <?= number_format($quantity, 0, ',', '.'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="price-cell">
                                            <?php 
                                            // Try different price columns
                                            $price = $order['price'] ?? $order['total_price'] ?? 0;
                                            
                                            // Handle different price formats
                                            if (is_string($price)) {
                                                $price = (float)str_replace([',', '.'], ['', '.'], $price);
                                            }
                                            $price = (float)$price;
                                            ?>
                                            Rp <?= number_format($price, 0, ',', '.'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="display: flex; justify-content: center; align-items: center; height: 100%;">
                                            <?php 
                                            $status = strtolower($order['status'] ?? 'pending');
                                            $statusClass = 'status-pending';
                                            $statusText = 'Menunggu';
                                            
                                            switch ($status) {
                                                case 'pending':
                                                    $statusClass = 'status-pending';
                                                    $statusText = 'Menunggu';
                                                    break;
                                                case 'processing':
                                                case 'inprogress':
                                                case 'in_progress':
                                                    $statusClass = 'status-processing';
                                                    $statusText = 'Diproses';
                                                    break;
                                                case 'completed':
                                                case 'success':
                                                case 'finished':
                                                case 'done':
                                                    $statusClass = 'status-completed';
                                                    $statusText = 'Selesai';
                                                    break;
                                                case 'failed':
                                                case 'error':
                                                    $statusClass = 'status-failed';
                                                    $statusText = 'Gagal';
                                                    break;
                                                case 'partial':
                                                    $statusClass = 'status-partial';
                                                    $statusText = 'Sebagian';
                                                    break;
                                                case 'cancelled':
                                                case 'canceled':
                                                    $statusClass = 'status-cancelled';
                                                    $statusText = 'Dibatalkan';
                                                    break;
                                                default:
                                                    $statusText = ucfirst($status);
                                            }
                                            ?>
                                            <span class="status-badge <?= $statusClass; ?>">
                                                <?= $statusText; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="date-cell">
                                            <?php if (!empty($order['created_at'])): ?>
                                                <?php
                                                // Create DateTime object with UTC timezone
                                                $date = new DateTime($order['created_at'], new DateTimeZone('UTC'));
                                                // Convert to WIB (Asia/Jakarta)
                                                $date->setTimezone(new DateTimeZone('Asia/Jakarta'));
                                                echo $date->format('d/m/Y H:i') . ' WIB';
                                                ?>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-cell">
                                            <button class="btn-detail" 
                                                data-order='<?= htmlspecialchars(json_encode($order), ENT_QUOTES, 'UTF-8'); ?>' 
                                                onclick="showOrderDetailFromButton(this)" 
                                                title="Lihat Detail">
                                                <svg viewBox="0 0 24 24">
                                                    <circle cx="12" cy="12" r="1"/>
                                                    <circle cx="19" cy="12" r="1"/>
                                                    <circle cx="5" cy="12" r="1"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($paginationInfo['total_pages'] > 1): ?>
                    <div class="pagination">
                        <?php if ($paginationInfo['current_page'] > 1): ?>
                        <a href="<?= buildFilterUrl($paginationInfo['current_page'] - 1); ?>" class="pagination-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="15,18 9,12 15,6"></polyline>
                            </svg>
                        </a>
                        <?php else: ?>
                        <span class="pagination-btn disabled">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="15,18 9,12 15,6"></polyline>
                            </svg>
                        </span>
                        <?php endif; ?>
                        
                        <?php
                        $startPage = max(1, $paginationInfo['current_page'] - 2);
                        $endPage = min($paginationInfo['total_pages'], $paginationInfo['current_page'] + 2);
                        
                        if ($startPage > 1): ?>
                        <a href="<?= buildFilterUrl(1); ?>" class="pagination-btn">1</a>
                        <?php if ($startPage > 2): ?>
                        <span class="pagination-btn disabled">...</span>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <a href="<?= buildFilterUrl($i); ?>" class="pagination-btn <?= $i === $paginationInfo['current_page'] ? 'active' : ''; ?>">
                            <?= $i; ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($endPage < $paginationInfo['total_pages']): ?>
                        <?php if ($endPage < $paginationInfo['total_pages'] - 1): ?>
                        <span class="pagination-btn disabled">...</span>
                        <?php endif; ?>
                        <a href="<?= buildFilterUrl($paginationInfo['total_pages']); ?>" class="pagination-btn"><?= $paginationInfo['total_pages']; ?></a>
                        <?php endif; ?>
                        
                        <?php if ($paginationInfo['current_page'] < $paginationInfo['total_pages']): ?>
                        <a href="<?= buildFilterUrl($paginationInfo['current_page'] + 1); ?>" class="pagination-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="9,18 15,12 9,6"></polyline>
                            </svg>
                        </a>
                        <?php else: ?>
                        <span class="pagination-btn disabled">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="9,18 15,12 9,6"></polyline>
                            </svg>
                        </span>
                        <?php endif; ?>
                        
                        <div class="pagination-info">
                            Menampilkan <?= (($paginationInfo['current_page'] - 1) * $paginationInfo['per_page']) + 1; ?>-<?= min($paginationInfo['current_page'] * $paginationInfo['per_page'], $paginationInfo['total']); ?> dari <?= number_format($paginationInfo['total']); ?> pesanan
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Modal Detail Popup -->
                <div class="modal-overlay" id="detailModal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 class="modal-title">Detail Pesanan</h3>
                            <button class="modal-close" onclick="closeOrderDetail()">
                                <svg viewBox="0 0 24 24">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="detail-grid" id="detailContent">
                                <!-- Content will be filled by JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // JAVASCRIPT SAMA PERSIS DENGAN DASHBOARD.PHP
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            initializeSidebar();
            initializeProfile();
            initializeMobileOptimizations();
            initializeFilters();
        });

        // Mobile optimizations
        function initializeMobileOptimizations() {
            // Improve touch scrolling on iOS
            if (window.DeviceMotionEvent) {
                document.addEventListener('touchstart', function() {}, { passive: true });
            }
            
            // Optimize table scrolling
            const tableWrapper = document.querySelector('.transactions-table-wrapper');
            if (tableWrapper) {
                let isScrolling = false;
                
                tableWrapper.addEventListener('scroll', function() {
                    if (!isScrolling) {
                        window.requestAnimationFrame(function() {
                            isScrolling = false;
                        });
                        isScrolling = true;
                    }
                }, { passive: true });
            }
            
            // Handle orientation change
            window.addEventListener('orientationchange', function() {
                setTimeout(function() {
                    // Force redraw after orientation change
                    const mainContent = document.getElementById('mainContent');
                    if (mainContent) {
                        mainContent.style.transform = 'translateZ(0)';
                        setTimeout(() => {
                            mainContent.style.transform = '';
                        }, 10);
                    }
                }, 100);
            });
        }

        // Sidebar functionality - ENHANCED FOR MOBILE
        function initializeSidebar() {
            const hamburgerBtn = document.getElementById('hamburgerBtn');
            const hamburger = document.getElementById('hamburger');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            hamburgerBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                if (window.innerWidth <= 768) {
                    // Mobile: toggle sidebar visibility
                    sidebar.classList.toggle('show');
                    sidebarOverlay.classList.toggle('show');
                    hamburger.classList.toggle('active');
                    
                    // Prevent body scroll when sidebar is open
                    if (sidebar.classList.contains('show')) {
                        document.body.style.overflow = 'hidden';
                    } else {
                        document.body.style.overflow = '';
                    }
                } else {
                    // Desktop: toggle sidebar collapse
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('collapsed');
                    hamburger.classList.toggle('active');
                }
            });

            // Close sidebar when clicking overlay
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
                hamburger.classList.remove('active');
                document.body.style.overflow = '';
            });
            
            // Handle swipe gestures on mobile
            let startX = 0;
            let endX = 0;
            
            document.addEventListener('touchstart', function(e) {
                startX = e.touches[0].clientX;
            }, { passive: true });
            
            document.addEventListener('touchend', function(e) {
                endX = e.changedTouches[0].clientX;
                handleSwipe();
            }, { passive: true });
            
            function handleSwipe() {
                const diffX = endX - startX;
                const minSwipeDistance = 100;
                
                // Swipe right to open sidebar (only if starting from left edge)
                if (diffX > minSwipeDistance && startX < 50 && window.innerWidth <= 768) {
                    if (!sidebar.classList.contains('show')) {
                        sidebar.classList.add('show');
                        sidebarOverlay.classList.add('show');
                        hamburger.classList.add('active');
                        document.body.style.overflow = 'hidden';
                    }
                }
                
                // Swipe left to close sidebar
                if (diffX < -minSwipeDistance && sidebar.classList.contains('show')) {
                    sidebar.classList.remove('show');
                    sidebarOverlay.classList.remove('show');
                    hamburger.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('show');
                    sidebarOverlay.classList.remove('show');
                    mainContent.classList.remove('expanded');
                    document.body.style.overflow = '';
                    
                    if (sidebar.classList.contains('collapsed')) {
                        mainContent.classList.add('collapsed');
                    } else {
                        mainContent.classList.remove('collapsed');
                    }
                } else {
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('collapsed');
                    if (!sidebar.classList.contains('show')) {
                        mainContent.classList.add('expanded');
                    }
                }
            });
            
            // Initialize proper state
            if (window.innerWidth <= 768) {
                mainContent.classList.add('expanded');
            }
        }

        // Profile dropdown functionality - SAMA PERSIS
        function initializeProfile() {
            const profileBtn = document.getElementById('profileBtn');
            const profileMenu = document.getElementById('profileMenu');

            profileBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                profileMenu.classList.toggle('show');
            });

            // Close profile menu when clicking outside
            document.addEventListener('click', function() {
                profileMenu.classList.remove('show');
            });

            profileMenu.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
        
        // Order detail modal functions
        function showOrderDetailFromButton(button) {
            try {
                const orderData = button.getAttribute('data-order');
                const order = JSON.parse(orderData);
                showOrderDetail(order);
            } catch (e) {
                console.error('Error parsing order data:', e);
                alert('Gagal memuat detail pesanan');
            }
        }
        
        function showOrderDetail(order) {
            const modal = document.getElementById('detailModal');
            const content = document.getElementById('detailContent');
            
            if (!modal || !content) {
                console.error('Modal elements not found');
                return;
            }
            
            // Format status
            const statusMap = {
                'pending': { text: 'Menunggu', class: 'status-pending' },
                'processing': { text: 'Diproses', class: 'status-processing' },
                'inprogress': { text: 'Diproses', class: 'status-processing' },
                'in_progress': { text: 'Diproses', class: 'status-processing' },
                'completed': { text: 'Selesai', class: 'status-completed' },
                'success': { text: 'Selesai', class: 'status-completed' },
                'finished': { text: 'Selesai', class: 'status-completed' },
                'done': { text: 'Selesai', class: 'status-completed' },
                'failed': { text: 'Gagal', class: 'status-failed' },
                'error': { text: 'Gagal', class: 'status-failed' },
                'partial': { text: 'Sebagian', class: 'status-partial' },
                'cancelled': { text: 'Dibatalkan', class: 'status-cancelled' },
                'canceled': { text: 'Dibatalkan', class: 'status-cancelled' }
            };
            
            const status = order.status ? order.status.toLowerCase() : 'pending';
            const statusInfo = statusMap[status] || { text: order.status || 'Pending', class: 'status-pending' };
            
            // Format date
            let formattedDate = 'N/A';
            if (order.created_at) {
                try {
                    // Assume database time is in UTC, convert to WIB
                    const utcDate = new Date(order.created_at + (order.created_at.includes('Z') ? '' : 'Z'));
                    
                    // Format in WIB timezone
                    const options = {
                        day: '2-digit',
                        month: '2-digit', 
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                        timeZone: 'Asia/Jakarta',
                        hour12: false
                    };
                    
                    formattedDate = utcDate.toLocaleString('id-ID', options).replace(',', '') + ' WIB';
                } catch (e) {
                    formattedDate = order.created_at;
                }
            }
            
            // Format numbers
            const formatNumber = (num) => {
                if (!num || num === 0) return '-';
                return new Intl.NumberFormat('id-ID').format(num);
            };
            
            const formatPrice = (price) => {
                if (!price || price === 0) return 'Rp 0';
                return 'Rp ' + new Intl.NumberFormat('id-ID').format(price);
            };
            
            // Escape HTML to prevent XSS - only for display
            const escapeHtml = (text) => {
                if (typeof text !== 'string') {
                    text = String(text || '');
                }
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.replace(/[&<>"']/g, (m) => map[m]);
            };
            
            const orderId = escapeHtml(order.id || '???');
            const targetLink = escapeHtml(order.link || 'N/A');
            
            content.innerHTML = `
                <div class="detail-item">
                    <div class="detail-label">ID Pesanan</div>
                    <div class="detail-value highlight">
                        #${orderId}
                        <button class="copy-btn" onclick="copyToClipboard('${orderId}')" title="Salin ID">
                            <svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        </button>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Dibuat</div>
                    <div class="detail-value">${formattedDate}</div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Layanan</div>
                    <div class="detail-value">${escapeHtml(order.service_name || 'Layanan ID: ' + (order.service_id || 'N/A'))}</div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Target</div>
                    <div class="detail-value">
                        ${targetLink}
                        ${order.link ? `<button class="copy-btn" onclick="copyToClipboard('${targetLink}')" title="Salin Target"><svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></button>` : ''}
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Jumlah Pesan</div>
                    <div class="detail-value highlight">${formatNumber(order.quantity)}</div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Biaya</div>
                    <div class="detail-value highlight">${formatPrice(order.price || order.total_price)}</div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Status</div>
                    <div class="detail-value">
                        <span class="detail-status ${statusInfo.class}">${statusInfo.text}</span>
                    </div>
                </div>
            `;
            
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            
            // Scroll modal overlay to top to ensure modal is visible
            setTimeout(() => {
                modal.scrollTop = 0;
            }, 10);
        }
        
        function closeOrderDetail() {
            const modal = document.getElementById('detailModal');
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
        
        function copyToClipboard(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(() => {
                    showToast('Berhasil disalin!');
                });
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    showToast('Berhasil disalin!');
                } catch (err) {
                    showToast('Gagal menyalin');
                }
                document.body.removeChild(textArea);
            }
        }
        
        function showToast(message) {
            // Simple toast notification
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: var(--teal-500);
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 500;
                z-index: 20000;
                animation: slideInRight 0.3s ease-out;
            `;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOutRight 0.3s ease-in forwards';
                setTimeout(() => document.body.removeChild(toast), 300);
            }, 2000);
        }
        
        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('detailModal');
            if (e.target === modal) {
                closeOrderDetail();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeOrderDetail();
            }
        });
        
        // Filter functionality
        function initializeFilters() {
            const filterToggle = document.getElementById('filterToggle');
            const filterForm = document.getElementById('filterForm');
            
            // Load filter state from localStorage
            const isCollapsed = localStorage.getItem('filterCollapsed') === 'true';
            if (isCollapsed) {
                filterForm.classList.add('collapsed');
                filterToggle.classList.add('collapsed');
                filterToggle.innerHTML = '<svg viewBox="0 0 24 24"><polyline points="6,9 12,15 18,9"/></svg>Tampilkan';
            }
            
            filterToggle.addEventListener('click', function() {
                const isCurrentlyCollapsed = filterForm.classList.contains('collapsed');
                
                if (isCurrentlyCollapsed) {
                    filterForm.classList.remove('collapsed');
                    filterToggle.classList.remove('collapsed');
                    filterToggle.innerHTML = '<svg viewBox="0 0 24 24"><polyline points="6,9 12,15 18,9"/></svg>Sembunyikan';
                    localStorage.setItem('filterCollapsed', 'false');
                } else {
                    filterForm.classList.add('collapsed');
                    filterToggle.classList.add('collapsed');
                    filterToggle.innerHTML = '<svg viewBox="0 0 24 24"><polyline points="6,9 12,15 18,9"/></svg>Tampilkan';
                    localStorage.setItem('filterCollapsed', 'true');
                }
            });
            
            // Auto-submit on filter change (optional - can be enabled)
            // const filterInputs = filterForm.querySelectorAll('select, input[type="date"]');
            // filterInputs.forEach(input => {
            //     input.addEventListener('change', function() {
            //         // Auto submit form when filter changes
            //         filterForm.submit();
            //     });
            // });
        }
        
        // Clear all filters
        function clearFilters() {
            const url = new URL(window.location);
            url.searchParams.delete('status');
            url.searchParams.delete('search');
            url.searchParams.delete('search_type');
            url.searchParams.delete('date_from');
            url.searchParams.delete('date_to');
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }
        
        // Remove specific filter
        function removeFilter(filterName) {
            const url = new URL(window.location);
            
            if (filterName === 'search') {
                url.searchParams.delete('search');
                url.searchParams.delete('search_type');
            } else {
                url.searchParams.delete(filterName);
            }
            
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }
    </script>
</body>
</html>