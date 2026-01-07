<?php
// admin/auth.php - TRẠM KIỂM SOÁT AN NINH
// File này sẽ được nhúng vào đầu các trang quan trọng (index, add, edit...)

// 1. Khởi động session (Nếu chưa có)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Kiểm tra Vé vào cửa (Session)
if (!isset($_SESSION['is_admin_logged_in']) || $_SESSION['is_admin_logged_in'] !== true) {
    // Chưa đăng nhập -> Chuyển hướng về trang Login
    header("Location: login.php");
    exit;
}