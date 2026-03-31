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
$hari_ini = (int)date('d');

$is_current_month = ($bulan == (int)date('m') && $tahun == (int)date('Y'));
$is_past_month = ($tahun < (int)date('Y') || ($tahun == (int)date('Y') && $bulan < (int)date('m')));

// Tunggakan = Belum bayar dan (Bulan/Tahun lalu OR (Bulan ini DAN hari ini > tanggal jatuh tempo))

$is_terlambat = function($jatuh_tempo) use ($is_current_month, $is_past_month, $hari_ini) {
    if ($is_past_month) return true;
    if ($is_current_month && $hari_ini > $jatuh_tempo) return true;
    return false;
};

if ($kategori == 'Semua' || $kategori == 'Member') {
    $q_member = mysqli_query($conn, "SELECT * FROM member WHERE status = 'Aktif' $cf");
    while($m = mysqli_fetch_assoc($q_member)) {
        if ($is_terlambat($m['tanggal_jatuh_tempo'])) {
            $m_id = $m['id'];
            if($m['tipe_pembayaran'] == 'Kolektif') {
                $q_cek = mysqli_query($conn, "SELECT id FROM pembayaran_member WHERE member_id = $m_id AND bulan = $bulan AND tahun = $tahun");
                if (mysqli_num_rows($q_cek) == 0) {
                    $results[] = [
                        'nama' => $m['nama'], 'kategori' => 'Member (Kolektif)', 
                        'jatuh_tempo' => $m['tanggal_jatuh_tempo'], 'nominal' => 'Sesuai Kesepakatan'
                    ];
                }
            } else {
                // Individual
                $q_det = mysqli_query($conn, "SELECT id, nama_personil, kendaraan FROM member_detail WHERE member_id = $m_id");
                while($d = mysqli_fetch_assoc($q_det)) {
                    $d_id = $d['id'];
                    $q_cek = mysqli_query($conn, "SELECT id FROM pembayaran_member WHERE member_detail_id = $d_id AND bulan = $bulan AND tahun = $tahun");
                    if (mysqli_num_rows($q_cek) == 0) {
                        $results[] = [
                            'nama' => $m['nama'] . ' - ' . $d['nama_personil'] . ' ('.$d['kendaraan'].')', 
                            'kategori' => 'Member (Individual)', 
                            'jatuh_tempo' => $m['tanggal_jatuh_tempo'], 'nominal' => 'Bervariasi'
                        ];
                    }
                }
            }
        }
    }
}

if ($kategori == 'Semua' || $kategori == 'Ruko') {
    $q_ruko = mysqli_query($conn, "SELECT * FROM ruko WHERE status = 'Aktif' $cf");
    while($r = mysqli_fetch_assoc($q_ruko)) {
        if ($is_terlambat($r['tanggal_jatuh_tempo'])) {
            $r_id = $r['id'];
            $q_cek = mysqli_query($conn, "SELECT id FROM pembayaran_ruko WHERE ruko_id = $r_id AND bulan = $bulan AND tahun = $tahun");
            if (mysqli_num_rows($q_cek) == 0) {
                $results[] = [
                    'nama' => $r['nama_ruko'], 'kategori' => 'Ruko', 
                    'jatuh_tempo' => $r['tanggal_jatuh_tempo'], 'nominal' => format_rupiah($r['nominal_iuran'])
                ];
            }
        }
    }
}

if ($kategori == 'Semua' || $kategori == 'Pedagang' || $kategori == 'Pedagang Bulanan') {
    $q_ped = mysqli_query($conn, "SELECT * FROM pedagang WHERE status = 'Aktif' AND kategori = 'Pedagang Bulanan' $cf");
    while($p = mysqli_fetch_assoc($q_ped)) {
        if ($is_terlambat($p['tanggal_jatuh_tempo'])) {
            $p_id = $p['id'];
            $q_cek = mysqli_query($conn, "SELECT id FROM pembayaran_pedagang WHERE pedagang_id = $p_id AND bulan = $bulan AND tahun = $tahun");
            if (mysqli_num_rows($q_cek) == 0) {
                $results[] = [
                    'nama' => $p['nama'], 'kategori' => 'Pedagang Bulanan',
                    'jatuh_tempo' => $p['tanggal_jatuh_tempo'], 'nominal' => format_rupiah($p['nominal_iuran'])
                ];
            }
        }
    }
}

