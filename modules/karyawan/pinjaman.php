<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Admin', 'Super Admin', 'HRD']);
$cabang_id = $_SESSION['user']['cabang_id'] ?? 1;
$is_super = ($_SESSION['user']['role'] === 'Super Admin');

// Helper to ensure 'Kasbon/Pinjaman Karyawan' exists in kategori_pengeluaran
function get_kategori_pinjaman_id($conn) {
    $q = mysqli_query($conn, "SELECT id FROM kategori_pengeluaran WHERE nama_kategori = 'Kasbon/Pinjaman Karyawan'");
    if(mysqli_num_rows($q) > 0) return mysqli_fetch_assoc($q)['id'];
    mysqli_query($conn, "INSERT INTO kategori_pengeluaran (nama_kategori) VALUES ('Kasbon/Pinjaman Karyawan')");
    return mysqli_insert_id($conn);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tipe_form'])) {
        $k_id = (int)$_POST['karyawan_id'];
        $tanggal = sanitize($conn, $_POST['tanggal']);
        $keterangan = sanitize($conn, $_POST['keterangan']);
        $kat_pengeluaran_id = get_kategori_pinjaman_id($conn);
        
        $c_id = $cabang_id;
        $q_kar = mysqli_query($conn, "SELECT nama, cabang_id FROM karyawan WHERE id = $k_id");
        if(mysqli_num_rows($q_kar) > 0) {
            $kar_row = mysqli_fetch_assoc($q_kar);
            $nama_kar = $kar_row['nama'];
            $c_id = $kar_row['cabang_id']; // sync with employee branch
        } else {
            $nama_kar = 'Karyawan';
        }

        if ($_POST['tipe_form'] === 'kasbon') {
            $nominal = (float)$_POST['nominal'];
            $ket_pengeluaran = "Pencairan Kasbon Karyawan an. $nama_kar - $keterangan";
            
            mysqli_query($conn, "INSERT INTO pengeluaran (tanggal, kategori_id, nominal, keterangan, cabang_id) VALUES ('$tanggal', $kat_pengeluaran_id, $nominal, '$ket_pengeluaran', $c_id)");
            $pengeluaran_id = mysqli_insert_id($conn);
            
            $stmt = $conn->prepare("INSERT INTO kasbon (karyawan_id, tanggal, nominal, keterangan, pengeluaran_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isdsi", $k_id, $tanggal, $nominal, $keterangan, $pengeluaran_id);
            $stmt->execute();
            set_flash_message('success', 'Kasbon berhasil dicatat dan sinkron ke kas pengeluaran.');
            
        } elseif ($_POST['tipe_form'] === 'pinjaman') {
            $nominal = (float)$_POST['nominal_total'];
            $tenor = (int)$_POST['tenor_bulan'];
            $cicilan = (float)$_POST['cicilan_per_bulan'];
            $ket_pengeluaran = "Pencairan Pinjaman Karyawan an. $nama_kar - $keterangan";
            
            mysqli_query($conn, "INSERT INTO pengeluaran (tanggal, kategori_id, nominal, keterangan, cabang_id) VALUES ('$tanggal', $kat_pengeluaran_id, $nominal, '$ket_pengeluaran', $c_id)");
            $pengeluaran_id = mysqli_insert_id($conn);
            
            $stmt = $conn->prepare("INSERT INTO pinjaman (karyawan_id, tanggal, nominal_total, tenor_bulan, cicilan_per_bulan, sisa_pinjaman, keterangan, pengeluaran_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isdiddsi", $k_id, $tanggal, $nominal, $tenor, $cicilan, $nominal, $keterangan, $pengeluaran_id);
            $stmt->execute();
            set_flash_message('success', 'Pinjaman berhasil dicatat dan sinkron ke kas pengeluaran.');
        }

        // Delete handlers
    } elseif (isset($_POST['delete_type'])) {
        $del_id = (int)$_POST['delete_id'];
        $type = $_POST['delete_type'];
        
        // Find pengeluaran_id first to delete cascadingly from operational expenses
        if($type === 'kasbon') {
            $q = mysqli_query($conn, "SELECT pengeluaran_id FROM kasbon WHERE id = $del_id");
            if(mysqli_num_rows($q)>0) {
                $pid = mysqli_fetch_assoc($q)['pengeluaran_id'];
                if($pid) mysqli_query($conn, "DELETE FROM pengeluaran WHERE id = $pid");
            }
            mysqli_query($conn, "DELETE FROM kasbon WHERE id = $del_id");
            set_flash_message('success', 'Kasbon dibatalkan dan pengeluaran terkait telah dihapus.');
        } else {
            $q = mysqli_query($conn, "SELECT pengeluaran_id FROM pinjaman WHERE id = $del_id");
            if(mysqli_num_rows($q)>0) {
                $pid = mysqli_fetch_assoc($q)['pengeluaran_id'];
                if($pid) mysqli_query($conn, "DELETE FROM pengeluaran WHERE id = $pid");
            }
            mysqli_query($conn, "DELETE FROM pinjaman WHERE id = $del_id");
            set_flash_message('success', 'Pinjaman dibatalkan dan pengeluaran terkait telah dihapus.');
        }
    }
    redirect('/pjr_parking/modules/karyawan/pinjaman.php');
}

