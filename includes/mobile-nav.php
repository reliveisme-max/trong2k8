<?php
// includes/mobile-nav.php
// MENU ĐA NĂNG: ĐÁY (MOBILE) - PHẢI (PC)

// Lấy từ khóa đang tìm để active icon (nếu có)
$currentQ = isset($_GET['q']) ? $_GET['q'] : '';
?>

<div class="floating-menu-container">

    <!-- 1. BAPE -->
    <a href="index.php?q=Bape" class="float-item <?= strpos($currentQ, 'Bape') !== false ? 'active' : '' ?>">
        <div class="float-icon">
            <img src="assets/images/bape.png" alt="Bape">
        </div>
        <span class="float-text">Bape</span>
    </a>

    <!-- 2. SẢNH XE -->
    <a href="index.php?q=Sảnh Xe" class="float-item <?= strpos($currentQ, 'Sảnh Xe') !== false ? 'active' : '' ?>">
        <div class="float-icon">
            <img src="assets/images/car.png" alt="Xe">
        </div>
        <span class="float-text">Sảnh Xe</span>
    </a>

    <!-- 3. MŨ ĐINH -->
    <a href="index.php?q=Mũ Đinh" class="float-item <?= strpos($currentQ, 'Mũ Đinh') !== false ? 'active' : '' ?>">
        <div class="float-icon">
            <img src="assets/images/mu.png" alt="Mũ">
        </div>
        <span class="float-text">Mũ Đinh</span>
    </a>

    <!-- 4. GĂNG TAY -->
    <a href="index.php?q=Găng Tay" class="float-item <?= strpos($currentQ, 'Găng Tay') !== false ? 'active' : '' ?>">
        <div class="float-icon">
            <img src="assets/images/gang.png" alt="Găng">
        </div>
        <span class="float-text">Găng Tay</span>
    </a>

</div>