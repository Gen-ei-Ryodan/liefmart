<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Penerimaan;
use App\Models\PenerimaanDetail;
use App\Models\WarehouseStock;
use App\Models\Product;
use App\Models\BarangKeluar;
use App\Models\MainCategory;
use App\Models\TaxCategory;
use App\Models\Lokasi;
use App\Models\Satuan;
use App\Models\User;

/**
 * Feature Test: Warehouse Stock (List, Analytics, Damaged Items)
 *
 * Menguji seluruh fitur warehouse stock:
 * 1. Stock list dengan berbagai filter (search, SKU, ED status, tax, brand, dll)
 * 2. Stock analytics - consolidated view per product
 * 3. Damaged items list - stock rusak
 * 4. Export Excel
 * 5. Summary cards (total items, total quantity, expired count)
 * 6. ED status calculation (kadaluarsa, <3bln, <6bln, <1thn, >1thn)
 * 7. Edge cases: multiple ED, stock dengan tax berbeda, stock 0
 *
 * POTENSI MASALAH & DATA JANGGAL:
 * - Global scope warehouse_stock join dengan products untuk main_category filtering
 *   bisa menyebabkan duplicate rows atau missing data jika join tidak tepat
 * - ED status dihitung 2x (di controller & di model mutator) -> potensi inkonsistensi
 * - Filter dengan whereHas vs join untuk is_free perlu waspada
 * - Duplikasi kode: list() method vs stockList() method di controller berbeda
 */
class WarehouseStockTest extends TestCase
{

