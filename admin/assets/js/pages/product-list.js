// admin/assets/js/pages/product-list.js

document.addEventListener('DOMContentLoaded', function() {
    // 1. Khởi tạo Kéo thả (Sortable) trong Modal
    const sortList = document.getElementById('sortableList');
    if (sortList && typeof Sortable !== 'undefined') {
        new Sortable(sortList, {
            animation: 150,
            ghostClass: 'bg-light',
            onEnd: function (evt) {
                // Cập nhật lại số thứ tự hiển thị (1, 2, 3...)
                const items = sortList.querySelectorAll('.sortable-list-item');
                items.forEach((item, index) => {
                    item.querySelector('.sort-index').innerText = index + 1;
                });
            }
        });
    }

    // 2. Xử lý thông báo từ URL (SweetAlert)
    const urlParams = new URLSearchParams(window.location.search);
    const msg = urlParams.get('msg');
    
    if (msg) {
        let title = 'Thành công';
        let text = '';
        
        if (msg === 'deleted_multi') text = `Đã xóa ${urlParams.get('count')} Acc`;
        else if (msg === 'added') text = 'Đã đăng acc mới';
        else if (msg === 'updated') text = 'Đã cập nhật acc';
        else if (msg === 'deleted') text = 'Đã xóa acc thành công';

        if (text) {
            Swal.fire(title, text, 'success');
            // Xóa param msg trên thanh địa chỉ cho sạch
            const newUrl = window.location.pathname + window.location.search.replace(/[\?&]msg=[^&]+/, '').replace(/[\?&]count=[^&]+/, '');
            window.history.replaceState({}, document.title, newUrl);
        }
    }
});

// --- CÁC HÀM XỬ LÝ CHÍNH ---

// Biến toàn cục lưu trạng thái lọc hiện tại
let currentType = new URLSearchParams(window.location.search).get('type') || '';

function loadPage(page) {
    const keyword = document.getElementById('searchInput').value;
    fetchData(page, currentType, keyword);
}

function applyFilter(type = null) {
    if (type !== null) {
        currentType = type;
        // Cập nhật giao diện nút bấm
        document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
        if (event && event.target) event.target.classList.add('active');
    }
    const keyword = document.getElementById('searchInput').value;
    fetchData(1, currentType, keyword);
}

function fetchData(page, type, keyword) {
    // Hiện loading
    const loading = document.getElementById('ajaxLoading');
    if(loading) loading.classList.remove('d-none');

    const url = `index.php?page=${page}&type=${type}&q=${encodeURIComponent(keyword)}&ajax=1`;

    fetch(url)
        .then(response => response.text())
        .then(data => {
            const parts = data.split('<!--DIVIDER-->');
            if (parts.length >= 2) {
                document.getElementById('tableBody').innerHTML = parts[0];
                document.getElementById('paginationContainer').innerHTML = parts[1];
            }
            
            // Ẩn loading
            if(loading) loading.classList.add('d-none');

            // Update URL không reload
            const newUrl = `index.php?page=${page}&type=${type}&q=${encodeURIComponent(keyword)}`;
            window.history.pushState({path: newUrl}, '', newUrl);

            // Reset nút xóa
            updateDeleteBtn();
        })
        .catch(err => {
            console.error(err);
            if(loading) loading.classList.add('d-none');
        });
}

// --- CHECKBOX & DELETE ---
function toggleAll(source) {
    document.querySelectorAll('.item-check').forEach(c => c.checked = source.checked);
    updateDeleteBtn();
}

function updateDeleteBtn() {
    const count = document.querySelectorAll('.item-check:checked').length;
    const btn = document.getElementById('btnDeleteMulti');
    const countSpan = document.getElementById('countSelect');
    if(countSpan) countSpan.innerText = count;
    if(btn) btn.style.display = count > 0 ? 'inline-block' : 'none';
}

function submitDelete() {
    Swal.fire({
        title: 'Xác nhận xóa?',
        text: "Các Acc đã chọn sẽ bị xóa vĩnh viễn!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Xóa ngay',
        cancelButtonText: 'Hủy'
    }).then((result) => {
        if (result.isConfirmed) document.getElementById('formMultiDelete').submit();
    })
}

function confirmDelete(e, url) {
    e.preventDefault();
    Swal.fire({
        title: 'Xóa Acc này?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Xóa'
    }).then((res) => {
        if (res.isConfirmed) window.location.href = url;
    });
}

// --- TÍNH NĂNG GHIM (FEATURED) ---

// 1. Xử lý bấm ngôi sao ở danh sách
function toggleStar(el, id) {
    el.style.transform = 'scale(0.8)';
    setTimeout(() => el.style.transform = 'scale(1)', 200);

    const formData = new FormData();
    formData.append('action', 'toggle_featured');
    formData.append('id', id);

    fetch('api/featured.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            if (data.new_state == 1) {
                el.className = 'ph-fill text-warning ph-star'; // Vàng
                const toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
                toast.fire({ icon: 'success', title: data.msg });
            } else {
                el.className = 'ph-bold text-secondary opacity-25 ph-star'; // Xám
            }
            // Reload nhẹ để cập nhật lại Modal sắp xếp nếu đang mở (hoặc để lần sau mở)
            // setTimeout(() => location.reload(), 1000); // Tạm tắt reload để mượt, user F5 khi cần
        } else {
            Swal.fire('Thất bại', data.msg, 'error');
        }
    })
    .catch(err => console.error(err));
}

// 2. Lưu thứ tự sắp xếp trong Modal
function saveSortOrder() {
    const items = document.querySelectorAll('.sortable-list-item');
    let orderData = [];
    items.forEach((item) => {
        orderData.push(item.getAttribute('data-id'));
    });

    const formData = new FormData();
    formData.append('action', 'save_featured_order');
    for (let i = 0; i < orderData.length; i++) {
        formData.append('order[]', orderData[i]);
    }

    fetch('api/featured.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            Swal.fire('Thành công', data.msg, 'success').then(() => {
                location.reload(); // Reload để thấy thứ tự mới ngoài danh sách
            });
        } else {
            Swal.fire('Lỗi', data.msg, 'error');
        }
    });
}