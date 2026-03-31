<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Admin', 'HRD', 'Bendahara']);

$cabang_id = $_SESSION['user']['cabang_id'] ?? 1;
$is_super = ($_SESSION['user']['role'] === 'Super Admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_pedagang'])) {
    $nama = sanitize($conn, $_POST['nama']);
    $kategori = sanitize($conn, $_POST['kategori']);
    $nominal = (float)$_POST['nominal_iuran'];
    $jatuh_tempo = (int)$_POST['tanggal_jatuh_tempo'];
    
    $stmt = $conn->prepare("INSERT INTO pedagang (cabang_id, nama, kategori, nominal_iuran, tanggal_jatuh_tempo) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issdi", $cabang_id, $nama, $kategori, $nominal, $jatuh_tempo);
    if($stmt->execute()) set_flash_message('success', 'Data Pedagang ditambahkan.');
    else set_flash_message('error', 'Gagal menambahkan Data.');
    redirect('/pjr_parking/modules/pedagang/index.php');
}

// Handle Toggle Status
if (isset($_GET['toggle_status']) && isset($_GET['to'])) {
    $id = (int)$_GET['toggle_status'];
    $to = sanitize($conn, $_GET['to']);
    $up_q = $is_super ? "UPDATE pedagang SET status='$to' WHERE id=$id" : "UPDATE pedagang SET status='$to' WHERE id=$id AND cabang_id=$cabang_id";
    mysqli_query($conn, $up_q);
    set_flash_message('success', 'Status pedagang direkam menjadi ' . $to . '.');
    redirect('/pjr_parking/modules/pedagang/index.php');
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $del = $is_super ? "DELETE FROM pedagang WHERE id=$id" : "DELETE FROM pedagang WHERE id=$id AND cabang_id=$cabang_id";
    mysqli_query($conn, $del);
    set_flash_message('success', 'Pedagang dihapus permanen.');
    redirect('/pjr_parking/modules/pedagang/index.php');
}

$filter_kategori = isset($_GET['filter_kat']) ? sanitize($conn, $_GET['filter_kat']) : '';
$filter_status = isset($_GET['filter_status']) ? sanitize($conn, $_GET['filter_status']) : '';
$where_p = $is_super ? "1=1" : "cabang_id = $cabang_id";
if ($filter_kategori) $where_p .= " AND kategori = '$filter_kategori'";
if ($filter_status) $where_p .= " AND status = '$filter_status'";
$query = mysqli_query($conn, "SELECT * FROM pedagang WHERE $where_p ORDER BY kategori, id DESC");
require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">Manajemen Iuran Pedagang</h4>
</div>

