<?php
// includes/config.php - FINAL SERVER CONFIG

// 1. TỰ ĐỘNG PHÁT HIỆN MÔI TRƯỜNG
$currentHost = $_SERVER['HTTP_HOST'];

// Kiểm tra nếu đang chạy ở Localhost (Máy tính cá nhân)
if ($currentHost == 'localhost' || $currentHost == '127.0.0.1') {

    // === CẤU HÌNH LOCALHOST (XAMPP) ===
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'trong2k8shop'); // Tên DB ở máy bạn
    define('BASE_URL', 'http://localhost/trong2k8shop/');

} else {

    // === CẤU HÌNH HOSTING (ONLINE) ===
    // Thông tin bạn cung cấp
    define('DB_HOST', 'localhost'); // Đa số hosting đều là localhost
    define('DB_USER', 'iezifotz_trong2k8shop');
    define('DB_PASS', 'Relive174@');
    define('DB_NAME', 'iezifotz_trong2k8shop');

    // Tên miền chính thức
    define('BASE_URL', 'https://trong2k8shop.vn');
}

// 2. KẾT NỐI DATABASE (PDO)
try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Hiển thị lỗi nếu không kết nối được
    die("<h1>Lỗi kết nối Database!</h1><p>Không thể kết nối đến CSDL trên Hosting.</p><br>Lỗi chi tiết: " . $e->getMessage());
}
?>