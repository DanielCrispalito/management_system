<!-- Sidebar  -->
<nav id="sidebar">
    <div class="sidebar-header">
        <h3>PJR PARKING</h3>
    </div>

    <ul class="list-unstyled components">
        <p>HOME</p>
        <li class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
            <a href="/pjr_parking/index.php"><i class="fas fa-home"></i> Dashboard</a>
        </li>
        
        <?php if($_SESSION['user']['role'] === 'Super Admin'): ?>
        <p class="text-warning">SUPERVISI</p>
        <li>
            <a href="#supervisiMenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle text-warning">
                <i class="fas fa-crown"></i> Super Admin
            </a>
            <ul class="collapse list-unstyled <?= strpos($_SERVER['REQUEST_URI'], '/modules/cabang') !== false || strpos($_SERVER['REQUEST_URI'], '/modules/users') !== false ? 'show' : '' ?>" id="supervisiMenu">
                <li><a href="/pjr_parking/modules/cabang/index.php">Manajemen Cabang</a></li>
                <li><a href="/pjr_parking/modules/users/index.php">Manajemen Petugas (Users)</a></li>
            </ul>
        </li>
        <?php endif; ?>
        
        <p>SISTEM TRANSAKSI</p>
        <?php if(in_array($_SESSION['user']['role'], ['Super Admin', 'Admin', 'Bendahara'])): ?>
        <li>
            <a href="#pembayaranMenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-money-bill-wave"></i> Pembayaran
            </a>
            <ul class="collapse list-unstyled <?= strpos($_SERVER['REQUEST_URI'], '/pembayaran/') !== false ? 'show' : '' ?>" id="pembayaranMenu">
                <li><a href="/pjr_parking/modules/pembayaran/member.php">Pembayaran Member</a></li>
                <li><a href="/pjr_parking/modules/pembayaran/ruko.php">Pembayaran Ruko</a></li>
                <li><a href="/pjr_parking/modules/pembayaran/pedagang.php">Pembayaran Pedagang</a></li>
            </ul>
        </li>
        <li>
            <a href="#pemasukanMenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-wallet"></i> Pemasukan
            </a>
            <ul class="collapse list-unstyled <?= strpos($_SERVER['REQUEST_URI'], '/pemasukan/') !== false ? 'show' : '' ?>" id="pemasukanMenu">
                <li><a href="/pjr_parking/modules/pemasukan/parkir.php">Pendapatan Parkir</a></li>
                <li><a href="/pjr_parking/modules/pemasukan/lain.php">Pendapatan Lain</a></li>
            </ul>
        </li>
        <li>
            <a href="#pengeluaranMenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-file-invoice-dollar"></i> Pengeluaran
            </a>
            <ul class="collapse list-unstyled <?= strpos($_SERVER['REQUEST_URI'], '/pengeluaran/') !== false ? 'show' : '' ?>" id="pengeluaranMenu">
                <li><a href="/pjr_parking/modules/pengeluaran/index.php">Daftar Pengeluaran</a></li>
                <li><a href="/pjr_parking/modules/pengeluaran/kategori.php">Kategori Pengeluaran</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <?php if(in_array($_SESSION['user']['role'], ['Super Admin', 'Admin', 'HRD'])): ?>
        <p>HRIS & PAYROLL</p>
        <li>
            <a href="#hrisMenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-users-cog"></i> Personalia
            </a>
            <ul class="collapse list-unstyled <?= strpos($_SERVER['REQUEST_URI'], '/modules/karyawan') !== false || strpos($_SERVER['REQUEST_URI'], '/modules/penggajian') !== false ? 'show' : '' ?>" id="hrisMenu">
                <li><a href="/pjr_parking/modules/karyawan/index.php">Database Karyawan</a></li>
                <li><a href="/pjr_parking/modules/karyawan/absensi.php">Absensi Karyawan</a></li>
                <li><a href="/pjr_parking/modules/karyawan/pinjaman.php">Kasbon & Pinjaman</a></li>
                <li><a href="/pjr_parking/modules/penggajian/index.php">Penggajian Bulanan</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <?php if(in_array($_SESSION['user']['role'], ['Super Admin', 'Admin', 'HRD', 'Bendahara'])): ?>
        <p>SISTEM INFORMASI</p>
        <li>
            <a href="#masterDataMenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-database"></i> Master Data
            </a>
            <ul class="collapse list-unstyled <?= strpos($_SERVER['REQUEST_URI'], '/modules/member') !== false || strpos($_SERVER['REQUEST_URI'], '/modules/ruko') !== false || strpos($_SERVER['REQUEST_URI'], '/modules/pedagang') !== false || strpos($_SERVER['REQUEST_URI'], '/modules/ojek') !== false ? 'show' : '' ?>" id="masterDataMenu">
                <?php if(in_array($_SESSION['user']['role'], ['Super Admin', 'Admin', 'HRD'])): ?>
                <li><a href="/pjr_parking/modules/member/index.php">Data Member</a></li>
                <?php endif; ?>
                <li><a href="/pjr_parking/modules/ruko/index.php">Data Ruko</a></li>
                <li><a href="/pjr_parking/modules/pedagang/index.php">Data Pedagang</a></li>
                <li><a href="/pjr_parking/modules/ojek/index.php">Data Ojek Online</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <?php if(in_array($_SESSION['user']['role'], ['Super Admin', 'Admin', 'Bendahara'])): ?>
        <p>LAPORAN</p>
        <li>
            <a href="#laporanMenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-chart-bar"></i> Laporan
            </a>
            <ul class="collapse list-unstyled <?= strpos($_SERVER['REQUEST_URI'], '/laporan/') !== false ? 'show' : '' ?>" id="laporanMenu">
                <li><a href="/pjr_parking/modules/laporan/harian.php" >Laporan Kas Harian</a></li>
                <li><a href="/pjr_parking/modules/laporan/pembayaran.php">Laporan Pembayaran</a></li>
                <li><a href="/pjr_parking/modules/laporan/tunggakan.php">Laporan Tunggakan</a></li>
                <li><a href="/pjr_parking/modules/laporan/jatuh_tempo.php">Laporan Jatuh Tempo</a></li>
                <li><a href="/pjr_parking/modules/laporan/pemasukan.php">Laporan Laba/Rugi</a></li>
            </ul>
        </li>
        <?php endif; ?>
    </ul>
