<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Admin', 'Bendahara']);

$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
$kategori = isset($_GET['kategori']) ? sanitize($conn, $_GET['kategori']) : 'Semua';

$is_super = ($_SESSION['user']['role'] === 'Super Admin');
$req_cabang = isset($_GET['cabang_id']) ? sanitize($conn, $_GET['cabang_id']) : 'all';

if($is_super) {
    if($req_cabang === 'all') {
        $c_m = "1=1"; $c_r = "1=1"; $c_p = "1=1";
        $nama_cabang_report = "Semua Cabang";
    } else {
        $cid = (int)$req_cabang;
        $c_m = "m.cabang_id=$cid"; $c_r = "r.cabang_id=$cid"; $c_p = "p.cabang_id=$cid";
        $nc_q = mysqli_query($conn, "SELECT nama_cabang FROM cabang WHERE id=$cid");
        $nama_cabang_report = mysqli_num_rows($nc_q)>0 ? mysqli_fetch_assoc($nc_q)['nama_cabang'] : 'Unknown';
    }
} else {
    $cid = (int)($_SESSION['user']['cabang_id'] ?? 1);
    $c_m = "m.cabang_id=$cid"; $c_r = "r.cabang_id=$cid"; $c_p = "p.cabang_id=$cid";
    $nc_q = mysqli_query($conn, "SELECT nama_cabang FROM cabang WHERE id=$cid");
    $nama_cabang_report = mysqli_num_rows($nc_q)>0 ? mysqli_fetch_assoc($nc_q)['nama_cabang'] : 'Unknown';
}
$q_cabang_list = mysqli_query($conn, "SELECT * FROM cabang ORDER BY nama_cabang ASC");

$results = [];
$total_keseluruhan = 0;

