// assets/js/main.js - FINAL VERSION: INSTANT SMOOTH PAGINATION

document.addEventListener("DOMContentLoaded", function() {
    console.log("Website Loaded - Trong2k8 Shop");
    // Căn giữa số trang hiện tại khi mới vào
    centerActivePage();
});

// 1. HÀM CHUYỂN TRANG CHÍNH (GỌI TỪ HTML)
async function goToPage(pageNum) {
    // Chặn nếu bấm trang hiện tại hoặc trang không hợp lệ
    if (pageNum < 1 || pageNum > window.totalPages || pageNum === window.currentPage) return;

    // Cập nhật biến toàn cục
    window.currentPage = pageNum;

    // BƯỚC 1: Xử lý Giao diện ngay lập tức (Instant UI)
    updatePaginationUI(pageNum);

    // BƯỚC 2: Gọi dữ liệu ngầm (AJAX)
    await loadGridData(pageNum);
}

// 2. HÀM CẬP NHẬT GIAO DIỆN PHÂN TRANG (KHÔNG CẦN SERVER)
function updatePaginationUI(pageNum) {
    // Xóa active cũ, thêm active mới
    document.querySelectorAll('.page-number-item').forEach(el => {
        el.classList.remove('active');
        // So sánh data-page với pageNum
        if (parseInt(el.getAttribute('data-page')) === pageNum) {
            el.classList.add('active');
        }
    });

    // Cập nhật trạng thái nút Mũi tên (Disabled/Enabled)
    const btnPrev = document.querySelector('.js-prev-btn');
    const btnNext = document.querySelector('.js-next-btn');

    if (btnPrev) {
        if (pageNum <= 1) {
            btnPrev.classList.add('disabled');
            btnPrev.setAttribute('onclick', ''); // Bỏ click
        } else {
            btnPrev.classList.remove('disabled');
            btnPrev.setAttribute('onclick', `goToPage(${pageNum - 1})`);
        }
    }

    if (btnNext) {
        if (pageNum >= window.totalPages) {
            btnNext.classList.add('disabled');
            btnNext.setAttribute('onclick', '');
        } else {
            btnNext.classList.remove('disabled');
            btnNext.setAttribute('onclick', `goToPage(${pageNum + 1})`);
        }
    }

    // Tự động căn giữa số vừa chọn
    centerActivePage();
}

// 3. HÀM CĂN GIỮA SỐ TRONG THANH TRƯỢT
function centerActivePage() {
    const container = document.getElementById('pagiContainer');
    if (!container) return;

    const activeItem = container.querySelector('.page-number-item.active');
    if (activeItem) {
        // Tính toán vị trí chính giữa
        const centerPos = activeItem.offsetLeft - (container.clientWidth / 2) + (activeItem.clientWidth / 2);
        container.scrollTo({ left: centerPos, behavior: 'smooth' });
    }
}

// 4. HÀM TẢI DỮ LIỆU SẢN PHẨM (AJAX)
async function loadGridData(pageNum) {
    const grid = document.getElementById('productGrid');
    if (!grid) return;

    // Hiệu ứng mờ nhẹ báo đang tải
    grid.style.opacity = '0.4';

    // Chuẩn bị URL
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('page', pageNum);
    urlParams.set('ajax', '1');

    try {
        const res = await fetch('index.php?' + urlParams.toString());
        const html = await res.text();

        // Chỉ thay thế nội dung lưới sản phẩm
        grid.innerHTML = html;

        // Cuộn nhẹ lên đầu danh sách (nếu đang đứng quá thấp)
        const filterSec = document.querySelector('.filter-section');
        if (filterSec) {
            const topPos = filterSec.getBoundingClientRect().top + window.scrollY - 80;
            window.scrollTo({ top: topPos, behavior: 'smooth' });
        }

        // Cập nhật URL trình duyệt (không reload)
        urlParams.delete('ajax');
        const newUrl = window.location.pathname + '?' + urlParams.toString();
        window.history.pushState({ path: newUrl }, '', newUrl);

    } catch (err) {
        console.error(err);
    } finally {
        // Hiển thị lại rõ ràng
        grid.style.opacity = '1';
    }
}