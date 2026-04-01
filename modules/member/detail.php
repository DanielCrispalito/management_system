<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Admin', 'HRD']);

if (!isset($_GET['id'])) {
    redirect('/modules/member/index.php');
}

$id = (int)$_GET['id'];

// Get Member Info
$q_member = mysqli_query($conn, "SELECT * FROM member WHERE id = $id");
if (mysqli_num_rows($q_member) == 0) {
    redirect('/modules/member/index.php');
}
$member = mysqli_fetch_assoc($q_member);

// Handle Toggle Status Personil (Soft Delete)
if (isset($_GET['toggle_personil']) && isset($_GET['status_to'])) {
    $dp_id = (int)$_GET['toggle_personil'];
    $to = ($_GET['status_to'] === 'Aktif') ? 'Aktif' : 'Tidak Aktif';
    mysqli_query($conn, "UPDATE member_detail SET status='$to' WHERE id=$dp_id AND member_id=$id");
    set_flash_message('success', 'Status personil diubah menjadi: ' . $to);
    redirect("/modules/member/detail.php?id=$id");
}

// Handle Delete Personil (Permanent - kept for cleanup purposes)
if (isset($_GET['delete_personil'])) {
    $dp_id = (int)$_GET['delete_personil'];
    mysqli_query($conn, "DELETE FROM member_detail WHERE id = $dp_id AND member_id = $id");
    set_flash_message('success', 'Data personil dihapus permanen.');
    redirect("/modules/member/detail.php?id=$id");
}

// Handle Add Personil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_personil'])) {
    $nama_personil = sanitize($conn, $_POST['nama_personil']);
    $kendaraan = sanitize($conn, $_POST['kendaraan']);
    $nopol = sanitize($conn, $_POST['nopol']);
    $nominal = (float)$_POST['nominal_iuran'];

    $stmt = $conn->prepare("INSERT INTO member_detail (member_id, nama_personil, kendaraan, nopol, nominal_iuran) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssd", $id, $nama_personil, $kendaraan, $nopol, $nominal);
    if($stmt->execute()) {
        set_flash_message('success', 'Personil berhasil ditambahkan.');
    } else {
        set_flash_message('error', 'Gagal menambahkan personil.');
    }
    redirect("/modules/member/detail.php?id=$id");
}

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';

$q_personil = mysqli_query($conn, "SELECT * FROM member_detail WHERE member_id = $id ORDER BY status ASC, id ASC");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">Detail Personil Member</h4>
    <a href="/modules/member/index.php" class="btn btn-secondary shadow-sm"><i class="fas fa-arrow-left"></i> Kembali ke Daftar Member</a>
</div>

<?php display_flash_message(); ?>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm border-0 border-top border-primary border-4">
            <div class="card-body">
                <h5 class="fw-bold"><?= htmlspecialchars($member['nama']) ?></h5>
                <hr>
                <p class="mb-1 text-muted">Jenis Member</p>
                <p class="fw-bold"><?= $member['jenis_member'] ?></p>
                <p class="mb-1 text-muted">Tipe Pembayaran</p>
                <p class="fw-bold">
                    <?php if ($member['tipe_pembayaran'] == 'Kolektif'): ?>
                        <span class="badge bg-primary">Kolektif</span>
                    <?php else: ?>
                        <span class="badge bg-info text-dark">Individual</span>
                    <?php endif; ?>
                </p>
                <p class="mb-1 text-muted">Jatuh Tempo</p>
                <p class="fw-bold">Tanggal <?= $member['tanggal_jatuh_tempo'] ?></p>
                <p class="mb-1 text-muted">Catatan</p>
                <p class="fw-bold"><?= nl2br(htmlspecialchars($member['catatan'] ?? '-')) ?></p>
            </div>
        </div>
        
        <div class="card shadow-sm border-0 mt-4 border-top border-success border-4">
            <div class="card-header bg-white fw-bold">Tambah Personil/Kendaraan</div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="add_personil" value="1">
                    <div class="mb-3">
                        <label class="form-label">Nama Personil / Nama Panggilan</label>
                        <input type="text" name="nama_personil" class="form-control" required placeholder="Contoh: Bpk Budi / Mobil 1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kendaraan</label>
                        <input type="text" name="kendaraan" class="form-control" placeholder="Motor / Mobil Box / Truk">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">No. Polisi (Opsional)</label>
                        <input type="text" name="nopol" class="form-control" placeholder="B 1234 CD">
                    </div>
                    <?php if ($member['tipe_pembayaran'] == 'Individual'): ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-success">Nominal Iuran Bulanan</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" name="nominal_iuran" class="form-control" required min="0" step="any">
                        </div>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="nominal_iuran" value="0">
                    <?php endif; ?>
                    <button type="submit" class="btn btn-success w-100"><i class="fas fa-plus"></i> Tambah Personil</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold text-primary">
                Daftar Personil / Kendaraan (<?= mysqli_num_rows($q_personil) ?>)
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Personil</th>
                                <th>Kendaraan</th>
                                <th>No. Polisi</th>
                                <th>Biaya Iuran</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($q_personil) > 0): $no=1; while($row = mysqli_fetch_assoc($q_personil)): ?>
                            <tr class="<?= (isset($row['status']) && $row['status'] == 'Tidak Aktif') ? 'table-secondary text-muted' : '' ?>">
                                <td><?= $no++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($row['nama_personil']) ?></strong>
                                    <?php if(isset($row['status']) && $row['status'] == 'Tidak Aktif'): ?>
                                        <span class="badge bg-secondary ms-1">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['kendaraan'] ?? '-') ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['nopol'] ?? '-') ?></span></td>
                                <td>
                                    <?php if ($member['tipe_pembayaran'] == 'Individual'): ?>
                                        <div class="fw-bold text-success"><?= format_rupiah($row['nominal_iuran']) ?></div>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Ikut Pusat</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                        $nama_p = htmlspecialchars($row['nama_personil'], ENT_QUOTES);
                                        $confirm_msg = "Nonaktifkan {$nama_p}? Histori iurannya tetap tersimpan dan tidak akan hilang.";
                                    ?>
                                    <?php if(!isset($row['status']) || $row['status'] == 'Aktif'): ?>
                                        <a href="?id=<?= $id ?>&toggle_personil=<?= $row['id'] ?>&status_to=Tidak Aktif" 
                                           class="btn btn-sm btn-warning shadow-sm" title="Nonaktifkan Personil"
                                           onclick="return confirm('<?= $confirm_msg ?>')"
                                        ><i class="fas fa-user-slash"></i></a>
                                    <?php else: ?>
                                        <a href="?id=<?= $id ?>&toggle_personil=<?= $row['id'] ?>&status_to=Aktif" 
                                           class="btn btn-sm btn-success shadow-sm" title="Aktifkan Kembali"
                                           onclick="return confirm('Aktifkan kembali personil ini?')"
                                        ><i class="fas fa-user-check"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada personil atau detail kendaraan.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="alert alert-warning shadow-sm mt-3 border-0">
            <i class="fas fa-lightbulb text-warning me-2"></i> <strong>Catatan Sistem:</strong><br>
            Jika member ini bertipe pembayaran "Individual", masing-masing personil di atas akan ditagih secara terpisah. Jika bertipe "Kolektif", penagihan hanya dilakukan 1x untuk entitas perusahaan utama.
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
