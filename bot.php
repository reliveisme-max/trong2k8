<?php
// bot.php - V62: SEARCH WITH INLINE DELETE BUTTON

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

// Danh sách Admin (ID Telegram được phép dùng bot)
$allowed_users = ['5914616789', '8343506927'];

if (!file_exists(TEMP_DIR)) mkdir(TEMP_DIR, 0777, true);

// =================================================================
// PHẦN 1: XỬ LÝ DỮ LIỆU TỪ TELEGRAM (WEBHOOK)
// =================================================================
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) exit;

// A. XỬ LÝ KHI BẤM NÚT INLINE (CALLBACK QUERY)
if (isset($update['callback_query'])) {
    $cb = $update['callback_query'];
    $cb_id = $cb['id'];
    $chat_id = $cb['message']['chat']['id'];
    $data = $cb['data'];
    $user_id = $cb['from']['id'];

    // Check quyền
    if (!in_array((string)$user_id, $allowed_users)) {
        answerCallback($cb_id, "⛔ Bạn không có quyền!");
        exit;
    }

    // Xử lý lệnh xóa: DEL_123
    if (strpos($data, 'DEL_') === 0) {
        $idToDelete = substr($data, 4); // Lấy ID sau chữ DEL_

        // Thực hiện xóa
        $result = deleteProductById($idToDelete, $conn);

        if ($result['status']) {
            // Xóa thành công -> Sửa lại tin nhắn cũ báo đã xóa
            $msg = "✅ <b>ĐÃ XÓA THÀNH CÔNG!</b>\n\n" .
                "🆔 Acc ID: <b>$idToDelete</b>\n" .
                "🗑️ Đã dọn dẹp: <b>{$result['img_count']}</b> ảnh.";

            // Edit tin nhắn hiện tại thành thông báo xóa
            editMessageText($chat_id, $cb['message']['message_id'], $msg);
            answerCallback($cb_id, "Đã xóa xong!");
        } else {
            answerCallback($cb_id, "❌ Lỗi: Acc này không còn tồn tại!");
        }
    }
    exit;
}

// B. XỬ LÝ TIN NHẮN CHAT BÌNH THƯỜNG
if (isset($update['message'])) {
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

    // 3. Xử lý Lệnh từ bàn phím dưới
    if ($text === '/start' || $text === '🔍 TRA CỨU') {
        file_put_contents($sessionFile, json_encode(['mode' => 'normal']));
        sendTelegram($chat_id, "🔍 <b>CHẾ ĐỘ TRA CỨU</b>\n\n👉 Nhập <b>Mã Acc</b> hoặc <b>ID</b> để xem chi tiết.\n(Có nút Xóa nhanh bên dưới kết quả)");
        exit;
    }

    if ($text === '❌ XÓA ACC') {
        file_put_contents($sessionFile, json_encode(['mode' => 'delete']));
        sendTelegram($chat_id, "🗑️ <b>CHẾ ĐỘ XÓA ACC (Thủ công)</b>\n\n👉 Nhập Mã Acc để xóa.");
        exit;
    }

    // 4. Xử lý tin nhắn văn bản
    if (!empty($text)) {
        if ($sessionData['mode'] === 'delete') {
            // Chế độ Xóa thủ công (nhập mã)
            $res = deleteProductByTitleOrId($text, $conn);
            if ($res['status']) {
                sendTelegram($chat_id, "✅ <b>ĐÃ XÓA THÀNH CÔNG</b>\n🆔 Acc: <b>{$res['title']}</b>\n🗑️ Ảnh đã xóa: {$res['img_count']}");
            } else {
                sendTelegram($chat_id, "❌ Không tìm thấy Acc: <b>$text</b>");
            }
        } else {
            // Chế độ Tra cứu (Mặc định) -> Có nút xóa
            searchProductWithButton($text, $chat_id, $conn);
        }
    }
}

// =================================================================
// PHẦN 2: CÁC HÀM XỬ LÝ (FUNCTIONS)
// =================================================================

