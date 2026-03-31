<?php
require_once __DIR__ . '/middleware/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/layouts/header.php';
require_once __DIR__ . '/layouts/sidebar.php';

// Prepare data for dashboard
$today = date('Y-m-d');
$first_day_of_month = date('Y-m-01');
$last_day_of_month = date('Y-m-t');

$cabang_id = $_SESSION['user']['cabang_id'] ?? 1;
$is_super = ($_SESSION['user']['role'] === 'Super Admin');
$where_cabang = $is_super ? "1=1" : "cabang_id = $cabang_id";

$wc_m = str_replace('cabang_id', 'm.cabang_id', $where_cabang);
$wc_r = str_replace('cabang_id', 'r.cabang_id', $where_cabang);
$wc_p = str_replace('cabang_id', 'p.cabang_id', $where_cabang);

// Helper for total
function get_sum($conn, $query) {
    $res = mysqli_query($conn, $query);
    if($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        return $row['total'] ?? 0;
    }
    return 0;
}

// ------------------ INCOME TODAY ------------------
$parkir_hari_ini = get_sum($conn, "SELECT SUM(total_bersih) as total FROM pendapatan_parkir WHERE tanggal = '$today' AND $where_cabang");
$lain_hari_ini = get_sum($conn, "SELECT SUM(nominal) as total FROM pendapatan_lain WHERE tanggal = '$today' AND $where_cabang");
$member_hari_ini = get_sum($conn, "SELECT SUM(pm.jumlah_bayar) as total FROM pembayaran_member pm JOIN member m ON pm.member_id = m.id WHERE pm.tanggal_bayar = '$today' AND $wc_m");
$ruko_hari_ini = get_sum($conn, "SELECT SUM(pr.jumlah) as total FROM pembayaran_ruko pr JOIN ruko r ON pr.ruko_id = r.id WHERE pr.tanggal_bayar = '$today' AND $wc_r");
$pedagang_hari_ini = get_sum($conn, "SELECT SUM(pp.jumlah) as total FROM pembayaran_pedagang pp JOIN pedagang p ON pp.pedagang_id = p.id WHERE pp.tanggal_bayar = '$today' AND $wc_p");

$total_income_today = $parkir_hari_ini + $lain_hari_ini + $member_hari_ini + $ruko_hari_ini + $pedagang_hari_ini;

// ------------------ INCOME THIS MONTH ------------------
$parkir_bulan_ini = get_sum($conn, "SELECT SUM(total_bersih) as total FROM pendapatan_parkir WHERE tanggal BETWEEN '$first_day_of_month' AND '$last_day_of_month' AND $where_cabang");
$lain_bulan_ini = get_sum($conn, "SELECT SUM(nominal) as total FROM pendapatan_lain WHERE tanggal BETWEEN '$first_day_of_month' AND '$last_day_of_month' AND $where_cabang");
$member_bulan_ini = get_sum($conn, "SELECT SUM(pm.jumlah_bayar) as total FROM pembayaran_member pm JOIN member m ON pm.member_id = m.id WHERE pm.bulan = ".(int)date('m')." AND pm.tahun = ".(int)date('Y')." AND $wc_m");
$ruko_bulan_ini = get_sum($conn, "SELECT SUM(pr.jumlah) as total FROM pembayaran_ruko pr JOIN ruko r ON pr.ruko_id = r.id WHERE pr.bulan = ".(int)date('m')." AND pr.tahun = ".(int)date('Y')." AND $wc_r");
$pedagang_bulan_ini = get_sum($conn, "SELECT SUM(pp.jumlah) as total FROM pembayaran_pedagang pp JOIN pedagang p ON pp.pedagang_id = p.id WHERE pp.bulan = ".(int)date('m')." AND pp.tahun = ".(int)date('Y')." AND $wc_p");

$total_income_month = $parkir_bulan_ini + $lain_bulan_ini + $member_bulan_ini + $ruko_bulan_ini + $pedagang_bulan_ini;