</nav>

<!-- Page Content  -->
<div id="content">
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <div class="d-flex align-items-center">
                <span class="fs-5 fw-bold ms-2 text-dark">Sistem Manajemen</span>
            </div>
            
            <div class="d-flex align-items-center">
                <!-- Branch Switcher for Multi-Branch Admins -->
                <?php if(isset($_SESSION['user']['akses_cabang_array']) && count($_SESSION['user']['akses_cabang_array']) > 0): 
                    $all_my_ids = $_SESSION['user']['akses_cabang_array'];
                    $all_my_ids[] = $_SESSION['user']['primary_cabang_id'];
                    $ids_sql = implode(',', array_map('intval', $all_my_ids));
                    $q_my_cabs = mysqli_query($conn, "SELECT id, nama_cabang FROM cabang WHERE id IN ($ids_sql) ORDER BY nama_cabang ASC");
                ?>
                <div class="dropdown me-3">
                    <button class="btn btn-outline-primary dropdown-toggle bg-white fw-bold shadow-sm" type="button" id="branchMenu" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-map-marker-alt text-danger me-1"></i> 
                        <?= htmlspecialchars($_SESSION['user']['nama_cabang'] ?? 'Cabang Aktif') ?>
                    </button>
                    <ul class="dropdown-menu shadow border-0" aria-labelledby="branchMenu">
                        <li><h6 class="dropdown-header text-muted">Akses Multi-Cabang</h6></li>
                        <?php while($cab = mysqli_fetch_assoc($q_my_cabs)): ?>
                        <li>
                            <form method="POST" action="/pjr_parking/switch_cabang.php">
                                <input type="hidden" name="switch_cabang_id" value="<?= $cab['id'] ?>">
                                <button type="submit" class="dropdown-item <?= ($cab['id'] == $_SESSION['user']['cabang_id']) ? 'active bg-primary text-white' : '' ?>">
                                    <?= htmlspecialchars($cab['nama_cabang']) ?>
                                </button>
                            </form>
                        </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle bg-transparent border-0 fw-semibold" type="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle fs-5 me-2 align-middle text-primary"></i>
                        <?= htmlspecialchars($_SESSION['user']['nama'] ?? 'User') ?>
                        <span class="badge bg-secondary ms-1 fw-normal"><?= htmlspecialchars($_SESSION['user']['role'] ?? '') ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="userMenu">
                        <li><a class="dropdown-item text-danger" href="/pjr_parking/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    <div class="content-wrapper">
