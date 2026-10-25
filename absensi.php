
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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pesan = '';
$error = '';
$editData = null;
$statusValid = ['Hadir', 'Izin', 'Sakit', 'Alpa'];

function aman($teks) {
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

function tanggalValid($tanggal) {
    if (!is_string($tanggal)) {
        return false;
    }

    $date = DateTime::createFromFormat('!Y-m-d', $tanggal);

    return $date && $date->format('Y-m-d') === $tanggal;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        http_response_code(403);
        exit('Permintaan tidak valid. Silakan kembali dan coba lagi.');
    }

    $aksi = $_POST['aksi'] ?? '';

    try {
        if ($aksi === 'tambah' || $aksi === 'edit') {
            $mahasiswaId = filter_var(
                $_POST['mahasiswa_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $mataKuliahId = filter_var(
                $_POST['mata_kuliah_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $tanggal = $_POST['tanggal'] ?? '';
            $status = $_POST['status'] ?? '';

            if (
                !$mahasiswaId || $mahasiswaId < 1 ||
                !$mataKuliahId || $mataKuliahId < 1
            ) {
                throw new RuntimeException(
                    'Pilih mahasiswa dan mata kuliah yang valid.'
                );
            }

            if (!tanggalValid($tanggal)) {
                throw new RuntimeException(
                    'Tanggal tidak valid.'
                );
            }

            if (!in_array($status, $statusValid, true)) {
                throw new RuntimeException(
                    'Status kehadiran tidak valid.'
                );
            }

            $cekMahasiswa = $pdo->prepare(
                'SELECT id FROM mahasiswa WHERE id = ?'
            );
            $cekMahasiswa->execute([$mahasiswaId]);

            if (!$cekMahasiswa->fetch()) {
                throw new RuntimeException(
                    'Mahasiswa tidak ditemukan.'
                );
            }

            $cekMataKuliah = $pdo->prepare(
                'SELECT id FROM mata_kuliah WHERE id = ?'
            );
            $cekMataKuliah->execute([$mataKuliahId]);

            if (!$cekMataKuliah->fetch()) {
                throw new RuntimeException(
                    'Mata kuliah tidak ditemukan.'
                );
            }

            if ($aksi === 'tambah') {
                $stmt = $pdo->prepare(
                    'INSERT INTO absensi
                    (mahasiswa_id, mata_kuliah_id, tanggal, status)
                    VALUES (?, ?, ?, ?)'
                );

                $stmt->execute([
                    $mahasiswaId,
                    $mataKuliahId,
                    $tanggal,
                    $status
                ]);

                $pesan = 'Data absensi berhasil ditambahkan.';
            } else {
                $id = filter_var(
                    $_POST['id'] ?? null,
                    FILTER_VALIDATE_INT
                );

                if (!$id || $id < 1) {
                    throw new RuntimeException(
                        'ID absensi tidak valid.'
                    );
                }

                $stmt = $pdo->prepare(
                    'UPDATE absensi
                    SET mahasiswa_id = ?,
                        mata_kuliah_id = ?,
                        tanggal = ?,
                        status = ?
                    WHERE id = ?'
                );

                $stmt->execute([
                    $mahasiswaId,
                    $mataKuliahId,
                    $tanggal,
                    $status,
                    $id
                ]);

                $pesan = $stmt->rowCount() >= 0
                    ? 'Data absensi berhasil diperbarui.'
                    : 'Data absensi gagal diperbarui.';
            }
        } elseif ($aksi === 'hapus') {
            $id = filter_var(
                $_POST['id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id || $id < 1) {
                throw new RuntimeException(
                    'ID absensi tidak valid.'
                );
            }

            $stmt = $pdo->prepare(
                'DELETE FROM absensi WHERE id = ?'
            );
            $stmt->execute([$id]);

            $pesan = $stmt->rowCount() > 0
                ? 'Data absensi berhasil dihapus.'
                : 'Data absensi tidak ditemukan.';
        } else {
            throw new RuntimeException(
                'Aksi tidak dikenal.'
            );
        }
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        error_log($e->getMessage());

        if ($e->getCode() === '23000') {
            $error = 'Absensi untuk mahasiswa, mata kuliah, dan tanggal tersebut sudah ada.';
        } else {
            $error = 'Data absensi gagal diproses. Silakan coba lagi.';
        }
    }
}

if (isset($_GET['edit'])) {
    $idEdit = filter_var($_GET['edit'], FILTER_VALIDATE_INT);

    if ($idEdit && $idEdit > 0) {
        $stmt = $pdo->prepare(
            'SELECT * FROM absensi WHERE id = ?'
        );
        $stmt->execute([$idEdit]);
        $editData = $stmt->fetch();
    }
}

$mahasiswa = $pdo->query(
    'SELECT id, nim, nama FROM mahasiswa ORDER BY nama ASC'
)->fetchAll();

$mataKuliah = $pdo->query(
    'SELECT id, nama_mk FROM mata_kuliah ORDER BY nama_mk ASC'
)->fetchAll();

$stmt = $pdo->query(
    'SELECT a.id, a.tanggal, a.status,
            m.nim, m.nama,
            mk.nama_mk
     FROM absensi a
     INNER JOIN mahasiswa m ON a.mahasiswa_id = m.id
     INNER JOIN mata_kuliah mk ON a.mata_kuliah_id = mk.id
     ORDER BY a.tanggal DESC, a.id DESC'
);
$daftarAbsensi = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Data Absensi Mahasiswa</title>

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
            max-width: 1200px;
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

        input, select {
            width: 100%;
            padding: 12px;
            margin: 7px 0 16px;
            border: 1px solid #bcccdc;
            border-radius: 6px;
            background: white;
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
            padding: 14px;
            border-radius: 6px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
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

    <section class="card">
        <h3><?= $editData ? 'Edit Absensi' : 'Tambah Absensi' ?></h3>

        <?php if (count($mahasiswa) === 0 || count($mataKuliah) === 0): ?>
            <p class="info">
                Tambahkan data mahasiswa dan mata kuliah terlebih dahulu sebelum mencatat absensi.
            </p>
        <?php else: ?>
            <form method="POST" action="absensi.php">
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

                <label for="mahasiswa_id">Mahasiswa</label>
                <select id="mahasiswa_id" name="mahasiswa_id" required>
                    <option value="">-- Pilih Mahasiswa --</option>

                    <?php foreach ($mahasiswa as $m): ?>
                        <option
                            value="<?= (int)$m['id'] ?>"
                            <?= (string)($editData['mahasiswa_id'] ?? '') === (string)$m['id'] ? 'selected' : '' ?>
                        >
                            <?= aman($m['nim']) ?> - <?= aman($m['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="mata_kuliah_id">Mata Kuliah</label>
                <select id="mata_kuliah_id" name="mata_kuliah_id" required>
                    <option value="">-- Pilih Mata Kuliah --</option>

                    <?php foreach ($mataKuliah as $mk): ?>
                        <option
                            value="<?= (int)$mk['id'] ?>"
                            <?= (string)($editData['mata_kuliah_id'] ?? '') === (string)$mk['id'] ? 'selected' : '' ?>
                        >
                            <?= aman($mk['nama_mk']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="tanggal">Tanggal Absensi</label>
                <input
                    type="date"
                    id="tanggal"
                    name="tanggal"
                    required
                    value="<?= aman($editData['tanggal'] ?? date('Y-m-d')) ?>"
                >

                <label for="status">Status Kehadiran</label>
                <select id="status" name="status" required>
                    <option value="">-- Pilih Status --</option>

                    <?php foreach ($statusValid as $s): ?>
                        <option
                            value="<?= aman($s) ?>"
                            <?= ($editData['status'] ?? '') === $s ? 'selected' : '' ?>
                        >
                            <?= aman($s) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">
                    <?= $editData ? 'Simpan Perubahan' : 'Simpan Absensi' ?>
                </button>

                <?php if ($editData): ?>
                    <a class="link batal" href="absensi.php">Batal Edit</a>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </section>

    <section class="card">
        <h3>Daftar Absensi Mahasiswa</h3>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>NIM</th>
                        <th>Nama Mahasiswa</th>
                        <th>Mata Kuliah</th>
                        <th>Tanggal</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($daftarAbsensi) > 0): ?>
                        <?php foreach ($daftarAbsensi as $i => $a): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= aman($a['nim']) ?></td>
                                <td><?= aman($a['nama']) ?></td>
                                <td><?= aman($a['nama_mk']) ?></td>
                                <td><?= aman($a['tanggal']) ?></td>
                                <td><?= aman($a['status']) ?></td>
                                <td>
                                    <div class="aksi">
                                        <a
                                            class="link"
                                            href="absensi.php?edit=<?= (int)$a['id'] ?>"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            action="absensi.php"
                                            onsubmit="return confirm('Yakin ingin menghapus catatan absensi ini?');"
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
                                                value="<?= (int)$a['id'] ?>"
                                            >

                                            <button class="hapus" type="submit">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">Belum ada catatan absensi.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
