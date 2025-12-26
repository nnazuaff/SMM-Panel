<?php
require_once __DIR__ . '/includes/auth.php';
auth_require();
auth_refresh_balance();

date_default_timezone_set('Asia/Jakarta');

$user = auth_user();
$activePage = 'balance_logs';
$sectionTitle = 'Riwayat Saldo';

// Helper function to build URL with current filters
function buildFilterUrl($page, $excludeFilters = []) {
    $params = [];
    $params['page'] = $page;
    
    $filterType = $_GET['type'] ?? '';
    $filterSearch = $_GET['search'] ?? '';
    $filterYear = $_GET['year'] ?? '';
    
    if (!empty($filterType) && $filterType !== 'all' && !in_array('type', $excludeFilters)) {
        $params['type'] = $filterType;
    }
    if (!empty($filterSearch) && !in_array('search', $excludeFilters)) {
        $params['search'] = $filterSearch;
    }
    if (!empty($filterYear) && !in_array('year', $excludeFilters)) {
        $params['year'] = $filterYear;
    }
    
    return '?' . http_build_query($params);
}

// Get balance logs from database
require_once __DIR__ . '/config/database.php';
$logs = [];
$paginationInfo = [
    'total' => 0,
    'current_page' => 1,
    'total_pages' => 1,
    'per_page' => 20
];

// Get filter parameters
$filterType = $_GET['type'] ?? '';
$filterSearch = $_GET['search'] ?? '';
$filterYear = $_GET['year'] ?? '';

