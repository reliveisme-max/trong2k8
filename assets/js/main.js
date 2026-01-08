// assets/js/main.js - FINAL CLEAN VERSION: MASONRY + SPINNER LOADING

let msnry;

document.addEventListener("DOMContentLoaded", function() {
    console.log("Website Loaded - Trong2k8 Shop");
    
    // 1. Kích hoạt Masonry ngay khi vào trang
    initMasonryGrid();

    // 2. Xử lý đóng Dropdown phân trang khi bấm ra ngoài
    document.addEventListener('click', function(e) {
        const container = document.querySelector('.pagination-container-modern');
        if (container && !container.contains(e.target)) {
            const dropdown = document.getElementById('pagiDropdown');
            const trigger = document.getElementById('pagiTrigger');
            if (dropdown) dropdown.classList.remove('show');
            if (trigger) trigger.classList.remove('is-open');
        }
    });
});

// ==================================================
// HÀM KHỞI TẠO MASONRY (XẾP GẠCH)
// ==================================================
function initMasonryGrid() {
    const grid = document.querySelector('#productGrid');
    if (!grid) return;

    // Hủy instance cũ nếu có
    if (msnry) {
        msnry.destroy();
        msnry = null;
    }

    // Chờ ảnh tải xong mới xếp để không bị đè
    imagesLoaded(grid, function() {
        msnry = new Masonry(grid, {
            itemSelector: '.feed-item-scroll',
            percentPosition: true
        });
        grid.style.opacity = '1'; // Hiện lại grid sau khi xếp xong
    });
}

// ==================================================
// 1. LOGIC ĐIỀU KHIỂN DROPDOWN
// ==================================================
function togglePaginationGrid() {
    const dropdown = document.getElementById('pagiDropdown');
    const trigger = document.getElementById('pagiTrigger');
    if (!dropdown) return;

    dropdown.classList.toggle('show');
    trigger.classList.toggle('is-open');

    // Tự cuộn đến số trang đang chọn
    if (dropdown.classList.contains('show')) {
        const activeItem = dropdown.querySelector('.pagi-num.active');
        if (activeItem) {
            activeItem.scrollIntoView({ block: 'nearest', inline: 'center' });
        }
    }
}

// ==================================================
// 2. HÀM CHUYỂN TRANG
// ==================================================
async function goToPage(pageNum) {
    if (pageNum < 1 || pageNum > window.totalPages || pageNum === window.currentPage) return;

    window.currentPage = pageNum;

    // Đóng dropdown ngay
    const dropdown = document.getElementById('pagiDropdown');
    const trigger = document.getElementById('pagiTrigger');
    if (dropdown) dropdown.classList.remove('show');
    if (trigger) trigger.classList.remove('is-open');

    // Cập nhật giao diện nút
    updatePaginationUI(pageNum);
    
    // Gọi dữ liệu
    await loadGridData(pageNum);
}

function updatePaginationUI(pageNum) {
    const lbl = document.getElementById('lblCurrentPage');
    if (lbl) lbl.innerText = pageNum;

    document.querySelectorAll('.pagi-num').forEach(el => {
        el.classList.remove('active');
        if (parseInt(el.getAttribute('data-page')) === pageNum) {
            el.classList.add('active');
        }
    });

    const btnPrev = document.querySelector('.js-prev-btn');
    const btnNext = document.querySelector('.js-next-btn');

    if (btnPrev) {
        if (pageNum <= 1) {
            btnPrev.classList.add('disabled');
            btnPrev.setAttribute('onclick', '');
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
}

// ==================================================
// 3. TẢI DỮ LIỆU & HIỆN SPINNER
// ==================================================
async function loadGridData(pageNum) {
    const grid = document.getElementById('productGrid');
    if (!grid) return;

    // Hủy Masonry cũ
    if (msnry) {
        msnry.destroy(); 
        msnry = null;
    }

    // A. CHỐNG GIẬT: Giữ chiều cao cũ
    const currentHeight = grid.offsetHeight;
    grid.style.minHeight = currentHeight + 'px';

    // B. CUỘN MƯỢT LÊN TRÊN
    const headerOffset = 100;
    const elementPosition = grid.getBoundingClientRect().top;
    const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
    window.scrollTo({ top: offsetPosition, behavior: "smooth" });

    // C. HIỆN SPINNER (VÒNG XOAY)
    grid.innerHTML = `
        <div class="col-12 loading-container">
            <div class="custom-loader"></div>
        </div>
    `;

    // D. GỌI SERVER
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('page', pageNum);
    urlParams.set('ajax', '1');

    try {
        // Giả lập trễ 400ms cho vòng xoay quay đẹp mắt
        await new Promise(r => setTimeout(r, 400));

        const res = await fetch('index.php?' + urlParams.toString());
        const html = await res.text();

        // Thay nội dung mới
        grid.innerHTML = html;

        // E. XẾP GẠCH LẠI
        initMasonryGrid();

        // Trả lại chiều cao tự động
        grid.style.minHeight = '0px'; 
        
        // Update URL
        urlParams.delete('ajax');
        const newUrl = window.location.pathname + '?' + urlParams.toString();
        window.history.pushState({ path: newUrl }, '', newUrl);

    } catch (err) {
        console.error(err);
        grid.innerHTML = '<div class="col-12 text-center text-danger py-5">Lỗi kết nối. Vui lòng tải lại trang!</div>';
    }
}
