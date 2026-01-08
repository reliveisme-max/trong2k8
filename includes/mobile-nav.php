<?php
// includes/mobile-nav.php - FINAL VERSION (MULTI-SELECT SUPPORT)

// Lấy danh sách Tag từ DB
if (isset($conn)) {
    // Lấy tên Tag và ID để làm bộ lọc
    $navTags = $conn->query("SELECT group_type, id, name FROM tags ORDER BY id DESC")->fetchAll(PDO::FETCH_GROUP);
} else {
    $navTags = [];
}
?>

<!-- 1. MENU ĐÁY (BOTTOM NAV) -->
<div class="mobile-bottom-nav d-md-none">
    <a href="./" class="nav-item <?= (!isset($_GET['view']) && !isset($_GET['q'])) ? 'active' : '' ?>">
        <i class="ph-fill ph-house"></i>
        <span>Trang chủ</span>
    </a>

    <div class="nav-item" onclick="toggleSheet('categorySheet')">
        <i class="ph-fill ph-squares-four" style="color: #F93920;"></i>
        <span style="color: #F93920; font-weight:700;">Danh mục</span>
    </div>

    <div class="nav-item" onclick="toggleSheet('searchSheet')">
        <i class="ph-bold ph-magnifying-glass"></i>
        <span>Tìm Skin</span>
    </div>

    <a href="https://zalo.me/0984074897" target="_blank" class="nav-item">
        <i class="ph-bold ph-chat-circle-dots"></i>
        <span>Hỗ trợ</span>
    </a>
</div>

<!-- 2. BẢNG DANH MỤC 4 Ô (CATEGORY SHEET) -->
<div id="categorySheet" class="mobile-sheet">
    <div class="sheet-overlay" onclick="toggleSheet('categorySheet')"></div>
    <div class="sheet-content">
        <div class="sheet-header">
            <h6 class="m-0 fw-bold"><i class="ph-fill ph-fire text-danger"></i> CHỌN SẢNH VIP (PUBG)</h6>
            <div class="btn-close-sheet" onclick="toggleSheet('categorySheet')"><i class="ph-bold ph-x"></i></div>
        </div>

        <div class="sheet-body">
            <div class="row g-2">
                <div class="col-6">
                    <a href="index.php?q=Sảnh Xe" class="cat-box box-purple">
                        <i class="ph-fill ph-car-profile"></i>
                        <span>SẢNH XE</span><small>Luxury</small>
                    </a>
                </div>
                <div class="col-6">
                    <a href="index.php?q=Mũ Đinh" class="cat-box box-red">
                        <i class="ph-fill ph-baseball-cap"></i>
                        <span>MŨ ĐINH</span><small>Hàng hiếm</small>
                    </a>
                </div>
                <div class="col-6">
                    <a href="index.php?q=Bape" class="cat-box box-gold">
                        <i class="ph-fill ph-t-shirt"></i>
                        <span>BAPE</span><small>Hypebeast</small>
                    </a>
                </div>
                <div class="col-6">
                    <a href="index.php?q=Súng Lab" class="cat-box box-ice">
                        <i class="ph-fill ph-snowflake"></i>
                        <span>SÚNG LAB</span><small>Hiệu ứng</small>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 3. BẢNG TÌM KIẾM NÂNG CAO (SMART SEARCH) -->
<div id="searchSheet" class="mobile-sheet">
    <div class="sheet-overlay" onclick="toggleSheet('searchSheet')"></div>
    <div class="sheet-content" style="height: 85vh;">

        <div class="sheet-header">
            <h6 class="m-0 fw-bold">🔍 TÌM KIẾM (CHỌN NHIỀU)</h6>
            <div class="btn-close-sheet" onclick="toggleSheet('searchSheet')"><i class="ph-bold ph-x"></i></div>
        </div>

        <div class="sheet-body scrollable">

            <!-- A. KHOẢNG GIÁ (Chỉ chọn 1 - Single Select) -->
            <div class="search-section">
                <label class="section-title">💰 Tài chính (Chọn 1 mức)</label>
                <div class="tag-list price-group">
                    <div class="tag-item" data-type="price" data-min="0" data-max="5000000">Dưới 5m</div>
                    <div class="tag-item" data-type="price" data-min="5000000" data-max="10000000">5m - 10m</div>
                    <div class="tag-item" data-type="price" data-min="10000000" data-max="20000000">10m - 20m</div>
                    <div class="tag-item" data-type="price" data-min="20000000" data-max="40000000">20m - 40m</div>
                    <div class="tag-item" data-type="price" data-min="40000000" data-max="60000000">40m - 60m</div>
                    <div class="tag-item" data-type="price" data-min="60000000" data-max="99999999999">Trên 60m</div>
                </div>
            </div>

            <!-- B. SÚNG HOT (Chọn nhiều - Multi Select) -->
            <?php if (isset($navTags['sung'])): ?>
            <div class="search-section">
                <label class="section-title">🔫 Súng & Skin (Chọn nhiều)</label>
                <div class="tag-list tag-group">
                    <?php foreach ($navTags['sung'] as $t): ?>
                    <!-- Lưu ID của tag vào data-id để gửi đi -->
                    <div class="tag-item" data-type="tag" data-id="<?= $t['id'] ?>"><?= $t['name'] ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- C. XE & TRANG PHỤC -->
            <?php if (isset($navTags['xe']) || isset($navTags['ao'])): ?>
            <div class="search-section">
                <label class="section-title">🏎️ Xe & Trang Phục</label>
                <div class="tag-list tag-group">
                    <?php if (isset($navTags['xe'])): foreach ($navTags['xe'] as $t): ?>
                    <div class="tag-item" data-type="tag" data-id="<?= $t['id'] ?>"><?= $t['name'] ?></div>
                    <?php endforeach;
                        endif; ?>

                    <?php if (isset($navTags['ao'])): foreach ($navTags['ao'] as $t): ?>
                    <div class="tag-item" data-type="tag" data-id="<?= $t['id'] ?>"><?= $t['name'] ?></div>
                    <?php endforeach;
                        endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- NÚT DÍNH ĐÁY (STICKY BOTTOM ACTION) -->
        <div class="sticky-bottom-action">
            <button type="button" class="btn-show-result" onclick="submitSmartSearch()">
                XEM <span id="resultCount">0</span> KẾT QUẢ
            </button>
        </div>

    </div>
</div>