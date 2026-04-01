<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';

check_role(['Admin', 'Super Admin', 'HRD']);
$cabang_id_session = $_SESSION['user']['cabang_id'] ?? 1;
$is_super = ($_SESSION['user']['role'] === 'Super Admin');

$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
$months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];

// Helper Kat
function get_kategori_gaji_id($conn) {
    $q = mysqli_query($conn, "SELECT id FROM kategori_pengeluaran WHERE nama_kategori = 'Gaji Karyawan'");
    if(mysqli_num_rows($q) > 0) return mysqli_fetch_assoc($q)['id'];
    mysqli_query($conn, "INSERT INTO kategori_pengeluaran (nama_kategori) VALUES ('Gaji Karyawan')");
    return mysqli_insert_id($conn);
}

// Generate Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_payroll'])) {
    $wc = $is_super ? "1=1" : "cabang_id = $cabang_id_session";
    $kat_gaji = get_kategori_gaji_id($conn);
    $hari_ini = date('Y-m-d');
    
    // Check if payroll already exists for any active employee this month. 
    // To prevent double generation, we only generate for those who haven't been generated yet.
    $q_emp = mysqli_query($conn, "SELECT * FROM karyawan WHERE status = 'Aktif' AND $wc");
    
    $generated_count = 0;
    while($emp = mysqli_fetch_assoc($q_emp)) {
        $k_id = $emp['id'];
        $c_id = $emp['cabang_id'];
        $nama = $emp['nama'];
        
        // Skip if already generated
        $check = mysqli_query($conn, "SELECT id FROM penggajian WHERE karyawan_id = $k_id AND bulan = $bulan AND tahun = $tahun");
        if(mysqli_num_rows($check) > 0) continue; 
        
        // 1. Absensi (Alpha calculation)
        $q_abs = mysqli_query($conn, "SELECT alpha FROM absensi WHERE karyawan_id = $k_id AND bulan = $bulan AND tahun = $tahun");
        $alpha_hari = mysqli_num_rows($q_abs) > 0 ? (int)mysqli_fetch_assoc($q_abs)['alpha'] : 0;
        
        // Asumsi daily rate = gaji pokok / 30
        $potongan_alpha = ($emp['gaji_pokok'] / 30) * $alpha_hari;
        
        // 2. Kasbon (Lunasin semua kasbon aktif)
        $kasbon_ids = [];
        $potongan_kasbon = 0;
        $q_kb = mysqli_query($conn, "SELECT id, nominal FROM kasbon WHERE karyawan_id = $k_id AND status_lunas = 'Belum Lunas'");
        while($kb = mysqli_fetch_assoc($q_kb)) {
            $potongan_kasbon += (float)$kb['nominal'];
            $kasbon_ids[] = $kb['id'];
        }
        
        // 3. Pinjaman Cicilan
        $pinjaman_history = [];
        $potongan_pinjaman = 0;
        $q_pj = mysqli_query($conn, "SELECT id, cicilan_per_bulan FROM pinjaman WHERE karyawan_id = $k_id AND status_lunas = 'Belum Lunas'");
        while($pj = mysqli_fetch_assoc($q_pj)) {
            $potongan_pinjaman += (float)$pj['cicilan_per_bulan'];
            $pinjaman_history[] = [
                'id' => $pj['id'],
                'deducted' => (float)$pj['cicilan_per_bulan']
            ];
        }
        
        $history_json = json_encode([
            'kasbon_ids' => $kasbon_ids,
            'pinjaman_history' => $pinjaman_history
        ]);
        
        // 4. Calculate Net Pay
        $gaji = (float)$emp['gaji_pokok'];
        $tjab = (float)$emp['tunjangan_jabatan'];
        $tmak = (float)$emp['tunjangan_makan'];
        $ttra = (float)$emp['tunjangan_transport'];
        
        $kotor = $gaji + $tjab + $tmak + $ttra;
        $potongan = $potongan_alpha + $potongan_kasbon + $potongan_pinjaman;
        $bersih = $kotor - $potongan;
        if($bersih < 0) $bersih = 0;
        
        // 5. Insert pengeluaran
        $ket_pengeluaran = "Gaji Karyawan a.n. $nama (Bulan $bulan/$tahun)";
        mysqli_query($conn, "INSERT INTO pengeluaran (tanggal, kategori_id, nominal, keterangan, cabang_id) VALUES ('$hari_ini', $kat_gaji, $bersih, '$ket_pengeluaran', $c_id)");
        $pengeluaran_id = mysqli_insert_id($conn);
        
        // 6. Insert penggajian
        $stmt = $conn->prepare("INSERT INTO penggajian (karyawan_id, bulan, tahun, gaji_pokok, tunjangan_jabatan, tunjangan_makan, tunjangan_transport, potongan_kasbon, potongan_pinjaman, potongan_alpha, total_gaji_bersih, tanggal_cair, pengeluaran_id, history_potongan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiddddddddsss", $k_id, $bulan, $tahun, $gaji, $tjab, $tmak, $ttra, $potongan_kasbon, $potongan_pinjaman, $potongan_alpha, $bersih, $hari_ini, $pengeluaran_id, $history_json);
        
        if($stmt->execute()) {
            $generated_count++;
            
            // Lunasin kasbon & kurangi pinjaman
            mysqli_query($conn, "UPDATE kasbon SET status_lunas = 'Lunas' WHERE karyawan_id = $k_id AND status_lunas = 'Belum Lunas'");
            
            $q_pj_list = mysqli_query($conn, "SELECT id, sisa_pinjaman, cicilan_per_bulan FROM pinjaman WHERE karyawan_id = $k_id AND status_lunas = 'Belum Lunas'");
            while($pj = mysqli_fetch_assoc($q_pj_list)) {
                $pid = $pj['id'];
                $sisa = $pj['sisa_pinjaman'] - $pj['cicilan_per_bulan'];
                if($sisa <= 0) {
                    $sisa = 0;
                    mysqli_query($conn, "UPDATE pinjaman SET sisa_pinjaman = 0, status_lunas = 'Lunas' WHERE id = $pid");
                } else {
                    mysqli_query($conn, "UPDATE pinjaman SET sisa_pinjaman = $sisa WHERE id = $pid");
                }
            }
        }
    }
    
    if($generated_count > 0) {
        set_flash_message('success', "Generate Penggajian selesai untuk $generated_count karyawan. Gaji bersih telah dipotong dari kas pengeluaran.");
    } else {
        set_flash_message('warning', "Semua karyawan aktif di cabang Anda sudah digenerate gajinya bulan ini, atau tidak ada karyawan aktif.");
    }
    redirect("/modules/penggajian/index.php?bulan=$bulan&tahun=$tahun");
}

