<?php
// admin/users.php - QUẢN LÝ NHÂN VIÊN (CHỈ BOSS THẤY)
require_once 'auth.php';
require_once '../includes/config.php';

// 1. KIỂM TRA QUYỀN BOSS
// Nếu không phải Boss (role=1) thì đá về trang chủ ngay
if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    echo "<script>alert('Bạn không có quyền truy cập trang này!'); window.location.href='index.php';</script>";
    exit;
}

// 2. XỬ LÝ THÊM NHÂN VIÊN
if (isset($_POST['btn_add_user'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role     = (int)$_POST['role'];
    $prefix   = trim($_POST['prefix']);

    // Validate
    if (empty($username) || empty($password)) {
        $error = "Vui lòng nhập đủ thông tin!";
    } else {
        // Check trùng tên đăng nhập
        $stmt = $conn->prepare("SELECT COUNT(*) FROM admins WHERE username = :u");
        $stmt->execute([':u' => $username]);
        if ($stmt->fetchColumn() > 0) {
            $error = "Tên đăng nhập đã tồn tại!";
        } else {
            // Thêm mới vào DB
            $hash = password_hash($password, PASSWORD_DEFAULT);
            // Nếu là Boss thì không cần prefix (NULL), nếu là QTV thì lấy prefix (viết hoa)
            $savePrefix = ($role == 1) ? NULL : strtoupper($prefix);

            $sql = "INSERT INTO admins (username, password, role, prefix) VALUES (:u, :p, :r, :pre)";
            $conn->prepare($sql)->execute([
                ':u' => $username,
                ':p' => $hash,
                ':r' => $role,
                ':pre' => $savePrefix
            ]);
            $success = "Đã thêm nhân viên thành công!";
        }
    }
}

// 3. XỬ LÝ XÓA NHÂN VIÊN
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Không cho tự xóa chính mình
    if ($id == $_SESSION['admin_id']) {
        $error = "Không thể tự xóa chính mình!";
    } else {
        $conn->prepare("DELETE FROM admins WHERE id = :id")->execute([':id' => $id]);
        $success = "Đã xóa thành công!";
    }
}

// 4. LẤY DANH SÁCH
$users = $conn->query("SELECT * FROM admins ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Nhân viên</title>
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
        <div class="brand"><i class="ph-fill ph-crown"></i> BOSS PANEL</div>
        <nav class="d-flex flex-column gap-2">
            <a href="index.php" class="menu-item"><i class="ph-duotone ph-squares-four"></i> Tổng Quan</a>
            <a href="add.php" class="menu-item"><i class="ph-duotone ph-plus-circle"></i> Đăng Acc Mới</a>
            <a href="library.php" class="menu-item"><i class="ph-duotone ph-image"></i> Thư viện ảnh</a>
            <a href="users.php" class="menu-item active"><i class="ph-duotone ph-users"></i> Nhân viên</a>
            <a href="change_pass.php" class="menu-item"><i class="ph-duotone ph-lock-key"></i> Đổi mật khẩu</a>
            <div class="mt-auto">
                <a href="logout.php" class="menu-item text-danger fw-bold"><i class="ph-duotone ph-sign-out"></i> Đăng
                    xuất</a>
            </div>
        </nav>
    </aside>

    <main class="main-content">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h4 class="m-0 fw-bold text-dark">Quản lý Nhân sự</h4>
        </div>

        <div class="row g-4">
            <!-- CỘT TRÁI: FORM THÊM -->
            <div class="col-12 col-md-4">
                <div class="form-card">
                    <h5 class="fw-bold mb-3">Thêm Tài Khoản</h5>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Tên đăng nhập</label>
                            <input type="text" name="username" class="form-control custom-input" required
                                placeholder="VD: qtv_nam">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mật khẩu</label>
                            <input type="text" name="password" class="form-control custom-input" required
                                placeholder="Nhập mật khẩu...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Chức vụ</label>
                            <select name="role" class="form-select custom-input" id="roleSelect"
                                onchange="togglePrefix()">
                                <option value="0">Cộng tác viên (QTV)</option>
                                <option value="1">BOSS (Quản trị viên)</option>
                            </select>
                        </div>
                        <div class="mb-4" id="prefixGroup">
                            <label class="form-label">Mã định danh (Prefix)</label>
                            <input type="text" name="prefix" class="form-control custom-input fw-bold text-uppercase"
                                placeholder="VD: NAM">
                            <small class="text-secondary" style="font-size: 11px;">Mã số acc sẽ tự tăng theo prefix này
                                (VD: NAM1, NAM2...)</small>
                        </div>
                        <button type="submit" name="btn_add_user" class="btn-submit">THÊM NGAY</button>
                    </form>
                </div>
            </div>

            <!-- CỘT PHẢI: DANH SÁCH -->
            <div class="col-12 col-md-8">
                <div class="card-table">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th class="ps-4">Username</th>
                                    <th>Chức vụ</th>
                                    <th>Prefix (Mã riêng)</th>
                                    <th class="text-end pe-4">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                <tr>
                                    <td class="ps-4 fw-bold"><?= $u['username'] ?></td>
                                    <td>
                                        <?php if ($u['role'] == 1): ?>
                                        <span class="badge bg-danger">BOSS</span>
                                        <?php else: ?>
                                        <span class="badge bg-success">QTV</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($u['prefix']): ?>
                                        <span class="badge bg-light text-dark border fw-bold"><?= $u['prefix'] ?></span>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <?php if ($u['id'] != $_SESSION['admin_id']): ?>
                                        <a href="users.php?delete=<?= $u['id'] ?>" class="btn-action text-danger"
                                            onclick="return confirm('Xóa nhân viên này?')">
                                            <i class="ph-bold ph-trash"></i>
                                        </a>
                                        <?php else: ?>
                                        <span class="text-muted small fst-italic">Đang online</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- MOBILE NAV -->
    <div class="bottom-nav">
        <a href="index.php" class="nav-item"><i class="ph-duotone ph-squares-four"></i></a>
        <a href="users.php" class="nav-item active"><i class="ph-duotone ph-users"></i></a>
        <a href="add.php" class="nav-item">
            <div class="nav-item-add"><i class="ph-bold ph-plus"></i></div>
        </a>
        <a href="library.php" class="nav-item"><i class="ph-duotone ph-image"></i></a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Hàm ẩn hiện ô nhập Prefix (Nếu chọn Boss thì ẩn, QTV thì hiện)
    function togglePrefix() {
        const role = document.getElementById('roleSelect').value;
        const group = document.getElementById('prefixGroup');
        group.style.display = (role == 1) ? 'none' : 'block';
    }

    <?php if (isset($success)): ?>
    Swal.fire('Thành công', '<?= $success ?>', 'success');
    // Xóa tham số trên URL để F5 không bị gửi lại form
    window.history.replaceState({}, document.title, "users.php");
    <?php endif; ?>
    <?php if (isset($error)): ?>
    Swal.fire('Thất bại', '<?= $error ?>', 'error');
    <?php endif; ?>
    </script>
</body>

</html>