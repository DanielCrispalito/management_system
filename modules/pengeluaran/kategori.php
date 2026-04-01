<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Admin', 'Bendahara', 'Super Admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_kategori'])) {
    $nama = sanitize($conn, $_POST['nama_kategori']);
    $stmt = $conn->prepare("INSERT INTO kategori_pengeluaran (nama_kategori) VALUES (?)");
    $stmt->bind_param("s", $nama);
    if ($stmt->execute()) {
        set_flash_message('success', 'Kategori pengeluaran berhasil ditambahkan.');
    } else {
        set_flash_message('error', 'Gagal menambahkan kategori.');
    }
    redirect('/modules/pengeluaran/kategori.php');
}

// Handle Delete Kategori
if (isset($_GET['del'])) {
    $del = (int)$_GET['del'];
    mysqli_query($conn, "DELETE FROM kategori_pengeluaran WHERE id = $del");
    set_flash_message('success', 'Kategori pengeluaran dan seluruh riwayatnya berhasil dihapus!');
    redirect('/modules/pengeluaran/kategori.php');
}

$q_kategori = mysqli_query($conn, "SELECT * FROM kategori_pengeluaran ORDER BY nama_kategori ASC");

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">Kategori Pengeluaran</h4>
    <a href="/modules/pengeluaran/index.php" class="btn btn-secondary shadow-sm">
        <i class="fas fa-arrow-left me-1"></i> Kembali ke Pengeluaran
    </a>
</div>

<?php display_flash_message(); ?>

<div class="row">
    <div class="col-md-5 mb-4">
        <div class="card shadow-sm border-0 border-top border-danger border-4 h-100">
            <div class="card-header bg-white fw-bold">Tambah Kategori</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="add_kategori" value="1">
                    <div class="mb-3">
                        <label class="form-label">Nama Kategori</label>
                        <input type="text" name="nama_kategori" class="form-control" required placeholder="Contoh: Gaji, Listrik, Bensin">
                    </div>
                    <button type="submit" class="btn btn-danger w-100 fw-bold"><i class="fas fa-plus me-1"></i> Tambah Kategori</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-7 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-bold">Daftar Kategori Pengeluaran</div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if(mysqli_num_rows($q_kategori) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($q_kategori)): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?= htmlspecialchars($row['nama_kategori']) ?>
                                <a href="?del=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="return confirm('Yakin hapus kategori beserta riwayatnya?')"><i class="fas fa-trash"></i></a>
                            </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li class="list-group-item text-center text-muted py-4">Belum ada kategori pengeluaran.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
