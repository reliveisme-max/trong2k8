<?php
// admin/auth.php - FIX: TĂNG THỜI GIAN ĐĂNG NHẬP LÊN 1 THÁNG (30 NGÀY)

// 1. Cấu hình thời gian tồn tại (30 ngày = 2592000 giây)
if (session_status() === PHP_SESSION_NONE) {
    // Thời gian sống của file session trên server
    ini_set('session.gc_maxlifetime', 2592000);

    // Thời gian sống của cookie trên trình duyệt
    session_set_cookie_params(2592000);

    session_start();
}

// 2. Kiểm tra Vé vào cửa (Session)
if (!isset($_SESSION['is_admin_logged_in']) || $_SESSION['is_admin_logged_in'] !== true) {
    // Chưa đăng nhập -> Chuyển hướng về trang Login
    header("Location: login.php");
    exit;
}