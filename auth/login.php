<?php
$activePage = 'login';
$pageTitle = 'Masuk - AcisPedia';

require_once '../includes/auth.php';

// Redirect jika sudah login
if (auth_user()) {
    header('Location: ../dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameOrEmail = trim($_POST['username_email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($usernameOrEmail && $password) {
        $result = auth_login($usernameOrEmail, $password);
        if ($result['ok']) {
            header('Location: ../dashboard.php');
            exit;
        } else {
            $error = $result['error'];
        }
    } else {
        $error = 'Silakan isi semua field.';
    }
}

require '../includes/header.php';
?>

<main id="mainContent" class="auth-page">
    <div class="container auth-wrapper">
        <div class="auth-grid">
            <aside class="auth-side">
                <h2 style="margin:0 0 .9rem;font-size:1.3rem;letter-spacing:.3px">Selamat Datang Kembali</h2>
                <p class="auth-lead">Kelola pesanan, pantau status, dan nikmati performa layanan cepat di satu dashboard.</p>
                <ul class="auth-benefits">
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Akses panel real‑time & progress order
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Riwayat transaksi & saldo transparan
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Support cepat & update berkala
                    </li>
                </ul>
                <p style="font-size:.75rem;letter-spacing:.5px;color:var(--slate-400,#94a3b8);margin-top:auto">Keamanan akun dilindungi enkripsi & proteksi sesi.</p>
            </aside>
            <div class="auth-card">
                <h1>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l4-5-4-5m4 5H3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Masuk Akun
                </h1>
                <p class="auth-lead" style="margin-top:-.4rem">Gunakan kredensial Anda untuk melanjutkan.</p>

                        <?php if (isset($_GET['registered']) && $_GET['registered'] == '1'): ?>
                            <div class="alert-inline success" role="status">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <span>Akun berhasil dibuat. Silakan masuk.</span>
                            </div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                    <div class="alert-inline error" role="alert">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10" stroke-width="2"/><path d="M15 9l-6 6m0-6l6 6" stroke-width="2" stroke-linecap="round"/></svg>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="post" class="auth-form compact" novalidate>
                    <div class="form-group">
                        <label for="username_email">Username / Email</label>
                        <input type="text" id="username_email" name="username_email" value="<?= htmlspecialchars($_POST['username_email'] ?? '') ?>" required autocomplete="username" />
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required autocomplete="current-password" />
                    </div>
                    <button type="submit" class="btn btn-primary w-full" style="width:100%;display:inline-flex;align-items:center;justify-content:center;gap:.45rem;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M9 16l4-4-4-4m4 4H3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Masuk
                    </button>
                </form>
                <div class="auth-switch">Belum punya akun? <a href="register.php">Daftar sekarang</a></div>
            </div>
        </div>
    </div>
</main>

<?php require '../includes/footer.php'; ?>