$wc_karyawan = $is_super ? "1=1" : "k.cabang_id = $cabang_id";
$q_emps = mysqli_query($conn, "SELECT id, nik, nama, cabang_id FROM karyawan k WHERE status='Aktif' AND $wc_karyawan ORDER BY nama ASC");
$karyawan_list = [];
while($emp = mysqli_fetch_assoc($q_emps)) $karyawan_list[] = $emp;

// Filters for Kasbon
$f_kar = isset($_GET['f_kar']) ? (int)$_GET['f_kar'] : 0;
$f_bln = isset($_GET['f_bln']) ? (int)$_GET['f_bln'] : 0;

$wc_filter = "";
if($f_kar > 0) $wc_filter .= " AND kb.karyawan_id = $f_kar ";
if($f_bln > 0) $wc_filter .= " AND MONTH(kb.tanggal) = $f_bln ";

$q_kasbon = mysqli_query($conn, "SELECT kb.*, k.nama, k.nik FROM kasbon kb JOIN karyawan k ON kb.karyawan_id = k.id WHERE $wc_karyawan $wc_filter ORDER BY kb.id DESC");
$q_pinjaman = mysqli_query($conn, "SELECT p.*, k.nama, k.nik FROM pinjaman p JOIN karyawan k ON p.karyawan_id = k.id WHERE $wc_karyawan ORDER BY p.id DESC");

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">Manajemen Kasbon & Pinjaman Cabang</h4>
    <div class="d-flex gap-2">
        <button class="btn btn-warning shadow-sm" data-bs-toggle="modal" data-bs-target="#modalKasbon"><i class="fas fa-hand-holding-usd me-1"></i> Input Kasbon</button>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalPinjaman"><i class="fas fa-money-check-alt me-1"></i> Input Pinjaman</button>
    </div>
</div>

<?php display_flash_message(); ?>

<ul class="nav nav-tabs mb-4 border-bottom-0" id="myTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-bold px-4 pt-3 pb-3 tab-rounded" id="kasbon-tab" data-bs-toggle="tab" data-bs-target="#kasbon" type="button" role="tab">Data Kasbon <span class="badge bg-warning ms-2"><?= mysqli_num_rows($q_kasbon) ?></span></button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-bold px-4 pt-3 pb-3 tab-rounded" id="pinjaman-tab" data-bs-toggle="tab" data-bs-target="#pinjaman" type="button" role="tab">Data Pinjaman Berkala <span class="badge bg-primary ms-2"><?= mysqli_num_rows($q_pinjaman) ?></span></button>
    </li>
</ul>

