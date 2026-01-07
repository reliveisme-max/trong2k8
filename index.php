<?php
// index.php - FINAL VERSION: SORTING LOGIC + HOT BADGE
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
    $pageTitle = "Danh sách Acc Thuê";
} else {
    $whereArr[] = "p.price > 0";
    $priceCol = 'p.price';
    $pageTitle = "Danh sách Acc Bán";
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
    // Đếm tổng
    if (!$isAjax) {
        $stmtCount = $conn->prepare("SELECT COUNT(*) FROM products p $whereSql");
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetchColumn();
    }

    // [QUERY QUAN TRỌNG] Sắp xếp: Ghim -> Thứ tự -> Boss -> QTV -> Mới nhất
    $sql = "SELECT p.*, a.role 
            FROM products p 
            LEFT JOIN admins a ON p.user_id = a.id 
            $whereSql 
            ORDER BY 
                p.is_featured DESC,  -- 1. Acc Ghim lên đầu
                p.view_order ASC,    -- 2. Theo thứ tự kéo thả
                a.role DESC,         -- 3. Boss (1) xếp trên QTV (0)
                p.id DESC            -- 4. Mới nhất xếp trên
            LIMIT $limit OFFSET $offset";

    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $val) $stmt->bindValue($key, $val);
    $stmt->execute();
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    if ($isAjax) die();
    die("Lỗi kết nối: " . $e->getMessage());
}

// Xử lý Ajax
if ($isAjax) {
    if (count($products) > 0) {
        foreach ($products as $p) renderProductCard($p, $viewMode);
    }
    exit;
}

