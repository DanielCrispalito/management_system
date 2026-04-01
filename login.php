<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Require functions
require_once __DIR__ . '/helpers/functions.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user'])) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/config/database.php';

    $username = sanitize($conn, $_POST['username']);
    $password = md5($_POST['password']);

    $query = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        $nama_cabang = 'Akses Lintas Cabang';
        if (!empty($user['cabang_id'])) {
            $c_n = mysqli_query($conn, "SELECT nama_cabang FROM cabang WHERE id = {$user['cabang_id']}");
            if ($c_n && mysqli_num_rows($c_n) > 0) {
                $nama_cabang = mysqli_fetch_assoc($c_n)['nama_cabang'];
            }
        }

        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'nama' => $user['nama'],
            'role' => $user['role'],
            'primary_cabang_id' => $user['cabang_id'],
            'cabang_id' => $user['cabang_id'],
            'nama_cabang' => $nama_cabang,
            'akses_cabang_array' => $user['akses_cabang'] ? explode(',', $user['akses_cabang']) : []
        ];
        redirect('index.php');
    } else {
        $error = "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PJR Parking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 1rem;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
            padding: 2.5rem;
        }

        .login-card h2 {
            font-weight: 700;
            color: #1e3c72;
            margin-bottom: 0.5rem;
        }

        .login-card p {
            color: #6c757d;
            margin-bottom: 2rem;
        }

        .form-control {
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
        }

        .btn-primary {
            background: #1e3c72;
            border: none;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: #2a5298;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>

    <div class="login-card">
        <div class="text-center">
            <h2>PJR PARKING</h2>
            <p>Sistem Manajemen Perusahaan</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label for="username" class="form-label text-muted fw-semibold">USERNAME</label>
                <input type="text" class="form-control" id="username" name="username" required autofocus>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label text-muted fw-semibold">PASSWORD</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Sign In</button>
        </form>

        <div class="text-center mt-4 text-muted small">
            &copy; <?= date('Y') ?> PJR Parking Management
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>