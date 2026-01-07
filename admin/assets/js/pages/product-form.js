// admin/assets/js/pages/product-form.js

let fileStore = {}; 
let sortable; 

document.addEventListener('DOMContentLoaded', function () {
    // 1. Khởi tạo Kéo thả ảnh (SortableJS)
    const grid = document.getElementById('imageGrid');
    if (grid && typeof Sortable !== 'undefined') {
        sortable = new Sortable(grid, { 
            animation: 150, 
            ghostClass: 'sortable-ghost',
            onEnd: function() {
                // Có thể thêm logic xử lý sau khi kéo thả nếu cần
            }
        });
    }

    // 2. Sự kiện chọn file từ máy tính
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
        fileInput.addEventListener('change', function (e) {
            handleLocalFiles(e.target.files);
            fileInput.value = ''; // Reset để chọn lại file cũ được
        });
    }

    // 3. Khởi tạo trạng thái Switch (Bán/Thuê)
    toggleSections();

    // 4. Auto Parse Title (Copy paste tự nhận diện giá)
    const titleInput = document.querySelector('input[name="title"]');
    if (titleInput) {
        titleInput.addEventListener('change', function() { autoParseEverything(this.value); });
        titleInput.addEventListener('paste', function() { 
            setTimeout(() => autoParseEverything(this.value), 50); 
        });
    }

    // 5. Smart Price (Nhập tắt 5m, 200k)
    const priceInputs = document.querySelectorAll('input[name="price"], input[name="price_rent"]');
    priceInputs.forEach(input => {
        input.addEventListener('blur', function() { parsePriceShortcut(this); });
        input.addEventListener('change', function() { parsePriceShortcut(this); });
    });
});

// --- LOGIC XỬ LÝ ẢNH ---

// Hàm này được gọi từ edit.php để load ảnh cũ
function initExistingImages(images) {
    if (!Array.isArray(images) || images.length === 0) return;
    images.forEach(filename => {
        const uid = 'old_' + Math.random().toString(36).substr(2, 9);
        // Ảnh cũ xem như loại 'lib' (đã có trên server)
        addToGrid(uid, `../uploads/${filename}`, 'lib', filename);
    });
    setTimeout(checkGridHeight, 500);
}

async function handleLocalFiles(files) {
    const fileArray = Array.from(files);
    for (const file of fileArray) {
        if (!file.type.startsWith('image/')) continue;
        
        await new Promise((resolve) => {
            const uid = uuidv4();
            fileStore[uid] = file; // Lưu file gốc vào bộ nhớ
            const blobUrl = URL.createObjectURL(file); // Tạo link xem trước
            addToGrid(uid, blobUrl, 'local', file.name);
            resolve();
        });
    }
    checkGridHeight();
}

function addToGrid(uid, src, type, filename = '') {
    const div = document.createElement('div');
    div.className = 'sortable-item';
    div.dataset.id = uid;    
    div.dataset.type = type; // 'local' hoặc 'lib'
    if (filename) div.dataset.filename = filename;
    
    div.innerHTML = `
        <img src="${src}">
        <div class="btn-remove-img" onclick="removeImage(this, '${uid}')">
            <i class="ph-bold ph-x"></i>
        </div>
    `;
    document.getElementById('imageGrid').appendChild(div);
}

function removeImage(btn, uid) {
    const item = btn.closest('.sortable-item');
    item.remove();
    // Nếu là file mới upload thì xóa khỏi bộ nhớ đệm
    if (fileStore[uid]) delete fileStore[uid];
    checkGridHeight();
}

// --- LOGIC SUBMIT FORM (QUAN TRỌNG) ---

