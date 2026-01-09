<?php
// admin/edit.php - BẢN SIÊU GỌN
require_once 'auth.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}
$id = (int)$_GET['id'];
$current_role = $_SESSION['role'] ?? 0;
$current_uid  = $_SESSION['admin_id'];

// Lấy thông tin (CÓ CHECK QUYỀN)
$sqlGet = "SELECT * FROM products WHERE id = :id";
if ($current_role == 0) {
    $sqlGet .= " AND user_id = :uid"; // CTV chỉ xem được bài mình
}

$stmt = $conn->prepare($sqlGet);

if ($current_role == 0) {
    $stmt->execute([':id' => $id, ':uid' => $current_uid]);
} else {
    $stmt->execute([':id' => $id]);
}

$product = $stmt->fetch();

if (!$product) {
    // Nếu không tìm thấy hoặc không phải chủ bài -> Đá về trang chủ
    echo "<script>alert('Bạn không có quyền sửa Acc này!'); window.location.href='index.php';</script>";
    exit;
}
// Lấy thông tin
$product = $conn->prepare("SELECT * FROM products WHERE id = :id");
$product->execute([':id' => $id]);
$product = $product->fetch();
if (!$product) die("Acc không tồn tại!");

// Lấy Tag đã chọn
$currentTags = $conn->prepare("SELECT tag_id FROM product_tags WHERE product_id = :id");
$currentTags->execute([':id' => $id]);
$currentTags = $currentTags->fetchAll(PDO::FETCH_COLUMN);

// Lấy toàn bộ Tag
$allTags = $conn->query("SELECT * FROM tags ORDER BY id ASC")->fetchAll();

