<?php
require_once __DIR__ . '/config/config.php';

$report   = new Report();
$products = new Product();

$todayTotal = $report->todayTotal();
$todayCount = $report->todayCount();
$week       = $report->last7Days();
$top        = $report->topProducts(5);
$recent     = $report->recentSales(8);
$low        = $products->lowStock();

$pageTitle = 'Dashboard';
$active    = 'dashboard';
include __DIR__ . '/includes/header.php';
?>
<main class="page container-fluid px-4 py-4">
    <h4 class="mb-3"><i class="bi bi-speedometer2"></i> Dashboard</h4>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="stat-card green">
            <i class="bi bi-cash-stack"></i><div><span>Today's Sales</span><strong><?= money($todayTotal) ?></strong></div></div></div>
        <div class="col-6 col-xl-3"><div class="stat-card blue">
            <i class="bi bi-receipt"></i><div><span>Invoices Today</span><strong><?= $todayCount ?></strong></div></div></div>
        <div class="col-6 col-xl-3"><div class="stat-card purple">
            <i class="bi bi-box-seam"></i><div><span>Total Products</span><strong><?= $products->count() ?></strong></div></div></div>
        <div class="col-6 col-xl-3"><div class="stat-card <?= $low ? 'red alert-blink' : 'green' ?>">
            <i class="bi bi-exclamation-triangle-fill"></i><div><span>Low-Stock Alerts</span><strong><?= count($low) ?></strong></div></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-8"><div class="card shadow-sm h-100"><div class="card-body">
            <h6 class="mb-3">Sales – last 7 days</h6>
            <div class="chart-box"><canvas id="salesChart"></canvas></div>
        </div></div></div>
        <div class="col-lg-4"><div class="card shadow-sm h-100"><div class="card-body">
            <h6 class="mb-3">Top selling products</h6>
            <?php if ($top): ?><div class="chart-box"><canvas id="topChart"></canvas></div>
            <?php else: ?><p class="text-muted">No sales yet.</p><?php endif; ?>
        </div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6"><div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <?php if ($low): ?><i class="bi bi-circle-fill text-danger blink me-1"></i><?php endif; ?>Low stock products
            </div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr><th>Product</th><th>Category</th><th class="text-center">Stock</th><th class="text-center">Alert at</th></tr></thead>
                <tbody>
                <?php foreach ($low as $p): ?>
                    <tr><td><?= e($p['name']) ?></td><td><?= e($p['category']) ?></td>
                        <td class="text-center"><span class="badge text-bg-<?= $p['qty'] <= 0 ? 'danger' : 'warning' ?> blink"><?= (int)$p['qty'] ?></span></td>
                        <td class="text-center text-muted"><?= (int)$p['low_stock_level'] ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$low): ?><tr><td colspan="4" class="text-center text-muted py-3">All stock levels are healthy 🎉</td></tr><?php endif; ?>
                </tbody></table></div>
        </div></div>

        <div class="col-lg-6"><div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Recent sales</div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr><th>Invoice</th><th>Time</th><th>Method</th><th class="text-end">Total</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($recent as $s): ?>
                    <tr><td><code><?= e($s['invoice_no']) ?></code></td>
                        <td class="text-muted"><?= e(date('d M H:i', strtotime($s['created_at']))) ?></td>
                        <td><span class="badge text-bg-secondary"><?= e($s['payment_method']) ?></span></td>
                        <td class="text-end"><?= money($s['total']) ?></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-dark" target="_blank" href="bill.php?id=<?= (int)$s['id'] ?>"><i class="bi bi-printer"></i></a></td></tr>
                <?php endforeach; ?>
                <?php if (!$recent): ?><tr><td colspan="5" class="text-center text-muted py-3">No sales yet.</td></tr><?php endif; ?>
                </tbody></table></div>
        </div></div>
    </div>
</main>

<?php
ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
    var week = <?= json_encode($week) ?>;
    var top  = <?= json_encode($top) ?>;

    new Chart(document.getElementById('salesChart'), {
        type: 'bar',
        data: { labels: week.labels, datasets: [{ label: 'Sales (<?= e(CURRENCY) ?>)', data: week.totals,
                backgroundColor: '#16a34a', borderRadius: 6 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
                   scales: { y: { beginAtZero: true } } }
    });

    var el = document.getElementById('topChart');
    if (el && top.length) {
        new Chart(el, {
            type: 'doughnut',
            data: { labels: top.map(function (t) { return t.product_name; }),
                    datasets: [{ data: top.map(function (t) { return +t.sold; }),
                                 backgroundColor: ['#16a34a', '#2563eb', '#f59e0b', '#9333ea', '#ef4444'] }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });
    }
})();
</script>
<?php $pageScripts = ob_get_clean();
include __DIR__ . '/includes/footer.php';
