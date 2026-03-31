<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Admin', 'Super Admin', 'HRD', 'Bendahara']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$query = mysqli_query($conn, "
    SELECT p.*, k.nama, k.nik, k.divisi, k.jabatan 
    FROM penggajian p 
    JOIN karyawan k ON p.karyawan_id = k.id 
    WHERE p.id = $id
");

if(mysqli_num_rows($query) == 0) {
    echo "Slip Gaji tidak ditemukan."; exit;
}
$data = mysqli_fetch_assoc($query);

$months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
$periode = $months[$data['bulan']-1] . " " . $data['tahun'];

$kotor = $data['gaji_pokok'] + $data['tunjangan_jabatan'] + $data['tunjangan_makan'] + $data['tunjangan_transport'];
$potong = $data['potongan_kasbon'] + $data['potongan_pinjaman'] + $data['potongan_alpha'];

require_once __DIR__ . '/../../layouts/header.php';
?>
<!-- Hide sidebar and navbar for pure print layout -->
<style>
    body { background-color: #f4f6f9; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
    #sidebar, .navbar { display: none !important; }
    #content { width: 100% !important; margin: 0 !important; padding: 2rem !important; }
    
    .slip-container {
        max-width: 650px;
        margin: 0 auto;
        background: #fff;
        padding: 30px 40px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        color: #1f2937;
    }
    
    .slip-header { border-bottom: 2px solid #1f2937; padding-bottom: 10px; margin-bottom: 20px; }
    
    .table-slip td { padding: 4px 0; border: none; font-size: 12px; vertical-align: middle; }
    .table-slip .nominal { text-align: right; font-weight: 600; font-family: 'Courier New', Courier, monospace; letter-spacing: -0.5px; white-space: nowrap; }
    
    .section-title {
        font-weight: bold;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 1px;
        color: #6b7280;
        border-bottom: 1px dashed #e5e7eb;
        margin-bottom: 8px;
        padding-bottom: 4px;
        margin-top: 15px;
    }
    
    .total-box {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 12px 20px;
    }
    
    .sign-box { margin-top: 40px; text-align: center; font-size: 11px; }
    .sign-box p { margin-bottom: 70px; color: #4b5563; }
    
    @media print {
        @page { size: portrait; margin: 1cm; }
        body { background-color: #fff; padding: 0; margin: 0; }
        #content { padding: 0 !important; }
        .slip-container { border: none; box-shadow: none; padding: 0; width: 100%; max-width: 100%; }
        .d-print-none { display: none !important; }
    }
</style>

<div class="mb-3 text-center d-print-none">
    <button onclick="window.print()" class="btn btn-primary btn-sm px-4 shadow-sm"><i class="fas fa-print me-2"></i> Cetak Slip</button>
    <button onclick="window.close()" class="btn btn-light btn-sm px-4 border shadow-sm ms-2"><i class="fas fa-times me-2"></i> Tutup</button>
</div>

<div class="slip-container">
    <div class="slip-header d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-1" style="color: #111827; letter-spacing: -0.5px;">PJR PARKING</h4>
            <div style="font-size: 10px; color: #6b7280;">Jl. Raya Parkir No. 1, Jakarta<br>Telp: (+62) 812-8209-0900</div>
        </div>
        <div class="text-end">
            <h5 class="fw-bold text-uppercase mb-1" style="color: #374151; letter-spacing: 2px;">Slip Gaji</h5>
            <div style="font-size: 11px; font-weight: 600; color: #10b981;">Periode: <?= $periode ?></div>
        </div>
    </div>
    
    <div class="row mb-3" style="background-color: #fafafa; padding: 10px; border-radius: 4px; margin: 0;">
        <div class="col-6">
            <table class="table-slip w-100">
                <tr><td width="35%" class="text-muted">ID Pegawai</td><td width="5%">:</td><td class="fw-bold"><?= htmlspecialchars($data['nik']) ?></td></tr>
                <tr><td class="text-muted">Nama Lengkap</td><td>:</td><td class="fw-bold fs-6 text-primary"><?= htmlspecialchars($data['nama']) ?></td></tr>
            </table>
        </div>
        <div class="col-6">
            <table class="table-slip w-100">
                <tr><td width="35%" class="text-muted">Jabatan</td><td width="5%">:</td><td class="fw-bold"><?= htmlspecialchars($data['jabatan']) ?></td></tr>
                <tr><td class="text-muted">Divisi</td><td>:</td><td class="fw-bold"><?= htmlspecialchars($data['divisi']) ?></td></tr>
            </table>
        </div>
    </div>
    
    <div class="row mb-2">
        <div class="col-12">
            <div class="section-title">A. Penerimaan</div>
            <table class="table-slip w-100 mb-0">
                <tr><td>Gaji Pokok</td><td class="nominal"><?= format_rupiah($data['gaji_pokok']) ?></td></tr>
                <?php if($data['tunjangan_jabatan'] > 0): ?><tr><td>Tunjangan Jabatan</td><td class="nominal"><?= format_rupiah($data['tunjangan_jabatan']) ?></td></tr><?php endif; ?>
                <?php if($data['tunjangan_makan'] > 0): ?><tr><td>Tunjangan Makan</td><td class="nominal"><?= format_rupiah($data['tunjangan_makan']) ?></td></tr><?php endif; ?>
                <?php if($data['tunjangan_transport'] > 0): ?><tr><td>Tunjangan Transport</td><td class="nominal"><?= format_rupiah($data['tunjangan_transport']) ?></td></tr><?php endif; ?>
            </table>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-12">
            <div class="section-title">B. Potongan</div>
            <table class="table-slip w-100 mb-0">
                <?php if($potong == 0): ?>
                    <tr><td class="text-muted fst-italic" style="font-size: 11px;">Tidak ada potongan.</td></tr>
                <?php else: ?>
                    <?php if($data['potongan_kasbon'] > 0): ?><tr><td>Pelunasan Kasbon</td><td class="nominal text-danger">- <?= format_rupiah($data['potongan_kasbon']) ?></td></tr><?php endif; ?>
                    <?php if($data['potongan_pinjaman'] > 0): ?><tr><td>Cicilan Pinjaman</td><td class="nominal text-danger">- <?= format_rupiah($data['potongan_pinjaman']) ?></td></tr><?php endif; ?>
                    <?php if($data['potongan_alpha'] > 0): ?><tr><td>Absensi (Alpha)</td><td class="nominal text-danger">- <?= format_rupiah($data['potongan_alpha']) ?></td></tr><?php endif; ?>
                <?php endif; ?>
            </table>
        </div>
    </div>
    
    <div class="row align-items-center mb-4">
        <div class="col-6 pe-4">
            <table class="table-slip w-100 mt-1 border-top pt-1">
                <tr><td class="text-muted">Total Kotor</td><td class="nominal fw-bold"><?= format_rupiah($kotor) ?></td></tr>
            </table>
        </div>
        <div class="col-6 ps-4 border-start">
            <table class="table-slip w-100 mt-1 border-top pt-1">
                <tr><td class="text-muted">Total Potongan</td><td class="nominal fw-bold text-danger"><?= format_rupiah($potong) ?></td></tr>
            </table>
        </div>
    </div>
    
    <div class="total-box d-flex justify-content-between align-items-center mb-3">
        <div>
            <div style="font-size: 10px; color: #64748b; font-weight: bold; text-transform: uppercase;">Total Diterima (Net Pay)</div>
            <div style="font-size: 11px; font-style: italic; color: #94a3b8;">* Ditransfer ke rekening terdaftar</div>
        </div>
        <h4 class="mb-0 fw-bold" style="color: #047857; font-family: 'Courier New', Courier, monospace; letter-spacing: -1px;"><?= format_rupiah($data['total_gaji_bersih']) ?></h4>
    </div>
    
    <div class="row sign-box">
        <div class="col-6">
            <p>Penerima,</p>
            <div class="fw-bold" style="text-decoration: underline; text-underline-offset: 4px;"><?= htmlspecialchars($data['nama']) ?></div>
            <div style="color: #9ca3af; margin-top: 3px;">Karyawan</div>
        </div>
        <div class="col-6">
            <p>Jakarta, <?= date('d M Y', strtotime($data['tanggal_cair'])) ?><br>Staf Personalia,</p>
            <div class="fw-bold" style="text-decoration: underline; text-underline-offset: 4px;"><?= htmlspecialchars($_SESSION['user']['nama'] ?? 'HRD / Keuangan') ?></div>
            <div style="color: #9ca3af; margin-top: 3px;">Manajemen PJR</div>
        </div>
    </div>
</div>

<!-- Essential scripts that header usually brings but we hid -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
