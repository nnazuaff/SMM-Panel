<?php
/**
 * Konfigurasi Database
 */

// Database credentials
// CATATAN: Pada hosting shared (Hostinger), host MySQL internal biasanya 'localhost'.
// Jika menggunakan domain situs bisa menyebabkan koneksi lambat / timeout karena DNS / firewall.
define('DB_HOST', 'localhost'); // gunakan host lokal (lebih cepat & sudah terbukti berhasil)
// Daftar fallback host untuk dicoba berurutan jika host utama gagal
define('DB_HOST_FALLBACKS', json_encode([
    'localhost',
    '127.0.0.1'
]));
define('DB_NAME', 'u403352430_acispedia');
define('DB_USER', 'u403352430_acispedia');
define('DB_PASS', 'OzanJB123#');
define('DB_CHARSET', 'utf8mb4');

// PDO connection function
function getDBConnection(bool $debug = false) {
    static $cached = null;
    if ($cached instanceof PDO) return $cached;

    $hosts = array_unique(array_filter([DB_HOST, ...json_decode(DB_HOST_FALLBACKS, true)]));
    $errors = [];
    foreach ($hosts as $host) {
        $start = microtime(true);
        try {
            $dsn = "mysql:host={$host};dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5, // detik
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci', time_zone = '+07:00'"
            ]);
            // Simpan host yang sukses (untuk info)
            if ($debug) {
                echo "<small>Sukses konek host {$host} dalam " . round((microtime(true)-$start)*1000) . " ms</small><br>"; flush();
            }
            return $cached = $pdo;
        } catch (PDOException $e) {
            $elapsed = round((microtime(true)-$start)*1000);
            $errors[] = "{$host} ({$elapsed} ms): " . $e->getMessage();
            error_log('[DB] Gagal host ' . $host . ' : ' . $e->getMessage());
            if ($debug) {
                echo "<small>Gagal host {$host}: " . htmlspecialchars($e->getMessage()) . "</small><br>"; flush();
            }
        }
    }
    if ($debug) {
        echo '<strong>Semua host gagal konek.</strong><br>'; flush();
    }
    // Simpan detail error terakhir untuk referensi luar
    $GLOBALS['DB_LAST_ERRORS'] = $errors;
    return false;
}

// Test database connection
function testDBConnection(bool $debug = false) {
    return (bool) getDBConnection($debug);
}

function db_last_errors(): array {
    return $GLOBALS['DB_LAST_ERRORS'] ?? [];
}
?>
