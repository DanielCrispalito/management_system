<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Admin', 'Bendahara', 'Super Admin']);

$cabang_id = $_SESSION['user']['cabang_id'] ?? 1;

// Handle Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_pengeluaran'])) {
    $tanggal = sanitize($conn, $_POST['tanggal']);
    $kategori_id = (int)$_POST['kategori_id'];
    $nominal = (float)$_POST['nominal'];
    $keterangan = sanitize($conn, $_POST['keterangan']);
    
    $stmt = $conn->prepare("INSERT INTO pengeluaran (cabang_id, tanggal, kategori_id, nominal, keterangan) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isids", $cabang_id, $tanggal, $kategori_id, $nominal, $keterangan);
    if($stmt->execute()) set_flash_message('success', 'Data pengeluaran berhasil disimpan.');
    else set_flash_message('error', 'Gagal menyimpan pengeluaran!');
    
    redirect('/pjr_parking/modules/pengeluaran/index.php');
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Check if this pengeluaran is linked to a penggajian record
    $q_gaji = mysqli_query($conn, "SELECT id, history_potongan FROM penggajian WHERE pengeluaran_id = $id");
    $is_payroll = false;
    $msg_extra = '';
    if(mysqli_num_rows($q_gaji) > 0) {
        $is_payroll = true;
        $gaji = mysqli_fetch_assoc($q_gaji);
        
        if(!empty($gaji['history_potongan'])) {
            $history = json_decode($gaji['history_potongan'], true);
            if(isset($history['kasbon_ids']) && count($history['kasbon_ids']) > 0) {
                $ids = implode(',', $history['kasbon_ids']);
                mysqli_query($conn, "UPDATE kasbon SET status_lunas = 'Belum Lunas' WHERE id IN ($ids)");
            }
            if(isset($history['pinjaman_history']) && count($history['pinjaman_history']) > 0) {
                foreach($history['pinjaman_history'] as $pj) {
                    $pj_id = (int)$pj['id'];
                    $deducted = (float)$pj['deducted'];
                    mysqli_query($conn, "UPDATE pinjaman SET sisa_pinjaman = sisa_pinjaman + $deducted, status_lunas = 'Belum Lunas' WHERE id = $pj_id");
                }
            }
            $msg_extra = " Status lunas Kasbon dan Pinjaman telah dikembalikan.";
        }
        
        mysqli_query($conn, "DELETE FROM penggajian WHERE pengeluaran_id = $id");
    }

    // Super admin can delete any, others only their branch
    if ($_SESSION['user']['role'] === 'Super Admin') {
        mysqli_query($conn, "DELETE FROM pengeluaran WHERE id=$id");
    } else {
        mysqli_query($conn, "DELETE FROM pengeluaran WHERE id=$id AND cabang_id=$cabang_id");
    }
    set_flash_message('success', 'Data pengeluaran dihapus.' . $msg_extra);
    redirect('/pjr_parking/modules/pengeluaran/index.php');
}

// Fetch Data
$filter_kategori = isset($_GET['filter_kategori']) ? (int)$_GET['filter_kategori'] : '';
$where_kat = $filter_kategori ? " AND p.kategori_id = $filter_kategori" : "";
$where_cabang = ($_SESSION['user']['role'] === 'Super Admin') ? "1=1" : "p.cabang_id = $cabang_id";

$query = mysqli_query($conn, "
    SELECT p.*, k.nama_kategori, c.nama_cabang
    FROM pengeluaran p 
    JOIN kategori_pengeluaran k ON p.kategori_id = k.id 
    JOIN cabang c ON p.cabang_id = c.id
    WHERE $where_cabang $where_kat
    ORDER BY p.tanggal DESC, p.id DESC
");

$q_kat = mysqli_query($conn, "SELECT * FROM kategori_pengeluaran ORDER BY nama_kategori ASC");

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">Data Pengeluaran Operasional</h4>
    <a href="/pjr_parking/modules/pengeluaran/kategori.php" class="btn btn-outline-danger"><i class="fas fa-cog"></i> Kelola Kategori Pengeluaran</a>
</div>

<?php display_flash_message(); ?>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 border-top border-danger border-4">
            <div class="card-header bg-white fw-bold">Input Pengeluaran Baru</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="add_pengeluaran" value="1">
                    <div class="mb-3">
                        <label class="form-label">Tanggal Pengeluaran <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kategori Pengeluaran <span class="text-danger">*</span></label>
                        <select name="kategori_id" class="form-select" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php while($kat = mysqli_fetch_assoc($q_kat)): ?>
                                <option value="<?= $kat['id'] ?>"><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nominal Pengeluaran <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" name="nominal" class="form-control" required min="1" step="any">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Keterangan / Deskripsi Lengkap <span class="text-danger">*</span></label>
                        <textarea name="keterangan" class="form-control" rows="3" required placeholder="Sebutkan detail barang/jasa..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-danger w-100 fw-bold"><i class="fas fa-save me-1"></i> Simpan Pengeluaran</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center py-3">
                <span class="m-0">Histori Pengeluaran</span>
                <form method="GET" class="d-inline-flex m-0 align-items-center gap-2">
                    <label class="form-label mb-0 small text-muted text-nowrap d-none d-md-inline">Filter:</label>
                    <select name="filter_kategori" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 150px;">
                        <option value="">Semua Kategori</option>
                        <?php 
                        $q_kat_filter = mysqli_query($conn, "SELECT * FROM kategori_pengeluaran ORDER BY nama_kategori ASC");
                        while($kat_f = mysqli_fetch_assoc($q_kat_filter)): 
                        ?>
                            <option value="<?= $kat_f['id'] ?>" <?= ($filter_kategori == $kat_f['id']) ? 'selected' : '' ?>><?= htmlspecialchars($kat_f['nama_kategori']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 550px; overflow-y: auto;">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="bg-light sticky-top shadow-sm" style="z-index: 10;">
                            <tr>
                                <th class="ps-4">Tanggal</th>
                                <?php if($_SESSION['user']['role'] === 'Super Admin') echo '<th>Cabang</th>'; ?>
                                <th>Kategori</th>
                                <th>Deskripsi</th>
                                <th class="text-end">Nominal Keluar</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($query) > 0): while($row = mysqli_fetch_assoc($query)): ?>
                            <tr>
                                <td class="ps-4 text-muted"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                                <?php if($_SESSION['user']['role'] === 'Super Admin'): ?>
                                    <td><span class="badge border text-dark bg-light"><?= htmlspecialchars($row['nama_cabang']) ?></span></td>
                                <?php endif; ?>
                                <td><span class="badge bg-danger"><?= htmlspecialchars($row['nama_kategori']) ?></span></td>
                                <td class="small"><?= htmlspecialchars($row['keterangan']) ?></td>
                                <td class="text-end fw-bold text-danger"><?= format_rupiah($row['nominal']) ?></td>
                                <td class="text-center">
                                    <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-outline-secondary shadow-sm border-0" onclick="return confirm('Hapus pengeluaran ini?')"><i class="fas fa-trash text-danger"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="<?= $_SESSION['user']['role'] === 'Super Admin' ? '6' : '5' ?>" class="text-center text-muted py-4">Belum ada data pengeluaran.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
