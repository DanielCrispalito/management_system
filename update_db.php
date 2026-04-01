<?php
require_once __DIR__ . '/config/database.php';

$q = "ALTER TABLE users MODIFY cabang_id int(11) DEFAULT NULL";

if (mysqli_query($conn, $q)) {
    echo "<h3>✅ Berhasil!</h3>";
    echo "<p>Kolom <strong>cabang_id</strong> pada tabel <strong>users</strong> sekarang dapat bernilai NULL.</p>";
    echo "<p>Error saat mendaftarkan user Super Admin sudah diperbaiki. Silakan coba daftarkan kembali.</p>";
} else {
    echo "<h3>❌ Gagal update database:</h3>";
    echo "<p>" . mysqli_error($conn) . "</p>";
}
?>
