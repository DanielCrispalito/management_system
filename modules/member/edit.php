<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Admin', 'Bendahara']);

if (!isset($_GET['id'])) {
    redirect('/modules/member/index.php');
}

$id = (int)$_GET['id'];
$query = mysqli_query($conn, "SELECT * FROM member WHERE id = $id");
if (mysqli_num_rows($query) == 0) {
    redirect('/modules/member/index.php');
}
$member = mysqli_fetch_assoc($query);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = sanitize($conn, $_POST['nama']);
    $jenis = sanitize($conn, $_POST['jenis_member']);
    $tipe = sanitize($conn, $_POST['tipe_pembayaran']);
    $tgl_jt = (int)$_POST['tanggal_jatuh_tempo'];
    $catatan = sanitize($conn, $_POST['catatan']);
    $status = sanitize($conn, $_POST['status']);
    
    // Perihal nominal iuran: jika tipe pembayaran Kolektif, simpan nominalnya
    $nominal = ($tipe == 'Kolektif') ? (float)$_POST['nominal_iuran'] : 0;
    
    $stmt = $conn->prepare("UPDATE member SET nama=?, jenis_member=?, tipe_pembayaran=?, nominal_iuran=?, tanggal_jatuh_tempo=?, status=?, catatan=? WHERE id=?");
    $stmt->bind_param("sssdissi", $nama, $jenis, $tipe, $nominal, $tgl_jt, $status, $catatan, $id);
    
    if($stmt->execute()) {
        set_flash_message('success', 'Data Member berhasil diupdate.');
        redirect('/modules/member/index.php');
    } else {
        $error = "Terjadi kesalahan: " . mysqli_error($conn);
    }
}

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">Edit Data Member</h4>
    <a href="/modules/member/index.php" class="btn btn-secondary shadow-sm">
        <i class="fas fa-arrow-left me-1"></i> Kembali
    </a>
</div>

<div class="card shadow-sm border-0 border-top border-warning border-4">
    <div class="card-body p-4">
        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Nama Entitas/Member <span class="text-danger">*</span></label>
                    <input type="text" name="nama" class="form-control" required value="<?= htmlspecialchars($member['nama']) ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Status Aktif <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        <option value="Aktif" <?= $member['status'] == 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                        <option value="Nonaktif" <?= $member['status'] == 'Nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Jenis Member <span class="text-danger">*</span></label>
                    <select name="jenis_member" class="form-select" required id="jenis_member">
                        <option value="Perusahaan" <?= $member['jenis_member'] == 'Perusahaan' ? 'selected' : '' ?>>Perusahaan (Corporate)</option>
                        <option value="Individual" <?= $member['jenis_member'] == 'Individual' ? 'selected' : '' ?>>Individual (Perseorangan)</option>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Tipe Penagihan Pembayaran <span class="text-danger">*</span></label>
                    <select name="tipe_pembayaran" class="form-select" required id="tipe_pembayaran">
                        <option value="Kolektif" <?= $member['tipe_pembayaran'] == 'Kolektif' ? 'selected' : '' ?>>Kolektif (Satu tagihan pusat perusahaan)</option>
                        <option value="Individual" <?= $member['tipe_pembayaran'] == 'Individual' ? 'selected' : '' ?>>Individual (Masing-masing personil bayar sendiri)</option>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3" id="nominalWrapper" style="display: <?= $member['tipe_pembayaran'] == 'Kolektif' ? 'block' : 'none' ?>;">
                    <label class="form-label fw-bold">Nominal Tagihan Kolektif <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="number" name="nominal_iuran" id="nominal_iuran" class="form-control" min="0" step="any" value="<?= (float)$member['nominal_iuran'] ?>">
                    </div>
                    <div class="form-text">Jika tipe tagihan Individual, nominal diisi per-personil di menu Detail.</div>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Tanggal Jatuh Tempo (1-31) <span class="text-danger">*</span></label>
                    <input type="number" name="tanggal_jatuh_tempo" class="form-control" required min="1" max="31" value="<?= $member['tanggal_jatuh_tempo'] ?>">
                </div>

                <div class="col-md-12 mb-4">
                    <label class="form-label fw-bold">Catatan / Deskripsi Tambahan</label>
                    <textarea name="catatan" class="form-control" rows="3"><?= htmlspecialchars($member['catatan']) ?></textarea>
                </div>

                <div class="col-md-12">
                    <button type="submit" class="btn btn-warning w-100 fw-bold border-0 shadow-sm"><i class="fas fa-save me-1"></i> Update Data Member</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    const jenis = document.getElementById('jenis_member');
    const tipe = document.getElementById('tipe_pembayaran');
    const nomWrap = document.getElementById('nominalWrapper');
    const nomInput = document.getElementById('nominal_iuran');

    function toggleNominal() {
        if(tipe.value === 'Kolektif') {
            nomWrap.style.display = 'block';
            nomInput.setAttribute('required', 'required');
        } else {
            nomWrap.style.display = 'none';
            nomInput.removeAttribute('required');
        }
    }

    jenis.addEventListener('change', function() {
        if(this.value === 'Individual') {
            tipe.value = 'Individual';
            tipe.setAttribute('readonly', 'readonly');
            // Hack for readonly select visually
            tipe.style.pointerEvents = 'none';
            tipe.style.background = '#e9ecef';
        } else {
            tipe.removeAttribute('readonly');
            tipe.style.pointerEvents = 'auto';
            tipe.style.background = '#fff';
        }
        toggleNominal();
    });
    
    tipe.addEventListener('change', toggleNominal);
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
