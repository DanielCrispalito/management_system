<?php
require_once __DIR__ . '/config/database.php';

$q = "ALTER TABLE member MODIFY jenis_member enum('Perusahaan','Individual') NOT NULL";

if (mysqli_query($conn, $q)) {
    echo "<h3>✅ Berhasil!</h3>";
    echo "<p>Kolom <strong>jenis_member</strong> pada tabel <strong>member</strong> telah diperbarui.</p>";
    echo "<p>Error 'data truncated' saat menambah member baru sudah diperbaiki. Silakan coba tambahkan input member kembali.</p>";
} else {
    echo "<h3>❌ Gagal update database:</h3>";
    echo "<p>" . mysqli_error($conn) . "</p>";
}
?>
