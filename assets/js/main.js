// assets/js/main.js

document.addEventListener("DOMContentLoaded", function() {
    // 1. Highlight nút filter đang active dựa trên URL
    const urlParams = new URLSearchParams(window.location.search);
    const min = urlParams.get('min');
    const max = urlParams.get('max');
    const status = urlParams.get('status');

    // Nếu không có tham số gì -> Active nút "Acc Của Shop"
    if (!min && !max && !status) {
        // Code logic highlight mặc định nếu cần
    }

    // (Bạn có thể thêm hiệu ứng cuộn trang, hoặc modal thông báo ở đây)
    console.log("Website Loaded - Trong2k8 Shop");
});