
<?php
session_start();
require_once 'config.php';

// Pastikan pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Pastikan akun masih ada dan role diambil dari database
$stmt = $pdo->prepare(
    'SELECT id, nama, username, role FROM users WHERE id = ?'
);
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

// Perbarui informasi akun dari database
$_SESSION['nama'] = $user['nama'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role'];

function aman($teks) {
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

// Hitung jumlah data untuk ditampilkan
$totalMahasiswa = (int) $pdo->query(
    'SELECT COUNT(*) FROM mahasiswa'
)->fetchColumn();

$totalMataKuliah = (int) $pdo->query(
    'SELECT COUNT(*) FROM mata_kuliah'
)->fetchColumn();

$totalAbsensi = (int) $pdo->query(
    'SELECT COUNT(*) FROM absensi'
)->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - Absensi Mahasiswa</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #243b53;
        }

        header {
            background: #1769aa;
            color: white;
            padding: 20px 6%;
        }

        header h2 {
            margin: 0 0 8px;
        }

        header p {
            margin: 0;
        }

        main {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .welcome {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 3px 12px #0000000a;
        }

        .welcome h2 {
            margin-top: 0;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .summary-card {
            background: white;
            padding: 22px;
            border-radius: 10px;
            border-left: 5px solid #1769aa;
            box-shadow: 0 3px 12px #0000000a;
        }

        .summary-card p {
            color: #627d98;
            margin: 0 0 12px;
        }

        .summary-card h2 {
            font-size: 30px;
            margin: 0;
        }

        .menu {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px #0000000a;
        }

        .menu h3 {
            margin-top: 0;
        }

        .menu-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .menu a {
            display: block;
            background: #eaf3fb;
            color: #1769aa;
            padding: 18px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
        }

        .menu a:hover {
            background: #d7e9f8;
        }

        .menu .logout {
            background: #fcebea;
            color: #b42318;
        }

        .menu .logout:hover {
            background: #f8d7d5;
        }

        footer {
            text-align: center;
            color: #627d98;
            padding: 25px 15px;
            font-size: 13px;
        }

        @media (max-width: 650px) {
            .summary {
                grid-template-columns: 1fr;
            }

            .menu-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <header>
        <h2>Sistem Informasi Absensi Mahasiswa</h2>
        <p>Dashboard Sistem Absensi</p>
    </header>

    <main>
        <section class="welcome">
            <h2>
                Selamat datang, <?= aman($user['nama']) ?>!
            </h2>

            <p>
                Username:
                <strong><?= aman($user['username']) ?></strong>
            </p>

            <p>
                Hak akses:
                <strong><?= aman(ucfirst($user['role'])) ?></strong>
            </p>

            <p>
                Silakan pilih menu di bawah untuk mengelola data
                mahasiswa dan pencatatan kehadiran.
            </p>
        </section>

        <section class="summary">
            <div class="summary-card">
                <p>Total Mahasiswa</p>
                <h2><?= $totalMahasiswa ?></h2>
            </div>

            <div class="summary-card">
                <p>Total Mata Kuliah</p>
                <h2><?= $totalMataKuliah ?></h2>
            </div>

            <div class="summary-card">
                <p>Total Catatan Absensi</p>
                <h2><?= $totalAbsensi ?></h2>
            </div>
        </section>

        <section class="menu">
            <h3>Menu Utama</h3>

            <div class="menu-list">
                <a href="mahasiswa.php">
                    Data Mahasiswa
                </a>

                <a href="absensi.php">
                    Data Absensi
                </a>

                <?php if ($user['role'] === 'admin'): ?>
                    <a href="pengguna.php">
                        Manajemen Pengguna
                    </a>
                <?php endif; ?>

                <a href="logout.php" class="logout">
                    Logout
                </a>
            </div>
        </section>
    </main>

    <footer>
        Sistem Informasi Absensi Mahasiswa
    </footer>
</body>
</html>
