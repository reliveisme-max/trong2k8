<?php
// index.php - V3: SẮP XẾP 3 TẦNG + GIAO DIỆN LỌC MỚI
require_once 'includes/config.php';
require_once 'includes/functions.php';

// 1. NHẬN DỮ LIỆU ĐẦU VÀO
$viewMode = isset($_GET['view']) && $_GET['view'] == 'rent' ? 'rent' : 'shop';
$keyword  = isset($_GET['q']) ? trim($_GET['q']) : '';
$page     = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit    = 12;
$offset   = ($page - 1) * $limit;
$isAjax   = isset($_GET['ajax']) && $_GET['ajax'] == 1;

$tagIds   = isset($_GET['tag_ids']) ? $_GET['tag_ids'] : '';
$sort     = isset($_GET['sort']) ? $_GET['sort'] : 'newest'; // Mặc định mới nhất

// 2. XÂY DỰNG CÂU TRUY VẤN
$whereArr = [];
$params = [];

// A. Phân loại Bán / Thuê
if ($viewMode == 'rent') {
    $whereArr[] = "p.price_rent > 0";
    $priceCol = 'p.price_rent';
    $pageTitleText = "Danh sách Acc Thuê";
} else {
    $whereArr[] = "p.price > 0";
    $priceCol = 'p.price';
    $pageTitleText = "Danh sách Acc Bán";
}

// B. Tìm kiếm từ khóa
if ($keyword) {
    $whereArr[] = "(p.title LIKE ? OR p.id = ? OR t.name LIKE ?)";
    $params[] = "%$keyword%";
    $params[] = (int)$keyword;
    $params[] = "%$keyword%";
}

// C. Lọc theo Tag ID
if (!empty($tagIds)) {
    $tIds = explode(',', $tagIds);
    $inQuery = implode(',', array_fill(0, count($tIds), '?'));
    $whereArr[] = "pt.tag_id IN ($inQuery)";
    foreach ($tIds as $tid) {
        $params[] = (int)$tid;
    }
}

// D. Lọc Giá
if (isset($_GET['min']) && is_numeric($_GET['min'])) {
    $whereArr[] = "$priceCol >= ?";
    $params[] = (int)$_GET['min'];
}
if (isset($_GET['max']) && is_numeric($_GET['max'])) {
    $whereArr[] = "$priceCol <= ?";
    $params[] = (int)$_GET['max'];
}

$whereArr[] = "p.status = 1";
$whereSql = !empty($whereArr) ? "WHERE " . implode(" AND ", $whereArr) : "";

// --- [LOGIC SẮP XẾP 3 TẦNG] ---
// Tầng 1: Ghim (is_featured DESC)
// Tầng 2: Boss > CTV (a.role DESC) - Boss là 1, CTV là 0
// Tầng 3: User chọn (Giá hoặc Thời gian)
switch ($sort) {
    case 'price_asc':
        $userSort = "$priceCol ASC"; // Giá thấp -> cao
        break;
    case 'price_desc':
        $userSort = "$priceCol DESC"; // Giá cao -> thấp
        break;
    default:
        $userSort = "p.created_at DESC"; // Mới nhất
        break;
}

try {
    $joinSql = "LEFT JOIN product_tags pt ON p.id = pt.product_id 
                LEFT JOIN tags t ON pt.tag_id = t.id
                LEFT JOIN admins a ON p.user_id = a.id";

    // 1. Đếm tổng
    if (!$isAjax) {
        $stmtCount = $conn->prepare("SELECT COUNT(DISTINCT p.id) FROM products p $joinSql $whereSql");
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetchColumn();
        $totalPages = ceil($totalRecords / $limit);
        if ($page > $totalPages && $totalPages > 0) $page = $totalPages;
    }

    // 2. Lấy dữ liệu (Áp dụng ORDER BY 3 tầng)
    $sql = "SELECT DISTINCT p.*, a.role 
            FROM products p 
            $joinSql
            $whereSql 
            ORDER BY 
                p.is_featured DESC,  -- Tầng 1: Ghim
                a.role DESC,         -- Tầng 2: Boss trước, CTV sau
                $userSort            -- Tầng 3: Theo ý khách (Trong cùng phân khúc)
            LIMIT $limit OFFSET $offset";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    if ($isAjax) die();
    die("Lỗi: " . $e->getMessage());
}

