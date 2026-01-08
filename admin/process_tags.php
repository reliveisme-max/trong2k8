<?php
// admin/process_tags.php
require_once 'auth.php';
require_once '../includes/config.php';

$action = $_REQUEST['action'] ?? '';

// 1. THÊM TAG
if ($action == 'add' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $group = $_POST['group_type'];

    if (!empty($name)) {
        $stmt = $conn->prepare("INSERT INTO tags (name, group_type) VALUES (:n, :g)");
        $stmt->execute([':n' => $name, ':g' => $group]);
    }
    header("Location: tags.php");
    exit;
}

// 2. XÓA TAG
if ($action == 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    // Xóa tag trong bảng tags
    $conn->prepare("DELETE FROM tags WHERE id = :id")->execute([':id' => $id]);

    // Xóa liên kết trong bảng product_tags (dọn rác)
    $conn->prepare("DELETE FROM product_tags WHERE tag_id = :id")->execute([':id' => $id]);

    header("Location: tags.php");
    exit;
}

header("Location: tags.php");
exit;