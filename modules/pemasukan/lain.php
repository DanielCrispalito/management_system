<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Admin', 'Bendahara']);

$cabang_id = $_SESSION['user']['cabang_id'] ?? 1;
$is_super = ($_SESSION['user']['role'] === 'Super Admin');

// Handle Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_lain'])) {
    $tanggal = sanitize($conn, $_POST['tanggal']);
    $subkategori_id = (int)$_POST['subkategori_id'];
    $nominal = (float)$_POST['nominal'];
    $keterangan = sanitize($conn, $_POST['keterangan']);
    
    $stmt = $conn->prepare("INSERT INTO pendapatan_lain (cabang_id, tanggal, subkategori_id, nominal, keterangan) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isids", $cabang_id, $tanggal, $subkategori_id, $nominal, $keterangan);
    if($stmt->execute()) set_flash_message('success', 'Data Pendapatan Lainnnya disimpan.');
    else set_flash_message('error', 'Gagal menyimpan!');
    redirect('/modules/pemasukan/lain.php');
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $del = $is_super ? "DELETE FROM pendapatan_lain WHERE id=$id" : "DELETE FROM pendapatan_lain WHERE id=$id AND cabang_id=$cabang_id";
    mysqli_query($conn, $del);
    set_flash_message('success', 'Data dihapus.');
    redirect('/modules/pemasukan/lain.php');
}

$filter_kategori = isset($_GET['filter_kategori']) ? (int)$_GET['filter_kategori'] : '';
$where_kat = $filter_kategori ? " AND s.id = $filter_kategori" : "";

$where_l = $is_super ? "1=1" : "p.cabang_id = $cabang_id";
$query = mysqli_query($conn, "
    SELECT p.*, s.nama_subkategori, k.nama_kategori 
    FROM pendapatan_lain p 
    JOIN subkategori s ON p.subkategori_id = s.id 
    JOIN kategori k ON s.kategori_id = k.id 
    WHERE $where_l $where_kat
    ORDER BY p.tanggal DESC, p.id DESC
");

$q_sub = mysqli_query($conn, "
    SELECT s.id, s.nama_subkategori, k.nama_kategori 
    FROM subkategori s 
    JOIN kategori k ON s.kategori_id = k.id 
    ORDER BY k.nama_kategori, s.nama_subkategori
");

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">Pendapatan Lain-Lain</h4>
    <a href="/modules/pemasukan/kategori.php" class="btn btn-outline-primary"><i class="fas fa-cog"></i> Kelola Kategori Pendapatan</a>
</div>

<?php display_flash_message(); ?>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 border-top border-info border-4">
            <div class="card-header bg-white fw-bold">Input Pendapatan Lain</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="add_lain" value="1">
                    <div class="mb-3">
                        <label class="form-label">Tanggal</label>
                        <input type="date" name="tanggal" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kategori</label>
                        <select name="subkategori_id" class="form-select" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php 
                            $current_kat = '';
                            while($sub = mysqli_fetch_assoc($q_sub)): 
                                if($current_kat != $sub['nama_kategori']) {
                                    if($current_kat != '') echo "</optgroup>";
                                    echo "<optgroup label='{$sub['nama_kategori']}'>";
                                    $current_kat = $sub['nama_kategori'];
                                }
                                echo "<option value='{$sub['id']}'>{$sub['nama_subkategori']}</option>";
                            endwhile;
                            if($current_kat != '') echo "</optgroup>";
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nominal</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" name="nominal" class="form-control" required min="0" step="any">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Keterangan</label>
                        <textarea name="keterangan" class="form-control" rows="2" placeholder="Detail transaksi..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-info text-white w-100 fw-bold"><i class="fas fa-save me-1"></i> Simpan Pemasukan</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center py-3">
                <span class="m-0">Histori Pendapatan Lain-Lain</span>
                <form method="GET" class="d-inline-flex m-0 align-items-center gap-2">
                    <label class="form-label mb-0 small text-muted text-nowrap d-none d-md-inline">Filter:</label>
                    <select name="filter_kategori" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 150px;">
                        <option value="">Semua Kategori</option>
                        <?php 
                        $q_kat_filter = mysqli_query($conn, "
                            SELECT s.id, s.nama_subkategori, k.nama_kategori 
                            FROM subkategori s 
                            JOIN kategori k ON s.kategori_id = k.id 
                            ORDER BY k.nama_kategori, s.nama_subkategori
                        ");
                        $curr_k = '';
                        while($kf = mysqli_fetch_assoc($q_kat_filter)): 
                            if($curr_k != $kf['nama_kategori']) {
                                if($curr_k != '') echo "</optgroup>";
                                echo "<optgroup label='{$kf['nama_kategori']}'>";
                                $curr_k = $kf['nama_kategori'];
                            }
                            $sel = ($filter_kategori == $kf['id']) ? 'selected' : '';
                            echo "<option value='{$kf['id']}' $sel>{$kf['nama_subkategori']}</option>";
                        endwhile; 
                        if($curr_k != '') echo "</optgroup>";
                        ?>
                    </select>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 550px; overflow-y: auto;">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="bg-light sticky-top shadow-sm" style="z-index: 10;">
                            <tr>
                                <th class="ps-4">Tanggal</th>
                                <th>Kategori</th>
                                <th>Subkategori</th>
                                <th>Deskripsi</th>
                                <th class="text-end">Nominal</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($query) > 0): while($row = mysqli_fetch_assoc($query)): ?>
                            <tr>
                                <td class="ps-4 text-muted"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($row['nama_kategori']) ?></span></td>
                                <td><?= htmlspecialchars($row['nama_subkategori']) ?></td>
                                <td class="small"><?= htmlspecialchars($row['keterangan']) ?></td>
                                <td class="text-end fw-bold text-success"><?= format_rupiah($row['nominal']) ?></td>
                                <td class="text-center">
                                    <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger shadow-sm border-0" onclick="return confirm('Hapus record ini?')"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada histori pemasukan lain.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
