<?php
// admin/add.php - BẢN SIÊU GỌN: CHỈ HIỆN 1 LIST TAG
require_once 'auth.php';
require_once '../includes/config.php';

$role = $_SESSION['role'] ?? 0;
$prefix = $_SESSION['prefix'] ?? '';

// Lấy toàn bộ Tag (Bape, Sảnh Xe...)
$stmtTags = $conn->query("SELECT * FROM tags ORDER BY id ASC");
$allTags = $stmtTags->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Acc Mới</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <aside class="sidebar">
        <div class="brand"><?php if ($role == 1): ?><i class="ph-fill ph-crown"></i> BOSS PANEL<?php else: ?><i
                class="ph-fill ph-user-circle"></i> STAFF PANEL<?php endif; ?></div>
        <nav class="d-flex flex-column gap-2">
            <a href="index.php" class="menu-item"><i class="ph-duotone ph-squares-four"></i> Tổng Quan</a>
            <a href="add.php" class="menu-item active"><i class="ph-duotone ph-plus-circle"></i> Đăng Acc Mới</a>
            <?php if ($role == 1): ?>
            <a href="tags.php" class="menu-item"><i class="ph-duotone ph-tag"></i> Quản lý Tag</a>
            <a href="users.php" class="menu-item"><i class="ph-duotone ph-users"></i> Nhân viên</a>
            <?php endif; ?>
            <a href="change_pass.php" class="menu-item"><i class="ph-duotone ph-lock-key"></i> Đổi mật khẩu</a>
            <div class="mt-auto"><a href="logout.php" class="menu-item text-danger fw-bold"><i
                        class="ph-duotone ph-sign-out"></i> Đăng xuất</a></div>
        </nav>
    </aside>

    <main class="main-content">
        <div class="d-flex align-items-center mb-4">
            <a href="index.php" class="btn btn-light border rounded-pill me-3 px-3 py-2"><i
                    class="ph-bold ph-arrow-left"></i></a>
            <h4 class="m-0 fw-bold text-dark">Đăng Acc Mới</h4>
        </div>

        <form action="process.php" method="POST" enctype="multipart/form-data" id="addForm">
            <div class="row g-4">

                <!-- CỘT TRÁI: THÔNG TIN -->
                <div class="col-12 col-lg-8 order-2 order-lg-1">
                    <div class="form-card mb-4">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Mã Acc / Tiêu đề <span
                                    class="text-danger">*</span></label>
                            <div class="input-group">
                                <?php if (!empty($prefix)): ?>
                                <span class="input-group-text bg-light fw-bold text-secondary"><?= $prefix ?></span>
                                <?php endif; ?>
                                <input type="text" name="title" class="form-control custom-input"
                                    placeholder="Nhập mã...">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-primary"><i class="ph-bold ph-lock-key"></i> Ghi chú
                                nội bộ</label>
                            <textarea name="private_note" class="form-control custom-input" rows="2"
                                placeholder="Giá gốc..."></textarea>
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
                            <div><input class="custom-toggle" type="checkbox" id="switchSell" checked
                                    onchange="toggleSections()"></div>
                        </div>

                        <div id="sellSection" class="mb-4 ps-4 border-start border-4 border-warning">
                            <label class="label-highlight">Giá Bán (VNĐ)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 fw-bold text-success">₫</span>
                                <input type="text" name="price"
                                    class="form-control custom-input price-input-lg border-start-0" placeholder="0"
                                    oninput="formatCurrency(this)">
                            </div>
                        </div>

                        <!-- CHO THUÊ -->
                        <div class="mode-switch-group mt-3 opacity-75">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-info bg-opacity-10 p-2 rounded-3 text-info"><i
                                        class="ph-fill ph-clock-user fs-4"></i></div>
                                <div>
                                    <div class="fw-bold text-dark">Cho Thuê</div>
                                </div>
                            </div>
                            <div><input class="custom-toggle" type="checkbox" id="switchRent"
                                    onchange="toggleSections()"></div>
                        </div>

                        <div id="rentSection" class="mb-4 ps-4 border-start border-4 border-info"
                            style="display: none;">
                            <label class="label-highlight text-info">Giá Thuê (VNĐ)</label>
                            <div class="row g-2">
                                <div class="col-8">
                                    <input type="text" name="price_rent" class="form-control custom-input"
                                        placeholder="0" oninput="formatCurrency(this)">
                                </div>
                                <div class="col-4">
                                    <select name="unit" class="form-select custom-input">
                                        <option value="2">/ Ngày</option>
                                        <option value="1">/ Giờ</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CỘT PHẢI: ẢNH & TAG -->
                <div class="col-12 col-lg-4 order-1 order-lg-2">

                    <!-- 1. ẢNH -->
                    <div class="form-card mb-4 sticky-top" style="top: 20px; z-index: 2;">
                        <label class="form-label fw-bold text-uppercase text-secondary" style="font-size: 12px;">Ảnh Sản
                            Phẩm</label>
                        <div class="image-uploader-area" onclick="document.getElementById('fileInput').click()">
                            <i class="ph-duotone ph-cloud-arrow-up text-secondary" style="font-size: 32px;"></i>
                            <div class="fw-bold mt-2 text-dark small">Tải ảnh lên</div>
                        </div>
                        <input type="file" id="fileInput" name="gallery[]" accept="image/*" multiple hidden>
                        <div id="imageGrid" class="sortable-grid"></div>
                    </div>

                    <!-- 3. TAGS (ĐÃ LÀM GỌN) -->
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
                                class="ph-bold ph-check-circle me-2"></i> ĐĂNG BÁN NGAY</button>
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
</body>

</html>