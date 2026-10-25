
<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$stmtUser = $pdo->prepare(
    'SELECT id, nama, username, role FROM users WHERE id = ?'
);
$stmtUser->execute([$_SESSION['user_id']]);
$user = $stmtUser->fetch();

if (!$user) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

$_SESSION['nama'] = $user['nama'];
$_SESSION['role'] = $user['role'];

$isAdmin = $user['role'] === 'admin';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pesan = '';
$error = '';
$editData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        http_response_code(403);
        exit('Permintaan tidak valid. Silakan kembali dan coba lagi.');
    }

    if (!$isAdmin) {
        http_response_code(403);
        exit('Akses ditolak. Hanya Admin yang boleh mengubah data mahasiswa.');
    }

    $aksi = $_POST['aksi'] ?? '';

    try {
        if ($aksi === 'tambah' || $aksi === 'edit') {
            $nim = trim($_POST['nim'] ?? '');
            $nama = trim($_POST['nama'] ?? '');
            $prodi = trim($_POST['program_studi'] ?? '');

            if ($nim === '' || $nama === '' || $prodi === '') {
                throw new RuntimeException('Semua kolom wajib diisi.');
            }

            if (
                strlen($nim) > 20 ||
                strlen($nama) > 100 ||
                strlen($prodi) > 100
            ) {
                throw new RuntimeException('Ada isian yang terlalu panjang.');
            }

            if ($aksi === 'tambah') {
                $stmt = $pdo->prepare(
                    'INSERT INTO mahasiswa (nim, nama, program_studi)
                     VALUES (?, ?, ?)'
                );
                $stmt->execute([$nim, $nama, $prodi]);
                $pesan = 'Data mahasiswa berhasil ditambahkan.';
            } else {
                $id = filter_var(
                    $_POST['id'] ?? null,
                    FILTER_VALIDATE_INT
                );

                if (!$id || $id < 1) {
                    throw new RuntimeException('ID mahasiswa tidak valid.');
                }

                $stmt = $pdo->prepare(
                    'UPDATE mahasiswa
                     SET nim = ?, nama = ?, program_studi = ?
                     WHERE id = ?'
                );
                $stmt->execute([$nim, $nama, $prodi, $id]);
                $pesan = 'Data mahasiswa berhasil diperbarui.';
            }
        } elseif ($aksi === 'hapus') {
            $id = filter_var(
                $_POST['id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id || $id < 1) {
                throw new RuntimeException('ID mahasiswa tidak valid.');
            }

            $stmt = $pdo->prepare(
                'DELETE FROM mahasiswa WHERE id = ?'
            );
            $stmt->execute([$id]);

            $pesan = $stmt->rowCount() > 0
                ? 'Data mahasiswa berhasil dihapus.'
                : 'Data mahasiswa tidak ditemukan.';
        } else {
            throw new RuntimeException('Aksi tidak dikenal.');
        }
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        error_log($e->getMessage());

        if ($e->getCode() === '23000') {
            $error = 'NIM sudah digunakan atau data masih terkait dengan absensi.';
        } else {
            $error = 'Data gagal diproses. Silakan coba lagi.';
        }
    }
}

if ($isAdmin && isset($_GET['edit'])) {
    $idEdit = filter_var($_GET['edit'], FILTER_VALIDATE_INT);

    if ($idEdit && $idEdit > 0) {
        $stmt = $pdo->prepare(
            'SELECT * FROM mahasiswa WHERE id = ?'
        );
        $stmt->execute([$idEdit]);
        $editData = $stmt->fetch();
    }
}

$stmt = $pdo->query(
    'SELECT * FROM mahasiswa ORDER BY id DESC'
);
$daftarMahasiswa = $stmt->fetchAll();

