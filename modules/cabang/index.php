<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

// Hanya Super Admin yang boleh akses
check_role(['Super Admin']);

// Handle Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_cabang'])) {
    $nama_cabang = sanitize($conn, $_POST['nama_cabang']);
    $alamat = sanitize($conn, $_POST['alamat']);
    
    $stmt = $conn->prepare("INSERT INTO cabang (nama_cabang, alamat) VALUES (?, ?)");
    $stmt->bind_param("ss", $nama_cabang, $alamat);
    if($stmt->execute()) set_flash_message('success', 'Cabang baru berhasil ditambahkan.');
    else set_flash_message('error', 'Gagal menambahkan data cabang!');
    redirect('/modules/cabang/index.php');
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    if($id === 1) {
        set_flash_message('error', 'Cabang Pusat (Default) tidak dapat dihapus.');
    } else {
        // Cek apakah ada data yang terkait dengan cabang ini
        $cek = mysqli_query($conn, "SELECT id FROM users WHERE cabang_id = $id LIMIT 1");
        if(mysqli_num_rows($cek) > 0) {
            set_flash_message('error', 'Gagal mengapus. Ada User yang terhubung dengan cabang ini. Hapus/pindahkan user tersebut terlebih dahulu.');
        } else {
            $delete = mysqli_query($conn, "DELETE FROM cabang WHERE id=$id");
            if($delete) set_flash_message('success', 'Data cabang berhasil dihapus.');
            else set_flash_message('error', 'Gagal menghapus cabang (Masih ada sisa data historis yg bergantung pada cabang ini).');
        }
    }
    
    redirect('/modules/cabang/index.php');
}

$query = mysqli_query($conn, "
    SELECT c.*, 
    (SELECT COUNT(id) FROM users u WHERE u.cabang_id = c.id) as total_admin 
    FROM cabang c 
    ORDER BY c.id ASC
");

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">Manajemen Cabang Franchise</h4>
</div>

<?php display_flash_message(); ?>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 border-top border-dark border-4">
            <div class="card-header bg-white fw-bold">Tambah Cabang Baru</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="add_cabang" value="1">
                    <div class="mb-3">
                        <label class="form-label">Nama Cabang / Lokasi</label>
                        <input type="text" name="nama_cabang" class="form-control" required placeholder="Contoh: Cabang Bekasi Timur">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Alamat Lengkap</label>
                        <textarea name="alamat" class="form-control" rows="3" placeholder="Alamat jalan lengkap..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-dark w-100 fw-bold"><i class="fas fa-save me-1"></i> Simpan Cabang</button>
                    <div class="form-text mt-3 text-muted small"><i class="fas fa-info-circle"></i> Catatan: Setelah cabang ditambah, Anda bisa membuat Akun User/Admin baru yang ditugaskan khusus untuk cabang ini di menu <b>Manajemen Users</b>.</div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold">Daftar Jaringan Cabang</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">No</th>
                                <th>Nama Cabang</th>
                                <th>Alamat</th>
                                <th>Total Akun Petugas</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($query) > 0): $no=1; while($row = mysqli_fetch_assoc($query)): ?>
                            <tr>
                                <td class="ps-4 text-muted"><?= $no++ ?></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($row['nama_cabang']) ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($row['alamat']) ?></td>
                                <td>
                                    <?php if($row['total_admin'] > 0): ?>
                                    <span class="badge bg-primary rounded-pill"><?= $row['total_admin'] ?> Akun Terdaftar</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary rounded-pill">0 Akun</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if($row['id'] !== '1'): ?>
                                    <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger shadow-sm border-0" onclick="return confirm('Hapus cabang ini? Penghapusan akan ditolak jika masih ada user/data yg bergantung dari cabang ini.')"><i class="fas fa-trash"></i></a>
                                    <?php else: ?>
                                    <span class="badge text-bg-light border text-muted">Akses Terkunci</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada cabang terdaftar.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
