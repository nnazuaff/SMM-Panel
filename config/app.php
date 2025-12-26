<?php
/**
 * Konfigurasi Aplikasi Umum
 * Menetapkan timezone default ke WIB (Asia/Jakarta)
 */
if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', 'Asia/Jakarta');
}
// Set timezone global (aman dipanggil berkali-kali)
@date_default_timezone_set(APP_TIMEZONE);

// Helper optional untuk format tanggal WIB
if (!function_exists('format_wib')) {
    function format_wib(string $mysqlTs, string $fmt = 'd/m/Y H:i') : string {
        // Asumsi timestamp MySQL sudah dalam server local time; jika Anda simpan UTC ubah baris new DateTime tanpa timezone -> new DateTime($mysqlTs, new DateTimeZone('UTC'));
        try {
            $dt = new DateTime($mysqlTs);
            $dt->setTimezone(new DateTimeZone(APP_TIMEZONE));
            return $dt->format($fmt);
        } catch (Throwable $e) { return $mysqlTs; }
    }
}
