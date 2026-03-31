-- Database Schema Dump (Auto Generated)
SET FOREIGN_KEY_CHECKS = 0;

-- Table structure for table `absensi`
DROP TABLE IF EXISTS `absensi`;
CREATE TABLE `absensi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `karyawan_id` int(11) NOT NULL,
  `bulan` int(11) NOT NULL,
  `tahun` int(11) NOT NULL,
  `hadir` int(11) DEFAULT 0,
  `izin` int(11) DEFAULT 0,
  `sakit` int(11) DEFAULT 0,
  `alpha` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `karyawan_id` (`karyawan_id`,`bulan`,`tahun`),
  CONSTRAINT `absensi_ibfk_1` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `cabang`
DROP TABLE IF EXISTS `cabang`;
CREATE TABLE `cabang` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_cabang` varchar(100) NOT NULL,
  `alamat` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `karyawan`
DROP TABLE IF EXISTS `karyawan`;
CREATE TABLE `karyawan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nik` varchar(50) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `divisi` varchar(50) DEFAULT NULL,
  `jabatan` varchar(50) DEFAULT NULL,
  `tanggal_masuk` date DEFAULT NULL,
  `status` enum('Aktif','Nonaktif') DEFAULT 'Aktif',
  `cabang_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `alamat` text DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `gaji_pokok` decimal(10,2) DEFAULT 0.00,
  `tunjangan_jabatan` decimal(10,2) DEFAULT 0.00,
  `tunjangan_makan` decimal(10,2) DEFAULT 0.00,
  `tunjangan_transport` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `cabang_id` (`cabang_id`),
  CONSTRAINT `karyawan_ibfk_1` FOREIGN KEY (`cabang_id`) REFERENCES `cabang` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `kasbon`
DROP TABLE IF EXISTS `kasbon`;
CREATE TABLE `kasbon` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `karyawan_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `nominal` decimal(10,2) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `status_lunas` enum('Belum Lunas','Lunas') DEFAULT 'Belum Lunas',
  `pengeluaran_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `karyawan_id` (`karyawan_id`),
  CONSTRAINT `kasbon_ibfk_1` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `kategori`
DROP TABLE IF EXISTS `kategori`;
CREATE TABLE `kategori` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_kategori` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `kategori_pengeluaran`
DROP TABLE IF EXISTS `kategori_pengeluaran`;
CREATE TABLE `kategori_pengeluaran` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_kategori` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `member`
DROP TABLE IF EXISTS `member`;
CREATE TABLE `member` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  `jenis_member` enum('Instansi','Perorangan') NOT NULL,
  `tipe_pembayaran` enum('Kolektif','Individual') NOT NULL,
  `nominal_iuran` decimal(10,2) NOT NULL,
  `tanggal_jatuh_tempo` int(11) NOT NULL,
  `catatan` text DEFAULT NULL,
  `status` enum('Aktif','Nonaktif') DEFAULT 'Aktif',
  `cabang_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `cabang_id` (`cabang_id`),
  CONSTRAINT `member_ibfk_1` FOREIGN KEY (`cabang_id`) REFERENCES `cabang` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `member_detail`
DROP TABLE IF EXISTS `member_detail`;
CREATE TABLE `member_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `nama_personil` varchar(100) NOT NULL,
  `kendaraan` varchar(50) DEFAULT NULL,
  `nopol` varchar(20) DEFAULT NULL,
  `nominal_iuran` decimal(10,2) DEFAULT NULL,
  `status` enum('Aktif','Tidak Aktif') NOT NULL DEFAULT 'Aktif',
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  CONSTRAINT `member_detail_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `member` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `ojek_online`
DROP TABLE IF EXISTS `ojek_online`;
CREATE TABLE `ojek_online` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipe` enum('GRAB','GOJEK') NOT NULL,
  `id_driver` varchar(100) NOT NULL,
  `nama_driver` varchar(100) NOT NULL,
  `kendaraan` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `pedagang`
DROP TABLE IF EXISTS `pedagang`;
CREATE TABLE `pedagang` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  `kategori` varchar(50) DEFAULT NULL,
  `nominal_iuran` decimal(10,2) NOT NULL,
  `tanggal_jatuh_tempo` int(11) NOT NULL,
  `status` enum('Aktif','Nonaktif') DEFAULT 'Aktif',
  `cabang_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `cabang_id` (`cabang_id`),
  CONSTRAINT `pedagang_ibfk_1` FOREIGN KEY (`cabang_id`) REFERENCES `cabang` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `pembayaran_member`
DROP TABLE IF EXISTS `pembayaran_member`;
CREATE TABLE `pembayaran_member` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `member_detail_id` int(11) DEFAULT NULL,
  `bulan` int(11) NOT NULL,
  `tahun` int(11) NOT NULL,
  `jumlah_bayar` decimal(10,2) NOT NULL,
  `tanggal_bayar` date NOT NULL,
  `metode_bayar` varchar(50) DEFAULT 'Tunai',
  `keterangan` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `member_detail_id` (`member_detail_id`),
  CONSTRAINT `pemb_member_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `member` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pemb_member_ibfk_2` FOREIGN KEY (`member_detail_id`) REFERENCES `member_detail` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `pembayaran_pedagang`
