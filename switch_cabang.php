<?php
session_start();
require_once __DIR__ . '/helpers/functions.php';
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['user'])) {
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['switch_cabang_id'])) {
    $target_id = (int)$_POST['switch_cabang_id'];
    
    $allowed_ids = $_SESSION['user']['akses_cabang_array'] ?? [];
    $allowed_ids[] = $_SESSION['user']['primary_cabang_id'];
    
    // cast allowed to ints just to be absolutely sure
    $allowed_ints = array_map('intval', $allowed_ids);
    
    if (in_array($target_id, $allowed_ints)) {
        $_SESSION['user']['cabang_id'] = $target_id;
        
        $qc = mysqli_query($conn, "SELECT nama_cabang FROM cabang WHERE id = $target_id");
        if($qc && mysqli_num_rows($qc) > 0) {
            $_SESSION['user']['nama_cabang'] = mysqli_fetch_assoc($qc)['nama_cabang'];
        }
        
        set_flash_message('success', 'Berhasil beralih ke ' . $_SESSION['user']['nama_cabang']);
    } else {
        set_flash_message('error', 'Anda tidak memiliki akses ke cabang tersebut.');
    }
}

$referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header("Location: $referer");
exit;
