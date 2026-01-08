<!-- FOOTER -->
<footer class="pb-5 mb-4 d-md-block d-none">
    <!-- Ẩn footer trên mobile cho đỡ vướng menu -->
    <div class="container">
        <p class="mb-1 fw-bold text-uppercase">&copy; 2026 TRỌNG 2K8 SHOP</p>
        <p class="mb-0 text-secondary">Hỗ trợ Zalo: <span class="text-dark fw-bold">0984.074.897</span></p>
    </div>
</footer>

<!-- KHOẢNG TRẮNG ĐỂ ĐỠ BỊ MENU CHE NỘI DUNG CUỐI -->
<div style="height: 80px;" class="d-md-none"></div>

<!-- COMMON SCRIPTS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- JS CHÍNH CỦA WEBSITE -->
<script src="assets/js/main.js?v=<?= time() ?>"></script>

<!-- [QUAN TRỌNG] GỌI MENU MOBILE & MODAL VÀO ĐÂY -->
<?php
// Nhúng file giao diện mobile nằm cùng thư mục includes
include __DIR__ . '/mobile-nav.php';
?>

</body>

</html>