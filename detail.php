<?php
// detail.php - CLEAN VERSION: TÁCH HEADER & FOOTER
require_once 'includes/config.php';
require_once 'includes/functions.php';

// 1. LẤY ID & KIỂM TRA
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id == 0) {
    header("Location: index.php");
    exit;
}

// 2. LẤY DỮ LIỆU TỪ DB
$stmt = $conn->prepare("SELECT id, title, price, price_rent, type, unit, thumb, gallery, status, views, created_at FROM products WHERE id = :id");
$stmt->execute([':id' => $id]);
$product = $stmt->fetch();

if (!$product) die("Acc không tồn tại!");

// 3. TĂNG VIEW
$conn->prepare("UPDATE products SET views = views + 1 WHERE id = :id")->execute([':id' => $id]);
$product['views']++;

// 4. XỬ LÝ LOGIC HIỂN THỊ (Bán hay Thuê)
$isRentMode = isset($_GET['view']) && $_GET['view'] == 'rent';

// Nếu acc chỉ cho thuê (giá bán = 0) thì tự động chuyển sang chế độ thuê
if (!$isRentMode && $product['price'] == 0 && $product['price_rent'] > 0) {
    $isRentMode = true;
}

$showPrice = $isRentMode ? $product['price_rent'] : $product['price'];
$unitLabel = $isRentMode ? (($product['unit'] == 2) ? "/ ngày" : "/ giờ") : "";
$backLink  = $isRentMode ? 'index.php?view=rent' : 'index.php';
$actionText = $isRentMode ? 'THUÊ NGAY' : 'MÚC NGAY';

// 5. XỬ LÝ GALLERY ẢNH
$gallery = json_decode($product['gallery'], true);
if (!is_array($gallery)) $gallery = [];
// Loại bỏ ảnh bìa khỏi danh sách gallery để tránh trùng
if (!empty($product['thumb'])) {
    $gallery = array_values(array_filter($gallery, function ($img) use ($product) {
        return $img !== $product['thumb'];
    }));
}

// --- CẤU HÌNH HEADER ---
$pageTitle = "Mã số: " . $product['title'] . " | TRỌNG 2K8 SHOP";
$isDetailPage = true; // Báo hiệu cho Header hiện nút "Quay lại"
$backUrl = $backLink; // Link quay lại

require_once 'includes/header.php';
?>

<div class="container py-4 detail-container">

    <!-- 1. KHỐI THÔNG TIN SẢN PHẨM -->
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
            <i class="ph-bold ph-shopping-cart"></i> <?= $actionText ?> (QUA ZÉP LÀO)
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

    <!-- 3. HIỆU ỨNG LOADING KHI CUỘN -->
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

// Logic Lazy Load ảnh (Tải dần khi cuộn trang để web nhẹ)
const galleryImages = <?= json_encode($gallery) ?>;
let currentIndex = 0;
const loadBatch = 3; // Mỗi lần cuộn tải thêm 3 ảnh
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

// Logic nút Mua ngay -> Chuyển qua Zalo
function buyNow() {
    var code = "<?= $product['title'] ?>";
    var price = "<?= formatPrice($showPrice) ?>";
    var unitLabel = "<?= $unitLabel ?>";
    var url = window.location.href;
    var actionText = "<?= $actionText ?>";
    var zaloPhone = "0984074897"; // Số Zalo của bạn

    var content = `Chào Shop, mình muốn ${actionText} Acc Mã Số: ${code} - Giá: ${price}${unitLabel}.\nLink: ${url}`;

    // Tự động phát hiện thiết bị để dùng link phù hợp
    var zaloLink = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent) ?
        `https://zalo.me/${zaloPhone}?text=${encodeURIComponent(content)}` :
        `https://chat.zalo.me/?phone=${zaloPhone}`;

    window.open(zaloLink, '_blank');
}
</script>

<?php require_once 'includes/footer.php'; ?>