<?php
// bot.php - V61: SEARCH (FULL INFO) & DELETE CLEAN

// --- 1. CẤU HÌNH ---
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

require_once 'includes/config.php';

// Token Bot của bạn
define('BOT_TOKEN', '8412417564:AAH-WRxefi2sXF0EJYNj6Ib3ke3GszCojck');
define('TEMP_DIR', 'temp_data/');

// Danh sách Admin (Nếu có thêm người khác thì điền thêm ID vào mảng này)
$allowed_users = ['5914616789', '8343506927']; 

if (!file_exists(TEMP_DIR)) mkdir(TEMP_DIR, 0777, true);

// =================================================================
// PHẦN 1: XỬ LÝ WEBHOOK TELEGRAM
// =================================================================
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if ($update && isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $text = $message['text'] ?? '';

    // 1. Chặn người lạ
    if (!in_array((string)$chat_id, $allowed_users)) {
        exit;
    }

    // 2. Quản lý trạng thái (Session)
    $sessionFile = TEMP_DIR . $chat_id . '.json';
    $sessionData = file_exists($sessionFile) ? json_decode(file_get_contents($sessionFile), true) : ['mode' => 'normal'];

    // 3. Xử lý Lệnh từ bàn phím
    if ($text === '/start' || $text === '🔍 TRA CỨU') {
        file_put_contents($sessionFile, json_encode(['mode' => 'normal']));
        sendTelegram($chat_id, "🔍 <b>CHẾ ĐỘ TRA CỨU</b>\n\n👉 Nhập <b>Mã Acc</b> hoặc <b>ID</b> để xem chi tiết.");
        exit;
    }

    if ($text === '❌ XÓA ACC') {
        file_put_contents($sessionFile, json_encode(['mode' => 'delete']));
        sendTelegram($chat_id, "🗑️ <b>CHẾ ĐỘ XÓA ACC</b>\n\n⚠️ <b>CẢNH BÁO:</b> Nhập Mã Acc nào là xóa NGAY Acc đó (kèm toàn bộ ảnh). Cẩn thận!\n\n👉 Nhập Mã Acc cần xóa:");
        exit;
    }

    // 4. Xử lý tin nhắn văn bản
    if (!empty($text)) {
        if ($sessionData['mode'] === 'delete') {
            // Chế độ Xóa
            deleteProductFinal($text, $chat_id, $conn);
        } else {
            // Chế độ Tra cứu (Mặc định)
            searchProductFinal($text, $chat_id, $conn);
        }
    }
}

// =================================================================
// PHẦN 2: CÁC HÀM XỬ LÝ (FUNCTIONS)
// =================================================================

function sendTelegram($cid, $txt)
{
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    // Bàn phím rút gọn
    $keyboard = [
        'keyboard' => [
            [['text' => '🔍 TRA CỨU'], ['text' => '❌ XÓA ACC']]
        ],
        'resize_keyboard' => true,
        'is_persistent' => true
    ];
    
    $postData = [
        'chat_id' => $cid,
        'text' => $txt,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => false,
        'reply_markup' => json_encode($keyboard)
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 5
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// Hàm Xóa Acc + Dọn sạch ảnh
function deleteProductFinal($input, $cid, $conn)
{
    $input = trim($input);
    // Tìm Acc trước
    $stmt = $conn->prepare("SELECT id, title, thumb, gallery FROM products WHERE title = :i OR id = :i LIMIT 1");
    $stmt->execute([':i' => $input]);
    $p = $stmt->fetch();

    if ($p) {
        $countImg = 0;

        // 1. Xóa ảnh bìa
        if (!empty($p['thumb'])) {
            $thumbPath = "uploads/" . $p['thumb'];
            if (file_exists($thumbPath)) {
                @unlink($thumbPath);
                $countImg++;
            }
        }

        // 2. Xóa album ảnh
        $gallery = json_decode($p['gallery'], true);
        if (is_array($gallery)) {
            foreach ($gallery as $imgName) {
                $imgPath = "uploads/" . $imgName;
                if (file_exists($imgPath)) {
                    @unlink($imgPath);
                    $countImg++;
                }
            }
        }

        // 3. Xóa DB
        $del = $conn->prepare("DELETE FROM products WHERE id = :id");
        $del->execute([':id' => $p['id']]);

        sendTelegram($cid, "✅ <b>ĐÃ XÓA THÀNH CÔNG</b>\n\n🆔 Acc: <b>{$p['title']}</b>\n🗑️ Đã dọn dẹp: <b>$countImg</b> file ảnh.");
    } else {
        sendTelegram($cid, "❌ Không tìm thấy Acc nào có mã: <b>$input</b>");
    }
}

// Hàm Tra cứu Acc (Logic mới: Hiển thị cả Bán & Thuê)
function searchProductFinal($input, $cid, $conn)
{
    $input = trim($input);
    $stmt = $conn->prepare("SELECT * FROM products WHERE title = :k OR id = :k LIMIT 1");
    $stmt->execute([':k' => $input]);
    $p = $stmt->fetch();

    if ($p) {
        $status = ($p['status'] == 1) ? "🟢 Đang bán" : "🔴 Đã bán/Ẩn";
        
        // --- LOGIC XỬ LÝ GIÁ & LOẠI ---
        $isSell = ($p['price'] > 0);
        $isRent = ($p['price_rent'] > 0);
        $typeLabel = "";
        $priceInfo = "";

        if ($isSell && $isRent) {
            // Acc Vừa Bán Vừa Thuê
            $typeLabel = "🛒 Bán & 📅 Thuê";
            $unitText = ($p['unit'] == 2) ? "Ngày" : "Giờ";
            $priceInfo = "\n   ├ <b>Bán:</b> " . number_format($p['price']) . " đ" .
                         "\n   └ <b>Thuê:</b> " . number_format($p['price_rent']) . " đ/" . $unitText;
        } elseif ($isRent) {
            // Acc Chỉ Thuê
            $typeLabel = "📅 Thuê";
            $unitText = ($p['unit'] == 2) ? "Ngày" : "Giờ";
            $priceInfo = "<b>" . number_format($p['price_rent']) . " đ / " . $unitText . "</b>";
        } else {
            // Acc Chỉ Bán (Hoặc chưa set giá)
            $typeLabel = "🛒 Bán vĩnh viễn";
            $priceInfo = "<b>" . number_format($p['price']) . " đ</b>";
        }
        // ------------------------------

        $link = BASE_URL . "detail.php?id=" . $p['id'];
        
        $msg = "🔎 <b>KẾT QUẢ TRA CỨU:</b>\n" .
               "➖➖➖➖➖➖➖➖\n" .
               "🆔 Mã: <b>{$p['title']}</b> (ID: {$p['id']})\n" .
               "📂 Loại: <b>$typeLabel</b>\n" .
               "💰 Giá: $priceInfo\n" .
               "ℹ️ Trạng thái: $status\n" .
               "👀 Lượt xem: " . number_format($p['views']) . "\n" .
               "➖➖➖➖➖➖➖➖\n" .
               "🔗 <a href='$link'>👉 Xem chi tiết trên Web</a>";
               
        sendTelegram($cid, $msg);
    } else {
        sendTelegram($cid, "❓ Không tìm thấy kết quả nào cho: <b>$input</b>");
    }
}
?>