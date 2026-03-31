<?php
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$user = getenv('DB_USER');
$pass = getenv('DB_PASSWORD');
$db   = getenv('DB_NAME');

$conn = mysqli_connect($host, $user, $pass, $db, $port);

if (!$conn) {
    die("Gagal koneksi: " . mysqli_connect_error());
}

echo "✔️ Koneksi berhasil<br>";

// CEK apakah tabel sudah ada
$check = mysqli_query($conn, "SHOW TABLES");

if (mysqli_num_rows($check) > 0) {
    die("⚠️ Database sudah terisi. Setup dibatalkan.");
}

// Load file SQL
$sql_file = __DIR__ . '/database.sql';

if (!file_exists($sql_file)) {
    die("File database.sql tidak ditemukan!");
}

$sql_contents = file_get_contents($sql_file);

// Eksekusi SQL
if (mysqli_multi_query($conn, $sql_contents)) {
    do {
        if ($result = mysqli_store_result($conn)) {
            mysqli_free_result($result);
        }
    } while (mysqli_more_results($conn) && mysqli_next_result($conn));

    echo "<br>✅ Database berhasil di-setup!";
    echo "<br><a href='index.php'>Lanjut ke aplikasi</a>";
} else {
    echo "❌ Error: " . mysqli_error($conn);
}

mysqli_close($conn);
?>