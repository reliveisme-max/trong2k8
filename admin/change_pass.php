<?php
// admin/change_pass.php - LIGHT MODE FINAL (NO MENU ICON)
require_once 'auth.php';
require_once '../includes/config.php';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Đổi mật khẩu</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Icon & Font -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <!-- SIDEBAR (DESKTOP) -->
    <aside class="sidebar">
        <div class="brand"><i class="ph-fill ph-hexagon"></i> ADMIN PANEL</div>
        <nav class="d-flex flex-column gap-2">
            <a href="index.php" class="menu-item"><i class="ph-duotone ph-squares-four"></i> Tổng Quan</a>
            <a href="add.php" class="menu-item"><i class="ph-duotone ph-plus-circle"></i> Đăng Acc Mới</a>
            <a href="library.php" class="menu-item"><i class="ph-duotone ph-image"></i> Thư viện ảnh</a>
            <a href="change_pass.php" class="menu-item active"><i class="ph-duotone ph-lock-key"></i> Đổi mật khẩu</a>
            <div class="mt-auto">
                <div class="border-top border-secondary opacity-25 mb-3"></div>
                <a href="logout.php" class="menu-item text-danger fw-bold"><i class="ph-duotone ph-sign-out"></i> Đăng
                    xuất</a>
            </div>
        </nav>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">

        <!-- Header Gọn Gàng (Đã bỏ icon menu thừa) -->
        <div class="d-flex align-items-center mb-4">
            <h4 class="m-0 fw-bold text-dark">Đổi mật khẩu</h4>
        </div>

        <div class="row justify-content-center">
            <div class="col-12 col-md-6">
                <div class="form-card">
                    <form action="process_pass.php" method="POST">

                        <div class="mb-4">
                            <label class="form-label">Tài khoản đang đăng nhập</label>
                            <input type="text" class="form-control custom-input"
                                value="<?= $_SESSION['admin_user'] ?? 'Admin' ?>" disabled
                                style="background-color: #f3f4f6 !important; color: #6b7280 !important;">
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Mật khẩu cũ <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <input type="password" name="old_pass" class="form-control custom-input" required
                                    placeholder="••••••">
                                <i
                                    class="ph-bold ph-lock-key position-absolute top-50 end-0 translate-middle-y me-3 text-secondary"></i>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Mật khẩu mới <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <input type="password" name="new_pass" class="form-control custom-input" required
                                    placeholder="••••••">
                                <i
                                    class="ph-bold ph-key position-absolute top-50 end-0 translate-middle-y me-3 text-secondary"></i>
                            </div>
                        </div>

                        <div class="mb-5">
                            <label class="form-label">Xác nhận mật khẩu mới <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <input type="password" name="confirm_pass" class="form-control custom-input" required
                                    placeholder="••••••">
                                <i
                                    class="ph-bold ph-check-circle position-absolute top-50 end-0 translate-middle-y me-3 text-secondary"></i>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" name="btn_change_pass" class="btn-submit">
                                <i class="ph-bold ph-floppy-disk me-2"></i> CẬP NHẬT MẬT KHẨU
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </main>

    <!-- MOBILE NAV -->
    <div class="bottom-nav">
        <a href="index.php" class="nav-item">
            <i class="ph-duotone ph-squares-four"></i> <span>Home</span>
        </a>
        <a href="index.php?type=0" class="nav-item">
            <i class="ph-duotone ph-tag"></i> <span>Kho</span>
        </a>
        <a href="add.php" class="nav-item">
            <div class="nav-item-add"><i class="ph-bold ph-plus"></i></div>
        </a>
        <a href="library.php" class="nav-item">
            <i class="ph-duotone ph-image"></i> <span>Ảnh</span>
        </a>
        <div class="dropup">
            <div class="nav-item active" data-bs-toggle="dropdown">
                <!-- Active ở Menu -->
                <i class="ph-duotone ph-user-circle"></i> <span>Menu</span>
            </div>
            <ul class="dropdown-menu mb-3 shadow-lg border-0">
                <li><a class="dropdown-item py-2 fw-bold active" href="change_pass.php">Đổi mật khẩu</a></li>
                <li><a class="dropdown-item py-2 text-danger" href="logout.php">Đăng xuất</a></li>
            </ul>
        </div>
    </div>

    <!-- SCRIPT -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Hiển thị SweetAlert từ URL
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        const msg = urlParams.get('msg');

        // Cấu hình Toast sáng màu
        const toastConfig = {
            confirmButtonColor: '#f59e0b',
            background: '#fff',
            color: '#000'
        };

        if (status === 'success') {
            Swal.fire({
                ...toastConfig,
                icon: 'success',
                title: 'Thành công!',
                text: 'Mật khẩu đã được thay đổi.'
            });
            window.history.replaceState({}, document.title, "change_pass.php");
        } else if (status === 'error') {
            Swal.fire({
                ...toastConfig,
                icon: 'error',
                title: 'Lỗi!',
                text: decodeURIComponent(msg.replace(/\+/g, ' ')),
                confirmButtonColor: '#ef4444'
            });
            window.history.replaceState({}, document.title, "change_pass.php");
        }
    });
    </script>
</body>

</html>