function aman($teks) {
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Data Mahasiswa</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #eef2f7;
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

        .card {
            background: white;
            padding: 24px;
            margin-bottom: 22px;
            border-radius: 12px;
            box-shadow: 0 3px 12px #0000000a;
        }

        input {
            width: 100%;
            padding: 12px;
            margin: 7px 0 16px;
            border: 1px solid #bcccdc;
            border-radius: 6px;
            font-size: 14px;
        }

        button, .link {
            display: inline-block;
            padding: 11px 15px;
            border: none;
            border-radius: 6px;
            background: #1769aa;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        button:hover, .link:hover {
            opacity: 0.88;
        }

        .hapus {
            background: #b42318;
        }

        .batal {
            background: #627d98;
        }

        .pesan {
            background: #e6f6ec;
            color: #17663a;
            padding: 13px;
            border-radius: 6px;
        }

        .error {
            background: #fef3f2;
            color: #b42318;
            padding: 13px;
            border-radius: 6px;
        }

        .info {
            background: #eaf3fb;
            color: #17527d;
            padding: 14px;
            border-radius: 6px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 550px;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid #d9e2ec;
            text-align: left;
        }

        th {
            background: #f0f4f8;
        }

        .aksi {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .aksi form {
            margin: 0;
        }

        .aksi form button {
            margin: 0;
        }

        @media (max-width: 650px) {
            main {
                padding: 0 12px;
            }

            .card {
                padding: 16px;
            }
        }
    </style>
</head>
<body>

<header>
    <h2>Sistem Informasi Absensi Mahasiswa</h2>
    <p>
        Login sebagai:
        <?= aman($user['nama']) ?>
        (<?= aman(ucfirst($user['role'])) ?>)
    </p>
</header>

<main>
    <p>
        <a class="link" href="dashboard.php">Kembali ke Dashboard</a>
    </p>

    <?php if ($pesan !== ''): ?>
        <p class="pesan"><?= aman($pesan) ?></p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="error"><?= aman($error) ?></p>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
        <section class="card">
            <h3><?= $editData ? 'Edit Mahasiswa' : 'Tambah Mahasiswa' ?></h3>

            <form method="POST" action="mahasiswa.php">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= aman($_SESSION['csrf_token']) ?>"
                >

                <input
                    type="hidden"
                    name="aksi"
                    value="<?= $editData ? 'edit' : 'tambah' ?>"
                >

                <?php if ($editData): ?>
                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int)$editData['id'] ?>"
                    >
                <?php endif; ?>

                <label for="nim">NIM</label>
                <input
                    id="nim"
                    name="nim"
                    maxlength="20"
                    required
                    value="<?= aman($editData['nim'] ?? '') ?>"
                >

                <label for="nama">Nama Mahasiswa</label>
                <input
                    id="nama"
                    name="nama"
                    maxlength="100"
                    required
                    value="<?= aman($editData['nama'] ?? '') ?>"
                >

                <label for="program_studi">Program Studi</label>
                <input
                    id="program_studi"
                    name="program_studi"
                    maxlength="100"
                    required
                    value="<?= aman($editData['program_studi'] ?? '') ?>"
                >

                <button type="submit">
                    <?= $editData ? 'Simpan Perubahan' : 'Tambah Data' ?>
                </button>

                <?php if ($editData): ?>
                    <a class="link batal" href="mahasiswa.php">Batal Edit</a>
                <?php endif; ?>
            </form>
        </section>
    <?php else: ?>
        <p class="info">
            Kamu login sebagai Petugas. Kamu dapat melihat data mahasiswa,
            tetapi tidak dapat menambah, mengedit, atau menghapus data.
        </p>
    <?php endif; ?>

    <section class="card">
        <h3>Daftar Mahasiswa</h3>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>NIM</th>
                        <th>Nama</th>
                        <th>Program Studi</th>
                        <?php if ($isAdmin): ?>
                            <th>Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($daftarMahasiswa) > 0): ?>
                        <?php foreach ($daftarMahasiswa as $i => $mhs): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= aman($mhs['nim']) ?></td>
                                <td><?= aman($mhs['nama']) ?></td>
                                <td><?= aman($mhs['program_studi']) ?></td>

                                <?php if ($isAdmin): ?>
                                    <td>
                                        <div class="aksi">
                                            <a
                                                class="link"
                                                href="mahasiswa.php?edit=<?= (int)$mhs['id'] ?>"
                                            >
                                                Edit
                                            </a>

                                            <form
                                                method="POST"
                                                action="mahasiswa.php"
                                                onsubmit="return confirm('Yakin ingin menghapus mahasiswa ini?');"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= aman($_SESSION['csrf_token']) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="aksi"
                                                    value="hapus"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int)$mhs['id'] ?>"
                                                >

                                                <button class="hapus" type="submit">
                                                    Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= $isAdmin ? 5 : 4 ?>">
                                Belum ada data mahasiswa.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

</body>
</html>