// ------------------ PENGELUARAN ------------------
$pengeluaran_hari_ini = get_sum($conn, "SELECT SUM(nominal) as total FROM pengeluaran WHERE tanggal = '$today' AND $where_cabang");
$pengeluaran_bulan_ini = get_sum($conn, "SELECT SUM(nominal) as total FROM pengeluaran WHERE tanggal BETWEEN '$first_day_of_month' AND '$last_day_of_month' AND $where_cabang");

// Arrears (Tunggakan) Calculation
$hari_ini = (int)date('d');
$bulan_ini = (int)date('m');
$tahun_ini = (int)date('Y');

// Member Tunggakan
$query_tunggakan_member = "
    SELECT m.id
    FROM member m
    WHERE m.status = 'Aktif' AND m.tanggal_jatuh_tempo < $hari_ini AND " . str_replace('cabang_id', 'm.cabang_id', $where_cabang) . "
    AND m.id NOT IN (
        SELECT member_id FROM pembayaran_member WHERE bulan = $bulan_ini AND tahun = $tahun_ini
    )
";
$res_tm = mysqli_query($conn, $query_tunggakan_member);
$total_tunggakan_member = $res_tm ? mysqli_num_rows($res_tm) : 0;

// Ruko Tunggakan
$query_tunggakan_ruko = "
    SELECT r.id, r.nominal_iuran
    FROM ruko r
    WHERE r.status = 'Aktif' AND r.tanggal_jatuh_tempo < $hari_ini AND " . str_replace('cabang_id', 'r.cabang_id', $where_cabang) . "
    AND r.id NOT IN (
        SELECT ruko_id FROM pembayaran_ruko WHERE bulan = $bulan_ini AND tahun = $tahun_ini
    )
";
$res_tr = mysqli_query($conn, $query_tunggakan_ruko);
$total_tunggakan_ruko = $res_tr ? mysqli_num_rows($res_tr) : 0;
$nominal_tunggakan_ruko = 0;
if($res_tr) while($row = mysqli_fetch_assoc($res_tr)) { $nominal_tunggakan_ruko += $row['nominal_iuran']; }

// Pedagang Tunggakan
$query_tunggakan_pedagang = "
    SELECT p.id, p.nominal_iuran
    FROM pedagang p
    WHERE p.status = 'Aktif' AND p.tanggal_jatuh_tempo < $hari_ini AND " . str_replace('cabang_id', 'p.cabang_id', $where_cabang) . "
    AND p.id NOT IN (
        SELECT pedagang_id FROM pembayaran_pedagang WHERE bulan = $bulan_ini AND tahun = $tahun_ini
    )
";
$res_tp = mysqli_query($conn, $query_tunggakan_pedagang);
$total_tunggakan_pedagang = $res_tp ? mysqli_num_rows($res_tp) : 0;
$nominal_tunggakan_pedagang = 0;
if($res_tp) while($row = mysqli_fetch_assoc($res_tp)) { $nominal_tunggakan_pedagang += $row['nominal_iuran']; }

// =================== CHART DATA (Dynamic Period) ===================
$period = isset($_GET['period']) ? $_GET['period'] : '1m';
$chart_data = [];
$chart_labels = [];
$chart_inc = [];
$chart_exp = [];

