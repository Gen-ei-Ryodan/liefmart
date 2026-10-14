<?php

namespace App\Queries\Analytics\Stock;

use Illuminate\Support\Facades\DB;

/**
 * StockAnalyticsQuery
 *
 * Query untuk analytics Warehouse Stock dengan optimasi SQL
 * Menggantikan perhitungan PHP dengan SQL aggregation
 */
class StockAnalyticsQuery
{
    /**
     * Build query untuk stock analytics dengan grouping per product
     */
    public static function build(array $filters = []): string
    {
        $conditions = self::buildWhereConditions($filters);

        return "
            SELECT 
                p.id as product_id,
                p.sku,
                p.name as product_name,
                p.main_category_id,
                p.brand_id,
                p.sub_brand_id,
                p.product_category_id,
                p.product_type_id,
                p.product_size_id,
                p.product_variant_id,
                
                -- Aggregations
                SUM(ws.qty) as total_qty,
                COUNT(DISTINCT ws.lokasi_id) as location_count,
                COUNT(DISTINCT CASE WHEN ws.expired_date IS NOT NULL THEN ws.expired_date END) as expired_dates_count,
                MAX(CASE WHEN ws.expired_date < NOW() THEN 1 ELSE 0 END) as has_expired,
                MIN(ws.expired_date) as earliest_expiry,
                COUNT(DISTINCT ws.tax_id) as tax_categories_count,
                MAX(CASE WHEN pd.is_free = 1 THEN 1 ELSE 0 END) as is_free
                
            FROM warehouse_stock ws
            INNER JOIN products p ON ws.product_id = p.id
            LEFT JOIN penerimaan_detail pd ON ws.penerimaan_detail_id = pd.id
            WHERE ws.is_damaged = 0
            {$conditions}
            GROUP BY 
                p.id, p.sku, p.name,
                p.main_category_id, p.brand_id, p.sub_brand_id,
                p.product_category_id, p.product_type_id,
                p.product_size_id, p.product_variant_id
            ORDER BY p.name ASC
        ";
    }

    /**
     * Build query untuk mendapatkan locations per product
     */
    public static function buildLocations(array $filters = [], array $productIds = []): string
    {
        $conditions = self::buildWhereConditions($filters);
        $productFilter = '';

        if (! empty($productIds)) {
            $productIdsStr = implode(',', array_map('intval', $productIds));
            $productFilter = "AND ws.product_id IN ({$productIdsStr})";
        }

        return "
            SELECT 
                ws.product_id,
                ws.lokasi_id,
                l.nama as lokasi_nama,
                SUM(ws.qty) as qty
            FROM warehouse_stock ws
            INNER JOIN products p ON ws.product_id = p.id
            LEFT JOIN lokasi l ON ws.lokasi_id = l.id
            WHERE ws.is_damaged = 0
            {$conditions}
            {$productFilter}
            GROUP BY ws.product_id, ws.lokasi_id, l.nama
            ORDER BY ws.product_id, l.nama
        ";
    }

    /**
     * Build query untuk summary cards
     */
    public static function buildSummary(array $filters = []): string
    {
        $conditions = self::buildWhereConditions($filters);

        return "
            SELECT 
                COUNT(DISTINCT ws.product_id) as total_items,
                COALESCE(SUM(ws.qty), 0) as total_quantity,
                COUNT(DISTINCT CASE 
                    WHEN ws.expired_date IS NOT NULL 
                    AND ws.expired_date < NOW() 
                    THEN ws.product_id 
                END) as expired_products_count,
                COUNT(DISTINCT CASE 
                    WHEN (
                        SELECT COUNT(DISTINCT ws2.expired_date) 
                        FROM warehouse_stock ws2 
                        WHERE ws2.product_id = ws.product_id 
                        AND ws2.expired_date IS NOT NULL
                        AND ws2.is_damaged = 0
                    ) > 1 
                    THEN ws.product_id 
                END) as multi_ed_products_count
            FROM warehouse_stock ws
            INNER JOIN products p ON ws.product_id = p.id
            WHERE ws.is_damaged = 0
            {$conditions}
        ";
    }

    /**
     * Build query untuk mendapatkan data inventory value
     * Returns raw data untuk dihitung di PHP karena kompleksitas perhitungan diskon bertingkat
     */
    public static function buildInventoryValueData(): string
    {
        return "
            SELECT 
                ws.product_id,
                ws.qty,
                pd.harga_hpp,
                pd.diskon_persen_1,
                pd.diskon_persen_2,
                pd.diskon_persen_3,
                pd.diskon_persen_4,
                pd.diskon_persen_5,
                pd.diskon_nominal_1,
                pd.diskon_nominal_2,
                pd.diskon_nominal_3,
                pd.diskon_nominal_4,
                pd.diskon_nominal_5
            FROM warehouse_stock ws
            LEFT JOIN penerimaan_detail pd ON ws.penerimaan_detail_id = pd.id
            WHERE ws.is_damaged = 0
            AND (ws.source_type IS NULL OR ws.source_type != 'penyesuaian')
            AND ws.qty > 0
            AND pd.harga_hpp IS NOT NULL
        ";
    }

