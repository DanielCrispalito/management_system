<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Admin', 'HRD']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = sanitize($conn, $_POST['nama']);
    $jenis_member = sanitize($conn, $_POST['jenis_member']);
    $tipe_pembayaran = sanitize($conn, $_POST['tipe_pembayaran']);
    $tanggal_jatuh_tempo = (int)$_POST['tanggal_jatuh_tempo'];
    $catatan = sanitize($conn, $_POST['catatan']);
    $nominal = ($tipe_pembayaran == 'Kolektif') ? (float)$_POST['nominal_iuran'] : 0;
    
    $cabang_id = $_SESSION['user']['cabang_id'] ?? 1;
    $stmt = $conn->prepare("INSERT INTO member (cabang_id, nama, jenis_member, tipe_pembayaran, nominal_iuran, tanggal_jatuh_tempo, catatan) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssdis", $cabang_id, $nama, $jenis_member, $tipe_pembayaran, $nominal, $tanggal_jatuh_tempo, $catatan);
    
    if ($stmt->execute()) {
        $member_id = $conn->insert_id;
        set_flash_message('success', 'Data member berhasil ditambahkan. Silahkan tambahkan detail personil jika diperlukan.');
        redirect("/pjr_parking/modules/member/detail.php?id=$member_id");
    } else {
        set_flash_message('error', 'Gagal menambahkan member.');
    }
}

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">Tambah Member Baru</h4>
    <a href="/pjr_parking/modules/member/index.php" class="btn btn-secondary shadow-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
</div>

<?php display_flash_message(); ?>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="alert alert-info border-0 shadow-sm">
            <i class="fas fa-info-circle me-2"></i> <strong>Informasi Tipe Pembayaran:</strong><br>
            - <b>Kolektif</b>: Pembayaran dilakukan sekaligus oleh Perusahaan. Semua personil di dalamnya akan dianggap sudah bayar jika Perusahaan membayar.<br>
            - <b>Individual</b>: Pembayaran dilakukan per personil / masing-masing kendaraan, meskipun tergabung dalam satu nama Perusahaan (atau member perorangan).
        </div>
        <form method="POST" action="">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Nama Member (Nama Perusahaan / Personal)</label>
                    <input type="text" name="nama" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Jenis Member</label>
                    <select name="jenis_member" class="form-select" required>
                        <option value="Perusahaan">Perusahaan</option>
                        <option value="Individual">Individual</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Tipe Pembayaran</label>
                    <select name="tipe_pembayaran" class="form-select" required>
                        <option value="Kolektif">Kolektif (1 Tagihan)</option>
                        <option value="Individual">Individual (Per Orang/Mobil)</option>
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6 mb-3" id="nominalWrapper" style="display:block;">
                    <label class="form-label fw-bold">Nominal Tagihan Kolektif <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="number" name="nominal_iuran" id="nominal_iuran" class="form-control" min="0" step="any" required>
                    </div>
                    <div class="form-text">Jika tagihan Individual, nominal diisi per-personil.</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Tanggal Jatuh Tempo (Setiap Bulan) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">Tanggal</span>
                        <input type="number" name="tanggal_jatuh_tempo" class="form-control" required min="1" max="31" value="5">
                    </div>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-12">
                    <label class="form-label fw-bold">Catatan</label>
                    <textarea name="catatan" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="text-end">
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i> Simpan & Lanjut ke Detail Personil</button>
            </div>
        </form>
    </div>
</div>

<script>
    const jenis = document.querySelector('[name=\"jenis_member\"]');
    const tipe = document.querySelector('[name=\"tipe_pembayaran\"]');
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
            tipe.style.pointerEvents = 'none';
            tipe.style.background = '#e9ecef';
        } else {
            tipe.style.pointerEvents = 'auto';
            tipe.style.background = '#fff';
        }
        toggleNominal();
    });
    
    tipe.addEventListener('change', toggleNominal);
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
