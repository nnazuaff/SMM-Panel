<?php
/**
 * Class MedanpediaAPI
 * Class untuk handle semua request ke API Medanpedia
 */

require_once __DIR__ . '/../config/medanpedia_config.php';

class MedanpediaAPI {
    
    private $api_id;
    private $api_key;
    private $base_url;
    
    public function __construct() {
        $this->api_id = MEDANPEDIA_API_ID;
        $this->api_key = MEDANPEDIA_API_KEY;
        $this->base_url = MEDANPEDIA_BASE_URL;
    }
    
    /**
     * Method untuk melakukan request ke API
     */
    private function makeRequest($url, $data = []) {
        // Tentukan apakah akan force IPv4 langsung
        $forceIPv4 = MEDANPEDIA_FORCE_IPV4 === true;
        $attempt = 0;
        $maxAttempts = ($forceIPv4 || !MEDANPEDIA_AUTO_IPV4_FALLBACK) ? 1 : 2; // Jika fallback aktif, maksimal 2 percobaan

        $lastResult = null;
        while ($attempt < $maxAttempts) {
            $attempt++;

            // Tambahkan kredensial ke data request
            $data['api_id'] = $this->api_id;
            $data['api_key'] = $this->api_key;
            
            $ch = curl_init();
            
            $curlOptions = [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($data),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => MEDANPEDIA_TIMEOUT,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => [
                    'User-Agent: AcisPedia SMM Panel/1.0',
                    'Accept: application/json',
                    'Content-Type: application/x-www-form-urlencoded'
                ]
            ];

            if ($forceIPv4 || ($attempt === 2 && MEDANPEDIA_AUTO_IPV4_FALLBACK)) {
                $curlOptions[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4; // Force IPv4
            }

            curl_setopt_array($ch, $curlOptions);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);

            if ($error) {
                $lastResult = [
                    'status' => false,
                    'msg' => 'cURL Error: ' . $error,
                    'data' => null,
                    'debug' => $info + ['attempt' => $attempt, 'forced_ipv4' => ($forceIPv4 || $attempt === 2)]
                ];
            } elseif ($http_code !== 200) {
                $error_msg = 'HTTP Error: ' . $http_code;
                switch ($http_code) {
                    case 400: $error_msg .= ' - Bad Request. Format permintaan tidak valid.'; break;
                    case 402: $error_msg .= ' - Payment Required. Kemungkinan saldo akun API tidak cukup atau parameter order ditolak.'; break;
                    case 403: $error_msg .= ' - Forbidden. Kemungkinan IP tidak ter-whitelist atau kredensial salah.'; break;
                    case 404: $error_msg .= ' - Not Found. URL endpoint tidak ditemukan.'; break;
                    case 500: $error_msg .= ' - Internal Server Error. Ada masalah di server Medanpedia.'; break;
                }

                // Deteksi pesan IP tidak diizinkan untuk memicu fallback
                $ipBlocked = false;
                if ($http_code === 403 && isset($response)) {
                    if (strpos($response, 'tidak diizinkan') !== false || strpos(strtolower($response), 'not allowed') !== false) {
                        $ipBlocked = true;
                    }
                }

                $lastResult = [
                    'status' => false,
                    'msg' => $error_msg,
                    'data' => null,
                    'debug' => [
                        'http_code' => $http_code,
                        'url' => $url,
                        'response' => $response,
                        'info' => $info,
                        'attempt' => $attempt,
                        'forced_ipv4' => ($forceIPv4 || $attempt === 2),
                        'auto_fallback' => MEDANPEDIA_AUTO_IPV4_FALLBACK,
                        'ip_blocked_detected' => $ipBlocked
                    ]
                ];

                // Lakukan fallback jika memenuhi kondisi
                if ($ipBlocked && !$forceIPv4 && MEDANPEDIA_AUTO_IPV4_FALLBACK && $attempt === 1) {
                    // Ulangi loop dengan forced IPv4
                    continue;
                }
            } else {
                $decoded = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $lastResult = [
                        'status' => false,
                        'msg' => 'JSON Decode Error: ' . json_last_error_msg(),
                        'data' => null,
                        'debug' => [
                            'raw_response' => $response,
                            'json_error' => json_last_error_msg(),
                            'attempt' => $attempt,
                            'forced_ipv4' => ($forceIPv4 || $attempt === 2)
                        ]
                    ];
                } else {
                    // Sukses
                    if ($attempt === 2 && MEDANPEDIA_AUTO_IPV4_FALLBACK) {
                        $decoded['fallback_used'] = 'ipv4';
                    }
                    return $decoded;
                }
            }

            // Stop jika tidak perlu fallback
            if ($attempt >= $maxAttempts) {
                break;
            }
        }

