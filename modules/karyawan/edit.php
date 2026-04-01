<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Admin', 'HRD']);

if (!isset($_GET['id'])) {
    redirect('/modules/karyawan/index.php');
}

$id = (int)$_GET['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nik = sanitize($conn, $_POST['nik']);
    $nama = sanitize($conn, $_POST['nama']);
    $divisi = sanitize($conn, $_POST['divisi']);
    $jabatan = sanitize($conn, $_POST['jabatan']);
    $tanggal_masuk = sanitize($conn, $_POST['tanggal_masuk']);
    $status = sanitize($conn, $_POST['status']);
    
    $alamat = sanitize($conn, $_POST['alamat']);
    $no_hp = sanitize($conn, $_POST['no_hp']);
    $email = sanitize($conn, $_POST['email']);
    $gaji_pokok = (float)$_POST['gaji_pokok'];
    $t_jabatan = (float)$_POST['tunjangan_jabatan'];
    $t_makan = (float)$_POST['tunjangan_makan'];
    $t_transport = (float)$_POST['tunjangan_transport'];
    
    // Check NIK exclude current id
    $check = mysqli_query($conn, "SELECT id FROM karyawan WHERE nik = '$nik' AND id != $id");
    if(mysqli_num_rows($check) > 0) {
        set_flash_message('error', 'NIK sudah dipakai oleh karyawan lain!');
    } else {
        $stmt = $conn->prepare("UPDATE karyawan SET nik=?, nama=?, divisi=?, jabatan=?, tanggal_masuk=?, status=?, alamat=?, no_hp=?, email=?, gaji_pokok=?, tunjangan_jabatan=?, tunjangan_makan=?, tunjangan_transport=? WHERE id=?");
        $stmt->bind_param("sssssssssddddi", $nik, $nama, $divisi, $jabatan, $tanggal_masuk, $status, $alamat, $no_hp, $email, $gaji_pokok, $t_jabatan, $t_makan, $t_transport, $id);
        if ($stmt->execute()) {
            set_flash_message('success', 'Data karyawan berhasil diperbarui.');
            redirect('/modules/karyawan/index.php');
        } else {
            set_flash_message('error', 'Gagal memperbarui data: ' . mysqli_error($conn));
        }
    }
}

$query = mysqli_query($conn, "SELECT * FROM karyawan WHERE id = $id");
if (mysqli_num_rows($query) == 0) {
    redirect('/modules/karyawan/index.php');
}
$data = mysqli_fetch_assoc($query);

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">Edit Data Karyawan</h4>
    <a href="/modules/karyawan/index.php" class="btn btn-secondary shadow-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
</div>

<?php display_flash_message(); ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="">
            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">A. Informasi Dasar & Kontak</h6>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">NIK Karyawan <span class="text-danger">*</span></label>
                    <input type="text" name="nik" class="form-control" required value="<?= htmlspecialchars($data['nik']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="nama" class="form-control" required value="<?= htmlspecialchars($data['nama']) ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Alamat Lengkap</label>
                <textarea name="alamat" class="form-control" rows="2"><?= htmlspecialchars($data['alamat'] ?? '') ?></textarea>
            </div>
            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold">No. Headphone (WhatsApp)</label>
                    <input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($data['no_hp'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Alamat Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($data['email'] ?? '') ?>">
                </div>
            </div>

            <h6 class="fw-bold text-info border-bottom pb-2 mb-3 mt-4">B. Posisi & Jabatan</h6>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Divisi <span class="text-danger">*</span></label>
                    <select name="divisi" class="form-select" required>
                        <option value="Parkir" <?= $data['divisi'] == 'Parkir' ? 'selected' : '' ?>>Parkir</option>
                        <option value="Security" <?= $data['divisi'] == 'Security' ? 'selected' : '' ?>>Security</option>
                        <option value="Admin" <?= $data['divisi'] == 'Admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="Manajemen" <?= $data['divisi'] == 'Manajemen' ? 'selected' : '' ?>>Manajemen</option>
                        <option value="Lainnya" <?= $data['divisi'] == 'Lainnya' ? 'selected' : '' ?>>Lainnya</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Jabatan <span class="text-danger">*</span></label>
                    <input type="text" name="jabatan" class="form-control" required value="<?= htmlspecialchars($data['jabatan']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        <option value="Aktif" <?= $data['status'] == 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                        <option value="Nonaktif" <?= $data['status'] == 'Nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-bold">Tanggal Masuk <span class="text-danger">*</span></label>
                <input type="date" name="tanggal_masuk" class="form-control" required value="<?= $data['tanggal_masuk'] ?>" style="max-width: 300px;">
            </div>

            <h6 class="fw-bold text-success border-bottom pb-2 mb-3 mt-4">C. Informasi Penggajian (Gaji Pokok & Tunjangan)</h6>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Gaji Pokok (Rp)</label>
                    <input type="number" name="gaji_pokok" class="form-control" min="0" step="5000" value="<?= round($data['gaji_pokok']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Tunjangan Jabatan (Rp)</label>
                    <input type="number" name="tunjangan_jabatan" class="form-control" min="0" step="5000" value="<?= round($data['tunjangan_jabatan']) ?>">
                </div>
            </div>
            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Tunjangan Makan (Rp) / Bulan</label>
                    <input type="number" name="tunjangan_makan" class="form-control" min="0" step="5000" value="<?= round($data['tunjangan_makan']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Tunjangan Transport (Rp) / Bulan</label>
                    <input type="number" name="tunjangan_transport" class="form-control" min="0" step="5000" value="<?= round($data['tunjangan_transport']) ?>">
                </div>
            </div>
            <div class="text-end">
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i> Update Data</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
