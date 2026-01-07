<?php
// admin/process.php - V12: AUTO INCREMENT TITLE FOR QTV + PRIVATE NOTE
require_once 'auth.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);

// =================================================================
// PHẦN 1: XỬ LÝ UPLOAD AJAX (GIỮ NGUYÊN)
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
// PHẦN 2: XỬ LÝ LƯU DATABASE (CÓ SỬA ĐỔI LỚN)
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        // --- LOGIC XỬ LÝ TIÊU ĐỀ (MÃ SỐ) ---
        $title = '';
        $role = $_SESSION['role'];

        if ($id == 0 && isset($_POST['auto_prefix']) && $_POST['auto_prefix'] == '1') {
            // Đây là QTV đăng mới -> Tự động sinh mã
            $prefix = $_SESSION['prefix'];
            if (empty($prefix)) {
                echo "❌ Lỗi: Tài khoản QTV này chưa được cấp Mã định danh (Prefix). Vui lòng liên hệ Boss!";
                exit;
            }

            // Tìm mã lớn nhất hiện tại của Prefix này
            // Logic: Lấy title LIKE 'NAM%', sắp xếp theo độ dài rồi đến giá trị để lấy số to nhất
            // Ví dụ: NAM1, NAM2, NAM10 (NAM10 dài hơn NAM2 nên sẽ xếp trên)
            $sqlGetMax = "SELECT title FROM products WHERE title LIKE :p ORDER BY LENGTH(title) DESC, title DESC LIMIT 1";
            $stmtMax = $conn->prepare($sqlGetMax);
            $stmtMax->execute([':p' => $prefix . '%']);
            $lastTitle = $stmtMax->fetchColumn();

            if ($lastTitle) {
                // Tách số ra khỏi chuỗi (Ví dụ NAM15 -> lấy 15)
                if (preg_match('/(\d+)$/', $lastTitle, $matches)) {
                    $nextNum = (int)$matches[1] + 1;
                } else {
                    $nextNum = 1;
                }
            } else {
                $nextNum = 1;
            }

            $title = $prefix . $nextNum; // Mã mới: NAM16

        } else {
            // Đây là Boss hoặc đang Sửa -> Lấy mã từ form
            $title = trim($_POST['title']);
            if (empty($title)) {
                echo "❌ Lỗi: Chưa nhập Mã Acc!";
                exit;
            }
        }

        // Check trùng (Chỉ check nếu Boss nhập tay)
        if ($id == 0 && $role == 1) {
            $checkSql = "SELECT COUNT(*) FROM products WHERE title = :title";
            $stmtCheck = $conn->prepare($checkSql);
            $stmtCheck->execute([':title' => $title]);
            if ($stmtCheck->fetchColumn() > 0) {
                echo "⚠️ Mã <b>$title</b> đã tồn tại!";
                exit;
            }
        }

        // Các thông tin khác
        $price = isset($_POST['price']) ? (int)str_replace(['.', ','], '', $_POST['price']) : 0;
        $priceRent = isset($_POST['price_rent']) ? (int)str_replace(['.', ','], '', $_POST['price_rent']) : 0;
        $unit = isset($_POST['unit']) ? (int)$_POST['unit'] : 0;
        $status = isset($_POST['status']) ? 1 : ($id == 0 ? 1 : 0);
        $type = ($priceRent > 0 && $price == 0) ? 1 : 0;

        // [MỚI] Ghi chú nội bộ
        $privateNote = $_POST['private_note'] ?? '';

        // [MỚI] ID người đăng
        $userId = $_SESSION['admin_id'];

        // Xử lý ảnh
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

        if ($id == 0) {
            // INSERT MỚI
            $sql = "INSERT INTO products (title, price, price_rent, type, unit, thumb, gallery, status, created_at, views, user_id, private_note) 
                    VALUES (:title, :price, :price_rent, :type, :unit, :thumb, :gallery, :status, NOW(), 0, :uid, :note)";
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
            // UPDATE (Sửa)
            // Lưu ý: Không cho sửa user_id để giữ nguyên người đăng gốc
            $sql = "UPDATE products SET title=:t, price=:p, price_rent=:pr, type=:ty, unit=:u, thumb=:th, gallery=:g, status=:s, private_note=:note WHERE id=:id";
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
        echo "❌ Lỗi SQL: " . $e->getMessage();
        exit;
    } catch (Exception $e) {
        echo "❌ Lỗi Hệ thống: " . $e->getMessage();
        exit;
    }
}