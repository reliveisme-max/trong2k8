<?php
// admin/get_images.php - V2: TĂNG LIMIT LOAD ẢNH
require_once 'auth.php'; 

header('Content-Type: application/json');

$dir = "../uploads/";
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// [SỬA Ở ĐÂY] Tăng từ 20 lên 200 để load 1 phát được nhiều ảnh luôn
$limit = 100; 

$offset = ($page - 1) * $limit;

if (!is_dir($dir)) {
    echo json_encode(['status' => 'error', 'message' => 'Thư mục uploads không tồn tại']);
    exit;
}

$files = scandir($dir);
$images = [];

foreach ($files as $file) {
    if ($file !== '.' && $file !== '..') {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $images[$file] = filemtime($dir . $file);
        }
    }
}

arsort($images);
$sortedImages = array_keys($images);

$totalImages = count($sortedImages);
$slicedImages = array_slice($sortedImages, $offset, $limit);
$hasMore = ($offset + $limit) < $totalImages;

echo json_encode([
    'status'   => 'success',
    'data'     => $slicedImages,
    'has_more' => $hasMore
]);