<?php
// includes/header.php
// File này chứa phần đầu trang dùng chung

// 1. Cấu hình tiêu đề mặc định nếu chưa có
if (!isset($pageTitle)) $pageTitle = 'TRỌNG 2K8 SHOP - Uy Tín Hàng Đầu';

// 2. Kiểm tra xem đang ở trang nào để hiển thị nút bấm phù hợp
// Nếu biến $isDetailPage được set là true thì hiện nút Back, ngược lại hiện nút Zalo
$showBackButton = isset($isDetailPage) && $isDetailPage === true;
$backLink = isset($backUrl) ? $backUrl : './';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- 1. CÁC THƯ VIỆN CỐT LÕI -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Thư viện ảnh (Chỉ dùng cho trang chi tiết) -->
    <?php if ($showBackButton): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" />
    <?php endif; ?>

    <!-- 2. CSS CHÍNH (INLINE ĐỂ TRÁNH LỖI MẠNG/CACHE) -->
    <style>
    <?php // Đường dẫn tuyệt đối đến file CSS để include chính xác
    $cssPath=dirname(__DIR__) . '/assets/css/style.css';

    if (file_exists($cssPath)) {
        include $cssPath;
    }

    else {
        echo "/* Lỗi: Không tìm thấy file style.css tại $cssPath */";
    }

    ?>
    </style>
</head>

<body>

    <!-- 3. HEADER / THANH MENU -->
    <header class="main-header">
        <div class="container d-flex justify-content-between align-items-center">
            <!-- LOGO -->
            <a href="./" class="text-decoration-none">
                <div class="logo-text"><i class="ph-fill ph-heart"></i> TRỌNG 2K8</div>
            </a>

            <!-- NÚT BÊN PHẢI -->
            <?php if ($showBackButton): ?>
            <!-- Nút Quay lại (Cho trang chi tiết) -->
            <a href="<?= $backLink ?>" class="btn btn-outline-secondary rounded-pill px-3 fw-bold"
                style="font-size: 14px;">
                <i class="ph-bold ph-arrow-left"></i> Quay lại
            </a>
            <?php else: ?>
            <!-- Nút Zalo (Cho trang chủ) -->
            <a href="https://zalo.me/0984074897" target="_blank"
                class="btn btn-outline-warning rounded-pill fw-bold px-4"
                style="color: var(--accent-hover); border-color: var(--accent);">
                <i class="ph-bold ph-phone"></i> 0984.074.897
            </a>
            <?php endif; ?>
        </div>
    </header>