DROP TABLE IF EXISTS `pembayaran_pedagang`;
CREATE TABLE `pembayaran_pedagang` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pedagang_id` int(11) NOT NULL,
  `bulan` int(11) NOT NULL,
  `tahun` int(11) NOT NULL,
  `jumlah` decimal(10,2) NOT NULL,
  `tanggal_bayar` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `pedagang_id` (`pedagang_id`),
  CONSTRAINT `pemb_pedagang_ibfk_1` FOREIGN KEY (`pedagang_id`) REFERENCES `pedagang` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `pembayaran_ruko`
DROP TABLE IF EXISTS `pembayaran_ruko`;
CREATE TABLE `pembayaran_ruko` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ruko_id` int(11) NOT NULL,
  `bulan` int(11) NOT NULL,
  `tahun` int(11) NOT NULL,
  `jumlah` decimal(10,2) NOT NULL,
  `tanggal_bayar` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ruko_id` (`ruko_id`),
  CONSTRAINT `pemb_ruko_ibfk_1` FOREIGN KEY (`ruko_id`) REFERENCES `ruko` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `pendapatan_lain`
DROP TABLE IF EXISTS `pendapatan_lain`;
CREATE TABLE `pendapatan_lain` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `subkategori_id` int(11) NOT NULL,
  `nominal` decimal(10,2) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `cabang_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `subkategori_id` (`subkategori_id`),
  KEY `cabang_id` (`cabang_id`),
  CONSTRAINT `fk_pendapatan_lain_subkategori_id` FOREIGN KEY (`subkategori_id`) REFERENCES `subkategori` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pend_lain_ibfk_2` FOREIGN KEY (`cabang_id`) REFERENCES `cabang` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `pendapatan_parkir`
DROP TABLE IF EXISTS `pendapatan_parkir`;
CREATE TABLE `pendapatan_parkir` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `shift` int(11) NOT NULL,
  `qris` decimal(10,2) DEFAULT 0.00,
  `non_tunai` decimal(10,2) DEFAULT 0.00,
  `tunai` decimal(10,2) DEFAULT 0.00,
  `bermasalah` decimal(10,2) DEFAULT 0.00,
  `keterangan` text DEFAULT NULL,
  `cabang_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_pendapatan` decimal(10,2) GENERATED ALWAYS AS (`qris` + `non_tunai` + `tunai`) VIRTUAL,
  `total_bersih` decimal(10,2) GENERATED ALWAYS AS (`qris` + `non_tunai` + `tunai` - `bermasalah`) VIRTUAL,
  PRIMARY KEY (`id`),
  KEY `cabang_id` (`cabang_id`),
  CONSTRAINT `pend_parkir_ibfk_1` FOREIGN KEY (`cabang_id`) REFERENCES `cabang` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `pengeluaran`
DROP TABLE IF EXISTS `pengeluaran`;
CREATE TABLE `pengeluaran` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `kategori_id` int(11) NOT NULL,
  `nominal` decimal(10,2) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `cabang_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `kategori_id` (`kategori_id`),
  KEY `cabang_id` (`cabang_id`),
  CONSTRAINT `fk_pengeluaran_kategori_id` FOREIGN KEY (`kategori_id`) REFERENCES `kategori_pengeluaran` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pengeluaran_ibfk_2` FOREIGN KEY (`cabang_id`) REFERENCES `cabang` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `penggajian`
DROP TABLE IF EXISTS `penggajian`;
CREATE TABLE `penggajian` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `karyawan_id` int(11) NOT NULL,
  `bulan` int(11) NOT NULL,
  `tahun` int(11) NOT NULL,
  `gaji_pokok` decimal(10,2) NOT NULL,
  `tunjangan_jabatan` decimal(10,2) DEFAULT 0.00,
  `tunjangan_makan` decimal(10,2) DEFAULT 0.00,
  `tunjangan_transport` decimal(10,2) DEFAULT 0.00,
  `potongan_kasbon` decimal(10,2) DEFAULT 0.00,
  `potongan_pinjaman` decimal(10,2) DEFAULT 0.00,
  `potongan_alpha` decimal(10,2) DEFAULT 0.00,
  `total_gaji_bersih` decimal(10,2) NOT NULL,
  `tanggal_cair` date NOT NULL,
  `pengeluaran_id` int(11) DEFAULT NULL,
  `history_potongan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `karyawan_id` (`karyawan_id`,`bulan`,`tahun`),
  CONSTRAINT `penggajian_ibfk_1` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `pinjaman`
DROP TABLE IF EXISTS `pinjaman`;
CREATE TABLE `pinjaman` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `karyawan_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `nominal_total` decimal(10,2) NOT NULL,
  `tenor_bulan` int(11) NOT NULL,
  `cicilan_per_bulan` decimal(10,2) NOT NULL,
  `sisa_pinjaman` decimal(10,2) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `status_lunas` enum('Belum Lunas','Lunas') DEFAULT 'Belum Lunas',
  `pengeluaran_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `karyawan_id` (`karyawan_id`),
  CONSTRAINT `pinjaman_ibfk_1` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `ruko`
DROP TABLE IF EXISTS `ruko`;
CREATE TABLE `ruko` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_ruko` varchar(100) NOT NULL,
  `pemilik` varchar(100) DEFAULT NULL,
  `nominal_iuran` decimal(10,2) NOT NULL,
  `tanggal_jatuh_tempo` int(11) NOT NULL,
  `status` enum('Aktif','Nonaktif') DEFAULT 'Aktif',
  `cabang_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `cabang_id` (`cabang_id`),
  CONSTRAINT `ruko_ibfk_1` FOREIGN KEY (`cabang_id`) REFERENCES `cabang` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `subkategori`
DROP TABLE IF EXISTS `subkategori`;
CREATE TABLE `subkategori` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kategori_id` int(11) NOT NULL,
  `nama_subkategori` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `kategori_id` (`kategori_id`),
  CONSTRAINT `subkategori_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `kategori` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `users`
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Super Admin','Admin','Bendahara','HRD') NOT NULL,
  `cabang_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `akses_cabang` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `cabang_id` (`cabang_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`cabang_id`) REFERENCES `cabang` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
