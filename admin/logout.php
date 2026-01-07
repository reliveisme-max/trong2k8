<?php
// admin/logout.php - ĐĂNG XUẤT
session_start();

// 1. Xóa tất cả dữ liệu phiên làm việc
session_unset();

// 2. Hủy hoàn toàn session
session_destroy();

// 3. Quay về trang đăng nhập
header("Location: login.php");
exit;