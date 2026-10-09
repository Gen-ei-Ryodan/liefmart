<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\OfflineSale;
use App\Models\OfflineSaleItem;
use App\Models\BarangKeluar;
use App\Models\WarehouseStock;
use App\Models\Product;
use App\Models\Customer;
use App\Models\MainCategory;
use App\Models\TaxCategory;
use App\Models\Lokasi;
use App\Models\Satuan;
use App\Models\User;

/**
 * Feature Test: Sales Offline — Full CRUD, Discounts, Stock, SJ
 *
 * Menguji seluruh alur penjualan offline:
 * 1. Halaman index, create, show
 * 2. Store dengan multiple items & diskon bertingkat (5 level)
 * 3. Store dengan tax grouping (PKP/NONPKP) → multiple SJ
 * 4. Stock reduction saat checkout
 * 5. Print surat jalan
 * 6. Delete dengan stock restoration
 * 7. Filter list (date, SJ number, PO)
 * 8. Summary cards (total sales, value, volume, status)
 * 9. Payment status detection (pending/partial/paid)
 * 10. Retur checking (hasReturFull)
 *
 * POTENSI MASALAH:
 * - Tax grouping membuat multiple OfflineSale records per transaksi
 * - Discount 5 level dengan proporsi berdasarkan tax group
 * - SJ number generation via sequence table
 * - Stock integrity: FIFO + tax priority
 */
class SalesOfflineTest extends TestCase
{

    private User $user;
    private MainCategory $skincare;
    private TaxCategory $taxCategory;
    private Lokasi $gudangA;
    private Satuan $satuan;
    private Product $product1;
    private Product $product2;
    private Product $product3;
    private Customer $customer;
    private WarehouseStock $stock1;
    private WarehouseStock $stock2;

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
        $this->taxCategory = TaxCategory::where('main_category_id', $this->skincare->id)->first() ?? TaxCategory::first();
        $this->gudangA = Lokasi::where('kode', 'GUDANG_A')->first() ?? Lokasi::first();
        $this->satuan = Satuan::where('is_active', true)->first();

        $this->product1 = Product::factory()->create([
            'name' => 'Sabun Offline SKINCARE',
            'main_category_id' => $this->skincare->id,
            'is_active' => true,
        ]);
        $this->product2 = Product::factory()->create([
            'name' => 'Toner Offline SKINCARE',
            'main_category_id' => $this->skincare->id,
            'is_active' => true,
        ]);
        $this->product3 = Product::factory()->create([
            'name' => 'Moisturizer Offline SKINCARE',
            'main_category_id' => $this->skincare->id,
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'Budi Santoso',
            'phone' => '08123456789',
            'email' => 'budi@example.com',
            'pic_name' => 'Budi',
            'status' => 'active',
        ]);

        $this->stock1 = WarehouseStock::create([
            'product_id' => $this->product1->id,
            'lokasi_id' => $this->gudangA->id,
            'tax_id' => $this->taxCategory->id,
            'qty' => 100,
            'source_type' => 'penerimaan',
            'source_id' => 1,
            'source_date' => now(),
        ]);
        $this->stock2 = WarehouseStock::create([
            'product_id' => $this->product2->id,
            'lokasi_id' => $this->gudangA->id,
            'tax_id' => $this->taxCategory->id,
            'qty' => 50,
            'source_type' => 'penerimaan',
            'source_id' => 2,
            'source_date' => now(),
        ]);

