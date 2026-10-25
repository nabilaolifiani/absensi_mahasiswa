
<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, nama, username, role FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

if (!$admin || $admin['role'] !== 'admin') {
    http_response_code(403);
    exit('Akses ditolak. Halaman ini hanya untuk Admin.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pesan = '';
$editData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        exit('Permintaan tidak valid. Silakan muat ulang halaman.');
    }

    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah') {
        $nama = trim($_POST['nama'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';

        if ($nama === '' || $username === '' || $password === '' ||
            !in_array($role, ['admin', 'petugas'], true)) {
            $pesan = 'Semua data wajib diisi dengan benar.';
        } elseif (strlen($password) < 6) {
            $pesan = 'Password minimal 6 karakter.';
        } elseif (strlen($username) > 50 || strlen($nama) > 100) {
            $pesan = 'Nama atau username terlalu panjang.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    'INSERT INTO users (nama, username, password, role) VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$nama, $username, $hash, $role]);
                $pesan = 'Pengguna berhasil ditambahkan.';
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $pesan = 'Pengguna gagal ditambahkan. Username mungkin sudah digunakan.';
            }
        }
    }

    if ($aksi === 'edit') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $nama = trim($_POST['nama'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $role = $_POST['role'] ?? '';
        $password = $_POST['password'] ?? '';

        if (!$id || $nama === '' || $username === '' ||
            !in_array($role, ['admin', 'petugas'], true)) {
            $pesan = 'Data yang dimasukkan belum benar.';
        } elseif (strlen($nama) > 100 || strlen($username) > 50) {
            $pesan = 'Nama atau username terlalu panjang.';
        } elseif ($id === (int)$admin['id'] && $role !== 'admin') {
            $pesan = 'Role akun yang sedang digunakan tidak dapat diubah menjadi Petugas.';
        } elseif ($password !== '' && strlen($password) < 6) {
            $pesan = 'Password baru minimal 6 karakter.';
        } else {
            try {
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare(
                        'UPDATE users SET nama = ?, username = ?, password = ?, role = ? WHERE id = ?'
                    );
                    $stmt->execute([$nama, $username, $hash, $role, $id]);
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE users SET nama = ?, username = ?, role = ? WHERE id = ?'
                    );
                    $stmt->execute([$nama, $username, $role, $id]);
                }
                $pesan = 'Data pengguna berhasil diperbarui.';
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $pesan = 'Data gagal diperbarui. Username mungkin sudah digunakan.';
            }
        }
    }

    if ($aksi === 'hapus') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if (!$id) {
            $pesan = 'Pengguna tidak valid.';
        } elseif ($id === (int)$admin['id']) {
            $pesan = 'Akun yang sedang digunakan tidak dapat dihapus.';
        } else {
            try {
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
                $stmt->execute([$id]);
                $pesan = $stmt->rowCount() > 0
                    ? 'Pengguna berhasil dihapus.'
                    : 'Pengguna tidak ditemukan.';
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $pesan = 'Pengguna gagal dihapus.';
            }
        }
    }
}

if (isset($_GET['edit'])) {
    $idEdit = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);

    if ($idEdit) {
        $stmt = $pdo->prepare('SELECT id, nama, username, role FROM users WHERE id = ?');
        $stmt->execute([$idEdit]);
        $editData = $stmt->fetch();
    }
}

$stmt = $pdo->query('SELECT id, nama, username, role FROM users ORDER BY id DESC');
$pengguna = $stmt->fetchAll();

function aman($nilai) {
    return htmlspecialchars((string)$nilai, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manajemen Pengguna</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f1f5f9; color: #243b53; }
        header { background: #1769aa; color: white; padding: 20px 6%; }
        main { max-width: 1100px; margin: 30px auto; padding: 0 20px; }
        .panel { background: white; padding: 24px; margin-bottom: 22px; border-radius: 12px; }
        h2, h3 { margin-top: 0; }
        label { display: block; margin-top: 14px; font-weight: bold; }
        input, select { width: 100%; padding: 11px; margin-top: 6px; border: 1px solid #cbd5e1; border-radius: 6px; }
        button, .tombol { display: inline-block; border: 0; padding: 10px 14px; border-radius: 6px; background: #1769aa; color: white; text-decoration: none; cursor: pointer; }
        .hapus { background: #c0392b; }
        .batal { background: #64748b; }
        .pesan { background: #eaf3fb; padding: 12px; border-radius: 6px; margin-bottom: 18px; }
        .tabel { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #e2e8f0; }
        th { background: #eaf3fb; }
        form.inline { display: inline-block; }
        .aksi { white-space: nowrap; }
        @media(max-width:600px) { main { padding: 0 12px; } .panel { padding: 16px; } }
    </style>
</head>
<body>
<header>
    <h2>Sistem Informasi Absensi Mahasiswa</h2>
    <p>Manajemen Pengguna — Admin</p>
</header>

<main>
    <p><a class="tombol batal" href="dashboard.php">Kembali ke Dashboard</a></p>

    <?php if ($pesan !== ''): ?>
        <div class="pesan"><?= aman($pesan) ?></div>
    <?php endif; ?>

    <section class="panel">
        <h3><?= $editData ? 'Edit Pengguna' : 'Tambah Pengguna' ?></h3>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= aman($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="aksi" value="<?= $editData ? 'edit' : 'tambah' ?>">

            <?php if ($editData): ?>
                <input type="hidden" name="id" value="<?= (int)$editData['id'] ?>">
            <?php endif; ?>

            <label>Nama</label>
            <input type="text" name="nama" maxlength="100" required value="<?= aman($editData['nama'] ?? '') ?>">

            <label>Username</label>
            <input type="text" name="username" maxlength="50" required value="<?= aman($editData['username'] ?? '') ?>">

            <label>Password <?= $editData ? '(kosongkan jika tidak ingin mengganti)' : '' ?></label>
            <input type="password" name="password" <?= $editData ? '' : 'required' ?> minlength="6">

            <label>Role</label>
            <select name="role" required>
                <option value="admin" <?= ($editData['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="petugas" <?= ($editData['role'] ?? '') === 'petugas' ? 'selected' : '' ?>>Petugas</option>
            </select>

            <br><br>
            <button type="submit"><?= $editData ? 'Simpan Perubahan' : 'Tambah Pengguna' ?></button>
            <?php if ($editData): ?>
                <a class="tombol batal" href="pengguna.php">Batal</a>
            <?php endif; ?>
        </form>
    </section>

    <section class="panel">
        <h3>Daftar Pengguna</h3>
        <div class="tabel">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$pengguna): ?>
                        <tr><td colspan="5">Belum ada pengguna.</td></tr>
                    <?php else: ?>
                        <?php foreach ($pengguna as $i => $u): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= aman($u['nama']) ?></td>
                                <td><?= aman($u['username']) ?></td>
                                <td><?= aman(ucfirst($u['role'])) ?></td>
                                <td class="aksi">
                                    <a class="tombol" href="pengguna.php?edit=<?= (int)$u['id'] ?>">Edit</a>
                                    <?php if ((int)$u['id'] !== (int)$admin['id']): ?>
                                        <form class="inline" method="post" onsubmit="return confirm('Yakin ingin menghapus pengguna ini?')">
                                            <input type="hidden" name="csrf_token" value="<?= aman($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="aksi" value="hapus">
                                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                            <button class="hapus" type="submit">Hapus</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
