<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Admin', 'Bendahara']);

$cabang_id = $_SESSION['user']['cabang_id'] ?? 1;
$is_super = ($_SESSION['user']['role'] === 'Super Admin');

// Handle Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_parkir'])) {
    $tanggal = sanitize($conn, $_POST['tanggal']);
    $qris = (float)$_POST['qris'];
    $non_tunai = (float)$_POST['non_tunai'];
    $tunai = (float)$_POST['tunai'];
    $bermasalah = (float)$_POST['bermasalah'];
    
    // Check if duplicate date
    $check = mysqli_query($conn, "SELECT id FROM pendapatan_parkir WHERE tanggal='$tanggal' AND cabang_id=$cabang_id");
    if(mysqli_num_rows($check) > 0) {
        set_flash_message('error', 'Data pendapatan parkir untuk tanggal tersebut sudah ada! Silahkan edit jika perlu.');
    } else {
        $stmt = $conn->prepare("INSERT INTO pendapatan_parkir (cabang_id, tanggal, qris, non_tunai, tunai, bermasalah) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isdddd", $cabang_id, $tanggal, $qris, $non_tunai, $tunai, $bermasalah);
        if($stmt->execute()) set_flash_message('success', 'Data Pendapatan Parkir disimpan.');
        else set_flash_message('error', 'Gagal menyimpan!');
    }
    redirect('/modules/pemasukan/parkir.php');
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $del = $is_super ? "DELETE FROM pendapatan_parkir WHERE id=$id" : "DELETE FROM pendapatan_parkir WHERE id=$id AND cabang_id=$cabang_id";
    mysqli_query($conn, $del);
    set_flash_message('success', 'Data Parkir dihapus.');
    redirect('/modules/pemasukan/parkir.php');
}

$where_p = $is_super ? "1=1" : "cabang_id = $cabang_id";
$query = mysqli_query($conn, "SELECT * FROM pendapatan_parkir WHERE $where_p ORDER BY tanggal DESC LIMIT 30");

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">Pendapatan Parkir Harian</h4>
</div>

<?php display_flash_message(); ?>

<div class="card shadow-sm border-0 border-top border-primary border-4 mb-4">
    <div class="card-header bg-white fw-bold">Input Pendapatan Harian</div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="add_parkir" value="1">
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label fw-bold">Tanggal</label>
                    <input type="date" name="tanggal" class="form-control" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold text-warning">Tunai</label>
                    <input type="number" name="tunai" class="form-control" value="0" min="0" step="1000">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold text-success">QRIS</label>
                    <input type="number" name="qris" class="form-control" value="0" min="0" step="1000">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold text-info">Non Tunai</label>
                    <input type="number" name="non_tunai" class="form-control" value="0" min="0" step="1000">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold text-danger">Bermasalah</label>
                    <input type="number" name="bermasalah" class="form-control" value="0" min="0" step="1000" title="Uang palsu, selisih kurang, dll">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="fas fa-save me-1"></i> Simpan</button>
                </div>
            </div>
            <div class="text-muted small mt-2">* Sistem otomatis menghitung Total Bersih = (QRIS + Non Tunai + Tunai) - Bermasalah.</div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white fw-bold">Histori Pendapatan Parkir (30 Hari Terakhir)</div>
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height: 550px; overflow-y: auto;">
            <table class="table table-hover table-striped mb-0 text-center align-middle">
                <thead class="bg-light sticky-top shadow-sm" style="z-index: 10;">
                    <tr>
                        <th class="text-start ps-4">Tanggal</th>
                        <th class="text-warning">Tunai</th>
                        <th class="text-success">QRIS</th>
                        <th class="text-info">Non Tunai</th>
                        <th class="text-primary">Total Pendapatan</th>
                        <th class="text-danger">Bermasalah</th>
                        <th class="fw-bold bg-light">TOTAL BERSIH</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($query) > 0): while($row = mysqli_fetch_assoc($query)): ?>
                    <tr>
                        <td class="text-start ps-4 fw-bold"><?= date('d M Y', strtotime($row['tanggal'])) ?></td>
                        <td><?= format_rupiah($row['tunai']) ?></td>
                        <td><?= format_rupiah($row['qris']) ?></td>
                        <td><?= format_rupiah($row['non_tunai']) ?></td>
                        <td class="fw-bold text-primary"><?= format_rupiah($row['total_pendapatan']) ?></td>
                        <td class="text-danger">-<?= format_rupiah($row['bermasalah']) ?></td>
                        <td class="fw-bold text-success fs-6 bg-light border-start border-end"><?= format_rupiah($row['total_bersih']) ?></td>
                        <td>
                            <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger shadow-sm" onclick="return confirm('Hapus record ini?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="8" class="text-muted py-4">Belum ada data pendapatan parkir.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
