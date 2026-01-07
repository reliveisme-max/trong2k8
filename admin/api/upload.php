<?php
// admin/api/upload.php
// CHUYÊN XỬ LÝ: UPLOAD ẢNH (AJAX)

// 1. Gọi các file cấu hình từ cấp cha
require_once '../auth.php';              // admin/auth.php
require_once '../../includes/config.php';     // includes/config.php
require_once '../../includes/functions.php';  // includes/functions.php

// Tắt lỗi HTML để JSON trả về không bị hỏng
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Chỉ chấp nhận POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'msg' => 'Invalid Request']);
    exit;
}

// 2. XỬ LÝ UPLOAD
if (isset($_POST['ajax_upload_mode']) && $_POST['ajax_upload_mode'] == '1') {
    $responseMap = [];
    $uids = isset($_POST['chunk_uids']) ? $_POST['chunk_uids'] : [];

    if (isset($_FILES['chunk_files'])) {
        $files = reArrayFiles($_FILES['chunk_files']);

        // [QUAN TRỌNG] Thay đổi thư mục làm việc hiện tại về 'admin/' 
        // Lý do: Hàm uploadImageToWebp dùng đường dẫn tương đối "../uploads/"
        // Nếu chạy từ 'admin/api/', nó sẽ tìm 'admin/uploads/' (Sai).
        // Lệnh này giúp nó tìm đúng 'root/uploads/'.
        chdir('../');

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

echo json_encode(['status' => 'error', 'msg' => 'No valid data sent']);