// Pedagang Pagi & Malam: Tunggakan = belum bayar untuk bulan ini (setidaknya 1 hari)
if ($kategori == 'Semua' || $kategori == 'Pedagang' || $kategori == 'Pedagang Pagi') {
    $q_ped = mysqli_query($conn, "SELECT * FROM pedagang WHERE status = 'Aktif' AND kategori = 'Pedagang Pagi' $cf");
    while($p = mysqli_fetch_assoc($q_ped)) {
        $p_id = $p['id'];
        // Count distinct days paid in this month
        $q_cek = mysqli_query($conn, "SELECT COUNT(DISTINCT tanggal_bayar) as hari FROM pembayaran_pedagang WHERE pedagang_id = $p_id AND bulan = $bulan AND tahun = $tahun");
        $hari_bayar = (int)mysqli_fetch_assoc($q_cek)['hari'];
        $hari_dlm_bulan = (int)date('t', mktime(0,0,0,$bulan,1,$tahun));
        $hari_sudah_lewat = ($is_past_month) ? $hari_dlm_bulan : (int)date('d');
        $belum_bayar = max(0, $hari_sudah_lewat - $hari_bayar);
        if($belum_bayar > 0 && ($is_past_month || $is_current_month)) {
            $results[] = [
                'nama' => $p['nama'], 'kategori' => 'Pedagang Pagi (Harian)',
                'jatuh_tempo' => '-', 'nominal' => "$belum_bayar hari x " . format_rupiah($p['nominal_iuran'])
            ];
        }
    }
}

if ($kategori == 'Semua' || $kategori == 'Pedagang' || $kategori == 'Pedagang Malam') {
    $q_ped = mysqli_query($conn, "SELECT * FROM pedagang WHERE status = 'Aktif' AND kategori = 'Pedagang Malam' $cf");
    while($p = mysqli_fetch_assoc($q_ped)) {
        $p_id = $p['id'];
        $q_cek = mysqli_query($conn, "SELECT COUNT(DISTINCT tanggal_bayar) as hari FROM pembayaran_pedagang WHERE pedagang_id = $p_id AND bulan = $bulan AND tahun = $tahun");
        $hari_bayar = (int)mysqli_fetch_assoc($q_cek)['hari'];
        $hari_dlm_bulan = (int)date('t', mktime(0,0,0,$bulan,1,$tahun));
        $hari_sudah_lewat = ($is_past_month) ? $hari_dlm_bulan : (int)date('d');
        $belum_bayar = max(0, $hari_sudah_lewat - $hari_bayar);
        if($belum_bayar > 0 && ($is_past_month || $is_current_month)) {
            $results[] = [
                'nama' => $p['nama'], 'kategori' => 'Pedagang Malam (Harian)',
                'jatuh_tempo' => '-', 'nominal' => "$belum_bayar hari x " . format_rupiah($p['nominal_iuran'])
            ];
        }
    }
}

$months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

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
    <h4 class="page-title mb-0">Laporan Tunggakan Aktif</h4>
    <div class="btn-export gap-2 d-flex">
        <button onclick="window.print()" class="btn btn-secondary"><i class="fas fa-print"></i> Print / PDF</button>
        <button onclick="exportTableToExcel('tunggakanTable', 'Laporan_Tunggakan')" class="btn btn-success"><i class="fas fa-file-excel"></i> Export Excel</button>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4 form-filter">
    <div class="card-body bg-light rounded">
        <form method="GET" class="d-flex w-100 align-items-end flex-wrap gap-3">
            <div style="width: 150px;">
                <label class="form-label fw-bold text-muted small">Bulan Penagihan</label>
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
                <label class="form-label fw-bold text-muted small">Filter Kategori</label>
                <select name="kategori" class="form-select">
                    <option value="Semua" <?= $kategori == 'Semua' ? 'selected' : '' ?>>Semua Kategori</option>
                    <option value="Member" <?= $kategori == 'Member' ? 'selected' : '' ?>>Member</option>
                    <option value="Ruko" <?= $kategori == 'Ruko' ? 'selected' : '' ?>>Ruko</option>
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
                <button type="submit" class="btn btn-danger fw-bold px-4"><i class="fas fa-search me-2"></i> Cari Tunggakan</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0 border-top border-danger border-4">
    <div class="card-header bg-white pb-0 border-0">
        <div class="text-center mb-4 mt-3">
            <h5 class="fw-bold mb-1 text-danger">DAFTAR TUNGGAKAN & KETERLAMBATAN PJR PARKING</h5>
            <p class="text-muted mb-0">Periode Tagihan: <?= $months[$bulan-1] ?> <?= $tahun ?> | Cabang: <?= htmlspecialchars($nama_cabang_report) ?></p>
            <p class="text-muted small">Kategori: <?= htmlspecialchars($kategori) ?></p>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive px-4 pb-4">
            <table class="table table-bordered table-striped" id="tunggakanTable">
                <thead class="bg-light text-center">
                    <tr>
                        <th width="5%">No</th>
                        <th width="35%">Nama Entitas Terutang</th>
                        <th width="20%">Kategori</th>
                        <th width="15%">Jatuh Tempo</th>
                        <th width="25%">Estimasi Tagihan / Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($results) > 0): $no=1; foreach($results as $row): ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($row['nama']) ?></td>
                        <td class="text-center"><span class="badge bg-secondary"><?= $row['kategori'] ?></span></td>
                        <td class="text-center text-danger fw-bold">Tgl <?= $row['jatuh_tempo'] ?></td>
                        <td class="text-end fw-bold"><?= $row['nominal'] ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="5" class="text-center py-4">Luar Biasa! Tidak ada tunggakan pada kriteria ini.</td></tr>
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
