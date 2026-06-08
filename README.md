# UKK RPL Pergudangan (Warehouse Management)

Aplikasi manajemen pergudangan/inventaris sederhana yang dibangun menggunakan PHP native prosedural (`mysqli`) dan CSS native untuk memenuhi kriteria uji kompetensi kejuruan (UKK) Rekayasa Perangkat Lunak.

## Kebutuhan Sistem
* PHP versi 7.4 ke atas.
* MySQL / MariaDB.
* Web server lokal seperti **XAMPP** atau **Laragon**.

## Cara Setup dan Instalasi

### Langkah 1: Pindahkan File Project
Salin folder project ini ke dalam direktori root web server lokal Anda:
* Jika menggunakan **XAMPP**: Pindahkan folder ke `C:\xampp\htdocs\`
* Jika menggunakan **Laragon**: Pindahkan folder ke `C:\laragon\www\`

### Langkah 2: Setup Database
1. Aktifkan modul **Apache** dan **MySQL** pada control panel XAMPP atau Laragon Anda.
2. Buka tool administrasi database seperti **phpMyAdmin** (`http://localhost/phpmyadmin`) atau **HeidiSQL**.
3. Buat database baru dengan nama:
   ```sql
   pergudangan
   ```
4. Pilih database `pergudangan`, lalu impor berkas database schema yang sudah disediakan:
   - Lokasi file: `database.sql` (berada di root folder project ini).
   - Atau salin seluruh isi query di file `database.sql` dan jalankan (Execute) di tab SQL phpMyAdmin/HeidiSQL.

### Langkah 3: Konfigurasi Database (Opsional)
Jika Anda menggunakan username/password database MySQL yang berbeda dari bawaan default:
1. Buka file `config.php` di editor kode Anda.
2. Cari baris konstanta kredensial berikut dan sesuaikan nilainya:
   ```php
   define('DB_HOST', '127.0.0.1');
   define('DB_USER', 'root');      // Ganti jika username mysql bukan root
   define('DB_PASS', '');          // Masukkan password mysql jika ada
   define('DB_NAME', 'pergudangan');
   ```

### Langkah 4: Jalankan Aplikasi

#### Pilihan A: Menggunakan Web Server Bawaan (XAMPP/Laragon)
1. Buka web browser Anda.
2. Akses alamat URL berikut:
   * `http://localhost/vio/login.php` (sesuaikan `vio` dengan nama folder project Anda).

#### Pilihan B: Menggunakan WSL / CLI (PHP CLI Server)
Jika Anda menggunakan **WSL** (Windows Subsystem for Linux) atau Linux CLI tanpa XAMPP/Laragon, Anda bisa menjalankan server bawaan PHP:
1. Pastikan Anda berada di direktori project:
   ```bash
   cd /home/pentagon/web_laravel/vio
   ```
2. Impor database dari terminal WSL (jika belum):
   ```bash
   # Masuk ke MySQL CLI sebagai root
   sudo mysql -u root

   # Di dalam MySQL CLI, jalankan perintah ini:
   CREATE DATABASE IF NOT EXISTS pergudangan;
   USE pergudangan;
   SOURCE database.sql;
   EXIT;
   ```
3. Jalankan server pengembangan bawaan PHP di port 8000:
   ```bash
   php -S localhost:8000
   ```
4. Buka browser di Windows Anda, lalu akses URL:
   * `http://localhost:8000/login.php`


---

## Akun Login Bawaan (Default Users)
Untuk masuk dan menguji berbagai peran (role-based access control), Anda dapat menggunakan akun percobaan berikut (semua password default adalah **password**):

| Username | Password | Role | Hak Akses |
| :--- | :--- | :--- | :--- |
| **admin** | password | Admin | Kelola User, Kategori, Barang, Input Transaksi, Cetak Laporan |
| **petugas** | password | Petugas | Kelola Kategori, Barang, Input Transaksi, Cetak Laporan |
| **pegawai** | password | Pegawai | Lihat Kategori, Lihat Barang, Cetak Laporan |

---

## Fitur Utama & Keamanan Tambahan
Aplikasi ini telah dilengkapi dengan proteksi keamanan standar untuk memastikan nilai kelulusan UKK maksimal:
* **SQL Injection Prevention**: Seluruh query database yang memuat input dinamis menggunakan *Prepared Statements* (`mysqli_prepare`).
* **Cross-Site Scripting (XSS) Prevention**: Seluruh output data di dalam HTML telah disaring menggunakan fungsi `htmlspecialchars`.
* **CSRF Protection**: Form submit dilengkapi dengan token validasi CSRF untuk mencegah eksploitasi request palsu.
* **Brute Force Protection**: Membatasi percobaan login yang salah maksimal 3 kali. Jika terlampaui, login dikunci selama 5 menit.
* **Secure File Upload**: Validasi ukuran foto maksimal 2MB, validasi ekstensi file gambar, serta verifikasi isi file asli melalui *Magic Bytes* (MIME type). Berkas gambar yang diunggah akan otomatis diubah namanya menjadi nama acak (random hash) untuk menghindari eksekusi script berbahaya.
