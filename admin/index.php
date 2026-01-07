<?php
// admin/index.php - V27: REFACTORED (CLEAN JS)
require_once 'auth.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

$current_role = $_SESSION['role'] ?? 0;
$current_id   = $_SESSION['admin_id'];

// --- 1. XỬ LÝ XÓA NHIỀU ---
if (isset($_POST['btn_delete_multi']) && !empty($_POST['selected_ids'])) {
    $ids = $_POST['selected_ids'];
    $countDeleted = 0;
    foreach ($ids as $id) {
        $id = (int)$id;
        $sqlCheck = "SELECT thumb, gallery, user_id FROM products WHERE id = :id";
        $stmt = $conn->prepare($sqlCheck);
        $stmt->execute([':id' => $id]);
        $prod = $stmt->fetch();
        if ($prod) {
            if ($current_role == 0 && $prod['user_id'] != $current_id) continue;
            if (!empty($prod['thumb']) && file_exists("../uploads/" . $prod['thumb'])) @unlink("../uploads/" . $prod['thumb']);
            $gallery = json_decode($prod['gallery'], true);
            if (is_array($gallery)) {
                foreach ($gallery as $g) {
                    if (file_exists("../uploads/" . $g)) @unlink("../uploads/" . $g);
                }
            }
            $conn->prepare("DELETE FROM products WHERE id = :id")->execute([':id' => $id]);
            $countDeleted++;
        }
    }
    header("Location: index.php?msg=deleted_multi&count=$countDeleted");
    exit;
}

// --- 2. LẤY DỮ LIỆU ---
$viewType = isset($_GET['type']) ? $_GET['type'] : '';
$keyword  = isset($_GET['q']) ? trim($_GET['q']) : '';
$page     = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit    = 10;
$offset   = ($page - 1) * $limit;

$whereArr = [];
$params = [];

if ($current_role == 0) {
    $whereArr[] = "p.user_id = :uid";
    $params[':uid'] = $current_id;
}
if ($viewType === 'sell') $whereArr[] = "p.price > 0";
elseif ($viewType === 'rent') $whereArr[] = "p.price_rent > 0";
if ($keyword) {
    $whereArr[] = "(p.title LIKE :kw OR p.id = :id)";
    $params[':kw'] = "%$keyword%";
    $params[':id'] = (int)$keyword;
}

$whereSql = !empty($whereArr) ? "WHERE " . implode(" AND ", $whereArr) : "";

// Đếm tổng
$sqlCount = "SELECT COUNT(*) FROM products p $whereSql";
$stmtCount = $conn->prepare($sqlCount);
$stmtCount->execute($params);
$totalRecords = $stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// [QUERY] Sắp xếp: Ghim -> Thứ tự -> Boss -> QTV -> Mới nhất
$sql = "SELECT p.*, a.username as author_name, a.prefix, a.role as author_role
        FROM products p 
        LEFT JOIN admins a ON p.user_id = a.id 
        $whereSql 
        ORDER BY p.is_featured DESC, p.view_order ASC, a.role DESC, p.id DESC 
        LIMIT $limit OFFSET $offset";

$stmt = $conn->prepare($sql);
foreach ($params as $key => $val) $stmt->bindValue($key, $val);
$stmt->execute();
$products = $stmt->fetchAll();

// [QUERY PHỤ] Lấy danh sách TOP GHIM (Để hiện trong Modal)
$featuredList = [];
if ($current_role == 1) {
    $stmtF = $conn->query("SELECT id, title, thumb, price, price_rent, unit FROM products WHERE is_featured = 1 ORDER BY view_order ASC");
    $featuredList = $stmtF->fetchAll();
}

