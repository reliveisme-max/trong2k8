<?php
// detail.php - FINAL FIX: EMBED CSS DIRECTLY (INLINE STYLE)
require_once 'includes/config.php';
require_once 'includes/functions.php';

// 1. LẤY ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id == 0) {
    header("Location: index.php");
    exit;
}

// 2. LẤY DỮ LIỆU
$stmt = $conn->prepare("SELECT id, title, price, price_rent, type, unit, thumb, gallery, status, views, created_at FROM products WHERE id = :id");
$stmt->execute([':id' => $id]);
$product = $stmt->fetch();

if (!$product) die("Acc không tồn tại!");

// 3. TĂNG VIEW
$conn->prepare("UPDATE products SET views = views + 1 WHERE id = :id")->execute([':id' => $id]);
$product['views']++;

// 4. XỬ LÝ HIỂN THỊ
$isRentMode = isset($_GET['view']) && $_GET['view'] == 'rent';
$showPrice = $isRentMode ? $product['price_rent'] : $product['price'];

if (!$isRentMode && $product['price'] == 0 && $product['price_rent'] > 0) {
    $isRentMode = true;
    $showPrice = $product['price_rent'];
}

$unitLabel = $isRentMode ? (($product['unit'] == 2) ? "/ ngày" : "/ giờ") : "";
$backLink = $isRentMode ? 'index.php?view=rent' : 'index.php';
$actionText = $isRentMode ? 'THUÊ NGAY' : 'MUA NGAY';

