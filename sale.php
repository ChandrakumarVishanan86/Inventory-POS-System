<?php
/**
 * POST JSON: { items:[{id,qty}], discount, paid, payment_method }
 * Prices always come from the database, never from the browser.
 * Whole sale runs in ONE PDO transaction; any stock < 0 => ROLLBACK + "Out of Stock".
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/config.php';

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

class OutOfStockException extends RuntimeException
{
    public string $product;

    public function __construct(string $product)
    {
        parent::__construct('Out of Stock: ' . $product);
        $this->product = $product;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'POST only.'], 405);
}
if (!hash_equals($_SESSION['csrf'] ?? '', $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    respond(['success' => false, 'message' => 'Session expired. Please reload the POS page.'], 403);
}
session_write_close(); // release session lock

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in) || empty($in['items']) || !is_array($in['items'])) {
    respond(['success' => false, 'message' => 'Cart is empty.'], 400);
}

// Merge duplicate lines + validate
$items = [];
foreach ($in['items'] as $row) {
    $pid = (int)($row['id'] ?? 0);
    $qty = (int)($row['qty'] ?? 0);
    if ($pid <= 0 || $qty <= 0) {
        respond(['success' => false, 'message' => 'Invalid cart item.'], 400);
    }
    $items[$pid] = ($items[$pid] ?? 0) + $qty;
}
ksort($items); // consistent lock order => avoids deadlocks between two tills

$method     = in_array($in['payment_method'] ?? 'cash', ['cash', 'card'], true) ? $in['payment_method'] : 'cash';
$discountIn = max(0.0, (float)($in['discount'] ?? 0));
$paid       = max(0.0, (float)($in['paid'] ?? 0));
$invoice    = 'INV-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

$pdo = Database::connect();

try {
    $pdo->beginTransaction();

    // 1) sales header (totals filled once lines are priced)
    $pdo->prepare(
        'INSERT INTO sales (invoice_no, subtotal, discount, total, paid, change_amount, payment_method)
         VALUES (?,0,0,0,0,0,?)'
    )->execute([$invoice, $method]);
    $saleId = (int)$pdo->lastInsertId();

    $getProduct = $pdo->prepare('SELECT id, name, price FROM products WHERE id = ? FOR UPDATE');
    $deduct     = $pdo->prepare('UPDATE products SET qty = qty - ? WHERE id = ?');
    $getQty     = $pdo->prepare('SELECT qty FROM products WHERE id = ?');
    $addItem    = $pdo->prepare(
        'INSERT INTO sale_items (sale_id, product_id, product_name, price, qty, line_total) VALUES (?,?,?,?,?,?)'
    );

    // 2) loop items: insert line + deduct stock
    $subtotal = 0.0;
    foreach ($items as $pid => $sold) {
        $getProduct->execute([$pid]);
        $p = $getProduct->fetch();
        if (!$p) {
            throw new RuntimeException("Product #$pid no longer exists.");
        }

        $deduct->execute([$sold, $pid]);          // UPDATE products SET qty = qty - sold_qty
        $getQty->execute([$pid]);
        if ((int)$getQty->fetchColumn() < 0) {    // went below zero => abort everything
            throw new OutOfStockException($p['name']);
        }

        $price = (float)$p['price'];
        $line  = round($price * $sold, 2);
        $addItem->execute([$saleId, $pid, $p['name'], $price, $sold, $line]);
        $subtotal += $line;
    }

    // 3) totals
    $subtotal = round($subtotal, 2);
    $discount = min(round($discountIn, 2), $subtotal);
    $total    = round($subtotal - $discount, 2);
    if ($method === 'card') {
        $paid = $total;
    }
    if ($paid + 0.001 < $total) {
        throw new RuntimeException('Amount paid is less than the total.');
    }
    $change = round($paid - $total, 2);

    $pdo->prepare('UPDATE sales SET subtotal=?, discount=?, total=?, paid=?, change_amount=? WHERE id=?')
        ->execute([$subtotal, $discount, $total, $paid, $change, $saleId]);

    $pdo->commit();

    respond([
        'success'    => true,
        'sale_id'    => $saleId,
        'invoice_no' => $invoice,
        'total'      => $total,
        'paid'       => $paid,
        'change'     => $change,
    ]);
} catch (OutOfStockException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respond(['success' => false, 'error' => 'out_of_stock', 'message' => $e->getMessage(), 'product' => $e->product], 409);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('sale.php: ' . $e->getMessage());
    $msg = $e instanceof RuntimeException ? $e->getMessage() : 'Server error. The sale was not saved.';
    respond(['success' => false, 'message' => $msg], 500);
}
