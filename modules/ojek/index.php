<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Admin', 'HRD']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_ojek'])) {
    $tipe = sanitize($conn, $_POST['tipe']);
    $id_driver = sanitize($conn, $_POST['id_driver']);
    $nama_driver = sanitize($conn, $_POST['nama_driver']);
    $kendaraan = sanitize($conn, $_POST['kendaraan']);
    
    $stmt = $conn->prepare("INSERT INTO ojek_online (tipe, id_driver, nama_driver, kendaraan) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $tipe, $id_driver, $nama_driver, $kendaraan);
    if($stmt->execute()) set_flash_message('success', 'Driver Ojek Online ditambahkan.');
    else set_flash_message('error', 'Gagal menambahkan Data.');
    redirect('/pjr_parking/modules/ojek/index.php');
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM ojek_online WHERE id=$id");
    set_flash_message('success', 'Driver dihapus.');
    redirect('/pjr_parking/modules/ojek/index.php');
}

$query = mysqli_query($conn, "SELECT * FROM ojek_online ORDER BY id DESC");
require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">Data Driver Ojek Online</h4>
</div>

<?php display_flash_message(); ?>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 border-top border-success border-4">
            <div class="card-header bg-white fw-bold">Tambah Driver Baru</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="add_ojek" value="1">
                    <div class="mb-3">
                        <label class="form-label">Tipe (GRAB/GOJEK)</label>
                        <select name="tipe" class="form-select" required>
                            <option value="GRAB">GRAB</option>
                            <option value="GOJEK">GOJEK</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ID Driver / Plat Nomor Utama</label>
                        <input type="text" name="id_driver" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Driver</label>
                        <input type="text" name="nama_driver" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kendaraan (Merk & Warna)</label>
                        <input type="text" name="kendaraan" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-success w-100 fw-bold">Simpan Driver</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold">Daftar Driver</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Tipe</th>
                                <th>ID Driver</th>
                                <th>Nama</th>
                                <th>Kendaraan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($query) > 0): $no=1; while($row = mysqli_fetch_assoc($query)): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <?php if($row['tipe'] == 'GRAB'): ?>
                                        <span class="badge" style="background-color: #00B14F;">GRAB</span>
                                    <?php else: ?>
                                        <span class="badge" style="background-color: #00AA13;">GOJEK</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="text-muted fw-bold"><?= htmlspecialchars($row['id_driver']) ?></span></td>
                                <td><strong><?= htmlspecialchars($row['nama_driver']) ?></strong></td>
                                <td><?= htmlspecialchars($row['kendaraan']) ?></td>
                                <td>
                                    <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger text-white shadow-sm" onclick="return confirm('Yakin ingin menghapus driver?')"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="6" class="text-center text-muted">Tidak ada data Ojol</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
