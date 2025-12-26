<?php
/**
 * Helper Autentikasi User
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Muat config aplikasi (debug mode dll)
@require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

function auth_register(string $username, string $email, string $password, string $fullName): array {
    $errors = [];
    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $errors[] = 'Username hanya boleh huruf, angka, underscore (3-30 karakter).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email tidak valid.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password minimal 6 karakter.';
    }
    if (strlen($fullName) < 2) {
        $errors[] = 'Nama lengkap minimal 2 karakter.';
    }
    if ($errors) return ['ok' => false, 'errors' => $errors];

    $pdo = getDBConnection();
    if (!$pdo) {
        return ['ok' => false, 'errors' => ['Koneksi database gagal.']];
    }
    
    // Cek existing
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1');
    $stmt->execute([':u' => $username, ':e' => $email]);
    if ($stmt->fetch()) {
        return ['ok' => false, 'errors' => ['Username atau email sudah terdaftar.']];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (username, email, password, full_name) VALUES (:u,:e,:p,:f)');
    $stmt->execute([':u' => $username, ':e' => $email, ':p' => $hash, ':f' => $fullName]);
    $newUserId = (int)$pdo->lastInsertId();

    // Pastikan ada record saldo awal di tabel user_balance jika arsitektur memisahkan saldo.
    try {
        $pdo->prepare('INSERT IGNORE INTO user_balance (user_id, balance, total_spent) VALUES (?,0,0)')->execute([$newUserId]);
    } catch (\Throwable $e) {
        // Abaikan jika tabel belum ada / struktur berbeda.
    }
    return ['ok' => true];
}

function auth_login(string $usernameOrEmail, string $password): array {
    $pdo = getDBConnection();
    if (!$pdo) {
        return ['ok' => false, 'error' => 'Koneksi database gagal.'];
    }

    // NOTE: MySQL PDO dengan ATTR_EMULATE_PREPARES=false tidak mengizinkan placeholder bernama dipakai ulang.
    // Jadi gunakan dua placeholder berbeda.
    // Kolom balance pada tabel users mungkin tidak dipakai jika saldo disimpan di user_balance.
    $stmt = $pdo->prepare('SELECT id, username, email, password, full_name, balance, status, created_at FROM users WHERE username = :u OR email = :e LIMIT 1');
    $stmt->execute([':u' => $usernameOrEmail, ':e' => $usernameOrEmail]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password'])) {
        // Pesan generik agar tidak bocorkan mana yang salah
        return ['ok' => false, 'error' => 'Email atau password salah'];
    }
    
    if ($user['status'] !== 'active') {
        return ['ok' => false, 'error' => 'Akun Anda tidak aktif.'];
    }
    
    // Regenerate session id untuk keamanan
    session_regenerate_id(true);
    // Ambil saldo aktual dari tabel user_balance jika tersedia
    $balance = 0;
    try {
        $stmtBal = $pdo->prepare('SELECT balance FROM user_balance WHERE user_id = ? LIMIT 1');
        $stmtBal->execute([$user['id']]);
        $balRow = $stmtBal->fetch();
        if ($balRow && isset($balRow['balance'])) {
            $balance = (float)$balRow['balance'];
        } else {
            // fallback ke kolom users.balance bila ada
            $balance = (float)$user['balance'];
        }
    } catch (\Throwable $e) {
        $balance = (float)$user['balance'];
    }

    // Pastikan ada baris saldo (self-healing kalau row terhapus manual)
    try {
        $pdo->prepare('INSERT IGNORE INTO user_balance (user_id, balance, total_spent) VALUES (?,0,0)')->execute([$user['id']]);
    } catch (\Throwable $e) { /* ignore */ }

    $displayName = trim((string)($user['full_name'] ?? '')) !== '' ? $user['full_name'] : $user['username'];

    $_SESSION['user'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'full_name' => $displayName,
        'balance' => $balance,
        'since' => $user['created_at'],
    ];
    return ['ok' => true];
}

function auth_user(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Refresh saldo (dan optionally nama) user dari database.
 */
function auth_refresh_balance(): void {
    if (!auth_user()) return;
    $pdo = getDBConnection();
    if (!$pdo) return;
    try {
    // Self-healing: buat baris jika hilang (misal user_balance terhapus manual)
    $pdo->prepare('INSERT IGNORE INTO user_balance (user_id, balance, total_spent) VALUES (?,0,0)')->execute([$_SESSION['user']['id']]);
        $stmt = $pdo->prepare('SELECT balance FROM user_balance WHERE user_id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user']['id']]);
        $bal = $stmt->fetchColumn();
        if ($bal !== false) {
            $_SESSION['user']['balance'] = (float)$bal;
        }
    } catch (\Throwable $e) { /* silent */ }
}

function auth_require(): void {
    if (!auth_user()) {
        header('Location: login.php');
        exit;
    }
}

function auth_check(): bool {
    return auth_user() !== null;
}

function auth_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
