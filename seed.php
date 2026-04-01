<?php
require_once __DIR__ . '/config/database.php';

// Memastikan tabel cabang dan users ada
$check = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
if (mysqli_num_rows($check) == 0) {
    die("Tabel users belum ada. Coba jalankan /setup.php terlebih dahulu.");
}

$res = mysqli_query($conn, "SELECT COUNT(*) as total FROM users");
$row = mysqli_fetch_assoc($res);

if ($row['total'] > 0) {
    die("✅ Data admin sudah ada! Silakan langsung login.<br>Bila lupa, biasanya Username: <b>admin</b> dan Password: <b>admin123</b> <br><br><a href='login.php'>Ke Halaman Login</a>");
}

// Tambahkan Cabang Utama
mysqli_query($conn, "INSERT IGNORE INTO cabang (id, nama_cabang, alamat) VALUES (1, 'Cabang Utama', 'Jakarta')");

// Tambahkan User Admin
$pass = md5('admin123');
$insert = mysqli_query($conn, "INSERT IGNORE INTO users (id, nama, username, password, role, cabang_id) VALUES (1, 'Administrator', 'admin', '$pass', 'Super Admin', 1)");

if ($insert) {
    echo "<h1>✅ Berhasil membuat hak akses pertama!</h1>";
    echo "Silakan login menggunakan akun berikut:<br><br>";
    echo "Username : <b>admin</b><br>";
    echo "Password : <b>admin123</b><br><br>";
    echo "<a href='login.php'>Klik di sini untuk Login</a>";
} else {
    echo "Gagal: " . mysqli_error($conn);
}
?>
