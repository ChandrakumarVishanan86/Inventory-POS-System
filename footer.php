<div class="toast-container position-fixed top-0 end-0 p-3" id="toasts" style="z-index:2000"></div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var el = document.getElementById('clock');
    function tick() { el.textContent = new Date().toLocaleString('en-GB', {weekday:'short', day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit', second:'2-digit'}); }
    tick(); setInterval(tick, 1000);
})();
</script>
<?= $pageScripts ?? '' ?>
</body>
</html>
