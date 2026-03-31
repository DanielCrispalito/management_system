<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if user is not authenticated
require_once __DIR__ . '/../helpers/functions.php';

if (!isset($_SESSION['user'])) {
    $current_url = $_SERVER['PHP_SELF'];
    if (strpos($current_url, 'login.php') === false) {
        header('Location: /pjr_parking/login.php');
        exit;
    }
}
?>