// Thống kê
if ($current_role == 1) {
    $totalAcc = $conn->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $countSale = $conn->query("SELECT COUNT(*) FROM products WHERE price > 0")->fetchColumn();
    $countRent = $conn->query("SELECT COUNT(*) FROM products WHERE price_rent > 0")->fetchColumn();
} else {
    $stmtStat = $conn->prepare("SELECT COUNT(*) FROM products WHERE user_id = ?");
    $stmtStat->execute([$current_id]);
    $totalAcc = $stmtStat->fetchColumn();
    $stmtStat = $conn->prepare("SELECT COUNT(*) FROM products WHERE user_id = ? AND price > 0");
    $stmtStat->execute([$current_id]);
    $countSale = $stmtStat->fetchColumn();
    $stmtStat = $conn->prepare("SELECT COUNT(*) FROM products WHERE user_id = ? AND price_rent > 0");
    $stmtStat->execute([$current_id]);
    $countRent = $stmtStat->fetchColumn();
}

// AJAX Response
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    renderTableBody($products, $current_role);
    echo "<!--DIVIDER-->";
    renderPagination($page, $totalPages);
    exit;
}

function renderTableBody($products, $current_role)
{
    if (empty($products)) {
        echo '<tr><td colspan="7" class="text-center py-5 text-secondary">Không tìm thấy dữ liệu</td></tr>';
        return;
    }
    foreach ($products as $p) {
        $thumb = !empty($p['thumb']) ? "../uploads/" . $p['thumb'] : "../assets/images/no-image.jpg";
        $starClass = ($p['is_featured'] == 1) ? 'ph-fill text-warning' : 'ph-bold text-secondary opacity-25';
        $starAction = ($current_role == 1) ? "onclick=\"toggleStar(this, {$p['id']})\" style=\"cursor:pointer\"" : "";
?>
<tr>
    <td class="ps-4"><input type="checkbox" name="selected_ids[]" value="<?= $p['id'] ?>"
            class="form-check-input item-check" onclick="updateDeleteBtn()"></td>
    <td>
        <img src="<?= $thumb ?>" class="thumb-img" loading="lazy">
        <!-- Star Mobile -->
        <div class="d-md-none position-absolute top-0 start-0 m-2 bg-white rounded-circle shadow-sm d-flex align-items-center justify-content-center"
            style="width:24px; height:24px; z-index:5;" <?= $starAction ?>>
            <i class="<?= $starClass ?> ph-star" style="font-size:14px;"></i>
        </div>
    </td>
    <td>
        <!-- Star PC -->
        <div class="d-flex align-items-center gap-2">
            <i class="<?= $starClass ?> ph-star d-none d-md-block" style="font-size:18px;" <?= $starAction ?>
                title="Ghim/Bỏ ghim"></i>
            <div class="fw-bold text-dark text-break">#<?= $p['id'] ?> - <?= $p['title'] ?></div>
        </div>

        <?php if (!empty($p['private_note'])): ?>
        <div class="mt-1 text-secondary fst-italic note-badge"
            style="font-size: 11px; background: #fffbeb; padding: 2px 6px; border-radius: 4px; border: 1px dashed #fcd34d; display: inline-block;">
            <i class="ph-fill ph-note-pencil text-warning"></i> <?= htmlspecialchars($p['private_note']) ?>
        </div>
        <?php endif; ?>

        <!-- Info Mobile (Gom nhóm) -->
        <div class="d-md-none mt-1 mobile-info-block">
            <?php if ($p['price'] > 0): ?><div class="fw-bold text-danger" style="font-size: 15px; line-height: 1.2;">
                <?= formatPrice($p['price']) ?></div><?php endif; ?>
            <?php if ($p['price_rent'] > 0): ?><div class="fw-bold text-primary"
                style="font-size: 14px; line-height: 1.2;">
                <?= formatPrice($p['price_rent']) ?>/<?= $p['unit'] == 2 ? 'ngày' : 'giờ' ?></div><?php endif; ?>
            <?php if ($current_role == 1): ?><div class="text-muted mt-1" style="font-size: 11px; font-weight: 500;">
                <?= $p['author_name'] ? $p['author_name'] : 'Ẩn danh' ?></div><?php endif; ?>
        </div>

        <div class="d-flex gap-2 mt-2 badge-group-pc">
            <?php if ($p['price'] > 0): ?><span class="badge-soft badge-sell">BÁN</span><?php endif; ?>
            <?php if ($p['price_rent'] > 0): ?><span class="badge-soft badge-rent">THUÊ</span><?php endif; ?>
        </div>
    </td>
    <td class="d-none d-md-table-cell">
        <?php if ($p['price'] > 0): ?><div class="price-display-sell"><?= formatPrice($p['price']) ?></div>
        <?php endif; ?>
        <?php if ($p['price_rent'] > 0): ?><div class="price-display-rent">
            <?= formatPrice($p['price_rent']) ?>/<?= $p['unit'] == 2 ? 'ngày' : 'giờ' ?></div><?php endif; ?>
    </td>
    <?php if ($current_role == 1): ?>
    <td class="d-none d-md-table-cell">
        <?php if ($p['author_name']): ?><div class="fw-bold text-primary"><?= $p['author_name'] ?></div>
        <?php if ($p['prefix']): ?><small
            class="text-secondary">(<?= $p['prefix'] ?>)</small><?php endif; ?><?php else: ?><span class="text-muted">Ẩn
            danh</span><?php endif; ?>
    </td>
    <?php endif; ?>
    <td><?= $p['status'] == 1 ? '<span class="badge-soft badge-status-active">Đang bán</span>' : '<span class="badge-soft badge-status-sold">Đã bán/Ẩn</span>' ?>
    </td>
    <td class="text-end pe-4">
        <a href="../detail.php?id=<?= $p['id'] ?>" target="_blank" class="btn-action btn-action-view me-1"><i
                class="ph-bold ph-eye"></i></a>
        <a href="edit.php?id=<?= $p['id'] ?>" class="btn-action btn-action-edit me-1"><i
                class="ph-bold ph-pencil-simple"></i></a>
        <a href="delete.php?id=<?= $p['id'] ?>" class="btn-action btn-action-delete"
            onclick="return confirmDelete(event, this.href)"><i class="ph-bold ph-trash"></i></a>
    </td>
</tr>
<?php
    }
}

