<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Admin', 'Bendahara']);

$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
$kategori_filter = isset($_GET['kategori']) ? sanitize($conn, $_GET['kategori']) : '';

$is_daily = in_array($kategori_filter, ['Pedagang Pagi', 'Pedagang Malam']);
$tanggal = isset($_GET['tanggal']) ? sanitize($conn, $_GET['tanggal']) : date('Y-m-d');

$cabang_id = $_SESSION['user']['cabang_id'] ?? 1;
$is_super = ($_SESSION['user']['role'] === 'Super Admin');

// Handle Delete Payment
if (isset($_GET['delete_bayar'])) {
    $del_id = (int)$_GET['delete_bayar'];
    mysqli_query($conn, "DELETE FROM pembayaran_pedagang WHERE id = $del_id");
    set_flash_message('success', 'Data pembayaran berhasil dihapus.');
    $back = isset($_GET['from_tanggal']) ? "tanggal={$_GET['from_tanggal']}&kategori=$kategori_filter" : "bulan=$bulan&tahun=$tahun&kategori=$kategori_filter";
    redirect("/pjr_parking/modules/pembayaran/pedagang.php?$back");
}

// Handle Process Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bayar_pedagang'])) {
    $pedagang_id = (int)$_POST['pedagang_id'];
    $jumlah = (float)$_POST['jumlah'];
    $tanggal_bayar = $is_daily ? $tanggal : date('Y-m-d'); 
    
    $b = $is_daily ? date('m', strtotime($tanggal_bayar)) : $bulan;
    $t = $is_daily ? date('Y', strtotime($tanggal_bayar)) : $tahun;

    $stmt = $conn->prepare("INSERT INTO pembayaran_pedagang (pedagang_id, bulan, tahun, jumlah, tanggal_bayar) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiids", $pedagang_id, $b, $t, $jumlah, $tanggal_bayar);
    
    if($stmt->execute()) {
        set_flash_message('success', 'Pembayaran Pedagang Berhasil Tersimpan.');
    } else {
        set_flash_message('error', 'Gagal memproses pembayaran!');
    }
    if($is_daily) {
        redirect("/pjr_parking/modules/pembayaran/pedagang.php?tanggal=$tanggal&kategori=$kategori_filter");
    } else {
        redirect("/pjr_parking/modules/pembayaran/pedagang.php?bulan=$bulan&tahun=$tahun&kategori=$kategori_filter");
    }
}

$where = array("p.status = 'Aktif'");
if(!$is_super) $where[] = "p.cabang_id = $cabang_id";
if($kategori_filter) $where[] = "p.kategori = '$kategori_filter'";
$whereClause = implode(" AND ", $where);

if($is_daily) {
    $sum_query = "SELECT SUM(jumlah) FROM pembayaran_pedagang WHERE pedagang_id = p.id AND tanggal_bayar = '$tanggal'";
} else {
    $sum_query = "SELECT SUM(jumlah) FROM pembayaran_pedagang WHERE pedagang_id = p.id AND bulan = $bulan AND tahun = $tahun";
}