    /**
     * Baris stok bernilai negatif (mis. penyesuaian stock opname) tidak ikut
     * terhitung di buildInventoryValueData() karena filter qty > 0, dan tidak
     * punya harga_hpp sendiri karena tidak terikat penerimaan_detail.
     *
     * Dipakai untuk mengurangi total nilai inventaris, agar unit yang sudah
     * keluar lewat penyesuaian tidak tetap dinilai.
     *
     * @return string
     */
    public static function buildNegativeStockData(): string
    {
        return "
            SELECT 
                ws.product_id,
                SUM(ws.qty) AS qty
            FROM warehouse_stock ws
            WHERE ws.is_damaged = 0
            AND ws.qty < 0
            GROUP BY ws.product_id
        ";
    }

    /**
     * Build WHERE conditions berdasarkan filters
     */
    private static function buildWhereConditions(array $filters): string
    {
        $conditions = [];
        $pdo = DB::getPdo();

        // Search filter
        if (! empty($filters['search'])) {
            $search = $pdo->quote('%'.$filters['search'].'%');
            $conditions[] = "p.name LIKE {$search}";
        }

        // SKU filter
        if (! empty($filters['sku'])) {
            $sku = $pdo->quote('%'.$filters['sku'].'%');
            $conditions[] = "p.sku LIKE {$sku}";
        }

        // Status ED filter
        if (! empty($filters['status_ed'])) {
            switch ($filters['status_ed']) {
                case 'kadaluarsa':
                    $conditions[] = 'ws.expired_date < NOW()';
                    break;
                case 'kurang_dari_3_bulan':
                    $conditions[] = 'ws.expired_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 MONTH)';
                    break;
                case 'kurang_dari_6_bulan':
                    $conditions[] = 'ws.expired_date BETWEEN DATE_ADD(NOW(), INTERVAL 3 MONTH) AND DATE_ADD(NOW(), INTERVAL 6 MONTH)';
                    break;
                case 'kurang_dari_1_tahun':
                    $conditions[] = 'ws.expired_date BETWEEN DATE_ADD(NOW(), INTERVAL 6 MONTH) AND DATE_ADD(NOW(), INTERVAL 1 YEAR)';
                    break;
                case 'lebih_dari_1_tahun':
                    $conditions[] = 'ws.expired_date > DATE_ADD(NOW(), INTERVAL 1 YEAR)';
                    break;
                case 'tidak_ada_ed':
                    $conditions[] = 'ws.expired_date IS NULL';
                    break;
            }
        }

        // Tax filter
        if (isset($filters['tax_id'])) {
            if ($filters['tax_id'] === 'N/A') {
                $conditions[] = 'ws.tax_id IS NULL';
            } else {
                $taxId = (int) $filters['tax_id'];
                $conditions[] = "ws.tax_id = {$taxId}";
            }
        }

        // Free item filter
        if (isset($filters['is_free'])) {
            $isFree = $filters['is_free'] ? 1 : 0;
            $conditions[] = "EXISTS (
                SELECT 1 FROM penerimaan_detail pd2 
                WHERE pd2.id = ws.penerimaan_detail_id 
                AND pd2.is_free = {$isFree}
            )";
        }

        // Brand filter
        if (! empty($filters['brand_id'])) {
            $brandId = (int) $filters['brand_id'];
            $conditions[] = "p.brand_id = {$brandId}";
        }

        // Sub Brand filter
        if (! empty($filters['sub_brand_id'])) {
            $subBrandId = (int) $filters['sub_brand_id'];
            $conditions[] = "p.sub_brand_id = {$subBrandId}";
        }

        // Product Category filter
        if (! empty($filters['product_category_id'])) {
            $categoryId = (int) $filters['product_category_id'];
            $conditions[] = "p.product_category_id = {$categoryId}";
        }

        // Product Type filter
        if (! empty($filters['product_type_id'])) {
            $typeId = (int) $filters['product_type_id'];
            $conditions[] = "p.product_type_id = {$typeId}";
        }

        // Product Size filter
        if (! empty($filters['product_size_id'])) {
            $sizeId = (int) $filters['product_size_id'];
            $conditions[] = "p.product_size_id = {$sizeId}";
        }

        // Product Variant filter
        if (! empty($filters['product_variant_id'])) {
            $variantId = (int) $filters['product_variant_id'];
            $conditions[] = "p.product_variant_id = {$variantId}";
        }

        return ! empty($conditions) ? 'AND '.implode(' AND ', $conditions) : '';
    }
}
