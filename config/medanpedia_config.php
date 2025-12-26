<?php
/**
 * Konfigurasi API Medanpedia
 * File ini berisi kredensial dan pengaturan untuk integrasi API
 */

// Kredensial API Medanpedia
define('MEDANPEDIA_API_ID', 39940);
define('MEDANPEDIA_API_KEY', 'algce4-ue158w-0gstmd-osishs-yl8kxk');

// Base URL API
define('MEDANPEDIA_BASE_URL', 'https://api.medanpedia.co.id');

// Endpoint URLs
define('MEDANPEDIA_PROFILE_URL', MEDANPEDIA_BASE_URL . '/profile');
define('MEDANPEDIA_SERVICES_URL', MEDANPEDIA_BASE_URL . '/services');
define('MEDANPEDIA_ORDER_URL', MEDANPEDIA_BASE_URL . '/order');
define('MEDANPEDIA_STATUS_URL', MEDANPEDIA_BASE_URL . '/status');

// Timeout untuk request (dalam detik)
define('MEDANPEDIA_TIMEOUT', 30);

// Opsi force IPv4 (set true jika ingin selalu pakai IPv4)
if (!defined('MEDANPEDIA_FORCE_IPV4')) {
	define('MEDANPEDIA_FORCE_IPV4', false);
}

// Opsi fallback otomatis: coba IPv6 dulu, jika ditolak (403 IP tidak diizinkan) ulangi dengan IPv4
if (!defined('MEDANPEDIA_AUTO_IPV4_FALLBACK')) {
	define('MEDANPEDIA_AUTO_IPV4_FALLBACK', true);
}
?>
