<?php
// detail.php - FINAL VERSION: DUAL PRICE DISPLAY + SIMPLE ZALO LINK
require_once 'includes/config.php';
require_once 'includes/functions.php';

// 1. LẤY ID & KIỂM TRA
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id == 0) {
    header("Location: index.php");
    exit;
}

// 2. LẤY DỮ LIỆU TỪ DB
$stmt = $conn->prepare("SELECT * FROM products WHERE id = :id");
$stmt->execute([':id' => $id]);
$product = $stmt->fetch();

if (!$product) die("Acc không tồn tại!");

// 3. TĂNG VIEW
$conn->prepare("UPDATE products SET views = views + 1 WHERE id = :id")->execute([':id' => $id]);
$product['views']++;

// 4. XỬ LÝ LOGIC HIỂN THỊ GIÁ
$isSell = ($product['price'] > 0);
$isRent = ($product['price_rent'] > 0);
$isDual = ($isSell && $isRent); // Acc vừa bán vừa thuê

// Xác định link quay lại
$backLink = 'index.php';
if (isset($_GET['view']) && $_GET['view'] == 'rent') $backLink = 'index.php?view=rent';

// 5. XỬ LÝ GALLERY ẢNH
$gallery = json_decode($product['gallery'], true);
if (!is_array($gallery)) $gallery = [];
if (!empty($product['thumb'])) {
    $gallery = array_values(array_filter($gallery, function ($img) use ($product) {
        return $img !== $product['thumb'];
    }));
}

// --- CẤU HÌNH HEADER ---
$pageTitle = "Mã số: " . $product['title'] . " | TRỌNG 2K8 SHOP";
$isDetailPage = true;
$backUrl = $backLink;

require_once 'includes/header.php';
?>

<div class="container py-4 detail-container">

    <!-- 1. KHỐI THÔNG TIN SẢN PHẨM -->
    <div class="detail-header">

        <!-- TIÊU ĐỀ & BADGE -->
        <h1 class="detail-title">
            <?php if ($isDual): ?>
            <span class="badge bg-danger align-middle" style="font-size: 14px;">BÁN</span>
            <span class="badge bg-primary align-middle" style="font-size: 14px;">THUÊ</span>
            <?php elseif ($isRent): ?>
            <span class="badge bg-primary align-middle" style="font-size: 14px;">THUÊ</span>
            <?php else: ?>
            <span class="badge bg-danger align-middle" style="font-size: 14px;">BÁN</span>
            <?php endif; ?>
            Mã số: <?= $product['title'] ?>
        </h1>

        <div class="text-secondary mb-4 small">
            <i class="ph-fill ph-eye"></i> <?= number_format($product['views']) ?> xem &bull;
            <i class="ph-bold ph-clock"></i> <?= date('d/m/Y', strtotime($product['created_at'])) ?>
        </div>

        <!-- HIỂN THỊ GIÁ (LOGIC MỚI) -->
        <div class="mb-4">
            <?php if ($isDual): ?>
            <!-- TRƯỜNG HỢP 1: VỪA BÁN VỪA THUÊ (Hiện 2 dòng) -->
            <div class="d-flex flex-column gap-2 align-items-center">
                <div class="fs-4 fw-bold" style="color: var(--price-color);">
                    <i class="ph-fill ph-shopping-cart me-2"></i>Giá Bán: <?= formatPrice($product['price']) ?>
                </div>
                <div class="fs-5 fw-bold text-primary">
                    <i class="ph-fill ph-clock-user me-2"></i>Giá Thuê: <?= formatPrice($product['price_rent']) ?> /
                    <?= ($product['unit'] == 2) ? 'ngày' : 'giờ' ?>
                </div>
            </div>

            <?php elseif ($isRent): ?>
            <!-- TRƯỜNG HỢP 2: CHỈ THUÊ -->
            <div class="detail-price-lg">
                <span class="text-secondary fw-normal fs-5">Giá Thuê: </span>
                <?= formatPrice($product['price_rent']) ?>
                <small class="text-muted fw-normal fs-6">/ <?= ($product['unit'] == 2) ? 'ngày' : 'giờ' ?></small>
            </div>

            <?php else: ?>
            <!-- TRƯỜNG HỢP 3: CHỈ BÁN -->
            <div class="detail-price-lg">
                <span class="text-secondary fw-normal fs-5">Giá Bán: </span>
                <?= formatPrice($product['price']) ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- NÚT MUA (GIỮ NGUYÊN STYLE, ĐỔI LOGIC) -->
        <?php if ($product['status'] == 1): ?>
        <button onclick="openZalo()" class="btn-buy-lg">
            <i class="ph-bold ph-shopping-cart me-2"></i> MÚC NGAY (QUA ZÉP LÀO)
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

    <!-- 2. DANH SÁCH ẢNH (GALLERY) -->
    <div class="feed-gallery" id="galleryFeed">
        <!-- Luôn hiện ảnh bìa đầu tiên -->
        <div class="feed-item">
            <a href="uploads/<?= $product['thumb'] ?>" data-fancybox="gallery">
                <img src="uploads/<?= $product['thumb'] ?>" alt="Ảnh bìa">
            </a>
        </div>
    </div>

    <!-- 3. HIỆU ỨNG LOADING SPINNER -->
    <div class="loading-spinner" id="loadingIcon">
        <div class="spinner-icon"></div>
        <div class="mt-2 text-secondary small">Đang tải thêm ảnh...</div>
    </div>

</div>

<!-- SCRIPTS RIÊNG CHO TRANG CHI TIẾT -->
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
<script>
// Cấu hình Fancybox (Xem ảnh phóng to)
Fancybox.bind("[data-fancybox]", {
    Thumbs: {
        type: "modern"
    }
});

// Logic Lazy Load ảnh (Tải dần khi cuộn trang)
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
    }, 300);
}

// Khởi chạy: Tải trước 2 ảnh đầu
if (galleryImages.length > 0) {
    renderImages(2);
} else {
    loadingEl.remove();
}
window.addEventListener('scroll', handleScroll);

// --- [UPDATE] LOGIC MỞ ZALO ĐƠN GIẢN ---
function openZalo() {
    var zaloPhone = "0984074897"; // Số Zalo của bạn

    // Tự động phát hiện thiết bị
    // Nếu là điện thoại -> Dùng link App (zalo.me)
    // Nếu là PC -> Dùng link Web (chat.zalo.me)
    var isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);

    var zaloLink = isMobile ?
        `https://zalo.me/${zaloPhone}` :
        `https://chat.zalo.me/?phone=${zaloPhone}`;

    window.open(zaloLink, '_blank');
}
</script>

<?php require_once 'includes/footer.php'; ?>