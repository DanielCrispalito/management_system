<?php

function format_rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function get_status_pembayaran($tanggal_jatuh_tempo, $status_bayar) {
    if ($status_bayar == 'Sudah Bayar') {
        return '<span class="badge bg-success">Sudah Bayar</span>';
    }

    $hari_ini = (int)date('d');
    if ($hari_ini > $tanggal_jatuh_tempo) {
        return '<span class="badge bg-danger">Terlambat</span>';
    }

    return '<span class="badge bg-warning text-dark">Belum Bayar</span>';
}

function redirect($url) {
    echo "<script>window.location.href='$url';</script>";
    exit;
}

function set_flash_message($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function display_flash_message() {
    if (isset($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'] === 'success' ? 'success' : 'danger';
        $message = $_SESSION['flash']['message'];
        echo "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
                {$message}
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
        unset($_SESSION['flash']);
    }
}

function sanitize($conn, $string) {
    return mysqli_real_escape_string($conn, htmlspecialchars(strip_tags($string)));
}

function check_role($allowed_roles) {
    if (!isset($_SESSION['user'])) {
        redirect('/login.php');
    }
    
    $user_role = $_SESSION['user']['role'];
    if (!in_array($user_role, $allowed_roles) && $user_role !== 'Admin' && $user_role !== 'Super Admin') {
        set_flash_message('error', 'Anda tidak memiliki akses ke halaman tersebut.');
        redirect('/index.php');
    }
}
?>
