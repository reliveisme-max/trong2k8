<?php
// update_db_v2.php - CHẠY 1 LẦN ĐỂ THÊM CỘT SẮP XẾP
require_once 'includes/config.php';

try {
    echo "<h1>🛠️ Đang cập nhật CSDL (Bước 2)...</h1>";

    // 1. Kiểm tra cột 'is_featured' (Ghim)
    $check1 = $conn->query("SHOW COLUMNS FROM products LIKE 'is_featured'");
    if ($check1->rowCount() == 0) {
        $conn->exec("ALTER TABLE products ADD COLUMN is_featured TINYINT(1) DEFAULT 0 AFTER title");
        echo "<p>✅ Đã thêm cột <b>is_featured</b>.</p>";
    } else {
        echo "<p>ℹ️ Cột <b>is_featured</b> đã có.</p>";
    }

    // 2. Kiểm tra cột 'view_order' (Thứ tự sắp xếp) - MỚI
    $check2 = $conn->query("SHOW COLUMNS FROM products LIKE 'view_order'");
    if ($check2->rowCount() == 0) {
        // Thêm cột view_order, mặc định là 0
        $conn->exec("ALTER TABLE products ADD COLUMN view_order INT DEFAULT 0 AFTER is_featured");
        echo "<p>✅ Đã thêm cột <b>view_order</b> (Để lưu thứ tự kéo thả).</p>";
    } else {
        echo "<p>ℹ️ Cột <b>view_order</b> đã có.</p>";
    }

    echo "<hr><h3>🎉 CẬP NHẬT THÀNH CÔNG!</h3>";
    echo "<p>Bạn vui lòng xóa file <b>update_db_v2.php</b> này đi.</p>";
    echo "<p>👉 Quay lại chat và gõ <b>'Oke'</b> để nhận file xử lý (Process).</p>";
} catch (PDOException $e) {
    echo "<h3 style='color:red'>❌ Lỗi: " . $e->getMessage() . "</h3>";
}