<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Penerimaan;
use App\Models\PenerimaanDetail;
use App\Models\PenerimaanActivity;
use App\Models\Product;
use App\Models\MainCategory;
use App\Models\TaxCategory;
use App\Models\Lokasi;
use App\Models\Satuan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Feature Test: Penerimaan (Goods Receipt) - SKINCARE
 *
 * Menguji seluruh alur CRUD:
 * 1. Index page - list penerimaan with filters
 * 2. Create form tampil
 * 3. Store penerimaan dengan batch items
 * 4. Store dengan free items (barang gratis)
 * 5. Store dengan diskon bertingkat
 * 6. Show detail penerimaan
 * 7. Edit & Update penerimaan
 * 8. Delete penerimaan
 * 9. Print penerimaan
 * 10. Export Excel
 * 11. Authorization - user tanpa permission
 * 12. Edge cases: empty items, invalid data
 * 13. Stock integrity setelah penerimaan
 * 14. Filter combinations
 */
class PenerimaanTest extends TestCase
{

    private User $user;
    private MainCategory $skincare;
    private TaxCategory $taxCategory;
    private Lokasi $lokasi;
    private Satuan $satuan;
    private Product $product1;
    private Product $product2;

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
        $this->lokasi = Lokasi::first();
        $this->satuan = Satuan::where('is_active', true)->first();

        // Create test products
        $this->product1 = Product::factory()->create([
            'name' => 'Sabun Wajah SKINCARE',
            'main_category_id' => $this->skincare->id,
            'is_active' => true,
        ]);

        $this->product2 = Product::factory()->create([
            'name' => 'Toner Wajah SKINCARE',
            'main_category_id' => $this->skincare->id,
            'is_active' => true,
        ]);

