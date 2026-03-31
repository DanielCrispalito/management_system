<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Admin', 'Bendahara', 'Super Admin']);

$tanggal = isset($_GET['tanggal']) ? sanitize($conn, $_GET['tanggal']) : date('Y-m-d');
$is_super = ($_SESSION['user']['role'] === 'Super Admin');
$req_cabang = isset($_GET['cabang_id']) ? sanitize($conn, $_GET['cabang_id']) : 'all';

if($is_super) {
    if($req_cabang === 'all') {
        $where_cabang = "1=1";
        $where_cabang_p = "1=1";
        $nama_cabang_report = "Semua Cabang";
    } else {
        $c_id = (int)$req_cabang;
        $where_cabang = "cabang_id = $c_id";
        $where_cabang_p = "p.cabang_id = $c_id";
        $nc_q = mysqli_query($conn, "SELECT nama_cabang FROM cabang WHERE id=$c_id");
        $nama_cabang_report = mysqli_num_rows($nc_q)>0 ? mysqli_fetch_assoc($nc_q)['nama_cabang'] : 'Unknown';
    }
} else {
    $c_id = (int)($_SESSION['user']['cabang_id'] ?? 1);
    $where_cabang = "cabang_id = $c_id";
    $where_cabang_p = "p.cabang_id = $c_id";
    $nc_q = mysqli_query($conn, "SELECT nama_cabang FROM cabang WHERE id=$c_id");
    $nama_cabang_report = mysqli_num_rows($nc_q)>0 ? mysqli_fetch_assoc($nc_q)['nama_cabang'] : 'Unknown';
}

$q_cabang_list = mysqli_query($conn, "SELECT * FROM cabang ORDER BY nama_cabang ASC");

// Helper query function for total
function get_total($conn, $query) {
    $res = mysqli_query($conn, $query);
    if($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        return $row['total'] ?? 0;
    }
    return 0;
}

// Check query build
$wc_m = str_replace('cabang_id', 'm.cabang_id', $where_cabang);
$wc_r = str_replace('cabang_id', 'r.cabang_id', $where_cabang);
$wc_p = str_replace('cabang_id', 'p.cabang_id', $where_cabang);


// 1. Parkir
$t_parkir = get_total($conn, "SELECT SUM(total_bersih) as total FROM pendapatan_parkir WHERE tanggal = '$tanggal' AND $where_cabang");

// 2. Member
$t_member = get_total($conn, "SELECT SUM(pm.jumlah_bayar) as total FROM pembayaran_member pm JOIN member m ON pm.member_id = m.id WHERE pm.tanggal_bayar = '$tanggal' AND $wc_m");

// 3. Ruko
$t_ruko = get_total($conn, "SELECT SUM(pr.jumlah) as total FROM pembayaran_ruko pr JOIN ruko r ON pr.ruko_id = r.id WHERE pr.tanggal_bayar = '$tanggal' AND $wc_r");

// 4. Pedagang
$t_pedagang = get_total($conn, "SELECT SUM(pp.jumlah) as total FROM pembayaran_pedagang pp JOIN pedagang p ON pp.pedagang_id = p.id WHERE pp.tanggal_bayar = '$tanggal' AND $wc_p");

// 5. Ojek Online (Future proofing if table exists, otherwise 0)
$t_ojek = 0;
$cek_ojek = mysqli_query($conn, "SHOW TABLES LIKE 'pembayaran_ojek'");
if(mysqli_num_rows($cek_ojek) > 0) {
    $t_ojek = get_total($conn, "SELECT SUM(jumlah) as total FROM pembayaran_ojek WHERE tanggal_bayar = '$tanggal' AND $where_cabang");
}

// 6. Pendapatan Lain
$t_lain = get_total($conn, "SELECT SUM(nominal) as total FROM pendapatan_lain WHERE tanggal = '$tanggal' AND $where_cabang");

$total_pendapatan = $t_parkir + $t_member + $t_ruko + $t_pedagang + $t_ojek + $t_lain;

// 7. Pengeluaran
$t_pengeluaran = get_total($conn, "SELECT SUM(nominal) as total FROM pengeluaran WHERE tanggal = '$tanggal' AND $where_cabang");

