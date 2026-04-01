<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Admin', 'Bendahara']);

if (!isset($_GET['id']) || !isset($_GET['bulan']) || !isset($_GET['tahun'])) {
    redirect('/modules/pembayaran/member.php');
}

$id = (int)$_GET['id'];
$bulan = (int)$_GET['bulan'];
$tahun = (int)$_GET['tahun'];

$q_m = mysqli_query($conn, "SELECT * FROM member WHERE id = $id");
if (mysqli_num_rows($q_m) == 0) redirect('/modules/pembayaran/member.php');
$member = mysqli_fetch_assoc($q_m);
$is_kolektif = ($member['tipe_pembayaran'] == 'Kolektif');

$months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$nama_bulan = $months[$bulan-1];

// Handle Pembayaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_bayar'])) {
    $detail_id = isset($_POST['detail_id']) && $_POST['detail_id'] != '' ? (int)$_POST['detail_id'] : 'NULL';
    $jumlah = (float)$_POST['jumlah_bayar'];
    $metode = sanitize($conn, $_POST['metode_bayar']);
    $ket = sanitize($conn, $_POST['keterangan']);
    $tgl_bayar = sanitize($conn, $_POST['tanggal_bayar']);
    
    // Remove double-payment block to allow cicilan
    $q_insert = "INSERT INTO pembayaran_member (member_id, member_detail_id, bulan, tahun, jumlah_bayar, tanggal_bayar, metode_bayar, keterangan) 
                 VALUES ($id, $detail_id, $bulan, $tahun, $jumlah, '$tgl_bayar', '$metode', '$ket')";
    if(mysqli_query($conn, $q_insert)) {
        set_flash_message('success', 'Pembayaran berhasil diproses dan masuk ke catatan Pemasukan.');
    } else {
        set_flash_message('error', 'Gagal memproses pembayaran: ' . mysqli_error($conn));
    }
    redirect("/modules/pembayaran/proses_member.php?id=$id&bulan=$bulan&tahun=$tahun");
}

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';

// Fetch details
$details = [];
$q_d = mysqli_query($conn, "SELECT * FROM member_detail WHERE member_id = $id");
while($r = mysqli_fetch_assoc($q_d)) $details[] = $r;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">Proses Pembayaran Member</h4>
    <a href="/modules/pembayaran/member.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" class="btn btn-secondary shadow-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
</div>

<?php display_flash_message(); ?>

