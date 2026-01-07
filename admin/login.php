<?php
// admin/login.php - LIGHT MODE + SHOW/HIDE PASSWORD
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
            $_SESSION['is_admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_user'] = $user['username'];

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
        background-color: #f3f4f6;
        /* Nền xám sáng */
        font-family: 'Manrope', sans-serif;
        height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .login-card {
        background: #ffffff;
        padding: 40px;
        border-radius: 20px;
        width: 100%;
        max-width: 420px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        /* Bóng đổ nhẹ */
        border: 1px solid #e5e7eb;
    }

    .brand-text {
        font-weight: 800;
        color: #111827;
        font-size: 24px;
        text-align: center;
        margin-bottom: 30px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .form-label {
        font-size: 13px;
        font-weight: 700;
        color: #4b5563;
        margin-bottom: 8px;
        text-transform: uppercase;
    }

    .form-control {
        background: #f9fafb;
        border: 1px solid #d1d5db;
        color: #111827;
        padding: 14px 16px;
        border-radius: 12px;
        font-size: 15px;
        transition: all 0.2s;
    }

    .form-control:focus {
        background: #fff;
        border-color: #f59e0b;
        box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.1);
    }

    /* Nút đăng nhập */
    .btn-login {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: #fff;
        font-weight: 700;
        width: 100%;
        padding: 14px;
        border: none;
        border-radius: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 10px;
        transition: 0.3s;
        box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3);
    }

    .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(245, 158, 11, 0.4);
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
        color: #9ca3af;
        cursor: pointer;
        font-size: 20px;
        transition: 0.2s;
    }

    .toggle-password:hover {
        color: #f59e0b;
    }

    .back-link {
        text-align: center;
        margin-top: 25px;
    }

    .back-link a {
        text-decoration: none;
        color: #6b7280;
        font-size: 13px;
        font-weight: 600;
        transition: 0.2s;
    }

    .back-link a:hover {
        color: #f59e0b;
    }
    </style>
</head>

<body>

    <div class="login-card">
        <div class="brand-text">
            <i class="ph-fill ph-hexagon text-warning"></i> ADMIN PANEL
        </div>

        <?php if ($error): ?>
        <div
            class="alert alert-danger py-2 text-center text-sm mb-4 rounded-3 border-0 bg-danger bg-opacity-10 text-danger fw-bold">
            <i class="ph-bold ph-warning-circle me-1"></i> <?= $error ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-4">
                <label class="form-label">Tài khoản</label>
                <input type="text" name="username" class="form-control" required placeholder="Nhập tên đăng nhập...">
            </div>

            <div class="mb-4">
                <label class="form-label">Mật khẩu</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="passInput" class="form-control" required
                        placeholder="Nhập mật khẩu..." style="padding-right: 45px;">

                    <!-- ICON MẮT -->
                    <i class="ph-bold ph-eye-slash toggle-password" id="toggleIcon" onclick="togglePass()"></i>
                </div>
            </div>

            <button type="submit" name="btn_login" class="btn btn-login">ĐĂNG NHẬP NGAY</button>
        </form>

        <div class="back-link">
            <a href="../index.php"><i class="ph-bold ph-arrow-left"></i> Quay lại trang chủ Shop</a>
        </div>
    </div>

    <!-- SCRIPT XỬ LÝ ẨN/HIỆN PASS -->
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