if($period === '1m') {
    $days_back = 29;
    $start_chart = date('Y-m-d', strtotime("-$days_back days"));
    for($i = $days_back; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $chart_data[$d] = ['inc' => 0, 'exp' => 0];
        $chart_labels[] = date('d M', strtotime($d));
    }
    
    $date_expr = "tanggal";
    $group_by = "tanggal";
    $date_expr_m = "pm.tanggal_bayar";
    $date_expr_r = "pr.tanggal_bayar";
    $date_expr_p = "pp.tanggal_bayar";
} else {
    $months_back = 2; // for 3m it's current month + 2 previous = 3
    if($period === '3m') $months_back = 2;
    elseif($period === '6m') $months_back = 5;
    elseif($period === '1y') $months_back = 11;
    elseif($period === '3y') $months_back = 35;
    elseif($period === '5y') $months_back = 59;
    
    $start_chart = date('Y-m-01', strtotime("-$months_back months"));
    
    for($i = $months_back; $i >= 0; $i--) {
        $k = date('Y-m', strtotime("-$i months"));
        $lbl = date('M Y', strtotime("-$i months"));
        $chart_data[$k] = ['inc' => 0, 'exp' => 0];
        $chart_labels[] = $lbl;
    }
    
    $date_expr = "DATE_FORMAT(tanggal, '%Y-%m')";
    $group_by = "DATE_FORMAT(tanggal, '%Y-%m')";
    $date_expr_m = "DATE_FORMAT(pm.tanggal_bayar, '%Y-%m')";
    $date_expr_r = "DATE_FORMAT(pr.tanggal_bayar, '%Y-%m')";
    $date_expr_p = "DATE_FORMAT(pp.tanggal_bayar, '%Y-%m')";
}

// Populate Pengeluaran Chart
$rq = mysqli_query($conn, "SELECT $date_expr as dkey, SUM(nominal) as total FROM pengeluaran WHERE tanggal >= '$start_chart' AND $where_cabang GROUP BY $group_by");
if($rq) while($row = mysqli_fetch_assoc($rq)) { if(isset($chart_data[$row['dkey']])) $chart_data[$row['dkey']]['exp'] += $row['total']; }

// Populate Income Chart (Parkir, Member, Ruko, Pedagang, Lain)
$rq = mysqli_query($conn, "SELECT $date_expr as dkey, SUM(total_bersih) as total FROM pendapatan_parkir WHERE tanggal >= '$start_chart' AND $where_cabang GROUP BY $group_by");
if($rq) while($row = mysqli_fetch_assoc($rq)) { if(isset($chart_data[$row['dkey']])) $chart_data[$row['dkey']]['inc'] += $row['total']; }

$rq = mysqli_query($conn, "SELECT $date_expr_m as dkey, SUM(pm.jumlah_bayar) as total FROM pembayaran_member pm JOIN member m ON pm.member_id = m.id WHERE pm.tanggal_bayar >= '$start_chart' AND $wc_m GROUP BY $date_expr_m");
if($rq) while($row = mysqli_fetch_assoc($rq)) { if(isset($chart_data[$row['dkey']])) $chart_data[$row['dkey']]['inc'] += $row['total']; }

$rq = mysqli_query($conn, "SELECT $date_expr_r as dkey, SUM(pr.jumlah) as total FROM pembayaran_ruko pr JOIN ruko r ON pr.ruko_id = r.id WHERE pr.tanggal_bayar >= '$start_chart' AND $wc_r GROUP BY $date_expr_r");
if($rq) while($row = mysqli_fetch_assoc($rq)) { if(isset($chart_data[$row['dkey']])) $chart_data[$row['dkey']]['inc'] += $row['total']; }

$rq = mysqli_query($conn, "SELECT $date_expr_p as dkey, SUM(pp.jumlah) as total FROM pembayaran_pedagang pp JOIN pedagang p ON pp.pedagang_id = p.id WHERE pp.tanggal_bayar >= '$start_chart' AND $wc_p GROUP BY $date_expr_p");
if($rq) while($row = mysqli_fetch_assoc($rq)) { if(isset($chart_data[$row['dkey']])) $chart_data[$row['dkey']]['inc'] += $row['total']; }

$rq = mysqli_query($conn, "SELECT $date_expr as dkey, SUM(nominal) as total FROM pendapatan_lain WHERE tanggal >= '$start_chart' AND $where_cabang GROUP BY $group_by");
if($rq) while($row = mysqli_fetch_assoc($rq)) { if(isset($chart_data[$row['dkey']])) $chart_data[$row['dkey']]['inc'] += $row['total']; }

