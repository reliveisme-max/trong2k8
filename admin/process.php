<?php
// admin/process.php
// CHUYÊN XỬ LÝ: LƯU SẢN PHẨM (THÊM MỚI / CẬP NHẬT)

require_once 'auth.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Chỉ xử lý POST request từ Form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

try {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $role = $_SESSION['role'];
    $userId = $_SESSION['admin_id'];
    $prefix = $_SESSION['prefix'] ?? ''; // Lấy Prefix (VD: BOY, CC...)

    $title = '';
    $inputTitle = isset($_POST['title']) ? trim($_POST['title']) : '';

    // =================================================================
    // 1. XỬ LÝ TIÊU ĐỀ / MÃ SỐ
    // =================================================================

    if ($id == 0) {
        // --- TRƯỜNG HỢP: THÊM MỚI ---

        if (empty($inputTitle)) {
            // A. Người dùng ĐỂ TRỐNG -> Tự động tạo mã theo Prefix
            if (empty($prefix)) {
                die("❌ Lỗi: Bạn chưa nhập Mã Acc và tài khoản của bạn cũng không có Prefix để tự tạo!");
            }

            // Tìm mã số lớn nhất hiện tại theo Prefix (Ví dụ: BOY99)
            $sqlGetMax = "SELECT title FROM products WHERE title LIKE :p ORDER BY LENGTH(title) DESC, title DESC LIMIT 1";
            $stmtMax = $conn->prepare($sqlGetMax);
            $stmtMax->execute([':p' => $prefix . '%']);
            $lastTitle = $stmtMax->fetchColumn();

            if ($lastTitle) {
                // Tách số ra khỏi chuỗi (BOY123 -> 123)
                if (preg_match('/(\d+)$/', $lastTitle, $matches)) {
                    $nextNum = (int)$matches[1] + 1;
                } else {
                    $nextNum = 1;
                }
            } else {
                $nextNum = 1; // Chưa có acc nào thì bắt đầu từ 1
            }
            $title = $prefix . $nextNum;
        } else {
            // B. Người dùng NHẬP TAY -> Kiểm tra trùng lặp
            $title = $inputTitle;

            $checkSql = "SELECT COUNT(*) FROM products WHERE title = :title";
            $stmtCheck = $conn->prepare($checkSql);
            $stmtCheck->execute([':title' => $title]);
            if ($stmtCheck->fetchColumn() > 0) {
                die("⚠️ Mã Acc <b>$title</b> đã tồn tại! Vui lòng nhập mã khác hoặc để trống để tự tạo.");
            }
        }
    } else {
        // --- TRƯỜNG HỢP: CẬP NHẬT (EDIT) ---
        if (empty($inputTitle)) {
            die("❌ Lỗi: Mã Acc không được để trống khi chỉnh sửa!");
        }
        $title = $inputTitle;

        // Kiểm tra trùng mã (trừ chính nó ra)
        $checkSql = "SELECT COUNT(*) FROM products WHERE title = :title AND id != :id";
        $stmtCheck = $conn->prepare($checkSql);
        $stmtCheck->execute([':title' => $title, ':id' => $id]);
        if ($stmtCheck->fetchColumn() > 0) {
            die("⚠️ Mã Acc <b>$title</b> đã tồn tại!");
        }
    }

    // =================================================================
    // 2. XỬ LÝ DỮ LIỆU KHÁC (GIÁ, ẢNH...)
    // =================================================================

    // Xóa dấu chấm/phẩy trong giá tiền (VD: 5.000.000 -> 5000000)
    $price = isset($_POST['price']) ? (int)str_replace(['.', ','], '', $_POST['price']) : 0;
    $priceRent = isset($_POST['price_rent']) ? (int)str_replace(['.', ','], '', $_POST['price_rent']) : 0;

    $unit = isset($_POST['unit']) ? (int)$_POST['unit'] : 0; // 1: Giờ, 2: Ngày
    $privateNote = $_POST['private_note'] ?? '';

    // Logic trạng thái: Nếu sửa thì lấy từ form, nếu thêm mới thì mặc định hiện (1)
    $status = isset($_POST['status']) ? 1 : ($id == 0 ? 1 : 0);

    // Logic loại acc: Nếu có giá thuê mà ko có giá bán -> Loại 1 (Chỉ thuê)
    $type = ($priceRent > 0 && $price == 0) ? 1 : 0;

    // Xử lý danh sách ảnh (Nhận JSON array filename từ Javascript)
    $finalGallery = [];
    if (isset($_POST['final_gallery_list'])) {
        $finalGallery = json_decode($_POST['final_gallery_list'], true);
    }

    if (empty($finalGallery) || !is_array($finalGallery)) {
        die("❌ Lỗi: Bạn chưa tải ảnh nào lên!");
    }

    // Ảnh đầu tiên làm ảnh bìa (Thumb)
    $thumb = $finalGallery[0];
    // Toàn bộ danh sách lưu vào cột gallery
    $galleryJson = json_encode($finalGallery);

    // =================================================================
    // 3. THỰC THI SQL
    // =================================================================

    if ($id == 0) {
        // INSERT
        $sql = "INSERT INTO products (
                    title, price, price_rent, type, unit, 
                    thumb, gallery, status, created_at, views, 
                    user_id, private_note, is_featured, view_order
                ) VALUES (
                    :title, :price, :price_rent, :type, :unit, 
                    :thumb, :gallery, :status, NOW(), 0, 
                    :uid, :note, 0, 0
                )";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':title' => $title,
            ':price' => $price,
            ':price_rent' => $priceRent,
            ':type' => $type,
            ':unit' => $unit,
            ':thumb' => $thumb,
            ':gallery' => $galleryJson,
            ':status' => $status,
            ':uid' => $userId,
            ':note' => $privateNote
        ]);

        header("Location: index.php?msg=added");
    } else {
        // UPDATE
        $sql = "UPDATE products SET 
                    title = :t, 
                    price = :p, 
                    price_rent = :pr, 
                    type = :ty, 
                    unit = :u, 
                    thumb = :th, 
                    gallery = :g, 
                    status = :s, 
                    private_note = :note 
                WHERE id = :id";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':t' => $title,
            ':p' => $price,
            ':pr' => $priceRent,
            ':ty' => $type,
            ':u' => $unit,
            ':th' => $thumb,
            ':g' => $galleryJson,
            ':s' => $status,
            ':note' => $privateNote,
            ':id' => $id
        ]);

        header("Location: index.php?msg=updated");
    }
    exit;
} catch (PDOException $e) {
    die("❌ Lỗi SQL: " . $e->getMessage());
} catch (Exception $e) {
    die("❌ Lỗi Hệ thống: " . $e->getMessage());
}