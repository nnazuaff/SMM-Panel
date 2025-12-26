<?php
$activePage = 'register';
$pageTitle = 'Daftar - AcisPedia';

require_once '../includes/auth.php';

// Redirect jika sudah login
if (auth_user()) {
    header('Location: ../dashboard.php');
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if ($password !== $confirmPassword) {
        $errors[] = 'Konfirmasi password tidak cocok.';
    }
    
    if (!$errors) {
        $result = auth_register($username, $email, $password, $fullName);
        if ($result['ok']) {
            // Redirect ke login dengan flag sukses
            header('Location: login.php?registered=1');
            exit;
        } else {
            $errors = $result['errors'];
        }
    }
}

require '../includes/header.php';
?>

<main id="mainContent" class="auth-page">
    <div class="container auth-wrapper">
        <div class="auth-grid">
            <aside class="auth-side">
                <h2 style="margin:0 0 .9rem;font-size:1.3rem;letter-spacing:.3px">Buat Akun Baru</h2>
                <p class="auth-lead">Nikmati harga kompetitif, otomatisasi order, & pembaruan layanan cepat.</p>
                <ul class="auth-benefits">
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>Lebih dari 5K+ order diproses lancar</li>
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>Dukungan & pemantauan performa</li>
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>Top up & saldo realtime</li>
                </ul>
                <p style="font-size:.75rem;letter-spacing:.5px;color:var(--slate-400,#94a3b8);margin-top:auto">Data disimpan aman sesuai standar praktik terbaik.</p>
            </aside>
            <div class="auth-card">
                <h1 style="display:flex;align-items:center;gap:.55rem;margin:0 0 .5rem;font-size:1.55rem;font-weight:600;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" stroke-width="2" stroke-linecap="round"/><circle cx="8.5" cy="7" r="4" stroke-width="2"/><path d="M20 8v6m3-3h-6" stroke-width="2" stroke-linecap="round"/></svg>
                    Registrasi
                </h1>
                <p class="auth-lead" style="margin-top:-.4rem">Lengkapi data di bawah ini.</p>

                <?php if ($errors): ?>
                    <div class="alert-inline error" role="alert">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10" stroke-width="2"/><path d="M15 9l-6 6m0-6l6 6" stroke-width="2" stroke-linecap="round"/></svg>
                        <ul style="margin:0;padding-left:1rem;">
                            <?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert-inline success" role="status">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                <?php endif; ?>

                <form method="post" class="auth-form compact" novalidate>
                    <div class="form-group">
                        <label for="full_name">Nama Lengkap</label>
                        <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required autocomplete="name" />
                    </div>
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autocomplete="username" pattern="[a-zA-Z0-9_]{3,30}" title="3-30 karakter, hanya huruf, angka, dan underscore" />
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="email" />
                    </div>
                        <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required autocomplete="new-password" minlength="6" />
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Konfirmasi Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password" minlength="6" />
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;display:inline-flex;align-items:center;justify-content:center;gap:.45rem;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" stroke-width="2" stroke-linecap="round"/><circle cx="8.5" cy="7" r="4" stroke-width="2"/><path d="M20 8v6m3-3h-6" stroke-width="2" stroke-linecap="round"/></svg>
                        Daftar Sekarang
                    </button>
                </form>
                <div class="auth-switch">Sudah punya akun? <a href="login.php">Masuk sekarang</a></div>
            </div>
        </div>
    </div>
</main>

<?php require '../includes/footer.php'; ?>