try {
    $pdo = getDBConnection();
    if ($pdo) {
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

        // Get pagination parameters
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        
        // Build WHERE clause with filters
        $whereConditions = ['user_id = ?'];
        $whereParams = [$user['id']];
        
        // Type filter
        if (!empty($filterType) && $filterType !== 'all') {
            $whereConditions[] = 'type = ?';
            $whereParams[] = $filterType;
        }
        
        // Search filter (by description or order_id)
        if (!empty($filterSearch)) {
            $whereConditions[] = '(description LIKE ? OR order_id LIKE ?)';
            $whereParams[] = '%' . $filterSearch . '%';
            $whereParams[] = '%' . $filterSearch . '%';
        }
        
        // Year filter
        if (!empty($filterYear)) {
            $whereConditions[] = 'YEAR(created_at) = ?';
            $whereParams[] = $filterYear;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Get total count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM balance_logs WHERE {$whereClause}");
        $countStmt->execute($whereParams);
        $totalLogs = (int)$countStmt->fetchColumn();
        
        $paginationInfo['total'] = $totalLogs;
        $paginationInfo['current_page'] = $page;
        $paginationInfo['total_pages'] = max(1, ceil($totalLogs / $perPage));
        
        // Get logs
        $stmt = $pdo->prepare("
            SELECT * FROM balance_logs 
            WHERE {$whereClause}
            ORDER BY created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($whereParams);
        $logs = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log('Balance logs error: ' . $e->getMessage());
}

// Get available years for filter
$availableYears = [];
try {
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT DISTINCT YEAR(created_at) as year FROM balance_logs WHERE user_id = ? ORDER BY year DESC");
        $stmt->execute([$user['id']]);
        $availableYears = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {
    // Ignore
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Saldo - AcisPedia</title>
    <link rel="icon" href="storage/assets/img/logo/logo_trans.png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="css/style-v2.css">
    
    <style>
        /* Dashboard Layout */
        .dashboard-layout {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
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
        
        .sidebar.collapsed ~ .main-content {
            margin-left: 80px;
        }
        
        .sidebar.hidden ~ .main-content {
            margin-left: 0;
        }
        
        /* Top Header */
        .top-header {
            background: rgba(255, 255, 255, 0.03);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(10px);
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .hamburger-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .hamburger {
            width: 24px;
            height: 24px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .hamburger span {
            width: 100%;
            height: 2px;
            background: var(--slate-300);
            border-radius: 2px;
            transition: all 0.3s;
        }
        
        .page-title {
            color: var(--slate-100);
            font-size: 20px;
            font-weight: 600;
            margin: 0;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .profile-dropdown {
            position: relative;
        }
        
        .profile-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 0;
        }
        
        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--teal-500), var(--teal-600));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
        }
        
        .profile-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: var(--navy-700);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 12px;
            min-width: 260px;
            box-shadow: 0 8px 24px rgba(0,0,0,.3);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s;
            z-index: 1000;
        }
        
        .profile-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .profile-menu-header {
            padding: 16px;
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
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Filters */
        .filters-container {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
            min-width: 200px;
        }
        
        .filter-label {
            color: var(--slate-300);
            font-size: 12px;
            font-weight: 500;
        }
        
        .filter-input,
        .filter-select {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 10px 12px;
            color: var(--slate-100);
            font-size: 14px;
            outline: none;
            transition: all 0.2s;
        }
        
        .filter-input:focus,
        .filter-select:focus {
            border-color: var(--teal-500);
            background: rgba(255, 255, 255, 0.08);
        }
        
        .filter-select {
            cursor: pointer;
        }
        
        .filter-actions {
            display: flex;
            gap: 8px;
            align-items: flex-end;
            padding-top: 18px;
        }
        
        .filter-btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }
        
        .filter-btn-primary {
            background: var(--teal-500);
            color: white;
        }
        
        .filter-btn-primary:hover {
            background: var(--teal-600);
        }
        
        .filter-btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: var(--slate-300);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .filter-btn-secondary:hover {
            background: rgba(255, 255, 255, 0.08);
        }
        
        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .summary-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .summary-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .summary-icon svg {
            width: 24px;
            height: 24px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
        }
        
        .summary-icon.green {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
        }
        
        .summary-icon.red {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        
        .summary-icon.blue {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        
        .summary-content {
            flex: 1;
        }
        
        .summary-label {
            color: var(--slate-400);
            font-size: 12px;
            margin-bottom: 4px;
        }
        
        .summary-value {
            color: var(--slate-100);
            font-size: 20px;
            font-weight: 600;
        }
        
        /* Logs Table */
        .logs-container {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            overflow: hidden;
        }
        
        .logs-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .logs-table thead {
            background: rgba(255, 255, 255, 0.03);
        }
        
        .logs-table th {
            color: var(--slate-300);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .logs-table td {
            color: var(--slate-200);
            font-size: 14px;
            padding: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .logs-table tr:last-child td {
            border-bottom: none;
        }
        
        .logs-table tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }
        
        .type-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .type-badge svg {
            width: 14px;
            height: 14px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
        }
        
        .type-badge.deposit {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
        }
        
        .type-badge.order {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        
        .type-badge.refund {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        
        .type-badge.convert {
            background: rgba(168, 85, 247, 0.1);
            color: #a855f7;
        }
        
        .type-badge.admin {
            background: rgba(251, 191, 36, 0.1);
            color: #fbbf24;
        }
        
        .amount-positive {
            color: #22c55e;
            font-weight: 600;
        }
        
        .amount-negative {
            color: #ef4444;
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state svg {
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            opacity: 0.3;
            stroke: var(--slate-400);
        }
        
        .empty-state h3 {
            color: var(--slate-300);
            font-size: 18px;
            font-weight: 600;
            margin: 0 0 8px 0;
        }
        
        .empty-state p {
            color: var(--slate-400);
            font-size: 14px;
            margin: 0;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .pagination-info {
            color: var(--slate-400);
            font-size: 14px;
        }
        
        .pagination-controls {
            display: flex;
            gap: 8px;
        }
        
        .page-btn {
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            color: var(--slate-300);
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .page-btn:hover:not(.disabled) {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--teal-500);
            color: var(--slate-100);
        }
        
        .page-btn.active {
            background: var(--teal-500);
            border-color: var(--teal-500);
            color: white;
        }
        
        .page-btn.disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .filters-container {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .filter-actions {
                width: 100%;
                padding-top: 0;
            }
            
            .filter-btn {
                flex: 1;
            }
            
            .logs-table {
                display: block;
                overflow-x: auto;
            }
            
            .pagination {
                flex-direction: column;
                gap: 16px;
            }
            
            .pagination-controls {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="storage/assets/img/logo/logo_trans.png" alt="Logo" class="sidebar-logo">
                <span class="sidebar-title">AcisPedia</span>
            </div>
            
            <nav class="sidebar-menu">
                <a href="dashboard.php" class="menu-item">
                    <div class="menu-icon">
                        <svg viewBox="0 0 24 24">
                            <rect x="3" y="3" width="7" height="7"/>
                            <rect x="14" y="3" width="7" height="7"/>
                            <rect x="14" y="14" width="7" height="7"/>
                            <rect x="3" y="14" width="7" height="7"/>
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
                            <line x1="8" y1="6" x2="21" y2="6"/>
                            <line x1="8" y1="12" x2="21" y2="12"/>
                            <line x1="8" y1="18" x2="21" y2="18"/>
                            <line x1="3" y1="6" x2="3.01" y2="6"/>
                            <line x1="3" y1="12" x2="3.01" y2="12"/>
                            <line x1="3" y1="18" x2="3.01" y2="18"/>
                        </svg>
                    </div>
                    <span class="menu-text">Layanan</span>
                    <div class="menu-tooltip">Layanan</div>
                </a>
                
                <a href="transactions.php" class="menu-item">
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
                
                <a href="balance_logs.php" class="menu-item active">
                    <div class="menu-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                            <polyline points="10 9 9 9 8 9"/>
                        </svg>
                    </div>
                    <span class="menu-text">Riwayat Saldo</span>
                    <div class="menu-tooltip">Riwayat Saldo</div>
                </a>
            </nav>
        </aside>
        
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <header class="top-header">
                <div class="header-left">
                    <button class="hamburger-btn" id="hamburgerBtn">
                        <div class="hamburger" id="hamburger">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </button>
                    <h1 class="page-title">Riwayat Saldo</h1>
                </div>
                
                <div class="header-right">
                    <div class="profile-dropdown">
                        <button class="profile-btn" id="profileBtn">
                            <div class="profile-avatar">
                                <?= strtoupper(substr($user['full_name'] ?? $user['username'] ?? 'U', 0, 1)) ?>
                            </div>
                        </button>
                        
                        <div class="profile-menu" id="profileMenu">
                            <div class="profile-menu-header">
                                <div class="profile-info">
                                    <div class="profile-avatar">
                                        <?= strtoupper(substr($user['full_name'] ?? $user['username'] ?? 'U', 0, 1)) ?>
                                    </div>
                                    <div class="profile-details">
                                        <h4><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'Pengguna') ?></h4>
                                        <p><?= htmlspecialchars($user['email'] ?? '') ?></p>
                                    </div>
                                </div>
                                <div class="balance-info">
                                    <span class="balance-icon" style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;background:rgba(20,184,166,.18);border:1px solid rgba(20,184,166,.35);border-radius:6px;font-size:10px;font-weight:600;letter-spacing:.5px;color:var(--teal-300);">RP</span>
                                    &nbsp;Saldo: Rp <?= number_format((int)($user['balance'] ?? 0), 0, ',', '.'); ?>
                                </div>
                            </div>
                            <div class="profile-menu-items">
                                <a href="profile.php" class="profile-menu-item">
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
            
            <div class="content-area">
                <!-- Summary Cards -->
                <?php
                $totalDeposit = 0;
                $totalSpent = 0;
                $totalRefund = 0;
                
                try {
                    if ($pdo) {
                        $stmt = $pdo->prepare("
                            SELECT 
                                SUM(CASE WHEN type IN ('deposit', 'convert', 'refund') THEN amount ELSE 0 END) as total_in,
                                SUM(CASE WHEN type = 'order' THEN amount ELSE 0 END) as total_out,
                                SUM(CASE WHEN type = 'refund' THEN amount ELSE 0 END) as total_refund
                            FROM balance_logs 
                            WHERE user_id = ?
                        ");
                        $stmt->execute([$user['id']]);
                        $summary = $stmt->fetch();
                        $totalDeposit = (float)($summary['total_in'] ?? 0);
                        $totalSpent = (float)($summary['total_out'] ?? 0);
                        $totalRefund = (float)($summary['total_refund'] ?? 0);
                    }
                } catch (Exception $e) {
                    // Ignore
                }
                ?>
                
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-icon green">
                            <svg viewBox="0 0 24 24">
                                <line x1="12" y1="1" x2="12" y2="23"/>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                        </div>
                        <div class="summary-content">
                            <div class="summary-label">Total Deposit</div>
                            <div class="summary-value">Rp <?= number_format($totalDeposit, 0, ',', '.') ?></div>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon red">
                            <svg viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12,6 12,12 16,14"/>
                            </svg>
                        </div>
                        <div class="summary-content">
                            <div class="summary-label">Total Pengeluaran</div>
                            <div class="summary-value">Rp <?= number_format($totalSpent, 0, ',', '.') ?></div>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon blue">
                            <svg viewBox="0 0 24 24">
                                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                            </svg>
                        </div>
                        <div class="summary-content">
                            <div class="summary-label">Total Refund</div>
                            <div class="summary-value">Rp <?= number_format($totalRefund, 0, ',', '.') ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <form method="GET" class="filters-container">
                    <div class="filter-group">
                        <label class="filter-label">Tipe Transaksi</label>
                        <select name="type" class="filter-select">
                            <option value="">Semua Tipe</option>
                            <option value="deposit" <?= $filterType === 'deposit' ? 'selected' : '' ?>>Deposit</option>
                            <option value="order" <?= $filterType === 'order' ? 'selected' : '' ?>>Pesanan</option>
                            <option value="refund" <?= $filterType === 'refund' ? 'selected' : '' ?>>Refund</option>
                            <option value="convert" <?= $filterType === 'convert' ? 'selected' : '' ?>>Konversi</option>
                            <option value="admin_adjustment" <?= $filterType === 'admin_adjustment' ? 'selected' : '' ?>>Penyesuaian Admin</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Cari</label>
                        <input type="text" name="search" class="filter-input" placeholder="Cari deskripsi atau ID pesanan..." value="<?= htmlspecialchars($filterSearch) ?>">
                    </div>
                    
                    <?php if (!empty($availableYears)): ?>
                    <div class="filter-group">
                        <label class="filter-label">Tahun</label>
                        <select name="year" class="filter-select">
                            <option value="">Semua Tahun</option>
                            <?php foreach ($availableYears as $year): ?>
                                <option value="<?= $year ?>" <?= $filterYear == $year ? 'selected' : '' ?>><?= $year ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="filter-actions">
                        <button type="submit" class="filter-btn filter-btn-primary">Filter</button>
                        <a href="balance_logs.php" class="filter-btn filter-btn-secondary">Reset</a>
                    </div>
                </form>
                
                <!-- Logs Table -->
                <div class="logs-container">
                    <?php if (empty($logs)): ?>
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                            </svg>
                            <h3>Belum Ada Riwayat</h3>
                            <p>Riwayat transaksi saldo Anda akan muncul di sini</p>
                        </div>
                    <?php else: ?>
                        <table class="logs-table">
                            <thead>
                                <tr>
                                    <th>Waktu</th>
                                    <th>Tipe</th>
                                    <th>Deskripsi</th>
                                    <th>Jumlah</th>
                                    <th>Saldo Sebelum</th>
                                    <th>Saldo Setelah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <?php
                                    $typeLabels = [
                                        'deposit' => 'Deposit',
                                        'order' => 'Pesanan',
                                        'refund' => 'Refund',
                                        'convert' => 'Konversi',
                                        'admin_adjustment' => 'Admin'
                                    ];
                                    $typeIcons = [
                                        'deposit' => '<svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>',
                                        'order' => '<svg viewBox="0 0 24 24"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>',
                                        'refund' => '<svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>',
                                        'convert' => '<svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>',
                                        'admin_adjustment' => '<svg viewBox="0 0 24 24"><path d="M12 15v2m-6 4h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2zm10-10V7a4 4 0 0 0-8 0v4h8z"/></svg>'
                                    ];
                                    $isPositive = in_array($log['type'], ['deposit', 'refund', 'convert', 'admin_adjustment']);
                                    $amountClass = $isPositive ? 'amount-positive' : 'amount-negative';
                                    $amountSign = $isPositive ? '+' : '-';
                                    ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
                                        <td>
                                            <span class="type-badge <?= $log['type'] ?>">
                                                <?= $typeIcons[$log['type']] ?? '' ?>
                                                <?= $typeLabels[$log['type']] ?? ucfirst($log['type']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($log['description'] ?? '-') ?></td>
                                        <td class="<?= $amountClass ?>">
                                            <?= $amountSign ?> Rp <?= number_format(abs($log['amount']), 0, ',', '.') ?>
                                        </td>
                                        <td>Rp <?= number_format($log['balance_before'], 0, ',', '.') ?></td>
                                        <td>Rp <?= number_format($log['balance_after'], 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Pagination -->
                        <?php if ($paginationInfo['total_pages'] > 1): ?>
                            <div class="pagination">
                                <div class="pagination-info">
                                    Menampilkan <?= count($logs) ?> dari <?= $paginationInfo['total'] ?> transaksi
                                </div>
                                <div class="pagination-controls">
                                    <?php if ($paginationInfo['current_page'] > 1): ?>
                                        <a href="<?= buildFilterUrl($paginationInfo['current_page'] - 1) ?>" class="page-btn">‹ Sebelumnya</a>
                                    <?php else: ?>
                                        <span class="page-btn disabled">‹ Sebelumnya</span>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $startPage = max(1, $paginationInfo['current_page'] - 2);
                                    $endPage = min($paginationInfo['total_pages'], $paginationInfo['current_page'] + 2);
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++):
                                    ?>
                                        <a href="<?= buildFilterUrl($i) ?>" class="page-btn <?= $i === $paginationInfo['current_page'] ? 'active' : '' ?>">
                                            <?= $i ?>
                                        </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($paginationInfo['current_page'] < $paginationInfo['total_pages']): ?>
                                        <a href="<?= buildFilterUrl($paginationInfo['current_page'] + 1) ?>" class="page-btn">Selanjutnya ›</a>
                                    <?php else: ?>
                                        <span class="page-btn disabled">Selanjutnya ›</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Sidebar controls
        const sidebar = document.getElementById('sidebar');
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        hamburgerBtn.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('show');
                sidebarOverlay.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
            }
        });
        
        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        });
        
        // Profile dropdown
        const profileBtn = document.getElementById('profileBtn');
        const profileMenu = document.getElementById('profileMenu');
        
        profileBtn.addEventListener('click', () => {
            profileMenu.classList.toggle('show');
        });
        
        document.addEventListener('click', (e) => {
            if (!profileBtn.contains(e.target) && !profileMenu.contains(e.target)) {
                profileMenu.classList.remove('show');
            }
        });
    </script>
</body>
</html>