// Regenerate single employee action
if (isset($_GET['regenerate'])) {
    $r_id = (int)$_GET['regenerate'];
    $q_old = mysqli_query($conn, "SELECT * FROM penggajian WHERE id = $r_id");
    
    if(mysqli_num_rows($q_old) > 0) {
        $old = mysqli_fetch_assoc($q_old);
        $k_id = $old['karyawan_id'];
        
        $q_emp = mysqli_query($conn, "SELECT * FROM karyawan WHERE id = $k_id");
        if(mysqli_num_rows($q_emp) > 0) {
            $emp = mysqli_fetch_assoc($q_emp);
            
            // Rekalkulasi Alpha
            $q_abs = mysqli_query($conn, "SELECT alpha FROM absensi WHERE karyawan_id = $k_id AND bulan = {$old['bulan']} AND tahun = {$old['tahun']}");
            $alpha_hari = mysqli_num_rows($q_abs) > 0 ? (int)mysqli_fetch_assoc($q_abs)['alpha'] : 0;
            $pot_alpha = ($emp['gaji_pokok'] / 30) * $alpha_hari;
            
            // Check Kasbon Baru
            $new_kb = 0; $new_kb_ids = [];
            $q_kb = mysqli_query($conn, "SELECT id, nominal FROM kasbon WHERE karyawan_id = $k_id AND status_lunas = 'Belum Lunas'");
            while($kb = mysqli_fetch_assoc($q_kb)) {
                $new_kb += (float)$kb['nominal'];
                $new_kb_ids[] = $kb['id'];
            }
            mysqli_query($conn, "UPDATE kasbon SET status_lunas = 'Lunas' WHERE karyawan_id = $k_id AND status_lunas = 'Belum Lunas'");
            
            // Check Pinjaman Baru
            $new_pj = 0; $new_pj_hist = [];
            $q_pj = mysqli_query($conn, "SELECT id, sisa_pinjaman, cicilan_per_bulan FROM pinjaman WHERE karyawan_id = $k_id AND status_lunas = 'Belum Lunas'");
            while($pj = mysqli_fetch_assoc($q_pj)) {
                $new_pj += $pj['cicilan_per_bulan'];
                $new_pj_hist[] = ['id' => $pj['id'], 'deducted' => (float)$pj['cicilan_per_bulan']];
                $sisa = $pj['sisa_pinjaman'] - $pj['cicilan_per_bulan'];
                if($sisa <= 0) {
                    mysqli_query($conn, "UPDATE pinjaman SET sisa_pinjaman = 0, status_lunas = 'Lunas' WHERE id = {$pj['id']}");
                } else {
                    mysqli_query($conn, "UPDATE pinjaman SET sisa_pinjaman = $sisa WHERE id = {$pj['id']}");
                }
            }
            
            // Append history
            $old_history = json_decode($old['history_potongan'] ?? '{"kasbon_ids":[], "pinjaman_history":[]}', true);
            $merged_history = [
                'kasbon_ids' => array_merge(isset($old_history['kasbon_ids']) ? $old_history['kasbon_ids'] : [], $new_kb_ids),
                'pinjaman_history' => array_merge(isset($old_history['pinjaman_history']) ? $old_history['pinjaman_history'] : [], $new_pj_hist)
            ];
            $fin_history = mysqli_real_escape_string($conn, json_encode($merged_history));
            
            // Gaji Master
            $gaji = $emp['gaji_pokok'];
            $tjab = $emp['tunjangan_jabatan'];
            $tmak = $emp['tunjangan_makan'];
            $ttra = $emp['tunjangan_transport'];
            
            // Akumulasi potongan (Lama + Baru)
            $fin_kb = $old['potongan_kasbon'] + $new_kb;
            $fin_pj = $old['potongan_pinjaman'] + $new_pj;
            
            // Hitung Ulang
            $kotor = $gaji + $tjab + $tmak + $ttra;
            $bersih = max(0, $kotor - $fin_kb - $fin_pj - $pot_alpha);
            
            // Update Data
            mysqli_query($conn, "UPDATE penggajian SET gaji_pokok=$gaji, tunjangan_jabatan=$tjab, tunjangan_makan=$tmak, tunjangan_transport=$ttra, potongan_alpha=$pot_alpha, potongan_kasbon=$fin_kb, potongan_pinjaman=$fin_pj, total_gaji_bersih=$bersih, history_potongan='$fin_history' WHERE id=$r_id");
            mysqli_query($conn, "UPDATE pengeluaran SET nominal=$bersih WHERE id={$old['pengeluaran_id']}");
            
            set_flash_message('success', 'Gaji berhasil di-Update Ulang dengan master data/absensi/kasbon terbaru.');
        }
    }
    redirect("/modules/penggajian/index.php?bulan=$bulan&tahun=$tahun");
}

