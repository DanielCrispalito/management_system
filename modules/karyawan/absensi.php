<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Admin', 'Super Admin', 'HRD']);

$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
$cabang_id = $_SESSION['user']['cabang_id'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['absensi'])) {
        foreach ($_POST['absensi'] as $karyawan_id => $data) {
            $h = (int)$data['hadir'];
            $i = (int)$data['izin'];
            $s = (int)$data['sakit'];
            $a = (int)$data['alpha'];
            
            // Upsert
            $check = mysqli_query($conn, "SELECT id FROM absensi WHERE karyawan_id = $karyawan_id AND bulan = $bulan AND tahun = $tahun");
            if (mysqli_num_rows($check) > 0) {
                mysqli_query($conn, "UPDATE absensi SET hadir = $h, izin = $i, sakit = $s, alpha = $a WHERE karyawan_id = $karyawan_id AND bulan = $bulan AND tahun = $tahun");
            } else {
                mysqli_query($conn, "INSERT INTO absensi (karyawan_id, bulan, tahun, hadir, izin, sakit, alpha) VALUES ($karyawan_id, $bulan, $tahun, $h, $i, $s, $a)");
            }
        }
        set_flash_message('success', "Data absensi bulan $bulan tahun $tahun berhasil disimpan.");
        redirect("/modules/karyawan/absensi.php?bulan=$bulan&tahun=$tahun");
    }
}

// Fetch active employees + current month's absensi
$query_emp = "
    SELECT k.*, 
           a.hadir, a.izin, a.sakit, a.alpha 
    FROM karyawan k 
    LEFT JOIN absensi a ON k.id = a.karyawan_id AND a.bulan = $bulan AND a.tahun = $tahun
    WHERE k.status = 'Aktif' 
";
if ($_SESSION['user']['role'] !== 'Super Admin') {
    $query_emp .= " AND k.cabang_id = $cabang_id ";
}
$query_emp .= " ORDER BY k.nama ASC";
$employees = mysqli_query($conn, $query_emp);

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">Input Absensi Karyawan</h4>
</div>

<?php display_flash_message(); ?>

<div class="card shadow-sm mb-4">
    <div class="card-body bg-light rounded">
        <form method="GET" class="row align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold">Pilih Bulan</label>
                <select name="bulan" class="form-select">
                    <?php 
                    $months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
                    for($i=1; $i<=12; $i++): 
                    ?>
                        <option value="<?= $i ?>" <?= $i == $bulan ? 'selected' : '' ?>><?= $months[$i-1] ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Pilih Tahun</label>
                <input type="number" name="tahun" class="form-control" value="<?= $tahun ?>" min="2020" max="2050">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary fw-bold"><i class="fas fa-filter me-1"></i> Buka Absensi</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0 border-top border-info border-4">
    <form method="POST">
        <div class="card-header bg-white pb-0 pt-3 border-0">
            <h5 class="fw-bold mb-1"><i class="fas fa-calendar-check text-info me-2"></i> Rekap Kehadiran Bulan: <?= $months[$bulan-1] ?> <?= $tahun ?></h5>
            <p class="text-muted small">Silakan input jumlah hari ketidakhadiran (Hadir, Izin, Sakit, Alpha) untuk bulan ini.</p>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive px-4 pb-4">
                <table class="table table-bordered table-striped align-middle mb-0">
                    <thead class="bg-light text-center">
                        <tr>
                            <th width="5%">No</th>
                            <th width="25%" class="text-start">Karyawan</th>
                            <th width="15%">Posisi</th>
                            <th width="15%" class="text-success border-success">Hadir (Hari)</th>
                            <th width="10%" class="text-warning border-warning">Izin</th>
                            <th width="10%" class="text-info border-info">Sakit</th>
                            <th width="15%" class="text-danger border-danger">Alpha (Hari)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($employees) > 0): ?>
                            <?php $no = 1; while ($row = mysqli_fetch_assoc($employees)): ?>
                                <tr>
                                    <td class="text-center"><?= $no++ ?></td>
                                    <td>
                                        <div class="fw-bold text-primary"><?= htmlspecialchars($row['nama']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($row['nik'] ?? '') ?></small>
                                    </td>
                                    <td class="text-center">
                                        <div class="small fw-semibold"><?= htmlspecialchars($row['jabatan'] ?? '') ?></div>
                                        <span class="badge bg-secondary" style="font-size: 0.70em;"><?= htmlspecialchars($row['divisi'] ?? '') ?></span>
                                    </td>
                                    <td>
                                        <input type="number" name="absensi[<?= $row['id'] ?>][hadir]" class="form-control form-control-sm text-center fw-bold text-success border-success" min="0" max="31" value="<?= $row['hadir'] ?? 0 ?>">
                                    </td>
                                    <td>
                                        <input type="number" name="absensi[<?= $row['id'] ?>][izin]" class="form-control form-control-sm text-center" min="0" max="31" value="<?= $row['izin'] ?? 0 ?>">
                                    </td>
                                    <td>
                                        <input type="number" name="absensi[<?= $row['id'] ?>][sakit]" class="form-control form-control-sm text-center" min="0" max="31" value="<?= $row['sakit'] ?? 0 ?>">
                                    </td>
                                    <td>
                                        <input type="number" name="absensi[<?= $row['id'] ?>][alpha]" class="form-control form-control-sm text-center fw-bold text-danger border-danger" min="0" max="31" value="<?= $row['alpha'] ?? 0 ?>">
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">Tidak ada karyawan aktif.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if (mysqli_num_rows($employees) > 0): ?>
        <div class="card-footer bg-white text-end py-3">
            <button type="submit" class="btn btn-success px-5 fw-bold"><i class="fas fa-save me-1"></i> Simpan Absensi Bulan <?= $months[$bulan-1] ?></button>
        </div>
        <?php endif; ?>
    </form>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