$isSell = ($product['price'] > 0);
$isRent = ($product['price_rent'] > 0);
$gallery = json_decode($product['gallery'], true);
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sửa Acc #<?= $id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <aside class="sidebar">
        <div class="brand"><i class="ph-fill ph-pencil-simple"></i> EDIT MODE</div>
        <nav class="d-flex flex-column gap-2">
            <a href="index.php" class="menu-item"><i class="ph-duotone ph-squares-four"></i> Tổng Quan</a>
            <a href="add.php" class="menu-item"><i class="ph-duotone ph-plus-circle"></i> Đăng Acc Mới</a>
            <a href="tags.php" class="menu-item"><i class="ph-duotone ph-tag"></i> Quản lý Tag</a>
            <div class="mt-auto"><a href="logout.php" class="menu-item text-danger fw-bold"><i
                        class="ph-duotone ph-sign-out"></i> Đăng xuất</a></div>
        </nav>
    </aside>

    <main class="main-content">
        <div class="d-flex align-items-center mb-4">
            <a href="index.php" class="btn btn-light border rounded-pill me-3 px-3 py-2"><i
                    class="ph-bold ph-arrow-left"></i></a>
            <div>
                <h4 class="m-0 fw-bold text-dark">Sửa Acc #<?= $id ?></h4>
            </div>
        </div>

        <form action="process.php" method="POST" enctype="multipart/form-data" id="addForm">
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="row g-4">
                <div class="col-12 col-lg-8 order-2 order-lg-1">
                    <div class="form-card mb-4">
                        <div
                            class="d-flex justify-content-between align-items-center mb-4 p-3 bg-light rounded-4 border">
                            <label class="fw-bold m-0 text-secondary">TRẠNG THÁI HIỂN THỊ</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="status" value="1"
                                    <?= $product['status'] == 1 ? 'checked' : '' ?> style="width: 40px; height: 20px;">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Mã Acc / Tiêu đề <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control custom-input"
                                value="<?= htmlspecialchars($product['title']) ?>" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-primary">Ghi chú nội bộ</label>
                            <textarea name="private_note" class="form-control custom-input"
                                rows="2"><?= htmlspecialchars($product['private_note'] ?? '') ?></textarea>
                        </div>

                        <!-- GIÁ BÁN -->
                        <div class="mode-switch-group">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-warning bg-opacity-10 p-2 rounded-3 text-warning"><i
                                        class="ph-fill ph-shopping-cart fs-4"></i></div>
                                <div>
                                    <div class="fw-bold text-dark">Bán Vĩnh Viễn</div>
                                </div>
                            </div>
                            <div><input class="custom-toggle" type="checkbox" id="switchSell"
                                    <?= $isSell ? 'checked' : '' ?> onchange="toggleSections()"></div>
                        </div>
                        <div id="sellSection" class="mb-4 ps-4 border-start border-4 border-warning"
                            style="<?= $isSell ? '' : 'display:none' ?>">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 fw-bold text-success">₫</span>
                                <input type="text" name="price"
                                    class="form-control custom-input price-input-lg border-start-0"
                                    value="<?= $product['price'] > 0 ? number_format($product['price']) : '' ?>"
                                    placeholder="0" oninput="formatCurrency(this)">
                            </div>
                        </div>

                        <!-- GIÁ THUÊ -->
                        <div class="mode-switch-group">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-info bg-opacity-10 p-2 rounded-3 text-info"><i
                                        class="ph-fill ph-clock-user fs-4"></i></div>
                                <div>
                                    <div class="fw-bold text-dark">Cho Thuê</div>
                                </div>
                            </div>
                            <div><input class="custom-toggle" type="checkbox" id="switchRent"
                                    <?= $isRent ? 'checked' : '' ?> onchange="toggleSections()"></div>
                        </div>
                        <div id="rentSection" class="mb-4 ps-4 border-start border-4 border-info"
                            style="<?= $isRent ? '' : 'display:none' ?>">
                            <div class="row g-2">
                                <div class="col-8">
                                    <input type="text" name="price_rent" class="form-control custom-input"
                                        value="<?= $product['price_rent'] > 0 ? number_format($product['price_rent']) : '' ?>"
                                        placeholder="0" oninput="formatCurrency(this)">
                                </div>
                                <div class="col-4">
                                    <select name="unit" class="form-select custom-input">
                                        <option value="2" <?= $product['unit'] == 2 ? 'selected' : '' ?>>/ Ngày</option>
                                        <option value="1" <?= $product['unit'] == 1 ? 'selected' : '' ?>>/ Giờ</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-4 order-1 order-lg-2">
                    <div class="form-card mb-4 sticky-top" style="top: 20px; z-index: 2;">
                        <label class="form-label fw-bold text-uppercase text-secondary" style="font-size: 12px;">Ảnh Sản
                            Phẩm</label>
                        <div class="image-uploader-area" onclick="document.getElementById('fileInput').click()">
                            <i class="ph-duotone ph-cloud-arrow-up text-secondary" style="font-size: 32px;"></i>
                            <div class="fw-bold mt-2 text-dark small">Thêm ảnh mới</div>
                        </div>
                        <input type="file" id="fileInput" name="gallery[]" accept="image/*" multiple hidden>
                        <div id="imageGrid" class="sortable-grid"></div>
                        <div id="toggleGridBtn" class="btn-toggle-view d-none" onclick="toggleGrid()">
                            <span id="toggleText">Xem thêm ảnh</span>
                            <i class="ph-bold ph-caret-down ms-1"></i>
                        </div>
                    </div>

                    <div class="form-card">
                        <label class="form-label fw-bold text-uppercase text-secondary mb-3"
                            style="font-size: 12px;">🏷️ Đặc điểm nổi bật</label>
                        <div class="tag-grid-wrapper">
                            <?php foreach ($allTags as $t):
                                // Logic check cho edit.php (nếu add.php thì bỏ dòng này hoặc để trống $isChecked)
                                $isChecked = (isset($currentTags) && in_array($t['id'], $currentTags)) ? 'checked' : '';
                            ?>

                            <!-- Thêm class 'tag-option-card' vào đây -->
                            <div class="form-check tag-option-card">
                                <input class="form-check-input" type="checkbox" name="tags[]" value="<?= $t['id'] ?>"
                                    id="tag_<?= $t['id'] ?>" <?= $isChecked ?>>
                                <label class="form-check-label" for="tag_<?= $t['id'] ?>">
                                    <?= htmlspecialchars($t['name']) ?>
                                </label>
                            </div>

                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="button" onclick="submitForm()" class="btn-submit"><i
                                class="ph-bold ph-floppy-disk me-2"></i> LƯU THAY ĐỔI</button>
                    </div>
                </div>
            </div>
        </form>
        <div style="height: 80px;"></div>
    </main>

    <div class="bottom-nav"><a href="index.php" class="nav-item"><i class="ph-duotone ph-squares-four"></i></a><a
            href="add.php" class="nav-item active">
            <div class="nav-item-add"><i class="ph-bold ph-plus"></i></div>
        </a><a href="#" class="nav-item disabled" style="opacity:0.3"><i class="ph-duotone ph-image"></i></a></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/pages/product-form.js?v=<?= time() ?>"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const existingImages = <?= json_encode($gallery) ?>;
        initExistingImages(existingImages);
    });
    </script>
</body>

</html>