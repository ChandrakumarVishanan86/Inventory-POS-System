<?php
require_once __DIR__ . '/config/config.php';

$model    = new Product();
$products = array_map(['Product', 'forClient'], $model->all());

$pageTitle = 'POS';
$active    = 'pos';
$bodyClass = 'pos-page';
include __DIR__ . '/includes/header.php';
?>
<div class="pos-wrap">

    <!-- LEFT 70% : products -->
    <section class="pos-left">
        <div class="pos-toolbar">
            <div class="scan-box">
                <i class="bi bi-upc-scan"></i>
                <input type="text" id="barcode" autocomplete="off" autofocus placeholder="Scan barcode &amp; press Enter  (F2)">
            </div>
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="search" id="search" autocomplete="off" placeholder="Search products  (F4)">
            </div>
        </div>
        <div class="cat-bar" id="catBar"></div>
        <div class="product-grid" id="productGrid"></div>
        <div id="noResults" class="empty-state d-none"><i class="bi bi-search"></i><p>No products match your search</p></div>
    </section>

    <!-- RIGHT 30% : cart -->
    <aside class="pos-right">
        <div class="cart-head">
            <h5><i class="bi bi-cart3"></i> Current Sale <span class="badge bg-light text-dark" id="itemCount">0</span></h5>
            <button class="btn btn-sm btn-outline-light" id="btnClear"><i class="bi bi-trash3"></i> Clear</button>
        </div>

        <div class="cart-items" id="cartItems"></div>

        <div class="cart-foot">
            <div class="tot-row"><span>Subtotal</span><strong id="tSub">0.00</strong></div>
            <div class="tot-row align-items-center">
                <span>Discount</span>
                <input type="number" min="0" step="0.01" id="discount" class="form-control form-control-sm tot-input" placeholder="0.00">
            </div>
            <div class="tot-row grand"><span>TOTAL</span><strong id="tTotal">0.00</strong></div>

            <div class="btn-group w-100 my-2" role="group">
                <input type="radio" class="btn-check" name="method" id="mCash" value="cash" checked>
                <label class="btn btn-outline-light" for="mCash"><i class="bi bi-cash-coin"></i> Cash</label>
                <input type="radio" class="btn-check" name="method" id="mCard" value="card">
                <label class="btn btn-outline-light" for="mCard"><i class="bi bi-credit-card"></i> Card</label>
            </div>

            <div class="tot-row align-items-center">
                <span>Paid</span>
                <input type="number" min="0" step="0.01" id="paid" class="form-control form-control-sm tot-input" placeholder="0.00">
            </div>
            <div class="quick-cash" id="quickCash">
                <button type="button" data-amt="exact">Exact</button>
                <button type="button" data-amt="500">500</button>
                <button type="button" data-amt="1000">1000</button>
                <button type="button" data-amt="2000">2000</button>
                <button type="button" data-amt="5000">5000</button>
            </div>
            <div class="tot-row change"><span>Change</span><strong id="tChange">0.00</strong></div>

            <button class="btn-pay" id="btnPay" disabled>PAY <small>(F9)</small></button>
        </div>
    </aside>
</div>

<!-- Success modal -->
<div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content text-center">
            <div class="modal-body p-4">
                <div class="ok-circle"><i class="bi bi-check-lg"></i></div>
                <h5 class="mt-3 mb-1">Payment successful</h5>
                <div class="text-muted small" id="sInvoice"></div>
                <div class="my-3">
                    <div class="d-flex justify-content-between"><span>Total</span><strong id="sTotal"></strong></div>
                    <div class="d-flex justify-content-between"><span>Paid</span><span id="sPaid"></span></div>
                    <div class="d-flex justify-content-between fs-5 text-success"><span>Change</span><strong id="sChange"></strong></div>
                </div>
                <a class="btn btn-dark w-100 mb-2" id="btnPrintBill" target="_blank" rel="noopener"><i class="bi bi-printer"></i> Print Bill</a>
                <button class="btn btn-success w-100" data-bs-dismiss="modal" id="btnNewSale"><i class="bi bi-plus-lg"></i> New Sale</button>
            </div>
        </div>
    </div>
</div>

<?php
ob_start(); ?>
<script>
window.POS = <?= json_encode([
    'products'    => $products,
    'currency'    => CURRENCY,
    'csrf'        => csrf_token(),
    'saleUrl'     => BASE_URL . '/api/sale.php',
    'productsUrl' => BASE_URL . '/products.php',
    'billUrl'     => BASE_URL . '/bill.php',
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/pos.js"></script>
<?php $pageScripts = ob_get_clean();
include __DIR__ . '/includes/footer.php';
