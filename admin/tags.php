<?php
// admin/tags.php - QUẢN LÝ TÊN SÚNG/XE/SKIN
require_once 'auth.php';
require_once '../includes/config.php';

// Lấy danh sách tags
$stmt = $conn->query("SELECT * FROM tags ORDER BY group_type ASC, id DESC");
$tags = $stmt->fetchAll();

// Map tên nhóm cho đẹp
$groupNames = [
    'highlight' => '🔥 Nhóm Danh Mục Chính (4 Ô)',
    'sung'      => '🔫 Súng & Skin Lab',
    'xe'        => '🏎️ Xe & Phương tiện',
    'ao'        => '🧥 X-Suit & Trang phục',
    'other'     => '📦 Khác'
];
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Tags</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>

<body>
    <aside class="sidebar">
        <div class="brand"><i class="ph-fill ph-tag"></i> ADMIN TAGS</div>
        <nav class="d-flex flex-column gap-2">
            <a href="index.php" class="menu-item"><i class="ph-duotone ph-arrow-u-up-left"></i> Quay lại Shop</a>
            <a href="tags.php" class="menu-item active"><i class="ph-duotone ph-tag"></i> Quản lý Tags</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="d-flex align-items-center mb-4">
            <h4 class="m-0 fw-bold text-dark">Quản lý Đặc Điểm (Tags)</h4>
        </div>

        <div class="row g-4">
            <!-- FORM THÊM TAG -->
            <div class="col-12 col-md-4">
                <div class="form-card">
                    <h6 class="fw-bold mb-3">Thêm Tag Mới</h6>
                    <form action="process_tags.php" method="POST">
                        <input type="hidden" name="action" value="add">

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Tên Tag</label>
                            <input type="text" name="name" class="form-control custom-input"
                                placeholder="VD: M4 Băng, Tesla..." required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Thuộc Nhóm</label>
                            <select name="group_type" class="form-select custom-input">
                                <option value="sung">🔫 Súng / Lab</option>
                                <option value="xe">🏎️ Xe / Phương tiện</option>
                                <option value="ao">🧥 X-Suit / Quần áo</option>
                                <option value="highlight">🔥 Danh Mục Chính (Hiện Menu)</option>
                                <option value="other">📦 Khác</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 fw-bold">THÊM NGAY</button>
                    </form>
                </div>
            </div>

            <!-- DANH SÁCH TAG -->
            <div class="col-12 col-md-8">
                <div class="card-table">
                    <div class="table-responsive">
                        <table class="table align-middle table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Tên Tag</th>
                                    <th>Nhóm</th>
                                    <th class="text-end pe-4">Xóa</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tags as $t): ?>
                                <tr>
                                    <td class="ps-4 fw-bold"><?= htmlspecialchars($t['name']) ?></td>
                                    <td>
                                        <span class="badge bg-secondary text-white border-0">
                                            <?= $groupNames[$t['group_type']] ?? $t['group_type'] ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="process_tags.php?action=delete&id=<?= $t['id'] ?>"
                                            class="btn btn-sm btn-light text-danger"
                                            onclick="return confirm('Xóa tag này? Acc đang gắn tag này sẽ bị mất tag đó.')">
                                            <i class="ph-bold ph-trash"></i>
                                        </a>
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
</body>

</html>