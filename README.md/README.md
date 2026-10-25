
# Sistem Informasi Absensi Mahasiswa

## Deskripsi
Aplikasi ini dibuat untuk membantu pengelolaan data mahasiswa dan pencatatan kehadiran mahasiswa pada mata kuliah. Aplikasi menggunakan PHP dan MySQL.

## Teknologi
- PHP
- MySQL
- HTML dan CSS
- XAMPP
- Visual Studio Code

## Fitur
- Login dan logout
- Dashboard
- Data mahasiswa
- Data absensi mahasiswa
- Manajemen akun pengguna
- Hak akses Admin dan Petugas

## Database
Nama database: `db_absensi_mahasiswa`

Tabel yang digunakan:
1. `users` untuk menyimpan akun pengguna.
2. `mahasiswa` untuk menyimpan data mahasiswa.
3. `mata_kuliah` untuk menyimpan data mata kuliah.
4. `absensi` untuk menyimpan catatan kehadiran mahasiswa.

## Cara Menjalankan
1. Jalankan Apache dan MySQL melalui XAMPP.
2. Simpan folder aplikasi di `C:\xampp\htdocs\absensi_mahasiswa`.
3. Pastikan database `db_absensi_mahasiswa` sudah tersedia.
4. Periksa pengaturan database pada file `config.php`.
5. Buka browser dan akses `http://localhost/absensi_mahasiswa/login.php`.
6. Masuk menggunakan akun yang sudah terdaftar.

## Hak Akses
Admin dapat mengelola data mahasiswa, data absensi, dan akun pengguna.

Petugas dapat melihat data mahasiswa dan menggunakan fitur absensi, tetapi tidak dapat membuka halaman manajemen pengguna.

## Keamanan
- Password disimpan menggunakan hash.
- Session digunakan untuk mengatur status login.
- Hak akses diperiksa di sisi server.
- Prepared statements digunakan untuk query database.
- Token CSRF digunakan untuk melindungi formulir perubahan data.

## Identitas Pengembang
Nama: Nabila Olifiani
NIM: 2500870
Mata Kuliah: Praktikum Sistem Basis Data
Tahun: 2026
