<?php
// admin/index.php - V10: ROLE BASED VIEW + AUTHOR + NOTE
require_once 'auth.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

$current_role = $_SESSION['role'] ?? 0; // 1: Boss, 0: QTV
$current_id   = $_SESSION['admin_id'];

// --- 1. XỬ LÝ XÓA NHIỀU (CÓ CHECK QUYỀN) ---
if (isset($_POST['btn_delete_multi']) && !empty($_POST['selected_ids'])) {
    $ids = $_POST['selected_ids'];
    $countDeleted = 0;

    foreach ($ids as $id) {
        $id = (int)$id;

        // [BẢO MẬT] Nếu là QTV, chỉ cho xóa acc của chính mình
        $sqlCheck = "SELECT thumb, gallery, user_id FROM products WHERE id = :id";
        $stmt = $conn->prepare($sqlCheck);
        $stmt->execute([':id' => $id]);
        $prod = $stmt->fetch();

        if ($prod) {
            // Nếu là QTV mà user_id không khớp -> Bỏ qua
            if ($current_role == 0 && $prod['user_id'] != $current_id) {
                continue;
            }

            // Xóa ảnh
            if (!empty($prod['thumb']) && file_exists("../uploads/" . $prod['thumb'])) {
                @unlink("../uploads/" . $prod['thumb']);
            }
            $gallery = json_decode($prod['gallery'], true);
            if (is_array($gallery)) {
                foreach ($gallery as $g) {
                    if (file_exists("../uploads/" . $g)) @unlink("../uploads/" . $g);
                }
            }
            // Xóa DB
            $conn->prepare("DELETE FROM products WHERE id = :id")->execute([':id' => $id]);
            $countDeleted++;
        }
    }
    header("Location: index.php?msg=deleted_multi&count=$countDeleted");
    exit;
}

// --- 2. LỌC & TÌM KIẾM & PHÂN QUYỀN ---
$viewType = isset($_GET['type']) ? $_GET['type'] : '';
$keyword  = isset($_GET['q']) ? trim($_GET['q']) : '';
$page     = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit    = 10;
$offset   = ($page - 1) * $limit;

$whereArr = [];
$params = [];

// A. PHÂN QUYỀN: Nếu là QTV, chỉ hiện acc của mình
if ($current_role == 0) {
    $whereArr[] = "p.user_id = :uid";
    $params[':uid'] = $current_id;
}

// B. BỘ LỌC CŨ
if ($viewType === 'sell') {
    $whereArr[] = "p.price > 0";
} elseif ($viewType === 'rent') {
    $whereArr[] = "p.price_rent > 0";
}

if ($keyword) {
    $whereArr[] = "(p.title LIKE :kw OR p.id = :id)";
    $params[':kw'] = "%$keyword%";
    $params[':id'] = (int)$keyword;
}

$whereSql = !empty($whereArr) ? "WHERE " . implode(" AND ", $whereArr) : "";

// C. TRUY VẤN (JOIN VỚI BẢNG ADMINS ĐỂ LẤY TÊN NGƯỜI ĐĂNG)
$sqlCount = "SELECT COUNT(*) FROM products p $whereSql";
$stmtCount = $conn->prepare($sqlCount);
$stmtCount->execute($params);
$totalRecords = $stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

$sql = "SELECT p.*, a.username as author_name, a.prefix 
        FROM products p 
        LEFT JOIN admins a ON p.user_id = a.id 
        $whereSql 
        ORDER BY p.id DESC LIMIT $limit OFFSET $offset";

$stmt = $conn->prepare($sql);
foreach ($params as $key => $val) $stmt->bindValue($key, $val);
$stmt->execute();
$products = $stmt->fetchAll();