// 5. XỬ LÝ GALLERY
$gallery = json_decode($product['gallery'], true);
if (!is_array($gallery)) $gallery = [];
if (!empty($product['thumb'])) {
    $gallery = array_values(array_filter($gallery, function ($img) use ($product) {
        return $img !== $product['thumb'];
    }));
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mã số: <?= $product['title'] ?> | TRỌNG 2K8 SHOP</title>

    <!-- CSS & FONT -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" />
    
    <!-- [FIX QUAN TRỌNG] INLINE CSS: Đọc file CSS và in thẳng vào HTML để tránh lỗi mạng -->
    <style>
        <?php
            $cssPath = __DIR__ . '/assets/css/style.css';
            if (file_exists($cssPath)) {
                include $cssPath;
            } else {
                echo "/* KHÔNG TÌM THẤY FILE CSS TẠI: $cssPath */";
            }
        ?>
    </style>
</head>

<body>

    <!-- HEADER -->
    <header class="main-header">
        <div class="container d-flex justify-content-between align-items-center">
            <a href="./" class="text-decoration-none">
                <div class="logo-text"><i class="ph-fill ph-heart"></i> TRỌNG 2K8</div>
            </a>
            <a href="<?= $backLink ?>" class="btn btn-outline-secondary rounded-pill px-3 fw-bold"
                style="font-size: 14px;">
                <i class="ph-bold ph-arrow-left"></i> Quay lại
            </a>
        </div>
    </header>

    <div class="container py-4 detail-container">

        <!-- 1. KHỐI THÔNG TIN -->
        <div class="detail-header">
            <h1 class="detail-title">
                <?php if ($isRentMode): ?>
                <span class="badge bg-primary align-middle" style="font-size: 14px;">THUÊ</span>
                <?php else: ?>
                <span class="badge bg-warning text-dark align-middle" style="font-size: 14px;">BÁN</span>
                <?php endif; ?>
                Mã số: <?= $product['title'] ?>
            </h1>

            <div class="text-secondary mb-3 small">
                <i class="ph-fill ph-eye"></i> <?= number_format($product['views']) ?> xem &bull;
                <i class="ph-bold ph-clock"></i> <?= date('d/m/Y', strtotime($product['created_at'])) ?>
            </div>

            <div class="detail-price-lg">
                <span class="text-secondary fw-normal" style="font-size: 18px; vertical-align: middle;">Giá: </span>
                <?= formatPrice($showPrice) ?>
                <small style="font-size: 16px; font-weight: normal; color: #6b7280;"><?= $unitLabel ?></small>
            </div>

            <?php if ($product['status'] == 1): ?>
            <button onclick="buyNow()" class="btn-buy-lg">
                <i class="ph-bold ph-shopping-cart"></i> <?= $actionText ?> (QUA ZALO)
            </button>
            <div class="mt-3 text-secondary fst-italic small">
                <i class="ph-fill ph-shield-check text-success"></i> Giao dịch tự động hoặc trung gian uy tín 100%
            </div>
            <?php else: ?>
            <button class="btn btn-secondary w-100 py-3 rounded-pill fw-bold mt-3" disabled>
                ĐÃ BÁN / ĐANG CÓ NGƯỜI THUÊ
            </button>
            <?php endif; ?>
        </div>

        <!-- 2. DANH SÁCH ẢNH -->
        <div class="feed-gallery" id="galleryFeed">
            <div class="feed-item">
                <a href="uploads/<?= $product['thumb'] ?>" data-fancybox="gallery">
                    <img src="uploads/<?= $product['thumb'] ?>" alt="Ảnh bìa">
                </a>
            </div>
        </div>

        <!-- 3. LOADING -->
        <div class="loading-spinner" id="loadingIcon">
            <div class="spinner-icon"></div>
            <div class="mt-2 text-secondary small">Đang tải thêm ảnh...</div>
        </div>

    </div>

    <footer>
        <div class="container">
            <p class="mb-1 fw-bold text-uppercase">&copy; 2024 TRỌNG 2K8 SHOP</p>
            <p class="mb-0 text-secondary">Hỗ trợ Zalo: <span class="text-dark fw-bold">0984.074.897</span></p>
        </div>
    </footer>

    <!-- SCRIPTS -->
    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
    <script>
    Fancybox.bind("[data-fancybox]", {
        Thumbs: { type: "modern" }
    });

    const galleryImages = <?= json_encode($gallery) ?>;
    let currentIndex = 0;
    const loadBatch = 3;
    const feedContainer = document.getElementById('galleryFeed');
    const loadingEl = document.getElementById('loadingIcon');
    let isLoading = false;

    function renderImages(count) {
        const max = Math.min(currentIndex + count, galleryImages.length);
        for (let i = currentIndex; i < max; i++) {
            const imgName = galleryImages[i];
            const div = document.createElement('div');
            div.className = 'feed-item';
            div.innerHTML = `
                    <a href="uploads/${imgName}" data-fancybox="gallery">
                        <img src="uploads/${imgName}" loading="lazy" alt="Ảnh chi tiết">
                    </a>
                `;
            feedContainer.appendChild(div);
        }
        currentIndex = max;
        if (currentIndex >= galleryImages.length) {
            loadingEl.remove();
            window.removeEventListener('scroll', handleScroll);
        }
    }

    function handleScroll() {
        if (isLoading) return;
        if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 600) {
            if (currentIndex < galleryImages.length) {
                loadMore();
            }
        }
    }

    function loadMore() {
        isLoading = true;
        loadingEl.style.display = 'block';
        setTimeout(() => {
            renderImages(loadBatch);
            isLoading = false;
            if (currentIndex < galleryImages.length) loadingEl.style.display = 'none';
        }, 500);
    }

    if (galleryImages.length > 0) {
        renderImages(2);
    } else {
        loadingEl.remove();
    }
    window.addEventListener('scroll', handleScroll);

    function buyNow() {
        var code = "<?= $product['title'] ?>";
        var price = "<?= formatPrice($showPrice) ?>";
        var unitLabel = "<?= $unitLabel ?>";
        var url = window.location.href;
        var actionText = "<?= $actionText ?>";
        var zaloPhone = "0984074897";
        var content = `Chào Shop, mình muốn ${actionText} Acc Mã Số: ${code} - Giá: ${price}${unitLabel}.\nLink: ${url}`;
        var zaloLink = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent) ?
            `https://zalo.me/${zaloPhone}?text=${encodeURIComponent(content)}` :
            `https://chat.zalo.me/?phone=${zaloPhone}`;
        window.open(zaloLink, '_blank');
    }
    </script>
</body>
</html>