<?php display_flash_message(); ?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body bg-light rounded">
        <form method="GET" class="d-flex w-100 align-items-end flex-wrap gap-3">
            <div style="width: 220px;">
                <label class="form-label fw-bold text-muted small">Kategori Pedagang</label>
                <select name="filter_kat" class="form-select">
                    <option value="">Semua Kategori</option>
                    <option value="Pedagang Bulanan" <?= $filter_kategori=='Pedagang Bulanan'?'selected':'' ?>>Pedagang Bulanan</option>
                    <option value="Pedagang Pagi" <?= $filter_kategori=='Pedagang Pagi'?'selected':'' ?>>Pedagang Pagi (Harian)</option>
                    <option value="Pedagang Malam" <?= $filter_kategori=='Pedagang Malam'?'selected':'' ?>>Pedagang Malam (Harian)</option>
                </select>
            </div>
            <div style="width: 180px;">
                <label class="form-label fw-bold text-muted small">Status</label>
                <select name="filter_status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="Aktif" <?= $filter_status=='Aktif'?'selected':'' ?>>Aktif</option>
                    <option value="Tidak Aktif" <?= $filter_status=='Tidak Aktif'?'selected':'' ?>>Tidak Aktif</option>
                </select>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning fw-bold px-4"><i class="fas fa-filter me-2"></i>Filter</button>
                <?php if($filter_kategori || $filter_status): ?>
                <a href="/pjr_parking/modules/pedagang/index.php" class="btn btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 border-top border-warning border-4">
            <div class="card-header bg-white fw-bold">Tambah Pedagang Baru</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="add_pedagang" value="1">
                    <div class="mb-3">
                        <label class="form-label">Nama Pedagang/Lapak</label>
                        <input type="text" name="nama" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kategori</label>
                        <select name="kategori" id="kategori_select" class="form-select" required onchange="toggleKategori()">
                            <option value="Pedagang Bulanan">Pedagang Bulanan</option>
                            <option value="Pedagang Pagi">Pedagang Pagi</option>
                            <option value="Pedagang Malam">Pedagang Malam</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" id="label_nominal">Nominal Iuran Bulanan</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" name="nominal_iuran" class="form-control" required min="0" step="1000">
                        </div>
                    </div>
                    <div class="mb-3" id="div_jatuh_tempo">
                        <label class="form-label">Tanggal Jatuh Tempo (1-31)</label>
                        <input type="number" name="tanggal_jatuh_tempo" id="input_jatuh_tempo" class="form-control" required min="1" max="31">
                    </div>
                    <button type="submit" class="btn btn-warning w-100 fw-bold">Simpan Pedagang</button>
                </form>

                <script>
                    function toggleKategori() {
                        const val = document.getElementById('kategori_select').value;
                        const lblNominal = document.getElementById('label_nominal');
                        const divJatuhTempo = document.getElementById('div_jatuh_tempo');
                        const inpJatuhTempo = document.getElementById('input_jatuh_tempo');
                        
                        if(val === 'Pedagang Bulanan') {
                            lblNominal.innerText = 'Nominal Iuran Bulanan';
                            divJatuhTempo.style.display = 'block';
                            inpJatuhTempo.required = true;
                        } else {
                            lblNominal.innerText = 'Nominal Iuran Harian';
                            divJatuhTempo.style.display = 'none';
                            inpJatuhTempo.required = false;
                            inpJatuhTempo.value = 1; // aman untuk DB
                        }
                    }
                    document.addEventListener('DOMContentLoaded', toggleKategori);
                </script>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold">Daftar Pedagang Lapak</div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 550px; overflow-y: auto;">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="bg-light sticky-top shadow-sm" style="z-index: 10;">
                            <tr>
                                <th>#</th>
                                <th>Nama Pedagang</th>
                                <th>Kategori</th>
                                <th>Iuran / Tarif</th>
                                <th>Jatuh Tempo</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($query) > 0): $no=1; while($row = mysqli_fetch_assoc($query)): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><strong><?= htmlspecialchars($row['nama']) ?></strong></td>
                                <td><span class="badge border bg-light text-dark"><?= $row['kategori'] ?></span></td>
                                <td>
                                    <?php 
                                        $is_harian = in_array($row['kategori'], ['Pedagang Pagi', 'Pedagang Malam']);
                                        echo '<span class="text-success fw-bold">' . format_rupiah($row['nominal_iuran']) . '</span>';
                                        echo $is_harian ? ' <small class="text-muted">/hari</small>' : ' <small class="text-muted">/bln</small>';
                                    ?>
                                </td>
                                <td>
                                    <?php if($is_harian): ?>
                                        <span class="badge bg-light text-secondary border">Bayar Harian</span>
                                    <?php else: ?>
                                        Tgl <?= $row['tanggal_jatuh_tempo'] ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if(isset($row['status']) && $row['status'] == 'Aktif'): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if(!isset($row['status']) || $row['status'] == 'Aktif'): ?>
                                        <a href="?toggle_status=<?= $row['id'] ?>&to=Tidak Aktif" class="btn btn-sm btn-warning shadow-sm" title="Nonaktifkan (Soft Delete)" onclick="return confirm('Nonaktifkan pedagang ini? Histori bayarnya akan tetap aman dan tidak terhapus.')"><i class="fas fa-store-slash"></i></a>
                                    <?php else: ?>
                                        <a href="?toggle_status=<?= $row['id'] ?>&to=Aktif" class="btn btn-sm btn-success shadow-sm" title="Aktifkan Kembali" onclick="return confirm('Aktifkan kembali pedagang ini?')"><i class="fas fa-check"></i></a>
                                    <?php endif; ?>
                                    
                                    <?php if($is_super): ?>
                                    <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger text-white shadow-sm" title="Hapus Permanen" onclick="return confirm('HAPUS PERMANEN? Semua histori bayar akan ikut lenyap!')"><i class="fas fa-trash"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="6" class="text-center text-muted">Tidak ada data Pedagang</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
