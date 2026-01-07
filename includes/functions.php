<?php
// includes/functions.php - FINAL STANDARDIZED VERSION

// 1. XỬ LÝ UPLOAD ẢNH (Auto convert to WebP)
function uploadImageToWebp($fileData)
{
    $targetDir = "../uploads/";
    if ($fileData['error'] !== 0) return false;

    $tempPath = $fileData['tmp_name'];
    $ext = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) return false;

    // Nếu là webp rồi thì move luôn
    if ($ext == 'webp') {
        $newFileName = 'acc_' . uniqid() . '.webp';
        if (move_uploaded_file($tempPath, $targetDir . $newFileName)) {
            return $newFileName;
        }
        return false;
    }

    // Xử lý ảnh JPG/PNG
    $image = null;
    if ($ext == 'jpg' || $ext == 'jpeg') $image = @imagecreatefromjpeg($tempPath);
    elseif ($ext == 'png') {
        $image = @imagecreatefrompng($tempPath);
        if ($image) {
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);
        }
    }

    if (!$image) return false;

    // Resize nếu ảnh quá lớn (>1200px)
    $maxWidth = 1200;
    $origWidth = imagesx($image);
    if ($origWidth > $maxWidth) {
        $newHeight = floor(imagesy($image) * ($maxWidth / $origWidth));
        $newImage = imagecreatetruecolor($maxWidth, $newHeight);
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $maxWidth, $newHeight, $origWidth, imagesy($image));
        imagedestroy($image);
        $image = $newImage;
    }

    $newFileName = 'acc_' . uniqid() . '.webp';
    $result = imagewebp($image, $targetDir . $newFileName, 80); // Quality 80
    imagedestroy($image);

    return $result ? $newFileName : false;
}

// 2. SẮP XẾP MẢNG FILE (Dùng cho upload nhiều ảnh)
function reArrayFiles(&$file_post)
{
    $file_ary = array();
    $file_count = count($file_post['name']);
    $file_keys = array_keys($file_post);
    for ($i = 0; $i < $file_count; $i++) {
        foreach ($file_keys as $key) {
            $file_ary[$i][$key] = $file_post[$key][$i];
        }
    }
    return $file_ary;
}

// 3. FORMAT GIÁ TIỀN (Hiển thị đẹp: 1m, 500k...)
function formatPrice($price)
{
    if ($price <= 0) return "Liên hệ";

    if ($price >= 1000000) {
        $val = $price / 1000000;
        // Nếu tròn (vd: 1.0) thì bỏ số 0, nếu lẻ (1.5) thì giữ
        $str = (string)$val;
        if (strpos($str, '.') !== false) {
            $str = rtrim(rtrim($str, '0'), '.');
        }
        return str_replace('.', ',', $str) . 'm';
    }

    if ($price >= 1000) {
        $val = $price / 1000;
        $str = (string)$val;
        if (strpos($str, '.') !== false) {
            $str = rtrim(rtrim($str, '0'), '.');
        }
        return str_replace('.', ',', $str) . 'k';
    }

    return number_format($price, 0, ',', '.') . ' đ';
}

// 4. CHECK ACTIVE BỘ LỌC (Dùng cho index.php)
// Hàm này kiểm tra xem URL hiện tại có trùng với min/max không để thêm class 'active'
function checkActive($min, $max)
{
    if (isset($_GET['min']) && $_GET['min'] == $min) {
        // Trường hợp max là null (Ví dụ: Trên 60m)
        if ($max === null && !isset($_GET['max'])) return 'active';

        // Trường hợp có cả min và max
        if (isset($_GET['max']) && $_GET['max'] == $max) return 'active';
    }
    return '';
}