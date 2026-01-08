<?php
// admin/edit.php - UPDATE: CHIA CỘT + LOAD TAG CŨ + TRẠNG THÁI ORDER
require_once 'auth.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}
$id = (int)$_GET['id'];

// 1. LẤY THÔNG TIN SẢN PHẨM
$stmt = $conn->prepare("SELECT * FROM products WHERE id = :id");
$stmt->execute([':id' => $id]);
$product = $stmt->fetch();

if (!$product) die("Acc không tồn tại!");

// 2. LẤY DANH SÁCH TAG CỦA ACC NÀY (Để tí nữa check vào ô)
$stmtProTags = $conn->prepare("SELECT tag_id FROM product_tags WHERE product_id = :id");
$stmtProTags->execute([':id' => $id]);
$currentTags = $stmtProTags->fetchAll(PDO::FETCH_COLUMN); // Mảng các ID tag đã chọn: [1, 5, 8...]

// 3. LẤY TOÀN BỘ TAG TRONG HỆ THỐNG (Để hiển thị list)
$stmtAllTags = $conn->query("SELECT * FROM tags ORDER BY group_type ASC, id DESC");
$allTags = $stmtAllTags->fetchAll();

// Phân nhóm tag
$groupedTags = ['sung' => [], 'xe' => [], 'ao' => [], 'highlight' => [], 'other' => []];
foreach ($allTags as $tag) {
    $g = $tag['group_type'];
    if (isset($groupedTags[$g])) $groupedTags[$g][] = $tag;
    else $groupedTags['other'][] = $tag;
}

// Xử lý dữ liệu hiển thị
$isSell = ($product['price'] > 0);
$isRent = ($product['price_rent'] > 0);
$gallery = json_decode($product['gallery'], true);
if (!is_array($gallery)) $gallery = [];
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
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
                <small class="text-secondary">Cập nhật thông tin & Tag</small>
            </div>
        </div>

        <form action="process.php" method="POST" enctype="multipart/form-data" id="addForm">
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="row g-4">

                <!-- CỘT TRÁI: THÔNG TIN CƠ BẢN -->
                <div class="col-12 col-lg-8">
                    <div class="form-card mb-4">

                        <!-- Trạng thái ẩn/hiện -->
                        <div
                            class="d-flex justify-content-between align-items-center mb-4 p-3 bg-light rounded-4 border">
                            <label class="fw-bold m-0 text-uppercase text-secondary" style="font-size: 13px;">Trạng thái
                                hiển thị</label>
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
                            <label class="form-label fw-bold text-primary"><i class="ph-bold ph-lock-key"></i> Ghi chú
                                nội bộ</label>
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
                            <label class="label-highlight">Giá Bán (VNĐ)</label>
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
                            <label class="label-highlight text-info">Giá Thuê (VNĐ)</label>
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

                <!-- CỘT PHẢI: ẢNH & TAG -->
                <div class="col-12 col-lg-4">

                    <!-- 1. ẢNH -->
                    <div class="form-card mb-4 sticky-top" style="top: 20px; z-index: 2;">
                        <label class="form-label fw-bold text-uppercase text-secondary" style="font-size: 12px;">Ảnh Sản
                            Phẩm</label>
                        <div class="image-uploader-area" onclick="document.getElementById('fileInput').click()">
                            <i class="ph-duotone ph-cloud-arrow-up text-secondary" style="font-size: 32px;"></i>
                            <div class="fw-bold mt-2 text-dark small">Thêm ảnh mới</div>
                        </div>
                        <input type="file" id="fileInput" name="gallery[]" accept="image/*" multiple hidden>
                        <div id="imageGrid" class="sortable-grid"></div>
                    </div>

                    <!-- 2. TRẠNG THÁI ORDER (CHECKBOX) -->
                    <div class="form-card mb-4 bg-light border-0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_order" value="1" id="checkOrder"
                                style="width: 40px; height: 20px;" <?= $product['is_order'] == 1 ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold text-danger ms-2" for="checkOrder">✈️ Acc Order / Ký
                                Gửi</label>
                        </div>
                    </div>

                    <!-- 3. DANH SÁCH TAG (CHECKED NẾU ĐÃ CÓ) -->
                    <div class="form-card">
                        <label class="form-label fw-bold text-uppercase text-secondary mb-3"
                            style="font-size: 12px;">🏷️ Đặc điểm nổi bật</label>

                        <!-- Súng Lab -->
                        <?php if (!empty($groupedTags['sung'])): ?>
                        <div class="mb-3">
                            <label class="d-block fw-bold small text-primary mb-2">🔥 Súng & Lab</label>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($groupedTags['sung'] as $t):
                                        $isChecked = in_array($t['id'], $currentTags) ? 'checked' : '';
                                    ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tags[]"
                                        value="<?= $t['id'] ?>" id="tag_<?= $t['id'] ?>" <?= $isChecked ?>>
                                    <label class="form-check-label small"
                                        for="tag_<?= $t['id'] ?>"><?= $t['name'] ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <hr class="opacity-25">
                        <?php endif; ?>

                        <!-- Xe -->
                        <?php if (!empty($groupedTags['xe'])): ?>
                        <div class="mb-3">
                            <label class="d-block fw-bold small text-primary mb-2">🏎️ Siêu Xe</label>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($groupedTags['xe'] as $t):
                                        $isChecked = in_array($t['id'], $currentTags) ? 'checked' : '';
                                    ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tags[]"
                                        value="<?= $t['id'] ?>" id="tag_<?= $t['id'] ?>" <?= $isChecked ?>>
                                    <label class="form-check-label small"
                                        for="tag_<?= $t['id'] ?>"><?= $t['name'] ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <hr class="opacity-25">
                        <?php endif; ?>

                        <!-- X-Suit -->
                        <?php if (!empty($groupedTags['ao'])): ?>
                        <div class="mb-3">
                            <label class="d-block fw-bold small text-primary mb-2">🧥 X-Suit & Đồ</label>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($groupedTags['ao'] as $t):
                                        $isChecked = in_array($t['id'], $currentTags) ? 'checked' : '';
                                    ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tags[]"
                                        value="<?= $t['id'] ?>" id="tag_<?= $t['id'] ?>" <?= $isChecked ?>>
                                    <label class="form-check-label small"
                                        for="tag_<?= $t['id'] ?>"><?= $t['name'] ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Danh mục chính -->
                        <?php if (!empty($groupedTags['highlight'])): ?>
                        <hr class="opacity-25">
                        <div class="mb-3 p-2 bg-warning bg-opacity-10 rounded">
                            <label class="d-block fw-bold small text-dark mb-2">🌟 Nhóm Danh Mục</label>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($groupedTags['highlight'] as $t):
                                        $isChecked = in_array($t['id'], $currentTags) ? 'checked' : '';
                                    ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tags[]"
                                        value="<?= $t['id'] ?>" id="tag_<?= $t['id'] ?>" <?= $isChecked ?>>
                                    <label class="form-check-label small fw-bold"
                                        for="tag_<?= $t['id'] ?>"><?= $t['name'] ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

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

    <!-- JS LOAD ẢNH CŨ -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const existingImages = <?= json_encode($gallery) ?>;
        // Gọi hàm từ file JS chung để hiện ảnh cũ
        initExistingImages(existingImages);
    });
    </script>
</body>

</html>