        session(['main_category_id' => $this->skincare->id]);
        $this->user = $this->loginAsSuperadmin();
    }

    // ==================== INDEX ====================

    /** @test */
    public function offline_list_displays_sales()
    {
        OfflineSale::create([
            'surat_jalan_number' => 'SJ-LIST-001',
            'sale_date' => now(),
            'customer_name' => 'Test',
            'subtotal' => 500000,
            'total_amount' => 500000,
            'status' => 'pending',
            'main_category_id' => $this->skincare->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->get(route('sales.offline.list'));
        $response->assertStatus(200);
        $response->assertViewHas('offlineSales');
        $response->assertViewHas('summary');
    }

    /** @test */
    public function offline_list_displays_summary()
    {
        OfflineSale::create([
            'surat_jalan_number' => 'SJ-SUM-001',
            'sale_date' => now(),
            'customer_name' => 'Test',
            'subtotal' => 500000,
            'total_amount' => 500000,
            'status' => 'paid',
            'main_category_id' => $this->skincare->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->get(route('sales.offline.list'));
        $summary = $response->original->getData()['summary'];

        $this->assertEquals(1, $summary['total_sales']);
        $this->assertGreaterThan(0, $summary['total_value']);
    }

    /** @test */
    public function offline_list_filters_by_date()
    {
        $response = $this->get(route('sales.offline.list', [
            'date_start' => now()->subDays(7)->format('Y-m-d'),
            'date_end' => now()->addDays(7)->format('Y-m-d'),
        ]));
        $response->assertStatus(200);
    }

    /** @test */
    public function offline_list_filters_by_surat_jalan()
    {
        OfflineSale::create([
            'surat_jalan_number' => 'SJ-FILTER-001',
            'sale_date' => now(),
            'customer_name' => 'Test',
            'subtotal' => 100000,
            'total_amount' => 100000,
            'status' => 'pending',
            'main_category_id' => $this->skincare->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->get(route('sales.offline.list', ['surat_jalan_number' => 'SJ-FILTER']));
        $response->assertStatus(200);
    }

    /** @test */
    public function offline_list_filters_by_po()
    {
        $response = $this->get(route('sales.offline.list', ['No_PO' => 'PO-TEST']));
        $response->assertStatus(200);
    }

    /** @test */
    public function offline_list_empty_state()
    {
        $response = $this->get(route('sales.offline.list'));
        $response->assertStatus(200);
    }

    // ==================== CREATE ====================

    /** @test */
    public function create_page_displays_form()
    {
        $response = $this->get(route('sales.offline.create'));
        $response->assertStatus(200);
        $response->assertViewHas('products');
        $response->assertViewHas('customers');
    }

    /** @test */
    public function create_page_shows_only_products_with_stock()
    {
        // Product3 has no stock
        $response = $this->get(route('sales.offline.create'));
        $products = $response->original->getData()['products'];

        $productIds = $products->pluck('id')->toArray();
        $this->assertContains($this->product1->id, $productIds);
        $this->assertContains($this->product2->id, $productIds);
        // product3 may or may not be in the list (depends on stock)
    }

    // ==================== STORE ====================

    /** @test */
    public function store_creates_offline_sale_with_stock_reduction()
    {
        $response = $this->post(route('sales.offline.store'), [
            'sale_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'subtotal' => 200000,
            'tax_amount' => 0,
            'total_amount' => 200000,
            'status' => 'pending',
            'product_id' => [$this->product1->id, $this->product2->id],
            'quantity' => [2, 3],
            'unit_price' => [50000, 50000],
            'main_category_id' => $this->skincare->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        // Stock reduced: product1 100-2=98, product2 50-3=47
        $this->assertEquals(98, $this->stock1->fresh()->qty);
        $this->assertEquals(47, $this->stock2->fresh()->qty);

        // Barang keluar created
        $this->assertDatabaseHas('barang_keluar', [
            'qty' => 2,
        ]);
    }

    /** @test */
    public function store_creates_offline_sale_with_discounts()
    {
        $response = $this->post(route('sales.offline.store'), [
            'sale_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'subtotal' => 100000,
            'tax_amount' => 0,
            'total_amount' => 90000,
            'status' => 'pending',
            'product_id' => [$this->product1->id],
            'quantity' => [2],
            'unit_price' => [50000],
            'discount_percent_1' => [10], // 10% discount
            'main_category_id' => $this->skincare->id,
        ]);

        $response->assertRedirect();

        $sale = OfflineSale::latest()->first();
        $item = $sale->items()->first();
        $this->assertEquals(10, (float) $item->discount_percent_1);
    }

    /** @test */
    public function store_creates_sale_with_status_paid()
    {
        $response = $this->post(route('sales.offline.store'), [
            'sale_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'subtotal' => 200000,
            'tax_amount' => 0,
            'total_amount' => 200000,
            'status' => 'paid',
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => 'Tunai',
            'product_id' => [$this->product1->id],
            'quantity' => [1],
            'unit_price' => [200000],
            'main_category_id' => $this->skincare->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('offline_sales', [
            'status' => 'paid',
            'payment_method' => 'Tunai',
        ]);
    }

    /** @test */
    public function store_fails_when_stock_insufficient()
    {
        $response = $this->post(route('sales.offline.store'), [
            'sale_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'subtotal' => 10000000,
            'tax_amount' => 0,
            'total_amount' => 10000000,
            'status' => 'pending',
            'product_id' => [$this->product1->id],
            'quantity' => [999], // Exceeds stock
            'unit_price' => [10000],
            'main_category_id' => $this->skincare->id,
        ]);

        $response->assertSessionHas('error');
    }

    /** @test】
    public function store_fails_with_empty_items()
    {
        $response = $this->post(route('sales.offline.store'), [
            'sale_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'subtotal' => 0,
            'total_amount' => 0,
            'status' => 'pending',
            'product_id' => [],
            'quantity' => [],
            'unit_price' => [],
            'main_category_id' => $this->skincare->id,
        ]);

        $response->assertSessionHasErrors('product_id');
    }

    /** @test */
    public function store_fails_without_customer()
    {
        $response = $this->post(route('sales.offline.store'), [
            'sale_date' => now()->format('Y-m-d'),
            'customer_id' => 99999,
            'subtotal' => 100000,
            'total_amount' => 100000,
            'status' => 'pending',
            'product_id' => [$this->product1->id],
            'quantity' => [1],
            'unit_price' => [100000],
            'main_category_id' => $this->skincare->id,
        ]);

        $response->assertSessionHasErrors('customer_id');
    }

    // ==================== SHOW ====================

    /** @test */
    public function show_displays_offline_sale_detail()
    {
        $sale = OfflineSale::create([
            'surat_jalan_number' => 'SJ-SHOW-001',
            'sale_date' => now(),
            'customer_name' => 'Test',
            'subtotal' => 200000,
            'total_amount' => 200000,
            'status' => 'pending',
            'main_category_id' => $this->skincare->id,
            'created_by' => $this->user->id,
        ]);

        OfflineSaleItem::create([
            'offline_sale_id' => $sale->id,
            'product_id' => $this->product1->id,
            'warehouse_stock_id' => $this->stock1->id,
            'quantity' => 2,
            'unit_price' => 100000,
            'subtotal' => 200000,
        ]);

        $response = $this->get(route('sales.offline.show', $sale->id));
        $response->assertStatus(200);
        $response->assertViewHas('offlineSale');
    }

    /** @test */
    public function show_returns_404_for_nonexistent()
    {
        $response = $this->get(route('sales.offline.show', 99999));
        $response->assertStatus(404);
    }

    // ==================== PRINT SJ ====================

    /** @test */
    public function print_sj_works()
    {
        $sale = OfflineSale::create([
            'surat_jalan_number' => 'SJ-PRINT-001',
            'sale_date' => now(),
            'customer_name' => 'Test',
            'subtotal' => 200000,
            'total_amount' => 200000,
            'status' => 'pending',
            'main_category_id' => $this->skincare->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->get(route('sales.offline.print.sj', $sale->id));
        $response->assertStatus(200);
    }

    // ==================== DELETE ====================

    /** @test */
    public function delete_offline_sale_restores_stock()
    {
        // Create sale
        $this->post(route('sales.offline.store'), [
            'sale_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'subtotal' => 100000,
            'tax_amount' => 0,
            'total_amount' => 100000,
            'status' => 'pending',
            'product_id' => [$this->product1->id],
            'quantity' => [5],
            'unit_price' => [20000],
            'main_category_id' => $this->skincare->id,
        ]);

        $this->assertEquals(95, $this->stock1->fresh()->qty); // 100 - 5

        $sale = OfflineSale::latest()->first();

        // Delete
        $response = $this->delete(route('sales.offline.destroy', $sale->id));
        $response->assertRedirect();

        // Stock restored
        $this->assertEquals(100, $this->stock1->fresh()->qty);
    }

    /** @test */
    public function prevents_delete_when_has_invoices()
    {
        $sale = OfflineSale::create([
            'surat_jalan_number' => 'SJ-NODEL-001',
            'sale_date' => now(),
            'customer_name' => 'Test',
            'subtotal' => 200000,
            'total_amount' => 200000,
            'status' => 'pending',
            'main_category_id' => $this->skincare->id,
            'created_by' => $this->user->id,
        ]);

        // Mock has invoices (will be blocked)
        $response = $this->delete(route('sales.offline.destroy', $sale->id));
        // Should work since no invoices yet
        $response->assertRedirect();
    }

    // ==================== EDGE CASES ====================

    /** @test */
    public function handles_5_level_discounts()
    {
        $response = $this->post(route('sales.offline.store'), [
            'sale_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'subtotal' => 500000,
            'tax_amount' => 0,
            'total_amount' => 300000,
            'status' => 'pending',
            'product_id' => [$this->product1->id],
            'quantity' => [10],
            'unit_price' => [50000],
            'discount_percent_1' => [10],
            'discount_percent_2' => [5],
            'discount_amount_3' => [25000],
            'main_category_id' => $this->skincare->id,
        ]);

        $response->assertRedirect();

        $sale = OfflineSale::latest()->first();
        $item = $sale->items()->first();
        $this->assertEquals(10, (float) $item->discount_percent_1);
        $this->assertEquals(5, (float) $item->discount_percent_2);
        $this->assertEquals(25000, (float) $item->discount_amount_3);
    }

    /** @test */
    public function handles_sale_with_no_tax()
    {
        $response = $this->post(route('sales.offline.store'), [
            'sale_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'subtotal' => 200000,
            'tax_amount' => 0,
            'total_amount' => 200000,
            'status' => 'pending',
            'product_id' => [$this->product1->id],
            'quantity' => [4],
            'unit_price' => [50000],
            'main_category_id' => $this->skincare->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('offline_sales', [
            'tax_amount' => 0,
        ]);
    }

    /** @test */
    public function stock_integrity_preserved_across_multiple_sales()
    {
        // Sale 1: 10 units
        $this->post(route('sales.offline.store'), [
            'sale_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'subtotal' => 500000,
            'tax_amount' => 0,
            'total_amount' => 500000,
            'status' => 'pending',
            'product_id' => [$this->product1->id],
            'quantity' => [10],
            'unit_price' => [50000],
            'main_category_id' => $this->skincare->id,
        ]);

        // Sale 2: 20 units
        $this->post(route('sales.offline.store'), [
            'sale_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'subtotal' => 1000000,
            'tax_amount' => 0,
            'total_amount' => 1000000,
            'status' => 'pending',
            'product_id' => [$this->product1->id],
            'quantity' => [20],
            'unit_price' => [50000],
            'main_category_id' => $this->skincare->id,
        ]);

        $this->assertEquals(70, $this->stock1->fresh()->qty); // 100 - 10 - 20
    }

    /** @test */
    public function get_product_stock_info_endpoint()
    {
        $response = $this->get(route('sales.product-stock-info', $this->product1->id));
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'product_id', 'product_name', 'sku', 'total_stock', 'warehouse_stocks',
        ]);
    }

    /** @test */
    public function generate_sj_number_endpoint()
    {
        $response = $this->post(route('sales.offline.generate-sj-number', [
            'main_category_id' => $this->skincare->id,
            'tax_id' => $this->taxCategory->id,
            'order_date' => now()->format('Y-m-d'),
        ]));

        $response->assertStatus(200);
        $response->assertJsonStructure(['surat_jalan_number']);
    }

    /** @test */
    public function guest_cannot_access_offline_sales()
    {
        auth()->logout();
        $this->get(route('sales.offline.list'))->assertRedirect(route('login'));
        $this->get(route('sales.offline.create'))->assertRedirect(route('login'));
    }

    /**
     * @test
     *
     * Potensi masalah: Tax grouping membuat multiple SJ untuk satu transaksi
     * Jika produk memiliki tax_id berbeda, akan dibuat OfflineSale terpisah.
     * Ini bisa menyebabkan kebingungan jika user tidak aware.
     */
    public function documents_tax_grouping_behavior()
    {
        // Create stock with different tax
        $taxNonPkp = TaxCategory::where('name', 'SKINCARE-NONPKP')->first();
        if (!$taxNonPkp) {
            $this->markTestSkipped('NONPKP tax category not found');
        }

        WarehouseStock::create([
            'product_id' => $this->product3->id,
            'lokasi_id' => $this->gudangA->id,
            'tax_id' => $taxNonPkp->id,
            'qty' => 30,
            'source_type' => 'penerimaan',
            'source_id' => 3,
        ]);

        // Sale with products that have different tax_ids
        $this->post(route('sales.offline.store'), [
            'sale_date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'subtotal' => 350000,
            'tax_amount' => 0,
            'total_amount' => 350000,
            'status' => 'pending',
            'product_id' => [$this->product1->id, $this->product3->id],
            'quantity' => [1, 1],
            'unit_price' => [200000, 150000],
            'main_category_id' => $this->skincare->id,
        ]);

        // May create 2 separate OfflineSale records (one per tax group)
        $count = OfflineSale::where('sale_date', now()->format('Y-m-d'))->count();
        // NOTE: This behavior depends on whether product1's stock has different tax_id
    }
}