// Thống kê nhanh (Cũng phải phân quyền)
if ($current_role == 1) {
    // Boss thấy tổng toàn sàn
    $totalAcc = $conn->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $countSale = $conn->query("SELECT COUNT(*) FROM products WHERE price > 0")->fetchColumn();
    $countRent = $conn->query("SELECT COUNT(*) FROM products WHERE price_rent > 0")->fetchColumn();
} else {
    // QTV thấy tổng của mình
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
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Acc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <aside class="sidebar">
        <div class="brand">
            <?php if ($current_role == 1): ?>
            <i class="ph-fill ph-crown"></i> BOSS PANEL
            <?php else: ?>
            <i class="ph-fill ph-user-circle"></i> STAFF PANEL
            <?php endif; ?>
        </div>
        <nav class="d-flex flex-column gap-2">
            <a href="index.php" class="menu-item active"><i class="ph-duotone ph-squares-four"></i> Tổng Quan</a>
            <a href="add.php" class="menu-item"><i class="ph-duotone ph-plus-circle"></i> Đăng Acc Mới</a>
            <a href="library.php" class="menu-item"><i class="ph-duotone ph-image"></i> Thư viện ảnh</a>
            <?php if ($current_role == 1): ?>
            <a href="users.php" class="menu-item"><i class="ph-duotone ph-users"></i> Nhân viên</a>
            <?php endif; ?>
            <a href="change_pass.php" class="menu-item"><i class="ph-duotone ph-lock-key"></i> Đổi mật khẩu</a>
            <div class="mt-auto">
                <a href="logout.php" class="menu-item text-danger fw-bold"><i class="ph-duotone ph-sign-out"></i> Đăng
                    xuất</a>
            </div>
        </nav>
    </aside>

    <main class="main-content">
        <div class="content-container">

            <div class="top-header">
                <h4 class="m-0 text-dark">Quản lý sản phẩm</h4>
                <?php if ($current_role == 0): ?>
                <span class="badge bg-success ms-2">QTV: <?= $_SESSION['prefix'] ?></span>
                <?php endif; ?>
            </div>

            <!-- THỐNG KÊ -->
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

            <!-- TOOLBAR -->
            <div class="admin-toolbar">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div class="filter-group">
                        <a href="index.php" class="filter-btn <?= $viewType == '' ? 'active' : '' ?>">Tất cả</a>
                        <a href="index.php?type=sell"
                            class="filter-btn <?= $viewType == 'sell' ? 'active' : '' ?>">Bán</a>
                        <a href="index.php?type=rent"
                            class="filter-btn <?= $viewType == 'rent' ? 'active' : '' ?>">Thuê</a>
                    </div>

                    <form action="" method="GET" class="search-group">
                        <?php if ($viewType): ?><input type="hidden" name="type"
                            value="<?= $viewType ?>"><?php endif; ?>
                        <i class="ph-bold ph-magnifying-glass"></i>
                        <input type="text" name="q" placeholder="Tìm tên, mã số..."
                            value="<?= htmlspecialchars($keyword) ?>">
                    </form>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button type="button" onclick="submitDelete()" id="btnDeleteMulti"
                        class="btn btn-danger btn-sm rounded-pill fw-bold px-3" style="display:none;">
                        <i class="ph-bold ph-trash"></i> Xóa (<span id="countSelect">0</span>)
                    </button>

                    <a href="add.php"
                        class="btn btn-warning btn-sm rounded-pill fw-bold px-3 py-2 d-flex align-items-center gap-2">
                        <i class="ph-bold ph-plus"></i> Đăng Acc
                    </a>
                </div>
            </div>

            <!-- TABLE -->
            <form id="formMultiDelete" method="POST" action="">
                <input type="hidden" name="btn_delete_multi" value="1">
                <div class="card-table desktop-table">
                    <div class="table-responsive">
                        <table class="table align-middle table-hover">
                            <thead>
                                <tr>
                                    <th class="ps-4" width="40"><input type="checkbox" class="form-check-input"
                                            onclick="toggleAll(this)"></th>
                                    <th width="80">Ảnh</th>
                                    <th>Thông tin Acc</th>
                                    <th>Giá tiền</th>
                                    <?php if ($current_role == 1): ?>
                                    <th>Người đăng</th>
                                    <?php endif; ?>
                                    <th>Trạng thái</th>
                                    <th class="text-end pe-4">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $p): ?>
                                <tr>
                                    <td class="ps-4">
                                        <input type="checkbox" name="selected_ids[]" value="<?= $p['id'] ?>"
                                            class="form-check-input item-check" onclick="updateDeleteBtn()">
                                    </td>
                                    <td><img src="../uploads/<?= $p['thumb'] ?>" class="thumb-img" loading="lazy"></td>
                                    <td>
                                        <div class="fw-bold text-dark">#<?= $p['id'] ?> - <?= $p['title'] ?></div>

                                        <!-- HIỆN GHI CHÚ NỘI BỘ -->
                                        <?php if (!empty($p['private_note'])): ?>
                                        <div class="mt-1 text-secondary fst-italic"
                                            style="font-size: 12px; background: #fffbeb; padding: 4px 8px; border-radius: 4px; border: 1px dashed #fcd34d; display: inline-block;">
                                            <i class="ph-fill ph-note-pencil text-warning"></i>
                                            <?= htmlspecialchars($p['private_note']) ?>
                                        </div>
                                        <?php endif; ?>

                                        <div class="d-flex gap-2 mt-2">
                                            <?php if ($p['price'] > 0): ?><span
                                                class="badge-soft badge-sell">BÁN</span><?php endif; ?>
                                            <?php if ($p['price_rent'] > 0): ?><span
                                                class="badge-soft badge-rent">THUÊ</span><?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($p['price'] > 0): ?>
                                        <div class="price-display-sell"><?= formatPrice($p['price']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($p['price_rent'] > 0): ?>
                                        <div class="price-display-rent">
                                            <?= formatPrice($p['price_rent']) ?>/<?= $p['unit'] == 2 ? 'ngày' : 'giờ' ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- CỘT NGƯỜI ĐĂNG (CHỈ BOSS THẤY) -->
                                    <?php if ($current_role == 1): ?>
                                    <td>
                                        <?php if ($p['author_name']): ?>
                                        <div class="fw-bold text-primary"><?= $p['author_name'] ?></div>
                                        <?php if ($p['prefix']): ?>
                                        <small class="text-secondary">(<?= $p['prefix'] ?>)</small>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <span class="text-muted">Ẩn danh</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>

                                    <td>
                                        <?= $p['status'] == 1
                                                ? '<span class="badge-soft badge-status-active">Đang bán</span>'
                                                : '<span class="badge-soft badge-status-sold">Đã bán/Ẩn</span>' ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="../detail.php?id=<?= $p['id'] ?>" target="_blank"
                                            class="btn-action btn-action-view me-1"><i class="ph-bold ph-eye"></i></a>
                                        <a href="edit.php?id=<?= $p['id'] ?>" class="btn-action btn-action-edit me-1"><i
                                                class="ph-bold ph-pencil-simple"></i></a>
                                        <a href="delete.php?id=<?= $p['id'] ?>" class="btn-action btn-action-delete"
                                            onclick="return confirmDelete(event, this.href)"><i
                                                class="ph-bold ph-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-secondary">Không tìm thấy dữ liệu</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>

            <!-- PAGINATION -->
            <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-center py-4">
                <nav>
                    <ul class="pagination">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                            <a class="page-link"
                                href="?page=<?= $i ?>&type=<?= $viewType ?>&q=<?= $keyword ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- MOBILE NAV -->
    <div class="bottom-nav">
        <a href="index.php" class="nav-item active"><i class="ph-duotone ph-squares-four"></i></a>
        <a href="add.php" class="nav-item">
            <div class="nav-item-add"><i class="ph-bold ph-plus"></i></div>
        </a>
        <a href="library.php" class="nav-item"><i class="ph-duotone ph-image"></i></a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('msg') === 'deleted_multi') {
        Swal.fire('Thành công', `Đã xóa ${urlParams.get('count')} Acc`, 'success');
        window.history.replaceState({}, document.title, "index.php");
    } else if (urlParams.get('msg') === 'added') {
        Swal.fire('Thành công', 'Đã đăng acc mới', 'success');
        window.history.replaceState({}, document.title, "index.php");
    } else if (urlParams.get('msg') === 'updated') {
        Swal.fire('Thành công', 'Đã cập nhật acc', 'success');
        window.history.replaceState({}, document.title, "index.php");
    }

    function toggleAll(source) {
        document.querySelectorAll('.item-check').forEach(c => c.checked = source.checked);
        updateDeleteBtn();
    }

    function updateDeleteBtn() {
        const count = document.querySelectorAll('.item-check:checked').length;
        const btn = document.getElementById('btnDeleteMulti');
        document.getElementById('countSelect').innerText = count;
        btn.style.display = count > 0 ? 'inline-block' : 'none';
    }

    function submitDelete() {
        Swal.fire({
            title: 'Xác nhận xóa?',
            text: "Các Acc đã chọn sẽ bị xóa vĩnh viễn!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Xóa ngay',
            cancelButtonText: 'Hủy'
        }).then((result) => {
            if (result.isConfirmed) document.getElementById('formMultiDelete').submit();
        })
    }

    function confirmDelete(e, url) {
        e.preventDefault();
        Swal.fire({
            title: 'Xóa Acc này?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Xóa'
        }).then((res) => {
            if (res.isConfirmed) window.location.href = url;
        });
    }
    </script>
</body>

</html>