<?php
// admin/delete.php - ĐÃ FIX QUYỀN CTV
require_once 'auth.php';
require_once '../includes/config.php';

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $current_role = $_SESSION['role'] ?? 0; // 1: Boss, 0: CTV
    $current_uid  = $_SESSION['admin_id'];

    // 1. Kiểm tra quyền sở hữu trước khi xóa
    $sqlCheck = "SELECT thumb, gallery, user_id FROM products WHERE id = :id";

    // Nếu là CTV thì chỉ tìm thấy bài của chính mình
    if ($current_role == 0) {
        $sqlCheck .= " AND user_id = :uid";
    }

    $stmt = $conn->prepare($sqlCheck);

    if ($current_role == 0) {
        $stmt->execute([':id' => $id, ':uid' => $current_uid]);
    } else {
        $stmt->execute([':id' => $id]);
    }

    $product = $stmt->fetch();

    if ($product) {
        // --- XÓA FILE ẢNH ---
        $thumbPath = "../uploads/" . $product['thumb'];
        if (file_exists($thumbPath)) @unlink($thumbPath);

        $gallery = json_decode($product['gallery'], true);
        if (is_array($gallery)) {
            foreach ($gallery as $img) {
                $imgPath = "../uploads/" . $img;
                if (file_exists($imgPath)) @unlink($imgPath);
            }
        }

        // --- XÓA DB ---
        // Không cần check lại user_id ở đây vì đã check ở bước SELECT trên rồi
        $delStmt = $conn->prepare("DELETE FROM products WHERE id = :id");
        $delStmt->execute([':id' => $id]);

        $msg = "deleted";
    } else {
        // Không tìm thấy hoặc không có quyền
        $msg = "error_perm";
    }
}

header("Location: index.php?msg=" . ($msg ?? 'error'));
exit;