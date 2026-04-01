<?php
require_once __DIR__ . '/config/database.php';

$q = "ALTER TABLE karyawan MODIFY nik varchar(50) DEFAULT NULL";

if (mysqli_query($conn, $q)) {
    echo "<h3>✅ Berhasil!</h3>";
    echo "<p>Kolom <strong>nik</strong> pada tabel <strong>karyawan</strong> telah diperbarui (sekarang bersifat opsional).</p>";
    echo "<p>Error saat menambah karyawan tanpa NIK sudah diperbaiki.</p>";
} else {
    echo "<h3>❌ Gagal update database:</h3>";
    echo "<p>" . mysqli_error($conn) . "</p>";
}
?>
