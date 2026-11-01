<?php
require_once __DIR__ . '/config/config.php';
$model = new Product();

/* ---------- AJAX endpoints used by the POS ---------- */
if (isset($_GET['barcode'])) {
    header('Content-Type: application/json; charset=utf-8');
    $p = $model->findByBarcode(trim($_GET['barcode']));
    echo json_encode($p
        ? ['success' => true, 'product' => Product::forClient($p)]
        : ['success' => false, 'message' => 'Product not found']);
    exit;
}
if (($_GET['ajax'] ?? '') === 'list') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'products' => array_map(['Product', 'forClient'], $model->all())]);
    exit;
}

/* ---------- form actions (create / update / delete) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_check()) {
            throw new RuntimeException('Invalid session token. Reload the page and try again.');
        }
        $action = $_POST['action'] ?? '';
        if ($action === 'save') {
            $isEdit = (int)($_POST['id'] ?? 0) > 0;
            $model->save($_POST, $_FILES['image'] ?? null);
            flash('success', $isEdit ? 'Product updated.' : 'Product added.');
        } elseif ($action === 'delete') {
            $model->delete((int)($_POST['id'] ?? 0));
            flash('success', 'Product deleted.');
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    header('Location: ' . BASE_URL . '/products.php');
    exit;
}

/* ---------- page ---------- */
$products   = $model->all();
$categories = $model->categories();
$flash      = get_flash();
$pageTitle  = 'Products';
$active     = 'products';
include __DIR__ . '/includes/header.php';
?>
<main class="page container-fluid px-4 py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-box-seam"></i> Products <span class="text-muted fs-6">(<?= count($products) ?>)</span></h4>
        <div class="d-flex gap-2">
            <input type="search" id="tableSearch" class="form-control" placeholder="Filter name / barcode / category" style="width:280px">
            <button class="btn btn-success" id="btnAdd"><i class="bi bi-plus-lg"></i> Add Product</button>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show">
            <?= e($flash['msg']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="productTable">
                <thead class="table-light">
                <tr><th style="width:70px">Image</th><th>Barcode</th><th>Name</th><th>Category</th>
                    <th class="text-end">Price</th><th class="text-end">Cost</th><th class="text-center">Stock</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($products as $p):
                    $img = Product::imageUrl($p['image']);
                    $cls = $p['qty'] <= 0 ? 'danger' : ($p['qty'] <= $p['low_stock_level'] ? 'warning' : 'success');
                    $data = [
                        'id' => (int)$p['id'], 'barcode' => $p['barcode'], 'name' => $p['name'], 'category' => $p['category'],
                        'price' => $p['price'], 'cost' => $p['cost'], 'qty' => (int)$p['qty'],
                        'low_stock_level' => (int)$p['low_stock_level'], 'image' => $img,
                    ];
                ?>
                    <tr data-s="<?= e(strtolower($p['barcode'] . ' ' . $p['name'] . ' ' . $p['category'])) ?>">
                        <td>
                            <?php if ($img): ?><img src="<?= e($img) ?>" class="thumb" alt="">
                            <?php else: ?><div class="thumb ph"><?= e(strtoupper(substr($p['name'], 0, 1))) ?></div><?php endif; ?>
                        </td>
                        <td><code><?= e($p['barcode']) ?></code></td>
                        <td class="fw-semibold"><?= e($p['name']) ?></td>
                        <td><?= e($p['category']) ?></td>
                        <td class="text-end"><?= money($p['price']) ?></td>
                        <td class="text-end text-muted"><?= money($p['cost']) ?></td>
                        <td class="text-center"><span class="badge text-bg-<?= $cls ?><?= $cls === 'danger' ? ' blink' : '' ?>"><?= (int)$p['qty'] ?></span></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary btn-edit" data-p="<?= e(json_encode($data)) ?>"><i class="bi bi-pencil"></i></button>
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this product?');">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$products): ?><tr><td colspan="8" class="text-center text-muted py-4">No products yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Add / Edit modal -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="post" enctype="multipart/form-data">
            <div class="modal-header"><h5 class="modal-title" id="modalTitle">Add Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="f_id" value="0">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Barcode *</label><input class="form-control" name="barcode" id="f_barcode" required></div>
                    <div class="col-md-8"><label class="form-label">Name *</label><input class="form-control" name="name" id="f_name" required></div>
                    <div class="col-md-4"><label class="form-label">Category</label>
                        <input class="form-control" name="category" id="f_category" list="catList" value="General">
                        <datalist id="catList"><?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></div>
                    <div class="col-md-4"><label class="form-label">Selling price *</label><input type="number" step="0.01" min="0" class="form-control" name="price" id="f_price" required></div>
                    <div class="col-md-4"><label class="form-label">Cost price</label><input type="number" step="0.01" min="0" class="form-control" name="cost" id="f_cost" value="0"></div>
                    <div class="col-md-4"><label class="form-label">Stock qty *</label><input type="number" min="0" class="form-control" name="qty" id="f_qty" required></div>
                    <div class="col-md-4"><label class="form-label">Low-stock alert at</label><input type="number" min="0" class="form-control" name="low_stock_level" id="f_low" value="10"></div>
                    <div class="col-md-4"><label class="form-label">Image (JPG/PNG/WEBP, max 2 MB)</label>
                        <input type="file" class="form-control" name="image" id="f_image" accept="image/*"></div>
                    <div class="col-12"><img id="preview" class="preview d-none" alt=""></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success"><i class="bi bi-check-lg"></i> Save</button></div>
        </form>
    </div>
</div>

<?php
ob_start(); ?>
<script>
$(function () {
    var modal = new bootstrap.Modal('#productModal');
    function setPreview(src) { $('#preview').attr('src', src || '').toggleClass('d-none', !src); }

    $('#btnAdd').on('click', function () {
        $('#modalTitle').text('Add Product');
        $('#f_id').val(0); $('#f_barcode,#f_name,#f_price,#f_qty').val('');
        $('#f_category').val('General'); $('#f_cost').val(0); $('#f_low').val(10); $('#f_image').val('');
        setPreview(null); modal.show();
    });
    $('#productTable').on('click', '.btn-edit', function () {
        var p = JSON.parse($(this).attr('data-p'));
        $('#modalTitle').text('Edit Product');
        $('#f_id').val(p.id); $('#f_barcode').val(p.barcode); $('#f_name').val(p.name); $('#f_category').val(p.category);
        $('#f_price').val(p.price); $('#f_cost').val(p.cost); $('#f_qty').val(p.qty); $('#f_low').val(p.low_stock_level);
        $('#f_image').val(''); setPreview(p.image); modal.show();
    });
    $('#f_image').on('change', function () {
        var f = this.files[0]; if (f) setPreview(URL.createObjectURL(f));
    });
    $('#tableSearch').on('input', function () {
        var q = $.trim(this.value).toLowerCase();
        $('#productTable tbody tr').each(function () { $(this).toggle(!q || ($(this).attr('data-s') || '').indexOf(q) > -1); });
    });
});
</script>
<?php $pageScripts = ob_get_clean();
include __DIR__ . '/includes/footer.php';
