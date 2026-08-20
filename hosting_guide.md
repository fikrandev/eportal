# Panduan Hosting E-Portal

Ikuti langkah-langkah berikut untuk meng-online-kan aplikasi E-Portal Anda ke VPS atau Hosting.

## 1. Persiapan File
- Kompres seluruh folder `eportal` menjadi format **ZIP**.
- Upload file ZIP tersebut ke folder `public_html` atau root directory di hosting Anda.
- Extract file ZIP tersebut.

## 2. Persiapan Database
- Masuk ke **phpMyAdmin** di hosting Anda.
- Buat database baru (misal: `u12345_eportal`).
- Import file SQL yang ada di folder `database/` secara berurutan:
    1. `eportal.sql` (Wajib - Core)
    2. Modul lain jika diperlukan (`e_graduation.sql`, `e_sarpras.sql`, dll).

## 3. Konfigurasi
- Buka file `api/config.php` melalui File Manager hosting.
- Cari bagian `--- MANUAL CONFIGURATION ---`.
- Sesuaikan pengaturannya:
```php
define('DB_HOST', 'localhost'); // Biasanya tetap localhost
define('DB_NAME', 'u12345_eportal'); // Nama database Anda
define('DB_USER', 'u12345_user');   // Username database Anda
define('DB_PASS', 'password_anda'); // Password database Anda
```

## 4. Verifikasi (PENTING)
- Buka browser dan akses: `domain-anda.com/setup.php`
- Pastikan semua indikator berwarna **Hijau (OK)**.
- Jika Database **Failed**, periksa kembali username/password di `api/config.php`.
- Jika **BASE_URL** tidak sesuai, Anda bisa mengisinya manual di `define('BASE_URL_OVERRIDE', 'https://domain-anda.com/');`.

## 5. Keamanan
- Setelah aplikasi berjalan normal, **HAPUS** file berikut demi keamanan:
    - `setup.php`
    - `migrate.php`
    - Folder `database/` (opsional)

---
**Tips VPS (Ubuntu/Nginx):**
Jika menggunakan Nginx, pastikan `client_max_body_size` diset minimal `10M` agar upload icon/foto lancar.
