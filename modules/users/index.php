<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Super Admin']);

// Handle Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $nama = sanitize($conn, $_POST['nama']);
    $username = sanitize($conn, $_POST['username']);
    $password = md5($_POST['password']);
    $role = sanitize($conn, $_POST['role']);
    $cabang_id = ($role === 'Super Admin') ? 'NULL' : (int)$_POST['cabang_id'];
    $akses_cabang = isset($_POST['akses_cabang']) ? sanitize($conn, implode(',', $_POST['akses_cabang'])) : '';
    
    // Check available username
    $cek = mysqli_query($conn, "SELECT id FROM users WHERE username='$username'");
    if(mysqli_num_rows($cek) > 0) {
        set_flash_message('error', 'Username sudah terdaftar! Gunakan username lain.');
    } else {
        $akses_sql = $akses_cabang ? "'$akses_cabang'" : "NULL";
        $q = "INSERT INTO users (cabang_id, username, password, nama, role, akses_cabang) VALUES ($cabang_id, '$username', '$password', '$nama', '$role', $akses_sql)";
        if(mysqli_query($conn, $q)) set_flash_message('success', 'Akun pengguna berhasil dibuat.');
        else set_flash_message('error', 'Gagal membuat akun: ' . mysqli_error($conn));
    }
    redirect('/modules/users/index.php');
}

// Handle Delete User
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    if($id === $_SESSION['user']['id']) {
        set_flash_message('error', 'Anda tidak dapat menghapus akun Anda sendiri saat sedang login.');
    } else {
        $delete = mysqli_query($conn, "DELETE FROM users WHERE id=$id");
        if($delete) set_flash_message('success', 'Akun otomatis dihapus.');
        else set_flash_message('error', 'Gagal menghapus akun.');
    }
    redirect('/modules/users/index.php');
}

$query = mysqli_query($conn, "
    SELECT u.*, c.nama_cabang 
    FROM users u 
    LEFT JOIN cabang c ON u.cabang_id = c.id 
    ORDER BY u.role, u.id ASC
");
$q_cabang = mysqli_query($conn, "SELECT id, nama_cabang FROM cabang ORDER BY nama_cabang ASC");

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">Manajemen Akses Petugas (Users)</h4>
</div>

<?php display_flash_message(); ?>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 border-top border-warning border-4">
            <div class="card-header bg-white fw-bold">Tambah Akun Baru</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="add_user" value="1">
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" name="nama" class="form-control" required placeholder="Nama Petugas">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username (Login)</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role / Hak Akses</label>
                        <select name="role" id="roleSelect" class="form-select" required>
                            <option value="Admin">Admin (Operasional Cabang)</option>
                            <option value="Bendahara">Bendahara (Keuangan)</option>
                            <option value="HRD">HRD (Kepegawaian)</option>
                            <option value="Super Admin">Super Admin (Akses Global Lintas Cabang)</option>
                        </select>
                    </div>
                    <div class="mb-4" id="cabangWrapper">
                        <label class="form-label">Ditugaskan di Cabang (Utama)</label>
                        <select name="cabang_id" class="form-select" required>
                            <option value="">-- Pilih Cabang --</option>
                            <?php 
                            mysqli_data_seek($q_cabang, 0);
                            while($cab = mysqli_fetch_assoc($q_cabang)): 
                            ?>
                                <option value="<?= $cab['id'] ?>"><?= htmlspecialchars($cab['nama_cabang']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <div class="form-text mt-1 text-muted"><i class="fas fa-info-circle"></i> User ini mengatur cabang utama di atas.</div>
                        
                        <label class="form-label mt-3">Akses Cabang Tambahan (Opsional)</label>
                        <div class="row px-2">
                            <?php 
                            mysqli_data_seek($q_cabang, 0);
                            while($cab = mysqli_fetch_assoc($q_cabang)): 
                            ?>
                            <div class="col-6 mb-2">
                                <div class="form-check">
                                  <input class="form-check-input" type="checkbox" name="akses_cabang[]" value="<?= $cab['id'] ?>" id="akses<?= $cab['id'] ?>">
                                  <label class="form-check-label" for="akses<?= $cab['id'] ?>">
                                    <?= htmlspecialchars($cab['nama_cabang']) ?>
                                  </label>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                        <div class="form-text mt-1 text-muted"><i class="fas fa-info-circle"></i> Centang cabang tambahan untuk mode Switcher.</div>
                    </div>
                    <button type="submit" class="btn btn-warning w-100 fw-bold"><i class="fas fa-user-plus me-1"></i> Buat Akun</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold">Daftar Akun Sistem</div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 550px; overflow-y: auto;">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="bg-light sticky-top shadow-sm" style="z-index: 10;">
                            <tr>
                                <th class="ps-4">Nama Lengkap</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Cabang Integrasi</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($query) > 0): while($row = mysqli_fetch_assoc($query)): ?>
                            <tr>
                                <td class="ps-4 fw-bold"><?= htmlspecialchars($row['nama']) ?></td>
                                <td><code><?= htmlspecialchars($row['username']) ?></code></td>
                                <td>
                                    <?php 
                                        if($row['role'] == 'Super Admin') echo '<span class="badge bg-danger rounded-pill"><i class="fas fa-crown me-1"></i>Super Admin</span>';
                                        elseif($row['role'] == 'Admin') echo '<span class="badge bg-primary">Admin</span>';
                                        elseif($row['role'] == 'Bendahara') echo '<span class="badge bg-success">Bendahara</span>';
                                        else echo '<span class="badge bg-info text-dark">HRD</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php if($row['cabang_id']): ?>
                                    <span class="badge text-bg-light border text-dark"><i class="fas fa-map-marker-alt text-danger me-1"></i> <?= htmlspecialchars($row['nama_cabang']) ?></span>
                                    <?php else: ?>
                                    <span class="text-muted fst-italic">Akses Lintas Cabang</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if($row['id'] !== $_SESSION['user']['id']): ?>
                                    <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger shadow-sm border-0" onclick="return confirm('Hapus permanen akun <?= $row['username'] ?>?')"><i class="fas fa-trash"></i></a>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Aktif (Anda)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada user terdaftar.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('roleSelect').addEventListener('change', function() {
    const wrap = document.getElementById('cabangWrapper');
    const input = wrap.querySelector('select');
    if(this.value === 'Super Admin') {
        wrap.style.display = 'none';
        input.removeAttribute('required');
    } else {
        wrap.style.display = 'block';
        input.setAttribute('required', 'required');
    }
});
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
