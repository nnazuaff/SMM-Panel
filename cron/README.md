# Setup Cron Job untuk Auto Update Status Transaksi

## ⚠️ SOLUSI untuk Error "Could not open input file"

Jika Anda mengalami error seperti pada screenshot, ini adalah masalah umum di shared hosting seperti Hostinger. **Solusinya adalah menggunakan HTTP Cron** alih-alih CLI cron.

## 🚀 Solusi 1: HTTP Cron (DIREKOMENDASIKAN untuk Hostinger)

### Langkah 1: Test HTTP Cron
1. Buka browser dan akses: `https://yourdomain.com/cron/http_cron.php?key=AcisPedia2024`
2. Pastikan halaman muncul dan menampilkan proses update status
3. Jika berhasil, lanjut ke langkah 2

### Langkah 2: Setup Cron Job di Hostinger
1. Login ke **hPanel Hostinger**
2. Cari menu **"Cron Jobs"** di bagian Advanced
3. Klik **"Create new cron job"**
4. Atur jadwal:
   - **Minute**: */5 (setiap 5 menit)
   - **Hour**: * (setiap jam)
   - **Day**: * (setiap hari)
   - **Month**: * (setiap bulan)
   - **Weekday**: * (setiap hari dalam seminggu)
5. **Command**: 
   ```bash
   /usr/bin/curl -s "https://yourdomain.com/cron/http_cron.php?key=AcisPedia2024" > /dev/null 2>&1
   ```
   
   **ATAU** jika curl tidak tersedia:
   ```bash
   /usr/bin/wget -q -O /dev/null "https://yourdomain.com/cron/http_cron.php?key=AcisPedia2024"
   ```

6. Klik **Save**

### Contoh Setup HTTP Cron:
```
Frequency: */5 * * * *
Command: /usr/bin/curl -s "https://acispedia.com/cron/http_cron.php?key=AcisPedia2024" > /dev/null 2>&1
```

## 🔧 Solusi 2: CLI Cron (Jika HTTP tidak bisa)

Jika tetap ingin menggunakan CLI cron, coba salah satu command berikut:

### Opsi 1: Direct PHP
```bash
/usr/bin/php /home/u403352430/public_html/cron/update_status.php
```

### Opsi 2: dengan wrapper
```bash
/usr/bin/php /home/u403352430/public_html/cron/cron_wrapper.php
```

### Opsi 3: dengan parameter
```bash
/usr/bin/php -f /home/u403352430/public_html/cron/update_status.php
```

## 📍 Cara Mengetahui Path yang Benar

1. Buat file `path_checker.php` di folder `cron`:
```php
<?php
echo "Current directory: " . __DIR__ . "<br>";
echo "Parent directory: " . dirname(__DIR__) . "<br>";
echo "PHP binary: " . PHP_BINARY . "<br>";
echo "PHP version: " . PHP_VERSION . "<br>";
?>
```

2. Jalankan via browser: `https://yourdomain.com/cron/path_checker.php`
3. Gunakan path yang ditampilkan untuk setup cron

## 🛠️ Troubleshooting

### Problem: "Could not open input file"
**✅ Solution**: Gunakan HTTP cron dengan curl/wget (Solusi 1 di atas)

### Problem: "Permission denied"
**✅ Solution**: 
1. Set permission file ke 755: `chmod 755 /path/to/cron/update_status.php`
2. Atau gunakan HTTP cron

### Problem: "Database connection failed"
**✅ Solution**: 
1. Pastikan path ke config database benar
2. Test akses database via `http_cron.php` terlebih dahulu

### Problem: Cron berjalan tapi tidak update status
**✅ Solution**:
1. Cek log file: `/cron/status_update.log`
2. Pastikan API Medanpedia berfungsi
3. Cek apakah ada transaksi yang perlu di-update

## 📊 Monitoring

### Cek Log
```bash
tail -f /path/to/cron/status_update.log
```

### Test Manual via Browser
```
https://yourdomain.com/cron/http_cron.php?key=AcisPedia2024
```

### Cek Status Cron di Hostinger
1. Masuk ke hPanel
2. Cron Jobs → View execution history

## 🔒 Keamanan

- File cron dilindungi dengan cron key: `AcisPedia2024`
- Akses langsung via browser memerlukan parameter `key`
- Log file otomatis dibersihkan (maksimal 100 baris)

## ⏰ Jadwal yang Disarankan

- **Setiap 5 menit**: `*/5 * * * *` (untuk monitoring aktif)
- **Setiap 10 menit**: `*/10 * * * *` (untuk penggunaan normal)
- **Setiap 15 menit**: `*/15 * * * *` (untuk menghemat resource)

## 🎯 Kesimpulan

**Untuk mengatasi error "Could not open input file":**
1. Gunakan **HTTP Cron** dengan file `http_cron.php`
2. Setup cron command dengan `curl` atau `wget`
3. Test manual via browser untuk memastikan berfungsi
4. Monitor log file untuk memastikan update status berjalan

Metode HTTP Cron lebih reliable di shared hosting dan menghindari masalah path yang kompleks.