// Hàm gửi tin nhắn (Có hỗ trợ nút bấm Inline)
function sendTelegram($cid, $txt, $inlineKeyboard = null)
{
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";

    // Bàn phím mặc định (Menu dưới cùng)
    $defaultKeyboard = [
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
        'disable_web_page_preview' => false
    ];

    // Nếu có nút Inline (nút xóa) thì ưu tiên hiện nút đó
    if ($inlineKeyboard) {
        $postData['reply_markup'] = json_encode(['inline_keyboard' => $inlineKeyboard]);
    } else {
        // Không có nút inline thì hiện menu dưới
        $postData['reply_markup'] = json_encode($defaultKeyboard);
    }

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

// Hàm Sửa tin nhắn (Dùng khi bấm nút xóa xong thì đổi nội dung tin nhắn đó)
function editMessageText($chat_id, $message_id, $new_text)
{
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/editMessageText";
    $postData = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $new_text,
        'parse_mode' => 'HTML'
    ];
    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $postData, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false]);
    curl_exec($ch);
    curl_close($ch);
}

// Hàm phản hồi khi bấm nút (để tắt cái vòng xoay loading trên nút)
function answerCallback($cb_id, $text)
{
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/answerCallbackQuery";
    $postData = ['callback_query_id' => $cb_id, 'text' => $text];
    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $postData, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false]);
    curl_exec($ch);
    curl_close($ch);
}

// Hàm xóa theo ID (Dùng cho nút bấm)
function deleteProductById($id, $conn)
{
    $stmt = $conn->prepare("SELECT id, title, thumb, gallery FROM products WHERE id = :i LIMIT 1");
    $stmt->execute([':i' => $id]);
    $p = $stmt->fetch();
    return processDelete($p, $conn);
}

// Hàm xóa theo Title hoặc ID (Dùng cho nhập tay)
function deleteProductByTitleOrId($input, $conn)
{
    $input = trim($input);
    $stmt = $conn->prepare("SELECT id, title, thumb, gallery FROM products WHERE title = :i OR id = :i LIMIT 1");
    $stmt->execute([':i' => $input]);
    $p = $stmt->fetch();
    return processDelete($p, $conn);
}

// Logic xóa chung (Xóa file + DB)
function processDelete($p, $conn)
{
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

        return ['status' => true, 'title' => $p['title'], 'img_count' => $countImg];
    }
    return ['status' => false];
}

// Hàm Tra cứu Acc (Có thêm nút bấm)
function searchProductWithButton($input, $cid, $conn)
{
    $input = trim($input);
    $stmt = $conn->prepare("SELECT * FROM products WHERE title = :k OR id = :k LIMIT 1");
    $stmt->execute([':k' => $input]);
    $p = $stmt->fetch();

    if ($p) {
        $status = ($p['status'] == 1) ? "🟢 Đang bán" : "🔴 Đã bán/Ẩn";

        // --- LOGIC XỬ LÝ GIÁ ---
        $isSell = ($p['price'] > 0);
        $isRent = ($p['price_rent'] > 0);
        $typeLabel = "";
        $priceInfo = "";

        if ($isSell && $isRent) {
            $typeLabel = "🛒 Bán & 📅 Thuê";
            $unitText = ($p['unit'] == 2) ? "Ngày" : "Giờ";
            $priceInfo = "\n   ├ <b>Bán:</b> " . number_format($p['price']) . " đ\n   └ <b>Thuê:</b> " . number_format($p['price_rent']) . " đ/" . $unitText;
        } elseif ($isRent) {
            $typeLabel = "📅 Thuê";
            $unitText = ($p['unit'] == 2) ? "Ngày" : "Giờ";
            $priceInfo = "<b>" . number_format($p['price_rent']) . " đ / " . $unitText . "</b>";
        } else {
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

        // TẠO NÚT BẤM INLINE (XÓA ACC)
        $inlineBtn = [
            [
                ['text' => '❌ XÓA ACC NÀY NGAY', 'callback_data' => 'DEL_' . $p['id']]
            ]
        ];

        sendTelegram($cid, $msg, $inlineBtn);
    } else {
        sendTelegram($cid, "❓ Không tìm thấy kết quả nào cho: <b>$input</b>");
    }
}