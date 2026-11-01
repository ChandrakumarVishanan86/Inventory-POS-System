<?php
class Report
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function todayTotal(): float
    {
        return (float)$this->db->query('SELECT COALESCE(SUM(total),0) FROM sales WHERE created_at >= CURDATE()')->fetchColumn();
    }

    public function todayCount(): int
    {
        return (int)$this->db->query('SELECT COUNT(*) FROM sales WHERE created_at >= CURDATE()')->fetchColumn();
    }

    /** @return array{labels:string[], totals:float[]} last 7 days incl. today, zero-filled */
    public function last7Days(): array
    {
        $rows = $this->db->query(
            'SELECT DATE(created_at) d, SUM(total) t FROM sales
             WHERE created_at >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(created_at)'
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        $labels = [];
        $totals = [];
        for ($i = 6; $i >= 0; $i--) {
            $day      = date('Y-m-d', strtotime("-$i day"));
            $labels[] = date('D d', strtotime($day));
            $totals[] = (float)($rows[$day] ?? 0);
        }
        return ['labels' => $labels, 'totals' => $totals];
    }

    public function topProducts(int $limit = 5): array
    {
        $st = $this->db->prepare(
            'SELECT product_name, SUM(qty) AS sold FROM sale_items
             GROUP BY product_name ORDER BY sold DESC LIMIT ' . (int)$limit
        );
        $st->execute();
        return $st->fetchAll();
    }

    public function recentSales(int $limit = 8): array
    {
        return $this->db->query('SELECT * FROM sales ORDER BY id DESC LIMIT ' . (int)$limit)->fetchAll();
    }
}