        return $lastResult; // Return hasil terakhir jika gagal
    }
    
    /**
     * Method untuk cek saldo/profile
     */
    public function getProfile() {
        return $this->makeRequest(MEDANPEDIA_PROFILE_URL);
    }
    
    /**
     * Method untuk format currency (Rupiah)
     */
    public function formatCurrency($amount) {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
    
    /**
     * Method untuk mendapatkan status koneksi API
     */
    public function testConnection() {
        $result = $this->getProfile();
        
        if ($result['status']) {
            return [
                'status' => true,
                'msg' => 'Koneksi API berhasil',
                'data' => $result['data']
            ];
        } else {
            return [
                'status' => false,
                'msg' => 'Koneksi API gagal: ' . $result['msg'],
                'data' => null
            ];
        }
    }

    /**
     * Ambil daftar layanan (services) dari Medanpedia.
     * Opsi caching sederhana file agar tidak membebani API tiap request page.
     * @param int $cacheTtl detik masa cache (default 300 = 5 menit)
     * @param bool $forceRefresh abaikan cache & paksa ambil baru
     */
    public function getServices($cacheTtl = 300, $forceRefresh = false) {
        $cacheDir = __DIR__ . '/../storage/cache';
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
        $cacheFile = $cacheDir . '/services.json';

        if (!$forceRefresh && file_exists($cacheFile)) {
            $age = time() - filemtime($cacheFile);
            if ($age < $cacheTtl) {
                $cached = json_decode(file_get_contents($cacheFile), true);
                if ($cached) {
                    $cached['cached'] = true;
                    return $cached;
                }
            }
        }

        $result = $this->makeRequest(MEDANPEDIA_SERVICES_URL, []);
        // Simpan ke cache hanya jika status true & ada data array
        if (isset($result['status']) && $result['status'] && isset($result['data']) && is_array($result['data'])) {
            @file_put_contents($cacheFile, json_encode($result));
        }
        $result['cached'] = false;
        return $result;
    }

    /**
     * Method untuk membuat pesanan baru
     * @param int $service ID layanan yang ingin dipesan
     * @param string $target Target akun social media (username/link)
     * @param int $quantity Jumlah pesanan (followers, likes, dll)
     */
    public function createOrder($service, $target, $quantity) {
        $data = [
            'service' => $service,
            'target' => $target,
            'quantity' => $quantity
        ];
        
        return $this->makeRequest(MEDANPEDIA_ORDER_URL, $data);
    }

    /**
     * Method untuk cek status pesanan
     * @param string $order_id ID pesanan dari Medanpedia
     */
    public function getOrderStatus($order_id) {
        // Sesuai dokumentasi API Medanpedia: parameter 'id' untuk single order
        $data = [ 'id' => $order_id ];
        $raw = $this->makeRequest(MEDANPEDIA_STATUS_URL, $data);
        
        // Normalisasi response sesuai struktur dokumentasi
        if (isset($raw['status']) && $raw['status'] === true) {
            // Single order response structure: { status: true, msg: "...", data: { id, status, charge, start_count, remains } }
            if (isset($raw['data']) && is_array($raw['data'])) {
                $raw['normalized'] = [
                    'id' => $raw['data']['id'] ?? $order_id,
                    'status' => $raw['data']['status'] ?? null,
                    'charge' => $raw['data']['charge'] ?? null,
                    'start_count' => $raw['data']['start_count'] ?? null,
                    'remains' => $raw['data']['remains'] ?? null
                ];
            }
        }
        return $raw;
    }

    /**
     * Bulk status (maks 50 id provider per request) sesuai dokumentasi Medanpedia
     * @param array $providerIds array of provider order IDs (string/int)
     */
    public function getOrdersStatusBulk(array $providerIds) {
        $providerIds = array_values(array_filter(array_unique(array_map('strval', $providerIds)), function($v){return $v !== ''; }));
        if (empty($providerIds)) {
            return ['status'=>false,'msg'=>'No provider IDs supplied','orders'=>[]];
        }
        
        // Sesuai dokumentasi: parameter 'id' dengan comma-separated values untuk bulk request
        $data = ['id' => implode(',', array_slice($providerIds, 0, 50))]; // max 50 orders per request
        $raw = $this->makeRequest(MEDANPEDIA_STATUS_URL, $data);
        
        // Normalisasi response sesuai struktur dokumentasi
        if (isset($raw['status']) && $raw['status'] === true) {
            // Bulk response structure: { status: true, msg: "Cek status pesanan massal.", orders: { "1107": {...}, "1234": {...} } }
            if (isset($raw['orders']) && is_array($raw['orders'])) {
                $normalized = [];
                foreach ($raw['orders'] as $id => $info) {
                    if (!is_array($info)) continue;
                    $normalized[$id] = [
                        'id' => $id,
                        'status' => $info['status'] ?? null,
                        'charge' => $info['charge'] ?? null,
                        'start_count' => $info['start_count'] ?? null,
                        'remains' => $info['remains'] ?? null,
                        'msg' => $info['msg'] ?? null
                    ];
                }
                $raw['normalized_orders'] = $normalized;
            }
        }
        return $raw;
    }
}
?>