// Hàm hiển thị Card
function renderProductCard($p, $viewMode)
{
    $displayPrice = ($viewMode == 'rent') ? $p['price_rent'] : $p['price'];
    $unitLabel = ($viewMode == 'rent') ? (($p['unit'] == 2) ? '/ ngày' : '/ giờ') : '';
    $thumbUrl = 'uploads/' . $p['thumb'];
    if (empty($p['thumb'])) $thumbUrl = 'assets/images/no-image.jpg';

    // Kiểm tra VIP
    $isVip = ($p['is_featured'] == 1);
    $vipClass = $isVip ? 'border-warning border-2' : ''; // Viền vàng nếu VIP
?>
<div class="col-12 col-md-4 col-lg-3 feed-item-scroll">
    <div class="product-card <?= $vipClass ?>" style="position: relative;">
        <a href="detail.php?id=<?= $p['id'] ?>" class="text-decoration-none">
            <div class="product-thumb-box">
                <!-- NHÃN NỔI BẬT -->
                <?php if ($isVip): ?>
                <span
                    class="badge bg-danger position-absolute top-0 start-0 m-2 shadow-sm d-flex align-items-center gap-1"
                    style="z-index:3; font-size:11px;">
                    <i class="ph-fill ph-fire"></i> NỔI BẬT
                </span>
                <?php endif; ?>

                <?php if ($viewMode == 'rent'): ?>
                <span class="badge bg-primary position-absolute top-0 end-0 m-2" style="z-index:2">THUÊ</span>
                <?php endif; ?>

                <img src="<?= $thumbUrl ?>" class="product-thumb" loading="lazy" alt="<?= $p['title'] ?>">
            </div>
        </a>
        <div class="product-body">
            <div class="d-flex align-items-center mb-2 gap-2">
                <a href="detail.php?id=<?= $p['id'] ?>" class="text-decoration-none product-title m-0">
                    Mã acc: <?= $p['title'] ?>
                </a>
                <button class="btn-copy-code" onclick="copyCode('<?= $p['title'] ?>')">
                    <i class="ph-bold ph-copy"></i> Copy
                </button>
            </div>

            <div class="d-flex justify-content-between align-items-center small text-secondary">
                <span><i class="ph-fill ph-clock"></i> <?= date('d/m/Y', strtotime($p['created_at'])) ?></span>
                <span><i class="ph-fill ph-eye"></i> <?= number_format($p['views']) ?> xem</span>
            </div>

            <div class="product-meta">
                <div class="price-tag">
                    <span class="text-secondary fw-normal" style="font-size: 14px;">Giá: </span>
                    <?= formatPrice($displayPrice) ?>
                    <small style="font-size:12px; font-weight:normal; color:#666"><?= $unitLabel ?></small>
                </div>
                <a href="detail.php?id=<?= $p['id'] ?>" class="btn-detail text-decoration-none">CHI TIẾT</a>
            </div>
        </div>
    </div>
</div>
<?php
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TRỌNG 2K8 SHOP - Uy Tín Hàng Đầu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
    <?php $cssPath=__DIR__ . '/assets/css/style.css';
    if (file_exists($cssPath)) include $cssPath;
    ?>
    </style>
</head>

<body>

    <header class="main-header">
        <div class="container d-flex justify-content-between align-items-center">
            <a href="./" class="text-decoration-none">
                <div class="logo-text"><i class="ph-fill ph-heart"></i> TRỌNG 2K8</div>
            </a>
            <a href="https://zalo.me/0984074897" target="_blank"
                class="btn btn-outline-warning rounded-pill fw-bold px-4"
                style="color: var(--accent-hover); border-color: var(--accent);">
                <i class="ph-bold ph-phone"></i> 0984.074.897
            </a>
        </div>
    </header>

    <div class="container py-5">
        <a href="https://zalo.me/0984074897" target="_blank" class="text-decoration-none">
            <div class="contact-banner">
                <h3><i class="ph-fill ph-chat-circle-dots"></i> Hỗ trợ giao dịch 24/7 qua Zalo</h3>
                <div class="contact-sub">Uy tín tạo niềm tin - Giao dịch nhanh gọn</div>
            </div>
        </a>

        <!-- SEARCH -->
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
                    <i class="ph-fill ph-squares-four" style="color: var(--accent);"></i> <?= $pageTitle ?>
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

        <!-- FILTERS -->
        <div class="filter-section">
            <a href="?view=<?= $viewMode ?>"
                class="filter-pill <?= (!isset($_GET['min']) && empty($keyword)) ? 'active' : '' ?>">Tất cả</a>
            <?php if ($viewMode == 'shop'): ?>
            <a href="?view=shop&min=0&max=5000000" class="filter-pill <?= checkActive(0, 5000000) ?>">Dưới 5m</a>
            <a href="?view=shop&min=5000000&max=10000000" class="filter-pill <?= checkActive(5000000, 10000000) ?>">5m -
                10m</a>
            <a href="?view=shop&min=10000000&max=20000000"
                class="filter-pill <?= checkActive(10000000, 20000000) ?>">10m - 20m</a>
            <a href="?view=shop&min=20000000&max=40000000"
                class="filter-pill <?= checkActive(20000000, 40000000) ?>">20m - 40m</a>
            <a href="?view=shop&min=40000000&max=60000000"
                class="filter-pill <?= checkActive(40000000, 60000000) ?>">40m - 60m</a>
            <a href="?view=shop&min=60000000" class="filter-pill <?= checkActive(60000000, null) ?>">Trên 60m</a>
            <?php else: ?>
            <a href="?view=rent&min=0&max=20000" class="filter-pill <?= checkActive(0, 20000) ?>">Dưới 20k</a>
            <a href="?view=rent&min=20000&max=50000" class="filter-pill <?= checkActive(20000, 50000) ?>">20k - 50k</a>
            <a href="?view=rent&min=50000&max=100000" class="filter-pill <?= checkActive(50000, 100000) ?>">50k -
                100k</a>
            <a href="?view=rent&min=100000" class="filter-pill <?= checkActive(100000, null) ?>">Trên 100k</a>
            <?php endif; ?>
        </div>

        <!-- PRODUCTS CONTAINER -->
        <div class="row g-4" id="productGrid">
            <?php foreach ($products as $p): renderProductCard($p, $viewMode);
            endforeach; ?>
        </div>

        <!-- EMPTY STATE -->
        <?php if (count($products) == 0): ?>
        <div class="text-center py-5">
            <i class="ph-duotone ph-magnifying-glass text-secondary opacity-25" style="font-size: 80px;"></i>
            <p class="text-secondary fw-bold mt-3">Không tìm thấy Acc nào!</p>
            <a href="?view=<?= $viewMode ?>" class="btn btn-warning text-white rounded-pill px-4">Xem tất cả</a>
        </div>
        <?php endif; ?>

        <!-- LOADING -->
        <div class="text-center py-4" id="loadingIndicator" style="display: none;">
            <div class="spinner-border text-warning" role="status"><span class="visually-hidden">Loading...</span></div>
            <div class="text-secondary mt-2 small">Đang tải thêm acc...</div>
        </div>
        <div class="text-center py-4 text-secondary small" id="endOfList" style="display: none;">Đã hiển thị hết danh
            sách</div>
    </div>

    <footer>
        <div class="container">
            <p class="mb-1 fw-bold text-uppercase">&copy; 2026 TRỌNG 2K8 SHOP</p>
            <p class="mb-0 text-secondary">Hỗ trợ Zalo: <span class="text-dark fw-bold">0984.074.897</span></p>
        </div>
    </footer>

    <script>
    function copyCode(text) {
        navigator.clipboard.writeText(text).then(function() {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 1500,
                timerProgressBar: true
            });
            Toast.fire({
                icon: 'success',
                title: 'Đã sao chép mã!'
            });
        }, function(err) {
            console.error('Lỗi copy: ', err);
        });
    }

    let currentPage = 1;
    let isLoading = false;
    let hasMore = true;
    const urlParams = new URLSearchParams(window.location.search);

    async function loadMoreProducts() {
        if (isLoading || !hasMore) return;
        isLoading = true;
        document.getElementById('loadingIndicator').style.display = 'block';
        currentPage++;
        urlParams.set('page', currentPage);
        urlParams.set('ajax', '1');

        try {
            const response = await fetch('index.php?' + urlParams.toString());
            const html = await response.text();
            if (html.trim() !== "") {
                document.getElementById('productGrid').insertAdjacentHTML('beforeend', html);
            } else {
                hasMore = false;
                document.getElementById('endOfList').style.display = 'block';
                window.removeEventListener('scroll', handleScroll);
            }
        } catch (error) {
            console.error('Lỗi tải trang:', error);
        } finally {
            isLoading = false;
            document.getElementById('loadingIndicator').style.display = 'none';
        }
    }

    function handleScroll() {
        if (isLoading) return;
        const {
            scrollTop,
            scrollHeight,
            clientHeight
        } = document.documentElement;
        if (scrollTop + clientHeight >= scrollHeight - 500) loadMoreProducts();
    }
    window.addEventListener('scroll', handleScroll);
    </script>
</body>

</html>