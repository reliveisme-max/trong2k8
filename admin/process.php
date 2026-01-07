<?php
// admin/process.php - FULL VERSION (Ghim + Sắp xếp + Phân quyền)
require_once 'auth.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);

// =================================================================
// PHẦN 1: XỬ LÝ GHIM / GỠ GHIM ACC (AJAX)
// =================================================================
if (isset($_POST['action']) && $_POST['action'] == 'toggle_featured') {
    header('Content-Type: application/json');

    // Chỉ Boss mới được ghim
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
                // Ghim mới thì mặc định order = 0 (hoặc xếp cuối cùng)
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
// PHẦN 1.5: LƯU THỨ TỰ SẮP XẾP (AJAX - MỚI)
// =================================================================
if (isset($_POST['action']) && $_POST['action'] == 'save_featured_order') {
    header('Content-Type: application/json');

    if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
        echo json_encode(['status' => 'error', 'msg' => 'Không có quyền!']);
        exit;
    }

    $orderData = isset($_POST['order']) ? $_POST['order'] : []; // Mảng ID gửi lên: [352, 219, 314...]

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

// =================================================================
// PHẦN 2: XỬ LÝ UPLOAD ẢNH (AJAX - GIỮ NGUYÊN)
// =================================================================
if (isset($_POST['ajax_upload_mode']) && $_POST['ajax_upload_mode'] == '1') {
    header('Content-Type: application/json');
    $responseMap = [];
    $uids = isset($_POST['chunk_uids']) ? $_POST['chunk_uids'] : [];

    if (isset($_FILES['chunk_files'])) {
        $files = reArrayFiles($_FILES['chunk_files']);
        foreach ($files as $index => $file) {
            $result = uploadImageToWebp($file);
            if ($result && isset($uids[$index])) {
                $responseMap[$uids[$index]] = $result;
            }
        }
    }
    echo json_encode(['status' => 'success', 'data' => $responseMap]);
    exit;
}

// =================================================================
// PHẦN 3: LƯU SẢN PHẨM (THÊM / SỬA - GIỮ NGUYÊN)
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        // --- A. LOGIC TIÊU ĐỀ & MÃ SỐ ---
        $title = '';
        $role = $_SESSION['role'];

        if ($id == 0 && isset($_POST['auto_prefix']) && $_POST['auto_prefix'] == '1') {
            // QTV đăng mới
            $prefix = $_SESSION['prefix'];
            if (empty($prefix)) {
                echo "❌ Lỗi: Tài khoản QTV chưa có Prefix!";
                exit;
            }

            $sqlGetMax = "SELECT title FROM products WHERE title LIKE :p ORDER BY LENGTH(title) DESC, title DESC LIMIT 1";
            $stmtMax = $conn->prepare($sqlGetMax);
            $stmtMax->execute([':p' => $prefix . '%']);
            $lastTitle = $stmtMax->fetchColumn();

            if ($lastTitle) {
                if (preg_match('/(\d+)$/', $lastTitle, $matches)) $nextNum = (int)$matches[1] + 1;
                else $nextNum = 1;
            } else $nextNum = 1;
            $title = $prefix . $nextNum;
        } else {
            // Boss hoặc Sửa
            $title = trim($_POST['title']);
            if (empty($title)) {
                echo "❌ Lỗi: Chưa nhập Mã Acc!";
                exit;
            }
        }

        if ($id == 0 && $role == 1) {
            $checkSql = "SELECT COUNT(*) FROM products WHERE title = :title";
            $stmtCheck = $conn->prepare($checkSql);
            $stmtCheck->execute([':title' => $title]);
            if ($stmtCheck->fetchColumn() > 0) {
                echo "⚠️ Mã <b>$title</b> đã tồn tại!";
                exit;
            }
        }

        // --- B. LẤY DỮ LIỆU ---
        $price = isset($_POST['price']) ? (int)str_replace(['.', ','], '', $_POST['price']) : 0;
        $priceRent = isset($_POST['price_rent']) ? (int)str_replace(['.', ','], '', $_POST['price_rent']) : 0;
        $unit = isset($_POST['unit']) ? (int)$_POST['unit'] : 0;
        $status = isset($_POST['status']) ? 1 : ($id == 0 ? 1 : 0);
        $type = ($priceRent > 0 && $price == 0) ? 1 : 0;
        $privateNote = $_POST['private_note'] ?? '';
        $userId = $_SESSION['admin_id'];

        $finalGallery = [];
        if (isset($_POST['final_gallery_list'])) {
            $finalGallery = json_decode($_POST['final_gallery_list'], true);
        }
        if (empty($finalGallery)) {
            echo "❌ Lỗi: Không có ảnh nào!";
            exit;
        }
        $thumb = $finalGallery[0];
        $galleryJson = json_encode($finalGallery);

        // --- C. SQL ---
        if ($id == 0) {
            // Insert mới (is_featured = 0)
            $sql = "INSERT INTO products (title, price, price_rent, type, unit, thumb, gallery, status, created_at, views, user_id, private_note, is_featured, view_order) 
                    VALUES (:title, :price, :price_rent, :type, :unit, :thumb, :gallery, :status, NOW(), 0, :uid, :note, 0, 0)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':title' => $title, ':price' => $price, ':price_rent' => $priceRent, ':type' => $type, ':unit' => $unit, ':thumb' => $thumb, ':gallery' => $galleryJson, ':status' => $status, ':uid' => $userId, ':note' => $privateNote]);
            header("Location: index.php?msg=added");
        } else {
            // Update (Giữ nguyên user_id, is_featured, view_order)
            $sql = "UPDATE products SET title=:t, price=:p, price_rent=:pr, type=:ty, unit=:u, thumb=:th, gallery=:g, status=:s, private_note=:note WHERE id=:id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':t' => $title, ':p' => $price, ':pr' => $priceRent, ':ty' => $type, ':u' => $unit, ':th' => $thumb, ':g' => $galleryJson, ':s' => $status, ':note' => $privateNote, ':id' => $id]);
            header("Location: index.php?msg=updated");
        }
        exit;
    } catch (PDOException $e) {
        echo "❌ Lỗi SQL: " . $e->getMessage();
        exit;
    } catch (Exception $e) {
        echo "❌ Lỗi Hệ thống: " . $e->getMessage();
        exit;
    }
}