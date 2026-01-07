<?php
// admin/process_pass.php - XỬ LÝ ĐỔI MẬT KHẨU
require_once 'auth.php';
require_once '../includes/config.php';

if (isset($_POST['btn_change_pass'])) {

    $id = $_SESSION['admin_id']; // Lấy ID admin đang đăng nhập
    $old_pass = $_POST['old_pass'];
    $new_pass = $_POST['new_pass'];
    $confirm_pass = $_POST['confirm_pass'];

    // 1. Kiểm tra 2 mật khẩu mới có khớp không
    if ($new_pass !== $confirm_pass) {
        header("Location: change_pass.php?status=error&msg=" . urlencode("Mật khẩu mới không khớp!"));
        exit;
    }

    // 2. Lấy mật khẩu cũ trong DB ra để so sánh
    $stmt = $conn->prepare("SELECT password FROM admins WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch();

    if (!$user) {
        // Trường hợp hiếm: User bị xóa khi đang đăng nhập
        header("Location: logout.php");
        exit;
    }

    // 3. Kiểm tra mật khẩu cũ
    if (!password_verify($old_pass, $user['password'])) {
        header("Location: change_pass.php?status=error&msg=" . urlencode("Mật khẩu cũ không đúng!"));
        exit;
    }

    // 4. Cập nhật mật khẩu mới (Mã hóa Hash)
    $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);

    $update = $conn->prepare("UPDATE admins SET password = :p WHERE id = :id");
    if ($update->execute([':p' => $new_hash, ':id' => $id])) {
        header("Location: change_pass.php?status=success");
        exit;
    } else {
        header("Location: change_pass.php?status=error&msg=" . urlencode("Lỗi hệ thống, vui lòng thử lại."));
        exit;
    }
} else {
    header("Location: index.php");
    exit;
}