async function submitForm() {
    const gridItems = document.querySelectorAll('.sortable-item');
    if (gridItems.length === 0) { 
        Swal.fire('Thiếu ảnh', 'Vui lòng chọn ít nhất 1 ảnh!', 'warning'); 
        return; 
    }
    
    const isSell = document.getElementById('switchSell').checked;
    const isRent = document.getElementById('switchRent').checked;
    if (!isSell && !isRent) { 
        Swal.fire('Lỗi', 'Chưa chọn chế độ Bán hoặc Thuê!', 'warning'); 
        return; 
    }

    // Đếm số ảnh mới cần upload
    let localCount = 0;
    gridItems.forEach(item => { if(item.dataset.type === 'local') localCount++; });

    // Hiển thị Loading
    Swal.fire({
        title: 'Đang xử lý...',
        html: `Đang nén và upload <b>${localCount}</b> ảnh mới...<br>Vui lòng không tắt trình duyệt!`,
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    try {
        const finalGalleryList = []; // Danh sách tên file cuối cùng để lưu DB
        const localUploadTasks = []; // Danh sách file cần upload

        // 1. Quét grid để xác định thứ tự
        gridItems.forEach(item => {
            const type = item.dataset.type;
            const uid = item.dataset.id;
            
            if (type === 'local') {
                // Tạo một object giữ chỗ, sau khi upload xong sẽ điền filename vào đây
                const mapItem = { type: 'local', uid: uid, filename: '' };
                finalGalleryList.push(mapItem); 
                localUploadTasks.push({ uid: uid, mapItemRef: mapItem }); 
            } else {
                // Ảnh cũ, lấy luôn tên file
                finalGalleryList.push({ type: 'lib', filename: item.dataset.filename });
            }
        });

        // 2. Thực hiện Upload (Chia nhỏ từng gói 5-10 file để tránh lỗi server)
        if (localUploadTasks.length > 0) {
            const CHUNK_SIZE = 5; 
            for (let i = 0; i < localUploadTasks.length; i += CHUNK_SIZE) {
                const chunk = localUploadTasks.slice(i, i + CHUNK_SIZE);
                const chunkFormData = new FormData();
                chunkFormData.append('ajax_upload_mode', '1');

                // Nén ảnh trước khi gửi
                const compressionPromises = chunk.map(async (task) => {
                    const file = fileStore[task.uid];
                    if (file) {
                        // Nén ảnh: Max 1200px, Chất lượng 0.7
                        const compressed = await compressImage(file, 1200, 0.7);
                        chunkFormData.append('chunk_files[]', compressed, compressed.name);
                        chunkFormData.append('chunk_uids[]', task.uid); 
                    }
                });

                await Promise.all(compressionPromises);

                // Gửi về Server
                const response = await fetch('process.php', { method: 'POST', body: chunkFormData });
                const data = await response.json();

                if (data.status === 'success') {
                    // Map lại tên file trả về từ server vào danh sách giữ chỗ
                    const resultKeyMap = data.data;
                    chunk.forEach(task => {
                        const svFilename = resultKeyMap[task.uid];
                        if (svFilename) {
                            task.mapItemRef.filename = svFilename;
                        }
                    });
                } else {
                    throw new Error('Upload ảnh thất bại. ' + (data.msg || ''));
                }
            }
        }

        // 3. Chuẩn bị dữ liệu cuối cùng (Chỉ lấy tên file)
        const simpleGallery = finalGalleryList.map(item => item.filename).filter(name => name !== '');

        // 4. Submit Form chính về PHP
        const mainForm = document.getElementById('addForm');
        const mainFormData = new FormData(mainForm);
        mainFormData.delete('gallery[]'); // Xóa input file gốc vì đã xử lý thủ công
        mainFormData.set('final_gallery_list', JSON.stringify(simpleGallery));

        const finalRes = await fetch('process.php', { method: 'POST', body: mainFormData });
        
        // Xử lý chuyển hướng
        if (finalRes.redirected) {
            window.location.href = finalRes.url;
        } else {
            const resText = await finalRes.text();
            // Fallback nếu PHP trả về HTML redirect
            if(resText.includes('<script>') || resText.includes('header')) {
                 document.write(resText);
            } else {
                 Swal.fire({ icon: 'error', title: 'Lỗi Server', html: resText });
            }
        }

    } catch (error) {
        console.error(error);
        Swal.fire('Lỗi', error.message, 'error');
    }
}

// --- TIỆN ÍCH HỖ TRỢ ---

// Nén ảnh Client-side (Giảm tải cho server)
function compressImage(file, maxWidth, quality) {
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = (event) => {
            const img = new Image();
            img.src = event.target.result;
            img.onload = () => {
                let width = img.width;
                let height = img.height;
                if (width > maxWidth) {
                    height = Math.round((height * maxWidth) / width);
                    width = maxWidth;
                }
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                canvas.toBlob((blob) => {
                    const newFileName = file.name.replace(/\.[^/.]+$/, "") + ".webp";
                    const newFile = new File([blob], newFileName, { type: 'image/webp', lastModified: Date.now() });
                    resolve(newFile);
                }, 'image/webp', quality);
            };
        };
    });
}

function parsePriceShortcut(input) {
    let val = input.value.toLowerCase().trim();
    if (!val) return;
    const regex = /^([0-9]+[.,]?[0-9]*)(k|m|tr)([0-9]*)$/;
    const match = val.match(regex);
    if (match) {
        let mainNum = parseFloat(match[1].replace(',', '.'));
        let unit = match[2];
        let decimalPart = match[3];
        let money = 0;
        if (unit === 'k') money = mainNum * 1000;
        else if (unit === 'm' || unit === 'tr') {
            money = mainNum * 1000000;
            if (decimalPart) {
                if (decimalPart.length === 1) money += parseInt(decimalPart) * 100000;
                else if (decimalPart.length === 2) money += parseInt(decimalPart) * 10000;
            }
        }
        if (money > 0) input.value = money.toLocaleString('vi-VN').replace(/,/g, '.');
    } else {
        let value = input.value.replace(/\D/g, '');
        if (value !== '') input.value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }
}

