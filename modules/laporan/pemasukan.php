<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Admin', 'Bendahara']);

$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

$is_super = ($_SESSION['user']['role'] === 'Super Admin');
$req_cabang = isset($_GET['cabang_id']) ? sanitize($conn, $_GET['cabang_id']) : 'all';

if($is_super) {
    if($req_cabang === 'all') {
        $cf = ""; $cfp = "";
        $nama_cabang_report = "Semua Cabang";
    } else {
        $cid = (int)$req_cabang;
        $cf = "AND cabang_id = $cid"; $cfp = "AND p.cabang_id = $cid";
        $nc_q = mysqli_query($conn, "SELECT nama_cabang FROM cabang WHERE id=$cid");
        $nama_cabang_report = mysqli_num_rows($nc_q)>0 ? mysqli_fetch_assoc($nc_q)['nama_cabang'] : 'Unknown';
    }
} else {
    $cid = (int)($_SESSION['user']['cabang_id'] ?? 1);
    $cf = "AND cabang_id = $cid"; $cfp = "AND p.cabang_id = $cid";
    $nc_q = mysqli_query($conn, "SELECT nama_cabang FROM cabang WHERE id=$cid");
    $nama_cabang_report = mysqli_num_rows($nc_q)>0 ? mysqli_fetch_assoc($nc_q)['nama_cabang'] : 'Unknown';
}
$q_cabang_list = mysqli_query($conn, "SELECT * FROM cabang ORDER BY nama_cabang ASC");

// 1. Fetch Income Data
$income_data = [];
$total_income = 0;

// Parkir
$q_parkir = mysqli_query($conn, "SELECT SUM(total_bersih) as total FROM pendapatan_parkir WHERE MONTH(tanggal) = $bulan AND YEAR(tanggal) = $tahun $cf");
if($r=mysqli_fetch_assoc($q_parkir)) { if((float)$r['total'] > 0) { $income_data[] = ['nama' => 'Pendapatan Parkir', 'total' => (float)$r['total']]; $total_income += (float)$r['total']; } }

// Member
$c_m_lab = $is_super ? ($req_cabang=='all' ? '1=1' : "m.cabang_id=$cid") : "m.cabang_id=$cid";
$q_mem = mysqli_query($conn, "SELECT SUM(pm.jumlah_bayar) as total FROM pembayaran_member pm JOIN member m ON pm.member_id = m.id WHERE pm.bulan = $bulan AND pm.tahun = $tahun AND $c_m_lab");
if($r=mysqli_fetch_assoc($q_mem)) { if((float)$r['total'] > 0) { $income_data[] = ['nama' => 'Iuran Member', 'total' => (float)$r['total']]; $total_income += (float)$r['total']; } }

// Ruko
$c_r_lab = $is_super ? ($req_cabang=='all' ? '1=1' : "r.cabang_id=$cid") : "r.cabang_id=$cid";
$q_ruk = mysqli_query($conn, "SELECT SUM(pr.jumlah) as total FROM pembayaran_ruko pr JOIN ruko r ON pr.ruko_id = r.id WHERE pr.bulan = $bulan AND pr.tahun = $tahun AND $c_r_lab");
if($r=mysqli_fetch_assoc($q_ruk)) { if((float)$r['total'] > 0) { $income_data[] = ['nama' => 'Iuran Ruko', 'total' => (float)$r['total']]; $total_income += (float)$r['total']; } }

// Pedagang - split by kategori
$c_p_lab = $is_super ? ($req_cabang=='all' ? '1=1' : "p.cabang_id=$cid") : "p.cabang_id=$cid";
foreach(['Pedagang Bulanan','Pedagang Pagi','Pedagang Malam'] as $kat_p) {
    $q_ped = mysqli_query($conn, "SELECT SUM(pp.jumlah) as total FROM pembayaran_pedagang pp JOIN pedagang p ON pp.pedagang_id = p.id WHERE pp.bulan = $bulan AND pp.tahun = $tahun AND p.kategori = \"$kat_p\" AND $c_p_lab");
    if($r=mysqli_fetch_assoc($q_ped)) { if((float)$r['total'] > 0) { $income_data[] = ['nama' => 'Iuran '.$kat_p, 'total' => (float)$r['total']]; $total_income += (float)$r['total']; } }
}

