<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Admin', 'HRD']);
require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';

$cabang_id = $_SESSION['user']['cabang_id'] ?? 1;
$is_super = ($_SESSION['user']['role'] === 'Super Admin');

// Handle Toggle Status
if (isset($_GET['toggle_status']) && isset($_GET['to'])) {
    $id = (int)$_GET['toggle_status'];
    $to = sanitize($conn, $_GET['to']);
    $up_q = $is_super ? "UPDATE member SET status='$to' WHERE id=$id" : "UPDATE member SET status='$to' WHERE id=$id AND cabang_id=$cabang_id";
    mysqli_query($conn, $up_q);
    set_flash_message('success', 'Status member direkam menjadi ' . $to . '.');
    redirect('/modules/member/index.php');
}

// Handle Delete (Permanent)
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $del_q = $is_super ? "DELETE FROM member WHERE id = $id" : "DELETE FROM member WHERE id = $id AND cabang_id = $cabang_id";
    mysqli_query($conn, $del_q);
    set_flash_message('success', 'Data member dihapus permanen dari sistem.');
    redirect('/modules/member/index.php');
}

$where_m = $is_super ? "1=1" : "m.cabang_id = $cabang_id";
$query = "
    SELECT m.*, 
    (SELECT COUNT(id) FROM member_detail md WHERE md.member_id = m.id) as total_personil 
    FROM member m 
    WHERE $where_m
    ORDER BY m.id DESC";
$result = mysqli_query($conn, $query);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">Manajemen Member</h4>
    <a href="/modules/member/create.php" class="btn btn-primary shadow-sm"><i class="fas fa-plus"></i> Tambah Member</a>
</div>

<?php display_flash_message(); ?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        Daftar Member (Perusahaan & Individual)
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height: 550px; overflow-y: auto;">
            <table class="table table-hover table-striped align-middle mb-0">
                <thead class="bg-light sticky-top shadow-sm" style="z-index: 10;">
                    <tr>
                        <th>ID</th>
                        <th>Nama Member</th>
                        <th>Jenis</th>
                        <th>Tipe Pembayaran</th>
                        <th>Jatuh Tempo</th>
                        <th>Jml Personil</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><span class="fw-bold text-muted">#<?= $row['id'] ?></span></td>
                                <td><?= htmlspecialchars($row['nama']) ?></td>
                                <td><span class="badge bg-secondary"><?= $row['jenis_member'] ?></span></td>
                                <td>
                                    <?php if ($row['tipe_pembayaran'] == 'Kolektif'): ?>
                                        <span class="badge bg-primary">Kolektif</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark">Individual</span>
                                    <?php endif; ?>
                                </td>
                                <td>Tgl <span class="fw-bold"><?= $row['tanggal_jatuh_tempo'] ?></span></td>
                                <td>
                                    <?php if ($row['total_personil'] > 0 || $row['jenis_member'] == 'Perusahaan'): ?>
                                        <span class="badge bg-dark"><?= $row['total_personil'] ?> Personil</span>
                                    <?php else: ?>
                                        - 
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['status'] == 'Aktif'): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['status'] == 'Aktif'): ?>
                                        <a href="?toggle_status=<?= $row['id'] ?>&to=Tidak Aktif" class="btn btn-sm btn-warning shadow-sm" title="Nonaktifkan (Soft Delete)" onclick="return confirm('Nonaktifkan member ini? Histori bayarnya akan tetap aman dan tidak terhapus dilaporkan.')"><i class="fas fa-user-slash"></i></a>
                                    <?php else: ?>
                                        <a href="?toggle_status=<?= $row['id'] ?>&to=Aktif" class="btn btn-sm btn-success shadow-sm" title="Aktifkan Kembali" onclick="return confirm('Aktifkan kembali member ini?')"><i class="fas fa-user-check"></i></a>
                                    <?php endif; ?>
                                    <a href="/modules/member/detail.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary shadow-sm" title="Kelola Personil"><i class="fas fa-users"></i></a>
                                    <a href="/modules/member/edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info text-white shadow-sm" title="Edit Data"><i class="fas fa-edit"></i></a>
                                    <?php if($is_super): ?>
                                    <a href="/modules/member/index.php?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger shadow-sm" title="Hapus Permanen" onclick="return confirm('HAPUS PERMANEN? Perhatian: Semua detail personil dan histori pembayarannya akan ikut terhapus dari sistem!')"><i class="fas fa-trash"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center">Tidak ada data member</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
