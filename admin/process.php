<?php
// admin/process.php - UPDATE: LƯU TAG & TRẠNG THÁI ORDER
require_once 'auth.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Chỉ xử lý POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

try {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $userId = $_SESSION['admin_id'];
    $prefix = $_SESSION['prefix'] ?? '';

    // 1. XỬ LÝ TIÊU ĐỀ (MÃ SỐ)
    $title = '';
    $inputTitle = isset($_POST['title']) ? trim($_POST['title']) : '';

    if ($id == 0) { // THÊM MỚI
        if (empty($inputTitle)) {
            // Tự động tạo mã nếu để trống
            if (empty($prefix)) die("❌ Lỗi: Bạn chưa nhập Mã Acc!");

            $stmtMax = $conn->prepare("SELECT title FROM products WHERE title LIKE :p ORDER BY LENGTH(title) DESC, title DESC LIMIT 1");
            $stmtMax->execute([':p' => $prefix . '%']);
            $lastTitle = $stmtMax->fetchColumn();

            $nextNum = ($lastTitle && preg_match('/(\d+)$/', $lastTitle, $matches)) ? (int)$matches[1] + 1 : 1;
            $title = $prefix . $nextNum;
        } else {
            $title = $inputTitle;
            // Check trùng
            $check = $conn->prepare("SELECT COUNT(*) FROM products WHERE title = :t");
            $check->execute([':t' => $title]);
            if ($check->fetchColumn() > 0) die("⚠️ Mã Acc <b>$title</b> đã tồn tại!");
        }
    } else { // CẬP NHẬT
        if (empty($inputTitle)) die("❌ Lỗi: Mã Acc không được để trống!");
        $title = $inputTitle;
        // Check trùng (trừ chính nó)
        $check = $conn->prepare("SELECT COUNT(*) FROM products WHERE title = :t AND id != :i");
        $check->execute([':t' => $title, ':i' => $id]);
        if ($check->fetchColumn() > 0) die("⚠️ Mã Acc <b>$title</b> đã tồn tại!");
    }

    // 2. DỮ LIỆU CƠ BẢN
    $price = isset($_POST['price']) ? (int)str_replace(['.', ','], '', $_POST['price']) : 0;
    $priceRent = isset($_POST['price_rent']) ? (int)str_replace(['.', ','], '', $_POST['price_rent']) : 0;
    $unit = isset($_POST['unit']) ? (int)$_POST['unit'] : 2;
    $privateNote = $_POST['private_note'] ?? '';
    $status = isset($_POST['status']) ? 1 : ($id == 0 ? 1 : 0); // Mới thì auto hiện, sửa thì theo form (nếu có)

    // [MỚI] Xử lý Acc Order (Checkbox)
    $isOrder = isset($_POST['is_order']) ? 1 : 0;

    // Logic loại acc (để tương thích code cũ)
    $type = ($priceRent > 0 && $price == 0) ? 1 : 0;

    // 3. XỬ LÝ ẢNH
    $finalGallery = [];
    if (isset($_POST['final_gallery_list'])) {
        $finalGallery = json_decode($_POST['final_gallery_list'], true);
    }
    if (empty($finalGallery) || !is_array($finalGallery)) die("❌ Lỗi: Chưa có ảnh nào!");

    $thumb = $finalGallery[0];
    $galleryJson = json_encode($finalGallery);

    // =========================================================
    // 4. THỰC THI SQL (LƯU SẢN PHẨM)
    // =========================================================

    if ($id == 0) {
        // INSERT
        $sql = "INSERT INTO products (
                    title, price, price_rent, type, unit, 
                    thumb, gallery, status, is_order, 
                    created_at, views, user_id, private_note, is_featured, view_order
                ) VALUES (
                    :title, :price, :rent, :type, :unit, 
                    :thumb, :gallery, :status, :is_order, 
                    NOW(), 0, :uid, :note, 0, 0
                )";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':title' => $title,
            ':price' => $price,
            ':rent' => $priceRent,
            ':type' => $type,
            ':unit' => $unit,
            ':thumb' => $thumb,
            ':gallery' => $galleryJson,
            ':status' => $status,
            ':is_order' => $isOrder, // [MỚI]
            ':uid' => $userId,
            ':note' => $privateNote
        ]);
        $productId = $conn->lastInsertId();
        $msg = "added";
    } else {
        // UPDATE
        $sql = "UPDATE products SET 
                    title = :title, price = :price, price_rent = :rent, 
                    type = :type, unit = :unit, thumb = :thumb, 
                    gallery = :gallery, status = :status, is_order = :is_order, 
                    private_note = :note 
                WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':title' => $title,
            ':price' => $price,
            ':rent' => $priceRent,
            ':type' => $type,
            ':unit' => $unit,
            ':thumb' => $thumb,
            ':gallery' => $galleryJson,
            ':status' => $status, // Lưu ý: Nếu form edit không gửi status thì cần check lại logic này ở form edit
            ':is_order' => $isOrder, // [MỚI]
            ':note' => $privateNote,
            ':id' => $id
        ]);
        $productId = $id;
        $msg = "updated";
    }

    // =========================================================
    // 5. LƯU TAG (QUAN TRỌNG)
    // =========================================================

    // B1: Xóa hết tag cũ của acc này (để cập nhật mới)
    $conn->prepare("DELETE FROM product_tags WHERE product_id = :pid")->execute([':pid' => $productId]);

    // B2: Thêm các tag mới chọn
    if (isset($_POST['tags']) && is_array($_POST['tags'])) {
        $sqlTag = "INSERT INTO product_tags (product_id, tag_id) VALUES (:pid, :tid)";
        $stmtTag = $conn->prepare($sqlTag);

        foreach ($_POST['tags'] as $tagId) {
            $stmtTag->execute([':pid' => $productId, ':tid' => (int)$tagId]);
        }
    }

    // XONG!
    header("Location: index.php?msg=$msg");
    exit;
} catch (Exception $e) {
    die("❌ Lỗi hệ thống: " . $e->getMessage());
}