if ($kategori == 'Semua' || $kategori == 'Member') {
    $q_member = mysqli_query($conn, "
        SELECT pm.tanggal_bayar, pm.jumlah_bayar, pm.metode_bayar, 
               m.nama as nama_pembayar, 'Member' as jenis,
               COALESCE(md.nama_personil, 'Kolektif') as detail
        FROM pembayaran_member pm
        JOIN member m ON pm.member_id = m.id
        LEFT JOIN member_detail md ON pm.member_detail_id = md.id
        WHERE pm.bulan = $bulan AND pm.tahun = $tahun AND $c_m
        ORDER BY pm.tanggal_bayar ASC
    ");
    while($r = mysqli_fetch_assoc($q_member)) {
        $results[] = $r;
        $total_keseluruhan += $r['jumlah_bayar'];
    }
}

if ($kategori == 'Semua' || $kategori == 'Ruko') {
    $q_ruko = mysqli_query($conn, "
        SELECT pr.tanggal_bayar, pr.jumlah as jumlah_bayar, 'Tunai' as metode_bayar, 
               r.nama_ruko as nama_pembayar, 'Ruko' as jenis, '-' as detail
        FROM pembayaran_ruko pr
        JOIN ruko r ON pr.ruko_id = r.id
        WHERE pr.bulan = $bulan AND pr.tahun = $tahun AND $c_r
        ORDER BY pr.tanggal_bayar ASC
    ");
    while($r = mysqli_fetch_assoc($q_ruko)) {
        $results[] = $r;
        $total_keseluruhan += $r['jumlah_bayar'];
    }
}

if ($kategori == 'Semua' || in_array($kategori, ['Pedagang','Pedagang Bulanan','Pedagang Pagi','Pedagang Malam'])) {
    $kat_cond = ($kategori != 'Semua' && $kategori != 'Pedagang') ? "AND p.kategori = '$kategori'" : "";
    $q_pedagang = mysqli_query($conn, "
        SELECT pp.tanggal_bayar, pp.jumlah as jumlah_bayar, 'Tunai' as metode_bayar, 
               p.nama as nama_pembayar, CONCAT('Pedagang - ', p.kategori) as jenis, p.kategori as detail
        FROM pembayaran_pedagang pp
        JOIN pedagang p ON pp.pedagang_id = p.id
        WHERE pp.bulan = $bulan AND pp.tahun = $tahun AND $c_p $kat_cond
        ORDER BY p.kategori ASC, pp.tanggal_bayar ASC
    ");
    while($r = mysqli_fetch_assoc($q_pedagang)) {
        $results[] = $r;
        $total_keseluruhan += $r['jumlah_bayar'];
    }
}

// Sort by date
usort($results, function($a, $b) {
    return strtotime($a['tanggal_bayar']) - strtotime($b['tanggal_bayar']);
});

$months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

// If print mode layout will be hidden via CSS below, but we use same page for simplicity
require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<!-- Print Specific Styling -->
<style>
    @media print {
        #sidebar, .navbar, .form-filter, .btn-export { display: none !important; }
        #content { width: 100% !important; margin: 0 !important; padding: 0 !important; top: 0 !important; }
        .card { border: none !important; box-shadow: none !important; }
        .page-title { text-align: center; }
        body { background: white; }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">Laporan Pembayaran</h4>
    <div class="btn-export gap-2 d-flex">
        <button onclick="window.print()" class="btn btn-secondary"><i class="fas fa-print"></i> Print / PDF</button>
        <button onclick="exportTableToExcel('reportTable', 'Laporan_Pembayaran')" class="btn btn-success"><i class="fas fa-file-excel"></i> Export Excel</button>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4 form-filter">
    <div class="card-body bg-light rounded d-flex align-items-center justify-content-between">
        <form method="GET" class="d-flex w-100 align-items-end flex-wrap gap-3">
            <div style="width: 150px;">
                <label class="form-label fw-bold text-muted small">Bulan</label>
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
            <div style="width: 200px;">
                <label class="form-label fw-bold text-muted small">Kategori Transaksi</label>
                <select name="kategori" class="form-select">
                    <option value="Semua" <?= $kategori == 'Semua' ? 'selected' : '' ?>>Semua Kategori</option>
                    <option value="Member" <?= $kategori == 'Member' ? 'selected' : '' ?>>Pembayaran Member</option>
                    <option value="Ruko" <?= $kategori == 'Ruko' ? 'selected' : '' ?>>Pembayaran Ruko</option>
                    <option value="Pedagang" <?= $kategori == 'Pedagang' ? 'selected' : '' ?>>Semua Pedagang</option>
                    <option value="Pedagang Bulanan" <?= $kategori == 'Pedagang Bulanan' ? 'selected' : '' ?>>Pedagang Bulanan</option>
                    <option value="Pedagang Pagi" <?= $kategori == 'Pedagang Pagi' ? 'selected' : '' ?>>Pedagang Pagi (Harian)</option>
                    <option value="Pedagang Malam" <?= $kategori == 'Pedagang Malam' ? 'selected' : '' ?>>Pedagang Malam (Harian)</option>
                </select>
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
                <button type="submit" class="btn btn-primary fw-bold px-4"><i class="fas fa-filter me-2"></i> Tampilkan Laporan</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0 border-top border-primary border-4" id="reportArea">
    <div class="card-header bg-white pb-0 border-0">
        <div class="text-center mb-4 mt-3">
            <h5 class="fw-bold mb-1">LAPORAN PEMBAYARAN - PJR PARKING SYSTEM</h5>
            <p class="text-muted mb-0">Periode: <?= $months[$bulan-1] ?> <?= $tahun ?> | Cabang: <?= htmlspecialchars($nama_cabang_report) ?></p>
            <p class="text-muted small">Kategori: <?= htmlspecialchars($kategori) ?></p>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive px-4 pb-4">
            <table class="table table-bordered table-striped" id="reportTable">
                <thead class="bg-light text-center">
                    <tr>
                        <th width="5%">No</th>
                        <th width="15%">Tanggal Bayar</th>
                        <th width="20%">Nama Pembayar</th>
                        <th width="15%">Kategori</th>
                        <th width="25%">Detail / Keterangan</th>
                        <th width="20%">Jumlah Bayar (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($results) > 0): $no=1; foreach($results as $row): ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td class="text-center"><?= date('d/m/Y', strtotime($row['tanggal_bayar'])) ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($row['nama_pembayar']) ?></td>
                        <td class="text-center"><?= $row['jenis'] ?></td>
                        <td><?= htmlspecialchars($row['detail']) ?></td>
                        <td class="text-end fw-bold text-success"><?= number_format($row['jumlah_bayar'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="6" class="text-center py-4">Tidak ada data pembayaran pada periode ini.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot class="bg-light">
                    <tr>
                        <th colspan="5" class="text-end fw-bold fs-5">TOTAL PEMBAYARAN BUKTI KAS MASUK:</th>
                        <th class="text-end text-primary fw-bold fs-5"><?= number_format($total_keseluruhan, 0, ',', '.') ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
function exportTableToExcel(tableID, filename = ''){
    var downloadLink;
    var dataType = 'application/vnd.ms-excel';
    var tableSelect = document.getElementById(tableID);
    var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
    
    filename = filename?filename+'.xls':'excel_data.xls';
    
    downloadLink = document.createElement("a");
    document.body.appendChild(downloadLink);
    
    if(navigator.msSaveOrOpenBlob){
        var blob = new Blob(['\ufeff', tableHTML], {
            type: dataType
        });
        navigator.msSaveOrOpenBlob( blob, filename);
    }else{
        downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
        downloadLink.download = filename;
        downloadLink.click();
    }
}
</script>

<style>
@media print {
    @page { size: A4 landscape; margin: 1cm; }
    body { background-color: #fff; font-size: 11pt !important; color: #000; }
    #sidebar, .navbar, .d-print-none { display: none !important; }
    #content { width: 100% !important; margin: 0 !important; padding: 0 !important; position: static; }
    .card { border: none !important; box-shadow: none !important; padding: 0 !important; margin: 0 !important; }
    .card-body { padding: 0 !important; }
    
    .fs-5 { font-size: 11pt !important; }
    h3 { font-size: 14pt !important; }
    h4 { font-size: 13pt !important; }
    h5 { font-size: 12pt !important; }
    .small, small { font-size: 9pt !important; }
    table { font-size: 10pt !important; margin-bottom: 0 !important; width: 100% !important; }
    .bg-light { background-color: #f8f9fa !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    
    tr { page-break-inside: avoid; }
}
</style>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