        // Login as admin with SKINCARE
        $this->user = $this->loginAsSuperadmin();
    }

    // ==================== INDEX ====================

    /** @test */
    public function index_page_displays_penerimaan_list()
    {
        // Create test data
        Penerimaan::factory()->count(5)->create([
            'main_category_id' => $this->skincare->id,
            'tax_category_id' => $this->taxCategory->id,
        ]);

        $response = $this->get(route('penerimaan.index'));

        $response->assertStatus(200);
        $response->assertViewIs('penerimaan.index');
        $response->assertViewHas('penerimaan');
    }

    /** @test */
    public function index_page_shows_empty_state_when_no_data()
    {
        $response = $this->get(route('penerimaan.index'));

        $response->assertStatus(200);
        // Should show empty message or table with no rows
    }

    /** @test */
    public function index_filters_by_kode_penerimaan()
    {
        Penerimaan::factory()->create([
            'kode_penerimaan' => 'PNR-SEARCH-001',
            'main_category_id' => $this->skincare->id,
        ]);
        Penerimaan::factory()->create([
            'kode_penerimaan' => 'PNR-OTHER-002',
            'main_category_id' => $this->skincare->id,
        ]);

        $response = $this->get(route('penerimaan.index', ['kode' => 'SEARCH']));

        $response->assertStatus(200);
        $penerimaan = $response->original->getData()['penerimaan'];
        $this->assertCount(1, $penerimaan);
        $this->assertEquals('PNR-SEARCH-001', $penerimaan->first()->kode_penerimaan);
    }

    /** @test */
    public function index_filters_by_status()
    {
        Penerimaan::factory()->create([
            'status' => 'Unlocated',
            'main_category_id' => $this->skincare->id,
        ]);
        Penerimaan::factory()->create([
            'status' => 'Located',
            'main_category_id' => $this->skincare->id,
        ]);

        $response = $this->get(route('penerimaan.index', ['status' => 'Unlocated']));

        $response->assertStatus(200);
        $penerimaan = $response->original->getData()['penerimaan'];
        foreach ($penerimaan as $p) {
            $this->assertEquals('Unlocated', $p->status);
        }
    }

    /** @test */
    public function index_filters_by_date_range()
    {
        Penerimaan::factory()->create([
            'tanggal_penerimaan' => '2024-01-15',
            'main_category_id' => $this->skincare->id,
        ]);
        Penerimaan::factory()->create([
            'tanggal_penerimaan' => '2024-06-15',
            'main_category_id' => $this->skincare->id,
        ]);

        $response = $this->get(route('penerimaan.index', [
            'start_date' => '2024-01-01',
            'end_date' => '2024-03-31',
        ]));

        $response->assertStatus(200);
    }

    /** @test */
    public function index_filters_by_nomor_po()
    {
        Penerimaan::factory()->create([
            'nomor_po' => 'PO-2024-001',
            'main_category_id' => $this->skincare->id,
        ]);

        $response = $this->get(route('penerimaan.index', ['nomor_po' => 'PO-2024']));

        $response->assertStatus(200);
    }

    /** @test */
    public function index_combines_multiple_filters()
    {
        Penerimaan::factory()->create([
            'kode_penerimaan' => 'PNR-MULTI-001',
            'status' => 'Unlocated',
            'main_category_id' => $this->skincare->id,
            'tanggal_penerimaan' => '2024-01-15',
        ]);
        Penerimaan::factory()->create([
            'kode_penerimaan' => 'PNR-MULTI-002',
            'status' => 'Located',
            'main_category_id' => $this->skincare->id,
            'tanggal_penerimaan' => '2024-06-15',
        ]);

        // All filters combined
        $response = $this->get(route('penerimaan.index', [
            'kode' => 'MULTI',
            'status' => 'Unlocated',
            'start_date' => '2024-01-01',
            'end_date' => '2024-02-28',
        ]));

        $response->assertStatus(200);
    }

    // ==================== CREATE ====================

    /** @test */
    public function create_page_displays_form()
    {
        $response = $this->get(route('penerimaan.create'));

        $response->assertStatus(200);
        $response->assertViewIs('penerimaan.create');
    }

    /** @test */
    public function store_creates_new_penerimaan_with_items()
    {
        $data = [
            'main_category_id' => $this->skincare->id,
            'tax_category_id' => $this->taxCategory->id,
            'kode_penerimaan' => 'PNR-TEST-STORE-001',
            'nomor_po' => 'PO-TEST-001',
            'tanggal_penerimaan' => now()->format('Y-m-d'),
            'metode_pembayaran' => 'Cash',
            'barang_id' => [$this->product1->id, $this->product2->id],
            'qty' => ['10', '20'],
            'satuan_id' => [$this->satuan->id, $this->satuan->id],
            'harga_hpp' => ['50000', '75000'],
        ];

        $response = $this->post(route('penerimaan.store'), $data);

        $response->assertRedirect(route('penerimaan.index'));
        $response->assertSessionHas('success');

        // Verify database
        $this->assertDatabaseHas('penerimaan', [
            'kode_penerimaan' => 'PNR-TEST-STORE-001',
            'status' => 'Unlocated',
        ]);

        $this->assertDatabaseHas('penerimaan_detail', [
            'product_id' => $this->product1->id,
            'qty' => '10.00',
        ]);
    }

    /** @test */
    public function store_with_free_items()
    {
        $data = [
            'main_category_id' => $this->skincare->id,
            'tax_category_id' => $this->taxCategory->id,
            'kode_penerimaan' => 'PNR-FREE-TEST-001',
            'nomor_po' => 'PO-FREE-001',
            'tanggal_penerimaan' => now()->format('Y-m-d'),
            'metode_pembayaran' => 'Cash',
            'barang_id' => [$this->product1->id, $this->product2->id],
            'qty' => ['10', '5'],
            'satuan_id' => [$this->satuan->id, $this->satuan->id],
            'harga_hpp' => ['50000', '0'],
            'is_free' => [0, 1],
        ];

        $response = $this->post(route('penerimaan.store'), $data);

        $response->assertRedirect(route('penerimaan.index'));

        // Verify free item has subtotal = 0
        $penerimaan = Penerimaan::where('kode_penerimaan', 'PNR-FREE-TEST-001')->first();
        $freeDetail = $penerimaan->details()->where('is_free', true)->first();
        $this->assertNotNull($freeDetail);
        $this->assertEquals(0, (int) $freeDetail->subtotal);
    }

    /** @test */
    public function store_with_discounts()
    {
        $data = [
            'main_category_id' => $this->skincare->id,
            'tax_category_id' => $this->taxCategory->id,
            'kode_penerimaan' => 'PNR-DISC-TEST-001',
            'nomor_po' => 'PO-DISC-001',
            'tanggal_penerimaan' => now()->format('Y-m-d'),
            'metode_pembayaran' => 'Cash',
            'barang_id' => [$this->product1->id],
            'qty' => ['100'],
            'satuan_id' => [$this->satuan->id],
            'harga_hpp' => ['10000'],
            'diskon_persen_1' => ['10'],
        ];

        $response = $this->post(route('penerimaan.store'), $data);

        $response->assertRedirect(route('penerimaan.index'));

        // Verify discount was applied
        $penerimaan = Penerimaan::where('kode_penerimaan', 'PNR-DISC-TEST-001')->first();
        $detail = $penerimaan->details()->first();
        $this->assertNotNull($detail);
        $this->assertEquals('10.00', (string) $detail->diskon_persen_1);
        // 100 * 10000 = 1,000,000 - 10% = 900,000
        $this->assertEquals(900000, (int) $detail->subtotal);
    }

    /** @test */
    public function store_with_jatuh_tempo_payment()
    {
        $data = [
            'main_category_id' => $this->skincare->id,
            'tax_category_id' => $this->taxCategory->id,
            'kode_penerimaan' => 'PNR-JT-TEST-001',
            'nomor_po' => 'PO-JT-001',
            'tanggal_penerimaan' => now()->format('Y-m-d'),
            'metode_pembayaran' => 'Jatuh Tempo',
            'tanggal_jatuh_tempo' => now()->addDays(30)->format('Y-m-d'),
            'barang_id' => [$this->product1->id],
            'qty' => ['10'],
            'satuan_id' => [$this->satuan->id],
            'harga_hpp' => ['50000'],
        ];

        $response = $this->post(route('penerimaan.store'), $data);

        $response->assertRedirect(route('penerimaan.index'));

        $penerimaan = Penerimaan::where('kode_penerimaan', 'PNR-JT-TEST-001')->first();
        $this->assertEquals('Jatuh Tempo', $penerimaan->metode_pembayaran);
        $this->assertNotNull($penerimaan->tanggal_jatuh_tempo);
    }

    /** @test */
    public function store_starts_with_unlocated_status()
    {
        $data = [
            'main_category_id' => $this->skincare->id,
            'tax_category_id' => $this->taxCategory->id,
            'kode_penerimaan' => 'PNR-STATUS-UNLOCATED',
            'nomor_po' => 'PO-STATUS-001',
            'tanggal_penerimaan' => now()->format('Y-m-d'),
            'metode_pembayaran' => 'Cash',
            'barang_id' => [$this->product1->id],
            'qty' => ['10'],
            'satuan_id' => [$this->satuan->id],
            'harga_hpp' => ['50000'],
        ];

        $this->post(route('penerimaan.store'), $data);

        $this->assertDatabaseHas('penerimaan', [
            'kode_penerimaan' => 'PNR-STATUS-UNLOCATED',
            'status' => 'Unlocated',
        ]);
    }

    // ==================== SHOW ====================

    /** @test */
    public function show_page_displays_penerimaan_detail()
    {
        $penerimaan = Penerimaan::factory()->create([
            'kode_penerimaan' => 'PNR-SHOW-001',
            'main_category_id' => $this->skincare->id,
            'tax_category_id' => $this->taxCategory->id,
        ]);

        PenerimaanDetail::create([
            'penerimaan_id' => $penerimaan->id,
            'product_id' => $this->product1->id,
            'qty' => 10,
            'satuan_id' => $this->satuan->id,
            'harga_hpp' => 50000,
            'subtotal' => 500000,
        ]);

        $response = $this->get(route('penerimaan.show', $penerimaan->id));

        $response->assertStatus(200);
        $response->assertViewIs('penerimaan.show');
    }

    /** @test */
    public function show_returns_404_for_nonexistent_penerimaan()
    {
        $response = $this->get(route('penerimaan.show', 99999));
        $response->assertStatus(404);
    }

    // ==================== EDIT & UPDATE ====================

    /** @test */
    public function edit_page_displays_form()
    {
        $penerimaan = Penerimaan::factory()->create([
            'kode_penerimaan' => 'PNR-EDIT-001',
            'main_category_id' => $this->skincare->id,
            'tax_category_id' => $this->taxCategory->id,
        ]);

        $response = $this->get(route('penerimaan.edit', $penerimaan->id));

        $response->assertStatus(200);
        $response->assertViewIs('penerimaan.edit');
    }

    /** @test */
    public function update_modifies_penerimaan()
    {
        $penerimaan = Penerimaan::factory()->create([
            'kode_penerimaan' => 'PNR-UPDATE-001',
            'main_category_id' => $this->skincare->id,
            'tax_category_id' => $this->taxCategory->id,
            'catatan' => 'Original note',
        ]);

        $response = $this->put(route('penerimaan.update', $penerimaan->id), [
            'catatan' => 'Updated note',
            'nomor_po' => 'PO-UPDATED-001',
            'tanggal_penerimaan' => now()->format('Y-m-d'),
            'metode_pembayaran' => 'Cash',
            'main_category_id' => $this->skincare->id,
            'tax_category_id' => $this->taxCategory->id,
            'barang_id' => [$this->product1->id],
            'qty' => [10],
            'satuan_id' => [$this->satuan->id],
            'harga_hpp' => [15000],
        ]);

        $response->assertRedirect(route('penerimaan.index'));
        $this->assertDatabaseHas('penerimaan', [
            'id' => $penerimaan->id,
            'catatan' => 'Updated note',
        ]);
    }

    // ==================== DELETE ====================

    /** @test */
    public function destroy_removes_penerimaan()
    {
        $penerimaan = Penerimaan::factory()->create([
            'kode_penerimaan' => 'PNR-DELETE-001',
            'main_category_id' => $this->skincare->id,
            'tax_category_id' => $this->taxCategory->id,
        ]);

        $response = $this->delete(route('penerimaan.destroy', $penerimaan->id));

        $response->assertRedirect(route('penerimaan.index'));
        $this->assertDatabaseMissing('penerimaan', ['id' => $penerimaan->id]);
    }

    // ==================== PRINT ====================

    /** @test */
    public function print_page_displays_penerimaan_print_view()
    {
        $penerimaan = Penerimaan::factory()->create([
            'kode_penerimaan' => 'PNR-PRINT-001',
            'main_category_id' => $this->skincare->id,
            'tax_category_id' => $this->taxCategory->id,
        ]);

        $response = $this->get(route('penerimaan.print', $penerimaan->id));

        $response->assertStatus(200);
    }

    // ==================== EXPORT ====================

    /** @test */
    public function export_excel_returns_file()
    {
        Penerimaan::factory()->create([
            'kode_penerimaan' => 'PNR-EXPORT-001',
            'main_category_id' => $this->skincare->id,
            'tax_category_id' => $this->taxCategory->id,
        ]);

        $response = $this->get(route('penerimaan.export'));

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );
    }

    /** @test */
    public function export_detail_excel_returns_file()
    {
        $penerimaan = Penerimaan::factory()->create([
            'kode_penerimaan' => 'PNR-EXPORT-DTL',
            'main_category_id' => $this->skincare->id,
            'tax_category_id' => $this->taxCategory->id,
        ]);

        PenerimaanDetail::create([
            'penerimaan_id' => $penerimaan->id,
            'product_id' => $this->product1->id,
            'qty' => 10,
            'satuan_id' => $this->satuan->id,
            'harga_hpp' => 50000,
            'subtotal' => 500000,
        ]);

        $response = $this->get(route('penerimaan.export-detail'));

        $response->assertStatus(200);
    }

    // ==================== AJAX ====================

    /** @test */
    public function get_products_endpoint_returns_json()
    {
        $response = $this->get(route('penerimaan.get-products', [
            'main_category_id' => $this->skincare->id,
        ]));

        $response->assertStatus(200);
        $response->assertJsonStructure(['*' => ['id', 'text']]);
    }

    /** @test */
    public function get_products_filters_by_search()
    {
        $response = $this->get(route('penerimaan.get-products', [
            'main_category_id' => $this->skincare->id,
            'search' => 'Sabun',
        ]));

        $response->assertStatus(200);
    }

    /** @test */
    public function get_tax_categories_endpoint_returns_json()
    {
        $response = $this->get(route('penerimaan.get-tax-categories', [
            'main_category_id' => $this->skincare->id,
        ]));

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'tax_categories']);
    }

    // ==================== AUTHORIZATION ====================

    /** @test */
    public function guest_cannot_access_penerimaan_pages()
    {
        auth()->logout();

        $this->get(route('penerimaan.index'))->assertRedirect(route('login'));
        $this->get(route('penerimaan.create'))->assertRedirect(route('login'));
        $this->post(route('penerimaan.store'), [])->assertRedirect(route('login'));
    }

    // ==================== EDGE CASES ====================

    /** @test */
    public function store_fails_with_empty_items()
    {
        $response = $this->post(route('penerimaan.store'), [
            'main_category_id' => $this->skincare->id,
            'tax_category_id' => $this->taxCategory->id,
            'kode_penerimaan' => 'PNR-EMPTY-ITEMS',
            'nomor_po' => 'PO-EMPTY',
            'tanggal_penerimaan' => now()->format('Y-m-d'),
            'metode_pembayaran' => 'Cash',
            'barang_id' => [],
            'qty' => [],
            'satuan_id' => [],
            'harga_hpp' => [],
        ]);

        $response->assertSessionHasErrors('barang_id');
    }

    /** @test */
    public function handles_duplicate_kode_penerimaan_gracefully()
    {
        Penerimaan::factory()->create([
            'kode_penerimaan' => 'PNR-DUP-001',
            'main_category_id' => $this->skincare->id,
        ]);

        $response = $this->post(route('penerimaan.store'), [
            'main_category_id' => $this->skincare->id,
            'tax_category_id' => $this->taxCategory->id,
            'kode_penerimaan' => 'PNR-DUP-001', // Duplicate
            'nomor_po' => 'PO-DUP',
            'tanggal_penerimaan' => now()->format('Y-m-d'),
            'metode_pembayaran' => 'Cash',
            'barang_id' => [$this->product1->id],
            'qty' => ['10'],
            'satuan_id' => [$this->satuan->id],
            'harga_hpp' => ['50000'],
        ]);

        $response->assertSessionHasErrors('kode_penerimaan');
    }

    /** @test */
    public function price_history_endpoint_works()
    {
        $response = $this->get(route('penerimaan.price-history', $this->product1->id));

        $response->assertStatus(200);
    }

    /** @test */
    public function penerimaan_creates_activity_log()
    {
        $this->post(route('penerimaan.store'), [
            'main_category_id' => $this->skincare->id,
            'tax_category_id' => $this->taxCategory->id,
            'kode_penerimaan' => 'PNR-ACT-LOG-001',
            'nomor_po' => 'PO-ACT',
            'tanggal_penerimaan' => now()->format('Y-m-d'),
            'metode_pembayaran' => 'Cash',
            'barang_id' => [$this->product1->id],
            'qty' => ['10'],
            'satuan_id' => [$this->satuan->id],
            'harga_hpp' => ['50000'],
        ]);

        $penerimaan = Penerimaan::where('kode_penerimaan', 'PNR-ACT-LOG-001')->first();

        $this->assertDatabaseHas('penerimaan_activities', [
            'penerimaan_id' => $penerimaan->id,
        ]);
    }
}
