<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Admin', 'Bendahara']);

$hari_ini = (int)date('d');
$kategori = isset($_GET['kategori']) ? sanitize($conn, $_GET['kategori']) : 'Semua';
$jangkauan = isset($_GET['jangkauan']) ? (int)$_GET['jangkauan'] : 5; // H+5

$is_super = ($_SESSION['user']['role'] === 'Super Admin');
$req_cabang = isset($_GET['cabang_id']) ? sanitize($conn, $_GET['cabang_id']) : 'all';

if($is_super) {
    if($req_cabang === 'all') {
        $cf = "";
        $nama_cabang_report = "Semua Cabang";
    } else {
        $cid = (int)$req_cabang;
        $cf = "AND cabang_id = $cid";
        $nc_q = mysqli_query($conn, "SELECT nama_cabang FROM cabang WHERE id=$cid");
        $nama_cabang_report = mysqli_num_rows($nc_q)>0 ? mysqli_fetch_assoc($nc_q)['nama_cabang'] : 'Unknown';
    }
} else {
    $cid = (int)($_SESSION['user']['cabang_id'] ?? 1);
    $cf = "AND cabang_id = $cid";
    $nc_q = mysqli_query($conn, "SELECT nama_cabang FROM cabang WHERE id=$cid");
    $nama_cabang_report = mysqli_num_rows($nc_q)>0 ? mysqli_fetch_assoc($nc_q)['nama_cabang'] : 'Unknown';
}
$q_cabang_list = mysqli_query($conn, "SELECT * FROM cabang ORDER BY nama_cabang ASC");

$results = [];
$target_hari = $hari_ini + $jangkauan;

// Query to show active entities whose due date is coming up within the next $jangkauan days.
if ($kategori == 'Semua' || $kategori == 'Member') {
    $q_member = mysqli_query($conn, "SELECT nama, tanggal_jatuh_tempo, 'Member' as kategori FROM member WHERE status='Aktif' AND tanggal_jatuh_tempo BETWEEN $hari_ini AND $target_hari $cf");
    while($r = mysqli_fetch_assoc($q_member)) $results[] = $r;
}
if ($kategori == 'Semua' || $kategori == 'Ruko') {
    $q_ruko = mysqli_query($conn, "SELECT nama_ruko as nama, tanggal_jatuh_tempo, 'Ruko' as kategori FROM ruko WHERE status='Aktif' AND tanggal_jatuh_tempo BETWEEN $hari_ini AND $target_hari $cf");
    while($r = mysqli_fetch_assoc($q_ruko)) $results[] = $r;
}
if ($kategori == 'Semua' || $kategori == 'Pedagang') {
    $q_ped = mysqli_query($conn, "SELECT nama, tanggal_jatuh_tempo, 'Pedagang' as kategori FROM pedagang WHERE status='Aktif' AND tanggal_jatuh_tempo BETWEEN $hari_ini AND $target_hari $cf");
    while($r = mysqli_fetch_assoc($q_ped)) $results[] = $r;
}

usort($results, function($a, $b) {
    return $a['tanggal_jatuh_tempo'] - $b['tanggal_jatuh_tempo'];
});

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<style>
    @media print {
        #sidebar, .navbar, .form-filter, .btn-export { display: none !important; }
        #content { width: 100% !important; margin: 0 !important; padding: 0 !important; }
        .card { border: none !important; box-shadow: none !important; }
        body { background: white; }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">Laporan Proyeksi Jatuh Tempo</h4>
    <div class="btn-export gap-2 d-flex">
        <button onclick="window.print()" class="btn btn-secondary"><i class="fas fa-print"></i> Print / PDF</button>
        <button onclick="exportTableToExcel('jatuhTempoTable', 'Laporan_JatuhTempo')" class="btn btn-success"><i class="fas fa-file-excel"></i> Export Excel</button>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4 form-filter">
    <div class="card-body bg-light rounded">
        <form method="GET" class="d-flex w-100 align-items-end flex-wrap gap-3">
            <div style="width: 250px;">
                <label class="form-label fw-bold text-muted small">Jangkauan Hari ke Depan</label>
                <select name="jangkauan" class="form-select">
                    <option value="3" <?= $jangkauan == 3 ? 'selected' : '' ?>>3 Hari Kedepan</option>
                    <option value="5" <?= $jangkauan == 5 ? 'selected' : '' ?>>5 Hari Kedepan</option>
                    <option value="7" <?= $jangkauan == 7 ? 'selected' : '' ?>>1 Minggu Kedepan</option>
                    <option value="14" <?= $jangkauan == 14 ? 'selected' : '' ?>>2 Minggu Kedepan</option>
                </select>
            </div>
            <div style="width: 200px;">
                <label class="form-label fw-bold text-muted small">Filter Kategori</label>
                <select name="kategori" class="form-select">
                    <option value="Semua" <?= $kategori == 'Semua' ? 'selected' : '' ?>>Semua Kategori</option>
                    <option value="Member" <?= $kategori == 'Member' ? 'selected' : '' ?>>Member</option>
                    <option value="Ruko" <?= $kategori == 'Ruko' ? 'selected' : '' ?>>Ruko</option>
                    <option value="Pedagang" <?= $kategori == 'Pedagang' ? 'selected' : '' ?>>Pedagang</option>
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
                <button type="submit" class="btn btn-info text-white fw-bold px-4"><i class="fas fa-search me-2"></i> Tampilkan</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0 border-top border-info border-4">
    <div class="card-header bg-white pb-0 border-0">
        <div class="text-center mb-4 mt-3">
            <h5 class="fw-bold mb-1 text-info">PROYEKSI JATUH TEMPO PJR PARKING</h5>
            <p class="text-muted mb-0">Mulai Hari Ini (Tgl <?= $hari_ini ?>) Hingga <?= $jangkauan ?> Hari ke depan (Tgl <?= min(31, $target_hari) ?>)</p>
            <p class="text-muted small">Cabang: <?= htmlspecialchars($nama_cabang_report) ?></p>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive px-4 pb-4">
            <table class="table table-bordered table-striped" id="jatuhTempoTable">
                <thead class="bg-light text-center">
                    <tr>
                        <th width="5%">No</th>
                        <th width="40%">Nama Entitas</th>
                        <th width="25%">Kategori</th>
                        <th width="30%">Tanggal Jatuh Tempo Bulan Ini</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($results) > 0): $no=1; foreach($results as $row): 
                        $sisa_hari = $row['tanggal_jatuh_tempo'] - $hari_ini;
                    ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($row['nama']) ?></td>
                        <td class="text-center"><span class="badge bg-secondary"><?= $row['kategori'] ?></span></td>
                        <td class="text-center fw-bold">
                            Tgl <?= $row['tanggal_jatuh_tempo'] ?> 
                            <span class="badge bg-warning text-dark ms-2">(Dalam <?= $sisa_hari ?> Hari)</span>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="4" class="text-center py-4 text-muted">Aman. Tidak ada entitas yang akan jatuh tempo dalam <?= $jangkauan ?> hari kedepan.</td></tr>
                    <?php endif; ?>
                </tbody>
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
        var blob = new Blob(['\ufeff', tableHTML], { type: dataType });
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
    @page { size: A4 portrait; margin: 1cm; }
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
