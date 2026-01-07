<?php
// admin/api/featured.php
// CHUYÊN XỬ LÝ: GHIM ACC & SẮP XẾP THỨ TỰ (AJAX)

// 1. Điều chỉnh đường dẫn để gọi file Auth và Config từ thư mục con
require_once '../auth.php';          // Gọi admin/auth.php
require_once '../../includes/config.php'; // Gọi includes/config.php

// Tắt hiển thị lỗi HTML để không làm hỏng JSON trả về
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Kiểm tra phương thức request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'msg' => 'Invalid Request']);
    exit;
}

// =================================================================
// 1. XỬ LÝ GHIM / GỠ GHIM ACC
// =================================================================
if (isset($_POST['action']) && $_POST['action'] == 'toggle_featured') {

    // Chỉ Boss (role = 1) mới được ghim
    if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
        echo json_encode(['status' => 'error', 'msg' => '⛔ Chỉ Admin mới có quyền Ghim acc!']);
        exit;
    }

    $id = (int)$_POST['id'];

    try {
        $stmt = $conn->prepare("SELECT is_featured FROM products WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $curr = $stmt->fetchColumn();

        if ($curr == 1) {
            // Gỡ ghim -> Reset cả thứ tự
            $conn->prepare("UPDATE products SET is_featured = 0, view_order = 0 WHERE id = :id")->execute([':id' => $id]);
            echo json_encode(['status' => 'success', 'new_state' => 0, 'msg' => 'Đã gỡ ghim']);
        } else {
            // Ghim -> Kiểm tra số lượng (Giới hạn 12)
            $count = $conn->query("SELECT COUNT(*) FROM products WHERE is_featured = 1")->fetchColumn();

            if ($count >= 12) {
                echo json_encode(['status' => 'error', 'msg' => '⚠️ Đã đạt giới hạn 12 Acc nổi bật!']);
            } else {
                // Ghim mới thì mặc định order = 99 (để xếp cuối danh sách ghim)
                $conn->prepare("UPDATE products SET is_featured = 1, view_order = 99 WHERE id = :id")->execute([':id' => $id]);
                echo json_encode(['status' => 'success', 'new_state' => 1, 'msg' => 'Đã ghim Acc (Vào quản lý để xếp thứ tự)']);
            }
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => 'Lỗi DB: ' . $e->getMessage()]);
    }
    exit;
}

// =================================================================
// 2. LƯU THỨ TỰ SẮP XẾP (Kéo thả trong Modal)
// =================================================================
if (isset($_POST['action']) && $_POST['action'] == 'save_featured_order') {

    if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
        echo json_encode(['status' => 'error', 'msg' => 'Không có quyền!']);
        exit;
    }

    $orderData = isset($_POST['order']) ? $_POST['order'] : []; // Mảng ID gửi lên

    if (!empty($orderData)) {
        try {
            $sql = "UPDATE products SET view_order = :order WHERE id = :id";
            $stmt = $conn->prepare($sql);

            // Duyệt mảng: Index là thứ tự (0, 1, 2...), Value là ID
            foreach ($orderData as $index => $id) {
                $stmt->execute([
                    ':order' => $index + 1, // Lưu 1, 2, 3...
                    ':id' => (int)$id
                ]);
            }
            echo json_encode(['status' => 'success', 'msg' => 'Đã cập nhật thứ tự hiển thị!']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'msg' => 'Lỗi lưu: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Dữ liệu trống!']);
    }
    exit;
}