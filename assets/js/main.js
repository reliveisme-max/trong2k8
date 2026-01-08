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
// ==================================================
// 4. [MỚI] LOGIC CHO MOBILE UI (MENU & SMART SEARCH)
// ==================================================

// Hàm Bật/Tắt Bảng trượt (Sảnh danh mục, Tìm kiếm)
function toggleSheet(sheetID) {
    const sheet = document.getElementById(sheetID);
    if (sheet) {
        // Toggle class 'show' để trượt lên/xuống
        sheet.classList.toggle('show');
        
        // Nếu đang mở bảng này thì đóng bảng kia (tránh bị chồng lên nhau)
        const otherID = (sheetID === 'categorySheet') ? 'searchSheet' : 'categorySheet';
        const other = document.getElementById(otherID);
        if(other && other.classList.contains('show')) {
            other.classList.remove('show');
        }
    }
}

// Hàm chọn GIÁ trong bảng Tìm kiếm (Smart Search)
function setPrice(min, max, element) {
    // Gán giá trị vào input ẩn
    document.getElementById('inpMin').value = min;
    document.getElementById('inpMax').value = max;

    // Hiệu ứng đổi màu nút đã chọn
    const parent = element.parentElement;
    const allBtns = parent.querySelectorAll('.tag-item');
    allBtns.forEach(btn => {
        btn.style.background = '#f9fafb';
        btn.style.color = '#1f2937';
        btn.style.borderColor = '#e5e7eb';
    });
    // Highlight nút vừa bấm
    element.style.background = '#e7f3ff';
    element.style.color = '#1877F2';
    element.style.borderColor = '#1877F2';

    // Submit form ngay lập tức (Tap & Go)
    document.getElementById('smartSearchForm').submit();
}

// Hàm chọn TỪ KHÓA (Súng/Xe) -> Tìm ngay
function setKeyword(keyword) {
    document.getElementById('inpKeyword').value = keyword;
    document.getElementById('smartSearchForm').submit();
}
// ==================================================
// 5. [NEW] LOGIC SMART SEARCH (ĐẾM KẾT QUẢ REALTIME)
// ==================================================

// Biến lưu trạng thái lọc
let currentFilter = {
    min: 0,
    max: 99999999999,
    tagIds: []
};

// Kích hoạt lắng nghe sự kiện khi mở web
document.addEventListener('DOMContentLoaded', function() {
    // Gán sự kiện click cho các nút Tag trong Modal
    const tagButtons = document.querySelectorAll('#searchSheet .tag-item');
    tagButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            handleFilterClick(this);
        });
    });
});

// Xử lý khi bấm vào 1 nút lọc
function handleFilterClick(el) {
    const type = el.getAttribute('data-type'); // 'price' hoặc 'tag'

    // 1. NẾU CHỌN GIÁ (Chỉ được chọn 1)
    if (type === 'price') {
        const isActive = el.classList.contains('active');
        
        // Bỏ chọn tất cả các nút giá khác
        document.querySelectorAll('#searchSheet .tag-item[data-type="price"]').forEach(b => {
            b.classList.remove('active');
        });

        if (!isActive) {
            // Chọn nút này
            el.classList.add('active');
            currentFilter.min = el.getAttribute('data-min');
            currentFilter.max = el.getAttribute('data-max');
        } else {
            // Bỏ chọn -> Reset về mặc định
            currentFilter.min = 0;
            currentFilter.max = 99999999999;
        }
    }

    // 2. NẾU CHỌN TAG (Được chọn nhiều)
    if (type === 'tag') {
        el.classList.toggle('active');
    }

    // 3. GỌI SERVER ĐẾM KẾT QUẢ NGAY
    countResults();
}

// Hàm gửi Ajax đếm số lượng acc
function countResults() {
    // Gom tất cả ID tag đang active
    currentFilter.tagIds = [];
    document.querySelectorAll('#searchSheet .tag-item[data-type="tag"].active').forEach(b => {
        currentFilter.tagIds.push(b.getAttribute('data-id'));
    });

    // Hiệu ứng "Đang tải..."
    const btnCount = document.getElementById('resultCount');
    if(btnCount) btnCount.innerText = '...';

    // Gửi dữ liệu sang PHP (ajax_search.php)
    fetch('ajax_search.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            min: currentFilter.min,
            max: currentFilter.max,
            tags: currentFilter.tagIds
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            if(btnCount) btnCount.innerText = data.count; // Cập nhật số
        } else {
            console.error('Lỗi đếm:', data.msg);
        }
    })
    .catch(err => console.error('Lỗi kết nối:', err));
}

// Hàm Chốt đơn (Chuyển hướng trang)
function submitSmartSearch() {
    const params = new URLSearchParams();

    // Thêm giá
    if (currentFilter.min != 0) params.append('min', currentFilter.min);
    if (currentFilter.max != 99999999999) params.append('max', currentFilter.max);

    // Thêm tags (dạng chuỗi: 1,5,8)
    if (currentFilter.tagIds.length > 0) {
        params.append('tag_ids', currentFilter.tagIds.join(','));
    }

    // Chuyển trang
    window.location.href = 'index.php?' + params.toString();
}