foreach($chart_data as $key => $vals) {
    $chart_inc[] = $vals['inc'];
    $chart_exp[] = $vals['exp'];
}

display_flash_message();
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="page-title mb-1">Dashboard Overview</h4>
            <p class="text-muted mb-0">Welcome back, <?= htmlspecialchars($_SESSION['user']['nama']) ?>! Here is what's happening <?= $is_super ? '(Semua Cabang)' : '(Cabang Anda)' ?>.</p>
        </div>
        <?php if($is_super): ?>
        <span class="badge border bg-white text-danger border-danger px-3 py-2 shadow-sm"><i class="fas fa-globe me-1"></i> Mode Super Admin: Semua Cabang</span>
        <?php endif; ?>
    </div>
</div>

<!-- Income & Expense Cards -->
<div class="row mb-4">
    <!-- Kemarin/Hari ini Box -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-uppercase mb-0 text-white-50 fw-bold">Pemasukan Hari Ini</h6>
                    <i class="fas fa-arrow-down fs-4 text-white-50"></i>
                </div>
                <h3 class="mb-0 fw-bold"><?= format_rupiah($total_income_today) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-uppercase mb-0 text-white-50 fw-bold">Pemasukan Bulan Ini</h6>
                    <i class="fas fa-wallet fs-4 text-white-50"></i>
                </div>
                <h3 class="mb-0 fw-bold"><?= format_rupiah($total_income_month) ?></h3>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #ff0844 0%, #ffb199 100%); color: white;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-uppercase mb-0 text-white-50 fw-bold">Pengeluaran Hari Ini</h6>
                    <i class="fas fa-arrow-up fs-4 text-white-50"></i>
                </div>
                <h3 class="mb-0 fw-bold"><?= format_rupiah($pengeluaran_hari_ini) ?></h3>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #f83600 0%, #f9d423 100%); color: white;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-uppercase mb-0 text-white-50 fw-bold">Pengeluaran Bulan Ini</h6>
                    <i class="fas fa-file-invoice-dollar fs-4 text-white-50"></i>
                </div>
                <h3 class="mb-0 fw-bold"><?= format_rupiah($pengeluaran_bulan_ini) ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Chart Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Grafik Pemasukan vs Pengeluaran</h6>
                <form method="GET" class="mb-0">
                    <select name="period" class="form-select form-select-sm" style="width: auto; min-width: 150px;" onchange="this.form.submit()">
                        <option value="1m" <?= $period == '1m' ? 'selected' : '' ?>>30 Hari Terakhir</option>
                        <option value="3m" <?= $period == '3m' ? 'selected' : '' ?>>3 Bulan Terakhir</option>
                        <option value="6m" <?= $period == '6m' ? 'selected' : '' ?>>6 Bulan Terakhir</option>
                        <option value="1y" <?= $period == '1y' ? 'selected' : '' ?>>1 Tahun Terakhir</option>
                        <option value="3y" <?= $period == '3y' ? 'selected' : '' ?>>3 Tahun Terakhir</option>
                        <option value="5y" <?= $period == '5y' ? 'selected' : '' ?>>5 Tahun Terakhir</option>
                    </select>
                </form>
            </div>
            <div class="card-body">
                <canvas id="financeChart" height="90"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Arrears Cards -->
