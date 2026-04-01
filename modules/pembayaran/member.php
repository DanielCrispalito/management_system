<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Admin', 'Bendahara']);

$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
$jenis_filter = isset($_GET['jenis']) ? sanitize($conn, $_GET['jenis']) : '';
$tipe_filter = isset($_GET['tipe']) ? sanitize($conn, $_GET['tipe']) : '';

$cabang_id = $_SESSION['user']['cabang_id'] ?? 1;
$is_super = ($_SESSION['user']['role'] === 'Super Admin');

$where = array("m.status = 'Aktif'");
if(!$is_super) {
    $where[] = "m.cabang_id = $cabang_id";
}
if($jenis_filter) $where[] = "m.jenis_member = '$jenis_filter'";
if($tipe_filter) $where[] = "m.tipe_pembayaran = '$tipe_filter'";
$whereClause = implode(" AND ", $where);

// Base Query
$query = "
    SELECT m.*, 
    (SELECT COUNT(id) FROM member_detail WHERE member_id = m.id AND status = 'Aktif') as total_personil
    FROM member m 
    WHERE $whereClause
    ORDER BY m.nama ASC
";
$result = mysqli_query($conn, $query);

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">Pembayaran Iuran Member</h4>
</div>

<?php display_flash_message(); ?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body bg-light rounded d-flex align-items-center justify-content-between">
        <form method="GET" class="d-flex w-100 align-items-end flex-wrap gap-3">
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
            <div style="width: 200px;">
                <label class="form-label fw-bold text-muted small">Jenis Member</label>
                <select name="jenis" class="form-select">
                    <option value="">Semua Jenis</option>
                    <option value="Perusahaan" <?= $jenis_filter == 'Perusahaan' ? 'selected' : '' ?>>Perusahaan</option>
                    <option value="Individual" <?= $jenis_filter == 'Individual' ? 'selected' : '' ?>>Individual</option>
                </select>
            </div>
            <div style="width: 200px;">
                <label class="form-label fw-bold text-muted small">Tipe Penagihan</label>
                <select name="tipe" class="form-select">
                    <option value="">Semua Tipe</option>
                    <option value="Kolektif" <?= $tipe_filter == 'Kolektif' ? 'selected' : '' ?>>Kolektif</option>
                    <option value="Individual" <?= $tipe_filter == 'Individual' ? 'selected' : '' ?>>Individual</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-primary fw-bold px-4"><i class="fas fa-filter me-2"></i> Filter Data</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0 border-top border-primary border-4">
    <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
        <span>Daftar Member Aktif</span>
        <span class="badge bg-secondary p-2">Menampilkan tagihan untuk Bulan: <?= $months[$bulan-1] ?> <?= $tahun ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height: 550px; overflow-y: auto;">
            <table class="table table-hover table-striped align-middle text-center mb-0">
                <thead class="bg-light sticky-top shadow-sm" style="z-index: 10;">
                    <tr>
                        <th class="ps-4 text-start">Nama Member</th>
                        <th>Jenis & Tipe</th>
                        <th>Jatuh Tempo</th>
                        <th>Keterangan Tagihan</th>
                        <th>Status Bulan Ini</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($result) > 0): while($row = mysqli_fetch_assoc($result)): 
                        $m_id = $row['id'];
                        // Cek status pembayaran
                        if ($row['tipe_pembayaran'] == 'Kolektif') {
                            $target = (float)$row['nominal_iuran'];
                            $q_cek = mysqli_query($conn, "SELECT SUM(jumlah_bayar) as total FROM pembayaran_member WHERE member_id = $m_id AND bulan = $bulan AND tahun = $tahun");
                            $dibayar = (float)mysqli_fetch_assoc($q_cek)['total'];
                            $is_paid = $dibayar >= $target;
                            $is_partial = $dibayar > 0 && !$is_paid;
                            
                            if($is_partial) $tagihan_info = "Kurang " . format_rupiah($target - $dibayar);
                            else $tagihan_info = "1 Tagihan Kolektif";
                        } else {
                            // Cek berapa banyak personil yg lunas
                            $tot_personil = max(1, $row['total_personil']);
                            $q_cek = mysqli_query($conn, "
                                SELECT dt.id FROM member_detail dt 
                                LEFT JOIN (SELECT member_detail_id, SUM(jumlah_bayar) as paid FROM pembayaran_member WHERE bulan=$bulan AND tahun=$tahun GROUP BY member_detail_id) p 
                                ON dt.id = p.member_detail_id 
                                WHERE dt.member_id = $m_id AND dt.status = 'Aktif' AND IFNULL(p.paid, 0) >= dt.nominal_iuran
                            ");
                            $paid_count = mysqli_num_rows($q_cek);
                            $is_paid = $paid_count >= $tot_personil;
                            $is_partial = $paid_count > 0 && !$is_paid;
                            $tagihan_info = "$paid_count / $tot_personil Lunas";
                        }
                    ?>
                    <tr>
                        <td class="text-start ps-4 fw-bold"><?= htmlspecialchars($row['nama']) ?></td>
                        <td>
                            <div class="d-flex flex-column align-items-center">
                                <span class="badge bg-secondary mb-1"><?= $row['jenis_member'] ?></span>
                                <span class="badge <?= $row['tipe_pembayaran'] == 'Kolektif' ? 'bg-primary' : 'bg-info text-dark' ?>"><?= $row['tipe_pembayaran'] ?></span>
                            </div>
                        </td>
                        <td>Tgl <?= $row['tanggal_jatuh_tempo'] ?></td>
                        <td><span class="fw-bold <?= $is_partial ? 'text-danger' : 'text-muted' ?>"><?= $tagihan_info ?></span></td>
                        <td>
                            <?php 
                                if($is_paid) {
                                    echo '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Lunas</span>';
                                } else {
                                    $is_current_month = ($bulan == (int)date('m') && $tahun == (int)date('Y'));
                                    $is_past_month = ($tahun < (int)date('Y') || ($tahun == (int)date('Y') && $bulan < (int)date('m')));
                                    if ($is_past_month || ($is_current_month && (int)date('d') > $row['tanggal_jatuh_tempo'])) {
                                        echo '<span class="badge bg-danger"><i class="fas fa-exclamation-circle me-1"></i> Terlambat</span>';
                                    } else {
                                        echo '<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> Belum Lunas</span>';
                                    }
                                }
                            ?>
                        </td>
                        <td class="text-end pe-4">
                            <a href="/modules/pembayaran/proses_member.php?id=<?= $row['id'] ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" class="btn btn-sm <?= $is_paid ? 'btn-outline-secondary' : 'btn-primary' ?> px-3 fw-bold rounded-pill">
                                Kelola Pembayaran <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">Tidak ada data member.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
