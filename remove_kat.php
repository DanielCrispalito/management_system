<?php
require_once __DIR__ . '/config/database.php';
$dsub = mysqli_query($conn, "DELETE FROM subkategori WHERE nama_subkategori IN ('Iuran Member', 'Iuran Ruko', 'Iuran Pedagang')");
echo "Deleted subkategori: " . mysqli_affected_rows($conn);
