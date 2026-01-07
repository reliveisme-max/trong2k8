<?php
// index.php - FINAL VERSION: INSTANT SMOOTH PAGINATION
require_once 'includes/config.php';
require_once 'includes/functions.php';

// 1. CẤU HÌNH LOGIC
$viewMode = isset($_GET['view']) && $_GET['view'] == 'rent' ? 'rent' : 'shop';
$keyword  = isset($_GET['q']) ? trim($_GET['q']) : '';
$page     = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit    = 12;
$offset   = ($page - 1) * $limit;
$isAjax   = isset($_GET['ajax']) && $_GET['ajax'] == 1;

// 2. XÂY DỰNG TRUY VẤN
$whereArr = [];
$params = [];

if ($viewMode == 'rent') {
    $whereArr[] = "p.price_rent > 0";
    $priceCol = 'p.price_rent';
    $pageTitleText = "Danh sách Acc Thuê";
} else {
    $whereArr[] = "p.price > 0";
    $priceCol = 'p.price';
    $pageTitleText = "Danh sách Acc Bán";
}

if ($keyword) {
    $whereArr[] = "(p.title LIKE :kw OR p.id = :id)";
    $params[':kw'] = "%$keyword%";
    $params[':id'] = (int)$keyword;
}

if (isset($_GET['min'])) {
    $whereArr[] = "$priceCol >= :min";
    $params[':min'] = (int)$_GET['min'];
}
if (isset($_GET['max'])) {
    $whereArr[] = "$priceCol <= :max";
    $params[':max'] = (int)$_GET['max'];
}

$whereArr[] = "p.status = 1";
$whereSql = !empty($whereArr) ? "WHERE " . implode(" AND ", $whereArr) : "";

try {
    // Đếm tổng số (Dùng để chia trang)
    // Lưu ý: Chỉ đếm khi tải trang lần đầu (không phải Ajax) để tối ưu
    if (!$isAjax) {
        $stmtCount = $conn->prepare("SELECT COUNT(*) FROM products p $whereSql");
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetchColumn();
        $totalPages = ceil($totalRecords / $limit);
        if ($page > $totalPages && $totalPages > 0) $page = $totalPages;
    }

    // Lấy danh sách sản phẩm
    $sql = "SELECT p.*, a.role 
            FROM products p 
            LEFT JOIN admins a ON p.user_id = a.id 
            $whereSql 
            ORDER BY 
                p.is_featured DESC, 
                p.view_order ASC, 
                a.role DESC, 
                p.id DESC 
            LIMIT $limit OFFSET $offset";

    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $val) $stmt->bindValue($key, $val);
    $stmt->execute();
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    if ($isAjax) die();
    die("Lỗi kết nối: " . $e->getMessage());
}

// RENDER CARD
function renderProductCard($p, $viewMode)
{
    $displayPrice = ($viewMode == 'rent') ? $p['price_rent'] : $p['price'];
    $unitLabel = ($viewMode == 'rent') ? (($p['unit'] == 2) ? '/ ngày' : '/ giờ') : '';
    $thumbUrl = 'uploads/' . $p['thumb'];
    if (empty($p['thumb']) || !file_exists($thumbUrl)) $thumbUrl = 'assets/images/no-image.jpg';
    $isVip = ($p['is_featured'] == 1);
    $vipClass = $isVip ? 'border-warning border-2' : '';
?>
<div class="col-12 col-md-4 col-lg-3 feed-item-scroll">
    <div class="product-card <?= $vipClass ?>">
        <a href="detail.php?id=<?= $p['id'] ?>" class="text-decoration-none">
            <div class="product-thumb-box">
                <?php if ($isVip): ?>
                <span
                    class="badge bg-danger position-absolute top-0 start-0 m-2 shadow-sm d-flex align-items-center gap-1"
                    style="z-index:3; font-size:11px;"><i class="ph-fill ph-fire"></i> NỔI BẬT</span>
                <?php endif; ?>
                <?php if ($viewMode == 'rent'): ?>
                <span class="badge bg-primary position-absolute top-0 end-0 m-2" style="z-index:2">THUÊ</span>
                <?php endif; ?>
                <img src="<?= $thumbUrl ?>" class="product-thumb" loading="lazy" alt="<?= $p['title'] ?>">
            </div>
        </a>
        <div class="product-body">
            <div class="d-flex align-items-center mb-2 gap-2">
                <a href="detail.php?id=<?= $p['id'] ?>" class="text-decoration-none product-title m-0">Mã acc:
                    <?= $p['title'] ?></a>
                <button class="btn-copy-code" onclick="copyCode('<?= $p['title'] ?>')"><i class="ph-bold ph-copy"></i>
                    Copy</button>
            </div>
            <div class="d-flex justify-content-between align-items-center small text-secondary">
                <span><i class="ph-fill ph-clock"></i> <?= date('d/m/Y', strtotime($p['created_at'])) ?></span>
                <span><i class="ph-fill ph-eye"></i> <?= number_format($p['views']) ?> xem</span>
            </div>
            <div class="product-meta">
                <div class="price-tag">
                    <span class="text-secondary fw-normal" style="font-size: 14px;">Giá:
                    </span><?= formatPrice($displayPrice) ?>
                    <small style="font-size:12px; font-weight:normal; color:#666"><?= $unitLabel ?></small>
                </div>
                <a href="detail.php?id=<?= $p['id'] ?>" class="btn-detail text-decoration-none">CHI TIẾT</a>
            </div>
        </div>
    </div>
</div>
<?php
}

