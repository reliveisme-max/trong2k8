<?php
// admin/process.php - V11: FIX SORT ORDER (UID MAPPING)
// 1. Upload AJAX: Nhận thêm 'chunk_uids' để khớp đúng ảnh với thứ tự bên JS.
// 2. Trả về JSON dạng Key-Value (UID -> Filename) thay vì mảng tuần tự.

require_once 'auth.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Tắt lỗi hiển thị
ini_set('display_errors', 0);
error_reporting(E_ALL);

// =================================================================
// PHẦN 1: XỬ LÝ UPLOAD NGẦM (AJAX CHUNK UPLOAD)
// =================================================================
if (isset($_POST['ajax_upload_mode']) && $_POST['ajax_upload_mode'] == '1') {
    header('Content-Type: application/json');
    
    $responseMap = []; // Mảng kết quả: [uid => filename]
    
    // Nhận danh sách UID (ID định danh) từ JS gửi lên
    $uids = isset($_POST['chunk_uids']) ? $_POST['chunk_uids'] : [];

    if (isset($_FILES['chunk_files'])) {
        // Hàm reArrayFiles giúp sắp xếp lại mảng $_FILES cho dễ duyệt
        $files = reArrayFiles($_FILES['chunk_files']);
        
        // Duyệt qua từng file
        foreach ($files as $index => $file) {
            // Upload ảnh
            $result = uploadImageToWebp($file);
            
            // Nếu upload thành công VÀ có UID tương ứng
            if ($result && isset($uids[$index])) {
                $currentUid = $uids[$index];
                // Gán kết quả: ID này => Tên file này
                $responseMap[$currentUid] = $result;
            }
        }
    }
    
    // Trả về map kết quả để JS tự sắp xếp
    echo json_encode(['status' => 'success', 'data' => $responseMap]);
    exit; 
}

// =================================================================
// PHẦN 2: XỬ LÝ LƯU DATABASE (GIỮ NGUYÊN NHƯ CŨ)
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $title = trim($_POST['title']);

        if (empty($title)) {
            echo "❌ Lỗi: Bạn chưa nhập Mã Acc / Tiêu đề!";
            exit;
        }

        // Check trùng
        $checkSql = "SELECT COUNT(*) FROM products WHERE title = :title AND id != :id";
        $stmtCheck = $conn->prepare($checkSql);
        $stmtCheck->execute([':title' => $title, ':id' => $id]);
        
        if ($stmtCheck->fetchColumn() > 0) {
            echo "⚠️ <b>MÃ ACC ĐÃ TỒN TẠI!</b><br>Mã: <b style='color:red'>$title</b> đã có trên hệ thống.<br>Vui lòng đổi sang mã khác.";
            exit; 
        }

        $price = isset($_POST['price']) ? (int)str_replace(['.', ','], '', $_POST['price']) : 0;
        $priceRent = isset($_POST['price_rent']) ? (int)str_replace(['.', ','], '', $_POST['price_rent']) : 0;
        $unit = isset($_POST['unit']) ? (int)$_POST['unit'] : 0;
        $status = isset($_POST['status']) ? 1 : ($id == 0 ? 1 : 0);
        $type = ($priceRent > 0 && $price == 0) ? 1 : 0;

        // Nhận danh sách ảnh cuối cùng từ JS (đã được sắp xếp đúng)
        $finalGallery = [];
        if (isset($_POST['final_gallery_list'])) {
            $finalGallery = json_decode($_POST['final_gallery_list'], true);
        }

        if (empty($finalGallery)) {
             echo "❌ Lỗi: Không có ảnh nào được ghi nhận! Vui lòng thử lại.";
             exit;
        }

        $thumb = $finalGallery[0];
        $galleryJson = json_encode($finalGallery);

        if ($id == 0) {
            $sql = "INSERT INTO products (title, price, price_rent, type, unit, thumb, gallery, status, created_at, views) 
                    VALUES (:title, :price, :price_rent, :type, :unit, :thumb, :gallery, :status, NOW(), 0)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':title' => $title, ':price' => $price, ':price_rent' => $priceRent,
                ':type' => $type, ':unit' => $unit, ':thumb' => $thumb,
                ':gallery' => $galleryJson, ':status' => $status
            ]);
            header("Location: index.php?msg=added");
        } else {
            $sql = "UPDATE products SET title=:t, price=:p, price_rent=:pr, type=:ty, unit=:u, thumb=:th, gallery=:g, status=:s WHERE id=:id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':t' => $title, ':p' => $price, ':pr' => $priceRent,
                ':ty' => $type, ':u' => $unit, ':th' => $thumb,
                ':g' => $galleryJson, ':s' => $status, ':id' => $id
            ]);
            header("Location: index.php?msg=updated");
        }
        exit;

    } catch (PDOException $e) {
        echo "❌ Lỗi SQL: " . $e->getMessage(); exit;
    } catch (Exception $e) {
        echo "❌ Lỗi Hệ thống: " . $e->getMessage(); exit;
    }
}