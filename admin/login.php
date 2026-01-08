<?php
// admin/login.php - FIX: TĂNG THỜI GIAN ĐĂNG NHẬP LÊN 1 THÁNG (30 NGÀY)

// 1. Cấu hình Session 30 ngày (Phải đặt trước session_start)
ini_set('session.gc_maxlifetime', 2592000);
session_set_cookie_params(2592000);

session_start();
require_once '../includes/config.php';

// Nếu đã đăng nhập thì chuyển vào trong luôn
if (isset($_SESSION['is_admin_logged_in']) && $_SESSION['is_admin_logged_in'] === true) {
    header("Location: index.php");
    exit;
}

$error = '';

if (isset($_POST['btn_login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Truy vấn
    try {
        $stmt = $conn->prepare("SELECT * FROM admins WHERE username = :u");
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Đăng nhập thành công -> Lưu Session
            $_SESSION['is_admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_user'] = $user['username'];

            // Lưu quyền hạn và Prefix
            $_SESSION['role'] = $user['role'];     // 1: Boss, 0: QTV
            $_SESSION['prefix'] = $user['prefix']; // Mã riêng

            header("Location: index.php");
            exit;
        } else {
            $error = 'Sai tài khoản hoặc mật khẩu!';
        }
    } catch (PDOException $e) {
        $error = "Lỗi kết nối: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập Admin</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Icon & Font -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">

    <style>
    body {
        background-color: #f0f2f5;
        /* Xanh Facebook nhạt */
        font-family: 'Manrope', sans-serif;
        height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .login-card {
        background: #ffffff;
        padding: 40px;
        border-radius: 12px;
        width: 100%;
        max-width: 400px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        /* Bóng kiểu FB */
        border: none;
    }

    .brand-text {
        font-weight: 800;
        color: #1877F2;
        /* Xanh FB */
        font-size: 28px;
        text-align: center;
        margin-bottom: 20px;
        letter-spacing: 0.5px;
    }

    .form-label {
        font-size: 14px;
        font-weight: 600;
        color: #050505;
        margin-bottom: 6px;
    }

    .form-control {
        background: #fff;
        border: 1px solid #dddfe2;
        color: #1c1e21;
        padding: 14px 16px;
        border-radius: 6px;
        font-size: 16px;
        transition: all 0.2s;
    }

    .form-control:focus {
        background: #fff;
        border-color: #1877F2;
        box-shadow: 0 0 0 2px rgba(24, 119, 242, 0.2);
    }

    /* Nút đăng nhập */
    .btn-login {
        background: #1877F2;
        color: #fff;
        font-weight: 700;
        font-size: 18px;
        width: 100%;
        padding: 12px;
        border: none;
        border-radius: 6px;
        margin-top: 15px;
        transition: 0.2s;
    }

    .btn-login:hover {
        background: #166fe5;
        color: #fff;
    }

    /* Icon mắt (Show/Hide) */
    .password-wrapper {
        position: relative;
    }

    .toggle-password {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #606770;
        cursor: pointer;
        font-size: 20px;
    }

    .toggle-password:hover {
        color: #1877F2;
    }

    .back-link {
        text-align: center;
        margin-top: 20px;
        border-top: 1px solid #dddfe2;
        padding-top: 20px;
    }

    .back-link a {
        text-decoration: none;
        color: #606770;
        font-size: 14px;
        font-weight: 600;
    }

    .back-link a:hover {
        color: #1877F2;
        text-decoration: underline;
    }
    </style>
</head>

<body>

    <div class="login-card">
        <div class="brand-text">
            <i class="ph-fill ph-heart"></i> ADMIN PANEL
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2 text-center text-sm mb-3 rounded-2 fw-bold" style="font-size: 13px;">
            <?= $error ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <input type="text" name="username" class="form-control" required placeholder="Tài khoản">
            </div>

            <div class="mb-3">
                <div class="password-wrapper">
                    <input type="password" name="password" id="passInput" class="form-control" required
                        placeholder="Mật khẩu" style="padding-right: 45px;">
                    <!-- ICON MẮT -->
                    <i class="ph-bold ph-eye-slash toggle-password" id="toggleIcon" onclick="togglePass()"></i>
                </div>
            </div>

            <button type="submit" name="btn_login" class="btn btn-login">Đăng nhập</button>
        </form>

        <div class="back-link">
            <a href="../index.php">Quay lại Shop</a>
        </div>
    </div>

    <script>
    function togglePass() {
        const input = document.getElementById('passInput');
        const icon = document.getElementById('toggleIcon');

        if (input.type === "password") {
            input.type = "text";
            icon.classList.remove('ph-eye-slash');
            icon.classList.add('ph-eye');
        } else {
            input.type = "password";
            icon.classList.remove('ph-eye');
            icon.classList.add('ph-eye-slash');
        }
    }
    </script>

</body>

</html>