// Pendapatan Lain-lain
$q_lain = mysqli_query($conn, "SELECT s.nama_subkategori as nama, SUM(p.nominal) as total FROM pendapatan_lain p JOIN subkategori s ON p.subkategori_id = s.id WHERE MONTH(p.tanggal) = $bulan AND YEAR(p.tanggal) = $tahun $cfp GROUP BY p.subkategori_id ORDER BY s.nama_subkategori ASC");
while($r = mysqli_fetch_assoc($q_lain)) { if($r['total'] > 0) { $income_data[] = ['nama' => 'Pendapatan Lain: ' . $r['nama'], 'total' => (float)$r['total']]; $total_income += (float)$r['total']; } }

// 2. Fetch Expense Data
$expense_data = [];
$total_expense = 0;

$q_peng = mysqli_query($conn, "
    SELECT k.nama_kategori as nama, SUM(p.nominal) as total
    FROM pengeluaran p
    JOIN kategori_pengeluaran k ON p.kategori_id = k.id
    WHERE MONTH(p.tanggal) = $bulan AND YEAR(p.tanggal) = $tahun $cfp
    GROUP BY p.kategori_id
    ORDER BY k.nama_kategori ASC
");
while($r = mysqli_fetch_assoc($q_peng)) {
    if($r['total'] > 0) {
        $expense_data[] = ['nama' => 'Beban ' . $r['nama'], 'total' => (float)$r['total']];
        $total_expense += (float)$r['total'];
    }
}

$laba_bersih = $total_income - $total_expense;
$months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<style>
    .statement-table th { background-color: #f8fafc !important; }
    .statement-row:hover { background-color: #f1f5f9; }
    .indent-1 { padding-left: 2rem !important; }
    .total-row { border-top: 2px solid #cbd5e1 !important; border-bottom: 2px solid #cbd5e1 !important; background-color: #f8fafc; font-weight: bold; }
    .grand-total { border-top: 3px double #334155 !important; border-bottom: 3px double #334155 !important; font-size: 1.15rem; background-color: #e2e8f0; }
    @media print {
        #sidebar, .navbar, .form-filter, .btn-export { display: none !important; }
        #content { width: 100% !important; margin: 0 !important; padding: 0 !important; }
        .card { border: none !important; box-shadow: none !important; margin: 0 !important; padding: 0 !important; }
        .card-header, .card-body { padding: 0 !important; }
        body { background: white; padding: 0; margin: 0; }
        .table-responsive { overflow: visible !important; display: table !important; width: 100% !important; }
        table { page-break-inside: auto !important; }
        tr { page-break-inside: avoid !important; page-break-after: auto !important; }
        .statement-table th, .statement-table td { padding-top: 5px !important; padding-bottom: 5px !important; }
        
        /* Hilangkan margin/padding berlebih saat print */
        .px-4 { padding-left: 0 !important; padding-right: 0 !important; }
        .pb-5 { padding-bottom: 0 !important; }
        .mt-5 { margin-top: 2rem !important; }
        .mb-4 { margin-bottom: 1rem !important; }
        
        @page { margin: 1.5cm; }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">Laporan Laba/Rugi (Profit & Loss)</h4>
    <div class="btn-export gap-2 d-flex">
        <button onclick="window.print()" class="btn btn-secondary shadow-sm"><i class="fas fa-print"></i> Print Laporan</button>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4 form-filter">
    <div class="card-body bg-light rounded d-flex align-items-center justify-content-between">
        <form method="GET" class="d-flex w-100 align-items-end flex-wrap gap-3">
            <div style="width: 150px;">
                <label class="form-label fw-bold text-muted small">Bulan Anggaran</label>
                <select name="bulan" class="form-select">
                    <?php 
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
            <?php if($is_super): ?>
            <div style="width: 200px;">
                <label class="form-label fw-bold text-muted small">Cabang</label>
                <select name="cabang_id" class="form-select">
                    <option value="all" <?= $req_cabang=='all'?'selected':'' ?>>Semua Cabang</option>
                    <?php while($cb = mysqli_fetch_assoc($q_cabang_list)): ?>
                    <option value="<?= $cb['id'] ?>" <?= $req_cabang==$cb['id']?'selected':'' ?>><?= htmlspecialchars($cb['nama_cabang']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <?php endif; ?>
            <div>
                <button type="submit" class="btn btn-primary fw-bold px-4"><i class="fas fa-search me-2"></i> Tampilkan</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0 border-top border-primary border-4" style="max-width: 900px; margin: 0 auto;">
    <div class="card-header bg-white pb-0 border-0">
        <div class="text-center mb-4 mt-3">
            <h4 class="fw-bold mb-1 text-primary">LAPORAN LABA RUGI</h4>
            <h6 class="fw-bold mb-1 text-dark">PJR PARKING MANAGEMENT</h6>
            <p class="text-muted mb-0">Periode: <?= $months[$bulan-1] ?> <?= $tahun ?> <br> Cabang: <?= htmlspecialchars($nama_cabang_report) ?></p>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive px-4 pb-5">
            <table class="table statement-table align-middle" style="border-color: transparent;">
                <tbody>
                    <!-- Bagian Pendapatan -->
                    <tr>
                        <th colspan="2" class="fs-5 text-dark pb-2 pt-3 border-0">PENDAPATAN OPERASIONAL</th>
                    </tr>
                    
                    <?php if(count($income_data) > 0): foreach($income_data as $i): ?>
                    <tr class="statement-row">
                        <td class="indent-1 border-0 py-2"><?= htmlspecialchars($i['nama']) ?></td>
                        <td class="text-end border-0 py-2"><?= format_rupiah($i['total']) ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="2" class="indent-1 border-0 py-2 text-muted fst-italic">Tidak ada catatan pendapatan.</td>
                    </tr>
                    <?php endif; ?>
                    
                    <tr class="total-row text-success">
                        <td class="py-3">TOTAL PENDAPATAN</td>
                        <td class="text-end py-3"><?= format_rupiah($total_income) ?></td>
                    </tr>

                    <!-- Spacing -->
                    <tr><td colspan="2" class="border-0 py-3"></td></tr>

                    <!-- Bagian Beban Pengeluaran -->
                    <tr>
                        <th colspan="2" class="fs-5 text-dark pb-2 border-0">BEBAN PENGELUARAN</th>
                    </tr>
                    
                    <?php if(count($expense_data) > 0): foreach($expense_data as $e): ?>
                    <tr class="statement-row">
                        <td class="indent-1 border-0 py-2"><?= htmlspecialchars($e['nama']) ?></td>
                        <td class="text-end border-0 py-2"><?= format_rupiah($e['total']) ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="2" class="indent-1 border-0 py-2 text-muted fst-italic">Tidak ada catatan pengeluaran.</td>
                    </tr>
                    <?php endif; ?>
                    
                    <tr class="total-row text-danger">
                        <td class="py-3">TOTAL BEBAN PENGELUARAN</td>
                        <td class="text-end py-3">(<?= format_rupiah($total_expense) ?>)</td>
                    </tr>
                    
                    <!-- Spacing -->
                    <tr><td colspan="2" class="border-0 py-4"></td></tr>
                    
                    <!-- Laba Bersih -->
                    <tr class="grand-total <?= $laba_bersih >= 0 ? 'text-primary' : 'text-danger' ?>">
                        <td class="py-4 font-monospace fw-bold">LABA BERSIH OPERASIONAL</td>
                        <td class="text-end py-4 fw-bold">
                            <?= $laba_bersih < 0 ? '(' . format_rupiah(abs($laba_bersih)) . ')' : format_rupiah($laba_bersih) ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <div class="row mt-5 pt-3">
                <div class="col-6 text-center text-muted">
                    <p class="mb-5">Dibuat Oleh,</p>
                    <p class="fw-bold text-dark border-bottom d-inline-block pb-1 px-4 mb-0"><?= htmlspecialchars($_SESSION['user']['nama']) ?></p>
                    <p class="small mt-1"><?= htmlspecialchars($_SESSION['user']['role']) ?></p>
                </div>
                <div class="col-6 text-center text-muted">
                    <p class="mb-5">Mengetahui,</p>
                    <p class="fw-bold text-dark border-bottom d-inline-block pb-1 px-4 mb-0">______________________</p>
                    <p class="small mt-1">Pimpinan Cabang</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