<div class="row">
    <!-- Info Section -->
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm border-0 border-top border-primary border-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3"><?= htmlspecialchars($member['nama']) ?></h5>
                <p class="mb-1 text-muted small">Periode Tagihan</p>
                <h6 class="fw-bold mb-3 text-primary"><?= $nama_bulan ?> <?= $tahun ?></h6>
                
                <p class="mb-1 text-muted small">Tipe Penagihan</p>
                <p class="fw-bold">
                    <?php if ($is_kolektif): ?>
                        <span class="badge bg-primary">Kolektif (1 Tagihan)</span>
                    <?php else: ?>
                        <span class="badge bg-info text-dark">Individual (Per Personil)</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Payment Section -->
    <div class="col-md-8 mb-4">
        <?php if ($is_kolektif): ?>
            <!-- Kolektif / 1 Form -->
            <?php 
            $q_cek = mysqli_query($conn, "SELECT SUM(jumlah_bayar) as tb FROM pembayaran_member WHERE member_id=$id AND bulan=$bulan AND tahun=$tahun");
            $tb = (float)mysqli_fetch_assoc($q_cek)['tb'];
            $target = (float)$member['nominal_iuran'];
            $sdh_bayar = $tb >= $target;
            $sisa = $target - $tb;
            ?>
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold d-flex justify-content-between">
                    <span>Tagihan Kolektif Perusahaan (Target: <?= format_rupiah($target) ?>)</span>
                    <?php if($sdh_bayar): ?>
                        <span class="badge bg-success">LUNAS</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if(!$sdh_bayar): ?>
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="proses_bayar" value="1">
                        <input type="hidden" name="detail_id" value="">
                        <div class="alert alert-warning py-2 small">Sisa tagihan yang harus dibayar: <strong><?= format_rupiah($sisa) ?></strong></div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Tanggal Bayar</label>
                                <input type="date" name="tanggal_bayar" class="form-control" required value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nominal Bayar (Bisa Dicicil)</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" name="jumlah_bayar" class="form-control" required min="1000" max="<?= $sisa ?>" value="<?= $sisa ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Metode Pembayaran</label>
                                <select name="metode_bayar" class="form-select" required>
                                    <option value="Transfer Bank">Transfer Bank / Non-Tunai</option>
                                    <option value="QRIS">QRIS</option>
                                    <option value="Tunai">Tunai</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Keterangan (Opsional)</label>
                                <input type="text" name="keterangan" class="form-control" placeholder="No Ref Transfer dll...">
                            </div>
                        </div>
                        <button class="btn btn-primary w-100 fw-bold"><i class="fas fa-check-circle me-1"></i> Simpan Pembayaran</button>
                    </form>
                    <?php endif; ?>
                    
                    <?php
                    $histori = mysqli_query($conn, "SELECT * FROM pembayaran_member WHERE member_id=$id AND bulan=$bulan AND tahun=$tahun ORDER BY id ASC");
                    if(mysqli_num_rows($histori)>0):
                    ?>
                    <h6 class="fw-bold fs-6 mt-3">Histori Pembayaran Bulan Ini</h6>
                    <ul class="list-group list-group-flush small">
                        <?php while($h = mysqli_fetch_assoc($histori)): ?>
                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-check text-success me-2"></i> <strong><?= date('d M Y', strtotime($h['tanggal_bayar'])) ?></strong><br>
                                <span class="text-muted ms-4"><?= $h['metode_bayar'] ?> <?= $h['keterangan'] ? "({$h['keterangan']})" : "" ?></span>
                            </div>
                            <span class="fw-bold text-success">+ <?= format_rupiah($h['jumlah_bayar']) ?></span>
                        </li>
                        <?php endwhile; ?>
                        <li class="list-group-item px-0 d-flex justify-content-between bg-light mt-2 rounded p-2">
                            <strong>Total Dibayar:</strong>
                            <strong class="text-primary"><?= format_rupiah($tb) ?></strong>
                        </li>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Individual / Multi Form -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold">
                    Penagihan Per Personil / Kendaraan
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Nama Personil & Kendaraan</th>
                                    <th>Status Pembayaran</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($details)==0): ?>
                                <tr>
                                    <td colspan="2" class="p-4 text-center">
                                        <div class="text-danger mb-2"><i class="fas fa-exclamation-triangle fa-2x"></i></div>
                                        <strong>Belum ada detail personil.</strong><br>
                                        Member individual yang belum memiliki detail personil tagihannya adalah untuk Master Member itu sendiri.<br>
                                        <button class="btn btn-sm btn-outline-primary mt-2" data-bs-toggle="modal" data-bs-target="#modalMaster">Bayar sebagai Master</button>
                                    </td>
                                </tr>
                                
                                <!-- Modal Master -->
                                <div class="modal fade" id="modalMaster" tabindex="-1" aria-hidden="true">
                                  <div class="modal-dialog">
                                    <div class="modal-content">
                                      <div class="modal-header">
                                        <h5 class="modal-title">Bayar untuk: <?= htmlspecialchars($member['nama']) ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                      </div>
                                      <form method="POST">
                                          <div class="modal-body">
                                              <?php 
                                              $c_master = mysqli_query($conn, "SELECT * FROM pembayaran_member WHERE member_id=$id AND member_detail_id IS NULL AND bulan=$bulan AND tahun=$tahun");
                                              if(mysqli_num_rows($c_master)>0): 
                                              ?>
                                                <div class="alert alert-success">Sudah dibayar! Lunas.</div>
                                              <?php else: ?>
                                              <input type="hidden" name="proses_bayar" value="1">
                                              <input type="hidden" name="detail_id" value="">
                                              <div class="mb-3">
                                                  <label>Tanggal</label><input type="date" name="tanggal_bayar" class="form-control" required value="<?= date('Y-m-d') ?>">
                                              </div>
                                              <div class="mb-3">
                                                  <label>Jumlah</label><input type="number" name="jumlah_bayar" class="form-control" required min="1000">
                                              </div>
                                              <div class="mb-3">
                                                  <label>Metode</label>
                                                  <select name="metode_bayar" class="form-select" required>
                                                      <option value="Tunai">Tunai</option><option value="Transfer Bank">Transfer Bank</option><option value="QRIS">QRIS</option>
                                                  </select>
                                              </div>
                                              <div class="mb-3">
                                                  <label>Ket</label><input type="text" name="keterangan" class="form-control">
                                              </div>
                                              <?php endif; ?>
                                          </div>
                                          <div class="modal-footer">
                                            <?php if(mysqli_num_rows($c_master) == 0): ?>
                                            <button type="submit" class="btn btn-primary w-100">Proses</button>
                                            <?php endif;?>
                                          </div>
                                      </form>
                                    </div>
                                  </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php foreach($details as $d): 
                                    $d_id = $d['id'];
                                    $c_det = mysqli_query($conn, "SELECT SUM(jumlah_bayar) as tb FROM pembayaran_member WHERE member_detail_id = $d_id AND bulan=$bulan AND tahun=$tahun");
                                    $tb = (float)mysqli_fetch_assoc($c_det)['tb'];
                                    $target = (float)$d['nominal_iuran'];
                                    $lunas = $tb >= $target;
                                    $sisa = $target - $tb;
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <strong><?= htmlspecialchars($d['nama_personil']) ?></strong>
                                        <div class="text-muted small"><?= htmlspecialchars($d['kendaraan']) ?> | <?= htmlspecialchars($d['nopol']) ?></div>
                                        <div class="text-primary small">Tagihan: <?= format_rupiah($target) ?></div>
                                    </td>
                                    <td>
                                        <?php if($lunas): ?>
                                            <span class="badge bg-success mb-1">LUNAS</span><br>
                                            <small class="text-muted">Dibayar: <?= format_rupiah($tb) ?></small>
                                        <?php else: ?>
                                            <?php if($tb > 0): ?>
                                                <span class="badge bg-warning text-dark mb-1">Kurang <?= format_rupiah($sisa) ?></span><br>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-primary mt-1" data-bs-toggle="modal" data-bs-target="#modalDet<?= $d_id ?>">Bayar Lapak Ini</button>
                                            
                                            <!-- Modal Det -->
                                            <div class="modal fade" id="modalDet<?= $d_id ?>" tabindex="-1" aria-hidden="true">
                                              <div class="modal-dialog">
                                                <div class="modal-content">
                                                  <div class="modal-header">
                                                    <h5 class="modal-title">Bayar: <?= htmlspecialchars($d['nama_personil']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                  </div>
                                                  <form method="POST">
                                                      <div class="modal-body">
                                                          <div class="alert alert-info py-2">Sisa Tagihan: <strong><?= format_rupiah($sisa) ?></strong></div>
                                                          <input type="hidden" name="proses_bayar" value="1">
                                                          <input type="hidden" name="detail_id" value="<?= $d_id ?>">
                                                          <div class="mb-3">
                                                              <label>Tanggal</label><input type="date" name="tanggal_bayar" class="form-control" required value="<?= date('Y-m-d') ?>">
                                                          </div>
                                                          <div class="mb-3">
                                                              <label>Jumlah Bayar</label><input type="number" name="jumlah_bayar" class="form-control" required min="1000" max="<?= $sisa ?>" value="<?= $sisa ?>">
                                                          </div>
                                                          <div class="mb-3">
                                                              <label>Metode</label>
                                                              <select name="metode_bayar" class="form-select" required>
                                                                  <option value="Tunai">Tunai</option><option value="Transfer Bank">Transfer Bank</option><option value="QRIS">QRIS</option>
                                                              </select>
                                                          </div>
                                                          <div class="mb-3">
                                                              <label>Keterangan</label><input type="text" name="keterangan" class="form-control">
                                                          </div>
                                                      </div>
                                                      <div class="modal-footer">
                                                        <button type="submit" class="btn btn-primary w-100">Proses</button>
                                                      </div>
                                                  </form>
                                                </div>
                                              </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
