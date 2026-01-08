<?php
// ajax_search.php - XỬ LÝ ĐẾM KẾT QUẢ TÌM KIẾM
require_once 'includes/config.php';

// Chỉ nhận JSON Post từ Javascript
$input = json_decode(file_get_contents('php://input'), true);

if ($input) {
    $min = isset($input['min']) ? (int)$input['min'] : 0;
    $max = isset($input['max']) ? (int)$input['max'] : 99999999999;
    $tags = isset($input['tags']) ? $input['tags'] : []; // Mảng ID các tag đã chọn

    // Xây dựng câu truy vấn đếm
    $whereArr = [];
    $params = [];

    // 1. Luôn chỉ đếm acc đang bán
    $whereArr[] = "p.status = 1";

    // 2. Lọc Giá Bán (Mặc định tìm theo giá bán, nếu muốn tìm giá thuê thì sửa logic sau)
    $whereArr[] = "p.price >= :min AND p.price <= :max";
    $params[':min'] = $min;
    $params[':max'] = $max;

    // 3. Lọc theo Tag (Nếu có chọn)
    $joinSql = "";
    if (!empty($tags)) {
        // Kỹ thuật: Tìm các sản phẩm có ÍT NHẤT 1 trong các tag đã chọn
        $joinSql = "JOIN product_tags pt ON p.id = pt.product_id";

        // Tạo chuỗi placeholder (?,?,?)
        $inQuery = implode(',', array_fill(0, count($tags), '?'));

        $whereArr[] = "pt.tag_id IN ($inQuery)";

        // Merge tham số tag vào params
        foreach ($tags as $t) {
            $params[] = $t; // Dùng ? nên không cần key :name
        }
    }

    $whereSql = "WHERE " . implode(" AND ", $whereArr);

    try {
        // Dùng DISTINCT p.id để tránh đếm trùng 1 acc nhiều lần
        $sql = "SELECT COUNT(DISTINCT p.id) FROM products p $joinSql $whereSql";
        $stmt = $conn->prepare($sql);

        // Bind tham số (Vì vừa có :name vừa có ?, nên ta execute theo thứ tự)
        // Lưu ý: PDO không hỗ trợ mix giữa named (:min) và positional (?) tốt trong 1 số driver cũ.
        // NÊN CHUYỂN HẾT VỀ ? CHO CHẮC.

        // --- VIẾT LẠI QUERY DẠNG ? ĐỂ AN TOÀN ---
        $w = ["p.status = 1", "p.price >= ?", "p.price <= ?"];
        $p = [$min, $max];

        if (!empty($tags)) {
            $inQ = implode(',', array_fill(0, count($tags), '?'));
            $w[] = "pt.tag_id IN ($inQ)";
            foreach ($tags as $tagId) $p[] = $tagId;
        }

        $sqlFinal = "SELECT COUNT(DISTINCT p.id) FROM products p $joinSql WHERE " . implode(" AND ", $w);

        $stmtFinal = $conn->prepare($sqlFinal);
        $stmtFinal->execute($p);

        $count = $stmtFinal->fetchColumn();

        echo json_encode(['status' => 'success', 'count' => $count]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
}