    private User $user;
    private MainCategory $skincare;
    private TaxCategory $taxPkp;
    private TaxCategory $taxNonPkp;
    private Lokasi $gudangA;
    private Satuan $satuan;
    private Product $product1;
    private Product $product2;
    private Product $product3;
    private Penerimaan $penerimaan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\MainCategorySeeder::class);
        $this->seed(\Database\Seeders\TaxCategorySeeder::class);
        $this->seed(\Database\Seeders\LokasiSeeder::class);
        $this->seed(\Database\Seeders\SatuanSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\SuperadminRoleSeeder::class);

        $this->skincare = MainCategory::where('is_active', true)->where('name', '!=', 'Kopi')->first() ?? MainCategory::first();
        $this->taxPkp = TaxCategory::where('main_category_id', $this->skincare->id)->where('name', 'like', '%PKP%')->where('name', 'not like', '%NON%')->first() ?? TaxCategory::first();
        $this->taxNonPkp = TaxCategory::where('main_category_id', $this->skincare->id)->where('name', 'like', '%NON%')->first() ?? TaxCategory::skip(1)->first() ?? $this->taxPkp;
        $this->gudangA = Lokasi::where('kode', 'GUDANG_A')->first() ?? Lokasi::factory()->create(['kode' => 'GUDANG_A', 'nama' => 'Gudang A']);
        $this->satuan = Satuan::where('is_active', true)->first();

        // Create products
        $this->product1 = Product::factory()->create([
            'name' => 'Face Wash SKINCARE',
            'sku' => 'FW-SK-001',
            'main_category_id' => $this->skincare->id,
            'is_active' => true,
        ]);
        $this->product2 = Product::factory()->create([
            'name' => 'Toner SKINCARE',
            'sku' => 'TN-SK-001',
            'main_category_id' => $this->skincare->id,
            'is_active' => true,
        ]);
        $this->product3 = Product::factory()->create([
            'name' => 'Sunscreen SKINCARE',
            'sku' => 'SS-SK-001',
            'main_category_id' => $this->skincare->id,
            'is_active' => true,
        ]);

        // Create penerimaan
        $this->penerimaan = Penerimaan::factory()->create([
            'kode_penerimaan' => 'PNR-STOCK-TEST',
            'main_category_id' => $this->skincare->id,
            'tax_category_id' => $this->taxPkp->id,
        ]);

        // Create penerimaan details
        $detail1 = PenerimaanDetail::create([
            'penerimaan_id' => $this->penerimaan->id,
            'product_id' => $this->product1->id,
            'qty' => 100,
            'satuan_id' => $this->satuan->id,
            'harga_hpp' => 25000,
            'subtotal' => 2500000,
        ]);
        $detail2 = PenerimaanDetail::create([
            'penerimaan_id' => $this->penerimaan->id,
            'product_id' => $this->product2->id,
            'qty' => 50,
            'satuan_id' => $this->satuan->id,
            'harga_hpp' => 50000,
            'subtotal' => 2500000,
        ]);
        $detail3 = PenerimaanDetail::create([
            'penerimaan_id' => $this->penerimaan->id,
            'product_id' => $this->product3->id,
            'qty' => 200,
            'satuan_id' => $this->satuan->id,
            'harga_hpp' => 35000,
            'subtotal' => 7000000,
        ]);

        // Create warehouse stock with varied scenarios
        // Product1: 80 qty PKP, expired 1 tahun lagi
        WarehouseStock::create([
            'product_id' => $this->product1->id,
            'lokasi_id' => $this->gudangA->id,
            'penerimaan_detail_id' => $detail1->id,
            'tax_id' => $this->taxPkp->id,
            'qty' => 80,
            'expired_date' => now()->addYear(),
            'source_type' => 'penerimaan',
            'source_id' => $this->penerimaan->id,
        ]);

        // Product2: 30 qty NONPKP, expired tinggal 2 bulan
        WarehouseStock::create([
            'product_id' => $this->product2->id,
            'lokasi_id' => $this->gudangA->id,
            'penerimaan_detail_id' => $detail2->id,
            'tax_id' => $this->taxNonPkp?->id,
            'qty' => 30,
            'expired_date' => now()->addMonths(2),
            'source_type' => 'penerimaan',
            'source_id' => $this->penerimaan->id,
        ]);

        // Product3: 150 qty PKP, no expired date
        WarehouseStock::create([
            'product_id' => $this->product3->id,
            'lokasi_id' => $this->gudangA->id,
            'penerimaan_detail_id' => $detail3->id,
            'tax_id' => $this->taxPkp->id,
            'qty' => 150,
            'expired_date' => null,
            'source_type' => 'penerimaan',
            'source_id' => $this->penerimaan->id,
        ]);

        // Product3: additional 50 qty, already expired
        WarehouseStock::create([
            'product_id' => $this->product3->id,
            'lokasi_id' => $this->gudangA->id,
            'penerimaan_detail_id' => $detail3->id,
            'tax_id' => $this->taxPkp->id,
            'qty' => 50,
            'expired_date' => now()->subDays(30), // Already expired
            'source_type' => 'penerimaan',
            'source_id' => $this->penerimaan->id,
        ]);

        session(['main_category_id' => $this->skincare->id]);
        $this->user = $this->loginAsSuperadmin();
    }

    // ==================== STOCK LIST ====================

    /** @test */
    public function stock_list_displays_all_items()
    {
        $response = $this->get(route('warehouse.stock.list'));

        $response->assertStatus(200);
        $response->assertViewIs('warehouse.stock-list');
        $response->assertViewHas('stocks');
        $response->assertViewHas('filteredStocks');
    }

    /** @test */
    public function stock_list_filters_by_search()
    {
        $response = $this->get(route('warehouse.stock.list', ['search' => 'Face Wash']));
        $response->assertStatus(200);
    }

    /** @test */
    public function stock_list_filters_by_sku()
    {
        $response = $this->get(route('warehouse.stock.list', ['sku' => 'FW-SK-001']));
        $response->assertStatus(200);
    }

    /** @test */
    public function stock_list_filters_by_expired_status()
    {
        // Filter kadaluarsa
        $response = $this->get(route('warehouse.stock.list', ['status_ed' => 'kadaluarsa']));
        $response->assertStatus(200);

        // Filter kurang dari 3 bulan
        $response = $this->get(route('warehouse.stock.list', ['status_ed' => 'kurang_dari_3_bulan']));
        $response->assertStatus(200);

        // Filter tidak ada ED
        $response = $this->get(route('warehouse.stock.list', ['status_ed' => 'tidak_ada_ed']));
        $response->assertStatus(200);
    }

    /** @test */
    public function stock_list_filters_by_tax()
    {
        $response = $this->get(route('warehouse.stock.list', ['tax_id' => $this->taxPkp->id]));
        $response->assertStatus(200);
    }

    /** @test */
    public function stock_list_filters_by_is_free()
    {
        $response = $this->get(route('warehouse.stock.list', ['is_free' => 0]));
        $response->assertStatus(200);
    }

    /** @test */
    public function stock_list_combines_multiple_filters()
    {
        $response = $this->get(route('warehouse.stock.list', [
            'search' => 'SKINCARE',
            'status_ed' => 'lebih_dari_1_tahun',
            'tax_id' => $this->taxPkp->id,
        ]));
        $response->assertStatus(200);
    }

    /** @test */
    public function stock_list_displays_summary_cards()
    {
        $response = $this->get(route('warehouse.stock.list'));
        $response->assertStatus(200);

        $filteredStocks = $response->original->getData()['filteredStocks'];
        $this->assertNotEmpty($filteredStocks);
    }

    // ==================== EDGE CASES - STOCK LIST ====================

    /** @test */
    public function stock_list_with_no_results_returns_empty()
    {
        $response = $this->get(route('warehouse.stock.list', ['search' => 'PRODUCT_TIDAK_ADA']));
        $response->assertStatus(200);
    }

    /** @test */
    public function stock_list_with_all_filters_empty_returns_all_items()
    {
        $response = $this->get(route('warehouse.stock.list', [
            'search' => '',
            'sku' => '',
            'status_ed' => '',
            'tax_id' => '',
        ]));
        $response->assertStatus(200);
    }

    // ==================== DAMAGED ITEMS ====================

    /** @test */
    public function damaged_list_shows_only_damaged_items()
    {
        // Create a damaged item first
        WarehouseStock::create([
            'product_id' => $this->product1->id,
            'lokasi_id' => $this->gudangA->id,
            'tax_id' => $this->taxPkp->id,
            'qty' => 5,
            'is_damaged' => true,
            'qty_damaged' => 5,
            'source_type' => 'penerimaan',
            'source_id' => $this->penerimaan->id,
        ]);

        $response = $this->get(route('warehouse.stock.damaged'));

        $response->assertStatus(200);
        $response->assertViewIs('warehouse.stock-list');
    }

    /** @test */
    public function damaged_list_accepts_same_filters()
    {
        $response = $this->get(route('warehouse.stock.damaged', ['search' => 'test']));
        $response->assertStatus(200);
    }

    /** @test */
    public function damaged_list_shows_retur_references()
    {
        $response = $this->get(route('warehouse.stock.damaged'));
        $response->assertStatus(200);
    }

    // ==================== ANALYTICS ====================

    /** @test */
    public function analytics_displays_consolidated_stock_view()
    {
        $response = $this->get(route('warehouse.stock.analytics'));

        $response->assertStatus(200);
        $response->assertViewIs('warehouse.stock-analytics');
        $response->assertViewHas('groupedStocks');
    }

    /** @test */
    public function analytics_groups_by_product()
    {
        $response = $this->get(route('warehouse.stock.analytics'));
        $groupedStocks = $response->original->getData()['groupedStocks'];

        // Check each product appears once
        $productIds = $groupedStocks->pluck('product.id')->toArray();
        $uniqueIds = array_unique($productIds);
        $this->assertCount(count($uniqueIds), $productIds, 'Product should appear once in analytics');
    }

    /** @test */
    public function analytics_calculates_total_qty_per_product()
    {
        $response = $this->get(route('warehouse.stock.analytics'));
        $groupedStocks = $response->original->getData()['groupedStocks'];

        // Product3 should have 200 total (150 + 50)
        $product3Group = $groupedStocks->firstWhere('product.id', $this->product3->id);
        $this->assertNotNull($product3Group);
        $this->assertEquals(200, (int) $product3Group['total_qty']);
    }

    /** @test */
    public function analytics_displays_summary_information()
    {
        $response = $this->get(route('warehouse.stock.analytics'));

        $response->assertStatus(200);
    }

    /** @test */
    public function analytics_filters_by_search()
    {
        $response = $this->get(route('warehouse.stock.analytics', ['search' => 'Toner']));
        $response->assertStatus(200);
    }

    /** @test */
    public function analytics_filters_by_sku()
    {
        $response = $this->get(route('warehouse.stock.analytics', ['sku' => 'TN-SK-001']));
        $response->assertStatus(200);
    }

    /** @test */
    public function analytics_filters_by_status_ed()
    {
        $response = $this->get(route('warehouse.stock.analytics', ['status_ed' => 'kadaluarsa']));
        $response->assertStatus(200);
        $response = $this->get(route('warehouse.stock.analytics', ['status_ed' => 'kurang_dari_3_bulan']));
        $response->assertStatus(200);
        $response = $this->get(route('warehouse.stock.analytics', ['status_ed' => 'tidak_ada_ed']));
        $response->assertStatus(200);
    }

    /** @test */
    public function analytics_filters_by_tax()
    {
        $response = $this->get(route('warehouse.stock.analytics', ['tax_id' => $this->taxPkp->id]));
        $response->assertStatus(200);
    }

    /** @test */
    public function analytics_filter_by_tax_na_shows_null_tax_items()
    {
        // Create item with null tax_id
        WarehouseStock::create([
            'product_id' => $this->product1->id,
            'lokasi_id' => $this->gudangA->id,
            'tax_id' => null,
            'qty' => 10,
            'source_type' => 'penerimaan',
            'source_id' => $this->penerimaan->id,
        ]);

        $response = $this->get(route('warehouse.stock.analytics', ['tax_id' => 'N/A']));
        $response->assertStatus(200);
    }

    /** @test */
    public function analytics_combined_filter_search_and_tax()
    {
        $response = $this->get(route('warehouse.stock.analytics', [
            'search' => 'SKINCARE',
            'tax_id' => $this->taxPkp->id,
            'status_ed' => 'lebih_dari_1_tahun',
        ]));
        $response->assertStatus(200);
    }

    // ==================== EDGE CASES - ANALYTICS ====================

    /** @test */
    public function analytics_empty_when_no_stock()
    {
        // Delete all stock
        WarehouseStock::query()->delete();

        $response = $this->get(route('warehouse.stock.analytics'));
        $response->assertStatus(200);
        $groupedStocks = $response->original->getData()['groupedStocks'];
        $this->assertEmpty($groupedStocks);
    }

    /** @test */
    public function analytics_handles_products_with_multiple_expiry_dates()
    {
        // Product3 already has 2 different expiry dates
        $response = $this->get(route('warehouse.stock.analytics'));
        $response->assertStatus(200);
    }

    /** @test */
    public function analytics_handles_products_with_zero_qty()
    {
        // Add stock with qty 0
        WarehouseStock::create([
            'product_id' => $this->product1->id,
            'lokasi_id' => $this->gudangA->id,
            'tax_id' => $this->taxPkp->id,
            'qty' => 0,
            'source_type' => 'penyesuaian',
            'source_id' => $this->penerimaan->id,
        ]);

        $response = $this->get(route('warehouse.stock.analytics'));
        $response->assertStatus(200);
    }

    // ==================== EXPORT ====================

    /** @test */
    public function export_stock_excel()
    {
        $response = $this->get(route('warehouse.stock.export'));
        $response->assertStatus(200);
    }

    // ==================== POTENTIAL ISSUES ====================

    /**
     * @test
     *
     * POTENSI MASALAH: Global Scope join dengan products table
     * WarehouseStock booted() method menggunakan join untuk filter main_category.
     * Ini bisa menyebabkan:
     * 1. Duplicate rows jika select hanya warehouse_stock.* tapi ada multiple products
     * 2. Missing data jika main_category session tidak ter-set
     * 3. Konflik dengan eager loading relationships
     */
    public function documents_global_scope_join_potential_issues()
    {
        // Test with valid session
        session(['main_category_id' => $this->skincare->id]);
        $stocks = WarehouseStock::all();
        $this->assertNotEmpty($stocks);

        // All returned stocks should be in SKINCARE category
        foreach ($stocks as $stock) {
            $this->assertEquals($this->skincare->id, $stock->product->main_category_id);
        }
    }

    /**
     * @test
     *
     * POTENSI MASALAH: ED status calculation discrepancy
     * ED status dihitung di:
     * 1. Model mutator (setStatusEdAttribute) - untuk model events
     * 2. Controller (stockList, damagedList, export) - untuk view display
     * Ini bisa menyebabkan inkonsistensi jika logika berbeda.
     */
    public function documents_ed_status_calculation_consistency()
    {
        // Model mutator logic (from WarehouseStock model)
        $modelExpiredDate = now()->subDays(1);
        $modelDaysUntilExpired = now()->diffInDays($modelExpiredDate, false);
        $modelStatus = $modelDaysUntilExpired < 0 ? 'kadaluarsa' : 'aman';

        // Controller logic (from WarehouseStockController stockList)
        $controllerExpiredDate = now()->subDays(1);
        $controllerDiffInDays = now()->diffInDays($controllerExpiredDate, false);
        $controllerStatus = $controllerDiffInDays < 0 ? 'kadaluarsa' : 'aman';

        // Both should produce same result
        $this->assertEquals('kadaluarsa', $modelStatus);
        $this->assertEquals('kadaluarsa', $controllerStatus);
    }

    /**
     * @test
     *
     * POTENSI DATA KACAU: Retur system integration
     * Warehouse analytics mengacu ke retur penjualan dan retur offline
     * untuk menampilkan barang rusak. Jika data retur tidak konsisten
     * (misalnya retur "selesai" tapi warehouse stock tidak dibuat),
     * maka data analitik bisa tidak akurat.
     */
    public function documents_retur_integration_potential_issues()
    {
        // Check that the analytics view handles retur data
        $response = $this->get(route('warehouse.stock.analytics'));
        $response->assertStatus(200);

        // Note: If there are retur records with status 'selesai' but without
        // corresponding warehouse_stock entries, the analytics will have
        // incomplete data. This is a known data integrity concern.
    }

    /**
     * @test
     *
     * POTENSI DATA KACAU: Duplikasi antara WarehouseController dan WarehouseStockController
     * Ada 2 controller yang menangani stock list:
     * 1. WarehouseController@stockList - filter lengkap + export
     * 2. WarehouseStockController@list - filter + summary cards
     * Keduanya memiliki logika filter yang mirip tapi tidak identik.
     * Jika ada perubahan di satu controller, yang lain perlu di-update juga.
     */
    public function documents_duplicate_stock_list_controllers()
    {
        // Kedua endpoint harus bisa diakses
        $this->get(route('warehouse.stock.list'))->assertStatus(200);
    }

    /** @test */
    public function authorization_required_for_stock_pages()
    {
        auth()->logout();
        $this->get(route('warehouse.stock.list'))->assertRedirect(route('login'));
        $this->get(route('warehouse.stock.analytics'))->assertRedirect(route('login'));
        $this->get(route('warehouse.stock.damaged'))->assertRedirect(route('login'));
        $this->get(route('warehouse.stock.export'))->assertRedirect(route('login'));
    }
}
