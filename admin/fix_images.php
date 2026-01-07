<?php
// admin/fix_images.php - TOOL FIX ẢNH LỖI (BROKEN IMAGES)
require_once 'auth.php';
require_once '../includes/config.php';

echo "<pre style='font-family: monospace; font-size: 14px; line-height: 1.5;'>";
echo "🔄 <b>ĐANG QUÉT VÀ SỬA LỖI ẢNH...</b>\n\n";

// 1. Lấy tất cả sản phẩm
$stmt = $conn->query("SELECT id, title, thumb, gallery FROM products");
$products = $stmt->fetchAll();

$fixedCount = 0;
$totalDeletedParams = 0;

foreach ($products as $p) {
    $id = $p['id'];
    $title = $p['title'];
    $thumb = $p['thumb'];
    $gallery = json_decode($p['gallery'], true);
    
    $isChanged = false;
    $log = "";

    // A. KIỂM TRA ẢNH BÌA (THUMB)
    if (!empty($thumb) && !file_exists("../uploads/" . $thumb)) {
        // Nếu ảnh bìa không tồn tại -> Xóa
        $thumb = ""; 
        $isChanged = true;
        $log .= "   - ❌ Ảnh bìa lỗi: {$p['thumb']} (Đã xóa)\n";
        $totalDeletedParams++;
    }

    // B. KIỂM TRA ALBUM ẢNH (GALLERY)
    $newGallery = [];
    if (is_array($gallery)) {
        foreach ($gallery as $img) {
            if (file_exists("../uploads/" . $img)) {
                $newGallery[] = $img; // Ảnh còn tồn tại -> Giữ lại
            } else {
                $isChanged = true; // Ảnh mất -> Đánh dấu là có thay đổi
                $log .= "   - ❌ Ảnh album lỗi: $img (Đã xóa)\n";
                $totalDeletedParams++;
            }
        }
    }

    // C. NẾU CÓ THAY ĐỔI -> CẬP NHẬT DATABASE
    if ($isChanged) {
        // Nếu ảnh bìa bị xóa mất, lấy ảnh đầu tiên trong gallery làm ảnh bìa mới (nếu có)
        if (empty($thumb) && count($newGallery) > 0) {
            $thumb = $newGallery[0];
            $log .= "   - 🔄 Tự động set ảnh bìa mới: $thumb\n";
        }

        $jsonGallery = json_encode($newGallery);
        
        $update = $conn->prepare("UPDATE products SET thumb = :th, gallery = :g WHERE id = :id");
        $update->execute([':th' => $thumb, ':g' => $jsonGallery, ':id' => $id]);
        
        echo "🆔 <b>Acc #$id ($title):</b>\n" . $log;
        $fixedCount++;
    }
}

echo "\n➖➖➖➖➖➖➖➖➖➖➖➖➖➖➖➖\n";
echo "✅ <b>HOÀN TẤT!</b>\n";
echo "🛠️ Đã sửa lỗi cho: <b>$fixedCount</b> Acc.\n";
echo "🗑️ Tổng số link ảnh chết đã xóa: <b>$totalDeletedParams</b>.\n";
echo "</pre>";
echo "<a href='index.php' style='display:inline-block; margin-top:20px; padding:10px 20px; background:#f59e0b; color:#fff; text-decoration:none; font-weight:bold; border-radius:5px;'>⬅️ Quay về Admin</a>";
?>