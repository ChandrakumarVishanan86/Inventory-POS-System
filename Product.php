<?php
class Product
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public static function imageUrl(?string $file): ?string
    {
        return $file ? UPLOAD_URL . rawurlencode($file) : null;
    }

    /** Shape sent to the POS JavaScript. */
    public static function forClient(array $p): array
    {
        return [
            'id'       => (int)$p['id'],
            'barcode'  => $p['barcode'],
            'name'     => $p['name'],
            'category' => $p['category'],
            'price'    => (float)$p['price'],
            'qty'      => (int)$p['qty'],
            'low'      => (int)$p['low_stock_level'],
            'image'    => self::imageUrl($p['image']),
        ];
    }

    public function all(): array
    {
        return $this->db->query('SELECT * FROM products ORDER BY name')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM products WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function findByBarcode(string $barcode): ?array
    {
        $st = $this->db->prepare('SELECT * FROM products WHERE barcode = ?');
        $st->execute([$barcode]);
        return $st->fetch() ?: null;
    }

    public function categories(): array
    {
        return $this->db->query('SELECT DISTINCT category FROM products ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
    }

    public function lowStock(): array
    {
        return $this->db->query('SELECT * FROM products WHERE qty <= low_stock_level ORDER BY qty ASC, name')->fetchAll();
    }

    public function count(): int
    {
        return (int)$this->db->query('SELECT COUNT(*) FROM products')->fetchColumn();
    }

    /** Insert or update. $file is the $_FILES['image'] entry (or null). */
    public function save(array $d, ?array $file): int
    {
        $id       = (int)($d['id'] ?? 0);
        $barcode  = trim($d['barcode'] ?? '');
        $name     = trim($d['name'] ?? '');
        $category = trim($d['category'] ?? '') ?: 'General';
        $price    = (float)($d['price'] ?? 0);
        $cost     = (float)($d['cost'] ?? 0);
        $qty      = (int)($d['qty'] ?? 0);
        $low      = (int)($d['low_stock_level'] ?? 10);

        if ($barcode === '' || $name === '') {
            throw new RuntimeException('Barcode and name are required.');
        }
        if ($price < 0 || $cost < 0 || $qty < 0 || $low < 0) {
            throw new RuntimeException('Price, cost and quantities cannot be negative.');
        }

        $image = $this->uploadImage($file);

        try {
            if ($id > 0) {
                $old = $this->find($id);
                if (!$old) {
                    throw new RuntimeException('Product not found.');
                }
                $sql = 'UPDATE products SET barcode=:b, name=:n, category=:c, price=:p, cost=:co, qty=:q, low_stock_level=:l'
                     . ($image ? ', image=:i' : '') . ' WHERE id=:id';
                $params = [':b' => $barcode, ':n' => $name, ':c' => $category, ':p' => $price,
                           ':co' => $cost, ':q' => $qty, ':l' => $low, ':id' => $id];
                if ($image) {
                    $params[':i'] = $image;
                }
                $this->db->prepare($sql)->execute($params);
                if ($image && $old['image']) {
                    $this->removeImage($old['image']);
                }
                return $id;
            }

            $st = $this->db->prepare(
                'INSERT INTO products (barcode, name, category, price, cost, qty, low_stock_level, image)
                 VALUES (?,?,?,?,?,?,?,?)'
            );
            $st->execute([$barcode, $name, $category, $price, $cost, $qty, $low, $image]);
            return (int)$this->db->lastInsertId();
        } catch (PDOException $e) {
            if ($image) {
                $this->removeImage($image);
            }
            if ($e->getCode() === '23000') {
                throw new RuntimeException('That barcode already exists.');
            }
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $p = $this->find($id);
        if (!$p) {
            return;
        }
        $this->db->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
        if ($p['image']) {
            $this->removeImage($p['image']);
        }
    }

    private function uploadImage(?array $f): ?string
    {
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($f['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Image upload failed (error code ' . $f['error'] . ').');
        }
        if ($f['size'] > MAX_UPLOAD_BYTES) {
            throw new RuntimeException('Image must be 2 MB or smaller.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? null;
        if (!$ext) {
            throw new RuntimeException('Only JPG, PNG, WEBP or GIF images are allowed.');
        }
        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0775, true);
        }
        $name = bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . $name)) {
            throw new RuntimeException('Could not save the uploaded image. Check folder permissions.');
        }
        return $name;
    }

    private function removeImage(string $file): void
    {
        $path = UPLOAD_DIR . basename($file);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
