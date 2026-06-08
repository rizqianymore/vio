# Uji Kualifikasi Level 4 RPL Pergudangan (Warehouse Management)

Aplikasi manajemen pergudangan/inventaris sederhana yang dibangun menggunakan PHP native prosedural (`mysqli`) dan CSS native untuk memenuhi kriteria Uji Kualifikasi Level 4 Rekayasa Perangkat Lunak SMK Telkom Jakarta Tahun Pelajaran 2025/2026.

## Kebutuhan Sistem
* PHP versi 7.4 ke atas.
* MySQL / MariaDB.
* Web server lokal seperti **XAMPP** atau **Laragon**.

## Cara Setup dan Instalasi Otomatis (Database Auto-Maker)

Project ini dilengkapi dengan **Database Auto-Maker** di dalam `config.php`. Anda **tidak perlu** mengimpor file `database.sql` secara manual lewat phpMyAdmin.

1. Salin folder project ini ke dalam direktori root web server lokal Anda:
   * Jika menggunakan **XAMPP**: Pindahkan folder ke `C:\xampp\htdocs\`
   * Jika menggunakan **Laragon**: Pindahkan folder ke `C:\laragon\www\`
2. Aktifkan modul **Apache** dan **MySQL** pada control panel XAMPP atau Laragon Anda.
3. Buka web browser Anda dan akses halaman login:
   * `http://localhost/vio/login.php` (sesuaikan `vio` dengan nama folder project Anda).
4. Ketika halaman login atau halaman apa pun diakses untuk pertama kali, sistem akan **otomatis membuat database `pergudangan`**, membuat semua tabel yang dibutuhkan, dan memasukkan data pengguna awal (seeding).

*Catatan: Jika Anda menggunakan password MySQL selain password kosong `""` atau user selain `root`, harap sesuaikan kredensial koneksi di file `config.php` terlebih dahulu.*

---

## Akun Login Bawaan (Default Users)
Untuk masuk dan menguji berbagai peran (role-based access control), Anda dapat menggunakan akun percobaan berikut:

| Username | Password | Role | Hak Akses |
| :--- | :--- | :--- | :--- |
| **staff** | staff123 | staff | Add & Update Product, Add & Update Supplier, Transaksi Masuk/Keluar |
| **manager** | manager123 | Warehouse Manager | Full CRUD Product & Supplier, Transaksi Masuk/Keluar, Cetak Laporan, Kelola User |

---

## Fitur Utama & Kriteria Penilaian UKK
Aplikasi ini telah disesuaikan dengan lembar kriteria penilaian UKK RPL 2025/2026:

1. **Dashboard Utama**: Menampilkan ringkasan total produk, total supplier, total stok masuk, dan total stok keluar.
2. **Kategori & Supplier CRUD**: Pengelolaan kategori barang dan data supplier (Nama, Alamat, No Telepon, Email).
3. **Product Management**: Pengelolaan produk dilengkapi dengan input harga (Price) dan unggah gambar produk (Product Cover).
4. **Transaksi Masuk & Keluar**:
   * **Stok Masuk**: Wajib memilih supplier. Menambah stok produk secara otomatis.
   * **Stok Keluar**: Mengurangi stok produk secara otomatis. Dilengkapi validasi mencegah stok bernilai negatif.
5. **Laporan Stok Barang (Product Stock Report)**: Laporan berformat tabel yang mendukung pencarian serta tata letak ramah cetak (Print-friendly CSS).
6. **Program Terminated Lockout**: Keamanan login dengan pembatasan percobaan maksimal 3 kali. Jika terlampaui, program akan dihentikan secara permanen (locked out) hingga sesi di-reset.
7. **Keamanan Maksimal**: Proteksi terhadap SQL Injection (Prepared Statements), Cross-Site Scripting / XSS (`htmlspecialchars`), validasi berkas unggahan gambar (Magic Bytes), dan CSRF Token pada setiap form.