// HÀM HIỂN THỊ THẺ SẢN PHẨM (GIỮ NGUYÊN)
function renderProductCard($p, $viewMode)
{
    $displayPrice = ($viewMode == 'rent') ? $p['price_rent'] : $p['price'];
    $unitLabel = ($viewMode == 'rent') ? (($p['unit'] == 2) ? '/ ngày' : '/ giờ') : '';
    $thumbUrl = 'uploads/' . $p['thumb'];
    if (empty($p['thumb']) || !file_exists($thumbUrl)) $thumbUrl = 'assets/images/no-image.jpg';

    $badgeRent = ($viewMode == 'rent')
        ? '<span class="badge bg-primary position-absolute top-0 end-0 m-2 shadow-sm" style="z-index:3; font-size:11px;">THUÊ</span>'
        : '';

    $isVip = ($p['is_featured'] == 1);
?>
    <div class="col-12 col-md-6 col-lg-4 feed-item-scroll">
        <div class="product-card">
            <a href="detail.php?id=<?= $p['id'] ?>" class="text-decoration-none">
                <div class="product-thumb-box">
                    <?php if ($isVip): ?>
                        <span
                            class="badge bg-danger position-absolute top-0 start-0 m-2 shadow-sm d-flex align-items-center gap-1"
                            style="z-index:3; font-size:11px;">
                            <i class="ph-fill ph-fire"></i> NỔI BẬT
                        </span>
                    <?php endif; ?>
                    <?= $badgeRent ?>
                    <img src="<?= $thumbUrl ?>" class="product-thumb" loading="lazy" alt="<?= $p['title'] ?>">
                </div>
            </a>
            <div class="product-body">
                <div class="d-flex align-items-center mb-2 gap-2">
                    <a href="detail.php?id=<?= $p['id'] ?>" class="text-decoration-none product-title m-0">
                        Mã: <?= $p['title'] ?>
                    </a>
                    <button class="btn-copy-code" onclick="copyCode('<?= $p['title'] ?>')"><i class="ph-bold ph-copy"></i>
                        Copy</button>
                </div>
                <div class="d-flex justify-content-between align-items-center small text-secondary">
                    <span><i class="ph-fill ph-clock"></i> <?= date('d/m/y', strtotime($p['created_at'])) ?></span>
                    <span><i class="ph-fill ph-eye"></i> <?= number_format($p['views']) ?></span>
                </div>
                <div class="product-meta">
                    <div class="price-tag">
                        <span class="fw-normal text-secondary" style="font-size: 14px;">Giá:
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

if ($isAjax) {
    if (count($products) > 0) {
        foreach ($products as $p) renderProductCard($p, $viewMode);
    } else {
        echo '<div class="col-12 text-center py-5 text-secondary">Không tìm thấy kết quả nào!</div>';
    }
    exit;
}

$pageTitle = $pageTitleText . " | TRỌNG 2K8 SHOP";
require_once 'includes/header.php';
?>

<script>
    window.totalPages = <?= $totalPages ?>;
    window.currentPage = <?= $page ?>;
    window.pageLimit = <?= $limit ?>;
</script>

<div class="container py-4">

    <!-- BANNER & SEARCH -->
    <a href="https://zalo.me/0984074897" target="_blank" class="text-decoration-none">
        <div class="contact-banner">
            <h3><i class="ph-fill ph-chat-circle-dots"></i> Hỗ trợ giao dịch 24/7 qua Zalo</h3>
            <div class="contact-sub">Uy tín tạo niềm tin - Giao dịch nhanh gọn</div>
        </div>
    </a>

    <!-- THANH TÌM KIẾM -->
    <div class="search-box-modern">
        <form action="" method="GET" class="position-relative">
            <?php if ($viewMode == 'rent'): ?><input type="hidden" name="view" value="rent"><?php endif; ?>

            <!-- Giữ lại tham số sort và min/max khi tìm kiếm -->
            <?php if (isset($_GET['sort'])): ?><input type="hidden" name="sort"
                    value="<?= htmlspecialchars($_GET['sort']) ?>"><?php endif; ?>
            <?php if (isset($_GET['min'])): ?><input type="hidden" name="min"
                    value="<?= htmlspecialchars($_GET['min']) ?>"><?php endif; ?>
            <?php if (isset($_GET['max'])): ?><input type="hidden" name="max"
                    value="<?= htmlspecialchars($_GET['max']) ?>"><?php endif; ?>

            <input type="text" name="q" class="search-input-modern" placeholder="Tìm kiếm tên acc, mã số, skin..."
                value="<?= htmlspecialchars($keyword) ?>">

            <?php if (!empty($keyword)): ?>
                <a href="?view=<?= $viewMode ?>" class="search-btn-modern text-white text-decoration-none"><i
                        class="ph-bold ph-x"></i></a>
            <?php else: ?>
                <button type="submit" class="search-btn-modern"><i class="ph-bold ph-magnifying-glass"></i></button>
            <?php endif; ?>
        </form>
    </div>

    <!-- KHU VỰC HEADER DANH SÁCH (PC & MOBILE) -->
    <div class="list-header-wrapper">

        <!-- [PC] TIÊU ĐỀ + NÚT LỌC BÊN CẠNH -->
        <div class="d-none d-lg-flex align-items-center gap-3">
            <div class="d-flex align-items-center gap-2">
                <h4 class="fw-bold m-0" style="color: var(--text-main);">
                    <i class="ph-fill ph-squares-four" style="color: var(--accent);"></i> <?= $pageTitleText ?>
                </h4>
                <span class="badge rounded-pill bg-warning text-dark"><?= $totalRecords ?></span>
            </div>

            <!-- Nút Lọc PC -->
            <form action="" method="GET" id="sortFormPC">
                <?php if ($viewMode == 'rent'): ?><input type="hidden" name="view" value="rent"><?php endif; ?>
                <?php if ($keyword): ?><input type="hidden" name="q"
                        value="<?= htmlspecialchars($keyword) ?>"><?php endif; ?>
                <?php if (isset($_GET['min'])): ?><input type="hidden" name="min"
                        value="<?= htmlspecialchars($_GET['min']) ?>"><?php endif; ?>
                <?php if (isset($_GET['max'])): ?><input type="hidden" name="max"
                        value="<?= htmlspecialchars($_GET['max']) ?>"><?php endif; ?>

                <div class="sort-dropdown-pc">
                    <i class="ph-bold ph-arrows-down-up"></i>
                    <span>Sắp xếp</span> <!-- ĐÃ THÊM DÒNG NÀY -->
                    <select name="sort" onchange="this.form.submit()">
                        <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>>Mới đăng</option>
                        <option value="price_asc" <?= $sort == 'price_asc' ? 'selected' : '' ?>>Giá thấp đến cao
                        </option>
                        <option value="price_desc" <?= $sort == 'price_desc' ? 'selected' : '' ?>>Giá cao đến thấp
                        </option>
                    </select>
                </div>
            </form>
        </div>

        <!-- [MOBILE] TIÊU ĐỀ (Chỉ hiện text) -->
        <div class="d-lg-none d-flex align-items-center gap-2 mb-2">
            <h4 class="fw-bold m-0 fs-5" style="color: var(--text-main);">
                <i class="ph-fill ph-squares-four" style="color: var(--accent);"></i> <?= $pageTitleText ?>
            </h4>
            <span class="badge rounded-pill bg-warning text-dark"><?= $totalRecords ?></span>
        </div>

        <!-- NÚT CHUYỂN SHOP/RENT -->
        <div class="d-flex justify-content-left mb-0">
            <div class="toggle-group">
                <a href="?view=shop" class="toggle-btn <?= $viewMode == 'shop' ? 'active' : '' ?>"><i
                        class="ph-bold ph-shopping-cart"></i> MUA ACC</a>
                <a href="?view=rent" class="toggle-btn <?= $viewMode == 'rent' ? 'active' : '' ?>"><i
                        class="ph-bold ph-clock-user"></i> THUÊ ACC</a>
            </div>
        </div>
    </div>

    <!-- [MOBILE] THANH CÔNG CỤ LỌC RIÊNG -->
    <div class="mobile-tools-bar d-lg-none d-flex justify-content-between align-items-center">
        <div class="text-secondary fw-bold" style="font-size: 13px;">
            <?= number_format($totalRecords) ?> kết quả
        </div>

        <form action="" method="GET" id="sortFormMobile" class="m-0">
            <?php if ($viewMode == 'rent'): ?><input type="hidden" name="view" value="rent"><?php endif; ?>
            <?php if ($keyword): ?><input type="hidden" name="q"
                    value="<?= htmlspecialchars($keyword) ?>"><?php endif; ?>
            <?php if (isset($_GET['min'])): ?><input type="hidden" name="min"
                    value="<?= htmlspecialchars($_GET['min']) ?>"><?php endif; ?>
            <?php if (isset($_GET['max'])): ?><input type="hidden" name="max"
                    value="<?= htmlspecialchars($_GET['max']) ?>"><?php endif; ?>

            <div class="sort-dropdown-mobile">
                <span>Sắp xếp</span> <i class="ph-bold ph-caret-down"></i>
                <select name="sort" onchange="this.form.submit()">
                    <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>>Mới đăng</option>
                    <option value="price_asc" <?= $sort == 'price_asc' ? 'selected' : '' ?>>Giá thấp đến cao</option>
                    <option value="price_desc" <?= $sort == 'price_desc' ? 'selected' : '' ?>>Giá cao đến thấp</option>
                </select>
            </div>
        </form>
    </div>

    <!-- FILTER SECTION (GIÁ) -->
    <div class="filter-section">
        <a href="?view=<?= $viewMode ?>&sort=<?= $sort ?>"
            class="filter-pill <?= (!isset($_GET['min']) && empty($keyword) && empty($tagIds)) ? 'active' : '' ?>">Tất
            cả</a>

        <?php if ($viewMode == 'shop'): ?>
            <a href="?view=shop&sort=<?= $sort ?>&min=0&max=5000000" class="filter-pill <?= checkActive(0, 5000000) ?>">Dưới
                5m</a>
            <a href="?view=shop&sort=<?= $sort ?>&min=5000000&max=10000000"
                class="filter-pill <?= checkActive(5000000, 10000000) ?>">5m - 10m</a>
            <a href="?view=shop&sort=<?= $sort ?>&min=10000000&max=20000000"
                class="filter-pill <?= checkActive(10000000, 20000000) ?>">10m - 20m</a>
            <a href="?view=shop&sort=<?= $sort ?>&min=20000000&max=40000000"
                class="filter-pill <?= checkActive(20000000, 40000000) ?>">20m - 40m</a>
            <a href="?view=shop&sort=<?= $sort ?>&min=40000000&max=60000000"
                class="filter-pill <?= checkActive(40000000, 60000000) ?>">40m - 60m</a>
            <a href="?view=shop&sort=<?= $sort ?>&min=60000000" class="filter-pill <?= checkActive(60000000, null) ?>">Trên
                60m</a>
        <?php else: ?>
            <a href="?view=rent&sort=<?= $sort ?>&min=0&max=100000" class="filter-pill <?= checkActive(0, 100000) ?>">Dưới
                100k</a>
            <a href="?view=rent&sort=<?= $sort ?>&min=100000&max=200000"
                class="filter-pill <?= checkActive(100000, 200000) ?>">100k - 200k</a>
            <a href="?view=rent&sort=<?= $sort ?>&min=200000&max=300000"
                class="filter-pill <?= checkActive(200000, 300000) ?>">200k - 300k</a>
            <a href="?view=rent&sort=<?= $sort ?>&min=300000&max=500000"
                class="filter-pill <?= checkActive(300000, 500000) ?>">300k - 500k</a>
            <a href="?view=rent&sort=<?= $sort ?>&min=500000" class="filter-pill <?= checkActive(500000, null) ?>">Trên
                500k</a>
        <?php endif; ?>
    </div>

    <!-- GRID SẢN PHẨM -->
    <div class="row position-relative" id="productGrid" style="margin: 0 -12px;">
        <?php if (count($products) > 0): ?>
            <?php foreach ($products as $p): renderProductCard($p, $viewMode);
            endforeach; ?>
        <?php else: ?>
            <div class="col-12 empty-state-box">
                <div class="text-center">
                    <i class="ph-duotone ph-magnifying-glass text-secondary opacity-25" style="font-size: 80px;"></i>
                    <p class="text-secondary fw-bold mt-3 mb-4">Không tìm thấy Acc phù hợp!</p>
                    <a href="?view=<?= $viewMode ?>" class="btn btn-warning text-white rounded-pill px-4 fw-bold shadow-sm">
                        <i class="ph-bold ph-arrow-counter-clockwise me-1"></i> Xem tất cả
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination-container-modern">
            <div class="pagi-nav-btn js-prev-btn <?= ($page <= 1) ? 'disabled' : '' ?>"
                onclick="<?= ($page > 1) ? "goToPage($page - 1)" : "" ?>"><i class="ph-bold ph-caret-left"></i></div>
            <div class="position-relative">
                <div class="pagi-main-btn" id="pagiTrigger" onclick="togglePaginationGrid()">
                    <span>Trang <span id="lblCurrentPage"><?= $page ?></span> / <?= $totalPages ?></span>
                    <i class="ph-bold ph-caret-up"></i>
                </div>
                <div class="pagi-dropdown" id="pagiDropdown">
                    <div class="pagi-grid-wrapper">
                        <?php for ($i = 1; $i <= $totalPages; $i++): $isActive = ($i == $page) ? 'active' : ''; ?>
                            <div class="pagi-num <?= $isActive ?>" onclick="goToPage(<?= $i ?>)" data-page="<?= $i ?>"><?= $i ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            <div class="pagi-nav-btn js-next-btn <?= ($page >= $totalPages) ? 'disabled' : '' ?>"
                onclick="<?= ($page < $totalPages) ? "goToPage($page + 1)" : "" ?>"><i class="ph-bold ph-caret-right"></i>
            </div>
        </div>
    <?php endif; ?>

</div>

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
</script>

<?php require_once 'includes/footer.php'; ?>