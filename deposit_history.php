<?php
require_once __DIR__ . '/includes/auth.php';
auth_require();
// Refresh saldo agar nilai terbaru langsung tampil saat halaman di-load
auth_refresh_balance();
$user = auth_user();
$activePage = 'deposit_history';
$sectionTitle = 'Riwayat Deposit';

// Set timezone to WIB (GMT+7)
date_default_timezone_set('Asia/Jakarta');

// Get user's deposits from database
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/deposit.php';

$deposits = [];
$paginationInfo = [
    'total' => 0,
    'current_page' => 1,
    'total_pages' => 1,
    'per_page' => 20
];

// Get pagination parameters
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get filter parameters
$filterYear = $_GET['year'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterMethod = $_GET['method'] ?? '';
$filterSearch = $_GET['search'] ?? '';

// Build WHERE clause with filters
$whereConditions = ['user_id = ?'];
$params = [$user['id']];

// Year filter
if ($filterYear !== '' && is_numeric($filterYear)) {
    $whereConditions[] = 'YEAR(created_at) = ?';
    $params[] = intval($filterYear);
}

// Status filter
if (!empty($filterStatus) && in_array($filterStatus, ['pending', 'success', 'failed', 'expired'])) {
    $whereConditions[] = 'status = ?';
    $params[] = $filterStatus;
}

// Method filter
if (!empty($filterMethod) && in_array($filterMethod, ['qris', 'conversion'])) {
    $whereConditions[] = 'payment_method = ?';
    $params[] = $filterMethod;
}

// Search filter (ID, amount, or final_amount)
if (!empty($filterSearch)) {
    $searchTerm = trim($filterSearch);
    if (is_numeric($searchTerm)) {
        $whereConditions[] = '(id = ? OR amount = ? OR final_amount = ?)';
        $params[] = intval($searchTerm);
        $params[] = floatval($searchTerm);
        $params[] = floatval($searchTerm);
    }
}

$whereClause = implode(' AND ', $whereConditions);

// Get deposits with pagination and filters
try {
    $pdo = getDBConnection();
    if ($pdo) {
        // Get total count with filters
        $countSql = "SELECT COUNT(*) FROM deposits WHERE $whereClause";
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($params);
        $totalDeposits = $stmt->fetchColumn();
        $paginationInfo['total'] = $totalDeposits;
        $paginationInfo['current_page'] = $page;
        $paginationInfo['total_pages'] = max(1, ceil($totalDeposits / $perPage));
        $paginationInfo['per_page'] = $perPage;

        // Get deposits with filters
        $sql = "SELECT * FROM deposits WHERE $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get available years for filter dropdown
        $stmtYears = $pdo->prepare('SELECT DISTINCT YEAR(created_at) as year FROM deposits WHERE user_id = ? ORDER BY year DESC');
        $stmtYears->execute([$user['id']]);
        $availableYears = $stmtYears->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {
    error_log('Error fetching deposits: ' . $e->getMessage());
    $deposits = [];
    $availableYears = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Deposit - AcisPedia</title>
    <link rel="icon" href="storage/assets/img/logo/logo_trans.png">
    
    <!-- Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="css/style-v2.css">
    
    <style>
        /* Dashboard Layout - SAMA PERSIS DENGAN DEPOSIT.PHP */
        .dashboard-layout {
            display: flex;
            min-height: 100vh;
        }
        
        /* Global font smoothing */
        * {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* DEPOSIT HISTORY SPECIFIC STYLES */
        .deposit-history-header {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .deposit-history-header h2 {
            color: var(--slate-100);
            font-size: 28px;
            font-weight: 600;
            margin: 0 0 8px 0;
        }
        
        .deposit-history-header p {
            color: var(--slate-300);
            font-size: 14px;
            margin: 0;
        }
        
        .deposits-table-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }
        
        .table-header {
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        
        .table-title {
            color: var(--slate-100);
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .deposits-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .deposits-table th,
        .deposits-table td {
            padding: 16px 24px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .deposits-table th {
            background: rgba(255, 255, 255, 0.03);
            color: var(--slate-300);
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .deposits-table tbody tr {
            transition: all 0.2s ease;
        }
        
        .deposits-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.03);
        }
        
        .deposit-id {
            color: var(--slate-100);
            font-weight: 600;
            font-family: 'Courier New', monospace;
        }
        
        .deposit-amount {
            color: var(--teal-300);
            font-weight: 600;
        }
        
        .deposit-code {
            color: var(--slate-200);
            font-family: 'Courier New', monospace;
        }
        
        .payment-method {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .payment-method svg {
            flex-shrink: 0;
        }
        
        .method-qris {
            background: rgba(20, 184, 166, 0.15);
            color: var(--teal-300);
        }
        
        .method-conversion {
            background: rgba(168, 85, 247, 0.15);
            color: #c084fc;
        }
        
        .deposit-status {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background: rgba(250, 204, 21, 0.15);
            color: #fbbf24;
        }
        
        .status-success {
            background: rgba(34, 197, 94, 0.15);
            color: #4ade80;
        }
        
        .status-failed {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
        }
        
        .status-expired {
            background: rgba(148, 163, 184, 0.15);
            color: #94a3b8;
        }
        
        .deposit-date {
            color: var(--slate-300);
            font-size: 14px;
        }
        
        .no-deposits {
            text-align: center;
            padding: 48px 24px;
        }
        
        .no-deposits-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            opacity: 0.3;
            color: var(--slate-400);
        }
        
        .no-deposits-text {
            color: var(--slate-400);
            font-size: 16px;
            margin-bottom: 16px;
        }
        
        .btn-primary {
            background: var(--teal-500);
            border: none;
            border-radius: 6px;
            padding: 10px 20px;
            color: white;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            letter-spacing: 0.02em;
        }
        
        .btn-primary:hover {
            background: var(--teal-600);
        }
        
        /* Filter Section */
        .filter-section {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        
        .filter-title {
            color: var(--slate-100);
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-icon {
            width: 20px;
            height: 20px;
            color: var(--teal-300);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .filter-search-group {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }
        
        .filter-search-box {
            flex: 1;
        }
        
        .filter-label {
            display: block;
            color: var(--slate-300);
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 6px;
        }
        
        .filter-select,
        .filter-input {
            width: 100%;
            background: rgba(15,41,66,.6);
            border: 1px solid rgba(255,255,255,.14);
            border-radius: 6px;
            padding: 10px 12px;
            color: var(--slate-100);
            font-size: 14px;
            transition: all 0.2s;
            box-sizing: border-box;
        }
        
        .filter-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 8px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 32px;
        }
        
        .filter-select:hover {
            border-color: var(--teal-500);
        }
        
        .filter-select:focus,
        .filter-input:focus {
            outline: none;
            border-color: var(--teal-500);
            background: rgba(15,41,66,.75);
            box-shadow: 0 0 0 3px rgba(20,184,166,.1);
        }
        
        .filter-select option {
            background: var(--navy-800);
            color: var(--slate-100);
        }
        
        .filter-btn {
            background: var(--teal-500);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            color: white;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        
        .filter-btn:hover {
            background: var(--teal-600);
        }
        
        .filter-btn svg {
            width: 16px;
            height: 16px;
        }
        
        .filter-reset {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: var(--slate-300);
        }
        
        .filter-reset:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
            color: var(--slate-200);
        }
        
        .filter-active-badge {
            display: inline-flex;
            align-items: center;
            background: rgba(20, 184, 166, 0.15);
            color: var(--teal-300);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            margin-left: auto;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 24px;
            padding: 0 24px 24px;
        }
        
        .pagination-btn {
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            color: var(--slate-200);
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .pagination-btn:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(20, 184, 166, 0.3);
        }
        
        .pagination-btn.active {
            background: var(--teal-500);
            border-color: var(--teal-500);
            color: white;
        }
        
        .pagination-btn.disabled {
            opacity: 0.4;
            pointer-events: none;
            cursor: not-allowed;
        }
        
        /* Mobile Responsive - SAMA PERSIS */
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
            
            .content-area {
                padding: 16px;
            }
            
            .deposits-table th,
            .deposits-table td {
                padding: 12px 16px;
                font-size: 13px;
            }
            
            .deposits-table th:nth-child(3),
            .deposits-table td:nth-child(3) {
                display: none;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-search-group {
                flex-direction: column;
            }
            
            .filter-btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .page-title {
                font-size: 18px;
            }
            
            .deposits-table th,
            .deposits-table td {
                padding: 10px 12px;
                font-size: 12px;
            }
            
            .deposits-table th:nth-child(4),
            .deposits-table td:nth-child(4) {
                display: none;
            }
            
            .filter-section {
                padding: 16px;
            }
            
            .filter-title {
                font-size: 14px;
            }
        }
        
        /* Overlay for mobile */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }
        
        .sidebar-overlay.show {
            display: block;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar - SAMA PERSIS DENGAN DEPOSIT.PHP -->
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
                
                <a href="deposit_history.php" class="menu-item active">
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
            </nav>
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
                    <h1 class="page-title">Riwayat Deposit</h1>
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

            <!-- Content Area - KONTEN UTAMA RIWAYAT DEPOSIT -->
            <div class="content-area">
                <div class="deposit-history-header">
                    <h2>Riwayat Deposit</h2>
                    <p>Lihat semua riwayat deposit saldo Anda</p>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <h3 class="filter-title">
                        <svg class="filter-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                        </svg>
                        Filter Pencarian
                        <?php
                        $activeFilters = 0;
                        if (!empty($filterYear)) $activeFilters++;
                        if (!empty($filterStatus)) $activeFilters++;
                        if (!empty($filterMethod)) $activeFilters++;
                        if (!empty($filterSearch)) $activeFilters++;
                        if ($activeFilters > 0):
                        ?>
                            <span class="filter-active-badge"><?= $activeFilters; ?> filter aktif</span>
                        <?php endif; ?>
                    </h3>
                    
                    <form method="GET" action="" id="filterForm">
                        <div class="filter-grid">
                            <!-- Year Filter -->
                            <div>
                                <label class="filter-label">Tahun</label>
                                <select name="year" class="filter-select">
                                    <option value="">Semua Tahun</option>
                                    <?php foreach ($availableYears as $year): ?>
                                        <option value="<?= $year; ?>" <?= $filterYear == $year ? 'selected' : ''; ?>>
                                            <?= $year; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Status Filter -->
                            <div>
                                <label class="filter-label">Status</label>
                                <select name="status" class="filter-select">
                                    <option value="">Semua Status</option>
                                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : ''; ?>>Menunggu</option>
                                    <option value="success" <?= $filterStatus === 'success' ? 'selected' : ''; ?>>Berhasil</option>
                                    <option value="failed" <?= $filterStatus === 'failed' ? 'selected' : ''; ?>>Gagal</option>
                                    <option value="expired" <?= $filterStatus === 'expired' ? 'selected' : ''; ?>>Kadaluarsa</option>
                                </select>
                            </div>
                            
                            <!-- Method Filter -->
                            <div>
                                <label class="filter-label">Metode Pembayaran</label>
                                <select name="method" class="filter-select">
                                    <option value="">Semua Metode</option>
                                    <option value="qris" <?= $filterMethod === 'qris' ? 'selected' : ''; ?>>QRIS</option>
                                    <option value="conversion" <?= $filterMethod === 'conversion' ? 'selected' : ''; ?>>Konversi</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Search Box -->
                        <div class="filter-search-group">
                            <div class="filter-search-box">
                                <label class="filter-label">Cari berdasarkan ID Deposit, Jumlah, atau Total Bayar</label>
                                <input 
                                    type="text" 
                                    name="search" 
                                    class="filter-input" 
                                    placeholder="Contoh: 12345 atau 10000"
                                    value="<?= htmlspecialchars($filterSearch); ?>"
                                >
                            </div>
                            <button type="submit" class="filter-btn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"/>
                                    <path d="m21 21-4.35-4.35"/>
                                </svg>
                                Cari
                            </button>
                            <button type="button" class="filter-btn filter-reset" onclick="resetFilters()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 6h18"/>
                                    <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/>
                                    <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                                    <line x1="10" y1="11" x2="10" y2="17"/>
                                    <line x1="14" y1="11" x2="14" y2="17"/>
                                </svg>
                                Reset
                            </button>
                        </div>
                    </form>
                </div>

                <div class="deposits-table-card">
                    <div class="table-header">
                        <h3 class="table-title">Daftar Deposit</h3>
                    </div>

                    <?php if (empty($deposits)): ?>
                        <div class="no-deposits">
                            <svg class="no-deposits-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <polyline points="14,2 14,8 20,8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <p class="no-deposits-text">Tidak ada riwayat deposit yang sesuai dengan filter</p>
                            <button onclick="resetFilters()" class="btn-primary">Reset Filter</button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="deposits-table">
                                <thead>
                                    <tr>
                                        <th>ID Deposit</th>
                                        <th>Jumlah</th>
                                        <th>Kode Unik</th>
                                        <th>Total Bayar</th>
                                        <th>Metode</th>
                                        <th>Status</th>
                                        <th>Tanggal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($deposits as $deposit): ?>
                                        <tr>
                                            <td>
                                                <span class="deposit-id">#<?= htmlspecialchars($deposit['id']); ?></span>
                                            </td>
                                            <td>
                                                <span class="deposit-amount">Rp <?= number_format($deposit['amount'], 0, ',', '.'); ?></span>
                                            </td>
                                            <td>
                                                <span class="deposit-code"><?= htmlspecialchars($deposit['unique_code']); ?></span>
                                            </td>
                                            <td>
                                                <span class="deposit-amount">Rp <?= number_format($deposit['final_amount'], 0, ',', '.'); ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                $paymentMethod = $deposit['payment_method'] ?? 'qris';
                                                $methodClass = 'method-' . $paymentMethod;
                                                $methodText = $paymentMethod === 'qris' ? 'QRIS' : 'Konversi';
                                                $methodIcon = $paymentMethod === 'qris' 
                                                    ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><path d="M3 9h18M9 21V9"/></svg>'
                                                    : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 16V4M7 4L3 8M7 4l4 4M17 8v12M17 20l4-4M17 20l-4-4"/></svg>';
                                                ?>
                                                <span class="payment-method <?= $methodClass; ?>">
                                                    <?= $methodIcon; ?>
                                                    <?= $methodText; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClass = 'status-' . strtolower($deposit['status']);
                                                $statusText = ucfirst($deposit['status']);
                                                if ($deposit['status'] === 'success') $statusText = 'Berhasil';
                                                elseif ($deposit['status'] === 'pending') $statusText = 'Menunggu';
                                                elseif ($deposit['status'] === 'failed') $statusText = 'Gagal';
                                                elseif ($deposit['status'] === 'expired') $statusText = 'Kadaluarsa';
                                                ?>
                                                <span class="deposit-status <?= $statusClass; ?>">
                                                    <?= $statusText; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="deposit-date">
                                                    <?php
                                                    // Convert database time to WIB
                                                    $datetime = new DateTime($deposit['created_at']);
                                                    $datetime->setTimezone(new DateTimeZone('Asia/Jakarta'));
                                                    echo $datetime->format('d/m/Y H:i') . ' WIB';
                                                    ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($paginationInfo['total_pages'] > 1): ?>
                            <div class="pagination">
                                <?php 
                                // Build query string to preserve filters
                                $queryParams = [];
                                if (!empty($filterYear)) $queryParams['year'] = $filterYear;
                                if (!empty($filterStatus)) $queryParams['status'] = $filterStatus;
                                if (!empty($filterMethod)) $queryParams['method'] = $filterMethod;
                                if (!empty($filterSearch)) $queryParams['search'] = $filterSearch;
                                $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                                ?>
                                
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page - 1; ?><?= $queryString; ?>" class="pagination-btn">« Sebelumnya</a>
                                <?php else: ?>
                                    <span class="pagination-btn disabled">« Sebelumnya</span>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($paginationInfo['total_pages'], $page + 2); $i++): ?>
                                    <a href="?page=<?= $i; ?><?= $queryString; ?>" class="pagination-btn <?= $i === $page ? 'active' : ''; ?>">
                                        <?= $i; ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $paginationInfo['total_pages']): ?>
                                    <a href="?page=<?= $page + 1; ?><?= $queryString; ?>" class="pagination-btn">Selanjutnya »</a>
                                <?php else: ?>
                                    <span class="pagination-btn disabled">Selanjutnya »</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // JAVASCRIPT SAMA PERSIS DENGAN DEPOSIT.PHP
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            initializeSidebar();
            initializeProfile();
        });

        // Reset all filters
        function resetFilters() {
            window.location.href = 'deposit_history.php';
        }

        // Sidebar functionality - SAMA PERSIS
        function initializeSidebar() {
            const hamburgerBtn = document.getElementById('hamburgerBtn');
            const hamburger = document.getElementById('hamburger');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            hamburgerBtn.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    // Mobile: toggle sidebar visibility
                    sidebar.classList.toggle('show');
                    sidebarOverlay.classList.toggle('show');
                    hamburger.classList.toggle('active');
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
            });

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('show');
                    sidebarOverlay.classList.remove('show');
                    mainContent.classList.remove('expanded');
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
        }

        // Profile dropdown functionality - SAMA PERSIS
        function initializeProfile() {
            const profileBtn = document.getElementById('profileBtn');
            const profileMenu = document.getElementById('profileMenu');

            profileBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                profileMenu.classList.toggle('show');
            });

            // Close when clicking outside
            document.addEventListener('click', function(e) {
                if (!profileBtn.contains(e.target) && !profileMenu.contains(e.target)) {
                    profileMenu.classList.remove('show');
                }
            });

            // Prevent menu from closing when clicking inside
            profileMenu.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
    </script>
</body>
</html>
