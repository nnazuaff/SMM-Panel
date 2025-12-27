<?php
// Konfigurasi dasar halaman
if (!isset($pageTitle)) { $pageTitle = 'AcisPedia - SMM Panel'; }
if (!isset($activePage)) { $activePage = ''; }
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$userNav = $_SESSION['user'] ?? null;

// Deteksi apakah script berada di subfolder (misal /auth)
$scriptDir = str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME']));
$inAuth = (strpos($scriptDir, '/auth') !== false);
// Jika file yang diminta (REQUEST_URI) mengandung /auth/ maka butuh prefix '../'
// Menggunakan pola sederhana: kalau folder saat ini bukan root project (index.php) maka naik satu level
$basePrefix = $inAuth ? '../' : '';
$assetVer = '20250817w'; // Explicit centering with auth-wrapper and better margins
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="AcisPedia - SMM Panel Indonesia Terbaik dengan layanan cepat, harga terjangkau, dan dukungan 24/7." />
    <link rel="icon" href="<?= $basePrefix ?>storage/assets/img/logo/logo_trans.png" />
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Styles (cache bust with version) -->
    <!-- Bump versi untuk bust cache setelah perubahan tombol login -->
    <link rel="stylesheet" href="<?= $basePrefix ?>css/style-v2.css?v=<?= $assetVer ?>" />
    <link rel="stylesheet" href="<?= $basePrefix ?>css/animations.css?v=<?= $assetVer ?>" />
    
    
    <script>/* flag JS enabled early */document.documentElement.classList.add('js');</script>
    <noscript><style>.reveal{opacity:1!important;transform:none!important}</style></noscript>
</head>
<body>
<div id="scroll-progress" aria-hidden="true"></div>
<a class="skip-link" href="#mainContent">Lewati ke konten</a>
<header class="site-header" id="top">
    <div class="container nav-inner">
        <a href="<?= $basePrefix ?>index.php" class="brand" aria-label="Beranda">
            <img src="<?= $basePrefix ?>storage/assets/img/logo/logo_trans.png" alt="Logo AcisPedia" />
            <span>AcisPedia</span>
        </a>
        <nav class="main-nav" aria-label="Navigasi utama">
            <ul>
                <li><a href="<?= $basePrefix ?>index.php" class="<?= $activePage === 'home' ? 'active' : '' ?>">Utama</a></li>
                <li><a href="<?= $basePrefix ?>services.php" class="<?= $activePage === 'services' ? 'active' : '' ?>">Layanan</a></li>
                <li><a href="<?= $basePrefix ?>contact.php" class="<?= $activePage === 'contact' ? 'active' : '' ?>">Kontak</a></li>
                <?php if(!$userNav): ?>
                    <li class="login-item">
                        <a href="<?= $basePrefix ?>auth/login.php" class="login-btn<?= $activePage==='login'?' active':'' ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4m-5-4l4-4-4-4m4 4H3" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Masuk
                        </a>
                    </li>
                <?php else: ?>
                    <li class="dashboard-item">
                        <a href="<?= $basePrefix ?>dashboard.php" class="dashboard-btn<?= $activePage==='dashboard'?' active':'' ?>">Dashboard</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        <button class="nav-toggle" aria-label="Buka menu" aria-expanded="false" aria-controls="mobileNav">
            <span class="bar"></span>
            <span class="bar"></span>
            <span class="bar"></span>
        </button>
    </div>
    <div id="mobileNav" class="mobile-drawer" hidden>
        <nav aria-label="Menu mobile">
            <ul>
                <li><a href="<?= $basePrefix ?>index.php" class="<?= $activePage === 'home' ? 'active' : '' ?>">Utama</a></li>
                <li><a href="<?= $basePrefix ?>services.php" class="<?= $activePage === 'services' ? 'active' : '' ?>">Layanan</a></li>
                <li><a href="<?= $basePrefix ?>contact.php" class="<?= $activePage === 'contact' ? 'active' : '' ?>">Kontak</a></li>
                <?php if(!$userNav): ?>
                    <li class="login-item">
                        <a href="<?= $basePrefix ?>auth/login.php" class="login-btn<?= $activePage==='login'?' active':'' ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4m-5-4l4-4-4-4m4 4H3" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Masuk
                        </a>
                    </li>
                <?php else: ?>
                    <li class="dashboard-item">
                        <a href="<?= $basePrefix ?>dashboard.php" class="dashboard-btn<?= $activePage==='dashboard'?' active':'' ?>">Dashboard</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>