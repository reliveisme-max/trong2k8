<?php
// admin/delete.php - ĐÃ BẢO MẬT
require_once 'auth.php'; // <--- CHỐT CHẶN BẢO VỆ
require_once '../includes/config.php';

// Kiểm tra ID
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    // 1. Lấy thông tin ảnh để xóa file trong thư mục uploads (dọn rác)
    $stmt = $conn->prepare("SELECT thumb, gallery FROM products WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $product = $stmt->fetch();

    if ($product) {
        // Xóa ảnh bìa
        $thumbPath = "../uploads/" . $product['thumb'];
        if (file_exists($thumbPath)) {
            unlink($thumbPath);
        }

        // Xóa album ảnh
        $gallery = json_decode($product['gallery'], true);
        if (is_array($gallery)) {
            foreach ($gallery as $img) {
                $imgPath = "../uploads/" . $img;
                if (file_exists($imgPath)) {
                    unlink($imgPath);
                }
            }
        }

        // 2. Xóa trong Database
        $delStmt = $conn->prepare("DELETE FROM products WHERE id = :id");
        $delStmt->execute([':id' => $id]);
    }
}

// Xóa xong quay về trang danh sách
header("Location: index.php?msg=deleted");
exit;