// === XỬ LÝ AJAX ===
// Chỉ trả về HTML của danh sách sản phẩm, KHÔNG trả về Pagination
if ($isAjax) {
    if (count($products) > 0) {
        foreach ($products as $p) renderProductCard($p, $viewMode);
    } else {
        echo '<div class="col-12 text-center py-5 text-secondary">Không tìm thấy kết quả nào!</div>';
    }
    exit;
}

// === GIAO DIỆN CHÍNH ===
$pageTitle = $pageTitleText . " | TRỌNG 2K8 SHOP";
require_once 'includes/header.php';
?>

<!-- Truyền biến PHP sang JS để xử lý Logic Phân trang -->
<script>
window.totalPages = <?= $totalPages ?>;
window.currentPage = <?= $page ?>;
</script>

<div class="container py-5">

    <!-- BANNER & SEARCH -->
    <a href="https://zalo.me/0984074897" target="_blank" class="text-decoration-none">
        <div class="contact-banner">
            <h3><i class="ph-fill ph-chat-circle-dots"></i> Hỗ trợ giao dịch 24/7 qua Zalo</h3>
            <div class="contact-sub">Uy tín tạo niềm tin - Giao dịch nhanh gọn</div>
        </div>
    </a>

    <div class="search-box-modern">
        <form action="" method="GET" class="position-relative">
            <?php if ($viewMode == 'rent'): ?><input type="hidden" name="view" value="rent"><?php endif; ?>
            <input type="text" name="q" class="search-input-modern" placeholder="Tìm kiếm tên acc, mã số..."
                value="<?= htmlspecialchars($keyword) ?>">
            <?php if (!empty($keyword)): ?>
            <a href="?view=<?= $viewMode ?>" class="search-btn-modern text-white text-decoration-none"><i
                    class="ph-bold ph-x"></i></a>
            <?php else: ?>
            <button type="submit" class="search-btn-modern"><i class="ph-bold ph-magnifying-glass"></i></button>
            <?php endif; ?>
        </form>
    </div>

    <!-- HEADER LIST -->
    <div class="list-header-wrapper align-items-center">
        <div class="d-flex align-items-center gap-2">
            <h4 class="fw-bold m-0" style="color: var(--text-main);">
                <i class="ph-fill ph-squares-four" style="color: var(--accent);"></i> <?= $pageTitleText ?>
            </h4>
            <span class="badge rounded-pill bg-warning text-dark"><?= $totalRecords ?></span>
        </div>
        <div class="toggle-group">
            <a href="?view=shop" class="toggle-btn <?= $viewMode == 'shop' ? 'active' : '' ?>"><i
                    class="ph-bold ph-shopping-cart"></i> MUA ACC</a>
            <a href="?view=rent" class="toggle-btn <?= $viewMode == 'rent' ? 'active' : '' ?>"><i
                    class="ph-bold ph-clock-user"></i> THUÊ ACC</a>
        </div>
    </div>

    <!-- FILTER -->
    <div class="filter-section">
        <a href="?view=<?= $viewMode ?>"
            class="filter-pill <?= (!isset($_GET['min']) && empty($keyword)) ? 'active' : '' ?>">Tất cả</a>
        <?php if ($viewMode == 'shop'): ?>
        <a href="?view=shop&min=0&max=5000000" class="filter-pill <?= checkActive(0, 5000000) ?>">Dưới 5m</a>
        <a href="?view=shop&min=5000000&max=10000000" class="filter-pill <?= checkActive(5000000, 10000000) ?>">5m -
            10m</a>
        <a href="?view=shop&min=10000000&max=20000000" class="filter-pill <?= checkActive(10000000, 20000000) ?>">10m -
            20m</a>
        <a href="?view=shop&min=20000000&max=40000000" class="filter-pill <?= checkActive(20000000, 40000000) ?>">20m -
            40m</a>
        <a href="?view=shop&min=40000000&max=60000000" class="filter-pill <?= checkActive(40000000, 60000000) ?>">40m -
            60m</a>
        <a href="?view=shop&min=60000000" class="filter-pill <?= checkActive(60000000, null) ?>">Trên 60m</a>
        <?php else: ?>
        <a href="?view=rent&min=0&max=20000" class="filter-pill <?= checkActive(0, 20000) ?>">Dưới 20k</a>
        <a href="?view=rent&min=20000&max=50000" class="filter-pill <?= checkActive(20000, 50000) ?>">20k - 50k</a>
        <a href="?view=rent&min=50000&max=100000" class="filter-pill <?= checkActive(50000, 100000) ?>">50k - 100k</a>
        <a href="?view=rent&min=100000" class="filter-pill <?= checkActive(100000, null) ?>">Trên 100k</a>
        <?php endif; ?>
    </div>

    <!-- GRID SẢN PHẨM -->
    <div class="row g-4 position-relative" id="productGrid">
        <?php if (count($products) > 0): ?>
        <?php foreach ($products as $p): renderProductCard($p, $viewMode);
            endforeach; ?>
        <?php else: ?>
        <div class="col-12 text-center py-5">
            <i class="ph-duotone ph-magnifying-glass text-secondary opacity-25" style="font-size: 80px;"></i>
            <p class="text-secondary fw-bold mt-3">Không tìm thấy Acc nào!</p>
            <a href="?view=<?= $viewMode ?>" class="btn btn-warning text-white rounded-pill px-4">Xem tất cả</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- PHÂN TRANG (STATIC HTML - XỬ LÝ BẰNG JS) -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination-wrapper">
        <!-- Nút Trái -->
        <a href="javascript:void(0)" class="page-nav-btn js-prev-btn <?= ($page <= 1) ? 'disabled' : '' ?>"
            onclick="<?= ($page > 1) ? "goToPage($page - 1)" : "" ?>">
            <i class="ph-bold ph-caret-left"></i>
        </a>

        <!-- Viên thuốc chứa số -->
        <div class="page-numbers-capsule">
            <div class="page-numbers-container" id="pagiContainer">
                <?php for ($i = 1; $i <= $totalPages; $i++):
                        $isActive = ($i == $page) ? 'active' : '';
                    ?>
                <a href="javascript:void(0)" onclick="goToPage(<?= $i ?>)" class="page-number-item <?= $isActive ?>"
                    data-page="<?= $i ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Nút Phải -->
        <a href="javascript:void(0)" class="page-nav-btn js-next-btn <?= ($page >= $totalPages) ? 'disabled' : '' ?>"
            onclick="<?= ($page < $totalPages) ? "goToPage($page + 1)" : "" ?>">
            <i class="ph-bold ph-caret-right"></i>
        </a>
    </div>
    <?php endif; ?>

</div>

<!-- SCRIPT HELPER -->
<script>
function copyCode(text) {
    navigator.clipboard.writeText(text).then(function() {
        Swal.fire({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 1500,
            timerProgressBar: true,
            icon: 'success',
            title: 'Đã sao chép!'
        });
    });
}
// (Các logic chuyển trang đã được chuyển sang assets/js/main.js để load nhanh hơn)
</script>

<?php require_once 'includes/footer.php'; ?>