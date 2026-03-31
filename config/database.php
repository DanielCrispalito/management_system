<!-- <?php
// $host = "localhost";
// $username = "root";
// $password = "";
// $database = "pjr_parking";
// $port = "3307";

// // Connect using MySQLi Since requested Procedural
// $conn = mysqli_connect($host, $username, $password, $database, $port);

// if (!$conn) {
//     die("Connection failed: " . mysqli_connect_error());
// }
?> -->

<?php
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$user = getenv('DB_USER');
$pass = getenv('DB_PASSWORD');
$db   = getenv('DB_NAME');

$conn = mysqli_connect($host, $user, $pass, $db, $port);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>