<h5 class="fw-bold mb-3 text-secondary">Status Tunggakan & Keterlambatan</h5>
<div class="row mb-4">
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-warning shadow-sm h-100 py-2 border-start border-4 border-warning">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs fw-bold text-warning text-uppercase mb-1">
                            Member Terlambat</div>
                        <div class="h5 mb-0 fw-bold text-gray-800"><?= $total_tunggakan_member ?> Member</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-black-50" style="opacity: 0.2"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-danger shadow-sm h-100 py-2 border-start border-4 border-danger">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs fw-bold text-danger text-uppercase mb-1">
                            Ruko Terlambat</div>
                        <div class="h5 mb-0 fw-bold text-gray-800"><?= $total_tunggakan_ruko ?> Ruko</div>
                        <div class="small fw-bold text-danger mt-1"><?= format_rupiah($nominal_tunggakan_ruko) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-store fa-2x text-black-50" style="opacity: 0.2"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-danger shadow-sm h-100 py-2 border-start border-4 border-danger">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs fw-bold text-danger text-uppercase mb-1">
                            Pedagang Terlambat</div>
                        <div class="h5 mb-0 fw-bold text-gray-800"><?= $total_tunggakan_pedagang ?> Pedagang</div>
                        <div class="small fw-bold text-danger mt-1"><?= format_rupiah($nominal_tunggakan_pedagang) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-utensils fa-2x text-black-50" style="opacity: 0.2"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex flex-row justify-content-between align-items-center py-3">
                <h6 class="m-0 font-weight-bold text-primary">Notifikasi Jatuh Tempo Mendatang (Maks 3 Hari Kebulan Depan/Sisa Bulan Ini)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Kategori</th>
                                <th>Nama</th>
                                <th>Tanggal Jatuh Tempo</th>
                                <th>Status Bayar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $target_hari = $hari_ini + 3;
                            $upcoming_query = "
                                SELECT 'Member' as kategori, nama, tanggal_jatuh_tempo FROM member WHERE status='Aktif' AND tanggal_jatuh_tempo BETWEEN $hari_ini AND $target_hari AND " . str_replace('cabang_id', 'cabang_id', $where_cabang) . "
                                UNION ALL
                                SELECT 'Ruko' as kategori, nama_ruko as nama, tanggal_jatuh_tempo FROM ruko WHERE status='Aktif' AND tanggal_jatuh_tempo BETWEEN $hari_ini AND $target_hari AND " . str_replace('cabang_id', 'cabang_id', $where_cabang) . "
                                UNION ALL
                                SELECT 'Pedagang' as kategori, nama, tanggal_jatuh_tempo FROM pedagang WHERE status='Aktif' AND tanggal_jatuh_tempo BETWEEN $hari_ini AND $target_hari AND " . str_replace('cabang_id', 'cabang_id', $where_cabang) . "
                                ORDER BY tanggal_jatuh_tempo ASC
                                LIMIT 10
                            ";
                            $res_up = mysqli_query($conn, $upcoming_query);
                            if($res_up && mysqli_num_rows($res_up) > 0) {
                                while($row = mysqli_fetch_assoc($res_up)) {
                                    echo "<tr>";
                                    echo "<td class='ps-4'><span class='badge bg-info text-dark'>{$row['kategori']}</span></td>";
                                    echo "<td><strong>" . htmlspecialchars($row['nama']) . "</strong></td>";
                                    echo "<td>Tanggal {$row['tanggal_jatuh_tempo']}</td>";
                                    echo "<td><span class='badge bg-warning text-dark'>Belum Bayar</span></td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='4' class='text-center text-muted py-4'>Tidak ada tagihan mendesak dalam waktu dekat.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Plugin -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var ctx = document.getElementById('financeChart').getContext('2d');
    var financeChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [
                {
                    label: 'Pemasukan (Rp)',
                    data: <?= json_encode($chart_inc) ?>,
                    borderColor: '#1cc88a',
                    backgroundColor: 'rgba(28, 200, 138, 0.1)',
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: '#1cc88a',
                    fill: true,
                    tension: 0.3
                },
                {
                    label: 'Pengeluaran (Rp)',
                    data: <?= json_encode($chart_exp) ?>,
                    borderColor: '#e74a3b',
                    backgroundColor: 'rgba(231, 74, 59, 0.1)',
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: '#e74a3b',
                    fill: true,
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) { label += ': '; }
                            if (context.parsed.y !== null) {
                                label += new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(context.parsed.y);
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value, index, values) {
                            if(value >= 1000000) return 'Rp ' + (value / 1000000) + ' Jt';
                            if(value >= 1000) return 'Rp ' + (value / 1000) + ' Rb';
                            return 'Rp ' + value;
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>