// Delete / Batalkan Gaji Action
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $q_del = mysqli_query($conn, "SELECT pengeluaran_id, history_potongan FROM penggajian WHERE id = $del_id");
    
    if(mysqli_num_rows($q_del) > 0) {
        $del_data = mysqli_fetch_assoc($q_del);
        
        // Revert History Potongan
        if(!empty($del_data['history_potongan'])) {
            $history = json_decode($del_data['history_potongan'], true);
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
        }
        
        // Hapus dari buku kas pengeluaran
        if($del_data['pengeluaran_id']) {
            mysqli_query($conn, "DELETE FROM pengeluaran WHERE id = {$del_data['pengeluaran_id']}");
        }
        // Hapus record penggajian (Sehingga bisa di-generate ulang)
        mysqli_query($conn, "DELETE FROM penggajian WHERE id = $del_id");
        
        set_flash_message('success', "Data Penggajian berhasil dibatalkan dan dihapus dari pembukuan kas. Status Potongan Kasbon/Pinjaman terkait telah di-revert lunas dengan sempurna.");
    }
    redirect("/modules/penggajian/index.php?bulan=$bulan&tahun=$tahun");
}

// Fetch Payroll Data
$wc_gaji = $is_super ? "1=1" : "k.cabang_id = $cabang_id_session";
$q_gaji = mysqli_query($conn, "
    SELECT p.*, k.nama, k.nik, k.divisi, k.jabatan
    FROM penggajian p 
    JOIN karyawan k ON p.karyawan_id = k.id 
    WHERE p.bulan = $bulan AND p.tahun = $tahun AND $wc_gaji
    ORDER BY k.nama ASC
");

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">Penggajian Bulanan & Slip Gaji</h4>
</div>

<?php display_flash_message(); ?>

<div class="card bg-white shadow-sm border-0 border-top border-primary border-4 mb-4">
    <div class="card-body">
        <div class="row align-items-end">
            <div class="col-md-9">
                <form method="GET" class="row align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Pilih Bulan</label>
                        <select name="bulan" class="form-select">
                            <?php for($i=1; $i<=12; $i++): ?>
                                <option value="<?= $i ?>" <?= $i == $bulan ? 'selected' : '' ?>><?= $months[$i-1] ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Pilih Tahun</label>
                        <input type="number" name="tahun" class="form-control" value="<?= $tahun ?>" min="2020" max="2050">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-outline-primary fw-bold"><i class="fas fa-search me-1"></i> Lihat Data</button>
                    </div>
                </form>
            </div>
            <div class="col-md-3 text-end">
                <form method="POST" action="">
                    <input type="hidden" name="generate_payroll" value="1">
                    <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm w-100" onclick="return confirm('Proses Penggajian Karyawan secara Massal untuk bulan ini?\n\nPastikan data Kasbon/Pinjaman/Absensi sudah final, karena proses ini akan langsung memotong kas Pengeluaran Operasional secara otomatis.')">
                        <i class="fas fa-cogs me-1"></i> Generate Gaji (Massal)
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card shadow border-0">
    <div class="card-body p-0">
        <div class="table-responsive p-3">
            <table class="table table-hover table-striped align-middle">
                <thead class="bg-light text-center">
                    <tr>
                        <th width="5%">No</th>
                        <th class="text-start">Nama / NIK</th>
                        <th>Posisi</th>
                        <th>Total Kotor</th>
                        <th>Total Potongan</th>
                        <th class="text-success border-success">Penerimaan Bersih</th>
                        <th>Tgl Generate</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($q_gaji)>0): $no=1; while($g = mysqli_fetch_assoc($q_gaji)): 
                        $kotor = $g['gaji_pokok'] + $g['tunjangan_jabatan'] + $g['tunjangan_makan'] + $g['tunjangan_transport'];
                        $potong = $g['potongan_kasbon'] + $g['potongan_pinjaman'] + $g['potongan_alpha'];
                    ?>
                    <tr>
                        <td class="text-center align-middle"><?= $no++ ?></td>
                        <td class="align-middle">
                            <div class="fw-bold text-primary"><?= htmlspecialchars($g['nama']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($g['nik']) ?></small>
                        </td>
                        <td class="text-center align-middle">
                            <div class="small fw-semibold"><?= htmlspecialchars($g['jabatan']) ?></div>
                            <span class="badge bg-secondary" style="font-size: 0.70em;"><?= htmlspecialchars($g['divisi']) ?></span>
                        </td>
                        <td class="text-end fw-semibold text-dark align-middle"><?= format_rupiah($kotor) ?></td>
                        <td class="text-end fw-semibold text-danger align-middle">- <?= format_rupiah($potong) ?></td>
                        <td class="text-end fw-bold text-success fs-6 align-middle border-success"><?= format_rupiah($g['total_gaji_bersih']) ?></td>
                        <td class="text-center small text-muted align-middle"><?= date('d/m/Y', strtotime($g['tanggal_cair'])) ?></td>
                        <td class="text-center align-middle">
                            <a href="/modules/penggajian/slip_gaji.php?id=<?= $g['id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark mb-1" title="Cetak Slip"><i class="fas fa-print"></i></a>
                            <a href="?regenerate=<?= $g['id'] ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" class="btn btn-sm btn-outline-info mb-1" onclick="return confirm('Update Ulang gaji ini dengan nominal master dan absensi terbaru?')" title="Update Ulang Master Gaji"><i class="fas fa-sync-alt"></i></a>
                            <br>
                            <a href="?delete=<?= $g['id'] ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Yakin ingin membatalkan dan menghapus data gaji ini? Data pengeluaran terkait juga akan ditarik mundur.')" title="Batalkan Gaji"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">
                        <i class="fas fa-file-invoice-dollar mt-3 mb-2" style="font-size: 2rem; opacity: 0.4;"></i><br>
                        Belum ada data penggajian untuk periode ini.<br>Silakan klik tombol <b>Generate Gaji (Massal)</b> di atas.
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
