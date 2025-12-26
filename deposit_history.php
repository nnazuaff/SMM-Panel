<?php
require_once __DIR__ . '/includes/auth.php';
auth_require();
// Refresh saldo agar nilai terbaru langsung tampil saat halaman di-load
auth_refresh_balance();
$user = auth_user();
$activePage = 'deposit_history';
$sectionTitle = 'Riwayat Deposit';

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

// Get deposits with pagination
try {
    $pdo = getDBConnection();
    if ($pdo) {
        // Get total count
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM deposits WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $totalDeposits = $stmt->fetchColumn();
        $paginationInfo['total'] = $totalDeposits;
        $paginationInfo['current_page'] = $page;
        $paginationInfo['total_pages'] = ceil($totalDeposits / $perPage);
        $paginationInfo['per_page'] = $perPage;

        // Get deposits
        $stmt = $pdo->prepare('SELECT * FROM deposits WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?');
        $stmt->execute([$user['id'], $perPage, $offset]);
        $deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log('Error fetching deposits: ' . $e->getMessage());
    $deposits = [];
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
    <link rel="stylesheet" href="css/animations.css">

    <style>
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
            width: 70px;
        }

        .sidebar.hidden {
            transform: translateX(-100%);
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar.collapsed .sidebar-header {
            padding: 1rem;
            justify-content: center;
        }

        .sidebar-logo {
            width: 32px;
            height: 32px;
            flex-shrink: 0;
        }

        .sidebar-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: white;
            transition: opacity 0.3s;
        }

        .sidebar.collapsed .sidebar-title {
            opacity: 0;
            position: absolute;
            pointer-events: none;
        }

        .sidebar-menu {
            padding: 1rem 0;
            display: flex;
            flex-direction: column;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s;
            position: relative;
            border-left: 3px solid transparent;
        }

        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: var(--teal-400);
        }

        .menu-item.active {
            background: rgba(20, 184, 166, 0.15);
            color: white;
            border-left-color: var(--teal-400);
        }

        .sidebar.collapsed .menu-item {
            padding: 0.75rem;
            justify-content: center;
        }

        .menu-icon {
            width: 24px;
            height: 24px;
            margin-right: 0.75rem;
            flex-shrink: 0;
        }

        .sidebar.collapsed .menu-icon {
            margin-right: 0;
        }

        .menu-text {
            font-size: 0.875rem;
            font-weight: 500;
            transition: opacity 0.3s;
        }

        .sidebar.collapsed .menu-text {
            opacity: 0;
            position: absolute;
            pointer-events: none;
        }

        /* Tooltip for collapsed sidebar */
        .menu-tooltip {
            position: absolute;
            left: 70px;
            top: 50%;
            transform: translateY(-50%);
            background: var(--navy-800);
            color: white;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
            pointer-events: none;
            z-index: 1001;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .sidebar.collapsed .menu-item:hover .menu-tooltip {
            opacity: 1;
            visibility: visible;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 100vh;
        }

        .sidebar.collapsed ~ .main-content {
            margin-left: 70px;
        }

        /* Top Header */
        .top-header {
            background: white;
            border-bottom: 1px solid var(--gray-200);
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .hamburger-btn {
            display: none;
            background: none;
            border: none;
            padding: 0.5rem;
            cursor: pointer;
            border-radius: 6px;
            transition: background 0.3s;
        }

        .hamburger-btn:hover {
            background: var(--gray-100);
        }

        .hamburger {
            width: 20px;
            height: 14px;
            position: relative;
        }

        .hamburger span {
            display: block;
            width: 100%;
            height: 2px;
            background: var(--gray-700);
            border-radius: 1px;
            position: absolute;
            transition: all 0.3s;
        }

        .hamburger span:nth-child(1) { top: 0; }
        .hamburger span:nth-child(2) { top: 6px; }
        .hamburger span:nth-child(3) { top: 12px; }

        .hamburger.active span:nth-child(1) {
            transform: rotate(45deg);
            top: 6px;
        }

        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }

        .hamburger.active span:nth-child(3) {
            transform: rotate(-45deg);
            top: 6px;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .profile-dropdown {
            position: relative;
        }

        .profile-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: none;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .profile-btn:hover {
            background: var(--gray-100);
        }

        .profile-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--teal-500);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .profile-menu {
            position: absolute;
            top: 100%;
            right: 0;
            width: 280px;
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s;
            z-index: 1000;
        }

        .profile-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .profile-menu-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-100);
        }

        .profile-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .profile-details h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }

        .profile-details p {
            font-size: 0.875rem;
            color: var(--gray-500);
            margin: 0.25rem 0 0 0;
        }

        .balance-info {
            margin-top: 1rem;
            padding: 0.75rem;
            background: var(--teal-50);
            border: 1px solid var(--teal-200);
            border-radius: 8px;
            text-align: center;
        }

        .balance-info .balance-amount {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--teal-700);
        }

        .balance-info .balance-label {
            font-size: 0.75rem;
            color: var(--teal-600);
            margin-top: 0.25rem;
        }

        .profile-menu-links {
            padding: 0.5rem 0;
        }

        .profile-menu-links a {
            display: block;
            padding: 0.75rem 1.5rem;
            color: var(--gray-700);
            text-decoration: none;
            transition: background 0.3s;
        }

        .profile-menu-links a:hover {
            background: var(--gray-50);
        }

        /* Content */
        .content {
            padding: 2rem;
        }

        .content-header {
            margin-bottom: 2rem;
        }

        .content-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .content-subtitle {
            color: var(--gray-600);
        }

        /* Deposits Table */
        .deposits-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 1rem 1.5rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-100);
        }

        .table th {
            background: var(--gray-50);
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .table tbody tr:hover {
            background: var(--gray-50);
        }

        .deposit-id {
            font-weight: 600;
            color: var(--gray-900);
        }

        .deposit-amount {
            font-weight: 600;
            color: var(--teal-600);
        }

        .deposit-status {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-pending {
            background: var(--yellow-100);
            color: var(--yellow-800);
        }

        .status-success {
            background: var(--green-100);
            color: var(--green-800);
        }

        .status-failed {
            background: var(--red-100);
            color: var(--red-800);
        }

        .status-expired {
            background: var(--gray-100);
            color: var(--gray-800);
        }

        .deposit-date {
            color: var(--gray-600);
            font-size: 0.875rem;
        }

        .no-deposits {
            text-align: center;
            padding: 3rem;
            color: var(--gray-500);
        }

        .no-deposits-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 1rem;
            opacity: 0.5;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination-btn {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-300);
            background: white;
            color: var(--gray-700);
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s;
        }

        .pagination-btn:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
        }

        .pagination-btn.active {
            background: var(--teal-500);
            border-color: var(--teal-500);
            color: white;
        }

        .pagination-btn.disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        /* Mobile Responsive */
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

            .hamburger-btn {
                display: block;
            }

            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s;
                z-index: 999;
            }

            .sidebar-overlay.show {
                opacity: 1;
                visibility: visible;
            }

            .top-header {
                padding: 1rem;
            }

            .page-title {
                font-size: 1.25rem;
            }

            .content {
                padding: 1rem;
            }

            .table th,
            .table td {
                padding: 0.75rem;
                font-size: 0.875rem;
            }

            .table th:nth-child(4),
            .table td:nth-child(4) {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
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
            <!-- Top Header -->
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
                                    <div class="balance-amount">Rp <?= number_format($user['balance'] ?? 0, 0, ',', '.'); ?></div>
                                    <div class="balance-label">Saldo Tersedia</div>
                                </div>
                            </div>
                            <div class="profile-menu-links">
                                <a href="deposit.php">Top Up Saldo</a>
                                <a href="auth/logout.php">Logout</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="content">
                <div class="content-header">
                    <h2 class="content-title">Riwayat Deposit</h2>
                    <p class="content-subtitle">Lihat semua riwayat deposit saldo Anda</p>
                </div>

                <div class="deposits-table">
                    <div class="table-header">
                        <h3 class="table-title">Daftar Deposit</h3>
                    </div>

                    <div class="table-responsive">
                        <?php if (empty($deposits)): ?>
                            <div class="no-deposits">
                                <svg class="no-deposits-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <polyline points="14,2 14,8 20,8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <p>Belum ada riwayat deposit</p>
                                <a href="deposit.php" class="btn btn-primary" style="margin-top: 1rem;">Top Up Saldo Sekarang</a>
                            </div>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID Deposit</th>
                                        <th>Jumlah</th>
                                        <th>Kode Unik</th>
                                        <th>Total Bayar</th>
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
                                                <span><?= htmlspecialchars($deposit['unique_code']); ?></span>
                                            </td>
                                            <td>
                                                <span class="deposit-amount">Rp <?= number_format($deposit['final_amount'], 0, ',', '.'); ?></span>
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
                                                    <?= date('d/m/Y H:i', strtotime($deposit['created_at'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($paginationInfo['total_pages'] > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1; ?>" class="pagination-btn">« Sebelumnya</a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">« Sebelumnya</span>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($paginationInfo['total_pages'], $page + 2); $i++): ?>
                            <a href="?page=<?= $i; ?>" class="pagination-btn <?= $i === $page ? 'active' : ''; ?>">
                                <?= $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $paginationInfo['total_pages']): ?>
                            <a href="?page=<?= $page + 1; ?>" class="pagination-btn">Selanjutnya »</a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">Selanjutnya »</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <script>
        // Sidebar toggle
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const hamburger = document.getElementById('hamburger');

        hamburgerBtn.addEventListener('click', () => {
            sidebar.classList.toggle('show');
            sidebarOverlay.classList.toggle('show');
            hamburger.classList.toggle('active');
        });

        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
            hamburger.classList.remove('active');
        });

        // Profile dropdown
        const profileBtn = document.getElementById('profileBtn');
        const profileMenu = document.getElementById('profileMenu');

        profileBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            profileMenu.classList.toggle('show');
        });

        document.addEventListener('click', () => {
            profileMenu.classList.remove('show');
        });

        // Close profile menu when clicking inside it (prevent closing when clicking menu items)
        profileMenu.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    </script>
</body>
</html>