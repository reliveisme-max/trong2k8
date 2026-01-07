<?php
// admin/edit.php - CLEAN VERSION (NO LIBRARY)
require_once 'auth.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}
$id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM products WHERE id = :id");
$stmt->execute([':id' => $id]);
$product = $stmt->fetch();

if (!$product) die("Acc không tồn tại!");

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
        <div class="brand"><i class="ph-fill ph-heart"></i> ADMIN PANEL</div>
        <nav class="d-flex flex-column gap-2">
            <a href="index.php" class="menu-item"><i class="ph-duotone ph-squares-four"></i> Tổng Quan</a>
            <a href="add.php" class="menu-item"><i class="ph-duotone ph-plus-circle"></i> Đăng Acc Mới</a>
            <a href="change_pass.php" class="menu-item"><i class="ph-duotone ph-lock-key"></i> Đổi mật khẩu</a>
            <div class="mt-auto">
                <div class="border-top border-secondary opacity-25 mb-3"></div>
                <a href="logout.php" class="menu-item text-danger fw-bold"><i class="ph-duotone ph-sign-out"></i> Đăng
                    xuất</a>
            </div>
        </nav>
    </aside>

    <main class="main-content">
        <div class="d-flex align-items-center mb-4">
            <a href="index.php" class="btn btn-light border rounded-pill me-3 px-3 py-2"><i
                    class="ph-bold ph-arrow-left"></i></a>
            <div>
                <h4 class="m-0 fw-bold text-dark">Sửa Acc #<?= $id ?></h4>
                <small class="text-secondary">Cập nhật thông tin sản phẩm</small>
            </div>
        </div>

        <form action="process.php" method="POST" enctype="multipart/form-data" id="addForm">
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="row g-4 justify-content-center">

                <!-- CỘT TRÁI: ẢNH -->
                <div class="col-12 col-lg-5 order-lg-2">
                    <div class="form-card sticky-top" style="top: 20px; z-index: 1;">
                        <label class="form-label fw-bold text-uppercase text-secondary" style="font-size: 12px;">Hình
                            ảnh sản phẩm</label>
                        <div class="text-secondary small mb-3 fst-italic"><i class="ph-fill ph-info"></i> Ảnh đầu tiên
                            là <b>Ảnh Bìa</b>. Kéo thả để sắp xếp.</div>

                        <div class="image-uploader-area" onclick="document.getElementById('fileInput').click()">
                            <i class="ph-duotone ph-cloud-arrow-up text-secondary" style="font-size: 48px;"></i>
                            <div class="fw-bold mt-2 text-dark">Thêm ảnh mới</div>
                        </div>

                        <input type="file" id="fileInput" name="gallery[]" accept="image/*" multiple hidden>
                        <input type="hidden" name="library_images" id="libraryInput">

                        <!-- ĐÃ XÓA NÚT CHỌN TỪ THƯ VIỆN -->

                        <div id="imageGrid" class="sortable-grid"></div>
                        <button type="button" id="toggleGridBtn" class="btn-toggle-view d-none" onclick="toggleGrid()">
                            <i class="ph-bold ph-caret-down"></i> <span id="toggleText">Xem thêm ảnh</span>
                        </button>
                    </div>
                </div>

                <!-- CỘT PHẢI: THÔNG TIN -->
                <div class="col-12 col-lg-7 order-lg-1">
                    <div class="form-card">

                        <!-- Trạng thái -->
                        <div
                            class="d-flex justify-content-between align-items-center mb-4 p-3 bg-light rounded-4 border">
                            <label class="fw-bold m-0 text-uppercase text-secondary" style="font-size: 13px;">Trạng thái
                                hiển thị</label>
                            <div>
                                <input class="custom-toggle" type="checkbox" name="status" value="1"
                                    <?= $product['status'] == 1 ? 'checked' : '' ?>>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Tiêu đề / Mã số <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control custom-input"
                                value="<?= htmlspecialchars($product['title']) ?>" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-primary"><i class="ph-bold ph-lock-key"></i> Ghi chú
                                nội bộ</label>
                            <textarea name="private_note" class="form-control custom-input"
                                rows="2"><?= htmlspecialchars($product['private_note'] ?? '') ?></textarea>
                        </div>

                        <!-- CHẾ ĐỘ BÁN -->
                        <div class="mode-switch-group">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-warning bg-opacity-10 p-2 rounded-3 text-warning"><i
                                        class="ph-fill ph-shopping-cart fs-4"></i></div>
                                <div>
                                    <div class="fw-bold text-dark">Bán Vĩnh Viễn</div>
                                </div>
                            </div>
                            <div>
                                <input class="custom-toggle" type="checkbox" id="switchSell"
                                    <?= $isSell ? 'checked' : '' ?> onchange="toggleSections()">
                            </div>
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

                        <!-- CHẾ ĐỘ THUÊ -->
                        <div class="mode-switch-group">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-info bg-opacity-10 p-2 rounded-3 text-info"><i
                                        class="ph-fill ph-clock-user fs-4"></i></div>
                                <div>
                                    <div class="fw-bold text-dark">Cho Thuê</div>
                                </div>
                            </div>
                            <div>
                                <input class="custom-toggle" type="checkbox" id="switchRent"
                                    <?= $isRent ? 'checked' : '' ?> onchange="toggleSections()">
                            </div>
                        </div>

                        <div id="rentSection" class="mb-4 ps-4 border-start border-4 border-info"
                            style="<?= $isRent ? '' : 'display:none' ?>">
                            <label class="label-highlight" style="color:#0ea5e9;">Giá Thuê (VNĐ)</label>
                            <div class="row g-2">
                                <div class="col-8">
                                    <div class="input-group">
                                        <span
                                            class="input-group-text bg-white border-end-0 fw-bold text-success">₫</span>
                                        <input type="text" name="price_rent"
                                            class="form-control custom-input price-input-lg border-start-0"
                                            value="<?= $product['price_rent'] > 0 ? number_format($product['price_rent']) : '' ?>"
                                            placeholder="0" oninput="formatCurrency(this)">
                                    </div>
                                </div>
                                <div class="col-4">
                                    <select name="unit" class="form-select custom-input h-100 fw-bold">
                                        <option value="2" selected>/ Ngày</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-5">
                            <button type="button" onclick="submitForm()" class="btn-submit">
                                <i class="ph-bold ph-floppy-disk me-2"></i> LƯU THAY ĐỔI
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <div style="height: 80px;"></div>
    </main>

    <div class="bottom-nav">
        <a href="index.php" class="nav-item"><i class="ph-duotone ph-squares-four"></i></a>
        <a href="add.php" class="nav-item active">
            <div class="nav-item-add"><i class="ph-bold ph-plus"></i></div>
        </a>
        <a href="#" class="nav-item disabled" style="opacity:0.3"><i class="ph-duotone ph-image"></i></a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin-add.js?v=<?= time() ?>"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const existingImages = <?= json_encode($gallery) ?>;
        existingImages.forEach(filename => {
            const uid = 'old_' + Math.random().toString(36).substr(2, 9);
            addToGrid(uid, `../uploads/${filename}`, 'lib', filename);
        });
        setTimeout(checkGridHeight, 500);
    });
    </script>
</body>

</html>