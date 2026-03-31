<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Admin', 'HRD']);
require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';

$cabang_id = $_SESSION['user']['cabang_id'] ?? 1;
$is_super = ($_SESSION['user']['role'] === 'Super Admin');

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $where_k = $is_super ? "id = ?" : "id = ? AND cabang_id = $cabang_id";
    $stmt = $conn->prepare("DELETE FROM karyawan WHERE $where_k");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        set_flash_message('success', 'Data karyawan berhasil dihapus.');
    } else {
        set_flash_message('error', 'Gagal menghapus data.');
    }
    redirect('/pjr_parking/modules/karyawan/index.php');
}

$where_k_sel = $is_super ? "1=1" : "cabang_id = $cabang_id";
$query = "SELECT * FROM karyawan WHERE $where_k_sel ORDER BY tanggal_masuk DESC";
$result = mysqli_query($conn, $query);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">Manajemen Karyawan</h4>
    <a href="/pjr_parking/modules/karyawan/create.php" class="btn btn-primary shadow-sm"><i class="fas fa-plus"></i> Tambah Karyawan</a>
</div>

<?php display_flash_message(); ?>

<div class="card shadow-sm">
    <div class="card-header bg-white">
        Daftar Karyawan
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height: 550px; overflow-y: auto;">
            <table class="table table-hover table-striped align-middle mb-0">
                <thead class="bg-light sticky-top shadow-sm" style="z-index: 10;">
                    <tr>
                        <th width="5%">No</th>
                        <th>ID Internal</th>
                        <th>NIK</th>
                        <th>Nama Lengkap</th>
                        <th>Posisi Pekerjaan</th>
                        <th>Info Kontak</th>
                        <th>Status</th>
                        <th width="10%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php $no = 1; while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td class="text-center align-middle"><?= $no++ ?></td>
                                <td class="align-middle"><span class="badge bg-dark">KRY-<?= sprintf('%03d', $row['id']) ?></span></td>
                                <td class="align-middle"><span class="fw-bold text-primary"><?= htmlspecialchars($row['nik']) ?></span></td>
                                <td class="align-middle">
                                    <div class="fw-bold fs-6"><?= htmlspecialchars($row['nama']) ?></div>
                                    <small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars($row['alamat']) ?: '-' ?></small>
                                </td>
                                <td class="align-middle">
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($row['jabatan']) ?></div>
                                    <span class="badge bg-light border text-dark mt-1"><?= htmlspecialchars($row['divisi']) ?></span>
                                </td>
                                <td class="align-middle">
                                    <div class="small"><i class="fas fa-phone-alt text-muted me-1"></i> <?= htmlspecialchars($row['no_hp']) ?: '-' ?></div>
                                    <div class="small"><i class="fas fa-envelope text-muted me-1"></i> <?= htmlspecialchars($row['email']) ?: '-' ?></div>
                                </td>
                                <td class="align-middle">
                                    <?php if ($row['status'] == 'Aktif'): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="align-middle">
                                    <a href="/pjr_parking/modules/karyawan/edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info text-white me-1"><i class="fas fa-edit"></i></a>
                                    <a href="/pjr_parking/modules/karyawan/index.php?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus data ini?')"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">Tidak ada data karyawan ditemukan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