<div class="tab-content" id="myTabContent">
    <!-- KASBON TAB -->
    <div class="tab-pane fade show active fade-down" id="kasbon" role="tabpanel">
        <div class="card shadow-sm border-0 border-top border-warning border-4">
            <div class="card-body p-0">
                <div class="card-header bg-white pb-0 border-0 pt-3 px-4">
                    <form method="GET" class="row">
                        <div class="col-md-4 mb-2">
                            <select name="f_kar" class="form-select form-select-sm">
                                <option value="">Semua Karyawan</option>
                                <?php foreach($karyawan_list as $emp): ?>
                                    <option value="<?= $emp['id'] ?>" <?= $f_kar == $emp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($emp['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <select name="f_bln" class="form-select form-select-sm">
                                <option value="">Semua Bulan</option>
                                <?php 
                                $mnths = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
                                for($i=1; $i<=12; $i++): 
                                ?>
                                    <option value="<?= $i ?>" <?= $f_bln == $i ? 'selected' : '' ?>><?= $mnths[$i-1] ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <button type="submit" class="btn btn-sm btn-outline-warning text-dark w-100 fw-bold"><i class="fas fa-filter"></i> Filter</button>
                        </div>
                        <?php if($f_kar > 0 || $f_bln > 0): ?>
                        <div class="col-md-2 mb-2">
                            <a href="/pjr_parking/modules/karyawan/pinjaman.php" class="btn btn-sm btn-light w-100">Reset</a>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="table-responsive p-3">
                    <table class="table table-hover table-striped align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Karyawan</th>
                                <th>Keterangan</th>
                                <th>Nominal</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($q_kasbon)>0): $no=1; while($kb = mysqli_fetch_assoc($q_kasbon)): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= date('d M Y', strtotime($kb['tanggal'])) ?></td>
                                <td>
                                    <div class="fw-bold text-primary"><?= htmlspecialchars($kb['nama']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($kb['nik']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($kb['keterangan'] ?: '-') ?></td>
                                <td class="fw-semibold text-danger"><?= format_rupiah($kb['nominal']) ?></td>
                                <td>
                                    <?php if($kb['status_lunas'] == 'Lunas'): ?>
                                        <span class="badge bg-success">Lunas</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Belum Lunas</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($kb['status_lunas'] == 'Belum Lunas'): ?>
                                    <form method="POST" style="display:inline-block;" onsubmit="return confirm('Batalkan kasbon? Pengeluaran yang sudah masuk akan ditarik (dihapus).')">
                                        <input type="hidden" name="delete_type" value="kasbon">
                                        <input type="hidden" name="delete_id" value="<?= $kb['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger px-2"><i class="fas fa-trash"></i> Batal</button>
                                    </form>
                                    <?php else: ?>
                                    <span class="text-muted small"><i class="fas fa-check"></i> Selesai</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data kasbon.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- PINJAMAN TAB -->
    <div class="tab-pane fade fade-down" id="pinjaman" role="tabpanel">
        <div class="card shadow-sm border-0 border-top border-primary border-4">
            <div class="card-body p-0">
                <div class="table-responsive p-3">
                    <table class="table table-hover table-striped align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>No</th>
                                <th>Tgl Cair</th>
                                <th>Karyawan</th>
                                <th>Nominal Total</th>
                                <th>Tenor (Bln)</th>
                                <th>Cicilan/Bln</th>
                                <th>Sisa Hutang</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($q_pinjaman)>0): $no=1; while($pj = mysqli_fetch_assoc($q_pinjaman)): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= date('d M Y', strtotime($pj['tanggal'])) ?></td>
                                <td>
                                    <div class="fw-bold text-primary"><?= htmlspecialchars($pj['nama']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($pj['nik']) ?></small>
                                </td>
                                <td class="fw-bold"><?= format_rupiah($pj['nominal_total']) ?></td>
                                <td class="text-center"><span class="badge bg-info text-dark fs-6"><?= $pj['tenor_bulan'] ?> X</span></td>
                                <td class="text-danger fw-semibold"><?= format_rupiah($pj['cicilan_per_bulan']) ?></td>
                                <td class="text-warning text-dark fw-bold"><?= format_rupiah($pj['sisa_pinjaman']) ?></td>
                                <td>
                                    <?php if($pj['status_lunas'] == 'Lunas'): ?>
                                        <span class="badge bg-success">Lunas</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Aktif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($pj['nominal_total'] == $pj['sisa_pinjaman']): ?>
                                    <!-- Only allow pure deletion if no installment has been paid yet -->
                                    <form method="POST" style="display:inline-block;" onsubmit="return confirm('Batalkan pinjaman penuh? Pengeluaran yang sudah masuk akan ditarik (dihapus).')">
                                        <input type="hidden" name="delete_type" value="pinjaman">
                                        <input type="hidden" name="delete_id" value="<?= $pj['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger px-2"><i class="fas fa-trash"></i> Batal</button>
                                    </form>
                                    <?php elseif ($pj['sisa_pinjaman'] <= 0): ?>
                                    <span class="text-muted small"><i class="fas fa-check"></i> Selesai</span>
                                    <?php else: ?>
                                    <span class="badge bg-light text-dark border"><i class="fas fa-lock text-muted"></i> Sedang Berjalan</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">Tidak ada data pinjaman cicilan.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Kasbon -->
<div class="modal fade" id="modalKasbon" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning border-0">
                <h5 class="modal-title fw-bold text-dark"><i class="fas fa-hand-holding-usd me-2"></i> Form Pencairan Kasbon Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="alert alert-light border-warning text-dark small mb-4">
                        <i class="fas fa-info-circle me-1"></i> Kasbon akan memotong kas pengeluaran hari ini dan menagih pelunasan otomatis 100% pada penggajian bulan ini/depan.
                    </div>
                    <input type="hidden" name="tipe_form" value="kasbon">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Pilih Karyawan</label>
                        <select name="karyawan_id" class="form-select" required>
                            <option value="">-- Karyawan Aktif --</option>
                            <?php foreach($karyawan_list as $emp): ?>
                                <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['nama']) ?> (<?= htmlspecialchars($emp['nik']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tanggal Pencairan</label>
                        <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nominal Kasbon (Rp)</label>
                        <input type="number" name="nominal" class="form-control" placeholder="Contoh: 500000" min="1000" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Keterangan / Alasan Kasbon</label>
                        <input type="text" name="keterangan" class="form-control" placeholder="Contoh: Kasbon darurat berobat...">
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning fw-bold text-dark"><i class="fas fa-check me-1"></i> Cairkan Kasbon</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Pinjaman -->
<div class="modal fade" id="modalPinjaman" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-money-check-alt me-2"></i> Form Pencairan Pinjaman Berjangka</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="alert alert-light border-primary text-dark small mb-4">
                        <i class="fas fa-info-circle me-1"></i> Pinjaman ini bersifat jangka panjang dan akan dicicil per bulan secara proporsional.
                    </div>
                    <input type="hidden" name="tipe_form" value="pinjaman">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Pilih Karyawan</label>
                        <select name="karyawan_id" class="form-select" required>
                            <option value="">-- Karyawan Aktif --</option>
                            <?php foreach($karyawan_list as $emp): ?>
                                <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['nama']) ?> (<?= htmlspecialchars($emp['nik']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tanggal Cair</label>
                        <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="row mb-3">
                        <div class="col-7">
                            <label class="form-label fw-bold">Nominal Total Pinjaman (Rp)</label>
                            <input type="number" name="nominal_total" class="form-control" id="pinjNom" oninput="calcPinj()" placeholder="1000000" min="1000" required>
                        </div>
                        <div class="col-5">
                            <label class="form-label fw-bold">Tenor (Bulan)</label>
                            <input type="number" name="tenor_bulan" class="form-control text-center text-primary fw-bold" id="pinjTenor" oninput="calcPinj()" placeholder="Bulan" min="1" max="120" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Cicilan / Bulan (Rp) <small class="text-muted fw-normal">- Bisa diubah manual</small></label>
                        <input type="number" name="cicilan_per_bulan" class="form-control fw-bold text-primary" id="pinjCicilInput" placeholder="Nominal cicilan per bulan" min="1000" required>
                        <small class="text-muted">Saran otomatis: <span id="pinjCicilHint">-</span></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Keterangan Pinjaman</label>
                        <input type="text" name="keterangan" class="form-control" placeholder="Tujuan Pinjaman...">
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary fw-bold"><i class="fas fa-check me-1"></i> Cairkan Pinjaman</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.tab-rounded { border-radius: 8px 8px 0 0 !important; border-bottom: none !important; margin-right: 5px; background-color: #f8f9fa; color: #555; }
.tab-rounded.active { background-color: #fff; color: #000; box-shadow: 0 -3px 0 0 inset var(--bs-primary); }
</style>
<script>
function calcPinj() {
    let nom = parseFloat(document.getElementById('pinjNom').value) || 0;
    let tenor = parseInt(document.getElementById('pinjTenor').value) || 1;
    let cicil = nom / tenor;
    let fmt = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits:0 }).format(cicil);
    document.getElementById('pinjCicilHint').innerText = fmt + ' (otomatis ' + tenor + ' bulan)';
    // Auto-fill only if cicilan field is empty
    let inputCicil = document.getElementById('pinjCicilInput');
    if(!inputCicil.value || inputCicil.dataset.autoset === 'true') {
        inputCicil.value = cicil.toFixed(0);
        inputCicil.dataset.autoset = 'true';
    }
}
document.getElementById('pinjCicilInput').addEventListener('input', function() {
    this.dataset.autoset = 'false';
});
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
