<?php
require_once __DIR__ . '/config/config.php';

$id  = (int)($_GET['id'] ?? 0);
$pdo = Database::connect();

$st = $pdo->prepare('SELECT * FROM sales WHERE id = ?');
$st->execute([$id]);
$sale = $st->fetch();
if (!$sale) {
    http_response_code(404);
    exit('Bill not found.');
}
$st = $pdo->prepare('SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id');
$st->execute([$id]);
$items = $st->fetchAll();
$auto  = isset($_GET['print']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Bill <?= e($sale['invoice_no']) ?></title>
<style>
    * { box-sizing: border-box; }
    body { background: #e5e7eb; margin: 0; padding: 16px; font-family: "Courier New", monospace; color: #000; }
    .actions { text-align: center; margin-bottom: 12px; }
    .actions button, .actions a { font: 600 14px system-ui, sans-serif; padding: 8px 18px; margin: 0 4px; border: 0; border-radius: 6px;
        background: #16a34a; color: #fff; cursor: pointer; text-decoration: none; display: inline-block; }
    .actions a { background: #374151; }
    .receipt { width: 80mm; max-width: 100%; margin: 0 auto; background: #fff; padding: 6mm 5mm; font-size: 12px; line-height: 1.35; }
    .center { text-align: center; }
    .store { font-size: 16px; font-weight: 700; }
    hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; border-bottom: 1px dashed #000; padding-bottom: 3px; }
    th:last-child, td:last-child { text-align: right; }
    td { padding: 2px 0; vertical-align: top; }
    .sub { color: #333; font-size: 11px; }
    .row { display: flex; justify-content: space-between; }
    .grand { font-size: 15px; font-weight: 700; }
    .thanks { margin-top: 8px; text-align: center; }

    @media print {
        @page { size: 80mm auto; margin: 0; }
        body { background: #fff; padding: 0; }
        .actions { display: none !important; }
        .receipt { width: 80mm; margin: 0; padding: 4mm; box-shadow: none; }
    }
</style>
</head>
<body>
<div class="actions">
    <button onclick="window.print()">🖨 Print</button>
    <a href="javascript:window.close()">Close</a>
</div>

<div class="receipt">
    <div class="center">
        <div class="store"><?= e(STORE_NAME) ?></div>
        <div><?= e(STORE_ADDRESS) ?></div>
        <div><?= e(STORE_PHONE) ?></div>
    </div>
    <hr>
    <div class="row"><span>Invoice</span><span><?= e($sale['invoice_no']) ?></span></div>
    <div class="row"><span>Date</span><span><?= e(date('d/m/Y H:i', strtotime($sale['created_at']))) ?></span></div>
    <div class="row"><span>Payment</span><span><?= e(strtoupper($sale['payment_method'])) ?></span></div>
    <hr>
    <table>
        <thead><tr><th>Item</th><th>Qty</th><th>Amount</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
            <tr>
                <td><?= e($it['product_name']) ?><div class="sub">@ <?= number_format((float)$it['price'], 2) ?></div></td>
                <td style="text-align:center"><?= (int)$it['qty'] ?></td>
                <td><?= number_format((float)$it['line_total'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <hr>
    <div class="row"><span>Subtotal</span><span><?= number_format((float)$sale['subtotal'], 2) ?></span></div>
    <?php if ((float)$sale['discount'] > 0): ?>
        <div class="row"><span>Discount</span><span>-<?= number_format((float)$sale['discount'], 2) ?></span></div>
    <?php endif; ?>
    <div class="row grand"><span>TOTAL</span><span><?= e(CURRENCY) ?> <?= number_format((float)$sale['total'], 2) ?></span></div>
    <div class="row"><span>Paid</span><span><?= number_format((float)$sale['paid'], 2) ?></span></div>
    <div class="row"><span>Change</span><span><?= number_format((float)$sale['change_amount'], 2) ?></span></div>
    <hr>
    <div class="thanks">Thank you for shopping with us!<br>Please come again.</div>
</div>

<?php if ($auto): ?>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 250); });</script>
<?php endif; ?>
</body>
</html>