function renderPagination($page, $totalPages)
{
    if ($totalPages <= 1) return;
    $getLink = function ($p) {
        $query = $_GET;
        $query['page'] = $p;
        return '?' . http_build_query($query);
    };
    echo '<nav><ul class="pagination justify-content-center">';
    $prevClass = ($page <= 1) ? 'disabled' : '';
    echo '<li class="page-item ' . $prevClass . '"><a class="page-link" href="' . $getLink($page - 1) . '" onclick="loadPage(' . ($page - 1) . '); return false;"><i class="ph-bold ph-caret-left"></i></a></li>';
    $range = 2;
    $showDotsFirst = false;
    $showDotsLast = false;
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i == 1 || $i == $totalPages || ($i >= $page - $range && $i <= $page + $range)) {
            $active = ($i == $page) ? 'active' : '';
            echo '<li class="page-item ' . $active . '"><a class="page-link" href="' . $getLink($i) . '" onclick="loadPage(' . $i . '); return false;">' . $i . '</a></li>';
        } elseif ($i < $page - $range && !$showDotsFirst) {
            echo '<li class="page-item disabled"><span class="page-link border-0 bg-transparent">...</span></li>';
            $showDotsFirst = true;
        } elseif ($i > $page + $range && !$showDotsLast) {
            echo '<li class="page-item disabled"><span class="page-link border-0 bg-transparent">...</span></li>';
            $showDotsLast = true;
        }
    }
    $nextClass = ($page >= $totalPages) ? 'disabled' : '';
    echo '<li class="page-item ' . $nextClass . '"><a class="page-link" href="' . $getLink($page + 1) . '" onclick="loadPage(' . ($page + 1) . '); return false;"><i class="ph-bold ph-caret-right"></i></a></li>';
    echo '</ul></nav>';
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Acc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
    .sortable-list-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px;
        background: #fff;
        border: 1px solid #e5e7eb;
        margin-bottom: 8px;
        border-radius: 8px;
        cursor: grab;
    }

    .sortable-list-item:active {
        cursor: grabbing;
        background: #f9fafb;
    }

    .sort-index {
        font-weight: 800;
        color: #f59e0b;
        width: 30px;
    }

    .sort-thumb {
        width: 40px;
        height: 40px;
        border-radius: 6px;
        object-fit: cover;
        margin-right: 10px;
    }
    </style>