$laba_bersih = $total_pendapatan - $t_pengeluaran;

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
    <h4 class="page-title mb-0">Laporan Kas Harian Gabungan</h4>
    <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print me-1"></i> Cetak Laporan</button>
</div>

<div class="card shadow-sm border-0 mb-4 d-print-none">
    <div class="card-body bg-light rounded">
        <form method="GET" class="row align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-bold">Pilih Tanggal</label>
                <input type="date" name="tanggal" class="form-control" value="<?= $tanggal ?>" required>
            </div>
            <?php if($is_super): ?>
            <div class="col-md-4">
                <label class="form-label fw-bold">Filter Cabang</label>
                <select name="cabang_id" class="form-select">
                    <option value="all" <?= $req_cabang=='all'?'selected':'' ?>>-- Semua Cabang --</option>
                    <?php while($cb = mysqli_fetch_assoc($q_cabang_list)): ?>
                    <option value="<?= $cb['id'] ?>" <?= $req_cabang==$cb['id']?'selected':'' ?>><?= htmlspecialchars($cb['nama_cabang']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-4">
                <button type="submit" class="btn btn-success fw-bold"><i class="fas fa-search me-1"></i> Filter Data</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow border-0" id="printArea">
    <div class="card-body p-5">
        <div class="text-center mb-5 pb-3 border-bottom">
            <h3 class="fw-bold mb-1">PJR PARKING & MANAGEMENT</h3>
            <h5 class="text-muted">Laporan Keuangan & Kas Harian Gabungan</h5>
            <p class="mb-0">Tanggal: <strong><?= date('d F Y', strtotime($tanggal)) ?></strong></p>
            <p class="mb-0 text-primary">Cakupan Rekap: <strong><?= htmlspecialchars($nama_cabang_report) ?></strong></p>
        </div>

        <div class="row">
            <div class="col-md-6 mb-4">
                <h5 class="fw-bold text-success border-bottom border-success pb-2 border-3">A. Rincian Pendapatan (Kas Masuk)</h5>
                <table class="table table-borderless table-sm mt-3">
                    <tr class="<?= $t_parkir > 0 ? 'bg-light' : '' ?>">
                        <td class="ps-3 fw-bold"><i class="fas fa-parking text-primary me-2"></i> Pendapatan Parkir</td>
                        <td class="text-end fw-bold text-primary"><?= format_rupiah($t_parkir) ?></td>
                    </tr>
                    
                    <tr class="<?= $t_member > 0 ? 'bg-light mt-2' : '' ?>">
                        <td class="ps-3 fw-bold"><i class="fas fa-users text-success me-2"></i> Iuran Member</td>
                        <td class="text-end fw-bold text-success"><?= format_rupiah($t_member) ?></td>
                    </tr>
                    <?php 
                    $q_member = mysqli_query($conn, "SELECT m.nama, SUM(pm.jumlah_bayar) as bayar FROM pembayaran_member pm JOIN member m ON pm.member_id = m.id WHERE pm.tanggal_bayar = '$tanggal' AND $wc_m GROUP BY m.id ORDER BY m.nama ASC");
                    while($m = mysqli_fetch_assoc($q_member)): ?>
                    <tr>
                        <td class="ps-5 text-muted small"><i class="fas fa-angle-right me-1"></i> <?= htmlspecialchars($m['nama']) ?></td>
                        <td class="text-end text-muted small"><?= format_rupiah($m['bayar']) ?></td>
                    </tr>
                    <?php endwhile; ?>

                    <tr class="<?= $t_ruko > 0 ? 'bg-light mt-2' : '' ?>">
                        <td class="ps-3 fw-bold"><i class="fas fa-store text-danger me-2"></i> Iuran Ruko</td>
                        <td class="text-end fw-bold text-danger"><?= format_rupiah($t_ruko) ?></td>
                    </tr>
                    <?php 
                    $q_ruko = mysqli_query($conn, "SELECT r.nama_ruko, SUM(pr.jumlah) as bayar FROM pembayaran_ruko pr JOIN ruko r ON pr.ruko_id = r.id WHERE pr.tanggal_bayar = '$tanggal' AND $wc_r GROUP BY r.id ORDER BY r.nama_ruko ASC");
                    while($r = mysqli_fetch_assoc($q_ruko)): ?>
                    <tr>
                        <td class="ps-5 text-muted small"><i class="fas fa-angle-right me-1"></i> <?= htmlspecialchars($r['nama_ruko']) ?></td>
                        <td class="text-end text-muted small"><?= format_rupiah($r['bayar']) ?></td>
                    </tr>
                    <?php endwhile; ?>

                    <tr class="<?= $t_pedagang > 0 ? 'bg-light mt-2' : '' ?>">
                        <td class="ps-3 fw-bold"><i class="fas fa-utensils text-info me-2"></i> Iuran Pedagang</td>
                        <td class="text-end fw-bold text-info"><?= format_rupiah($t_pedagang) ?></td>
                    </tr>
                    <?php 
                    $q_pedagang = mysqli_query($conn, "SELECT p.nama, p.kategori, SUM(pp.jumlah) as bayar FROM pembayaran_pedagang pp JOIN pedagang p ON pp.pedagang_id = p.id WHERE pp.tanggal_bayar = '$tanggal' AND $wc_p GROUP BY p.id, p.kategori ORDER BY p.kategori ASC, p.nama ASC");
                    $ped_detail = ['Pedagang Bulanan' => [], 'Pedagang Pagi' => [], 'Pedagang Malam' => []];
                    while($pd = mysqli_fetch_assoc($q_pedagang)) {
                        $ped_detail[$pd['kategori']][] = $pd;
                    }
                    foreach($ped_detail as $kat_name => $list): 
                        if(count($list) > 0): 
                            $sum_kat = array_sum(array_column($list, 'bayar'));
                    ?>
                        <tr>
                            <td class="ps-4 fw-semibold text-secondary small pt-2"><i class="fas fa-caret-down me-1"></i> <?= $kat_name ?></td>
                            <td class="text-end fw-semibold text-secondary small pt-2"><?= format_rupiah($sum_kat) ?></td>
                        </tr>
                        <?php foreach($list as $pd): ?>
                        <tr>
                            <td class="ps-5 text-muted small"><i class="fas fa-angle-right me-1"></i> <?= htmlspecialchars($pd['nama']) ?></td>
                            <td class="text-end text-muted small"><?= format_rupiah($pd['bayar']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if($t_ojek > 0): ?>
                    <tr class="bg-light mt-2">
                        <td class="ps-3 fw-bold"><i class="fas fa-motorcycle text-warning me-2"></i> Iuran Ojek Online</td>
                        <td class="text-end fw-bold text-warning"><?= format_rupiah($t_ojek) ?></td>
                    </tr>
                    <?php endif; ?>

                    <tr class="<?= $t_lain > 0 ? 'bg-light mt-2' : '' ?>">
                        <td class="ps-3 fw-bold"><i class="fas fa-plus-circle text-secondary me-2"></i> Pendapatan Lain-lain</td>
                        <td class="text-end fw-bold text-secondary"><?= format_rupiah($t_lain) ?></td>
                    </tr>
                    <?php 
                    $q_lain_kat = mysqli_query($conn, "SELECT k.nama_kategori, s.nama_subkategori, p.nominal, p.keterangan FROM pendapatan_lain p JOIN subkategori s ON p.subkategori_id = s.id JOIN kategori k ON s.kategori_id = k.id WHERE p.tanggal = '$tanggal' AND $where_cabang ORDER BY k.nama_kategori, s.nama_subkategori");
                    $cur_kat = '';
                    while($ln = mysqli_fetch_assoc($q_lain_kat)):
                        if($cur_kat != $ln['nama_kategori']):
                            $cur_kat = $ln['nama_kategori'];
                    ?>
                    <tr>
                        <td class="ps-4 fw-semibold text-secondary small"><i class="fas fa-caret-down me-1"></i> <?= htmlspecialchars($ln['nama_kategori']) ?></td>
                        <td></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="ps-5 text-muted small"><i class="fas fa-angle-right me-1"></i> <?= htmlspecialchars($ln['nama_subkategori']) ?> <?= $ln['keterangan'] ? '– '.htmlspecialchars($ln['keterangan']) : '' ?></td>
                        <td class="text-end text-muted small"><?= format_rupiah($ln['nominal']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                    
                    <tr class="border-top">
                        <td class="ps-3 fw-bold fs-5 pt-2">Total Kas Masuk</td>
                        <td class="text-end fw-bold fs-5 pt-2 text-success"><?= format_rupiah($total_pendapatan) ?></td>
                    </tr>
                </table>
            </div>

            <div class="col-md-6 mb-4">
                <h5 class="fw-bold text-danger border-bottom border-danger pb-2 border-3">B. Rincian Pengeluaran (Kas Keluar)</h5>
                <?php
                $q_keluar = mysqli_query($conn, "SELECT p.nominal, k.nama_kategori, p.keterangan FROM pengeluaran p JOIN kategori_pengeluaran k ON p.kategori_id = k.id WHERE p.tanggal = '$tanggal' AND $where_cabang_p");
                ?>
                <table class="table table-borderless table-sm mt-3">
                    <?php if(mysqli_num_rows($q_keluar) > 0): while($k = mysqli_fetch_assoc($q_keluar)): ?>
                    <tr>
                        <td class="ps-3">
                            <span class="badge bg-danger me-2"><?= htmlspecialchars($k['nama_kategori']) ?></span>
                            <small class="text-muted"><?= htmlspecialchars($k['keterangan']) ?></small>
                        </td>
                        <td class="text-end fw-semibold"><?= format_rupiah($k['nominal']) ?></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="2" class="text-center text-muted py-3">Tidak ada pengeluaran hari ini.</td></tr>
                    <?php endif; ?>
                    
                    <tr class="border-top">
                        <td class="ps-3 fw-bold fs-5 pt-2">Total Kas Keluar</td>
                        <td class="text-end fw-bold fs-5 pt-2 text-danger"><?= format_rupiah($t_pengeluaran) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="p-4 rounded border <?= $laba_bersih >= 0 ? 'bg-success text-white border-success' : 'bg-danger text-white border-danger' ?>">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0 fw-bold"><i class="fas fa-balance-scale me-2"></i> KAS BERSIH (LABA/RUGI) HARI INI</h4>
                        <h2 class="mb-0 fw-bold"><?= format_rupiah($laba_bersih) ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-5 pt-4">
            <div class="col-4 text-center">
                <p>Mengetahui,</p>
                <br><br><br>
                <p class="mb-0 fw-bold text-decoration-underline">Pimpinan / Manager</p>
            </div>
            <div class="col-4 text-center">
            </div>
            <div class="col-4 text-center">
                <p>Dibuat Oleh,</p>
                <br><br><br>
                <p class="mb-0 fw-bold text-decoration-underline"><?= htmlspecialchars($_SESSION['user']['nama']) ?></p>
                <small class="text-muted"><?= $_SESSION['user']['role'] ?></small>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    @page { size: A4 portrait; margin: 1cm; }
    body { background-color: #fff; font-size: 11pt !important; color: #000; }
    #sidebar, .navbar, .d-print-none { display: none !important; }
    #content { width: 100% !important; margin: 0 !important; padding: 0 !important; position: static; }
    .card { border: none !important; box-shadow: none !important; padding: 0 !important; margin: 0 !important; }
    .card-body { padding: 0 !important; }
    
    /* Font resizing for readability and fitting on paper */
    .fs-5 { font-size: 11pt !important; }
    h3 { font-size: 14pt !important; }
    h4 { font-size: 13pt !important; }
    h5 { font-size: 12pt !important; }
    h2 { font-size: 16pt !important; }
    .small, small { font-size: 9pt !important; }
    table { font-size: 10pt !important; margin-bottom: 0 !important; width: 100% !important; }
    .badge { border: 1px solid #777 !important; color: #000 !important; background: transparent !important; }
    .bg-light { background-color: #f8f9fa !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .text-success, .text-danger, .text-primary, .text-info, .text-warning, .text-secondary { color: #000 !important; }
    .border-success, .border-danger { border-color: #000 !important; }
    .p-5, .p-4 { padding: 1rem !important; }
    .mt-5, .pt-4 { margin-top: 1rem !important; padding-top: 1rem !important; }
    
    /* Force page break avoidance */
    tr { page-break-inside: avoid; }
    .row { page-break-inside: avoid; }
}
</style>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
