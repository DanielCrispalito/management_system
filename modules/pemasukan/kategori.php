<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Admin', 'Bendahara']);

// Handle Add Kategori
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_kategori'])) {
    $nama = sanitize($conn, $_POST['nama_kategori']);
    $stmt = $conn->prepare("INSERT INTO kategori (nama_kategori) VALUES (?)");
    $stmt->bind_param("s", $nama);
    $stmt->execute();
    set_flash_message('success', 'Kategori ditambahkan.');
    redirect('/modules/pemasukan/kategori.php');
}

// Handle Add Subkategori
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subkategori'])) {
    $id_kat = (int)$_POST['kategori_id'];
    $nama_sub = sanitize($conn, $_POST['nama_subkategori']);
    $stmt = $conn->prepare("INSERT INTO subkategori (kategori_id, nama_subkategori) VALUES (?, ?)");
    $stmt->bind_param("is", $id_kat, $nama_sub);
    $stmt->execute();
    set_flash_message('success', 'Subkategori ditambahkan.');
    redirect('/modules/pemasukan/kategori.php');
}

// Handle Delete Kategori
if (isset($_GET['del'])) {
    $del = (int)$_GET['del'];
    mysqli_query($conn, "DELETE FROM kategori WHERE id = $del");
    set_flash_message('success', 'Kategori dan riwayat transaksinya berhasil dihapus!');
    redirect('/modules/pemasukan/kategori.php');
}

// Handle Delete Subkategori
if (isset($_GET['del_sub'])) {
    $del = (int)$_GET['del_sub'];
    mysqli_query($conn, "DELETE FROM subkategori WHERE id = $del");
    set_flash_message('success', 'Subkategori dan riwayat transaksinya berhasil dihapus!');
    redirect('/modules/pemasukan/kategori.php');
}

$q_kategori = mysqli_query($conn, "SELECT * FROM kategori ORDER BY id DESC");
require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">Master Data Kategori Pemasukan Lainnya</h4>
</div>

<?php display_flash_message(); ?>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-bold">Kategori Utama</div>
            <div class="card-body">
                <form method="POST" class="d-flex mb-3">
                    <input type="hidden" name="add_kategori" value="1">
                    <input type="text" name="nama_kategori" class="form-control me-2" required placeholder="Nama Kategori Baru">
                    <button type="submit" class="btn btn-primary">Tambah</button>
                </form>
                
                <ul class="list-group list-group-flush border">
                    <?php 
                    $kategoris = [];
                    if(mysqli_num_rows($q_kategori) > 0) {
                        while($row = mysqli_fetch_assoc($q_kategori)) {
                            $kategoris[] = $row;
                            $del_btn = $row['nama_kategori'] !== 'Iuran' ? "<a href='?del={$row['id']}' class='btn btn-sm btn-outline-danger py-0 px-2' onclick=\"return confirm('Yakin hapus kategori ini beserta riwayat transaksinya?')\"><i class='fas fa-trash'></i></a>" : "<span class='badge bg-secondary'><i class='fas fa-lock'></i></span>";
                            echo "<li class='list-group-item d-flex justify-content-between align-items-center'>
                                    " . htmlspecialchars($row['nama_kategori']) . " 
                                    $del_btn
                                  </li>";
                        }
                    } else {
                        echo "<li class='list-group-item text-center text-muted'>Belum ada kategori</li>";
                    }
                    ?>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-bold">Subkategori</div>
            <div class="card-body">
                <form method="POST" class="mb-4">
                    <input type="hidden" name="add_subkategori" value="1">
                    <div class="mb-2">
                        <select name="kategori_id" class="form-select" required>
                            <option value="">-- Pilih Kategori Utama --</option>
                            <?php foreach($kategoris as $k): ?>
                                <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="d-flex">
                        <input type="text" name="nama_subkategori" class="form-control me-2" required placeholder="Nama Subkategori Baru">
                        <button type="submit" class="btn btn-success">Tambah</button>
                    </div>
                </form>
                
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="bg-light">
                            <tr><th>Kategori Utama</th><th>Subkategori</th></tr>
                        </thead>
                        <tbody>
                            <?php
                            $q_sub = mysqli_query($conn, "SELECT s.*, k.nama_kategori FROM subkategori s JOIN kategori k ON s.kategori_id = k.id ORDER BY k.nama_kategori, s.nama_subkategori");
                            if(mysqli_num_rows($q_sub) > 0) {
                                while($sub = mysqli_fetch_assoc($q_sub)) {
                                    $del_btn = $sub['nama_kategori'] !== 'Iuran' ? "<a href='?del_sub={$sub['id']}' class='text-danger' onclick=\"return confirm('Yakin hapus subkategori?')\"><i class='fas fa-trash'></i></a>" : "";
                                    echo "<tr>
                                        <td><span class='badge bg-secondary'>".htmlspecialchars($sub['nama_kategori'])."</span></td>
                                        <td class='d-flex justify-content-between align-items-center'>".htmlspecialchars($sub['nama_subkategori'])." $del_btn</td>
                                    </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='2' class='text-center'>Belum ada subkategori</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