// Fetch Data Pedagang
$query = mysqli_query($conn, "
    SELECT p.*, 
    IFNULL(($sum_query), 0) as total_dibayar
    FROM pedagang p 
    WHERE $whereClause
    ORDER BY p.kategori ASC, p.nama ASC
");

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">Pembayaran Iuran Pedagang</h4>
</div>

<?php display_flash_message(); ?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body bg-light rounded d-flex align-items-center justify-content-between">
        <form method="GET" class="d-flex w-100 align-items-end flex-wrap gap-3">
            <div style="width: 220px;">
                <label class="form-label fw-bold text-muted small">Kategori Pedagang</label>
                <select name="kategori" class="form-select" onchange="this.form.submit()">
                    <option value="">Semua Kategori (Bulanan)</option>
                    <option value="Pedagang Bulanan" <?= $kategori_filter == 'Pedagang Bulanan' ? 'selected' : '' ?>>Pedagang Bulanan</option>
                    <option value="Pedagang Pagi" <?= $kategori_filter == 'Pedagang Pagi' ? 'selected' : '' ?>>Pedagang Pagi (Harian)</option>
                    <option value="Pedagang Malam" <?= $kategori_filter == 'Pedagang Malam' ? 'selected' : '' ?>>Pedagang Malam (Harian)</option>
                </select>
            </div>
            
            <?php if(!$is_daily): ?>
            <div style="width: 150px;">
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
            <div style="width: 120px;">
                <label class="form-label fw-bold text-muted small">Tahun</label>
                <input type="number" name="tahun" class="form-control" value="<?= $tahun ?>" min="2020" max="2050">
            </div>
            <?php else: ?>
            <div style="width: 200px;">
                <label class="form-label fw-bold text-muted small">Pilih Tanggal</label>
                <input type="date" name="tanggal" class="form-control" value="<?= $tanggal ?>">
            </div>
            <?php endif; ?>
            <div>
                <button type="submit" class="btn btn-warning fw-bold px-4"><i class="fas fa-filter me-2"></i> Filter Data</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0 border-top border-warning border-4">
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height: 550px; overflow-y: auto;">
            <table class="table table-hover table-striped align-middle text-center mb-0">
                <thead class="bg-light sticky-top shadow-sm" style="z-index: 10;">
                    <tr>
                        <th class="ps-4 text-start">Nama Pedagang</th>
                        <th>Kategori</th>
                        <?php if($is_daily): ?>
                        <th>Tarif Harian</th>
                        <th>Tgl Tagihan</th>
                        <?php else: ?>
                        <th>Tagihan/Bulan</th>
                        <th>Jatuh Tempo</th>
                        <?php endif; ?>
                        <th>Status</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($query) > 0): while($row = mysqli_fetch_assoc($query)): ?>
                    <tr>
                        <td class="text-start ps-4 fw-bold"><?= htmlspecialchars($row['nama']) ?></td>
                        <td>
                            <?php 
                                if($row['kategori'] == 'Pedagang Pagi') echo '<span class="badge bg-info text-dark">Pagi</span>';
                                elseif($row['kategori'] == 'Pedagang Malam') echo '<span class="badge bg-dark">Malam</span>';
                                else echo '<span class="badge bg-secondary">Bulanan</span>';
                            ?>
                        </td>
                        <td class="text-success fw-bold"><?= format_rupiah($row['nominal_iuran']) ?></td>
                        <td>
                            <?php if($is_daily): ?>
                                Tgl <?= date('d M Y', strtotime($tanggal)) ?>
                            <?php else: ?>
                                Tgl <?= $row['tanggal_jatuh_tempo'] ?>
                            <?php endif; ?>
                        </td>
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
                                        if($is_daily) {
                                            $is_past = strtotime($tanggal) < strtotime(date('Y-m-d'));
                                            if($is_past) {
                                                echo '<span class="badge bg-danger"><i class="fas fa-exclamation-circle me-1"></i> Terlambat (Belum Bayar)</span>';
                                            } else {
                                                echo '<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> Belum Bayar</span>';
                                            }
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
                                }
                            ?>
                        </td>
                        <td class="text-end pe-4">
                            <?php if(!$lunas): ?>
                            <form method="POST" class="d-flex align-items-center justify-content-end" style="gap:5px;">
                                <input type="hidden" name="bayar_pedagang" value="1">
                                <input type="hidden" name="pedagang_id" value="<?= $row['id'] ?>">
                                <input type="number" name="jumlah" class="form-control form-control-sm border-warning" style="width:110px;" required min="1000" max="<?= $sisa ?>" value="<?= $sisa ?>">
                                <button type="submit" class="btn btn-sm btn-warning text-dark fw-bold" title="Proses Bayar"><i class="fas fa-arrow-right"></i></button>
                            </form>
                            <?php endif; ?>
                            <?php
                            // Show delete buttons per payment record
                            if($is_daily) {
                                $q_hist = mysqli_query($conn, "SELECT id, jumlah FROM pembayaran_pedagang WHERE pedagang_id = {$row['id']} AND tanggal_bayar = '$tanggal'");
                            } else {
                                $q_hist = mysqli_query($conn, "SELECT id, jumlah FROM pembayaran_pedagang WHERE pedagang_id = {$row['id']} AND bulan = $bulan AND tahun = $tahun");
                            }
                            $from_param = $is_daily ? "from_tanggal=$tanggal" : "";
                            while($h = mysqli_fetch_assoc($q_hist)):
                            ?>
                            <div class="mt-1">
                                <small class="text-muted">Bayar: <?= format_rupiah($h['jumlah']) ?></small>
                                <a href="?delete_bayar=<?= $h['id'] ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&<?= $from_param ?>" class="btn btn-xs btn-outline-danger btn-sm ms-1 py-0 px-1" onclick="return confirm('Hapus data pembayaran ini?')" title="Hapus"><i class="fas fa-trash"></i></a>
                            </div>
                            <?php endwhile; ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">Tidak ada data pedagang.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