</head>

<body>
    <aside class="sidebar">
        <div class="brand"><?php if ($current_role == 1): ?><i class="ph-fill ph-crown"></i> BOSS PANEL<?php else: ?><i
                class="ph-fill ph-user-circle"></i> STAFF PANEL<?php endif; ?></div>
        <nav class="d-flex flex-column gap-2">
            <a href="index.php" class="menu-item active"><i class="ph-duotone ph-squares-four"></i> Tổng Quan</a>
            <a href="add.php" class="menu-item"><i class="ph-duotone ph-plus-circle"></i> Đăng Acc Mới</a>
            <?php if ($current_role == 1): ?><a href="users.php" class="menu-item"><i class="ph-duotone ph-users"></i>
                Nhân viên</a><?php endif; ?>
            <a href="change_pass.php" class="menu-item"><i class="ph-duotone ph-lock-key"></i> Đổi mật khẩu</a>
            <div class="mt-auto"><a href="logout.php" class="menu-item text-danger fw-bold"><i
                        class="ph-duotone ph-sign-out"></i> Đăng xuất</a></div>
        </nav>
    </aside>

    <main class="main-content">
        <div class="content-container">
            <div class="top-header">
                <h4 class="m-0 text-dark">Quản lý sản phẩm</h4><?php if ($current_role == 0): ?><span
                    class="badge bg-success ms-2">QTV: <?= $_SESSION['prefix'] ?></span><?php endif; ?>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-12 col-md-4">
                    <div class="stat-card total">
                        <div class="stat-info">
                            <div class="stat-label">Tổng Acc</div>
                            <div class="stat-value"><?= number_format($totalAcc) ?></div>
                        </div>
                        <div class="stat-icon"><i class="ph-duotone ph-shopping-cart"></i></div>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="stat-card sale">
                        <div class="stat-info">
                            <div class="stat-label">Acc Bán</div>
                            <div class="stat-value"><?= number_format($countSale) ?></div>
                        </div>
                        <div class="stat-icon"><i class="ph-duotone ph-tag"></i></div>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="stat-card rent">
                        <div class="stat-info">
                            <div class="stat-label">Acc Thuê</div>
                            <div class="stat-value"><?= number_format($countRent) ?></div>
                        </div>
                        <div class="stat-icon"><i class="ph-duotone ph-clock"></i></div>
                    </div>
                </div>
            </div>

            <div class="admin-toolbar">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div class="filter-group">
                        <a href="#" onclick="applyFilter(''); return false;"
                            class="filter-btn <?= $viewType == '' ? 'active' : '' ?>">Tất cả</a>
                        <a href="#" onclick="applyFilter('sell'); return false;"
                            class="filter-btn <?= $viewType == 'sell' ? 'active' : '' ?>">Bán</a>
                        <a href="#" onclick="applyFilter('rent'); return false;"
                            class="filter-btn <?= $viewType == 'rent' ? 'active' : '' ?>">Thuê</a>
                    </div>
                    <div class="search-group"><i class="ph-bold ph-magnifying-glass"></i><input type="text"
                            id="searchInput" placeholder="Tìm tên, mã số..." value="<?= htmlspecialchars($keyword) ?>"
                            onkeypress="if(event.key === 'Enter') applyFilter();"></div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" onclick="submitDelete()" id="btnDeleteMulti"
                        class="btn btn-danger btn-sm rounded-pill fw-bold px-3" style="display:none;"><i
                            class="ph-bold ph-trash"></i> Xóa (<span id="countSelect">0</span>)</button>
                    <?php if ($current_role == 1): ?>
                    <button type="button"
                        class="btn btn-primary btn-sm rounded-pill fw-bold px-3 py-2 d-flex align-items-center gap-2"
                        data-bs-toggle="modal" data-bs-target="#sortModal"><i class="ph-fill ph-star"></i> Xếp Hạng
                        Top</button>
                    <?php endif; ?>
                    <a href="add.php"
                        class="btn btn-warning btn-sm rounded-pill fw-bold px-3 py-2 d-flex align-items-center gap-2"><i
                            class="ph-bold ph-plus"></i> Đăng Acc</a>
                </div>
            </div>

            <form id="formMultiDelete" method="POST" action=""><input type="hidden" name="btn_delete_multi" value="1">
                <div class="card-table desktop-table position-relative">
                    <div id="ajaxLoading" class="ajax-loading-overlay d-none">
                        <div class="spinner-border text-warning" role="status"></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle table-hover">
                            <thead>
                                <tr>
                                    <th class="ps-4" width="40"><input type="checkbox" class="form-check-input"
                                            onclick="toggleAll(this)"></th>
                                    <th width="80">Ảnh</th>
                                    <th>Thông tin Acc</th>
                                    <th>Giá tiền</th><?php if ($current_role == 1): ?><th>Người đăng</th><?php endif; ?>
                                    <th>Trạng thái</th>
                                    <th class="text-end pe-4">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody"><?php renderTableBody($products, $current_role); ?></tbody>
                        </table>
                    </div>
                </div>
            </form>
            <div id="paginationContainer" class="d-flex justify-content-center py-4">
                <?php renderPagination($page, $totalPages); ?></div>
        </div>
    </main>

    <!-- SORT MODAL -->
    <div class="modal fade" id="sortModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold"><i class="ph-fill ph-star text-warning"></i> Sắp xếp Acc Nổi Bật
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light">
                    <p class="small text-secondary mb-2"><i class="ph-fill ph-info"></i> Kéo thả để thay đổi vị trí.</p>
                    <?php if (empty($featuredList)): ?>
                    <div class="text-center py-4 text-muted">Chưa có acc nào được ghim!</div>
                    <?php else: ?>
                    <div id="sortableList">
                        <?php foreach ($featuredList as $index => $item): ?>
                        <?php $fThumb = !empty($item['thumb']) ? "../uploads/" . $item['thumb'] : "../assets/images/no-image.jpg"; ?>
                        <div class="sortable-list-item" data-id="<?= $item['id'] ?>">
                            <div class="d-flex align-items-center">
                                <div class="sort-index"><?= $index + 1 ?></div><img src="<?= $fThumb ?>"
                                    class="sort-thumb">
                                <div>
                                    <div class="fw-bold text-dark small">#<?= $item['id'] ?> - <?= $item['title'] ?>
                                    </div>
                                    <div class="text-danger small fw-bold">
                                        <?= formatPrice($item['price'] > 0 ? $item['price'] : $item['price_rent']) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="text-secondary"><i class="ph-bold ph-list"></i></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Đóng</button>
                    <?php if (!empty($featuredList)): ?>
                    <button type="button" class="btn btn-warning text-white fw-bold rounded-pill"
                        onclick="saveSortOrder()"><i class="ph-bold ph-floppy-disk"></i> Lưu thứ tự</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="bottom-nav"><a href="index.php" class="nav-item active"><i class="ph-duotone ph-squares-four"></i></a><a
            href="add.php" class="nav-item">
            <div class="nav-item-add"><i class="ph-bold ph-plus"></i></div>
        </a><a href="#" class="nav-item disabled"><i class="ph-duotone ph-image" style="opacity:0.3"></i></a></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- LOAD FILE JS RIÊNG -->
    <script src="assets/js/pages/product-list.js?v=<?= time() ?>"></script>
</body>

</html>