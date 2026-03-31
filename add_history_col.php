<?php
require_once __DIR__ . '/config/database.php';
$check = mysqli_query($conn, "SHOW COLUMNS FROM penggajian LIKE 'history_potongan'");
if(mysqli_num_rows($check) == 0) {
    mysqli_query($conn, "ALTER TABLE penggajian ADD COLUMN history_potongan TEXT NULL NULL AFTER pengeluaran_id");
    echo "Column history_potongan added.";
} else {
    echo "Column already exists.";
}
