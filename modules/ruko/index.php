<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Admin', 'HRD', 'Bendahara']);

$cabang_id = $_SESSION['user']['cabang_id'] ?? 1;
$is_super = ($_SESSION['user']['role'] === 'Super Admin');

// Handle Add Custom Inline (Quick add)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_ruko'])) {
    $nama_ruko = sanitize($conn, $_POST['nama_ruko']);
    $nominal = (float)$_POST['nominal_iuran'];
    $jatuh_tempo = (int)$_POST['tanggal_jatuh_tempo'];
    
    $stmt = $conn->prepare("INSERT INTO ruko (cabang_id, nama_ruko, nominal_iuran, tanggal_jatuh_tempo) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isdi", $cabang_id, $nama_ruko, $nominal, $jatuh_tempo);
    if($stmt->execute()) set_flash_message('success', 'Data Ruko ditambahkan.');
    else set_flash_message('error', 'Gagal menambahkan Data.');
    redirect('/modules/ruko/index.php');
}

// Handle Toggle Status
if (isset($_GET['toggle_status']) && isset($_GET['to'])) {
    $id = (int)$_GET['toggle_status'];
    $to = sanitize($conn, $_GET['to']);
    $up_q = $is_super ? "UPDATE ruko SET status='$to' WHERE id=$id" : "UPDATE ruko SET status='$to' WHERE id=$id AND cabang_id=$cabang_id";
    mysqli_query($conn, $up_q);
    set_flash_message('success', 'Status ruko direkam menjadi ' . $to . '.');
    redirect('/modules/ruko/index.php');
}

// Handle Delete Permanen
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $del = $is_super ? "DELETE FROM ruko WHERE id=$id" : "DELETE FROM ruko WHERE id=$id AND cabang_id=$cabang_id";
    mysqli_query($conn, $del);
    set_flash_message('success', 'Data ruko dihapus permanen.');
    redirect('/modules/ruko/index.php');
}

$where_r = $is_super ? "1=1" : "cabang_id = $cabang_id";
$query = mysqli_query($conn, "SELECT * FROM ruko WHERE $where_r ORDER BY id DESC");
require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">Manajemen Iuran Ruko</h4>
</div>

<?php display_flash_message(); ?>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 border-top border-primary border-4">
            <div class="card-header bg-white fw-bold">Tambah Ruko Baru</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="add_ruko" value="1">
                    <div class="mb-3">
                        <label class="form-label">Nama Ruko / Blok</label>
                        <input type="text" name="nama_ruko" class="form-control" required placeholder="Contoh: Ruko Blok A1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nominal Iuran Bulanan</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" name="nominal_iuran" class="form-control" required min="0" step="1000">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tanggal Jatuh Tempo (1-31)</label>
                        <input type="number" name="tanggal_jatuh_tempo" class="form-control" required min="1" max="31">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Simpan Ruko</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold">Daftar Ruko / Blok</div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 550px; overflow-y: auto;">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="bg-light sticky-top shadow-sm" style="z-index: 10;">
                            <tr>
                                <th>#</th>
                                <th>Nama Ruko</th>
                                <th>Iuran/Bulan</th>
                                <th>Jatuh Tempo</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($query) > 0): $no=1; while($row = mysqli_fetch_assoc($query)): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><strong><?= htmlspecialchars($row['nama_ruko']) ?></strong></td>
                                <td><span class="text-success fw-bold"><?= format_rupiah($row['nominal_iuran']) ?></span></td>
                                <td>Tgl <?= $row['tanggal_jatuh_tempo'] ?></td>
                                <td>
                                    <?php if($row['status'] == 'Aktif'): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['status'] == 'Aktif'): ?>
                                        <a href="?toggle_status=<?= $row['id'] ?>&to=Tidak Aktif" class="btn btn-sm btn-warning shadow-sm" title="Nonaktifkan (Soft Delete)" onclick="return confirm('Nonaktifkan ruko ini? Histori bayarnya akan tetap aman dan tidak terhapus.')"><i class="fas fa-store-slash"></i></a>
                                    <?php else: ?>
                                        <a href="?toggle_status=<?= $row['id'] ?>&to=Aktif" class="btn btn-sm btn-success shadow-sm" title="Aktifkan Kembali" onclick="return confirm('Aktifkan kembali ruko ini?')"><i class="fas fa-check"></i></a>
                                    <?php endif; ?>
                                    
                                    <?php if($is_super): ?>
                                    <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger text-white shadow-sm" title="Hapus Permanen" onclick="return confirm('HAPUS PERMANEN? Semua histori bayar akan ikut lenyap!')"><i class="fas fa-trash"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="6" class="text-center text-muted">Tidak ada data Ruko</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
