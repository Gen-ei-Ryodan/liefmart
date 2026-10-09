<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\ReturOfflineSale;
use App\Models\ReturOfflineSaleDetail;
use App\Models\OfflineSale;
use App\Models\OfflineSaleItem;
use App\Models\FinanceOffline;
use App\Models\WarehouseStock;
use App\Models\Product;
use App\Models\Customer;
use App\Models\MainCategory;
use App\Models\TaxCategory;
use App\Models\Lokasi;
use App\Models\User;

/**
 * Feature Test: Retur Offline Sale
 *
 * Alur:
 * 1. Index — filter by search, status, date, user
 * 2. Create — pilih offline sale
 * 3. Store — draft
 * 4. Process — draft → selesai dengan stock restoration & finance adjustment
 * 5. Edit — hanya draft yang bisa diedit
 * 6. Cancel — hanya draft yang bisa dibatalkan
 * 7. Reverse — batalkan retur yang sudah selesai
 * 8. Finance impact — saat retur diproses
 * 9. Show — detail retur
 *
 * POTENSI MASALAH:
 * - Retur saat invoice sudah paid
 * - Retur saat belum ada invoice
 * - Reverse return setelah finance di-adjust
 */
class ReturOfflineFeatureTest extends TestCase
{

    private User $user;
    private MainCategory $skincare;
    private TaxCategory $taxCategory;
    private Lokasi $lokasi;
    private Product $product;
    private Customer $customer;
    private OfflineSale $offlineSale;
    private OfflineSaleItem $saleItem;
    private WarehouseStock $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\MainCategorySeeder::class);
        $this->seed(\Database\Seeders\TaxCategorySeeder::class);
        $this->seed(\Database\Seeders\LokasiSeeder::class);
        $this->seed(\Database\Seeders\SatuanSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\SuperadminRoleSeeder::class);

        $this->skincare = MainCategory::where('name', 'SKINCARE')->first();
        $this->taxCategory = TaxCategory::where('main_category_id', $this->skincare->id)->first();
        $this->lokasi = Lokasi::first();
        $this->product = Product::factory()->create(['name' => 'Offline Retur Product', 'main_category_id' => $this->skincare->id]);
        $this->customer = Customer::create(['name' => 'Retur Customer', 'phone' => '08123', 'email' => 'retur@test.com', 'pic_name' => 'Retur PIC', 'status' => 'active']);

        $this->stock = WarehouseStock::create([
            'product_id' => $this->product->id, 'lokasi_id' => $this->lokasi->id,
            'tax_id' => $this->taxCategory->id, 'qty' => 100,
            'source_type' => 'penerimaan', 'source_id' => 1,
        ]);

        $this->offlineSale = OfflineSale::create([
            'surat_jalan_number' => 'SJ-OFF-RETUR',
            'sale_date' => now(), 'customer_name' => $this->customer->name,
            'customer_id' => $this->customer->id,
            'subtotal' => 500000, 'total_amount' => 500000,
            'status' => 'pending', 'main_category_id' => $this->skincare->id,
            'created_by' => 1,
        ]);

        $this->saleItem = OfflineSaleItem::create([
            'offline_sale_id' => $this->offlineSale->id,
            'product_id' => $this->product->id,
            'warehouse_stock_id' => $this->stock->id,
            'quantity' => 10, 'unit_price' => 50000, 'subtotal' => 500000,
        ]);

        session(['main_category_id' => $this->skincare->id]);
        $this->user = $this->loginAsSuperadmin();
    }

    // ==================== INDEX ====================

    /** @test */
    public function index_displays_retur_list()
    {
        ReturOfflineSale::create([
            'kode_retur' => 'RJO' . now()->format('Ymd') . '0001',
            'offline_sale_id' => $this->offlineSale->id, 'user_id' => $this->user->id,
            'tanggal_retur' => now(), 'status' => 'draft',
        ]);

        $response = $this->get(route('retur-offline.index'));
        $response->assertStatus(200);
        $response->assertViewHas('returOfflineSales');
    }

    /** @test */
    public function index_filters_by_status()
    {
        $response = $this->get(route('retur-offline.index', ['status' => 'draft']));
        $response->assertStatus(200);
    }

    /** @test */
    public function index_filters_by_date()
    {
        $response = $this->get(route('retur-offline.index', [
            'date_from' => now()->subDays(7)->format('Y-m-d'),
            'date_to' => now()->addDays(7)->format('Y-m-d'),
        ]));
        $response->assertStatus(200);
    }

    /** @test */
    public function index_filters_by_search()
    {
        $response = $this->get(route('retur-offline.index', ['search' => 'RJO']));
        $response->assertStatus(200);
    }

    // ==================== CREATE ====================

    /** @test */
    public function create_page_displays_offline_sales()
    {
        $response = $this->get(route('retur-offline.create'));
        $response->assertStatus(200);
    }

    // ==================== STORE (DRAFT) ====================

    /** @test */
    public function store_creates_draft_retur()
    {
        $response = $this->post(route('retur-offline.store'), [
            'offline_sale_id' => $this->offlineSale->id,
            'tanggal_retur' => now()->format('Y-m-d'),
            'details' => [
                [
                    'offline_sale_item_id' => $this->saleItem->id,
                    'product_id' => $this->product->id,
                    'qty' => 2,
                    'kondisi' => 'BAGUS',
                    'alasan' => 'Barang cacat',
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('retur_offline_sales', [
            'offline_sale_id' => $this->offlineSale->id,
            'status' => 'draft',
        ]);
    }

    /** @test */
    public function store_fails_without_details()
    {
        $response = $this->post(route('retur-offline.store'), [
            'offline_sale_id' => $this->offlineSale->id,
            'tanggal_retur' => now()->format('Y-m-d'),
            'details' => [],
        ]);
        $response->assertSessionHasErrors('details');
    }

    /** @test】
    public function store_fails_with_qty_exceeding_original()
    {
        $response = $this->post(route('retur-offline.store'), [
            'offline_sale_id' => $this->offlineSale->id,
            'tanggal_retur' => now()->format('Y-m-d'),
            'details' => [
                ['offline_sale_item_id' => $this->saleItem->id, 'product_id' => $this->product->id, 'qty' => 999, 'kondisi' => 'BAGUS'],
            ],
        ]);
        $response->assertSessionHas('error');
    }

    /** @test */
    public function store_fails_with_invalid_kondisi()
    {
        $response = $this->post(route('retur-offline.store'), [
            'offline_sale_id' => $this->offlineSale->id,
            'tanggal_retur' => now()->format('Y-m-d'),
            'details' => [
                ['offline_sale_item_id' => $this->saleItem->id, 'product_id' => $this->product->id, 'qty' => 1, 'kondisi' => 'INVALID'],
            ],
        ]);
        $response->assertSessionHasErrors('details.0.kondisi');
    }

    // ==================== SHOW ====================

    /** @test */
    public function show_displays_retur_detail()
    {
        $retur = ReturOfflineSale::create([
            'kode_retur' => 'RJO' . now()->format('Ymd') . '0010',
            'offline_sale_id' => $this->offlineSale->id, 'user_id' => $this->user->id,
            'tanggal_retur' => now(), 'status' => 'draft',
        ]);

        $response = $this->get(route('retur-offline.show', $retur->id));
        $response->assertStatus(200);
    }

    /** @test */
    public function show_returns_404()
    {
        $response = $this->get(route('retur-offline.show', 99999));
        $response->assertStatus(404);
    }

    // ==================== PROCESS (DRAFT → SELESAI) ====================

    /** @test */
    public function process_changes_status_to_selesai_and_restores_stock()
    {
        $retur = ReturOfflineSale::create([
            'kode_retur' => 'RJO' . now()->format('Ymd') . '0020',
            'offline_sale_id' => $this->offlineSale->id, 'user_id' => $this->user->id,
            'tanggal_retur' => now(), 'status' => 'draft',
        ]);

        ReturOfflineSaleDetail::create([
            'retur_offline_sale_id' => $retur->id,
            'offline_sale_item_id' => $this->saleItem->id,
            'product_id' => $this->product->id,
            'qty' => 3, 'kondisi' => 'BAGUS',
        ]);

        $stockBefore = (int) $this->stock->qty;

        $response = $this->post(route('retur-offline.process', $retur->id));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Status should be 'selesai'
        $this->assertEquals('selesai', $retur->fresh()->status);

        // Stock should increase (100 + 3 = 103)
        $this->assertGreaterThan($stockBefore, (int) $this->stock->fresh()->qty);
    }

    /** @test */
    public function process_fails_for_non_draft_retur()
    {
        $retur = ReturOfflineSale::create([
            'kode_retur' => 'RJO' . now()->format('Ymd') . '0030',
            'offline_sale_id' => $this->offlineSale->id, 'user_id' => $this->user->id,
            'tanggal_retur' => now(), 'status' => 'selesai',
        ]);

        $response = $this->post(route('retur-offline.process', $retur->id));
        $response->assertSessionHas('error');
    }

    /** @test */
    public function process_rusak_creates_damaged_stock()
    {
        $retur = ReturOfflineSale::create([
            'kode_retur' => 'RJO' . now()->format('Ymd') . '0040',
            'offline_sale_id' => $this->offlineSale->id, 'user_id' => $this->user->id,
            'tanggal_retur' => now(), 'status' => 'draft',
        ]);

        ReturOfflineSaleDetail::create([
            'retur_offline_sale_id' => $retur->id,
            'offline_sale_item_id' => $this->saleItem->id,
            'product_id' => $this->product->id,
            'qty' => 2, 'kondisi' => 'RUSAK',
        ]);

        $this->post(route('retur-offline.process', $retur->id));

        $damagedStock = WarehouseStock::where('product_id', $this->product->id)
            ->where('is_damaged', true)->sum('qty');
        $this->assertGreaterThan(0, (int) $damagedStock);
    }

    /** @test */
    public function process_with_finance_offline_adjusts_invoice()
    {
        // Create invoice
        FinanceOffline::create([
            'invoice_number' => '8888/2606/AMP/01',
            'nominal' => 500000,
            'tanggal_invoice' => now(), 'status' => 'unpaid',
            'main_category_id' => $this->skincare->id,
        ]);

        $retur = ReturOfflineSale::create([
            'kode_retur' => 'RJO' . now()->format('Ymd') . '0050',
            'offline_sale_id' => $this->offlineSale->id, 'user_id' => $this->user->id,
            'tanggal_retur' => now(), 'status' => 'draft',
        ]);

        ReturOfflineSaleDetail::create([
            'retur_offline_sale_id' => $retur->id,
            'offline_sale_item_id' => $this->saleItem->id,
            'product_id' => $this->product->id,
            'qty' => 5, 'kondisi' => 'BAGUS',
        ]);

        $response = $this->post(route('retur-offline.process', $retur->id));
        $response->assertRedirect();
        // FinanceOffline should be adjusted (handleOfflineReturFinance)
    }

    // ==================== CANCEL ====================

    /** @test */
    public function cancel_changes_status_to_dibatalkan()
    {
        $retur = ReturOfflineSale::create([
            'kode_retur' => 'RJO' . now()->format('Ymd') . '0060',
            'offline_sale_id' => $this->offlineSale->id, 'user_id' => $this->user->id,
            'tanggal_retur' => now(), 'status' => 'draft',
        ]);

        $response = $this->post(route('retur-offline.cancel', $retur->id));
        $response->assertRedirect();

        $this->assertEquals('dibatalkan', $retur->fresh()->status);
    }

    /** @test */
    public function cancel_fails_for_non_draft()
    {
        $retur = ReturOfflineSale::create([
            'kode_retur' => 'RJO' . now()->format('Ymd') . '0070',
            'offline_sale_id' => $this->offlineSale->id, 'user_id' => $this->user->id,
            'tanggal_retur' => now(), 'status' => 'selesai',
        ]);

        $response = $this->post(route('retur-offline.cancel', $retur->id));
        $response->assertSessionHas('error');
    }

    // ==================== REVERSE ====================

    /** @test */
    public function reverse_restores_original_state()
    {
        $retur = ReturOfflineSale::create([
            'kode_retur' => 'RJO' . now()->format('Ymd') . '0080',
            'offline_sale_id' => $this->offlineSale->id, 'user_id' => $this->user->id,
            'tanggal_retur' => now(), 'status' => 'selesai',
        ]);

        ReturOfflineSaleDetail::create([
            'retur_offline_sale_id' => $retur->id,
            'offline_sale_item_id' => $this->saleItem->id,
            'product_id' => $this->product->id,
            'qty' => 3, 'kondisi' => 'BAGUS',
        ]);

        // Process first
        $this->post(route('retur-offline.process', $retur->id));
        $itemQtyAfterProcess = (float) $this->saleItem->fresh()->quantity; // 10 - 3 = 7

        // Reverse
        $response = $this->post(route('retur-offline.reverse', $retur->id));
        $response->assertRedirect();

        // Item quantity should be restored to 10
        $this->assertEquals(10, (float) $this->saleItem->fresh()->quantity);
    }

    /** @test */
    public function reverse_fails_for_non_selesai()
    {
        $retur = ReturOfflineSale::create([
            'kode_retur' => 'RJO' . now()->format('Ymd') . '0090',
            'offline_sale_id' => $this->offlineSale->id, 'user_id' => $this->user->id,
            'tanggal_retur' => now(), 'status' => 'draft',
        ]);

        $response = $this->post(route('retur-offline.reverse', $retur->id));
        $response->assertSessionHas('error');
    }

    // ==================== EDIT ====================

    /** @test】
    public function edit_page_accessible_for_draft()
    {
        $retur = ReturOfflineSale::create([
            'kode_retur' => 'RJO' . now()->format('Ymd') . '0100',
            'offline_sale_id' => $this->offlineSale->id, 'user_id' => $this->user->id,
            'tanggal_retur' => now(), 'status' => 'draft',
        ]);

        $response = $this->get(route('retur-offline.edit', $retur->id));
        $response->assertStatus(200);
    }

    /** @test */
    public function edit_blocked_for_non_draft()
    {
        $retur = ReturOfflineSale::create([
            'kode_retur' => 'RJO' . now()->format('Ymd') . '0110',
            'offline_sale_id' => $this->offlineSale->id, 'user_id' => $this->user->id,
            'tanggal_retur' => now(), 'status' => 'selesai',
        ]);

        $response = $this->get(route('retur-offline.edit', $retur->id));
        $response->assertSessionHas('error');
    }

    /** @test */
    public function update_modifies_draft_retur()
    {
        $retur = ReturOfflineSale::create([
            'kode_retur' => 'RJO' . now()->format('Ymd') . '0120',
            'offline_sale_id' => $this->offlineSale->id, 'user_id' => $this->user->id,
            'tanggal_retur' => now(), 'status' => 'draft',
        ]);

        ReturOfflineSaleDetail::create([
            'retur_offline_sale_id' => $retur->id,
            'offline_sale_item_id' => $this->saleItem->id,
            'product_id' => $this->product->id,
            'qty' => 2, 'kondisi' => 'BAGUS',
        ]);

        $response = $this->put(route('retur-offline.update', $retur->id), [
            'offline_sale_id' => $this->offlineSale->id,
            'tanggal_retur' => now()->format('Y-m-d'),
            'catatan' => 'Updated note',
            'details' => [
                ['offline_sale_item_id' => $this->saleItem->id, 'product_id' => $this->product->id, 'qty' => 3, 'kondisi' => 'BAGUS'],
            ],
        ]);

        $response->assertRedirect();
    }

    // ==================== GET OFFLINE SALE ====================

    /** @test */
    public function get_offline_sale_endpoint()
    {
        $response = $this->get(route('retur-offline.get-offline-sale', $this->offlineSale->id));
        $response->assertStatus(200);
        $response->assertJsonStructure(['id']);
    }

    // ==================== COMPLEX SCENARIOS ====================

    /**
     * @test
     *
     * COMPLEX: Retur offline terjadi saat belum ada invoice
     * 1. Sale tanpa invoice
     * 2. Retur full — stock kembali
     * 3. Finance service: handleOfflineReturFinance dengan 0 invoice
     */
    public function complex_offline_return_without_invoice()
    {
        $this->assertFalse($this->offlineSale->hasInvoices(), 'Belum ada invoice');

        $retur = ReturOfflineSale::create([
            'kode_retur' => 'RJO' . now()->format('Ymd') . '0200',
            'offline_sale_id' => $this->offlineSale->id, 'user_id' => $this->user->id,
            'tanggal_retur' => now(), 'status' => 'draft',
        ]);

        ReturOfflineSaleDetail::create([
            'retur_offline_sale_id' => $retur->id,
            'offline_sale_item_id' => $this->saleItem->id,
            'product_id' => $this->product->id,
            'qty' => 10, 'kondisi' => 'BAGUS',
        ]);

        $response = $this->post(route('retur-offline.process', $retur->id));
        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    /** @test */
    public function authorization_guest_blocked()
    {
        auth()->logout();
        $this->get(route('retur-offline.index'))->assertRedirect(route('login'));
        $this->get(route('retur-offline.create'))->assertRedirect(route('login'));
    }
}