function formatCurrency(input) {
    if (/[kmtr]/i.test(input.value)) return;
    let value = input.value.replace(/\D/g, '');
    if (value === '') return;
    input.value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

function uuidv4() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
}

function toggleSections() {
    const switchSell = document.getElementById('switchSell');
    const switchRent = document.getElementById('switchRent');
    if(switchSell && switchRent) {
        const sellSec = document.getElementById('sellSection');
        const rentSec = document.getElementById('rentSection');
        if(sellSec) sellSec.style.display = switchSell.checked ? 'block' : 'none';
        if(rentSec) rentSec.style.display = switchRent.checked ? 'block' : 'none';
    }
}

// Logic Mở rộng/Thu gọn lưới ảnh
function checkGridHeight() {
    const grid = document.getElementById('imageGrid');
    const btn = document.getElementById('toggleGridBtn');
    if(!grid || !btn) return;
    const items = grid.querySelectorAll('.sortable-item');
    if (items.length > 4) btn.classList.remove('d-none');
    else { btn.classList.add('d-none'); grid.classList.remove('expanded'); resetToggleBtn(); }
}
function toggleGrid() {
    const grid = document.getElementById('imageGrid');
    const txt = document.getElementById('toggleText');
    const icon = document.querySelector('#toggleGridBtn i');
    if (grid.classList.contains('expanded')) {
        grid.classList.remove('expanded'); txt.innerText = 'Xem thêm ảnh'; icon.className = 'ph-bold ph-caret-down';
        grid.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } else {
        grid.classList.add('expanded'); txt.innerText = 'Thu gọn'; icon.className = 'ph-bold ph-caret-up';
    }
}
function resetToggleBtn() {
    const txt = document.getElementById('toggleText');
    const icon = document.querySelector('#toggleGridBtn i');
    if(txt) txt.innerText = 'Xem thêm ảnh';
    if(icon) icon.className = 'ph-bold ph-caret-down';
}

function autoParseEverything(text) {
    if (!text) return;
    let workingText = text; let hasChange = false;
    const rentRegex = /([0-9]+[.,]?[0-9]*)\s*(k|m|tr)?\s*\/\s*(h|g|giờ|ngày|d|day)/i;
    const rentMatch = workingText.match(rentRegex);
    if (rentMatch) {
        let val = parseFloat(rentMatch[1].replace(',', '.'));
        let mag = rentMatch[2] ? rentMatch[2].toLowerCase() : 'k'; 
        let unitStr = rentMatch[3].toLowerCase();
        let money = (mag === 'm' || mag === 'tr') ? val * 1000000 : val * 1000;
        let unitVal = ['ngày', 'd', 'day'].includes(unitStr) ? 2 : 1;
        if (money > 0) {
            document.querySelector('input[name="price_rent"]').value = money.toLocaleString('vi-VN').replace(/,/g, '.');
            document.querySelector('select[name="unit"]').value = unitVal;
            const swRent = document.getElementById('switchRent');
            if (swRent && !swRent.checked) { swRent.checked = true; toggleSections(); }
            let escapedMatch = rentMatch[0].replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); 
            workingText = workingText.replace(new RegExp(`(thuê|giá thuê|price)?\\s*[:.-]?\\s*${escapedMatch}`, 'gi'), ' ').trim();
            hasChange = true;
        }
    }
    const sellRegex = /([0-9]+[.,]?[0-9]*)\s*(m|tr|k)\s*([0-9]*)/i;
    const sellMatch = workingText.match(sellRegex);
    if (sellMatch) {
        let val = parseFloat(sellMatch[1].replace(',', '.'));
        let mag = sellMatch[2].toLowerCase();
        let dec = sellMatch[3];
        let money = 0;
        if (mag === 'k') money = val * 1000;
        else if (mag === 'm' || mag === 'tr') {
            money = val * 1000000;
            if (dec && dec.length > 0) {
                if (dec.length === 1) money += parseInt(dec) * 100000;
                else if (dec.length === 2) money += parseInt(dec) * 10000;
            }
        }
        if (money > 0) {
            document.querySelector('input[name="price"]').value = money.toLocaleString('vi-VN').replace(/,/g, '.');
            const swSell = document.getElementById('switchSell');
            if (swSell && !swSell.checked) { swSell.checked = true; toggleSections(); }
            let escapedMatch = sellMatch[0].replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            workingText = workingText.replace(new RegExp(`(bán|giá bán|giá)?\\s*[:.-]?\\s*${escapedMatch}`, 'gi'), ' ').trim();
            hasChange = true;
        }
    }
    if (hasChange) {
        workingText = workingText.replace(/^(mã|ms|code|acc|id|account)\s*[:.-]?\s*/gi, '');
        workingText = workingText.replace(/^[:.-]+|[:.-]+$/g, '').trim();
        workingText = workingText.replace(/\s\s+/g, ' ');
        document.querySelector('input[name="title"]').value = workingText;
    }
}