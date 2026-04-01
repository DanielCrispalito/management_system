<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Admin', 'Bendahara']);

$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

$cabang_id = $_SESSION['user']['cabang_id'] ?? 1;
$is_super = ($_SESSION['user']['role'] === 'Super Admin');

// Handle Delete
if (isset($_GET['delete_bayar'])) {
    $del_id = (int)$_GET['delete_bayar'];
    mysqli_query($conn, "DELETE FROM pembayaran_ruko WHERE id = $del_id");
    set_flash_message('success', 'Data pembayaran ruko dihapus.');
    redirect("/modules/pembayaran/ruko.php?bulan=$bulan&tahun=$tahun");
}

// Handle Process Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bayar_ruko'])) {
    $ruko_id = (int)$_POST['ruko_id'];
    $jumlah = (float)$_POST['jumlah'];
    $tanggal_bayar = date('Y-m-d'); // Today
    
    $stmt = $conn->prepare("INSERT INTO pembayaran_ruko (ruko_id, bulan, tahun, jumlah, tanggal_bayar) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiids", $ruko_id, $bulan, $tahun, $jumlah, $tanggal_bayar);
    
    if($stmt->execute()) {
        set_flash_message('success', 'Pembayaran Ruko Berhasil Tersimpan.');
    } else {
        set_flash_message('error', 'Gagal memproses pembayaran!');
    }
    redirect("/modules/pembayaran/ruko.php?bulan=$bulan&tahun=$tahun");
}

// Fetch Data Ruko along with their payment status for selected month & year
$whereClause = $is_super ? "r.status = 'Aktif'" : "r.status = 'Aktif' AND r.cabang_id = $cabang_id";
$query = mysqli_query($conn, "
    SELECT r.*, 
    IFNULL((SELECT SUM(jumlah) FROM pembayaran_ruko WHERE ruko_id = r.id AND bulan = $bulan AND tahun = $tahun), 0) as total_dibayar
    FROM ruko r 
    WHERE $whereClause
    ORDER BY r.nama_ruko ASC
");

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">Pembayaran Iuran Ruko</h4>
</div>

<?php display_flash_message(); ?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body bg-light rounded d-flex align-items-center justify-content-between">
        <form method="GET" class="d-flex w-100 align-items-end">
            <div class="me-3" style="width: 150px;">
                <label class="form-label fw-bold text-muted small">Bulan</label>
                <select name="bulan" class="form-select">
                    <?php 
                    $months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                    for($i=1; $i<=12; $i++): 
                        $sel = ($i == $bulan) ? 'selected' : '';
                        echo "<option value='$i' $sel>{$months[$i-1]}</option>";
                    endfor; 
                    ?>
                </select>
            </div>
            <div class="me-3" style="width: 120px;">
                <label class="form-label fw-bold text-muted small">Tahun</label>
                <input type="number" name="tahun" class="form-control" value="<?= $tahun ?>" min="2020" max="2050">
            </div>
            <button type="submit" class="btn btn-primary px-4"><i class="fas fa-filter me-2"></i> Filter Data</button>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0 border-top border-primary border-4">
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height: 550px; overflow-y: auto;">
            <table class="table table-hover table-striped align-middle text-center mb-0">
                <thead class="bg-light sticky-top shadow-sm" style="z-index: 10;">
                    <tr>
                        <th class="ps-4 text-start">Nama Ruko</th>
                        <th>Tagihan/Bulan</th>
                        <th>Jatuh Tempo</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($query) > 0): while($row = mysqli_fetch_assoc($query)): ?>
                    <tr>
                        <td class="text-start ps-4 fw-bold"><?= htmlspecialchars($row['nama_ruko']) ?></td>
                        <td class="text-success fw-bold"><?= format_rupiah($row['nominal_iuran']) ?></td>
                        <td>Tgl <?= $row['tanggal_jatuh_tempo'] ?></td>
                        <td>
                            <?php 
                                $target = (float)$row['nominal_iuran'];
                                $dibayar = (float)$row['total_dibayar'];
                                $sisa = $target - $dibayar;
                                $lunas = $dibayar >= $target;
                                
                                if($lunas) {
                                    echo '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Lunas</span>';
                                    echo "<div class='small text-muted mt-1'>Dibayar: ".format_rupiah($dibayar)."</div>";
                                } else {
                                    if($dibayar > 0) {
                                        echo '<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> Kurang '.format_rupiah($sisa).'</span>';
                                    } else {
                                        $is_current_month = ($bulan == (int)date('m') && $tahun == (int)date('Y'));
                                        $is_past_month = ($tahun < (int)date('Y') || ($tahun == (int)date('Y') && $bulan < (int)date('m')));
                                        if ($is_past_month || ($is_current_month && (int)date('d') > $row['tanggal_jatuh_tempo'])) {
                                            echo '<span class="badge bg-danger"><i class="fas fa-exclamation-circle me-1"></i> Terlambat</span>';
                                        } else {
                                            echo '<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> Belum Bayar</span>';
                                        }
                                    }
                                }
                            ?>
                        </td>
                        <td class="text-end pe-4">
                            <?php if(!$lunas): ?>
                            <form method="POST" class="d-flex align-items-center justify-content-end" style="gap:5px;">
                                <input type="hidden" name="bayar_ruko" value="1">
                                <input type="hidden" name="ruko_id" value="<?= $row['id'] ?>">
                                <input type="number" name="jumlah" class="form-control form-control-sm border-primary" style="width:110px;" required min="1000" max="<?= $sisa ?>" value="<?= $sisa ?>">
                                <button type="submit" class="btn btn-sm btn-primary fw-bold" title="Proses Bayar"><i class="fas fa-arrow-right"></i></button>
                            </form>
                            <?php endif; ?>
                            <?php
                            $q_hist = mysqli_query($conn, "SELECT id, jumlah FROM pembayaran_ruko WHERE ruko_id = {$row['id']} AND bulan = $bulan AND tahun = $tahun");
                            while($h = mysqli_fetch_assoc($q_hist)):
                            ?>
                            <div class="mt-1">
                                <small class="text-muted">Bayar: <?= format_rupiah($h['jumlah']) ?></small>
                                <a href="?delete_bayar=<?= $h['id'] ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" class="btn btn-xs btn-outline-danger btn-sm ms-1 py-0 px-1" onclick="return confirm('Hapus data pembayaran ini?')" title="Hapus"><i class="fas fa-trash"></i></a>
                            </div>
                            <?php endwhile; ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="5" class="text-center py-4 text-muted">Tidak ada data Ruko yang aktif.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
