<?php
// admin/library.php - UPDATE V4: GRID 8 CỘT + INFINITE SCROLL + CHỌN TẤT CẢ
require_once 'auth.php';
require_once '../includes/config.php';

$msg = '';

// 1. XỬ LÝ XÓA ẢNH (LOGIC MẠNH)
if (isset($_POST['action_delete']) && $_POST['action_delete'] == '1' && !empty($_POST['selected_files'])) {

    $filesToDelete = $_POST['selected_files']; // Mảng tên file
    $countPhysical = 0;
    $countDB = 0;

    foreach ($filesToDelete as $filename) {
        $path = "../uploads/" . $filename;
        if (file_exists($path)) {
            unlink($path);
            $countPhysical++;
        }
        // Xóa Thumb trong DB nếu trùng
        $stmtThumb = $conn->prepare("UPDATE products SET thumb = '' WHERE thumb = :img");
        $stmtThumb->execute([':img' => $filename]);
    }

    // Quét và xóa ảnh trong Gallery của các acc (JSON)
    $stmtAll = $conn->prepare("SELECT id, gallery FROM products");
    $stmtAll->execute();
    $allProducts = $stmtAll->fetchAll();

    foreach ($allProducts as $p) {
        $gallery = json_decode($p['gallery'], true);
        if (is_array($gallery) && !empty($gallery)) {
            $newGallery = [];
            $isChanged = false;
            foreach ($gallery as $img) {
                if (!in_array($img, $filesToDelete)) $newGallery[] = $img;
                else $isChanged = true;
            }
            if ($isChanged) {
                $newJson = json_encode(array_values($newGallery));
                $stmtUpdate = $conn->prepare("UPDATE products SET gallery = :gallery WHERE id = :id");
                $stmtUpdate->execute([':gallery' => $newJson, ':id' => $p['id']]);
                $countDB++;
            }
        }
    }
    $msg = "Đã xóa $countPhysical ảnh và làm sạch $countDB Acc liên quan!";
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Thư viện NFT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="brand"><i class="ph-fill ph-hexagon"></i> ADMIN PANEL</div>
        <nav class="d-flex flex-column gap-2">
            <a href="index.php" class="menu-item"><i class="ph-duotone ph-squares-four"></i> Tổng Quan</a>
            <a href="add.php" class="menu-item"><i class="ph-duotone ph-plus-circle"></i> Đăng Acc Mới</a>
            <a href="library.php" class="menu-item active"><i class="ph-duotone ph-image"></i> Thư viện ảnh</a>
            <a href="change_pass.php" class="menu-item"><i class="ph-duotone ph-lock-key"></i> Đổi mật khẩu</a>
            <div class="mt-auto">
                <div class="border-top border-secondary opacity-25 mb-3"></div>
                <a href="logout.php" class="menu-item text-danger fw-bold"><i class="ph-duotone ph-sign-out"></i> Đăng
                    xuất</a>
            </div>
        </nav>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">

        <!-- HEADER -->
        <div class="top-header mb-4">
            <div class="d-flex align-items-center justify-content-between w-100 flex-wrap gap-3">
                <div>
                    <h4 class="m-0 fw-bold text-dark">Thư viện Assets</h4>
                    <small class="text-secondary">Quản lý toàn bộ ảnh đã tải lên</small>
                </div>

                <div class="d-flex gap-2">
                    <!-- Nút Chọn tất cả -->
                    <button type="button" class="btn btn-white border fw-bold rounded-pill px-3"
                        onclick="selectAllImages()">
                        <i class="ph-bold ph-checks"></i> Chọn tất cả
                    </button>

                    <!-- Nút Tải ảnh -->
                    <a href="add.php" class="btn btn-dark d-flex align-items-center gap-2 rounded-pill px-3 fw-bold">
                        <i class="ph-bold ph-upload-simple"></i> <span class="d-none d-sm-inline">Tải lên</span>
                    </a>
                </div>
            </div>
        </div>

        <form method="POST" id="libForm">
            <input type="hidden" name="action_delete" value="1">

            <!-- LƯỚI ẢNH 8 CỘT (Grid container) -->
            <!-- Dữ liệu sẽ được JS load vào đây -->
            <div class="nft-grid-8" id="mainLibGrid"></div>

            <!-- Loading Spinner -->
            <div id="pageLoading" class="text-center py-5">
                <div class="spinner-border text-warning" role="status"></div>
                <p class="mt-2 text-muted small">Đang tải dữ liệu...</p>
            </div>

            <!-- End of list message -->
            <div id="endOfList" class="text-center py-5 text-muted d-none">
                <i class="ph-duotone ph-check-circle fs-2 mb-2"></i>
                <p>Đã hiển thị toàn bộ ảnh</p>
            </div>

            <!-- THANH CÔNG CỤ NỔI (FLOATING TOOLBAR) -->
            <div class="floating-toolbar" id="floatingBar">
                <div class="floating-count" id="countText">Đã chọn 0</div>

                <button type="button" class="floating-btn-delete" onclick="confirmDelete()">
                    <i class="ph-bold ph-trash"></i> Xóa ngay
                </button>

                <button type="button" class="floating-btn-close" onclick="deselectAll()">
                    <i class="ph-bold ph-x"></i>
                </button>
            </div>

        </form>

        <!-- Khoảng trống đệm dưới cùng -->
        <div style="height: 100px;"></div>
    </main>

    <!-- MOBILE NAV -->
    <div class="bottom-nav">
        <a href="index.php" class="nav-item"><i class="ph-duotone ph-squares-four"></i></a>
        <a href="add.php" class="nav-item">
            <div class="nav-item-add"><i class="ph-bold ph-plus"></i></div>
        </a>
        <a href="library.php" class="nav-item active"><i class="ph-duotone ph-image"></i></a>
    </div>

    <!-- SCRIPT -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    <?php if ($msg): ?>
    Swal.fire({
        icon: 'success',
        title: 'Thành công!',
        text: '<?= $msg ?>',
        confirmButtonColor: '#f59e0b',
        background: '#fff',
        color: '#000'
    });
    <?php endif; ?>

    // --- LOGIC INFINITE SCROLL & CHỌN ẢNH ---
    let currentPage = 1;
    let isLoading = false;
    let hasMore = true;

    // Khởi chạy
    document.addEventListener('DOMContentLoaded', function() {
        loadImages(1);

        // Sự kiện cuộn trang (Window Scroll)
        window.addEventListener('scroll', () => {
            if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 500) {
                if (hasMore && !isLoading) {
                    loadImages(currentPage + 1);
                }
            }
        });
    });

    async function loadImages(page) {
        if (isLoading) return;
        isLoading = true;

        // Hiện loading
        document.getElementById('pageLoading').classList.remove('d-none');

        try {
            const response = await fetch(`get_images.php?page=${page}`);
            const data = await response.json();

            if (data.status === 'success') {
                const grid = document.getElementById('mainLibGrid');

                data.data.forEach(filename => {
                    const div = document.createElement('div');
                    // Sử dụng class nft-card (CSS V4 đã chỉnh vuông + contain)
                    div.className = 'nft-card';
                    div.innerHTML = `
                        <input type="checkbox" name="selected_files[]" value="${filename}" style="display:none">
                        <img src="../uploads/${filename}" loading="lazy" alt="img">
                        <div class="nft-check-icon"><i class="ph-bold ph-check"></i></div>
                    `;

                    // Sự kiện click chọn
                    div.onclick = function() {
                        toggleSelection(this);
                    };

                    grid.appendChild(div);
                });

                hasMore = data.has_more;
                currentPage = page;

                if (!hasMore) {
                    document.getElementById('endOfList').classList.remove('d-none');
                }
            }
        } catch (error) {
            console.error('Lỗi tải ảnh:', error);
        } finally {
            isLoading = false;
            document.getElementById('pageLoading').classList.add('d-none');
        }
    }

    // Toggle chọn 1 ảnh
    function toggleSelection(card) {
        card.classList.toggle('active'); // CSS V4 dùng class .active cho viền cam
        const checkbox = card.querySelector('input');
        checkbox.checked = !checkbox.checked;
        updateToolbar();
    }

    // Chọn tất cả (Chỉ chọn những ảnh đã load)
    function selectAllImages() {
        const cards = document.querySelectorAll('.nft-card');
        let allActive = true;

        // Kiểm tra xem đã chọn hết chưa
        cards.forEach(card => {
            if (!card.classList.contains('active')) allActive = false;
        });

        // Nếu chưa chọn hết -> Chọn tất cả. Nếu đã chọn hết -> Bỏ chọn tất cả
        const targetState = !allActive;

        cards.forEach(card => {
            if (targetState) card.classList.add('active');
            else card.classList.remove('active');

            card.querySelector('input').checked = targetState;
        });
        updateToolbar();
    }

    // Cập nhật thanh công cụ xóa
    function updateToolbar() {
        const checkedCount = document.querySelectorAll('input[name="selected_files[]"]:checked').length;
        const bar = document.getElementById('floatingBar');
        const countText = document.getElementById('countText');

        if (checkedCount > 0) {
            bar.classList.add('active');
            countText.innerText = `Đã chọn ${checkedCount}`;
        } else {
            bar.classList.remove('active');
        }
    }

    // Hủy chọn
    function deselectAll() {
        document.querySelectorAll('.nft-card').forEach(c => {
            c.classList.remove('active');
            c.querySelector('input').checked = false;
        });
        updateToolbar();
    }

    // Xác nhận xóa
    function confirmDelete() {
        const checkedCount = document.querySelectorAll('input[name="selected_files[]"]:checked').length;
        Swal.fire({
            title: `Xóa ${checkedCount} ảnh?`,
            text: "Cảnh báo: Ảnh trong các bài viết liên quan cũng sẽ bị gỡ bỏ!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Xóa vĩnh viễn',
            cancelButtonText: 'Hủy'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('libForm').submit();
            }
        });
    }
    </script>
</body>

</html>